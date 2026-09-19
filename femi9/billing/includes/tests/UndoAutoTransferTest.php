<?php
// femi9/billing/includes/tests/UndoAutoTransferTest.php
// Manual run: php UndoAutoTransferTest.php
//
// Regression/behavior test for undo_auto_transfer() in
// company/include/AutoTransferDemand.php: reverses both legs
// (Neksomo -> Healthcare -> LLP) of one product's already-completed
// auto-transfer, refuses if the stock has already moved on further down
// the chain, and never touches a sibling product/tempid it wasn't asked
// to undo.
//
// Uses a disposable `undo_auto_transfer_test` schema — never the app's
// production database — with real `stock` / `stock_ledger` / `stock_lots`
// / `stock_ledger_lot_consumption` / `internal_transfer` /
// `internal_transfer_invoice` table shapes.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/AutoTransferDemand.php';
require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const TEST_SCHEMA = 'undo_auto_transfer_test';

$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `" . TEST_SCHEMA . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
    fwrite(STDERR, "FATAL: could not CREATE DATABASE `" . TEST_SCHEMA . "` — " . $conn->error . "\n");
    exit(1);
}
if (!$conn->select_db(TEST_SCHEMA)) {
    fwrite(STDERR, "FATAL: could not switch to schema `" . TEST_SCHEMA . "` — " . $conn->error . "\n");
    exit(1);
}

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

// ========== SETUP: real table shapes ==========
$conn->query("CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    opening_qty INT NOT NULL DEFAULT 0,
    opening_date DATE NOT NULL DEFAULT '2026-01-01',
    input_qty INT NOT NULL DEFAULT 0,
    sales_qty INT NOT NULL DEFAULT 0,
    sent_qty INT NOT NULL DEFAULT 0,
    returnqty INT NOT NULL DEFAULT 0,
    closing_qty INT NOT NULL DEFAULT 0,
    extra_pieces INT UNSIGNED NOT NULL DEFAULT 0,
    user_type VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    warehouse_id INT NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id)
)");

$conn->query("CREATE TABLE stock_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_type VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    warehouse_id INT NULL,
    action VARCHAR(50) NOT NULL,
    qty INT NOT NULL,
    qty_before INT NOT NULL,
    qty_after INT NOT NULL,
    ref_type VARCHAR(50) NOT NULL,
    ref_id VARCHAR(255) NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_by VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE stock_lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_type VARCHAR(32) NOT NULL,
    user_id VARCHAR(32) NOT NULL,
    warehouse_id INT NULL,
    rate DECIMAL(12,6) NOT NULL,
    qty_purchased INT NOT NULL,
    qty_remaining INT NOT NULL,
    purchase_date DATE NOT NULL,
    ref_type VARCHAR(32) NOT NULL,
    ref_id VARCHAR(64) NULL,
    created_by VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE stock_ledger_lot_consumption (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stock_ledger_id INT NOT NULL,
    stock_lot_id INT NULL,
    qty_taken INT NOT NULL,
    rate DECIMAL(12,6) NOT NULL
)");

$conn->query("CREATE TABLE internal_transfer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tempid VARCHAR(255) NOT NULL,
    send_from INT NOT NULL,
    send_to INT NOT NULL,
    date DATE NOT NULL,
    product_id INT NOT NULL,
    qty INT NOT NULL,
    returned_qty INT NOT NULL DEFAULT 0,
    price INT NOT NULL DEFAULT 0,
    discount VARCHAR(255) NOT NULL DEFAULT '0',
    sub_total VARCHAR(255) NOT NULL DEFAULT '0',
    gst INT NOT NULL DEFAULT 0,
    gst_type VARCHAR(20) NOT NULL DEFAULT 'exclusive',
    taxable_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    gst_amount VARCHAR(255) NOT NULL DEFAULT '0',
    total VARCHAR(255) NOT NULL DEFAULT '0',
    hsn VARCHAR(255) NOT NULL DEFAULT '',
    username VARCHAR(255) NOT NULL DEFAULT '',
    usertype VARCHAR(255) NOT NULL DEFAULT ''
)");

$conn->query("CREATE TABLE internal_transfer_invoice (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tempid VARCHAR(255) NOT NULL,
    inv_id VARCHAR(50) NOT NULL DEFAULT '0',
    inv_number VARCHAR(255) NOT NULL DEFAULT '',
    courier_charges VARCHAR(50) NOT NULL DEFAULT '0'
)");

// StockService::transferOut() calls ensureNeksomoTopUp(), which queries
// company_godown for a gname='NEKSOMO HYGIENE INDUSTRIES' row even when
// (as here) the product being moved has nothing to do with Neksomo's
// pool — an empty table is enough for get_neksomo_godown_id() to return
// null and short-circuit the top-up as a no-op.
$conn->query("CREATE TABLE company_godown (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gname VARCHAR(255) NOT NULL
)");

// StockService::fallbackRate() falls back to this table's most recent
// rate when no stock_lots row covers the qty being moved (none are
// seeded here, so every transferOut() consumes via this fallback).
$conn->query("CREATE TABLE neksomo_llp_piece_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    effective_date DATE NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL,
    gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_type VARCHAR(10) NOT NULL DEFAULT 'exclusive'
)");

$conn->query("CREATE TABLE femi9_llp_sale_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    effective_date DATE NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL,
    gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_type VARCHAR(10) NOT NULL DEFAULT 'exclusive'
)");

$conn->query("CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    productName VARCHAR(255) NOT NULL DEFAULT '',
    pieces_per_pack INT NULL
)");
$conn->query("INSERT INTO products (id, productName) VALUES
    (100, 'Product A'), (200, 'Product B'), (300, 'Product C')");

$stockService = new StockService($conn);

const NEKSOMO_ID    = 3;
const HEALTHCARE_ID = 2;
const LLP_ID        = 1;
const PRODUCT_A     = 100;
const PRODUCT_B     = 200;

// Mirrors internal_transfer_auto_action.php's own $writeLeg(): only
// inserts the shared invoice header once per tempid (a run can move
// several products under the same tempid pair, all sharing one header).
function insertInvoiceIfMissing($conn, $tempid, $invNumber) {
    $count = $conn->query("SELECT COUNT(*) AS n FROM internal_transfer_invoice WHERE tempid = '$tempid'")->fetch_assoc()['n'];
    if ((int) $count === 0) {
        $conn->query("INSERT INTO internal_transfer_invoice (tempid, inv_number) VALUES ('$tempid', '$invNumber')");
    }
}

function seedAutoTransferRun($conn, $stockService, $tempidBase, $productId, $qty) {
    $tempid1 = $tempidBase . '-N1';
    $tempid2 = $tempidBase . '-N2';

    // Leg 1: Neksomo -> Healthcare
    $stockService->transferOut($productId, 'company', (string) NEKSOMO_ID, $qty, 'transfer', $tempid1, 'seed', true);
    $stockService->transferIn($productId, 'company', (string) HEALTHCARE_ID, $qty, 'transfer', $tempid1, 'seed', true);
    insertInvoiceIfMissing($conn, $tempid1, 'G/1');
    $conn->query("INSERT INTO internal_transfer (tempid, send_from, send_to, date, product_id, qty) VALUES ('$tempid1', " . NEKSOMO_ID . ", " . HEALTHCARE_ID . ", CURDATE(), $productId, $qty)");

    // Leg 2: Healthcare -> LLP
    $stockService->transferOut($productId, 'company', (string) HEALTHCARE_ID, $qty, 'transfer', $tempid2, 'seed', true);
    $stockService->transferIn($productId, 'company', (string) LLP_ID, $qty, 'transfer', $tempid2, 'seed', true);
    insertInvoiceIfMissing($conn, $tempid2, 'S/1');
    $conn->query("INSERT INTO internal_transfer (tempid, send_from, send_to, date, product_id, qty) VALUES ('$tempid2', " . HEALTHCARE_ID . ", " . LLP_ID . ", CURDATE(), $productId, $qty)");
}

// Seed initial stock: Neksomo has plenty, Healthcare/LLP start empty.
$conn->query("INSERT INTO stock (product_id, closing_qty, user_type, user_id) VALUES (" . PRODUCT_A . ", 1000, 'company', '" . NEKSOMO_ID . "')");
$conn->query("INSERT INTO stock (product_id, closing_qty, user_type, user_id) VALUES (" . PRODUCT_A . ", 0, 'company', '" . HEALTHCARE_ID . "')");
$conn->query("INSERT INTO stock (product_id, closing_qty, user_type, user_id) VALUES (" . PRODUCT_A . ", 500, 'company', '" . LLP_ID . "')");
$conn->query("INSERT INTO stock (product_id, closing_qty, user_type, user_id) VALUES (" . PRODUCT_B . ", 2000, 'company', '" . NEKSOMO_ID . "')");
$conn->query("INSERT INTO stock (product_id, closing_qty, user_type, user_id) VALUES (" . PRODUCT_B . ", 0, 'company', '" . HEALTHCARE_ID . "')");
$conn->query("INSERT INTO stock (product_id, closing_qty, user_type, user_id) VALUES (" . PRODUCT_B . ", 300, 'company', '" . LLP_ID . "')");

// Two products moved in the SAME auto-transfer run (shared tempid base) —
// undoing product A must never touch product B's rows/stock.
seedAutoTransferRun($conn, $stockService, 'AUTOTEST1', PRODUCT_A, 100);
seedAutoTransferRun($conn, $stockService, 'AUTOTEST1', PRODUCT_B, 50);

// ---- Baseline stock after seeding ----
function closingQty($conn, $productId, $godownId) {
    $row = $conn->query("SELECT closing_qty FROM stock WHERE product_id=$productId AND user_type='company' AND user_id='$godownId'")->fetch_assoc();
    return (int) $row['closing_qty'];
}

assertEqual(closingQty($conn, PRODUCT_A, NEKSOMO_ID), 900, 'setup: product A Neksomo stock reduced by 100');
assertEqual(closingQty($conn, PRODUCT_A, HEALTHCARE_ID), 0, 'setup: product A Healthcare stock net zero (100 in, 100 out)');
assertEqual(closingQty($conn, PRODUCT_A, LLP_ID), 600, 'setup: product A LLP stock increased by 100');

// ---- Undo product A's transfer ----
$result = undo_auto_transfer($conn, 'AUTOTEST1-N2', PRODUCT_A, 'company', NEKSOMO_ID, HEALTHCARE_ID, LLP_ID, 'tester');
assertEqual($result['success'], true, 'undo_auto_transfer succeeds for product A');

assertEqual(closingQty($conn, PRODUCT_A, NEKSOMO_ID), 1000, 'product A Neksomo stock fully restored');
assertEqual(closingQty($conn, PRODUCT_A, HEALTHCARE_ID), 0, 'product A Healthcare stock still net zero after undo');
assertEqual(closingQty($conn, PRODUCT_A, LLP_ID), 500, 'product A LLP stock reverted to original');

$remainingA = $conn->query("SELECT COUNT(*) AS c FROM internal_transfer WHERE tempid IN ('AUTOTEST1-N1', 'AUTOTEST1-N2') AND product_id=" . PRODUCT_A)->fetch_assoc()['c'];
assertEqual((int) $remainingA, 0, 'product A internal_transfer rows deleted');

// ---- Product B (same tempid base, different product) is untouched ----
assertEqual(closingQty($conn, PRODUCT_B, NEKSOMO_ID), 1950, 'product B Neksomo stock untouched by product A undo');
assertEqual(closingQty($conn, PRODUCT_B, HEALTHCARE_ID), 0, 'product B Healthcare stock untouched (still net zero)');
assertEqual(closingQty($conn, PRODUCT_B, LLP_ID), 350, 'product B LLP stock untouched by product A undo');

$remainingB = $conn->query("SELECT COUNT(*) AS c FROM internal_transfer WHERE tempid IN ('AUTOTEST1-N1', 'AUTOTEST1-N2') AND product_id=" . PRODUCT_B)->fetch_assoc()['c'];
assertEqual((int) $remainingB, 2, 'product B internal_transfer rows still present (2 legs)');

// internal_transfer_invoice rows must survive too, since product B's line
// items still reference the same tempids.
$invoiceCount = $conn->query("SELECT COUNT(*) AS c FROM internal_transfer_invoice WHERE tempid IN ('AUTOTEST1-N1', 'AUTOTEST1-N2')")->fetch_assoc()['c'];
assertEqual((int) $invoiceCount, 2, 'internal_transfer_invoice rows survive since product B still references them');

// ---- Undoing again (already-deleted rows) refuses cleanly ----
$resultAgain = undo_auto_transfer($conn, 'AUTOTEST1-N2', PRODUCT_A, 'company', NEKSOMO_ID, HEALTHCARE_ID, LLP_ID, 'tester');
assertEqual($resultAgain['success'], false, 'undoing an already-undone transfer refuses');
assertEqual($resultAgain['reason'] ?? null, 'not_found', 'refusal reason is not_found');

// ---- Refuses when LLP stock already moved on (partially sold) ----
seedAutoTransferRun($conn, $stockService, 'AUTOTEST2', PRODUCT_A, 200);
assertEqual(closingQty($conn, PRODUCT_A, LLP_ID), 700, 'setup: product A LLP stock after second transfer (500+200)');

// Simulate 650 of the 700 having been sold onward — only 50 left, less
// than the 200 this undo would need to reverse.
$conn->query("UPDATE stock SET closing_qty = 50 WHERE product_id=" . PRODUCT_A . " AND user_type='company' AND user_id='" . LLP_ID . "'");

$resultRefused = undo_auto_transfer($conn, 'AUTOTEST2-N2', PRODUCT_A, 'company', NEKSOMO_ID, HEALTHCARE_ID, LLP_ID, 'tester');
assertEqual($resultRefused['success'], false, 'undo refuses when stock already moved on further down the chain');
assertEqual($resultRefused['reason'] ?? null, 'insufficient_stock_to_reverse', 'refusal reason is insufficient_stock_to_reverse');
assertEqual($resultRefused['available'] ?? null, 50, 'refusal reports correct available qty');

$stillPresent = $conn->query("SELECT COUNT(*) AS c FROM internal_transfer WHERE tempid IN ('AUTOTEST2-N1', 'AUTOTEST2-N2')")->fetch_assoc()['c'];
assertEqual((int) $stillPresent, 2, 'internal_transfer rows untouched after refused undo (nothing deleted)');

// ---- Malformed tempid (not ending in -N2) refuses without side effects ----
$resultMalformed = undo_auto_transfer($conn, 'AUTOTEST1-N1', PRODUCT_A, 'company', NEKSOMO_ID, HEALTHCARE_ID, LLP_ID, 'tester');
assertEqual($resultMalformed['success'], false, 'undo refuses a tempid that is not the -N2 leg');
assertEqual($resultMalformed['reason'] ?? null, 'not_found', 'malformed tempid refusal reason is not_found');

// ---- get_auto_transfer_history_grouped_for_date(): groups a
// multi-product run into one row, invoice-wise (matching Manage Internal
// Stock Transfer's own layout) ----
$conn->query("INSERT INTO stock (product_id, closing_qty, user_type, user_id) VALUES (300, 400, 'company', '" . NEKSOMO_ID . "')");
$conn->query("INSERT INTO stock (product_id, closing_qty, user_type, user_id) VALUES (300, 0, 'company', '" . HEALTHCARE_ID . "')");
$conn->query("INSERT INTO stock (product_id, closing_qty, user_type, user_id) VALUES (300, 0, 'company', '" . LLP_ID . "')");
seedAutoTransferRun($conn, $stockService, 'AUTOTEST3', 300, 25);
seedAutoTransferRun($conn, $stockService, 'AUTOTEST3', PRODUCT_B, 10);

$today = date('Y-m-d');
$runs = get_auto_transfer_history_grouped_for_date($conn, $today, NEKSOMO_ID, HEALTHCARE_ID, LLP_ID);
$run3 = null;
foreach ($runs as $r) { if ($r['tempid'] === 'AUTOTEST3-N2') { $run3 = $r; break; } }

assertEqual($run3 !== null, true, 'grouped history finds the AUTOTEST3 run');
assertEqual(count($run3['products'] ?? []), 2, 'AUTOTEST3 run groups both its products (300 and PRODUCT_B) into one row');
assertEqual($run3['inv_number_leg1'], 'G/1', 'grouped run resolves leg 1 invoice number');
assertEqual($run3['inv_number_leg2'], 'S/1', 'grouped run resolves leg 2 invoice number');

$productIdsInRun = array_column($run3['products'], 'product_id');
sort($productIdsInRun);
$expectedIds = [300, PRODUCT_B];
sort($expectedIds);
assertEqual($productIdsInRun, $expectedIds, 'grouped run lists exactly the 2 products moved in that run');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
