<?php
// femi9/billing/includes/tests/StockServiceReverseTransferInTest.php
// Manual run: php StockServiceReverseTransferInTest.php
//
// Regression test for the Sep 8, 2026 closing-stock bug: deleting an
// internal transfer whose transferred-in stock had already been partly
// sold/moved out was silently flooring closing_qty at 0 instead of
// refusing the reversal (see STOCK_AUDIT_2026.md "Floors at zero" rule,
// which was never meant to apply to reversals eating real sales).
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

// ========== SETUP: real `stock` / `stock_ledger` table shapes ==========
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
    updated_at TIMESTAMP NULL
)");

$conn->query("CREATE TABLE stock_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_type VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
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

// ---- Scenario reproducing the Sep 8 bug ----
// Destination godown received 300 units via transfer-in (closing 198 -> 498).
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id)
    VALUES (14, 0, 300, 0, 0, 0, 498, 'company', '1')");

// 230 units legitimately sold/transferred out afterward: closing 498 -> 268.
// (Applied directly via SQL rather than StockService::deduct() — deduct()
// also calls into NeksomoStockBridge, which needs a `company_godown`
// table this fixture doesn't set up; the effect on `stock` is identical.)
$conn->query("UPDATE stock SET sales_qty = sales_qty + 230, closing_qty = closing_qty - 230
    WHERE product_id=14 AND user_type='company' AND user_id='1'");
$setupRow = $conn->query("SELECT closing_qty FROM stock WHERE product_id=14 AND user_type='company' AND user_id='1'")->fetch_assoc();
assertEqual((int)$setupRow['closing_qty'], 268, 'setup: closing stock is 268 after legitimate sale');

// Now the original transfer-in of 300 gets deleted/cancelled. Only 268
// units are still there, so the reversal cannot be fully honored.
$reverseResult = $stockService->reverseTransferIn(
    14, 'company', '1', 300, 'transfer', 'ref-transfer-1', 'tester', false
);

assertEqual($reverseResult['success'], false, 'reverseTransferIn refuses when stock already partly consumed');
assertEqual($reverseResult['reason'] ?? null, 'insufficient_stock_to_reverse', 'reverseTransferIn reports insufficient_stock_to_reverse');
assertEqual($reverseResult['available'] ?? null, 268, 'reverseTransferIn reports correct available qty');

// Closing stock must be untouched — NOT floored to 0.
$row = $conn->query("SELECT closing_qty FROM stock WHERE product_id=14 AND user_type='company' AND user_id='1'")->fetch_assoc();
assertEqual((int)$row['closing_qty'], 268, 'closing_qty is untouched after refused reversal (not floored to 0)');

// No stray ledger entry should have been written for the refused reversal.
$ledgerCount = $conn->query("SELECT COUNT(*) AS c FROM stock_ledger WHERE action='transfer_in_reverse'")->fetch_assoc()['c'];
assertEqual((int)$ledgerCount, 0, 'no transfer_in_reverse ledger entry written for refused reversal');

// ---- Control: reversal succeeds normally when stock is still fully available ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id)
    VALUES (14, 0, 300, 0, 0, 0, 300, 'company', '2')");

$okResult = $stockService->reverseTransferIn(
    14, 'company', '2', 300, 'transfer', 'ref-transfer-2', 'tester', false
);
assertEqual($okResult['success'], true, 'reverseTransferIn succeeds when full qty still available');
assertEqual($okResult['qty_after'], 0, 'reverseTransferIn correctly reduces closing_qty to 0 when fully reversible');

// ========== TEARDOWN ==========
$conn->select_db('information_schema'); // step off the schema before dropping it
$conn->query("DROP DATABASE IF EXISTS `" . STOCK_TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
