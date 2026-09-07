# EspoCRM Sales Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a real-time sales dashboard that reads directly from an externally-hosted EspoCRM MySQL database, showing funnel/conversion and call-activity metrics — scoped to "just me" for Sales BDM logins and "whole team + person-wise" for Company logins.

**Architecture:** A second read-only `mysqli` connection (`EspoDb.php`) to EspoCRM's remote database, config-driven via `.env`. A shared metrics class (`EspoMetrics.php`) with one method per metric, each accepting `$espoUserId = null|string` to switch between whole-team aggregate and per-rep filtering — this single parameter drives both dashboard pages, so there is one query path, not two. A new `espo_user_id` column on `sales_bdm_staff` maps this app's reps to EspoCRM users, set via a small admin UI. Two thin page files consume the shared metrics class under each login's existing session/permission checks.

**Tech Stack:** PHP 7/8 + mysqli (no framework, no ORM — matches existing codebase), plain HTML/CSS/Bootstrap-style markup matching existing report pages (e.g. `cp-district-sales-report.php`), vanilla JS for date-range filter form submission (GET params, page reload — no AJAX/SPA framework in use here).

**Spec:** `docs/superpowers/specs/2026-09-05-espocrm-sales-dashboard-design.md`

## Global Constraints

- Real-time only — no local cache/sync tables; every page load queries EspoCRM's DB directly (spec §1).
- Read-only against EspoCRM's DB — no write queries ever issued through `EspoDb.php` (spec §3.1).
- Every EspoCRM table query must include `AND deleted = 0` (EspoCRM soft-deletes) (spec §3.2).
- EspoCRM connection failure must render a "CRM data unavailable" banner, never a fatal PHP error, and must not affect any other part of the app (spec §3.3, §7).
- `$espoUserId = null` → whole-team aggregate; `$espoUserId = '<id>'` → filtered to one rep. Same signature across every `EspoMetrics` method (spec §5).
- No auto-mapping of BDM→Espo user without explicit admin confirmation (spec §4).
- No custom EspoCRM field reconciliation in this pass — standard schema only (spec §8, explicit non-goal).

---

## File Structure

| File | Responsibility |
|---|---|
| `femi9/billing/shared/.env` | Add `ESPO_DB_HOST`, `ESPO_DB_PORT`, `ESPO_DB_USERNAME`, `ESPO_DB_PASSWORD`, `ESPO_DB_NAME` keys (modify) |
| `femi9/billing/includes/EspoDb.php` | New. Opens the second read-only mysqli connection to EspoCRM; one function `getEspoDbConnection()` returning a connected `mysqli` or `null` on failure. |
| `femi9/billing/includes/EspoMetrics.php` | New. All metric-computing methods, each taking `$espoConn, $espoUserId, $dateFrom, $dateTo`. |
| `femi9/billing/company/salesbdm_action.php` | Modify: add `espo_user_id` handling to the existing add/edit POST action. |
| `femi9/billing/company/salesbdm_edit.php` | Modify: add EspoCRM-user dropdown to the edit form. |
| `femi9/billing/company/get-espo-users.php` | New. AJAX endpoint returning EspoCRM users as JSON (id, name, email) for the mapping dropdown. |
| `femi9/billing/company/dashboard-crm.php` | New. Company-login CRM dashboard (whole-team + per-rep table). |
| `femi9/billing/salesbdm/dashboard-crm.php` | New. Sales-BDM-login CRM dashboard (own metrics only). |

Files that change together (the `sales_bdm_staff` schema change, its edit form, and its POST handler) are grouped into one task rather than split by technical layer.

---

## Task 1: EspoCRM DB connection layer

**Files:**
- Modify: `femi9/billing/shared/.env` (add 5 keys; real credentials supplied by user later — placeholders committed are safe non-secret defaults, not real values)
- Create: `femi9/billing/includes/EspoDb.php`
- Test: `femi9/billing/includes/tests/EspoDbTest.php` (manual-run PHP script, no test framework in this codebase — see Step 2)

