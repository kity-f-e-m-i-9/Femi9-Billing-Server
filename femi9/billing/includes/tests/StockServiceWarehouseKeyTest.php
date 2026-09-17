<?php
// femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php
// Manual run: php StockServiceWarehouseKeyTest.php
//
// Regression/behavior test for Phase 1 of the per-godown split-stock spec
// (docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md):
// confirms StockService's private key helpers (lockStockRow,
// updateStockSnapshot, writeLedger) correctly scope by warehouse_id when
// given one, and reproduce today's exact behavior when given none (null).
//
// Uses a disposable `stock_service_test` schema — never the app's
// production database — with real `stock` / `stock_ledger` table shapes.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const STOCK_TEST_SCHEMA = 'stock_service_test';

$conn->query("DROP DATABASE IF EXISTS `" . STOCK_TEST_SCHEMA . "`");
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `" . STOCK_TEST_SCHEMA . "`")) {
    fwrite(STDERR, "FATAL: could not CREATE DATABASE `" . STOCK_TEST_SCHEMA . "` — " . $conn->error . "\n");
    exit(1);
}
if (!$conn->select_db(STOCK_TEST_SCHEMA)) {
    fwrite(STDERR, "FATAL: could not switch to schema `" . STOCK_TEST_SCHEMA . "` — " . $conn->error . "\n");
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

// ========== SETUP: real `stock` / `stock_ledger` table shapes, post-Phase-1 ==========
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

// StockService::deduct()/transferOut()/otDeduct() call ensureNeksomoTopUp(),
// which queries company_godown via NeksomoStockBridge.php's
// get_neksomo_godown_id(). An empty table (no NEKSOMO HYGIENE INDUSTRIES row)
// is enough — the lookup returns 0, and every Neksomo-pool code path
// early-returns for any user_id that isn't that real godown id, which none
// of this test's fixture rows are.
$conn->query("CREATE TABLE company_godown (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gname VARCHAR(255) NOT NULL
)");

// deduct()/transferOut() also call StockLots::consumeFifo(), which queries
// stock_lots regardless of warehouse (FIFO lot warehouse-awareness is
// Phase 4, out of scope here) — an empty table is enough; no lots means
// consumeFifo() falls back to fallbackRate() and returns no consumption.
$conn->query("CREATE TABLE stock_lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_type VARCHAR(32) NOT NULL,
    user_id VARCHAR(32) NOT NULL,
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

// fallbackRate() (used when no lot covers a deduct) queries these two rate
// tables — empty is fine, COALESCE(...) in that query falls through to 0.
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
    pieces_per_pack INT NULL
)");

// ---- Two rows for the SAME product/entity, different warehouses ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (14, 0, 300, 0, 0, 0, 300, 'company', '1', 101)");
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (14, 0, 200, 0, 0, 0, 200, 'company', '1', NULL)");

$reflection = new ReflectionClass('StockService');
$stockService = new StockService($conn);

function callPrivate($obj, string $method, array $args) {
    $ref = new ReflectionMethod($obj, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs($obj, $args);
}

// ---- lockStockRow: warehouse_id distinguishes the two rows ----
$rowWh101 = callPrivate($stockService, 'lockStockRow', [14, 'company', '1', 101]);
assertEqual((int)$rowWh101['closing_qty'], 300, 'lockStockRow(warehouse=101) reads the warehouse-101 row');

$rowNull = callPrivate($stockService, 'lockStockRow', [14, 'company', '1', null]);
assertEqual((int)$rowNull['closing_qty'], 200, 'lockStockRow(warehouse=null) reads the unassigned row, not the 101 row');

// ---- updateStockSnapshot: only touches the row matching warehouse_id ----
callPrivate($stockService, 'updateStockSnapshot', [14, 'company', '1', ['closing_qty' => 999], 101]);
$after101 = $conn->query("SELECT closing_qty FROM stock WHERE product_id=14 AND user_type='company' AND user_id='1' AND warehouse_id=101")->fetch_assoc();
$afterNull = $conn->query("SELECT closing_qty FROM stock WHERE product_id=14 AND user_type='company' AND user_id='1' AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$after101['closing_qty'], 999, 'updateStockSnapshot(warehouse=101) updates only the 101 row');
assertEqual((int)$afterNull['closing_qty'], 200, 'updateStockSnapshot(warehouse=101) leaves the unassigned row untouched');

// ---- writeLedger: records warehouse_id on the ledger row ----
$ledgerId = callPrivate($stockService, 'writeLedger', [14, 'company', '1', 'credit', 50, 200, 250, 'adjustment', 'ref-1', '', 'tester', 101]);
$ledgerRow = $conn->query("SELECT warehouse_id FROM stock_ledger WHERE id=$ledgerId")->fetch_assoc();
assertEqual((int)$ledgerRow['warehouse_id'], 101, 'writeLedger records the given warehouse_id');

$ledgerIdNull = callPrivate($stockService, 'writeLedger', [14, 'company', '1', 'credit', 50, 200, 250, 'adjustment', 'ref-2', '', 'tester', null]);
$ledgerRowNull = $conn->query("SELECT warehouse_id FROM stock_ledger WHERE id=$ledgerIdNull")->fetch_assoc();
assertEqual($ledgerRowNull['warehouse_id'], null, 'writeLedger(warehouse=null) records NULL, matching pre-Phase-1 behavior');

// ---- Backward compatibility: omitting the parameter entirely behaves like null ----
$rowOmitted = callPrivate($stockService, 'lockStockRow', [14, 'company', '1']);
assertEqual((int)$rowOmitted['closing_qty'], 200, 'lockStockRow with warehouse param omitted defaults to null (unassigned row)');

// ---- Public API: deduct/credit correctly scope by warehouse_id ----
// Two independent stock pools for the same product/entity, different godowns.
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (20, 0, 100, 0, 0, 0, 100, 'company', '5', 201)");
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (20, 0, 50, 0, 0, 0, 50, 'company', '5', 202)");

// Deduct 30 from warehouse 201 only — warehouse 202's 50 units must be untouched.
$deductResult = $stockService->deduct(20, 'company', '5', 30, 'adjustment', 'wh-test-1', 'tester', false, 201);
assertEqual($deductResult['success'], true, 'deduct(warehouse=201) succeeds');
$wh201After = $conn->query("SELECT closing_qty FROM stock WHERE product_id=20 AND user_type='company' AND user_id='5' AND warehouse_id=201")->fetch_assoc();
$wh202After = $conn->query("SELECT closing_qty FROM stock WHERE product_id=20 AND user_type='company' AND user_id='5' AND warehouse_id=202")->fetch_assoc();
assertEqual((int)$wh201After['closing_qty'], 70, 'deduct(warehouse=201) reduces only the 201 row (100 -> 70)');
assertEqual((int)$wh202After['closing_qty'], 50, 'deduct(warehouse=201) leaves the 202 row untouched (still 50)');

// Deducting more than warehouse 201 has left (70) must fail even though
// warehouse 202 + 201 combined would cover it — no cross-warehouse fallback.
try {
    $stockService->deduct(20, 'company', '5', 71, 'adjustment', 'wh-test-2', 'tester', false, 201);
    assertEqual('no exception thrown', 'StockException', 'deduct(warehouse=201, qty=71) throws StockException (insufficient in that warehouse alone)');
} catch (StockException $e) {
    assertEqual(true, true, 'deduct(warehouse=201, qty=71) throws StockException (insufficient in that warehouse alone)');
}

// credit() with a warehouse_id on a brand-new product/entity/warehouse combo
// creates a row carrying that warehouse_id.
$creditResult = $stockService->credit(21, 'company', '5', 40, 'adjustment', 'wh-test-3', 'tester', false, 301);
assertEqual($creditResult['success'], true, 'credit(warehouse=301) on new row succeeds');
$newRow = $conn->query("SELECT closing_qty, warehouse_id FROM stock WHERE product_id=21 AND user_type='company' AND user_id='5' AND warehouse_id=301")->fetch_assoc();
assertEqual((int)$newRow['closing_qty'], 40, 'credit(warehouse=301) created row has correct closing_qty');
assertEqual((int)$newRow['warehouse_id'], 301, 'credit(warehouse=301) created row carries the warehouse_id');

// Backward compatibility: calling deduct/credit with NO warehouse arg at all
// (today's exact call shape, 8 positional args) must still work unchanged.
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (22, 0, 10, 0, 0, 0, 10, 'company', '5', NULL)");
$legacyDeduct = $stockService->deduct(22, 'company', '5', 5, 'adjustment', 'wh-test-4', 'tester', false);
assertEqual($legacyDeduct['success'], true, 'deduct() called with the pre-Phase-1 8-argument shape still succeeds');
$legacyRow = $conn->query("SELECT closing_qty FROM stock WHERE product_id=22 AND user_type='company' AND user_id='5' AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$legacyRow['closing_qty'], 5, 'deduct() with no warehouse arg updates the unassigned row exactly as before');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . STOCK_TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
