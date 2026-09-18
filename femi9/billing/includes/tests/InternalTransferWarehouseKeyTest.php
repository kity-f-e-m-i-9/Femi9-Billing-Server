<?php
// femi9/billing/includes/tests/InternalTransferWarehouseKeyTest.php
// Manual run: php InternalTransferWarehouseKeyTest.php
//
// Regression/behavior test for Phase 3 (internal transfers) of the
// per-godown split-stock spec
// (docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md):
// confirms internal_transfer_action.php's warehouse_from_id/warehouse_to_id
// POST fields correctly flow through StockService::transferOut/transferIn
// as the trailing $warehouseId argument — moving stock out of the specific
// source warehouse and crediting the specific destination warehouse,
// leaving unassigned/other-warehouse rows untouched.
//
// Mirrors internal_transfer_action.php's exact StockService call shape
// (not a require of the script itself, which is a session-gated POST
// handler) against a disposable `internal_transfer_test` schema with real
// table shapes.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const TEST_SCHEMA = 'internal_transfer_test';

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

// See StockServiceWarehouseKeyTest.php for why these fixture tables are
// needed even though this test never touches them directly: transferOut()
// calls ensureNeksomoTopUp() (company_godown) and StockLots::consumeFifo()
// (stock_lots / rate tables), transferIn() creates stock_lots rows.
$conn->query("CREATE TABLE company_godown (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gname VARCHAR(255) NOT NULL
)");

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

$stockService = new StockService($conn);

// ---- Fixture: product 40, entity '1' (source) has stock in warehouse 201
// AND an unrelated unassigned row; entity '2' (destination) starts empty. ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (40, 0, 100, 0, 0, 0, 100, 'company', '1', 201)");
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (40, 0, 500, 0, 0, 0, 500, 'company', '1', NULL)");

// ---- Replicates internal_transfer_action.php's transferOut/transferIn
// call shape exactly (Phase 3 change: trailing $warehouseId args). ----
function runTransfer($stockService, int $pid, string $sendFrom, string $sendTo, int $qty, ?int $warehouseFromId, ?int $warehouseToId) {
    $outResult = $stockService->transferOut(
        $pid, 'company', $sendFrom, $qty,
        'transfer', 'TEST-TEMPID', 'tester',
        true,
        $warehouseFromId
    );
    $stockService->transferIn(
        $pid, 'company', $sendTo, $qty,
        'transfer', 'TEST-TEMPID', 'tester',
        true,
        $outResult['consumed_rate'] ?? null,
        $warehouseToId
    );
    return $outResult;
}

// ---- Test: transfer 30 units from entity 1's warehouse-201 row to
// entity 2's warehouse-301 row. Entity 1's unassigned row must be
// untouched (proves the "from" side reads the RIGHT warehouse row). ----
runTransfer($stockService, 40, '1', '2', 30, 201, 301);

$fromWh201 = $conn->query("SELECT closing_qty FROM stock WHERE product_id=40 AND user_type='company' AND user_id='1' AND warehouse_id=201")->fetch_assoc();
$fromUnassigned = $conn->query("SELECT closing_qty FROM stock WHERE product_id=40 AND user_type='company' AND user_id='1' AND warehouse_id IS NULL")->fetch_assoc();
$toWh301 = $conn->query("SELECT closing_qty FROM stock WHERE product_id=40 AND user_type='company' AND user_id='2' AND warehouse_id=301")->fetch_assoc();

assertEqual((int)$fromWh201['closing_qty'], 70, 'Source warehouse-201 row deducted correctly (100 - 30 = 70)');
assertEqual((int)$fromUnassigned['closing_qty'], 500, 'Source entity\'s unassigned row is completely untouched by the warehouse-201 transfer');
assertEqual($toWh301 !== null, true, 'Destination warehouse-301 row was created');
assertEqual((int)$toWh301['closing_qty'], 30, 'Destination warehouse-301 row credited correctly');

// ---- Test: a second transfer with warehouse_from_id/warehouse_to_id both
// omitted (null) — the "not tracked" default — must fall back to each
// entity's unassigned row, exactly like before Phase 3, and must NOT
// touch the warehouse-201/301 rows just created above. ----
runTransfer($stockService, 40, '1', '2', 40, null, null);

$fromWh201After = $conn->query("SELECT closing_qty FROM stock WHERE product_id=40 AND user_type='company' AND user_id='1' AND warehouse_id=201")->fetch_assoc();
$fromUnassignedAfter = $conn->query("SELECT closing_qty FROM stock WHERE product_id=40 AND user_type='company' AND user_id='1' AND warehouse_id IS NULL")->fetch_assoc();
$toUnassigned = $conn->query("SELECT closing_qty FROM stock WHERE product_id=40 AND user_type='company' AND user_id='2' AND warehouse_id IS NULL")->fetch_assoc();
$toWh301After = $conn->query("SELECT closing_qty FROM stock WHERE product_id=40 AND user_type='company' AND user_id='2' AND warehouse_id=301")->fetch_assoc();

assertEqual((int)$fromWh201After['closing_qty'], 70, 'Untracked transfer leaves warehouse-201 row untouched');
assertEqual((int)$fromUnassignedAfter['closing_qty'], 460, 'Untracked transfer deducts from the unassigned row instead (500 - 40 = 460)');
assertEqual($toUnassigned !== null, true, 'Untracked transfer creates destination\'s unassigned row');
assertEqual((int)$toUnassigned['closing_qty'], 40, 'Destination unassigned row credited correctly');
assertEqual((int)$toWh301After['closing_qty'], 30, 'Destination warehouse-301 row remains untouched by the untracked transfer');

// ---- Test: ledger entries record the warehouse_id used on each leg. ----
$ledgerOut = $conn->query("SELECT warehouse_id FROM stock_ledger WHERE product_id=40 AND user_id='1' AND action='transfer_out' AND warehouse_id=201")->fetch_assoc();
$ledgerIn = $conn->query("SELECT warehouse_id FROM stock_ledger WHERE product_id=40 AND user_id='2' AND action='transfer_in' AND warehouse_id=301")->fetch_assoc();
assertEqual((int)$ledgerOut['warehouse_id'], 201, 'transfer_out ledger entry records the source warehouse_id');
assertEqual((int)$ledgerIn['warehouse_id'], 301, 'transfer_in ledger entry records the destination warehouse_id');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
