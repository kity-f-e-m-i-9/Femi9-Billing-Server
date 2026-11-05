<?php
// Product-wise stock breakdown for one TP — backs the "Stock Qty" click-to-
// view modal on my-assigned-tps.php. Scoped to the logged-in DM/ASM's own
// assigned district(s)/division(s) only — tp_id is never trusted from the
// client without checking it's actually one of this person's own TPs, same
// spirit as territory-partner/get-ss-products.php re-deriving its own scope
// server-side instead of trusting a client-supplied id directly.
require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/MsDistrictScope.php';
header('Content-Type: application/json');
error_reporting(0);

$msId  = (int)$Login_user_IDvl;
$tpId  = (int)($_GET['tp_id'] ?? 0);
if ($tpId <= 0) { echo json_encode([]); exit; }

$scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $msId);
$allowedTpIds = [];
foreach ($scopeNodes as $node) {
    $allowedTpIds = array_merge($allowedTpIds, getMsNodeTpIds($db_conn, [$node['id']], true));
}
if (!in_array($tpId, $allowedTpIds, true)) {
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
