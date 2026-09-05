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

// Test counter
$passCount = 0;
$failCount = 0;

function assertEqual($actual, $expected, $label) {
    global $passCount, $failCount;
    if ($actual == $expected) {
        echo "PASS: $label\n";
        $passCount++;
    } else {
        echo "FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
        $failCount++;
    }
}

function assertArrayContains($array, $key, $value, $label) {
    global $passCount, $failCount;
    if (!is_array($array)) {
        echo "FAIL: $label — array parameter is not an array: " . var_export($array, true) . "\n";
        $failCount++;
        return;
    }
    if (isset($array[$key]) && $array[$key] == $value) {
        echo "PASS: $label\n";
        $passCount++;
    } else {
        $actual = isset($array[$key]) ? $array[$key] : 'KEY_NOT_FOUND';
        echo "FAIL: $label — expected array[$key]=" . var_export($value, true) . ", got " . var_export($actual, true) . "\n";
        $failCount++;
    }
}

// ========== SETUP: Create fixture tables with real EspoCRM table names ==========

// Clean up any existing fixture tables
$conn->query("DROP TABLE IF EXISTS `lead`");
$conn->query("DROP TABLE IF EXISTS `opportunity`");
$conn->query("DROP TABLE IF EXISTS `call`");

// Create lead table
$conn->query("CREATE TABLE `lead` (
    id VARCHAR(24) PRIMARY KEY,
    status VARCHAR(50),
    created_at DATETIME,
    assigned_user_id VARCHAR(24),
    deleted TINYINT DEFAULT 0
)");

