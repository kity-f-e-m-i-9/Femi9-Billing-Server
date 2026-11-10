<?php
// Product-wise current stock breakdown for one TP — backs the "Stock Qty"
// click-to-view modal on my-assigned-tps-stock.php. tp_id is re-checked
// against this Sales BDM's own assigned districts, same pattern
// get-tp-product-breakdown.php already uses.
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
header('Content-Type: application/json');
error_reporting(0);

$tpId = (int)($_GET['tp_id'] ?? 0);
if ($tpId <= 0) { echo json_encode([]); exit; }

$myTpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID, true);
if (!in_array($tpId, $myTpIds, true)) {
    echo json_encode([]);
    exit;
}

$stmt = $db_conn->prepare("
    SELECT p.productName, tps.closing_qty
    FROM territory_partner_stock tps
    JOIN products p ON p.id = tps.product_id
    WHERE tps.territory_partner_id = ? AND tps.closing_qty > 0
    ORDER BY p.productName ASC
");
$stmt->bind_param('i', $tpId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($rows);