**Interfaces:**
- Produces: `getEspoDbConnection(): ?mysqli` — returns a connected, read-only-intended mysqli handle on success, `null` on any connection failure (never throws, never dies).

- [ ] **Step 1: Add EspoCRM connection keys to `.env`**

Open `femi9/billing/shared/.env` and add, right after the existing `DB_*` block:

```
ESPO_DB_HOST=
ESPO_DB_PORT=3306
ESPO_DB_USERNAME=
ESPO_DB_PASSWORD=
ESPO_DB_NAME=
```

Leave the value side blank except `ESPO_DB_PORT=3306` — real values are supplied by the user once the remote MySQL allow-list is set up (spec §3.1). This file is already gitignored (it holds `DB_PASSWORD` etc. today), so blank placeholders here are fine to commit as part of this task's surrounding code, but the `.env` file itself is not committed.

- [ ] **Step 2: Write `EspoDb.php`**

```php
<?php
/**
 * Read-only connection to the externally-hosted EspoCRM MySQL database.
 * Never issue write queries through the connection this returns.
 */

require_once __DIR__ . '/../shared/env-loader.php';

if (!function_exists('getEspoDbConnection')) {
    function getEspoDbConnection(): ?mysqli {
        $host     = $_ENV['ESPO_DB_HOST'] ?? '';
        $port     = (int)($_ENV['ESPO_DB_PORT'] ?? 3306);
        $username = $_ENV['ESPO_DB_USERNAME'] ?? '';
        $password = $_ENV['ESPO_DB_PASSWORD'] ?? '';
        $dbname   = $_ENV['ESPO_DB_NAME'] ?? '';

        if ($host === '' || $username === '' || $dbname === '') {
            return null;
        }

        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @mysqli_connect($host, $username, $password, $dbname, $port);

        if (!$conn) {
            return null;
        }

        return $conn;
    }
}
```

- [ ] **Step 3: Write a manual verification script**

```php
<?php
// femi9/billing/includes/tests/EspoDbTest.php
// Manual run: php EspoDbTest.php
require_once __DIR__ . '/../EspoDb.php';

$conn = getEspoDbConnection();
if ($conn === null) {
    echo "PASS: getEspoDbConnection() returns null when ESPO_DB_* env vars are unset/unreachable\n";
} else {
    $result = $conn->query("SELECT COUNT(*) AS c FROM user WHERE deleted = 0");
    $row = $result->fetch_assoc();
    echo "PASS: connected, {$row['c']} active EspoCRM users found\n";
    $conn->close();
}
```

- [ ] **Step 4: Run it to verify the null-connection path**

Run: `php "femi9/billing/includes/tests/EspoDbTest.php"`
Expected (before real credentials are supplied): `PASS: getEspoDbConnection() returns null when ESPO_DB_* env vars are unset/unreachable`

- [ ] **Step 5: Commit**

