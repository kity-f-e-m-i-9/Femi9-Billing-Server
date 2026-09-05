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

// ---- Internal helpers (table name is a parameter so tests can point them
//      at a fixture table; public functions below always pass the real
//      EspoCRM table names) ----

if (!function_exists('espoFunnelSnapshotFromLeadTable')) {
    function espoFunnelSnapshotFromLeadTable(mysqli $conn, string $table, ?string $espoUserId, string $dateFrom, string $dateTo): array {
        $userFilter = espoUserFilterClause($espoUserId, $conn);
        $from = $conn->real_escape_string($dateFrom);
        $to   = $conn->real_escape_string($dateTo);

        $sql = "SELECT status, COUNT(*) AS c FROM `{$table}`
                WHERE deleted = 0 AND created_at BETWEEN '{$from}' AND '{$to} 23:59:59'
                {$userFilter}
                GROUP BY status";

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

        $leadSql = "SELECT DATE_FORMAT(created_at, '{$dateFormat}') AS period,
                           COUNT(*) AS created,
                           SUM(CASE WHEN status = 'Converted' THEN 1 ELSE 0 END) AS converted
                    FROM `lead`
                    WHERE deleted = 0 AND created_at BETWEEN '{$from}' AND '{$to} 23:59:59'
                    {$userFilter}
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
        $sql = "SELECT AVG(DATEDIFF(close_date, created_at)) AS avg_days
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
