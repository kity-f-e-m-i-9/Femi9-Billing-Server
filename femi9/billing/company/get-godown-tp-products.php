<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once __DIR__ . '/../shared/TpProductType.php';
header('Content-Type: application/json');
error_reporting(0);

$godown_id = (int)($_GET['godown_id'] ?? 0);
if (!$godown_id || !is_godown_allowed($db_conn, $godown_id)) { echo json_encode([]); exit; }

$productType = tpResolveProductType($_GET['product_type'] ?? null);
$uid = (string)$godown_id;
// GROUP BY p.id, summing across any per-warehouse rows — a product's
// stock can now be split across multiple physical godowns (H1/G1/G2);
// this dropdown shows the combined total across all of them, same as
// before physical warehouses existed. HAVING (not WHERE) filters the
// summed total. p.stockist_price/p.mrp are functionally dependent on
// p.id, safe to select alongside the GROUP BY.
$stmt = $db_conn->prepare("
    SELECT p.id AS product_id, p.productName, SUM(s.closing_qty) AS available_qty,
           COALESCE(NULLIF(p.stockist_price, 0), p.mrp, 0) AS rate
    FROM stock s
    JOIN products p ON p.id = s.product_id
    WHERE s.user_type = 'company' AND s.user_id = ? AND p.deleted_at IS NULL
      AND (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL)
      AND " . tpProductTypeSqlFilter($productType) . "
    GROUP BY p.id, p.productName, p.stockist_price, p.mrp
    HAVING SUM(s.closing_qty) > 0
    ORDER BY p.productName
");
$stmt->bind_param("s", $uid);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($products);
