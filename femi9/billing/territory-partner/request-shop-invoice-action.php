<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpShopInvoiceActionRequest.php';
require_once __DIR__ . '/../shared/TpCourierAmountRequest.php';
error_reporting(0);
header('Content-Type: application/json');

$tp_id = (int)$Login_user_IDvl;
$inv_id = trim((string)($_POST['inv_id'] ?? ''));
$actionType = $_POST['action_type'] ?? '';

if ($inv_id === '' || !in_array($actionType, ['return', 'remove'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Never trust the posted inv_id alone — confirm it's actually this TP's own
// shop invoice before raising a request for it.
$stmt = $db_conn->prepare("SELECT 1 FROM user_invoice WHERE inv_id = ? AND from_user_id = ? AND from_user_type = 'territory_partner' AND to_user_type = 'shop'");
$stmt->bind_param('si', $inv_id, $tp_id);
$stmt->execute();
$owns = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$owns) {
    echo json_encode(['success' => false, 'message' => 'Invoice not found.']);
    exit;
}

$ok = tpShopInvoiceActionRequestUpsert($db_conn, $tp_id, $inv_id, $actionType);

$coveredByBdm = !empty(tpFindBdmIdsForTp($db_conn, $tp_id));
$message = $coveredByBdm
    ? 'Request sent to your Sales BDM.'
    : 'Request submitted, but no Sales BDM is currently assigned to your district — it will be reviewed once one is assigned.';

echo json_encode(['success' => $ok, 'message' => $ok ? $message : 'Could not submit the request. Please try again.']);
