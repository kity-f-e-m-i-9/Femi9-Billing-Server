<?php
// Save endpoint for dm-order-view.php's qty edit. Scope-checked to
// assigned_cp_id = this CP's own id; refuses an already-invoiced order.
// CP edits are qty-only (never price -- that's Company's own capability,
// see company/ms-order-edit-action.php).
include("checksession.php");
include("config.php");
require_once(__DIR__ . '/../shared/MsOrderEdits.php');
header('Content-Type: application/json');
error_reporting(0);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$cpId = (int)$Login_user_IDvl;
$order_id = $_POST['order_id'] ?? '';
$reason = trim($_POST['reason'] ?? '');
$qtyRaw = $_POST['qty'] ?? [];

if ($order_id === '' || !is_array($qtyRaw) || $reason === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Scope check -- this order must actually belong to this CP.
$stmtChk = $db_conn->prepare("SELECT 1 FROM ms_orders WHERE order_id = ? AND assigned_cp_id = ? LIMIT 1");
$stmtChk->bind_param('si', $order_id, $cpId);
$stmtChk->execute();
$owns = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();
if (!$owns) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'This order is not assigned to you.']);
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

$cpName = $_SESSION['LOGIN_USER_NAME'] ?? '';
$result = msOrderApplyEdit($db_conn, $order_id, 'cp', $cpName, $qtyChanges, null, $reason);

echo json_encode($result);
