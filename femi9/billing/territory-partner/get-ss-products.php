<?php
// Read-only stock lookup for the "Submit To: [Super Stockist]" option on
// add-purchase-order.php — same purpose as get-cp-products.php, just
// sourced from the SS's own stock (table `stock`, user_type=
// 'super_stockiest') instead of channel_partner_stock. The SS is never
// taken from the client: it's re-derived here from this TP's own
// onboard_ss_id via tpGetAssignedSs(), the same server-side-only
// resolution purchase-order-action.php itself trusts.
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpApproverContext.php';
require_once __DIR__ . '/../shared/TpProductType.php';
header('Content-Type: application/json');
error_reporting(0);

$tp_id = (int)$Login_user_IDvl;
$assignedSs = tpGetAssignedSs($db_conn, $tp_id);
if ($assignedSs === null) { echo json_encode([]); exit; }

$productType = tpResolveProductType($_GET['product_type'] ?? null);
$ss_temp_id  = $assignedSs['temp_id'];

// p.deleted_at and p.visible_to_tp_cp are deliberately NOT filtered here —
// same convention as get-cp-products.php and super-stockist/get-ss-tp-
// products.php: this picker's whole job is to let the TP account for
// whatever stock the SS actually holds, not the currently-orderable-fresh
// catalog. s.closing_qty > 0 already scopes this to real, present stock.
$stmt = $db_conn->prepare("
    SELECT p.id AS product_id, p.productName, s.closing_qty AS available_qty,
           COALESCE(p.stockist_price, p.distributor_price, 0) AS rate, p.packs_per_carton
    FROM stock s
    JOIN products p ON p.id = s.product_id
    WHERE s.user_type = 'super_stockiest' AND s.user_id = ? AND s.closing_qty > 0
      AND (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL)
      AND " . tpProductTypeSqlFilter($productType) . "
    ORDER BY p.productName
");
$stmt->bind_param("s", $ss_temp_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($products);
