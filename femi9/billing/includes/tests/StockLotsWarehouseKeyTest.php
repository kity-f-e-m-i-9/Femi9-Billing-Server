<?php
// femi9/billing/includes/tests/StockLotsWarehouseKeyTest.php
// Manual run: php StockLotsWarehouseKeyTest.php
//
// Regression/behavior test for Phase 4 of the per-godown split-stock spec
// (docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md):
// confirms StockLots::recordLot()/consumeFifo() correctly scope by
// warehouse_id when given one, and reproduce today's exact behavior
// (drawing from the unassigned/NULL bucket) when given none.
//
// Uses a disposable `stock_lots_warehouse_test` schema — never the app's
// production database — with real `stock_lots` /
// `stock_ledger_lot_consumption` table shapes.

require_once __DIR__ . '/../../company/include/StockLots.php';
require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const TEST_SCHEMA = 'stock_lots_warehouse_test';

$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `" . TEST_SCHEMA . "`")) {
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

// ========== SETUP: real table shapes, post-Phase-4 ==========
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

function fallbackRate(): float { return 99.0; } // distinctive sentinel — should never be hit while real lots cover the qty

// ========== TESTS: recordLot tags the warehouse_id ==========
$lotId = StockLots::recordLot($conn, 10, 'company', '1', 5.00, 100, '2026-09-01', 'test', 'ref-1', 'tester', 201);
$row = $conn->query("SELECT warehouse_id FROM stock_lots WHERE id=$lotId")->fetch_assoc();
assertEqual((int)$row['warehouse_id'], 201, 'recordLot(warehouse=201) tags the lot with warehouse_id');

$lotIdNull = StockLots::recordLot($conn, 10, 'company', '1', 6.00, 50, '2026-09-02', 'test', 'ref-2', 'tester', null);
$rowNull = $conn->query("SELECT warehouse_id FROM stock_lots WHERE id=$lotIdNull")->fetch_assoc();
assertEqual($rowNull['warehouse_id'], null, 'recordLot(warehouse=null) leaves warehouse_id NULL (unassigned)');

// ========== TESTS: consumeFifo only draws from the matching warehouse's lots ==========
// Product 20: two lots in warehouse 301 (oldest first), one lot in warehouse 302, one unassigned lot.
StockLots::recordLot($conn, 20, 'company', '2', 10.00, 30, '2026-09-01', 'test', 'w301-a', 'tester', 301);
StockLots::recordLot($conn, 20, 'company', '2', 12.00, 40, '2026-09-05', 'test', 'w301-b', 'tester', 301);
StockLots::recordLot($conn, 20, 'company', '2', 20.00, 100, '2026-09-01', 'test', 'w302-a', 'tester', 302);
StockLots::recordLot($conn, 20, 'company', '2', 30.00, 100, '2026-09-01', 'test', 'unassigned-a', 'tester', null);

// Consume 50 units from warehouse 301: should take all 30 from the oldest
// lot (rate 10.00) then 20 from the second lot (rate 12.00) — never touch
// warehouse 302's or the unassigned lot.
$consumed301 = StockLots::consumeFifo($conn, 20, 'company', '2', 50, 'fallbackRate', 301);
assertEqual(count($consumed301), 2, 'consumeFifo(warehouse=301) draws from exactly 2 lots (FIFO order within that warehouse)');
assertEqual($consumed301[0]['qty_taken'], 30, 'consumeFifo(warehouse=301) takes the full oldest lot first (30 units @ rate 10.00)');
assertEqual($consumed301[0]['rate'], 10.00, 'consumeFifo(warehouse=301) oldest lot rate is correct');
assertEqual($consumed301[1]['qty_taken'], 20, 'consumeFifo(warehouse=301) takes the remainder from the second lot (20 units @ rate 12.00)');
assertEqual($consumed301[1]['rate'], 12.00, 'consumeFifo(warehouse=301) second lot rate is correct');

$wh302Row = $conn->query("SELECT qty_remaining FROM stock_lots WHERE product_id=20 AND warehouse_id=302")->fetch_assoc();
assertEqual((int)$wh302Row['qty_remaining'], 100, 'consumeFifo(warehouse=301) leaves warehouse-302 lot completely untouched');

$unassignedRow = $conn->query("SELECT qty_remaining FROM stock_lots WHERE product_id=20 AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$unassignedRow['qty_remaining'], 100, 'consumeFifo(warehouse=301) leaves the unassigned lot completely untouched');

// Consume 40 units with warehouse=null: should draw only from the
// unassigned lot, not warehouse 301 or 302.
$consumedNull = StockLots::consumeFifo($conn, 20, 'company', '2', 40, 'fallbackRate', null);
assertEqual(count($consumedNull), 1, 'consumeFifo(warehouse=null) draws from exactly 1 lot (the unassigned one)');
assertEqual($consumedNull[0]['qty_taken'], 40, 'consumeFifo(warehouse=null) takes the correct qty from the unassigned lot');
assertEqual($consumedNull[0]['rate'], 30.00, 'consumeFifo(warehouse=null) unassigned lot rate is correct');

$unassignedAfter = $conn->query("SELECT qty_remaining FROM stock_lots WHERE product_id=20 AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$unassignedAfter['qty_remaining'], 60, 'consumeFifo(warehouse=null) correctly decremented the unassigned lot (100-40=60)');

// ========== TEST: fallback rate still triggers when a warehouse's own lots are insufficient ==========
// Warehouse 301 had 70 total (30+40); the earlier 50-unit consumption left
// exactly 20 remaining in the second lot. Drain that remaining 20 first so
// warehouse 301 has genuinely 0 left, then confirm a further request falls
// back rather than bleeding into warehouse 302 or the unassigned lot.
$drain301 = StockLots::consumeFifo($conn, 20, 'company', '2', 20, 'fallbackRate', 301);
assertEqual($drain301[0]['qty_taken'], 20, 'Draining warehouse 301\'s remaining 20 units succeeds from its own lot (no fallback yet)');

$consumedShort = StockLots::consumeFifo($conn, 20, 'company', '2', 10, 'fallbackRate', 301);
assertEqual(count($consumedShort), 1, 'consumeFifo(warehouse=301) with no remaining lots falls back to fallbackRateFn');
assertEqual($consumedShort[0]['stock_lot_id'], null, 'Fallback consumption has no stock_lot_id');
assertEqual($consumedShort[0]['rate'], 99.0, 'Fallback consumption uses the sentinel fallback rate (99.0), never bleeds into warehouse 302 or unassigned lots');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);