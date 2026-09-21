<?php
// AJAX backend for internal_transfer_auto.php's page-level "View All
// Orders" modal — every order (TP PO / OT draft) contributing to today's
// auto-transfer, each with ALL of its own product lines (not scoped to
// one product, unlike get-auto-transfer-breakdown.php).
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/AutoTransferDemand.php");
include("config.php");

// Internal Stock Transfer is a finance-only area.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
header('Content-Type: application/json');
error_reporting(0);

$llpId = resolve_godown_id_by_gname($db_conn, 'FEMI NAYAN LLP');
if (!$llpId) { echo json_encode(['tp' => [], 'ot' => []]); exit; }

echo json_encode(get_auto_transfer_orders_overview($db_conn, $llpId));
