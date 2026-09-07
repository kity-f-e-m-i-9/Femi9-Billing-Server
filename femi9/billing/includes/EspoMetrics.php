<?php
/**
 * Shared metrics engine for the EspoCRM sales dashboard.
 *
 * Every public function takes $espoUserId as its second parameter:
 *   null        -> whole-team aggregate (no assigned_user_id filter)
 *   '<espo id>' -> filtered to that one rep
 * This single parameter is what drives both the Sales BDM dashboard
 * (always passes a fixed id) and the Company dashboard (passes null for
 * the top KPI cards, then loops over each linked rep's id for the
 * per-rep table).
 *
 * All queries include "AND deleted = 0" per EspoCRM's soft-delete
 * convention (spec section 3.2).
 */

if (!function_exists('espoUserFilterClause')) {
    function espoUserFilterClause(?string $espoUserId, mysqli $conn): string {
        if ($espoUserId === null || $espoUserId === '') return '';
        $escaped = $conn->real_escape_string($espoUserId);
        return " AND assigned_user_id = '{$escaped}'";
    }
}

// Lead assignment in this EspoCRM instance is NOT stored on
// lead.assigned_user_id (verified live: that column is NULL on every
// lead row). Leads use EspoCRM's multi-assignee "Assigned Users" field
// instead, which lives in the generic entity_user junction table
// (entity_type='Lead'). Opportunity and Call both use the legacy single
// assigned_user_id column correctly (verified: populated, entity_user
// has zero rows for either entity type there) — this filter is scoped
// to Lead queries only, everything else keeps using
// espoUserFilterClause() above.
if (!function_exists('espoLeadUserJoinAndFilter')) {
    function espoLeadUserJoinAndFilter(?string $espoUserId, mysqli $conn, string $leadAlias = 'l'): array {
        if ($espoUserId === null || $espoUserId === '') {
            return ['join' => '', 'filter' => ''];
        }
        $escaped = $conn->real_escape_string($espoUserId);
        $join = " JOIN entity_user eu ON eu.entity_id = {$leadAlias}.id AND eu.entity_type = 'Lead' AND eu.deleted = 0";
        $filter = " AND eu.user_id = '{$escaped}'";
        return ['join' => $join, 'filter' => $filter];
    }
}

// ---- Internal helpers (table name is a parameter so tests can point them
//      at a fixture table; public functions below always pass the real
//      EspoCRM table names) ----

if (!function_exists('espoFunnelSnapshotFromLeadTable')) {
    function espoFunnelSnapshotFromLeadTable(mysqli $conn, string $table, ?string $espoUserId, string $dateFrom, string $dateTo): array {
        $from = $conn->real_escape_string($dateFrom);
        $to   = $conn->real_escape_string($dateTo);
        $leadUser = espoLeadUserJoinAndFilter($espoUserId, $conn, 'l');

        $sql = "SELECT l.status, COUNT(*) AS c FROM `{$table}` l
                {$leadUser['join']}
                WHERE l.deleted = 0 AND l.created_at BETWEEN '{$from}' AND '{$to} 23:59:59'
                {$leadUser['filter']}
                GROUP BY l.status";

        $counts = ['new' => 0, 'assigned' => 0, 'in_process' => 0, 'converted' => 0, 'recycled' => 0, 'dead' => 0];
        $statusMap = [
            'New' => 'new', 'Assigned' => 'assigned', 'In Process' => 'in_process',
            'Converted' => 'converted', 'Recycled' => 'recycled', 'Dead' => 'dead',
        ];

        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $key = $statusMap[$row['status']] ?? null;
                if ($key !== null) {
                    $counts[$key] = (int)$row['c'];
                }
            }
        }
        return $counts;
    }
}

if (!function_exists('espoCallsPerConversionRatio')) {
    function espoCallsPerConversionRatio(int $calls, int $conversions): float {
        if ($conversions === 0) return 0.0;
        return round($calls / $conversions, 2);
    }
}

// ---- Public API ----

if (!function_exists('espoFunnelSnapshot')) {
    function espoFunnelSnapshot(mysqli $conn, ?string $espoUserId, string $dateFrom, string $dateTo): array {
        $leadCounts = espoFunnelSnapshotFromLeadTable($conn, 'lead', $espoUserId, $dateFrom, $dateTo);

        $userFilter = espoUserFilterClause($espoUserId, $conn);
        $from = $conn->real_escape_string($dateFrom);
        $to   = $conn->real_escape_string($dateTo);
        $sql = "SELECT stage, COUNT(*) AS c FROM `opportunity`
                WHERE deleted = 0 AND created_at BETWEEN '{$from}' AND '{$to} 23:59:59'
                {$userFilter}
                GROUP BY stage";
        $oppStages = [];
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $oppStages[$row['stage']] = (int)$row['c'];
            }
        }

        $leadCounts['opp_stages'] = $oppStages;
        return $leadCounts;
    }
}

