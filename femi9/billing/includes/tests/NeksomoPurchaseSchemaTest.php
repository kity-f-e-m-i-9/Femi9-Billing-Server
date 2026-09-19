<?php
// femi9/billing/includes/tests/NeksomoPurchaseSchemaTest.php
// Manual run: php NeksomoPurchaseSchemaTest.php
//
// Regression/behavior test for the shared credit/reverse helpers in
// company/include/NeksomoPurchaseSchema.php, used by
// neksomo-manufacturer-purchase-action.php (Add), edit-neksomo-
// manufacturer-purchase-action.php (Edit), and
// delete-neksomo-manufacturer-purchase.php (Delete): confirms
// neksomo_credit_pieces()/neksomo_reverse_pieces() correctly move
// quantity between closing_qty and extra_pieces, scope by warehouse_id,
// and that ensure_neksomo_manufacturer_purchases_warehouse_column()
// self-migrates.
//
// Uses a disposable `neksomo_purchase_schema_test` schema — never the
// app's production database — with real `stock` / `stock_ledger` /
// `neksomo_manufacturer_purchases` table shapes.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/NeksomoPurchaseSchema.php';
require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const TEST_SCHEMA = 'neksomo_purchase_schema_test';

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

// StockService::credit()/reverseCredit() call ensureNeksomoTopUp(), which
// queries company_godown for a gname='NEKSOMO HYGIENE INDUSTRIES' row even
// when unrelated to Neksomo's pool — an empty table is enough for
// get_neksomo_godown_id() to return null and short-circuit as a no-op.
$conn->query("CREATE TABLE company_godown (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gname VARCHAR(255) NOT NULL
)");

$conn->query("CREATE TABLE neksomo_manufacturer_purchases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    product_id INT NOT NULL,
    manufacturer_name VARCHAR(255) NOT NULL,
    purchase_date DATE NOT NULL,
    total_amount DECIMAL(16,6) NOT NULL DEFAULT 0,
    total_taxable_value DECIMAL(16,6) NOT NULL DEFAULT 0,
    total_gst_amount DECIMAL(16,6) NOT NULL DEFAULT 0,
    quantity_packs INT UNSIGNED NOT NULL DEFAULT 0,
    cost_per_piece DECIMAL(14,6) NOT NULL DEFAULT 0,
    total_cost DECIMAL(16,6) NOT NULL DEFAULT 0,
    created_by VARCHAR(100) NOT NULL DEFAULT ''
)");

$stockService = new StockService($conn);

// ========== TESTS: ensure_neksomo_manufacturer_purchases_warehouse_column ==========
$colBefore = $conn->query("SHOW COLUMNS FROM neksomo_manufacturer_purchases LIKE 'warehouse_id'");
assertEqual($colBefore->num_rows, 0, 'setup: warehouse_id column does not exist yet');

ensure_neksomo_manufacturer_purchases_warehouse_column($conn);
$colAfter = $conn->query("SHOW COLUMNS FROM neksomo_manufacturer_purchases LIKE 'warehouse_id'");
assertEqual($colAfter->num_rows, 1, 'ensure_neksomo_manufacturer_purchases_warehouse_column() self-migrates the column');

// Calling it again must not error (idempotent).
ensure_neksomo_manufacturer_purchases_warehouse_column($conn);
$colAgain = $conn->query("SHOW COLUMNS FROM neksomo_manufacturer_purchases LIKE 'warehouse_id'");
assertEqual($colAgain->num_rows, 1, 'calling the guard twice is idempotent');

// ========== TESTS: neksomo_credit_pieces() ==========
function closingAndExtra($conn, $productId, $godownId, $warehouseId) {
    $sql = "SELECT closing_qty, extra_pieces FROM stock WHERE product_id=? AND user_type='company' AND user_id=?
              AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ' . (int) $warehouseId);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $productId, $godownId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? [(int) $row['closing_qty'], (int) $row['extra_pieces']] : [null, null];
}

// pieces_per_pack=10: crediting 25 pieces at warehouse 501 -> 2 packs + 5 loose.
neksomo_credit_pieces($conn, $stockService, 100, '5', 10, 25, 'ref-1', 'tester', 501);
[$closing, $extra] = closingAndExtra($conn, 100, '5', 501);
assertEqual($closing, 2, 'neksomo_credit_pieces: 25 pieces / 10 per pack = 2 whole packs credited');
assertEqual($extra, 5, 'neksomo_credit_pieces: 5 leftover pieces held as extra_pieces');

// A second credit of 8 more pieces (5+8=13 -> 1 more pack, 3 leftover).
neksomo_credit_pieces($conn, $stockService, 100, '5', 10, 8, 'ref-2', 'tester', 501);
[$closing2, $extra2] = closingAndExtra($conn, 100, '5', 501);
assertEqual($closing2, 3, 'neksomo_credit_pieces: second credit accumulates onto the same warehouse row (2+1=3 packs)');
assertEqual($extra2, 3, 'neksomo_credit_pieces: remainder correctly carries (5+8=13, 1 more pack, 3 left over)');

// A DIFFERENT warehouse for the same product must be fully independent.
neksomo_credit_pieces($conn, $stockService, 100, '5', 10, 50, 'ref-3', 'tester', 502);
[$closingOther, $extraOther] = closingAndExtra($conn, 100, '5', 502);
assertEqual($closingOther, 5, 'neksomo_credit_pieces: different warehouse gets its own independent row (50/10=5 packs)');
[$closingUnchanged] = closingAndExtra($conn, 100, '5', 501);
assertEqual($closingUnchanged, 3, 'neksomo_credit_pieces: crediting warehouse 502 leaves warehouse 501 untouched');

// ========== TESTS: neksomo_reverse_pieces() ==========
// Reverse 3 pieces from warehouse 501 (currently 3 packs, 3 extra pieces):
// net = 3 - 3 = 0, so no pack needs to be broken open.
neksomo_reverse_pieces($conn, $stockService, 100, '5', 10, 3, 'ref-4', 'tester', 501);
[$closing3, $extra3] = closingAndExtra($conn, 100, '5', 501);
assertEqual($closing3, 3, 'neksomo_reverse_pieces: reversing exactly the extra_pieces balance leaves closing_qty untouched');
assertEqual($extra3, 0, 'neksomo_reverse_pieces: extra_pieces correctly zeroed');

// Reverse 15 more pieces (currently 3 packs=30 pieces + 0 extra = 30 pieces
// total on hand): net = 0 - 15 = -15, borrows back 2 packs (ceil(15/10)=2),
// leaving 2*10-15=5 pieces re-added as extra.
neksomo_reverse_pieces($conn, $stockService, 100, '5', 10, 15, 'ref-5', 'tester', 501);
[$closing4, $extra4] = closingAndExtra($conn, 100, '5', 501);
assertEqual($closing4, 1, 'neksomo_reverse_pieces: borrows back whole packs when the piece reversal exceeds extra_pieces (3-2=1 pack left)');
assertEqual($extra4, 5, 'neksomo_reverse_pieces: leftover from the borrowed packs correctly re-added to extra_pieces');

// Reversing against a (product, godown, warehouse) with no stock row at
// all is a silent no-op — nothing to reverse.
neksomo_reverse_pieces($conn, $stockService, 999, '5', 10, 5, 'ref-6', 'tester', 501);
[$closingNone, $extraNone] = closingAndExtra($conn, 999, '5', 501);
assertEqual($closingNone, null, 'neksomo_reverse_pieces: no-ops silently when no stock row exists for that product/warehouse');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
