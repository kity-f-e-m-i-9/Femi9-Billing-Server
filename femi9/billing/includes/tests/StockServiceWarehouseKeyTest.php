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

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . STOCK_TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
