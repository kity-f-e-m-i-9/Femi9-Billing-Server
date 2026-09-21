<?php
// femi9/billing/company/get-godown-warehouses.php
//
// Returns the warehouse options for one company profile, filtered by
// company_godown_warehouses — falls back to the full active-warehouse
// list if nothing is mapped yet. Used by internal_transfer.php's
// Send From/Send To pickers to re-filter the two existing warehouse
// dropdowns on change (see docs/superpowers/specs/2026-09-21-warehouse-
// aware-auto-transfer-design.md).

include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/GodownWarehouseMapping.php");
header('Content-Type: application/json');
error_reporting(0);

// Internal Stock Transfer is a finance-only area.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$companyGodownId = (int) ($_GET['company_godown_id'] ?? 0);
if (!$companyGodownId) {
    echo json_encode(['error' => 'invalid_godown']);
    exit;
}

$warehouses = get_warehouses_for_godown($db_conn, $companyGodownId);
if (empty($warehouses)) {
    $res = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
    $warehouses = $res->fetch_all(MYSQLI_ASSOC);
    $warehouses = array_map(fn($w) => ['id' => (int) $w['id'], 'code' => $w['code'], 'name' => $w['name']], $warehouses);
}

echo json_encode(['warehouses' => $warehouses]);