```bash
git add "femi9/billing/includes/EspoDb.php" "femi9/billing/includes/tests/EspoDbTest.php"
git commit -m "Add read-only EspoCRM DB connection layer

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

(Note: `.env` itself is gitignored and not committed — only the code files are staged here. Tell the user to add the 5 `ESPO_DB_*` keys to their live `.env` manually, mirroring Step 1.)

---

## Task 2: Metrics engine (`EspoMetrics.php`)

**Files:**
- Create: `femi9/billing/includes/EspoMetrics.php`
- Test: `femi9/billing/includes/tests/EspoMetricsTest.php`

**Interfaces:**
- Consumes: a connected `mysqli` from `getEspoDbConnection()` (Task 1), passed in by the caller — this file does not open its own connection, keeping it testable against any mysqli handle.
- Produces:
  - `espoFunnelSnapshot(mysqli $conn, ?string $espoUserId, string $dateFrom, string $dateTo): array` → `['new' => int, 'assigned' => int, 'in_process' => int, 'converted' => int, 'recycled' => int, 'dead' => int, 'opp_stages' => [stageName => count, ...]]`
  - `espoConversionTrend(mysqli $conn, ?string $espoUserId, string $dateFrom, string $dateTo, string $granularity): array` → list of `['period' => 'YYYY-MM' or 'YYYY-MM-DD', 'leads_created' => int, 'leads_converted' => int, 'lead_conversion_rate' => float, 'opps_created' => int, 'opps_won' => int, 'opp_conversion_rate' => float]`, one row per period bucket
  - `espoWonLostSplit(mysqli $conn, ?string $espoUserId, string $dateFrom, string $dateTo): array` → `['won' => int, 'lost' => int, 'won_amount' => float, 'lost_amount' => float]`
  - `espoAvgSalesCycleDays(mysqli $conn, ?string $espoUserId, string $dateFrom, string $dateTo): float`
  - `espoCallActivity(mysqli $conn, ?string $espoUserId, string $dateFrom, string $dateTo): array` → `['planned' => int, 'held' => int, 'not_held' => int, 'overdue' => int, 'upcoming' => int]`
  - `espoCallsPerConversion(mysqli $conn, ?string $espoUserId, string $dateFrom, string $dateTo): float` (0.0 if zero conversions, to avoid division-by-zero)

- [ ] **Step 1: Write the failing tests**

```php
<?php
// femi9/billing/includes/tests/EspoMetricsTest.php
// Manual run: php EspoMetricsTest.php
// Uses an in-memory-style fixture: connects to any reachable MySQL server
// (the app's own local DB is fine) and creates throwaway tables shaped like
// EspoCRM's schema, to test aggregation math without needing real EspoCRM
// credentials.

require_once __DIR__ . '/../EspoMetrics.php';
require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn as the test fixture DB

