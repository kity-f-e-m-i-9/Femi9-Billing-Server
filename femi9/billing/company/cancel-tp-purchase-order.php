<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
error_reporting(0);

header('Content-Type: application/json');

function respond(array $payload, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode($payload);
    exit;
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    respond(['success' => false, 'message' => 'Session expired — please reload the page and try again.'], 400);
}

$poId   = (int)($_POST['po_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$cancelledBy = $_SESSION['LOGIN_USER'] ?? '';

if ($poId <= 0) {
    respond(['success' => false, 'message' => 'Invalid order.'], 400);
}

$stmt = $db_conn->prepare("SELECT id, status FROM tp_purchase_orders WHERE id = ?");
$stmt->bind_param('i', $poId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    respond(['success' => false, 'message' => 'Order not found.'], 404);
}
if ($order['status'] !== 'waiting') {
    respond(['success' => false, 'message' => 'Only orders that are still waiting can be cancelled.'], 400);
}

$upd = $db_conn->prepare(
    "UPDATE tp_purchase_orders
     SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, cancel_reason = ?
     WHERE id = ? AND status = 'waiting'"
);
$upd->bind_param('ssi', $cancelledBy, $reason, $poId);
$upd->execute();
$ok = $upd->affected_rows === 1;
$upd->close();

if (!$ok) {
    respond(['success' => false, 'message' => 'Could not cancel this order — it may have already been actioned.'], 409);
}

respond(['success' => true, 'message' => 'Order cancelled.']);
