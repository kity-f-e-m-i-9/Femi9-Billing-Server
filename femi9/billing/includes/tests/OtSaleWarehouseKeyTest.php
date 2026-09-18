<?php
// femi9/billing/includes/tests/OtSaleWarehouseKeyTest.php
// Manual run: php OtSaleWarehouseKeyTest.php
//
// Confirms ot-sale-action.php's otDeduct()/otReverse() call sites
// correctly thread a warehouse_id through to StockService, per
// docs/superpowers/specs/2026-09-18-invoice-warehouse-selection-design.md.
//
// This test exercises StockService::otDeduct/otReverse directly with
// the same call shape ot-sale-action.php uses post-fix — it does not
// require the script itself (session-gated POST handler).

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/db-connect.php';

$conn = $db_conn;

const TEST_SCHEMA = 'ot_sale_warehouse_test';

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

// ========== SETUP: real table shapes, post-Phase-1 ==========
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

$conn->query("CREATE TABLE company_godown (id INT AUTO_INCREMENT PRIMARY KEY, gname VARCHAR(255) NOT NULL)");
$conn->query("CREATE TABLE stock_lots (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(32) NOT NULL,
    user_id VARCHAR(32) NOT NULL, warehouse_id INT NULL, rate DECIMAL(12,6) NOT NULL, qty_purchased INT NOT NULL,
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

// ---- Fixture: product 50, entity '3' (an OT godown id), warehouse 401 ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (50, 0, 80, 0, 0, 0, 80, 'company', '3', 401)");
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (50, 0, 200, 0, 0, 0, 200, 'company', '3', NULL)");

// ---- Test: otDeduct with warehouse_id=401 only touches that row ----
$stockService->otDeduct(50, 'company', '3', 20, 'OT-TEMPID-1', 'tester', false, 401);

$wh401 = $conn->query("SELECT closing_qty FROM stock WHERE product_id=50 AND user_id='3' AND warehouse_id=401")->fetch_assoc();
$unassigned = $conn->query("SELECT closing_qty FROM stock WHERE product_id=50 AND user_id='3' AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$wh401['closing_qty'], 60, 'otDeduct(warehouse=401) deducts from the warehouse-401 row (80-20=60)');
assertEqual((int)$unassigned['closing_qty'], 200, 'otDeduct(warehouse=401) leaves the unassigned row untouched');

// ---- Test: otReverse with the same warehouse_id restores it ----
$stockService->otReverse(50, 'company', '3', 20, 'OT-TEMPID-1', 'tester', false, 401);
$wh401After = $conn->query("SELECT closing_qty FROM stock WHERE product_id=50 AND user_id='3' AND warehouse_id=401")->fetch_assoc();
assertEqual((int)$wh401After['closing_qty'], 80, 'otReverse(warehouse=401) restores the warehouse-401 row (60+20=80)');

// ---- Test: omitting warehouse_id (null) hits the unassigned row instead ----
$stockService->otDeduct(50, 'company', '3', 30, 'OT-TEMPID-2', 'tester', false, null);
$unassignedAfter = $conn->query("SELECT closing_qty FROM stock WHERE product_id=50 AND user_id='3' AND warehouse_id IS NULL")->fetch_assoc();
$wh401Unchanged = $conn->query("SELECT closing_qty FROM stock WHERE product_id=50 AND user_id='3' AND warehouse_id=401")->fetch_assoc();
assertEqual((int)$unassignedAfter['closing_qty'], 170, 'otDeduct(warehouse=null) deducts from the unassigned row (200-30=170)');
assertEqual((int)$wh401Unchanged['closing_qty'], 80, 'otDeduct(warehouse=null) leaves warehouse-401 untouched');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);