$conn = $db_conn;
$conn->query("DROP TABLE IF EXISTS test_lead");
$conn->query("CREATE TABLE test_lead (id VARCHAR(24), status VARCHAR(50), created_at DATETIME, assigned_user_id VARCHAR(24), deleted TINYINT DEFAULT 0)");
$conn->query("INSERT INTO test_lead (id, status, created_at, assigned_user_id, deleted) VALUES
    ('l1','Converted','2026-09-01 10:00:00','u1',0),
    ('l2','New','2026-09-02 10:00:00','u1',0),
    ('l3','Converted','2026-09-03 10:00:00','u2',0),
    ('l4','Dead','2026-09-04 10:00:00','u1',1)"); -- deleted, must be excluded

function assertEqual($actual, $expected, $label) {
    if ($actual == $expected) {
        echo "PASS: $label\n";
    } else {
        echo "FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
    }
}

// Test: whole-team lead counts exclude deleted rows
$snapshot = espoFunnelSnapshotFromLeadTable($conn, 'test_lead', null, '2026-09-01', '2026-09-30');
assertEqual($snapshot['converted'], 2, 'whole-team converted lead count excludes deleted');
assertEqual($snapshot['new'], 1, 'whole-team new lead count');

// Test: per-rep filter narrows correctly
$snapshotU1 = espoFunnelSnapshotFromLeadTable($conn, 'test_lead', 'u1', '2026-09-01', '2026-09-30');
assertEqual($snapshotU1['converted'], 1, 'per-rep (u1) converted lead count');
assertEqual($snapshotU1['new'], 1, 'per-rep (u1) new lead count');

$snapshotU2 = espoFunnelSnapshotFromLeadTable($conn, 'test_lead', 'u2', '2026-09-01', '2026-09-30');
assertEqual($snapshotU2['converted'], 1, 'per-rep (u2) converted lead count');

// Test: calls-per-conversion avoids division by zero
assertEqual(espoCallsPerConversionRatio(5, 0), 0.0, 'calls-per-conversion returns 0.0 when zero conversions');
assertEqual(espoCallsPerConversionRatio(10, 5), 2.0, 'calls-per-conversion computes ratio correctly');

$conn->query("DROP TABLE IF EXISTS test_lead");
```

Note: `espoFunnelSnapshotFromLeadTable` and `espoCallsPerConversionRatio` are internal helper names used only inside this test to exercise the aggregation logic against a table name parameter — the real `EspoMetrics.php` functions hardcode the `lead`/`opportunity`/`call` table names per spec §3.2, but factor the actual SQL-building/aggregation into small internal helpers that accept a table name, so this test can point them at `test_lead` instead of the real `lead` table. This keeps the test free of any EspoCRM DB dependency.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php "femi9/billing/includes/tests/EspoMetricsTest.php"`
Expected: PHP fatal error — `espoFunnelSnapshotFromLeadTable() not defined` (file doesn't exist yet)

- [ ] **Step 3: Write `EspoMetrics.php`**

```php
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
        $sql = "SELECT stage, COUNT(*) AS c FROM opportunity
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
                    FROM lead
                    WHERE deleted = 0 AND created_at BETWEEN '{$from}' AND '{$to} 23:59:59'
                    {$userFilter}
                    GROUP BY period ORDER BY period";

        $oppSql = "SELECT DATE_FORMAT(created_at, '{$dateFormat}') AS period,
                          COUNT(*) AS created,
                          SUM(CASE WHEN stage = 'Closed Won' THEN 1 ELSE 0 END) AS won
                   FROM opportunity
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
                FROM opportunity
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
                FROM opportunity
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

        $wonLost = espoWonLostSplit($conn, $espoUserId, $dateFrom, $dateTo);

        return espoCallsPerConversionRatio($calls, $wonLost['won']);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php "femi9/billing/includes/tests/EspoMetricsTest.php"`
Expected: all lines `PASS: ...`, no `FAIL:` lines

- [ ] **Step 5: Commit**

```bash
git add "femi9/billing/includes/EspoMetrics.php" "femi9/billing/includes/tests/EspoMetricsTest.php"
git commit -m "Add EspoCRM metrics engine (funnel, conversion trend, call activity)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Rep mapping (`espo_user_id` column + admin UI)

**Files:**
- Modify: `femi9/billing/company/salesbdm_manage.php:144` (add column-check/ALTER right after the existing `sales_bdm_staff` CREATE TABLE, same pattern as the `monthly_target_amount` backfill at lines 145-148)
- Create: `femi9/billing/company/get-espo-users.php`
- Modify: `femi9/billing/company/salesbdm_edit.php` (add EspoCRM-user dropdown to the edit form)
- Modify: `femi9/billing/company/salesbdm_action.php` (persist `espo_user_id` on save)

**Interfaces:**
- Consumes: `getEspoDbConnection()` (Task 1)
- Produces: `sales_bdm_staff.espo_user_id` column, readable by Task 4/5 via `$result_LoGuserDtails['espo_user_id']` (company checksession pattern already loads `SELECT *`).

- [ ] **Step 1: Add the column backfill**

In `femi9/billing/company/salesbdm_manage.php`, right after line 148 (`ADD COLUMN monthly_target_amount ...`), add:

```php
$_chkEspo = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'espo_user_id'");
if ($_chkEspo && $_chkEspo->num_rows === 0) {
    $db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN espo_user_id VARCHAR(24) NULL DEFAULT NULL AFTER monthly_target_amount");
}
```

- [ ] **Step 2: Verify the column gets created**

Run: visit `salesbdm_manage.php` once in a browser (or `php -r` a request isn't practical here — instead run the SQL check directly):
```bash
php -r '
require "femi9/billing/company/include/db-connect.php";
$r = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE \"espo_user_id\"");
echo $r && $r->num_rows === 1 ? "PASS: espo_user_id column exists\n" : "FAIL: column missing\n";
'
```
(Run this only after `salesbdm_manage.php` has been hit once, since the ALTER is lazy/on-page-load per existing codebase convention — same as `monthly_target_amount`.)
Expected: `PASS: espo_user_id column exists`

- [ ] **Step 3: Write the EspoCRM-users JSON endpoint**

```php
<?php
// femi9/billing/company/get-espo-users.php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
require_once __DIR__ . '/../includes/EspoDb.php';

header('Content-Type: application/json');

$conn = getEspoDbConnection();
if ($conn === null) {
    echo json_encode(['error' => 'CRM data unavailable', 'users' => []]);
    exit;
}

$result = $conn->query("SELECT id, first_name, last_name, email FROM user WHERE deleted = 0 ORDER BY first_name, last_name");
$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'email' => $row['email'],
        ];
    }
}
$conn->close();

