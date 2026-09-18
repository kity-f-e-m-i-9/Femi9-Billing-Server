<?php
// femi9/billing/includes/tests/StockReturnUpdateWarehouseKeyTest.php
// Manual run: php StockReturnUpdateWarehouseKeyTest.php
//
// Regression/behavior test for Phase 2 of the per-godown split-stock spec
// (docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md):
// confirms stock_return_update.php's two raw `stock` statements (the
// SELECT ... FOR UPDATE lock and the UPDATE) correctly scope by
// warehouse_id now that it's part of stock's unique key.
//
// Mirrors stock_return_update.php's statements verbatim (not a require of
// the script itself, which is a direct-execution POST handler gated on
// $_REQUEST['add-record'] and session state) — this proves the SQL shape
// is correct against a disposable `stock_return_test` schema with real
// table shapes.

require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const TEST_SCHEMA = 'stock_return_test';

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

// ---- Fixture: a product/entity with BOTH a warehouse-tagged row and the
// unassigned row the return must actually touch. ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (40, 0, 0, 0, 0, 0, 300, 'company', '1', 777)");
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (40, 0, 0, 0, 0, 0, 100, 'company', '1', NULL)");

// ---- Replicate stock_return_update.php's exact statement shapes, POST-FIX ----
function runStockReturnFlow($conn, int $prid, string $userType, string $userId, int $returnQty) {
    $s = $conn->prepare(
        "SELECT returnqty, closing_qty FROM stock
          WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL
          FOR UPDATE"
    );
    $s->bind_param('iss', $prid, $userType, $userId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$row) {
        throw new \RuntimeException("Stock row not found for product=$prid");
    }

    $newReturnQty  = (int)$row['returnqty']   + $returnQty;
    $newClosingQty = (int)$row['closing_qty'] - $returnQty;

    if ($newClosingQty < 0) {
        throw new \RuntimeException("Insufficient stock for company return");
    }

    $s = $conn->prepare(
        "UPDATE stock SET returnqty = ?, closing_qty = ?, updated_at = NOW()
          WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL"
    );
    $s->bind_param('iiiss', $newReturnQty, $newClosingQty, $prid, $userType, $userId);
    $s->execute();
    $s->close();

    return ['before' => (int)$row['closing_qty'], 'after' => $newClosingQty];
}

// ---- Test: return 30 units — must hit the unassigned row (100 -> 70),
// leaving the warehouse-777 row (300) completely untouched. ----
$result = runStockReturnFlow($conn, 40, 'company', '1', 30);
assertEqual($result['before'], 100, 'Stock return reads the unassigned row\'s closing_qty (100), not warehouse 777\'s (300)');
assertEqual($result['after'], 70, 'Stock return correctly computes new closing_qty (100 - 30 = 70)');

$wh777 = $conn->query("SELECT closing_qty, returnqty FROM stock WHERE product_id=40 AND user_type='company' AND user_id='1' AND warehouse_id=777")->fetch_assoc();
$unassigned = $conn->query("SELECT closing_qty, returnqty FROM stock WHERE product_id=40 AND user_type='company' AND user_id='1' AND warehouse_id IS NULL")->fetch_assoc();

assertEqual((int)$wh777['closing_qty'], 300, 'Warehouse-777 row is completely untouched after the return');
assertEqual((int)$wh777['returnqty'], 0, 'Warehouse-777 row\'s returnqty is completely untouched');
assertEqual((int)$unassigned['closing_qty'], 70, 'Unassigned row\'s closing_qty correctly reduced');
assertEqual((int)$unassigned['returnqty'], 30, 'Unassigned row\'s returnqty correctly incremented');

// ---- Test: a product with ONLY a warehouse-tagged row (no unassigned
// row at all) must fail loudly, not silently match the wrong warehouse. ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (41, 0, 0, 0, 0, 0, 50, 'company', '1', 888)");
try {
    runStockReturnFlow($conn, 41, 'company', '1', 10);
    assertEqual('no exception thrown', 'RuntimeException', 'Return against a product with only a warehouse-tagged row throws (no ambiguous match)');
} catch (\RuntimeException $e) {
    assertEqual(true, true, 'Return against a product with only a warehouse-tagged row throws (no ambiguous match)');
}
$wh888Unchanged = $conn->query("SELECT closing_qty FROM stock WHERE product_id=41 AND user_type='company' AND user_id='1' AND warehouse_id=888")->fetch_assoc();
assertEqual((int)$wh888Unchanged['closing_qty'], 50, 'Warehouse-888 row is untouched after the refused return');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