if (!function_exists('espoConversionTrend')) {
    function espoConversionTrend(mysqli $conn, ?string $espoUserId, string $dateFrom, string $dateTo, string $granularity = 'monthly'): array {
        $userFilter = espoUserFilterClause($espoUserId, $conn);
        $from = $conn->real_escape_string($dateFrom);
        $to   = $conn->real_escape_string($dateTo);
        $dateFormat = $granularity === 'weekly' ? '%x-W%v' : '%Y-%m';
        $leadUser = espoLeadUserJoinAndFilter($espoUserId, $conn, 'l');

        $leadSql = "SELECT DATE_FORMAT(l.created_at, '{$dateFormat}') AS period,
                           COUNT(*) AS created,
                           SUM(CASE WHEN l.status = 'Converted' THEN 1 ELSE 0 END) AS converted
                    FROM `lead` l
                    {$leadUser['join']}
                    WHERE l.deleted = 0 AND l.created_at BETWEEN '{$from}' AND '{$to} 23:59:59'
                    {$leadUser['filter']}
                    GROUP BY period ORDER BY period";

        $oppSql = "SELECT DATE_FORMAT(created_at, '{$dateFormat}') AS period,
                          COUNT(*) AS created,
                          SUM(CASE WHEN stage = 'Closed Won' THEN 1 ELSE 0 END) AS won
                   FROM `opportunity`
                   WHERE deleted = 0 AND created_at BETWEEN '{$from}' AND '{$to} 23:59:59'
                   {$userFilter}
                   GROUP BY period ORDER BY period";

        $leadsByPeriod = [];
        $r = $conn->query($leadSql);
        if ($r) { while ($row = $r->fetch_assoc()) { $leadsByPeriod[$row['period']] = $row; } }

        $oppsByPeriod = [];
        $r = $conn->query($oppSql);
        if ($r) { while ($row = $r->fetch_assoc()) { $oppsByPeriod[$row['period']] = $row; } }

        $allPeriods = array_unique(array_merge(array_keys($leadsByPeriod), array_keys($oppsByPeriod)));
        sort($allPeriods);

        $trend = [];
        foreach ($allPeriods as $period) {
            $leadsCreated   = (int)($leadsByPeriod[$period]['created'] ?? 0);
            $leadsConverted = (int)($leadsByPeriod[$period]['converted'] ?? 0);
            $oppsCreated    = (int)($oppsByPeriod[$period]['created'] ?? 0);
            $oppsWon        = (int)($oppsByPeriod[$period]['won'] ?? 0);

            $trend[] = [
                'period' => $period,
                'leads_created' => $leadsCreated,
                'leads_converted' => $leadsConverted,
                'lead_conversion_rate' => $leadsCreated > 0 ? round($leadsConverted / $leadsCreated * 100, 1) : 0.0,
                'opps_created' => $oppsCreated,
                'opps_won' => $oppsWon,
                'opp_conversion_rate' => $oppsCreated > 0 ? round($oppsWon / $oppsCreated * 100, 1) : 0.0,
            ];
        }
        return $trend;
    }
}

if (!function_exists('espoWonLostSplit')) {
    function espoWonLostSplit(mysqli $conn, ?string $espoUserId, string $dateFrom, string $dateTo): array {
        $userFilter = espoUserFilterClause($espoUserId, $conn);
        $from = $conn->real_escape_string($dateFrom);
        $to   = $conn->real_escape_string($dateTo);
        $sql = "SELECT
                    SUM(CASE WHEN stage = 'Closed Won' THEN 1 ELSE 0 END) AS won,
                    SUM(CASE WHEN stage = 'Closed Lost' THEN 1 ELSE 0 END) AS lost,
                    SUM(CASE WHEN stage = 'Closed Won' THEN amount ELSE 0 END) AS won_amount,
                    SUM(CASE WHEN stage = 'Closed Lost' THEN amount ELSE 0 END) AS lost_amount
                FROM `opportunity`
                WHERE deleted = 0 AND close_date BETWEEN '{$from}' AND '{$to}'
                {$userFilter}";
        $result = $conn->query($sql);
        $row = $result ? $result->fetch_assoc() : null;
        return [
            'won' => (int)($row['won'] ?? 0),
            'lost' => (int)($row['lost'] ?? 0),
            'won_amount' => (float)($row['won_amount'] ?? 0),
            'lost_amount' => (float)($row['lost_amount'] ?? 0),
        ];
    }
}

