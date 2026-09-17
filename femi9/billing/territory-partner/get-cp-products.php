<?php
// Read-only stock lookup for the "Submit To: [Channel Partner]" option on
// add-purchase-order.php — same query as company/get-tp-location-products.php,
// duplicated here under territory-partner/checksession.php since that file's
// own checksession only accepts a company login. $cp_id itself isn't
// re-validated against "does this TP's territory actually map to this CP" —
// the only cp_id this page ever passes in is the one PHP itself resolved for
// this TP in add-purchase-order.php, never a free-typed value.
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';
header('Content-Type: application/json');
error_reporting(0);

$cp_id = (int)($_GET['cp_id'] ?? 0);
if (!$cp_id) { echo json_encode([]); exit; }

$productType = tpResolveProductType($_GET['product_type'] ?? null);
$stmt = $db_conn->prepare("
    SELECT p.id AS product_id, p.productName, cps.closing_qty AS available_qty,
           COALESCE(NULLIF(p.stockist_price, 0), p.mrp, 0) AS rate, p.packs_per_carton
    FROM channel_partner_stock cps
    JOIN products p ON p.id = cps.product_id
    WHERE cps.channel_partner_id = ? AND cps.closing_qty > 0 AND p.deleted_at IS NULL
      AND (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL)
      AND " . tpProductTypeSqlFilter($productType) . "
    ORDER BY p.productName
");
$stmt->bind_param("i", $cp_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($products);
