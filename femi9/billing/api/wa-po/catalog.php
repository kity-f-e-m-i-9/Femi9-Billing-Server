<?php
/**
 * GET /catalog/products?category=distributor&q=optional-search&session_token=...
 * Spec §1.3 — product catalog, priced per the caller's category tier.
 * Excludes soft-deleted and Neksomo (NKS-prefixed temp_id) products, same
 * filter territory-partner/add-purchase-order.php uses.
 *
 * products table has per-tier price columns (verified via SHOW COLUMNS):
 * mrp, supersstock_price, stockist_price, distributor_price,
 * super_distributor_price, outlet_price — but no dedicated column for
 * channel_partner/candf/marketing/territory_partner. territory_partner's
 * existing PO screen already uses stockist_price, so we follow that
 * precedent for territory_partner too; channel_partner/candf/marketing
 * (no precedent in this codebase) fall back to outlet_price as the most
 * conservative (retail-facing) tier available.
 */
require_once __DIR__ . '/_bootstrap.php';

$session = wa_po_require_session($db_conn, $input);
$category = $_GET['category'] ?? $session['user_category'];
$q = trim((string)($_GET['q'] ?? ''));

$priceColumnMap = [
    'distributor' => 'distributor_price',
    'super_distributor' => 'super_distributor_price',
    'stockiest' => 'stockist_price',
    'super_stockiest' => 'supersstock_price',
    'channel_partner' => 'outlet_price',
    'candf' => 'outlet_price',
    'marketing' => 'outlet_price',
    'territory_partner' => 'stockist_price',
];

$priceColumn = $priceColumnMap[$category] ?? 'outlet_price';

$sql = "SELECT id, temp_id, productName, `$priceColumn` AS price
        FROM products
        WHERE deleted_at IS NULL AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL)";
$params = [];
$types = '';

if ($q !== '') {
    $sql .= " AND productName LIKE ?";
    $params[] = '%' . $q . '%';
    $types .= 's';
}
$sql .= " ORDER BY productName ASC";

$stmt = mysqli_prepare($db_conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$products = [];
while ($row = $res->fetch_assoc()) {
    $products[] = [
        'sku' => (string)$row['temp_id'],
        'product_id' => (int)$row['id'],
        'name' => (string)$row['productName'],
        'price' => (float)$row['price'],
        'moq' => 1,
        'in_stock' => true,
    ];
}
mysqli_stmt_close($stmt);

echo json_encode(['products' => $products]);