echo json_encode(['error' => null, 'users' => $users]);
```

- [ ] **Step 4: Manually verify the endpoint's failure path**

Run: `php -r '
$_SERVER["REQUEST_METHOD"]="GET";
chdir("femi9/billing/company");
ob_start();
include "get-espo-users.php";
$out = ob_get_clean();
$data = json_decode($out, true);
echo (isset($data["users"]) && is_array($data["users"])) ? "PASS: get-espo-users.php returns valid JSON shape\n" : "FAIL: bad response: $out\n";
'`
Expected (before real ESPO_DB_* creds exist): `PASS: get-espo-users.php returns valid JSON shape` with `error: "CRM data unavailable"` and an empty `users` array — this confirms the null-connection path degrades gracefully rather than fataling.

Note: this manual check bypasses `checksession.php`'s login redirect for a quick CLI probe; running it inside a real logged-in browser session against `salesbdm_manage.php`'s existing auth is the fuller check once the page is wired up in Step 5.

- [ ] **Step 5: Add the dropdown to `salesbdm_edit.php`**

Find the existing form fields in `femi9/billing/company/salesbdm_edit.php` (mirroring how `bdm_email`/`bdm_mobile` fields are rendered) and add, near those fields:

```php
<div class="form-group">
    <label>Linked EspoCRM User</label>
    <select name="espo_user_id" id="espo_user_id" class="form-control">
        <option value="">-- Not linked --</option>
    </select>
    <small class="form-text text-muted">Select the EspoCRM user this Sales BDM corresponds to, for CRM dashboard metrics.</small>
</div>
<script>
$(function() {
    $.getJSON('get-espo-users.php', function(resp) {
        if (resp.error) {
            $('#espo_user_id').after('<small class="text-danger d-block">CRM data unavailable — cannot load user list.</small>');
            return;
        }
        var current = <?php echo json_encode($result_product_list['espo_user_id'] ?? ''); ?>;
        resp.users.forEach(function(u) {
            var opt = $('<option>').val(u.id).text(u.name + ' (' + u.email + ')');
            if (u.id === current) opt.prop('selected', true);
            $('#espo_user_id').append(opt);
        });
    });
});
</script>
```

(Adjust the PHP variable name holding the current record — `$result_product_list` is the pattern used in `salesbdm_manage.php`'s listing loop at line 166; confirm the exact variable name used in `salesbdm_edit.php`'s own record-fetch query when implementing, since the edit page fetches a single record rather than a list.)

- [ ] **Step 6: Persist `espo_user_id` on save**

In `femi9/billing/company/salesbdm_action.php`, find the existing UPDATE/INSERT statement for `sales_bdm_staff` and add `espo_user_id` alongside the other fields already being written (e.g. `bdm_email`, `bdm_address`):

```php
$espo_user_id = $_POST['espo_user_id'] ?? null;
$espo_user_id = ($espo_user_id === '') ? null : $espo_user_id;
```

Bind this into the existing prepared statement (or string-built query, matching whatever style `salesbdm_action.php` already uses) as an additional column/value pair.

- [ ] **Step 7: Commit**

```bash
git add femi9/billing/company/salesbdm_manage.php femi9/billing/company/salesbdm_edit.php femi9/billing/company/salesbdm_action.php femi9/billing/company/get-espo-users.php
git commit -m "Add EspoCRM user mapping to Sales BDM records

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: Company-login CRM dashboard

**Files:**
- Create: `femi9/billing/company/dashboard-crm.php`

**Interfaces:**
- Consumes: `getEspoDbConnection()` (Task 1), all `Espo*` metric functions (Task 2), `sales_bdm_staff.espo_user_id` (Task 3)

- [ ] **Step 1: Write the page**

```php
<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
require_once __DIR__ . '/../includes/EspoDb.php';
require_once __DIR__ . '/../includes/EspoMetrics.php';
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$from = isset($_GET['from']) && $_GET['from'] ? date('Y-m-d', strtotime($_GET['from'])) : date('Y-m-01');
$to   = isset($_GET['to'])   && $_GET['to']   ? date('Y-m-d', strtotime($_GET['to']))   : date('Y-m-t');

