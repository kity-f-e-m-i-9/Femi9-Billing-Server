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
    status ENUM('waiting','completed','cancelled') NOT NULL DEFAULT 'waiting',
    approver_type ENUM('company','ss') NOT NULL DEFAULT 'company',
    preferred_cp_id INT NULL,
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

$conn->query("CREATE TABLE channel_partner_purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel_partner_id INT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    status ENUM('waiting','completed','cancelled') NOT NULL DEFAULT 'waiting'
)");

$conn->query("CREATE TABLE channel_partner_purchase_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id INT UNSIGNED NOT NULL,
    product_id INT NOT NULL,
    qty INT NOT NULL
)");

// Shop Invoice (company/ms-order-invoice-add.php's "no CP" path, 2026-11-13).
$conn->query("CREATE TABLE user_invoice (
    inv_id VARCHAR(255) NOT NULL PRIMARY KEY,
    source_ms_order_id VARCHAR(255) NULL,
    status ENUM('draft','submitted','cancelled') NOT NULL DEFAULT 'draft',
    from_user_type VARCHAR(255) NOT NULL,
    from_user_id VARCHAR(255) NOT NULL
)");

$conn->query("CREATE TABLE user_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inv_id VARCHAR(255) NOT NULL,
    pr_id INT NOT NULL,
    qty INT NOT NULL
)");

