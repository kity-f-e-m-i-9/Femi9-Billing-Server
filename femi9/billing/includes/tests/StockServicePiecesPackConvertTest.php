<?php
// femi9/billing/includes/tests/StockServicePiecesPackConvertTest.php
// Manual run: php StockServicePiecesPackConvertTest.php
//
// Regression/behavior test for the Pieces <-> Pack converter
// (docs/superpowers/specs/2026-09-18-pieces-pack-converter-design.md):
// confirms StockService::convertPiecesToPack()/convertPackToPieces()
// correctly move quantity between closing_qty and extra_pieces on the
// same stock row, scope by warehouse_id, write a ledger entry per
// call, and refuse when there isn't enough of the source unit.
//
// Uses a disposable `stock_service_pieces_pack_test` schema — never
// the app's production database — with real `stock` / `stock_ledger`
// table shapes.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const TEST_SCHEMA = 'stock_service_pieces_pack_test';

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

$stockService = new StockService($conn);

// ---- Fixture: product 50, godown '3', warehouse 401 — 5 packs, 8 loose pieces. pieces_per_pack=12 ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, extra_pieces, user_type, user_id, warehouse_id)
    VALUES (50, 0, 5, 0, 0, 0, 5, 8, 'company', '3', 401)");
// A second, unrelated row for the same product but a DIFFERENT warehouse — proves scoping.
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, extra_pieces, user_type, user_id, warehouse_id)
    VALUES (50, 0, 100, 0, 0, 0, 100, 999, 'company', '3', 402)");

// ---- Test: insufficient pieces (need 12, have 8) throws and changes nothing ----
$threw = false;
try {
    $stockService->convertPiecesToPack(50, 'company', '3', 12, 1, 'CONV-1', 'tester', false, 401);
} catch (StockException $e) {
    $threw = true;
}
assertEqual($threw, true, 'convertPiecesToPack throws StockException when extra_pieces < piecesPerPack');

$unchanged = $conn->query("SELECT closing_qty, extra_pieces FROM stock WHERE product_id=50 AND warehouse_id=401")->fetch_assoc();
assertEqual((int)$unchanged['closing_qty'], 5, 'Failed conversion leaves closing_qty untouched');
assertEqual((int)$unchanged['extra_pieces'], 8, 'Failed conversion leaves extra_pieces untouched');

// ---- Test: pack -> pieces (break open 1 pack) succeeds: 5 packs -> 4, 8 pieces -> 20 ----
$result = $stockService->convertPackToPieces(50, 'company', '3', 12, 1, 'CONV-2', 'tester', false, 401);
assertEqual($result['success'], true, 'convertPackToPieces succeeds when closing_qty >= packCount');
assertEqual($result['closing_qty_after'], 4, 'convertPackToPieces return value reports closing_qty_after=4');
assertEqual($result['extra_pieces_after'], 20, 'convertPackToPieces return value reports extra_pieces_after=20 (8+12)');

$afterBreak = $conn->query("SELECT closing_qty, extra_pieces FROM stock WHERE product_id=50 AND warehouse_id=401")->fetch_assoc();
assertEqual((int)$afterBreak['closing_qty'], 4, 'DB: closing_qty decremented to 4');
assertEqual((int)$afterBreak['extra_pieces'], 20, 'DB: extra_pieces incremented to 20');

// ---- Test: now pieces -> pack succeeds (20 >= 12): closing_qty 4->5, extra_pieces 20->8 ----
$result2 = $stockService->convertPiecesToPack(50, 'company', '3', 12, 1, 'CONV-3', 'tester', false, 401);
assertEqual($result2['closing_qty_after'], 5, 'convertPiecesToPack return value reports closing_qty_after=5');
assertEqual($result2['extra_pieces_after'], 8, 'convertPiecesToPack return value reports extra_pieces_after=8 (20-12)');

// ---- Test: multi-pack conversion in one call (packCount=2): needs 24 pieces ----
$conn->query("UPDATE stock SET extra_pieces=30 WHERE product_id=50 AND warehouse_id=401");
$result3 = $stockService->convertPiecesToPack(50, 'company', '3', 12, 2, 'CONV-4', 'tester', false, 401);
assertEqual($result3['closing_qty_after'], 7, 'Multi-pack convertPiecesToPack(packCount=2): closing_qty 5->7');
assertEqual($result3['extra_pieces_after'], 6, 'Multi-pack convertPiecesToPack(packCount=2): extra_pieces 30-24=6');

// ---- Test: warehouse 402's row is completely untouched by all the above ----
$other = $conn->query("SELECT closing_qty, extra_pieces FROM stock WHERE product_id=50 AND warehouse_id=402")->fetch_assoc();
assertEqual((int)$other['closing_qty'], 100, 'Warehouse-402 row closing_qty untouched by warehouse-401 conversions');
assertEqual((int)$other['extra_pieces'], 999, 'Warehouse-402 row extra_pieces untouched by warehouse-401 conversions');

// ---- Test: ledger entries record the correct action/qty/before/after ----
$ledgerRows = $conn->query("SELECT action, qty, qty_before, qty_after, warehouse_id FROM stock_ledger WHERE product_id=50 ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
assertEqual(count($ledgerRows), 3, 'Exactly 3 ledger rows written (1 failed attempt writes none, 3 successful conversions each write 1)');
assertEqual($ledgerRows[0]['action'], 'pack_to_pieces', 'Ledger row 1 action=pack_to_pieces');
assertEqual((int)$ledgerRows[0]['qty'], 1, 'Ledger row 1 qty=1 (pack count)');
assertEqual((int)$ledgerRows[0]['qty_before'], 5, 'Ledger row 1 qty_before=5 (closing_qty before)');
assertEqual((int)$ledgerRows[0]['qty_after'], 4, 'Ledger row 1 qty_after=4 (closing_qty after)');
assertEqual($ledgerRows[1]['action'], 'pieces_to_pack', 'Ledger row 2 action=pieces_to_pack');
assertEqual($ledgerRows[2]['action'], 'pieces_to_pack', 'Ledger row 3 action=pieces_to_pack');
assertEqual((int)$ledgerRows[2]['qty'], 2, 'Ledger row 3 (multi-pack) qty=2');
assertEqual((int)$ledgerRows[0]['warehouse_id'], 401, 'Ledger rows record the correct warehouse_id');

// ---- Test: no stock row at all for the given warehouse throws ----
$threwNoRow = false;
try {
    $stockService->convertPiecesToPack(50, 'company', '3', 12, 1, 'CONV-5', 'tester', false, 403);
} catch (StockException $e) {
    $threwNoRow = true;
}
assertEqual($threwNoRow, true, 'convertPiecesToPack throws StockException when no stock row exists for that warehouse');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);