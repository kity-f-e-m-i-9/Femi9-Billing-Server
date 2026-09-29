<?php
// AJAX backend for the Internal Transfer Qty / Movement to CP click-to-view
// popups on overall-stock.php (Current Stock tab) and overstock_datewise.php
// (Datewise tab) — returns the company-profile + warehouse breakdown of one
// product's figure, scoped to exactly the same (godown ids, warehouse, date
// range) as the cell that was clicked. type=internal (default) or type=cp.
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/PermissionCheck.php"); requirePermission('products');
require_once("include/DatewiseStockReport.php");
include("config.php");
header('Content-Type: application/json');
error_reporting(0);

$productId = (int) ($_GET['product_id'] ?? 0);
$fromDate  = $_GET['from_date'] ?? '';
$toDate    = $_GET['to_date'] ?? '';
if (!$productId || !$fromDate || !$toDate) {
    echo json_encode(['rows' => []]);
    exit;
}

// Company Profile scope — same convention as overall-stock.php /
// overstock_datewise.php: godownid[] checkboxes, each id checked against
// is_godown_allowed() so this endpoint can't be used to read another
// company's transfers.
$godownIds = [];
if (!empty($_GET['godownid'])) {
    $raw = is_array($_GET['godownid']) ? $_GET['godownid'] : [$_GET['godownid']];
    foreach ($raw as $gid) {
        $gid = (int)$gid;
        if ($gid < 1) continue;
        if (!is_godown_allowed($db_conn, $gid)) { http_response_code(403); echo json_encode(['error' => 'unauthorized']); exit; }
        $godownIds[] = $gid;
    }
}
$filterByGodown = !empty($godownIds);
$godownIdsSql = implode(',', $godownIds ?: [0]);

// Warehouse scope — a single warehouse id, 'unassigned', or empty (no
// filter), matching one card/cell's own scope (never a multi-select here,
// since a click always comes from one specific cell).
$warehouseNames = [];
$whRes = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
while ($whRes && ($whRow = $whRes->fetch_assoc())) {
    $warehouseNames[(int)$whRow['id']] = $whRow['code'] . ($whRow['name'] ? ' - ' . $whRow['name'] : '');
}
$warehouseParam = $_GET['warehouse'] ?? '';
if ($warehouseParam === 'unassigned') {
    $warehouseCond = warehouseLedgerCondition(true, [], true);
} elseif ($warehouseParam !== '' && isset($warehouseNames[(int)$warehouseParam])) {
    $warehouseCond = warehouseLedgerCondition(true, [(int)$warehouseParam], false);
} else {
    $warehouseCond = '';
}

$type = ($_GET['type'] ?? 'internal') === 'cp' ? 'cp' : 'internal';
$rows = $type === 'cp'
    ? datewiseCpMovementBreakdownRows($db_conn, $productId, $fromDate, $toDate, $godownIdsSql, $filterByGodown, $warehouseCond, $warehouseNames)
    : datewiseTransferBreakdownRows($db_conn, $productId, $fromDate, $toDate, $godownIdsSql, $filterByGodown, $warehouseCond, $warehouseNames);
echo json_encode(['rows' => $rows, 'type' => $type]);
