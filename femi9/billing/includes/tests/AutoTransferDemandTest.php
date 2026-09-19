<?php
// femi9/billing/includes/tests/AutoTransferDemandTest.php
// Manual run: php AutoTransferDemandTest.php
//
// Tests the pure demand-aggregation/capping logic in
// company/include/AutoTransferDemand.php against a disposable schema,
// per docs/superpowers/specs/2026-09-18-auto-internal-transfer-design.md.

require_once __DIR__ . '/../../company/include/db-connect.php';
require_once __DIR__ . '/../../company/include/AutoTransferDemand.php';

$conn = $db_conn;

const TEST_SCHEMA = 'auto_transfer_demand_test';

$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
// Explicit collation: the real app database uses utf8mb4_general_ci
// throughout (see AutoTransferDemand.php's own self-migrating tables).
// Without this, the schema instead inherits the MySQL server's own
// default (utf8mb4_0900_ai_ci on MySQL 8), and joining a table created
// here against one of AutoTransferDemand.php's tables throws "Illegal
// mix of collations".
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `" . TEST_SCHEMA . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
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

// ========== SETUP: minimal real table shapes ==========
$conn->query("CREATE TABLE company_godown (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gname VARCHAR(255) NOT NULL,
    finance_only TINYINT NOT NULL DEFAULT 0,
    contact VARCHAR(255) NOT NULL DEFAULT ''
)");

$conn->query("CREATE TABLE tp_purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    territory_partner_id INT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    status ENUM('waiting','completed') NOT NULL DEFAULT 'waiting',
    tp_invoice_id INT UNSIGNED NULL,
    notes VARCHAR(500) NOT NULL DEFAULT ''
)");

$conn->query("CREATE TABLE tp_purchase_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id INT UNSIGNED NOT NULL,
    product_id INT NOT NULL,
    qty INT NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0
)");

$conn->query("CREATE TABLE ot_sales_invoice (
    tempid VARCHAR(255) NOT NULL,
    status ENUM('confirmed','draft') NOT NULL DEFAULT 'confirmed'
)");

$conn->query("CREATE TABLE ot_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    godownid INT NOT NULL,
    prid INT NOT NULL,
    qty INT NOT NULL,
    date DATE NOT NULL,
    tempid VARCHAR(255) NOT NULL
)");

// ========== FIXTURES ==========
$conn->query("INSERT INTO company_godown (id, gname) VALUES
    (1, 'NEKSOMO HYGIENE INDUSTRIES'),
    (2, 'FEMI HEALTH CARE'),
    (3, 'FEMI NAYAN LLP')");

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// TP PO: product 101 qty 40, waiting, today
$conn->query("INSERT INTO tp_purchase_orders (id, territory_partner_id, order_date, status) VALUES (1, 9, '$today', 'waiting')");
$conn->query("INSERT INTO tp_purchase_order_items (po_id, product_id, qty) VALUES (1, 101, 40)");

// TP PO: product 101 qty 999, but completed (must be excluded)
$conn->query("INSERT INTO tp_purchase_orders (id, territory_partner_id, order_date, status) VALUES (2, 9, '$today', 'completed')");
$conn->query("INSERT INTO tp_purchase_order_items (po_id, product_id, qty) VALUES (2, 101, 999)");

// TP PO: product 101 qty 999, waiting but yesterday (must be excluded)
$conn->query("INSERT INTO tp_purchase_orders (id, territory_partner_id, order_date, status) VALUES (3, 9, '$yesterday', 'waiting')");
$conn->query("INSERT INTO tp_purchase_order_items (po_id, product_id, qty) VALUES (3, 101, 999)");

// OT draft for LLP (godownid=3), product 101 qty 15, today
$conn->query("INSERT INTO ot_sales_invoice (tempid, status) VALUES ('OTD1', 'draft')");
$conn->query("INSERT INTO ot_sales (godownid, prid, qty, date, tempid) VALUES (3, 101, 15, '$today', 'OTD1')");

// OT confirmed (not draft) for LLP, product 101 qty 999 (must be excluded)
$conn->query("INSERT INTO ot_sales_invoice (tempid, status) VALUES ('OTC1', 'confirmed')");
$conn->query("INSERT INTO ot_sales (godownid, prid, qty, date, tempid) VALUES (3, 101, 999, '$today', 'OTC1')");

// OT draft for Healthcare (godownid=2), product 101 qty 999 (wrong godown, must be excluded)
$conn->query("INSERT INTO ot_sales_invoice (tempid, status) VALUES ('OTD2', 'draft')");
$conn->query("INSERT INTO ot_sales (godownid, prid, qty, date, tempid) VALUES (2, 101, 999, '$today', 'OTD2')");

// Product 202: only an OT draft, qty 7, today, LLP
$conn->query("INSERT INTO ot_sales_invoice (tempid, status) VALUES ('OTD3', 'draft')");
$conn->query("INSERT INTO ot_sales (godownid, prid, qty, date, tempid) VALUES (3, 202, 7, '$today', 'OTD3')");

// ========== TESTS: resolve_godown_id_by_gname ==========
assertEqual(resolve_godown_id_by_gname($conn, 'FEMI HEALTH CARE'), 2, 'resolves Healthcare godown id');
assertEqual(resolve_godown_id_by_gname($conn, 'FEMI NAYAN LLP'), 3, 'resolves LLP godown id');
assertEqual(resolve_godown_id_by_gname($conn, 'DOES NOT EXIST'), null, 'returns null for unknown gname');

// ========== TESTS: get_auto_transfer_requirements ==========
$requirements = get_auto_transfer_requirements($conn, 3);
assertEqual($requirements[101] ?? null, 55, 'product 101: 40 (waiting TP today) + 15 (LLP draft OT today) = 55, excludes completed/yesterday/confirmed/wrong-godown');
assertEqual($requirements[202] ?? null, 7, 'product 202: only the LLP draft OT counts');
assertEqual(count($requirements), 2, 'no extraneous product keys');

// ========== TESTS: cap_auto_transfer_qty ==========
assertEqual(cap_auto_transfer_qty(55, 30, 10), 40, 'caps to neksomo+healthcare available when short');
assertEqual(cap_auto_transfer_qty(55, 100, 100), 55, 'uses required qty when stock is sufficient');
assertEqual(cap_auto_transfer_qty(55, 0, 0), 0, 'floors at 0 when no stock anywhere');

// ========== SUMMARY ==========
echo "\n$passCount passed, $failCount failed\n";
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
exit($failCount > 0 ? 1 : 0);