// Submitted-invoice marker (company/shop-user-invoice-submit.php inserts a
// row here the first time an invoice is finalized; user_invoice.status
// itself never leaves 'draft' for this pipeline, so this table is the real
// "already submitted, stock already deducted directly" signal the Shop
// demand query excludes on, 2026-11-13).
$conn->query("CREATE TABLE receipt (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receiptid VARCHAR(255) NOT NULL
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

// TP PO: product 101 qty 999, but completed (must be excluded regardless
// of order_date — a fulfilled PO never counts again)
$conn->query("INSERT INTO tp_purchase_orders (id, territory_partner_id, order_date, status) VALUES (2, 9, '$today', 'completed')");
$conn->query("INSERT INTO tp_purchase_order_items (po_id, product_id, qty) VALUES (2, 101, 999)");

// TP PO: product 101 qty 15, still waiting from yesterday — must now
// COUNT (order_date is no longer part of the filter: a PO raised
// yesterday that's still genuinely outstanding is just as much
// "required" as one raised today).
$conn->query("INSERT INTO tp_purchase_orders (id, territory_partner_id, order_date, status) VALUES (3, 9, '$yesterday', 'waiting')");
$conn->query("INSERT INTO tp_purchase_order_items (po_id, product_id, qty) VALUES (3, 101, 15)");

// TP PO: product 101 qty 999, waiting, but approver_type='ss' (must be
// excluded — fulfilled through the Super Stockist's own stock, not this
// company's Neksomo/Healthcare/LLP chain).
$conn->query("INSERT INTO tp_purchase_orders (id, territory_partner_id, order_date, status, approver_type) VALUES (4, 9, '$today', 'waiting', 'ss')");
$conn->query("INSERT INTO tp_purchase_order_items (po_id, product_id, qty) VALUES (4, 101, 999)");

// TP PO: product 101 qty 999, waiting, approver_type='company', but has a
// preferred_cp_id (must be excluded — the TP wants this sourced via a
// Channel Partner, not moved through company godowns).
$conn->query("INSERT INTO tp_purchase_orders (id, territory_partner_id, order_date, status, approver_type, preferred_cp_id) VALUES (5, 9, '$today', 'waiting', 'company', 77)");
$conn->query("INSERT INTO tp_purchase_order_items (po_id, product_id, qty) VALUES (5, 101, 999)");

// TP PO: product 101 qty 999, but status='cancelled' (must be excluded).
$conn->query("INSERT INTO tp_purchase_orders (id, territory_partner_id, order_date, status) VALUES (6, 9, '$today', 'cancelled')");
$conn->query("INSERT INTO tp_purchase_order_items (po_id, product_id, qty) VALUES (6, 101, 999)");

// OT draft for LLP (godownid=3), product 101 qty 15, today
$conn->query("INSERT INTO ot_sales_invoice (tempid, status) VALUES ('OTD1', 'draft')");
$conn->query("INSERT INTO ot_sales (godownid, prid, qty, date, tempid) VALUES (3, 101, 15, '$today', 'OTD1')");

// OT draft for LLP, product 101 qty 5, dated yesterday — must now COUNT
// (same date-independence as the TP side above).
$conn->query("INSERT INTO ot_sales_invoice (tempid, status) VALUES ('OTD4', 'draft')");
$conn->query("INSERT INTO ot_sales (godownid, prid, qty, date, tempid) VALUES (3, 101, 5, '$yesterday', 'OTD4')");

// OT confirmed (not draft) for LLP, product 101 qty 999 (must be excluded)
$conn->query("INSERT INTO ot_sales_invoice (tempid, status) VALUES ('OTC1', 'confirmed')");
$conn->query("INSERT INTO ot_sales (godownid, prid, qty, date, tempid) VALUES (3, 101, 999, '$today', 'OTC1')");

// OT draft for Healthcare (godownid=2), product 101 qty 999 (wrong godown, must be excluded)
$conn->query("INSERT INTO ot_sales_invoice (tempid, status) VALUES ('OTD2', 'draft')");
$conn->query("INSERT INTO ot_sales (godownid, prid, qty, date, tempid) VALUES (2, 101, 999, '$today', 'OTD2')");

// Product 202: only an OT draft, qty 7, today, LLP
$conn->query("INSERT INTO ot_sales_invoice (tempid, status) VALUES ('OTD3', 'draft')");
$conn->query("INSERT INTO ot_sales (godownid, prid, qty, date, tempid) VALUES (3, 202, 7, '$today', 'OTD3')");

// CP PO: product 101 qty 20, waiting
$conn->query("INSERT INTO channel_partner_purchase_orders (id, channel_partner_id, order_date, status) VALUES (1, 55, '$today', 'waiting')");
$conn->query("INSERT INTO channel_partner_purchase_order_items (po_id, product_id, qty) VALUES (1, 101, 20)");

// CP PO: product 101 qty 999, completed (must be excluded)
$conn->query("INSERT INTO channel_partner_purchase_orders (id, channel_partner_id, order_date, status) VALUES (2, 55, '$today', 'completed')");
$conn->query("INSERT INTO channel_partner_purchase_order_items (po_id, product_id, qty) VALUES (2, 101, 999)");

// Shop Invoice (draft, company-sourced from LLP godown id 3, has
// source_ms_order_id): product 101 qty 10 — must COUNT.
$conn->query("INSERT INTO user_invoice (inv_id, source_ms_order_id, status, from_user_type, from_user_id) VALUES ('SHOP1', 'ORDER1', 'draft', 'company', '3')");
$conn->query("INSERT INTO user_invoice_items (inv_id, pr_id, qty) VALUES ('SHOP1', 101, 10)");

// Shop Invoice: already submitted (not draft) — must be excluded.
$conn->query("INSERT INTO user_invoice (inv_id, source_ms_order_id, status, from_user_type, from_user_id) VALUES ('SHOP2', 'ORDER2', 'submitted', 'company', '3')");
$conn->query("INSERT INTO user_invoice_items (inv_id, pr_id, qty) VALUES ('SHOP2', 101, 999)");

// Shop Invoice: draft, sourced from Healthcare (godown id 2), not LLP — must
// still COUNT. company/ms-order-invoice-add.php's "no CP" path lets the
// company pick ANY allowed godown via its own dropdown, not just LLP, and
// the 2026-11-17 design note requires EVERY DM Get Order Shop Invoice to
// show up as Auto Transfer demand regardless of godown. Previously excluded
// here (a real bug fixed 2026-11-21 — found via code review, confirmed this
// test itself was asserting the wrong behavior).
$conn->query("INSERT INTO user_invoice (inv_id, source_ms_order_id, status, from_user_type, from_user_id) VALUES ('SHOP3', 'ORDER3', 'draft', 'company', '2')");
$conn->query("INSERT INTO user_invoice_items (inv_id, pr_id, qty) VALUES ('SHOP3', 101, 999)");

// Shop Invoice: draft, LLP, but NO source_ms_order_id (not a DM-order-
// derived invoice) — must be excluded.
$conn->query("INSERT INTO user_invoice (inv_id, source_ms_order_id, status, from_user_type, from_user_id) VALUES ('SHOP4', NULL, 'draft', 'company', '3')");
$conn->query("INSERT INTO user_invoice_items (inv_id, pr_id, qty) VALUES ('SHOP4', 101, 999)");

// Shop Invoice: draft, LLP, has source_ms_order_id, but from_user_type is
// NOT 'company' (e.g. a TP's own shop invoice) — must be excluded, this
// demand source is only ever company-direct invoices.
$conn->query("INSERT INTO user_invoice (inv_id, source_ms_order_id, status, from_user_type, from_user_id) VALUES ('SHOP5', 'ORDER5', 'draft', 'territory_partner', '3')");
$conn->query("INSERT INTO user_invoice_items (inv_id, pr_id, qty) VALUES ('SHOP5', 101, 999)");

// Shop Invoice: status column never leaves 'draft' for this pipeline even
// after the real Submit Invoice step finalizes it and deducts stock
// directly (company/shop-user-invoice-submit.php) -- the only real
// "already submitted" signal is a matching receipt row. Must be excluded,
// else this real-flow gap would make Auto Transfer treat an already-
// fulfilled order as outstanding demand forever (caught via live-DB
// testing 2026-11-13, not by this suite originally).
$conn->query("INSERT INTO user_invoice (inv_id, source_ms_order_id, status, from_user_type, from_user_id) VALUES ('SHOP6', 'ORDER6', 'draft', 'company', '3')");
$conn->query("INSERT INTO user_invoice_items (inv_id, pr_id, qty) VALUES ('SHOP6', 101, 999)");
$conn->query("INSERT INTO receipt (receiptid) VALUES ('SHOP6')");

// ========== TESTS: resolve_godown_id_by_gname ==========
assertEqual(resolve_godown_id_by_gname($conn, 'FEMI HEALTH CARE'), 2, 'resolves Healthcare godown id');
assertEqual(resolve_godown_id_by_gname($conn, 'FEMI NAYAN LLP'), 3, 'resolves LLP godown id');
assertEqual(resolve_godown_id_by_gname($conn, 'DOES NOT EXIST'), null, 'returns null for unknown gname');

// ========== TESTS: get_auto_transfer_requirements ==========
$requirements = get_auto_transfer_requirements($conn, 3);
assertEqual($requirements[101]['tp'] ?? null, 55, 'product 101 tp: 40 (waiting today) + 15 (waiting yesterday), excludes completed/ss-approved/cp-preferred/cancelled');
assertEqual($requirements[101]['ot'] ?? null, 20, 'product 101 ot: 15 (LLP draft today) + 5 (LLP draft yesterday), excludes confirmed/wrong-godown');
assertEqual($requirements[101]['cp'] ?? null, 20, 'product 101 cp: 20 waiting, excludes completed');
assertEqual($requirements[101]['shop'] ?? null, 1009, 'product 101 shop: 10 (LLP) + 999 (Healthcare, counts too — not godown-restricted) draft+company+source_ms_order_id, excludes submitted/no-source-order/non-company/already-receipted');
assertEqual($requirements[202]['ot'] ?? null, 7, 'product 202: only the LLP draft OT counts');
assertEqual(count($requirements), 2, 'no extraneous product keys');

// ========== TESTS: cap_auto_transfer_qty ==========
assertEqual(cap_auto_transfer_qty(55, 30, 10), 40, 'caps to neksomo+healthcare available when short');
assertEqual(cap_auto_transfer_qty(55, 100, 100), 55, 'uses required qty when stock is sufficient');
assertEqual(cap_auto_transfer_qty(55, 0, 0), 0, 'floors at 0 when no stock anywhere');

// ========== TESTS: cap_auto_transfer_qty_by_source (priority TP > CP > Shop > OT) ==========
$split = cap_auto_transfer_qty_by_source(55, 20, 20, 90, 10); // tp=55, cp=20, ot=20, available=90, shop=10 -> all fit (55+20+10+20=105 > 90? no wait check below)
// available=90: tp takes 55 (35 left), cp takes 20 (15 left), shop takes 10 (5 left), ot takes min(20,5)=5
assertEqual($split, ['tp' => 55, 'cp' => 20, 'shop' => 10, 'ot' => 5], 'TP first, then CP, then Shop, then OT gets the remainder');

$split2 = cap_auto_transfer_qty_by_source(55, 20, 20, 60, 10); // available=60: tp=55 (5 left), cp=min(20,5)=5 (0 left), shop=0, ot=0
assertEqual($split2, ['tp' => 55, 'cp' => 5, 'shop' => 0, 'ot' => 0], 'CP, Shop and OT starved when TP alone nearly exhausts available stock');

$split3 = cap_auto_transfer_qty_by_source(0, 0, 20, 10, 10); // available=10: tp=0, cp=0, shop=min(10,10)=10 (0 left), ot=0
assertEqual($split3, ['tp' => 0, 'cp' => 0, 'shop' => 10, 'ot' => 0], 'Shop beats OT when both compete for the same limited stock');

$split4 = cap_auto_transfer_qty_by_source(0, 0, 0, 100); // shop omitted -> defaults to 0, backward-compatible with pre-shop callers
assertEqual($split4, ['tp' => 0, 'cp' => 0, 'shop' => 0, 'ot' => 0], 'shop param is optional and defaults to 0');

// ========== SUMMARY ==========
echo "\n$passCount passed, $failCount failed\n";
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
exit($failCount > 0 ? 1 : 0);
