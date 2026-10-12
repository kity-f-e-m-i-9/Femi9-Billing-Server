<?php
// TEMPORARY diagnostic — checks exactly what's known to make company login
// slow: missing indexes on invoice/user_invoice/ms_orders/ot_sales (dropped
// by a DB reimport before, twice), and the ALTER privilege the self-healing
// ensurePerformanceIndexes() needs to re-create them. Delete this file after
// use.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
include("config.php");
error_reporting(0);

header('Content-Type: text/plain; charset=utf-8');

echo "=== Company login / MIS report speed diagnostic ===\n";
echo "Run at: " . date('Y-m-d H:i:s') . "\n\n";

$tables = ['invoice', 'user_invoice', 'ms_orders', 'ms_shop', 'ot_sales', 'ot_sales_invoice'];

echo "--- Row counts ---\n";
foreach ($tables as $t) {
    $r = $db_conn->query("SELECT COUNT(*) c FROM `$t`");
    $c = $r ? $r->fetch_assoc()['c'] : '?';
    echo str_pad($t, 20) . " : $c rows\n";
}

echo "\n--- Indexes present (excluding PRIMARY) ---\n";
foreach ($tables as $t) {
    $r = $db_conn->query("SHOW INDEX FROM `$t` WHERE Key_name != 'PRIMARY'");
    $names = [];
    if ($r) { while ($row = $r->fetch_assoc()) { $names[$row['Key_name']] = true; } }
    echo str_pad($t, 20) . " : " . (empty($names) ? "**NONE** (likely dropped by a reimport)" : implode(', ', array_keys($names))) . "\n";
}

echo "\n--- Can this DB user ALTER TABLE (needed for auto-heal)? ---\n";
$testRes = @$db_conn->query("ALTER TABLE invoice ADD INDEX idx_diag_test_tmp (id)");
if ($testRes) {
    echo "YES — ALTER succeeded, dropping the test index again.\n";
    @$db_conn->query("ALTER TABLE invoice DROP INDEX idx_diag_test_tmp");
} else {
    echo "NO — ALTER TABLE failed: " . $db_conn->error . "\n";
    echo "If this says 'command denied', the DB user lacks ALTER privilege and\n";
    echo "ensurePerformanceIndexes() can NEVER self-heal on this server — indexes\n";
    echo "must be added manually (e.g. via phpMyAdmin) every time a reimport drops them.\n";
}

echo "\n--- Timing the 3 queries previously found to hang without an index ---\n";

$from = date('Y-m-01');
$to   = date('Y-m-t');

$t0 = microtime(true);
$db_conn->query("SELECT COUNT(*), SUM(sub_total) FROM invoice WHERE user_type='territory_partner' AND date BETWEEN '$from' AND '$to'");
echo "invoice (user_type+date)           : " . round(microtime(true) - $t0, 3) . "s\n";

$t0 = microtime(true);
$db_conn->query("SELECT COUNT(*), SUM(sub_total) FROM user_invoice WHERE from_user_type='company' AND date BETWEEN '$from' AND '$to'");
echo "user_invoice (to_user_id composite) : " . round(microtime(true) - $t0, 3) . "s\n";

$t0 = microtime(true);
$db_conn->query("SELECT COUNT(*) FROM ms_orders WHERE order_date BETWEEN '$from' AND '$to'");
echo "ms_orders (order_date)              : " . round(microtime(true) - $t0, 3) . "s\n";

$t0 = microtime(true);
$db_conn->query("SELECT COUNT(*) FROM ot_sales WHERE date BETWEEN '$from' AND '$to'");
echo "ot_sales (date)                     : " . round(microtime(true) - $t0, 3) . "s\n";

echo "\n--- Full mis-report.php page load time ---\n";
$t0 = microtime(true);
ob_start();
include("mis-report.php");
ob_end_clean();
echo "Total include() time: " . round(microtime(true) - $t0, 3) . "s\n";

echo "\nDone. If any query/section above is several seconds or more, that's the\n";
echo "slow part. Paste this whole output back for diagnosis, then delete this file.\n";
