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
    ('l4','Dead','2026-09-04 10:00:00','u1',1)"); // deleted, must be excluded

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
