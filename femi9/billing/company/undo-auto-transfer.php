<?php
// AJAX backend for internal_transfer_auto.php's Transfer History "Undo"
// button — reverses one product's already-completed auto-transfer (both
// legs: Neksomo -> Healthcare -> LLP) via undo_auto_transfer().
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/AutoTransferDemand.php");

// Internal Stock Transfer is a finance-only area.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
include("config.php");
header('Content-Type: application/json');
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'reason' => 'invalid']); exit;
}

$tempid    = trim($_POST['tempid'] ?? '');
$productId = (int) ($_POST['product_id'] ?? 0);

if ($tempid === '' || $productId <= 0) {
    echo json_encode(['success' => false, 'reason' => 'invalid']); exit;
}

$neksomoId    = get_neksomo_godown_id($db_conn);
$healthcareId = resolve_godown_id_by_gname($db_conn, 'FEMI HEALTH CARE');
$llpId        = resolve_godown_id_by_gname($db_conn, 'FEMI NAYAN LLP');

if (!$neksomoId || !$healthcareId || !$llpId) {
    echo json_encode(['success' => false, 'reason' => 'misconfigured']); exit;
}

if (!is_godown_allowed($db_conn, (int) $neksomoId) || !is_godown_allowed($db_conn, (int) $healthcareId) || !is_godown_allowed($db_conn, (int) $llpId)) {
    echo json_encode(['success' => false, 'reason' => 'unauthorized']); exit;
}

$createdBy = $_SESSION['LOGIN_USER'] ?? 'system';

try {
    $result = undo_auto_transfer($db_conn, $tempid, $productId, $Login_user_TYPEvl, (int) $neksomoId, (int) $healthcareId, (int) $llpId, $createdBy);
    echo json_encode($result);
} catch (\Throwable $e) {
    error_log("undo-auto-transfer error: " . $e->getMessage());
    echo json_encode(['success' => false, 'reason' => 'error']);
}