$espoConn = getEspoDbConnection();
$crmUnavailable = ($espoConn === null);

$teamFunnel = $teamTrend = $teamWonLost = [];
$teamAvgCycle = $teamCalls = $teamCallsPerConv = 0;
$repRows = [];

if (!$crmUnavailable) {
    $teamFunnel = espoFunnelSnapshot($espoConn, null, $from, $to);
    $teamTrend = espoConversionTrend($espoConn, null, $from, $to, 'monthly');
    $teamWonLost = espoWonLostSplit($espoConn, null, $from, $to);
    $teamAvgCycle = espoAvgSalesCycleDays($espoConn, null, $from, $to);
    $teamCalls = espoCallActivity($espoConn, null, $from, $to);
    $teamCallsPerConv = espoCallsPerConversion($espoConn, null, $from, $to);

    $bdms = $db_conn->query("SELECT id, bdm_name, espo_user_id FROM sales_bdm_staff ORDER BY bdm_name");
    while ($bdm = $bdms->fetch_assoc()) {
        if (empty($bdm['espo_user_id'])) {
            $repRows[] = ['bdm_name' => $bdm['bdm_name'], 'linked' => false];
            continue;
        }
        $eid = $bdm['espo_user_id'];
        $repRows[] = [
            'bdm_name' => $bdm['bdm_name'],
            'linked' => true,
            'funnel' => espoFunnelSnapshot($espoConn, $eid, $from, $to),
            'won_lost' => espoWonLostSplit($espoConn, $eid, $from, $to),
            'calls' => espoCallActivity($espoConn, $eid, $from, $to),
            'calls_per_conv' => espoCallsPerConversion($espoConn, $eid, $from, $to),
        ];
    }
    $espoConn->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>CRM Sales Dashboard</title>
    <?php include("logo.php"); ?>
</head>
<body>
<?php include("femi_menu.php"); ?>
<?php include("app-header.php"); ?>

<div class="container-fluid">
    <h3>CRM Sales Dashboard — Whole Team</h3>

    <form method="get" class="form-inline mb-3">
        <label class="mr-2">From <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control mx-2"></label>
        <label class="mr-2">To <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control mx-2"></label>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>

    <?php if ($crmUnavailable): ?>
        <div class="alert alert-warning">CRM data unavailable — could not connect to EspoCRM. Please try again shortly.</div>
    <?php else: ?>
        <div class="row">
            <div class="col-md-3"><div class="card p-3"><h6>Leads Converted</h6><h3><?php echo $teamFunnel['converted']; ?></h3></div></div>
            <div class="col-md-3"><div class="card p-3"><h6>Opportunities Won</h6><h3><?php echo $teamWonLost['won']; ?></h3></div></div>
            <div class="col-md-3"><div class="card p-3"><h6>Avg. Sales Cycle (days)</h6><h3><?php echo $teamAvgCycle; ?></h3></div></div>
            <div class="col-md-3"><div class="card p-3"><h6>Calls Held</h6><h3><?php echo $teamCalls['held']; ?></h3></div></div>
        </div>

        <h5 class="mt-4">Person-wise Breakdown</h5>
        <table class="table table-bordered">
            <thead>
                <tr><th>BDM</th><th>Leads Converted</th><th>Opps Won</th><th>Opps Lost</th><th>Calls Held</th><th>Calls/Conversion</th></tr>
            </thead>
            <tbody>
                <?php foreach ($repRows as $row): ?>
                    <?php if (!$row['linked']): ?>
                        <tr><td><?php echo htmlspecialchars($row['bdm_name']); ?></td><td colspan="5" class="text-muted">Not linked to a CRM user</td></tr>
                    <?php else: ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['bdm_name']); ?></td>
                            <td><?php echo $row['funnel']['converted']; ?></td>
                            <td><?php echo $row['won_lost']['won']; ?></td>
                            <td><?php echo $row['won_lost']['lost']; ?></td>
                            <td><?php echo $row['calls']['held']; ?></td>
                            <td><?php echo $row['calls_per_conv']; ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
