<?php
// femi9/billing/includes/tests/TpInvoiceWarehouseKeyTest.php
// Manual run: php TpInvoiceWarehouseKeyTest.php
//
// Confirms the refactored tp-invoice-action.php / edit-tp-invoice-action.php
// godown-sourced path correctly threads warehouse_id through
// StockService::deduct()/reverseDeduct(), replacing the old hand-rolled
// raw-SQL helpers, per
// docs/superpowers/specs/2026-09-18-invoice-warehouse-selection-design.md.
//
// Exercises StockService directly with the exact call shape the
// refactored files use — this is the behavior contract Step 3/5 below
// must implement in the real files.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/db-connect.php';

$conn = $db_conn;

const TEST_SCHEMA = 'tp_invoice_warehouse_test';

$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `" . TEST_SCHEMA . "`")) {
    fwrite(STDERR, "FATAL: could not CREATE DATABASE — " . $conn->error . "\n");
    exit(1);
}
if (!$conn->select_db(TEST_SCHEMA)) {
    fwrite(STDERR, "FATAL: could not switch schema — " . $conn->error . "\n");
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

// ========== SETUP ==========
$conn->query("CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, opening_qty INT NOT NULL DEFAULT 0,
    opening_date DATE NOT NULL DEFAULT '2026-01-01', input_qty INT NOT NULL DEFAULT 0,
    sales_qty INT NOT NULL DEFAULT 0, sent_qty INT NOT NULL DEFAULT 0, returnqty INT NOT NULL DEFAULT 0,
    closing_qty INT NOT NULL DEFAULT 0, extra_pieces INT UNSIGNED NOT NULL DEFAULT 0,
    user_type VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, warehouse_id INT NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id)
)");
$conn->query("CREATE TABLE stock_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL, warehouse_id INT NULL, action VARCHAR(50) NOT NULL, qty INT NOT NULL,
    qty_before INT NOT NULL, qty_after INT NOT NULL, ref_type VARCHAR(50) NOT NULL, ref_id VARCHAR(255) NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '', created_by VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE company_godown (id INT AUTO_INCREMENT PRIMARY KEY, gname VARCHAR(255) NOT NULL)");
$conn->query("CREATE TABLE stock_lots (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(32) NOT NULL,
    user_id VARCHAR(32) NOT NULL, rate DECIMAL(12,6) NOT NULL, qty_purchased INT NOT NULL,
    qty_remaining INT NOT NULL, purchase_date DATE NOT NULL, ref_type VARCHAR(32) NOT NULL,
    ref_id VARCHAR(64) NULL, created_by VARCHAR(64) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE stock_ledger_lot_consumption (
    id INT AUTO_INCREMENT PRIMARY KEY, stock_ledger_id INT NOT NULL, stock_lot_id INT NULL,
    qty_taken INT NOT NULL, rate DECIMAL(12,6) NOT NULL
)");
$conn->query("CREATE TABLE neksomo_llp_piece_rates (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, effective_date DATE NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL, gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_type VARCHAR(10) NOT NULL DEFAULT 'exclusive'
)");
$conn->query("CREATE TABLE femi9_llp_sale_rates (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, effective_date DATE NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL, gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_type VARCHAR(10) NOT NULL DEFAULT 'exclusive'
)");
$conn->query("CREATE TABLE products (id INT AUTO_INCREMENT PRIMARY KEY, pieces_per_pack INT NULL)");

$stockService = new StockService($conn);

// ---- Fixture: godown '7' (a company_godown id used as TP invoice source), warehouse 601 ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (70, 0, 90, 0, 0, 0, 90, 'company', '7', 601)");
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (70, 0, 150, 0, 0, 0, 150, 'company', '7', NULL)");

// ---- Test: creating a TP invoice (deduct) with warehouse_id=601 ----
$stockService->deduct(70, 'company', '7', 25, 'tp_invoice', 'TPINV-1', 'tester', false, 601);

$wh601 = $conn->query("SELECT closing_qty, sales_qty FROM stock WHERE product_id=70 AND user_id='7' AND warehouse_id=601")->fetch_assoc();
$unassigned = $conn->query("SELECT closing_qty FROM stock WHERE product_id=70 AND user_id='7' AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$wh601['closing_qty'], 65, 'TP invoice deduct(warehouse=601) deducts from the warehouse-601 row (90-25=65)');
assertEqual((int)$wh601['sales_qty'], 25, 'TP invoice deduct(warehouse=601) increments sales_qty (matches old debitGodownForTp behavior)');
assertEqual((int)$unassigned['closing_qty'], 150, 'TP invoice deduct(warehouse=601) leaves the unassigned row untouched');

$ledgerRow = $conn->query("SELECT action, ref_type, warehouse_id FROM stock_ledger WHERE product_id=70 AND user_id='7' AND ref_id='TPINV-1'")->fetch_assoc();
assertEqual($ledgerRow['action'], 'deduct', 'TP invoice deduct writes ledger action=deduct (new convention, was transfer_out)');
assertEqual($ledgerRow['ref_type'], 'tp_invoice', 'TP invoice deduct writes ledger ref_type=tp_invoice (new convention, was transfer)');
assertEqual((int)$ledgerRow['warehouse_id'], 601, 'TP invoice deduct ledger records the warehouse_id');

// ---- Test: editing/reversing that same invoice restores stock via reverseDeduct ----
$stockService->reverseDeduct(70, 'company', '7', 25, 'tp_invoice', 'TPINV-1', 'tester', false, 601);
$wh601After = $conn->query("SELECT closing_qty, sales_qty FROM stock WHERE product_id=70 AND user_id='7' AND warehouse_id=601")->fetch_assoc();
assertEqual((int)$wh601After['closing_qty'], 90, 'reverseDeduct(warehouse=601) restores closing_qty (65+25=90)');
assertEqual((int)$wh601After['sales_qty'], 0, 'reverseDeduct(warehouse=601) restores sales_qty (25-25=0)');

// ---- Test: a TP invoice with no warehouse selected (null) hits the unassigned row ----
$stockService->deduct(70, 'company', '7', 40, 'tp_invoice', 'TPINV-2', 'tester', false, null);
$unassignedAfter = $conn->query("SELECT closing_qty FROM stock WHERE product_id=70 AND user_id='7' AND warehouse_id IS NULL")->fetch_assoc();
$wh601Unchanged = $conn->query("SELECT closing_qty FROM stock WHERE product_id=70 AND user_id='7' AND warehouse_id=601")->fetch_assoc();
assertEqual((int)$unassignedAfter['closing_qty'], 110, 'TP invoice deduct(warehouse=null) deducts from the unassigned row (150-40=110)');
assertEqual((int)$wh601Unchanged['closing_qty'], 90, 'TP invoice deduct(warehouse=null) leaves warehouse-601 untouched');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);