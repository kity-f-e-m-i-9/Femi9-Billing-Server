<?php
// AJAX backend for internal_transfer_auto.php's "View Breakdown" modal —
// per-order detail (TP purchase orders / OT drafts) for one product's
// contribution to today's auto-transfer requirement.
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/AutoTransferDemand.php");
include("config.php");
header('Content-Type: application/json');

// Internal Stock Transfer is a finance-only area.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
error_reporting(0);

$productId = (int) ($_GET['product_id'] ?? 0);
if (!$productId) { echo json_encode(['tp' => [], 'ot' => []]); exit; }

$llpId = resolve_godown_id_by_gname($db_conn, 'FEMI NAYAN LLP');
if (!$llpId) { echo json_encode(['tp' => [], 'ot' => []]); exit; }

echo json_encode(get_auto_transfer_breakdown_for_product($db_conn, $productId, $llpId));