```

- [ ] **Step 2: Manually verify the unavailable-CRM path**

Run: with `.env`'s `ESPO_DB_*` keys still blank (from Task 1), load `company/dashboard-crm.php` in a browser while logged in as company.
Expected: page renders (no fatal error), shows the "CRM data unavailable" alert, and the rest of the app's nav/header still renders normally.

- [ ] **Step 3: Commit**

```bash
git add femi9/billing/company/dashboard-crm.php
git commit -m "Add company-login CRM dashboard (whole-team + person-wise)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 5: Sales-BDM-login CRM dashboard

**Files:**
- Create: `femi9/billing/salesbdm/dashboard-crm.php`

**Interfaces:**
- Consumes: `getEspoDbConnection()` (Task 1), all `Espo*` metric functions (Task 2), `$_SESSION['LOGIN_USER_ID']` / `$result_LoGuserDtails['espo_user_id']` (Task 3 + existing `salesbdm/checksession.php` pattern)

- [ ] **Step 1: Write the page**

```php
<?php
include("checksession.php"); // sets $result_LoGuserDtails from sales_bdm_staff (existing pattern)
require_once __DIR__ . '/../includes/EspoDb.php';
require_once __DIR__ . '/../includes/EspoMetrics.php';
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$from = isset($_GET['from']) && $_GET['from'] ? date('Y-m-d', strtotime($_GET['from'])) : date('Y-m-01');
$to   = isset($_GET['to'])   && $_GET['to']   ? date('Y-m-d', strtotime($_GET['to']))   : date('Y-m-t');

$myEspoId = $result_LoGuserDtails['espo_user_id'] ?? null;
$notLinked = empty($myEspoId);

$funnel = $trend = $wonLost = [];
$avgCycle = 0;
$calls = ['planned'=>0,'held'=>0,'not_held'=>0,'overdue'=>0,'upcoming'=>0];
$callsPerConv = 0.0;
$crmUnavailable = false;

if (!$notLinked) {
    $espoConn = getEspoDbConnection();
    $crmUnavailable = ($espoConn === null);
    if (!$crmUnavailable) {
        $funnel = espoFunnelSnapshot($espoConn, $myEspoId, $from, $to);
        $trend = espoConversionTrend($espoConn, $myEspoId, $from, $to, 'monthly');
        $wonLost = espoWonLostSplit($espoConn, $myEspoId, $from, $to);
        $avgCycle = espoAvgSalesCycleDays($espoConn, $myEspoId, $from, $to);
        $calls = espoCallActivity($espoConn, $myEspoId, $from, $to);
        $callsPerConv = espoCallsPerConversion($espoConn, $myEspoId, $from, $to);
        $espoConn->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>My CRM Dashboard</title></head>
<body>
<div class="container-fluid">
    <h3>My CRM Dashboard</h3>

    <?php if ($notLinked): ?>
        <div class="alert alert-info">Ask your admin to link your CRM account to see your metrics here.</div>
    <?php elseif ($crmUnavailable): ?>
        <div class="alert alert-warning">CRM data unavailable — could not connect to EspoCRM. Please try again shortly.</div>
    <?php else: ?>
        <form method="get" class="form-inline mb-3">
            <label class="mr-2">From <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control mx-2"></label>
            <label class="mr-2">To <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control mx-2"></label>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>

        <div class="row">
            <div class="col-md-3"><div class="card p-3"><h6>Leads Converted</h6><h3><?php echo $funnel['converted']; ?></h3></div></div>
            <div class="col-md-3"><div class="card p-3"><h6>Opportunities Won</h6><h3><?php echo $wonLost['won']; ?></h3></div></div>
            <div class="col-md-3"><div class="card p-3"><h6>Avg. Sales Cycle (days)</h6><h3><?php echo $avgCycle; ?></h3></div></div>
            <div class="col-md-3"><div class="card p-3"><h6>Calls Held</h6><h3><?php echo $calls['held']; ?></h3></div></div>
        </div>
        <p class="mt-3">Calls per conversion: <strong><?php echo $callsPerConv; ?></strong></p>
    <?php endif; ?>
</div>
</body>
</html>
```

