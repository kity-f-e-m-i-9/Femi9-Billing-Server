<?php
// femi9/billing/includes/tests/InputActionWarehouseKeyTest.php
// Manual run: php InputActionWarehouseKeyTest.php
//
// Regression/behavior test for Phase 2 of the per-godown split-stock spec
// (docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md):
// confirms input-action.php's four raw `stock` statements (stmtChkProd,
// stmtInsertStock, stmtGetStock, stmtUpdateStock) correctly scope by
// warehouse_id now that it's part of stock's unique key, instead of
// silently matching/reusing whichever row happens to exist for
// (product_id, user_type, user_id) regardless of warehouse.
//
// Mirrors input-action.php's statements verbatim (not a require of the
// script itself, which is a direct-execution POST handler gated on
// session state) — this proves the SQL shape is correct against a
// disposable `input_action_test` schema with real table shapes.

require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const TEST_SCHEMA = 'input_action_test';

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

// ========== SETUP: real `stock` table shape, post-Phase-1 ==========
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

// ---- Fixture: a product/entity that ALREADY has a warehouse-tagged row
// (e.g. created directly via the Manage Godowns flow or a future Phase 3
// caller), plus separately the "unassigned" row Add Input Stock must use. ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (30, 0, 500, 0, 0, 0, 500, 'company', '1', 999)");

// ---- Replicate input-action.php's exact statement shapes, POST-FIX ----
// (This block is what Step 3 makes real in input-action.php itself.)
function runInputActionFlow($conn, int $pid, string $userType, string $userId, int $qty, string $inputDate) {
    $stmtChkProd = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL
         FOR UPDATE"
    );
    $stmtChkProd->bind_param('iss', $pid, $userType, $userId);
    $stmtChkProd->execute();
    $cntProd = (int) $stmtChkProd->get_result()->fetch_assoc()['cnt'];
    $stmtChkProd->close();

    if ($cntProd === 0) {
        $stmtInsertStock = $conn->prepare(
            "INSERT INTO stock
                 (product_id, opening_qty, opening_date, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
             VALUES (?, 0, ?, 0, 0, 0, 0, 0, ?, ?, NULL)"
        );
        $stmtInsertStock->bind_param('issi', $pid, $inputDate, $userType, $userId);
        $stmtInsertStock->execute();
        $stmtInsertStock->close();
    }

    $stmtGetStock = $conn->prepare(
        "SELECT input_qty, closing_qty FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL
         FOR UPDATE"
    );
    $stmtGetStock->bind_param('iss', $pid, $userType, $userId);
    $stmtGetStock->execute();
    $stockRow = $stmtGetStock->get_result()->fetch_assoc();
    $stmtGetStock->close();

    $newInputQty   = (int) $stockRow['input_qty']   + $qty;
    $newClosingQty = (int) $stockRow['closing_qty'] + $qty;

    $stmtUpdateStock = $conn->prepare(
        "UPDATE stock SET input_qty = ?, closing_qty = ?
         WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL"
    );
    $stmtUpdateStock->bind_param('iiiss', $newInputQty, $newClosingQty, $pid, $userType, $userId);
    $stmtUpdateStock->execute();
    $stmtUpdateStock->close();
}

// ---- Test: Add Input Stock for a product that has a warehouse-tagged row
// (999) but NOT yet an unassigned row must create the unassigned row,
// leaving warehouse 999's row completely untouched. ----
runInputActionFlow($conn, 30, 'company', '1', 50, '2026-09-17');

$wh999 = $conn->query("SELECT closing_qty FROM stock WHERE product_id=30 AND user_type='company' AND user_id='1' AND warehouse_id=999")->fetch_assoc();
$unassigned = $conn->query("SELECT closing_qty FROM stock WHERE product_id=30 AND user_type='company' AND user_id='1' AND warehouse_id IS NULL")->fetch_assoc();

assertEqual((int)$wh999['closing_qty'], 500, 'Add Input Stock leaves an existing warehouse-999 row completely untouched');
assertEqual($unassigned !== null, true, 'Add Input Stock creates a separate unassigned row rather than reusing warehouse 999\'s row');
assertEqual((int)$unassigned['closing_qty'], 50, 'Add Input Stock unassigned row has the correct closing_qty (50)');

// ---- Test: a SECOND Add Input Stock for the same product correctly
// accumulates onto the unassigned row (not warehouse 999's), fixing the
// exact bug caught during Phase-0 prototyping (see spec Phase 2 notes). ----
runInputActionFlow($conn, 30, 'company', '1', 25, '2026-09-17');

$wh999After2nd = $conn->query("SELECT closing_qty FROM stock WHERE product_id=30 AND user_type='company' AND user_id='1' AND warehouse_id=999")->fetch_assoc();
$unassignedAfter2nd = $conn->query("SELECT closing_qty FROM stock WHERE product_id=30 AND user_type='company' AND user_id='1' AND warehouse_id IS NULL")->fetch_assoc();

assertEqual((int)$wh999After2nd['closing_qty'], 500, 'Second Add Input Stock still leaves warehouse-999 row untouched');
assertEqual((int)$unassignedAfter2nd['closing_qty'], 75, 'Second Add Input Stock correctly accumulates onto the unassigned row (50 + 25 = 75)');

// ---- Test: brand-new product with no existing rows at all creates
// exactly one unassigned row. ----
runInputActionFlow($conn, 31, 'company', '1', 10, '2026-09-17');
$newProductRows = $conn->query("SELECT COUNT(*) AS c FROM stock WHERE product_id=31 AND user_type='company' AND user_id='1'")->fetch_assoc();
$newProductRow = $conn->query("SELECT closing_qty, warehouse_id FROM stock WHERE product_id=31 AND user_type='company' AND user_id='1'")->fetch_assoc();
assertEqual((int)$newProductRows['c'], 1, 'Brand-new product creates exactly one stock row');
assertEqual($newProductRow['warehouse_id'], null, 'Brand-new product\'s row has warehouse_id NULL (unassigned)');
assertEqual((int)$newProductRow['closing_qty'], 10, 'Brand-new product\'s row has the correct closing_qty');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
