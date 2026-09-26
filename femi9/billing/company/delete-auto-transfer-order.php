<?php
// AJAX backend for internal_transfer_auto.php's "View All Orders" modal's
// "Delete Selected" button (TP Purchase Orders / OT Channel Orders tabs).
// Marks one (order, product) line skipped for today with reason='excluded'
// — same auto_transfer_skip_today table/mechanism the retired "Not Today"
// feature used, and the same table internal_transfer_auto_action.php's own
// reason='transferred' rows live in. Never touches stock or
// internal_transfer/stock_ledger; the order/PO's own status is untouched
// too — this only removes it from today's Required Qty calculation. Shows
// up afterward in "Already Transferred Today" tagged "Deleted" (as opposed
// to "Already transferred"), and can be undone with the same "Re-add to TP
// Purchase Orders" button unskip-auto-transfer-order.php powers for both
// reasons.
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/AutoTransferDemand.php");
include("config.php");
header('Content-Type: application/json');

// Internal Stock Transfer is a finance-only area.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    http_response_code(403);
    echo json_encode(['success' => false, 'reason' => 'unauthorized']);
    exit;
}
error_reporting(0);

// source_id is "tp:<orderKey>:<productId>" / "ot:<orderKey>:<productId>",
// the same shape get_auto_transfer_orders_overview() hands the client.
$sourceId = (string) ($_POST['source_id'] ?? '');
$parts = explode(':', $sourceId, 2);
if (count($parts) !== 2 || !in_array($parts[0], ['tp', 'ot'], true)) {
    echo json_encode(['success' => false, 'reason' => 'invalid_source_id']);
    exit;
}
[$sourceType, $sourceRef] = $parts;

$createdBy = $_SESSION['LOGIN_USER'] ?? 'system';
mark_auto_transfer_order_skipped($db_conn, $sourceType, $sourceRef, 'excluded', $createdBy);

echo json_encode(['success' => true]);