- [ ] **Step 2: Manually verify the not-linked path**

Run: log in as a Sales BDM whose `sales_bdm_staff.espo_user_id` is still NULL (true for every existing record until Task 3's mapping UI is used), load `salesbdm/dashboard-crm.php`.
Expected: page renders the "Ask your admin to link your CRM account" info alert, no fatal error.

- [ ] **Step 3: Commit**

```bash
git add femi9/billing/salesbdm/dashboard-crm.php
git commit -m "Add Sales BDM CRM dashboard (own metrics only)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 6: Nav links

**Files:**
- Modify: `femi9/billing/company/femi_menu.php` (add a "CRM Dashboard" nav entry linking to `dashboard-crm.php`, matching the existing menu-item markup pattern for other report links like `cp-district-sales-report.php`)
- Modify: the Sales BDM equivalent menu file (locate via the same search pattern used for `femi9/billing/salesbdm/checksession.php`'s including page, e.g. a `salesbdm/` menu/header include)

**Interfaces:**
- Consumes: nothing new — pure navigation wiring to Tasks 4 and 5's pages.

- [ ] **Step 1: Find the exact menu markup pattern**

Run: `grep -n "cp-district-sales-report" femi9/billing/company/femi_menu.php`
Read the surrounding `<li>`/`<a>` markup to copy its exact structure (icon class, permission-gated visibility if any).

- [ ] **Step 2: Add the Company-side nav entry**

Add a new menu item in `femi9/billing/company/femi_menu.php`, immediately alongside the existing sales-report links, pointing to `dashboard-crm.php`, using the exact markup style found in Step 1 (icon, label "CRM Dashboard").

- [ ] **Step 3: Add the Sales-BDM-side nav entry**

Run: `grep -rln "tp-advance-payment-report" femi9/billing/salesbdm/` to find the BDM-side menu/header file, then add an equivalent nav entry there pointing to `dashboard-crm.php`, matching that file's existing markup style.

- [ ] **Step 4: Manually verify both links render and route correctly**

Run: log in as company, confirm "CRM Dashboard" appears in nav and clicking it loads Task 4's page. Log in as a Sales BDM, confirm the equivalent link loads Task 5's page.
Expected: both links visible in their respective logins' nav, both route to the correct dashboard page with no 404/fatal error.

- [ ] **Step 5: Commit**

```bash
git add femi9/billing/company/femi_menu.php
git commit -m "Add CRM Dashboard nav links for company and Sales BDM logins

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

(The Sales BDM-side menu file path is determined during Step 3 above — stage and commit it alongside `femi_menu.php` in this same commit once found.)

---

## Post-implementation note for the user

Once all 6 tasks are done, the dashboard is fully wired but **inert** until real EspoCRM credentials are supplied:

1. Open cPanel's Remote MySQL allow-list (or AWS security group) on the EspoCRM AWS account for this billing server's outbound IP, per spec §3.1.
2. Create a **read-only** MySQL user scoped to the EspoCRM database.
3. Fill in `ESPO_DB_HOST`, `ESPO_DB_PORT`, `ESPO_DB_USERNAME`, `ESPO_DB_PASSWORD`, `ESPO_DB_NAME` in the live `femi9/billing/shared/.env` (not committed to git).
4. Use `salesbdm_edit.php`'s new dropdown (Task 3) to link each Sales BDM to their EspoCRM user.
5. If the real EspoCRM instance uses custom lead statuses, custom opportunity stages, or a custom call-outcome field beyond the standard schema assumed here, that reconciliation is explicitly out of scope for this plan (spec §8) and should be scoped as a follow-up once actual field names are known.
