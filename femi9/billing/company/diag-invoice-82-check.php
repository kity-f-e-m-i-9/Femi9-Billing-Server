<?php
// READ-ONLY diagnostic — upload to company/, open once in a logged-in
// company browser tab, paste the output back, then DELETE this file.
// Does not write anything to the database.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
header('Content-Type: text/plain');

$inv_num = 'TP/26-27/1701';
$cp_code = 'CP-0002';

echo "=== tp_invoices row ===\n";
$s = $db_conn->prepare("SELECT * FROM tp_invoices WHERE invoice_number = ?");
$s->bind_param('s', $inv_num);
$s->execute();
$inv = $s->get_result()->fetch_assoc();
$s->close();
if (!$inv) { echo "NOT FOUND — invoice_number mismatch?\n"; exit; }
print_r($inv);

$invoice_id = (int)$inv['id'];
$source_godown_id = (int)$inv['source_godown_id'];
$warehouse_id = $inv['warehouse_id'] !== null ? (int)$inv['warehouse_id'] : null;

echo "\n=== company_godown (current source) ===\n";
$g = $db_conn->prepare("SELECT id, gname FROM company_godown WHERE id = ?");
$g->bind_param('i', $source_godown_id);
$g->execute();
print_r($g->get_result()->fetch_assoc());
$g->close();

echo "\n=== channel_partners row (CP-0002) ===\n";
$cp = $db_conn->prepare("SELECT id, name, cp_id FROM channel_partners WHERE cp_id = ?");
$cp->bind_param('s', $cp_code);
$cp->execute();
$cpRow = $cp->get_result()->fetch_assoc();
$cp->close();
print_r($cpRow);
$cp_id = $cpRow ? (int)$cpRow['id'] : 0;

echo "\n=== tp_invoice_items (line items on this invoice) ===\n";
$items = $db_conn->prepare("SELECT tpii.*, p.productName FROM tp_invoice_items tpii JOIN products p ON p.id = tpii.product_id WHERE tpii.tp_invoice_id = ?");
$items->bind_param('i', $invoice_id);
$items->execute();
$itemRows = $items->get_result()->fetch_all(MYSQLI_ASSOC);
$items->close();
print_r($itemRows);

echo "\n=== Current company_godown stock for each product (seller side, what was wrongly deducted) ===\n";
foreach ($itemRows as $it) {
    $pid = (int)$it['product_id'];
    $wq = $warehouse_id === null ? "warehouse_id IS NULL" : "warehouse_id = $warehouse_id";
    $st = $db_conn->query("SELECT * FROM stock WHERE product_id = $pid AND user_type='company' AND user_id='$source_godown_id' AND $wq");
    echo "Product $pid ({$it['productName']}) qty on invoice: {$it['quantity']}\n";
    print_r($st->fetch_assoc());
}

echo "\n=== Current channel_partner_stock for CP-0002, each product (what should have been deducted) ===\n";
if ($cp_id > 0) {
    foreach ($itemRows as $it) {
        $pid = (int)$it['product_id'];
        $cst = $db_conn->query("SELECT * FROM channel_partner_stock WHERE channel_partner_id = $cp_id AND product_id = $pid");
        echo "Product $pid ({$it['productName']}):\n";
        print_r($cst->fetch_assoc());
    }
}

echo "\n=== stock_ledger rows already written for this invoice (seller side) ===\n";
$sl = $db_conn->query("SELECT * FROM stock_ledger WHERE ref_type='tp_invoice' AND ref_id='" . $db_conn->real_escape_string($inv_num) . "' ORDER BY id");
while ($r = $sl->fetch_assoc()) print_r($r);

echo "\n=== stock_ledger_lot_consumption rows tied to those ledger ids (FIFO cost lots) ===\n";
$sl2 = $db_conn->query("SELECT slc.* FROM stock_ledger sl JOIN stock_ledger_lot_consumption slc ON slc.stock_ledger_id = sl.id WHERE sl.ref_type='tp_invoice' AND sl.ref_id='" . $db_conn->real_escape_string($inv_num) . "'");
while ($r = $sl2->fetch_assoc()) print_r($r);

echo "\n=== tp_advance_payments / advance ledger rows tied to this invoice ===\n";
$tbls = $db_conn->query("SHOW TABLES LIKE '%advance%'");
while ($t = $tbls->fetch_row()) {
    $tname = $t[0];
    $cols = $db_conn->query("SHOW COLUMNS FROM `$tname`");
    $colNames = [];
    while ($c = $cols->fetch_assoc()) { $colNames[] = $c['Field']; }
    if (in_array('tp_invoice_id', $colNames, true)) {
        echo "--- $tname (by tp_invoice_id=$invoice_id) ---\n";
        $r = $db_conn->query("SELECT * FROM `$tname` WHERE tp_invoice_id = $invoice_id");
        while ($row = $r->fetch_assoc()) print_r($row);
    } elseif (in_array('ref_id', $colNames, true)) {
        echo "--- $tname (by ref_id='$inv_num') ---\n";
        $r = $db_conn->query("SELECT * FROM `$tname` WHERE ref_id = '" . $db_conn->real_escape_string($inv_num) . "'");
        while ($row = $r->fetch_assoc()) print_r($row);
    } else {
        echo "--- $tname: columns " . implode(',', $colNames) . " (no obvious invoice link column, skipped) ---\n";
    }
}

echo "\nDONE.\n";
