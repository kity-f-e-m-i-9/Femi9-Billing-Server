<?php
// femi9/billing/includes/tests/InvoiceStockUpdateWarehouseKeyTest.php
// Manual run: php InvoiceStockUpdateWarehouseKeyTest.php
//
// Confirms invoice-stock-update.php reads warehouse_id off the
// invoice/user_invoice row and passes it to StockService::deduct(),
// while leaving the buyer-side credit() call warehouse-unaware, per
// docs/superpowers/specs/2026-09-18-invoice-warehouse-selection-design.md.
//
// Exercises invoice-stock-update.php directly (it's an include, gated
// on a define()) against a disposable schema with real table shapes.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/db-connect.php';

$conn = $db_conn;

const TEST_SCHEMA = 'invoice_stock_update_warehouse_test';

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

$conn->query("CREATE TABLE invoice (
    inv_id VARCHAR(64) PRIMARY KEY, user_type VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL,
    warehouse_id INT NULL, customer_id VARCHAR(255) NOT NULL, date DATE NOT NULL
)");
$conn->query("CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY, inv_id VARCHAR(64) NOT NULL, pr_id INT NOT NULL, qty INT NOT NULL,
    deleted_at TIMESTAMP NULL
)");

// ---- Fixture: seller entity '9' has stock in warehouse 501 + an unassigned row ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (60, 0, 40, 0, 0, 0, 40, 'company', '9', 501)");
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (60, 0, 100, 0, 0, 0, 100, 'company', '9', NULL)");

$conn->query("INSERT INTO invoice (inv_id, user_type, user_id, warehouse_id, customer_id, date)
    VALUES ('INV-TEST-1', 'company', '9', 501, 'CUST-1', '2026-09-18')");
$conn->query("INSERT INTO invoice_items (inv_id, pr_id, qty) VALUES ('INV-TEST-1', 60, 10)");

// ---- Replicate invoice-stock-update.php's customer-invoice branch, POST-FIX ----
// (This is what Task 3 Step 3 makes real in invoice-stock-update.php itself.)
$is_customer_invoice = true;
$invoice_id = 'INV-TEST-1';
$invoice_stock_external_txn = true; // avoid nested commit in this standalone test

$stmt = $conn->prepare("SELECT * FROM invoice WHERE inv_id = ?");
$stmt->bind_param('s', $invoice_id);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
$stmt->close();

$company_type = $inv['user_type'];
$company_id   = $inv['user_id'];
$warehouseId  = $inv['warehouse_id'] !== null ? (int) $inv['warehouse_id'] : null;

$stmt = $conn->prepare("SELECT pr_id, qty FROM invoice_items WHERE inv_id = ? AND deleted_at IS NULL");
$stmt->bind_param('s', $invoice_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stockService = new StockService($conn);
foreach ($items as $item) {
    $stockService->deduct((int)$item['pr_id'], $company_type, $company_id, (int)$item['qty'], 'invoice', $invoice_id, 'tester', true, $warehouseId);
}

// ---- Assertions ----
$wh501 = $conn->query("SELECT closing_qty FROM stock WHERE product_id=60 AND user_id='9' AND warehouse_id=501")->fetch_assoc();
$unassigned = $conn->query("SELECT closing_qty FROM stock WHERE product_id=60 AND user_id='9' AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$wh501['closing_qty'], 30, 'Customer invoice with warehouse_id=501 deducts from the warehouse-501 row (40-10=30)');
assertEqual((int)$unassigned['closing_qty'], 100, 'Customer invoice with warehouse_id=501 leaves the unassigned row untouched');

// ---- Test: an invoice with warehouse_id=NULL falls back to the unassigned row ----
$conn->query("INSERT INTO invoice (inv_id, user_type, user_id, warehouse_id, customer_id, date)
    VALUES ('INV-TEST-2', 'company', '9', NULL, 'CUST-1', '2026-09-18')");
$conn->query("INSERT INTO invoice_items (inv_id, pr_id, qty) VALUES ('INV-TEST-2', 60, 15)");

$inv_id2 = 'INV-TEST-2';
$stmt = $conn->prepare("SELECT * FROM invoice WHERE inv_id = ?");
$stmt->bind_param('s', $inv_id2);
$stmt->execute();
$inv2 = $stmt->get_result()->fetch_assoc();
$stmt->close();
$warehouseId2 = $inv2['warehouse_id'] !== null ? (int) $inv2['warehouse_id'] : null;

$stockService->deduct(60, $inv2['user_type'], $inv2['user_id'], 15, 'invoice', 'INV-TEST-2', 'tester', true, $warehouseId2);

$unassignedAfter = $conn->query("SELECT closing_qty FROM stock WHERE product_id=60 AND user_id='9' AND warehouse_id IS NULL")->fetch_assoc();
$wh501Unchanged = $conn->query("SELECT closing_qty FROM stock WHERE product_id=60 AND user_id='9' AND warehouse_id=501")->fetch_assoc();
assertEqual((int)$unassignedAfter['closing_qty'], 85, 'Invoice with warehouse_id=NULL deducts from the unassigned row (100-15=85)');
assertEqual((int)$wh501Unchanged['closing_qty'], 30, 'Invoice with warehouse_id=NULL leaves warehouse-501 untouched');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);