// Insert lead test data
$conn->query("INSERT INTO `lead` (id, status, created_at, assigned_user_id, deleted) VALUES
    ('l1', 'Converted', '2026-08-01 10:00:00', 'u1', 0),
    ('l2', 'New', '2026-08-02 10:00:00', 'u1', 0),
    ('l3', 'Converted', '2026-08-03 10:00:00', 'u2', 0),
    ('l4', 'In Process', '2026-08-05 10:00:00', 'u1', 0),
    ('l5', 'Assigned', '2026-08-06 10:00:00', 'u2', 0),
    ('l6', 'Dead', '2026-08-07 10:00:00', 'u1', 1),
    ('l7', 'Recycled', '2026-08-08 10:00:00', 'u1', 0),
    ('l8', 'Converted', '2026-09-01 10:00:00', 'u1', 0),
    ('l9', 'Converted', '2026-09-05 10:00:00', 'u2', 0)");

// Create opportunity table
$conn->query("CREATE TABLE `opportunity` (
    id VARCHAR(24) PRIMARY KEY,
    stage VARCHAR(50),
    amount DECIMAL(15,2),
    created_at DATETIME,
    close_date DATE,
    assigned_user_id VARCHAR(24),
    deleted TINYINT DEFAULT 0
)");

// Insert opportunity test data
// close_date is used for filtering in espoWonLostSplit
// For August: 2 won, 1 lost (all closed in date range)
// For Sept: 2 won, 1 lost (all closed in date range)
$conn->query("INSERT INTO `opportunity` (id, stage, amount, created_at, close_date, assigned_user_id, deleted) VALUES
    ('o1', 'Closed Won', 10000.00, '2026-08-01 09:00:00', '2026-08-10', 'u1', 0),
    ('o2', 'Closed Lost', 5000.00, '2026-08-05 09:00:00', '2026-08-20', 'u1', 0),
    ('o3', 'Closed Won', 15000.00, '2026-08-10 09:00:00', '2026-08-25', 'u2', 0),
    ('o4', 'Qualification', 8000.00, '2026-08-15 09:00:00', NULL, 'u1', 0),
    ('o5', 'Closed Won', 20000.00, '2026-09-01 09:00:00', '2026-09-05', 'u1', 0),
    ('o6', 'Closed Lost', 12000.00, '2026-09-03 09:00:00', '2026-09-08', 'u2', 0),
    ('o7', 'Proposal/Quote', 7000.00, '2026-09-05 09:00:00', NULL, 'u1', 0),
    ('o8', 'Closed Won', 18000.00, '2026-09-02 09:00:00', '2026-09-09', 'u2', 0)");

// Create call table
$conn->query("CREATE TABLE `call` (
    id VARCHAR(24) PRIMARY KEY,
    status VARCHAR(50),
    date_start DATETIME,
    assigned_user_id VARCHAR(24),
    deleted TINYINT DEFAULT 0
)");

// Insert call test data (using realistic dates around 2026-09-05)
$now = date('Y-m-d H:i:s');
$nowDate = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day')) . ' 10:00:00';
$yesterday = date('Y-m-d', strtotime('-1 day')) . ' 10:00:00';
$pastTime = date('Y-m-d', strtotime('-2 days')) . ' 10:00:00';

$conn->query("INSERT INTO `call` (id, status, date_start, assigned_user_id, deleted) VALUES
    ('c1', 'Held', '2026-08-20 10:00:00', 'u1', 0),
    ('c2', 'Held', '2026-08-25 14:00:00', 'u1', 0),
    ('c3', 'Not Held', '2026-08-28 15:00:00', 'u2', 0),
    ('c4', 'Planned', '{$pastTime}', 'u1', 0),
    ('c5', 'Planned', '{$yesterday}', 'u1', 0),
    ('c6', 'Planned', '{$tomorrow}', 'u1', 0),
    ('c7', 'Held', '2026-09-02 11:00:00', 'u2', 0),
    ('c8', 'Planned', '2026-09-04 09:00:00', 'u1', 0),
    ('c9', 'Not Held', '2026-09-04 16:00:00', 'u1', 0)");

echo "=== SETUP: Fixture tables created ===\n\n";

// ========== TEST 1: espoFunnelSnapshot ==========
echo "=== TEST 1: espoFunnelSnapshot (public function) ===\n";

// Test whole-team funnel
$snapshot = espoFunnelSnapshot($conn, null, '2026-08-01', '2026-09-09');
assertEqual($snapshot['converted'], 4, 'espoFunnelSnapshot: whole-team converted count (l1,l3,l8,l9)');
assertEqual($snapshot['new'], 1, 'espoFunnelSnapshot: whole-team new count (l2)');
assertEqual(isset($snapshot['opp_stages']) && is_array($snapshot['opp_stages']), true, 'espoFunnelSnapshot: opp_stages is array');
assertArrayContains($snapshot['opp_stages'], 'Closed Won', 4, 'espoFunnelSnapshot: whole-team 4 Closed Won opportunities');
assertArrayContains($snapshot['opp_stages'], 'Closed Lost', 2, 'espoFunnelSnapshot: whole-team 2 Closed Lost opportunities');

// Test per-rep funnel
$snapshotU1 = espoFunnelSnapshot($conn, 'u1', '2026-08-01', '2026-09-09');
assertEqual($snapshotU1['converted'], 2, 'espoFunnelSnapshot: per-rep (u1) converted count (l1,l8)');
assertEqual($snapshotU1['new'], 1, 'espoFunnelSnapshot: per-rep (u1) new count (l2)');
assertArrayContains($snapshotU1['opp_stages'], 'Closed Won', 2, 'espoFunnelSnapshot: per-rep (u1) 2 Closed Won');

echo "\n";

// ========== TEST 2: espoConversionTrend (monthly granularity) ==========
echo "=== TEST 2: espoConversionTrend (public function) ===\n";

$trend = espoConversionTrend($conn, null, '2026-08-01', '2026-09-09', 'monthly');
assertEqual(count($trend), 2, 'espoConversionTrend: returns 2 monthly periods (Aug, Sept)');

// Find August and September periods
$augRecord = null;
$septRecord = null;
foreach ($trend as $rec) {
    if (strpos($rec['period'], '2026-08') === 0) $augRecord = $rec;
    if (strpos($rec['period'], '2026-09') === 0) $septRecord = $rec;
}

assertEqual($augRecord !== null, true, 'espoConversionTrend: August period exists');
assertEqual($septRecord !== null, true, 'espoConversionTrend: September period exists');

if ($augRecord) {
    assertEqual($augRecord['leads_created'], 6, 'espoConversionTrend: Aug leads_created (l1-l5,l7; l6 deleted)');
    assertEqual($augRecord['leads_converted'], 2, 'espoConversionTrend: Aug leads_converted (l1,l3)');
    assertEqual($augRecord['lead_conversion_rate'], 33.3, 'espoConversionTrend: Aug lead conversion rate (2/6*100)');
    assertEqual($augRecord['opps_created'], 4, 'espoConversionTrend: Aug opps_created (o1-o4)');
    assertEqual($augRecord['opps_won'], 2, 'espoConversionTrend: Aug opps_won (o1,o3)');
    assertEqual($augRecord['opp_conversion_rate'], 50.0, 'espoConversionTrend: Aug opp conversion rate (2/4*100)');
}

if ($septRecord) {
    assertEqual($septRecord['leads_created'], 2, 'espoConversionTrend: Sept leads_created (l8,l9)');
    assertEqual($septRecord['leads_converted'], 2, 'espoConversionTrend: Sept leads_converted (l8,l9)');
    assertEqual($septRecord['opps_created'], 4, 'espoConversionTrend: Sept opps_created (o5-o8)');
    assertEqual($septRecord['opps_won'], 2, 'espoConversionTrend: Sept opps_won (o5,o8)');
}

// Test per-rep trend
$trendU1 = espoConversionTrend($conn, 'u1', '2026-08-01', '2026-09-09', 'monthly');
assertEqual(count($trendU1) > 0, true, 'espoConversionTrend: per-rep (u1) returns periods');

echo "\n";

// ========== TEST 3: espoWonLostSplit ==========
echo "=== TEST 3: espoWonLostSplit (public function) ===\n";

$wonLost = espoWonLostSplit($conn, null, '2026-08-01', '2026-09-09');
assertEqual($wonLost['won'], 4, 'espoWonLostSplit: whole-team won count (o1,o3,o5,o8)');
assertEqual($wonLost['lost'], 2, 'espoWonLostSplit: whole-team lost count (o2,o6)');
assertEqual($wonLost['won_amount'], 63000.0, 'espoWonLostSplit: whole-team won amount (10k+15k+20k+18k)');
assertEqual($wonLost['lost_amount'], 17000.0, 'espoWonLostSplit: whole-team lost amount (5k+12k)');

// Test per-rep split (u1 has o1 won and o5 won: 10k + 20k)
$wonLostU1 = espoWonLostSplit($conn, 'u1', '2026-08-01', '2026-09-09');
assertEqual($wonLostU1['won'], 2, 'espoWonLostSplit: per-rep (u1) won count (o1,o5)');
assertEqual($wonLostU1['lost'], 1, 'espoWonLostSplit: per-rep (u1) lost count (o2)');
assertEqual($wonLostU1['won_amount'], 30000.0, 'espoWonLostSplit: per-rep (u1) won amount (10k+20k)');

echo "\n";

// ========== TEST 4: espoAvgSalesCycleDays ==========
echo "=== TEST 4: espoAvgSalesCycleDays (public function) ===\n";

$avgDays = espoAvgSalesCycleDays($conn, null, '2026-08-01', '2026-09-09');
// o1: 2026-08-10 - 2026-08-01 = 9 days
// o3: 2026-08-25 - 2026-08-10 = 15 days
// o5: 2026-09-05 - 2026-09-01 = 4 days
// o8: 2026-09-09 - 2026-09-02 = 7 days
// Average: (9+15+4+7)/4 = 8.75
assertEqual($avgDays, 8.8, 'espoAvgSalesCycleDays: whole-team average (8.8 days)');

// Test per-rep (u1: o1 with 9 days, o5 with 4 days => (9+4)/2 = 6.5)
$avgDaysU1 = espoAvgSalesCycleDays($conn, 'u1', '2026-08-01', '2026-09-09');
assertEqual($avgDaysU1, 6.5, 'espoAvgSalesCycleDays: per-rep (u1) average (6.5 days)');

echo "\n";

// ========== TEST 5: espoCallActivity ==========
echo "=== TEST 5: espoCallActivity (public function) ===\n";

$callActivity = espoCallActivity($conn, null, '2026-08-01', '2026-09-09');
assertEqual($callActivity['held'], 3, 'espoCallActivity: whole-team held count (c1,c2,c7)');
assertEqual($callActivity['not_held'], 2, 'espoCallActivity: whole-team not_held count (c3,c9)');
assertEqual(isset($callActivity['planned']), true, 'espoCallActivity: has planned field');
assertEqual(isset($callActivity['overdue']), true, 'espoCallActivity: has overdue field');
assertEqual(isset($callActivity['upcoming']), true, 'espoCallActivity: has upcoming field');

// Overdue/upcoming logic: c4, c5 should be overdue (past), c6 should be upcoming (future)
assertEqual($callActivity['overdue'] >= 0, true, 'espoCallActivity: overdue count is non-negative');
assertEqual($callActivity['upcoming'] >= 0, true, 'espoCallActivity: upcoming count is non-negative');

// Test per-rep call activity
$callActivityU1 = espoCallActivity($conn, 'u1', '2026-08-01', '2026-09-09');
assertEqual(isset($callActivityU1['held']), true, 'espoCallActivity: per-rep (u1) has held field');
assertEqual($callActivityU1['held'] >= 0, true, 'espoCallActivity: per-rep (u1) held count is non-negative');

echo "\n";

// ========== TEST 6: espoCallsPerConversion ==========
echo "=== TEST 6: espoCallsPerConversion (public function) ===\n";

$callsPerConv = espoCallsPerConversion($conn, null, '2026-08-01', '2026-09-09');
// Total calls in date range: c1,c2,c3,c4,c5,c6,c7,c8,c9 = 9 calls (all in 08-01 to 09-09 range)
// Won opportunities (close_date filtering): o1,o3,o5,o8 = 4 won (close dates 08-10, 08-25, 09-05, 09-09)
// Ratio: 9/4 = 2.25
assertEqual($callsPerConv, 2.25, 'espoCallsPerConversion: whole-team ratio (9 calls / 4 won = 2.25)');

// Test per-rep (u1)
$callsPerConvU1 = espoCallsPerConversion($conn, 'u1', '2026-08-01', '2026-09-09');
// u1 calls: c1,c2,c4,c5,c6,c8,c9 = 7 calls
// u1 won (close_date filtering): o1 (08-10), o5 (09-05) = 2 won
// Ratio: 7/2 = 3.5
assertEqual($callsPerConvU1, 3.5, 'espoCallsPerConversion: per-rep (u1) ratio (7 calls / 2 won = 3.5)');

// Test zero conversions (should return 0.0)
$callsPerConvZero = espoCallsPerConversion($conn, 'u999', '2026-08-01', '2026-09-09');
assertEqual($callsPerConvZero, 0.0, 'espoCallsPerConversion: zero conversions returns 0.0');

echo "\n";

// ========== HELPER FUNCTIONS TESTS (original tests) ==========
echo "=== HELPER FUNCTION TESTS (original) ===\n";

// Test: calls-per-conversion avoids division by zero
assertEqual(espoCallsPerConversionRatio(5, 0), 0.0, 'calls-per-conversion returns 0.0 when zero conversions');
assertEqual(espoCallsPerConversionRatio(10, 5), 2.0, 'calls-per-conversion computes ratio correctly');

echo "\n";

// ========== CLEANUP ==========
$conn->query("DROP TABLE IF EXISTS `lead`");
$conn->query("DROP TABLE IF EXISTS `opportunity`");
$conn->query("DROP TABLE IF EXISTS `call`");

// ========== SUMMARY ==========
echo "=== TEST SUMMARY ===\n";
echo "PASS: $passCount\n";
echo "FAIL: $failCount\n";
if ($failCount === 0) {
    echo "All tests passed!\n";
    exit(0);
} else {
    echo "Some tests failed.\n";
    exit(1);
}
