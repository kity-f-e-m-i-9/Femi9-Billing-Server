<?php
// System stock vs physically-counted actual stock, per product, for one TP +
// category — shown right after Save on the "Actual Stock" modal so the DM/
// ASM immediately sees how far off the software's stock is from what they
// actually counted. tp_id is re-checked against the caller's own scope, same
// pattern as the other tp-actual-stock-* endpoints.
require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/MsDistrictScope.php';
header('Content-Type: application/json');
error_reporting(0);

$msId     = (int)$Login_user_IDvl;
$tpId     = (int)($_GET['tp_id'] ?? 0);
$category = $_GET['category'] ?? '';
if ($tpId <= 0 || !in_array($category, ['napkin', 'diaper'], true)) { echo json_encode([]); exit; }

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
    SELECT p.id AS product_id, p.productName,
           COALESCE(tps.closing_qty, 0) AS system_qty,
           e.qty AS actual_qty
    FROM products p
    LEFT JOIN territory_partner_stock tps ON tps.product_id = p.id AND tps.territory_partner_id = ?
    LEFT JOIN tp_actual_stock_entries e   ON e.product_id = p.id AND e.territory_partner_id = ?
    WHERE p.category = ? AND p.deleted_at IS NULL AND e.qty IS NOT NULL
    ORDER BY p.productName ASC
");
$stmt->bind_param('iis', $tpId, $tpId, $category);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($rows as &$r) {
    $r['product_id']  = (int)$r['product_id'];
    $r['system_qty']  = (int)$r['system_qty'];
    $r['actual_qty']  = (int)$r['actual_qty'];
    $r['diff']        = $r['actual_qty'] - $r['system_qty'];
}

echo json_encode($rows);
