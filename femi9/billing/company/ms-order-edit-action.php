<?php
// Save endpoint for ms_prorders.php's Edit modal — Company edits qty AND
// price (CP's own edit, channel-partner/dm-order-edit-action.php, is
// qty-only). Refuses an already-invoiced order. 2026-11-13, user request.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms_edit');
require_once(__DIR__ . '/../shared/MsOrderEdits.php');
header('Content-Type: application/json');
error_reporting(0);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$order_id = $_POST['order_id'] ?? '';
$reason = trim($_POST['reason'] ?? '');
$qtyRaw = $_POST['qty'] ?? [];
$priceRaw = $_POST['price'] ?? [];

if ($order_id === '' || !is_array($qtyRaw) || $reason === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if (msOrderIsInvoiced($db_conn, $order_id)) {
    echo json_encode(['success' => false, 'error' => 'This order has already been invoiced and can no longer be edited.']);
    exit;
}

$qtyChanges = [];
foreach ($qtyRaw as $prId => $qty) {
    $qtyChanges[(int)$prId] = (int)$qty;
}
$priceChanges = [];
if (is_array($priceRaw)) {
    foreach ($priceRaw as $prId => $price) {
        $priceChanges[(int)$prId] = ($price === '' ? null : (float)$price);
    }
}

$companyName = $_SESSION['LOGIN_USER_NAME'] ?? 'Company';
$result = msOrderApplyEdit($db_conn, $order_id, 'company', $companyName, $qtyChanges, $priceChanges, $reason);

echo json_encode($result);
