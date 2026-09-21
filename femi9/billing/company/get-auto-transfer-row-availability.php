<?php
// femi9/billing/company/get-auto-transfer-row-availability.php
//
// Recomputes one product row's Neksomo/Healthcare available stock scoped
// to a specific source warehouse, for the Auto Transfer page's warehouse
// pickers (see docs/superpowers/specs/2026-09-21-warehouse-aware-auto-
// transfer-design.md). Mirrors get-piece-pack-stock.php's shape, but for
// two entities (Neksomo + Healthcare) instead of one.

include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/NeksomoStockBridge.php");
require_once("include/AutoTransferDemand.php");
header('Content-Type: application/json');
error_reporting(0);

// Internal Stock Transfer is a finance-only area.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$productId = (int) ($_GET['product_id'] ?? 0);
$sourceWarehouseId       = filter_var($_GET['source_warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$intermediateWarehouseId = filter_var($_GET['intermediate_warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;

if (!$productId) {
    echo json_encode(['error' => 'invalid_product']);
    exit;
}

$neksomoId    = get_neksomo_godown_id($db_conn);
$healthcareId = resolve_godown_id_by_gname($db_conn, 'FEMI HEALTH CARE');
if (!$neksomoId || !$healthcareId) {
    echo json_encode(['error' => 'misconfigured']);
    exit;
}

$stockService = new StockService($db_conn);
$neksomoAvail    = (int) ($stockService->getClosingQty($productId, $Login_user_TYPEvl, (string) $neksomoId, $sourceWarehouseId) ?? 0);
$healthcareAvail = (int) ($stockService->getClosingQty($productId, $Login_user_TYPEvl, (string) $healthcareId, $intermediateWarehouseId) ?? 0);

echo json_encode([
    'neksomo_avail'    => $neksomoAvail,
    'healthcare_avail' => $healthcareAvail,
]);
