<?php
// TEMPORARY read-only check — before touching anything further, confirm
// exactly what manual_corr_* ledger rows already exist on THIS server, and
// the current live stock numbers for the 4 affected products, so we don't
// risk double-crediting stock by re-running a correction that's already
// been applied. Delete this file after use.
include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");
error_reporting(0);

header('Content-Type: text/plain; charset=utf-8');

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['admin', 'finance'], true)) {
    die("Access denied — admin/finance only.\n");
}

echo "=== All manual_corr_* ledger entries on this server ===\n";
$res = $db_conn->query("SELECT id, product_id, qty, qty_before, qty_after, ref_id, created_at FROM stock_ledger WHERE ref_id LIKE 'manual_corr_%' ORDER BY id");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

echo "\n=== Current live stock (FEMI NAYAN LLP, warehouse G1) for the 4 products ===\n";
$res2 = $db_conn->query("SELECT product_id, sales_qty, closing_qty, updated_at FROM stock WHERE product_id IN (4,51,3,11) AND user_type='company' AND user_id='1' AND warehouse_id=2");
while ($row = $res2->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

echo "\nDone. Paste this whole output back, then delete this file.\n";