if (!function_exists('espoAvgSalesCycleDays')) {
    function espoAvgSalesCycleDays(mysqli $conn, ?string $espoUserId, string $dateFrom, string $dateTo): float {
        $userFilter = espoUserFilterClause($espoUserId, $conn);
        $from = $conn->real_escape_string($dateFrom);
        $to   = $conn->real_escape_string($dateTo);
        // GREATEST(0, ...) clamps each opportunity's own cycle length at 0
        // before averaging — a real (if rare) data-quality issue in EspoCRM
        // lets close_date be backdated earlier than created_at (e.g. a deal
        // entered into the CRM a few days after it actually closed),
        // producing a nonsensical negative day count for that one row. This
        // keeps such rows from dragging the whole average negative, while
        // still counting them as same-day closes rather than excluding them.
        $sql = "SELECT AVG(GREATEST(0, DATEDIFF(close_date, created_at))) AS avg_days
                FROM `opportunity`
                WHERE deleted = 0 AND stage = 'Closed Won'
                AND close_date BETWEEN '{$from}' AND '{$to}'
                {$userFilter}";
        $result = $conn->query($sql);
        $row = $result ? $result->fetch_assoc() : null;
        return $row && $row['avg_days'] !== null ? round((float)$row['avg_days'], 1) : 0.0;
    }
}

if (!function_exists('espoCallActivity')) {
    function espoCallActivity(mysqli $conn, ?string $espoUserId, string $dateFrom, string $dateTo): array {
        $userFilter = espoUserFilterClause($espoUserId, $conn);
        $from = $conn->real_escape_string($dateFrom);
        $to   = $conn->real_escape_string($dateTo);
        $now  = date('Y-m-d H:i:s');

        $sql = "SELECT status, COUNT(*) AS c FROM `call`
                WHERE deleted = 0 AND date_start BETWEEN '{$from}' AND '{$to} 23:59:59'
                {$userFilter}
                GROUP BY status";
        $counts = ['planned' => 0, 'held' => 0, 'not_held' => 0];
        $statusMap = ['Planned' => 'planned', 'Held' => 'held', 'Not Held' => 'not_held'];
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $key = $statusMap[$row['status']] ?? null;
                if ($key !== null) $counts[$key] = (int)$row['c'];
            }
        }

        $overdueSql = "SELECT COUNT(*) AS c FROM `call`
                        WHERE deleted = 0 AND status = 'Planned' AND date_start < '{$now}'
                        {$userFilter}";
        $r = $conn->query($overdueSql);
        $counts['overdue'] = $r ? (int)$r->fetch_assoc()['c'] : 0;

        $upcomingSql = "SELECT COUNT(*) AS c FROM `call`
                         WHERE deleted = 0 AND status = 'Planned' AND date_start >= '{$now}'
                         {$userFilter}";
        $r = $conn->query($upcomingSql);
        $counts['upcoming'] = $r ? (int)$r->fetch_assoc()['c'] : 0;

        return $counts;
    }
}

if (!function_exists('espoCallsPerConversion')) {
    function espoCallsPerConversion(mysqli $conn, ?string $espoUserId, string $dateFrom, string $dateTo): float {
        $userFilter = espoUserFilterClause($espoUserId, $conn);
        $from = $conn->real_escape_string($dateFrom);
        $to   = $conn->real_escape_string($dateTo);

        $callSql = "SELECT COUNT(*) AS c FROM `call`
                     WHERE deleted = 0 AND date_start BETWEEN '{$from}' AND '{$to} 23:59:59'
                     {$userFilter}";
        $r = $conn->query($callSql);
        $calls = $r ? (int)$r->fetch_assoc()['c'] : 0;

        $wonLostSql = "SELECT
                    SUM(CASE WHEN stage = 'Closed Won' THEN 1 ELSE 0 END) AS won
                FROM `opportunity`
                WHERE deleted = 0 AND close_date BETWEEN '{$from}' AND '{$to}'
                {$userFilter}";
        $r = $conn->query($wonLostSql);
        $wonLostRow = $r ? $r->fetch_assoc() : null;
        $conversions = $wonLostRow ? (int)($wonLostRow['won'] ?? 0) : 0;

        return espoCallsPerConversionRatio($calls, $conversions);
    }
}
