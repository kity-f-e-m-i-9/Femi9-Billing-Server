<?php
// femi9/billing/includes/tests/StockViewerDataTest.php
// Manual run: php StockViewerDataTest.php
//
// Regression/behavior test for the Stock Viewer login's read-only data layer
// (docs/superpowers/specs/2026-09-18-stock-viewer-login-design.md):
// confirms get_stock_viewer_rows() filters correctly (single and combined
// filters), excludes NKS-% placeholder products, and that the aggregate
// helpers (summary, by-profile, by-warehouse, top products) compute
// correctly off it.
//
// Uses a disposable `stock_viewer_data_test` schema — never the app's
// production database — with real `stock` / `products` / `company_godown` /
// `warehouses` table shapes.

require_once __DIR__ . '/../../company/include/StockViewerData.php';
require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const TEST_SCHEMA = 'stock_viewer_data_test';

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
        echo "FAIL: $label (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")\n";
        $failCount++;
    }
}

// ========== SCHEMA ==========
$conn->query("CREATE TABLE products (
    id INT PRIMARY KEY, temp_id VARCHAR(255) NULL, productName VARCHAR(255),
    unit_type ENUM('pieces','pack') DEFAULT 'pieces', pieces_per_pack INT NULL
) ENGINE=InnoDB");
$conn->query("CREATE TABLE company_godown (
    id INT PRIMARY KEY, gname VARCHAR(255), finance_only TINYINT(1) DEFAULT 0
) ENGINE=InnoDB");
$conn->query("CREATE TABLE warehouses (
    id INT PRIMARY KEY, code VARCHAR(32), name VARCHAR(255), is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB");
$conn->query("CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT, opening_qty INT DEFAULT 0,
    opening_date DATE NULL, input_qty INT DEFAULT 0, sales_qty INT DEFAULT 0,
    sent_qty INT DEFAULT 0, returnqty INT DEFAULT 0, closing_qty INT DEFAULT 0,
    extra_pieces INT UNSIGNED DEFAULT 0, user_type VARCHAR(255), user_id VARCHAR(255),
    warehouse_id INT NULL, updated_at TIMESTAMP NULL
) ENGINE=InnoDB");

// ========== FIXTURES ==========
$conn->query("INSERT INTO products (id, temp_id, productName, unit_type, pieces_per_pack) VALUES
    (1, NULL, 'Napkin A', 'pieces', 9),
    (2, NULL, 'Napkin B', 'pieces', 6),
    (3, NULL, 'Diaper C', 'pack', NULL),
    (99, 'NKS-260101-ABC123', 'Raw Neksomo Placeholder', 'pieces', NULL)");
$conn->query("INSERT INTO company_godown (id, gname, finance_only) VALUES
    (10, 'FEMI NAYAN LLP', 0),
    (20, 'NEKSOMO HYGIENE INDUSTRIES', 1)");
$conn->query("INSERT INTO warehouses (id, code, name, is_active) VALUES
    (100, 'H1', 'Head Office', 1),
    (200, 'G1', 'Godown 1', 1)");
$conn->query("INSERT INTO stock (product_id, closing_qty, extra_pieces, user_type, user_id, warehouse_id) VALUES
    (1, 50, 3, 'company', '10', 100),
    (1, 20, 0, 'company', '20', NULL),
    (2, 30, 0, 'company', '10', 200),
    (3, 15, 0, 'company', '20', 100),
    (99, 99999, 0, 'company', '20', NULL)"); // NKS-% placeholder — must never appear

// ========== TESTS ==========

// ---- get_stock_viewer_rows(): no filters returns every non-NKS row ----
$allRows = get_stock_viewer_rows($conn);
assertEqual(count($allRows), 4, 'No filters: returns all 4 non-placeholder rows, excludes the NKS-% row');
foreach ($allRows as $r) {
    assertEqual($r['product_id'] != 99, true, 'No row references the NKS-% placeholder product');
}

// ---- single filter: product_id ----
$byProduct = get_stock_viewer_rows($conn, ['product_id' => 1]);
assertEqual(count($byProduct), 2, 'product_id filter: 2 rows for product 1');

// ---- single filter: company_godown_id ----
$byProfile = get_stock_viewer_rows($conn, ['company_godown_id' => 10]);
assertEqual(count($byProfile), 2, 'company_godown_id filter: 2 rows for profile 10');

// ---- single filter: warehouse_id ----
$byWarehouse = get_stock_viewer_rows($conn, ['warehouse_id' => 100]);
assertEqual(count($byWarehouse), 2, 'warehouse_id filter: 2 rows for warehouse 100');

// ---- combined filters: product_id + company_godown_id ----
$combined = get_stock_viewer_rows($conn, ['product_id' => 1, 'company_godown_id' => 10]);
assertEqual(count($combined), 1, 'Combined product_id + company_godown_id filter: exactly 1 matching row');
assertEqual((int)$combined[0]['closing_qty'], 50, 'Combined filter row has the expected closing_qty');

// ---- array-valued filter: multiple product_ids at once ----
$multiProduct = get_stock_viewer_rows($conn, ['product_id' => [1, 2]]);
assertEqual(count($multiProduct), 3, 'Array-valued product_id filter: 3 rows across products 1 and 2');

// ---- get_stock_viewer_summary() ----
$summary = get_stock_viewer_summary($conn);
assertEqual($summary['total_closing_qty'], 50 + 20 + 30 + 15, 'Summary: total_closing_qty sums all non-placeholder rows');
assertEqual($summary['sku_count'], 3, 'Summary: sku_count counts distinct non-placeholder products (1, 2, 3)');
assertEqual($summary['company_profile_count'], 2, 'Summary: company_profile_count counts distinct profiles');
assertEqual($summary['warehouse_count'], 2, 'Summary: warehouse_count counts distinct non-null warehouses');

// ---- get_stock_viewer_totals_by_profile() ----
$totalsByProfile = get_stock_viewer_totals_by_profile($conn);
assertEqual($totalsByProfile['FEMI NAYAN LLP'], 50 + 30, 'Totals by profile: FEMI NAYAN LLP sums its two rows');
assertEqual($totalsByProfile['NEKSOMO HYGIENE INDUSTRIES'], 20 + 15, 'Totals by profile: NEKSOMO HYGIENE INDUSTRIES sums its two rows');

// ---- get_stock_viewer_totals_by_warehouse() ----
$totalsByWarehouse = get_stock_viewer_totals_by_warehouse($conn);
assertEqual($totalsByWarehouse['Unassigned'], 20, 'Totals by warehouse: Unassigned bucket for NULL warehouse_id row');
assertEqual($totalsByWarehouse['H1 - Head Office'], 50 + 15, 'Totals by warehouse: H1 sums its two rows');
assertEqual($totalsByWarehouse['G1 - Godown 1'], 30, 'Totals by warehouse: G1 sums its one row');

// ---- get_stock_viewer_top_products() ----
$top = get_stock_viewer_top_products($conn, 2);
assertEqual(count($top), 2, 'Top products: respects the limit');
assertEqual($top[0]['productName'], 'Napkin A', 'Top products: highest total (70) ranks first');
assertEqual($top[0]['total_qty'], 70, 'Top products: Napkin A total is 50+20=70');

// ---- get_stock_viewer_product_options() excludes NKS-% ----
$options = get_stock_viewer_product_options($conn);
$optionIds = array_column($options, 'id');
assertEqual(in_array(99, $optionIds), false, 'Product options: NKS-% placeholder product never offered as a pickable option');
assertEqual(count($optionIds), 3, 'Product options: exactly the 3 non-placeholder products with stock');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
