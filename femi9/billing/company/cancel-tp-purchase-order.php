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

// If any linked payment screenshot has already been converted into a real
// tp_advance_payments ledger entry, refuse to cancel here — same guard as
// the TP-side delete-purchase-order.php. That money is already credited to
// the TP and needs a deliberate refund/adjustment decision, not a silent
// status flip that leaves the credit dangling with no order behind it.
$chk = $db_conn->prepare(
    "SELECT COUNT(*) AS cnt FROM tp_purchase_order_screenshots WHERE po_id = ? AND advance_payment_id IS NOT NULL"
);
$chk->bind_param('i', $poId);
$chk->execute();
$alreadyCredited = (int)($chk->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;
$chk->close();

if ($alreadyCredited) {
    respond(['success' => false, 'message' => 'This order cannot be cancelled — a payment from it has already been credited to the TP\'s advance balance. Adjust the advance payment separately first.'], 409);
}

$db_conn->begin_transaction();
try {
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
        throw new \Exception('Could not cancel this order — it may have already been actioned.');
    }

    // Any leftover screenshot on this PO that was never converted to a credit
    // (still pending_review or accepted-but-unconverted) must not remain
    // actionable afterward — tp-screenshot-to-advance-payment.php only checks
    // the screenshot's own status, not its parent PO's, so without this a
    // reviewer could still credit money for an order that no longer exists.
    $rejectReason = 'Parent purchase order was cancelled';
    $rej = $db_conn->prepare(
        "UPDATE tp_purchase_order_screenshots
         SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW()
         WHERE po_id = ? AND advance_payment_id IS NULL AND status IN ('accepted', 'pending_review')"
    );
    $rej->bind_param('ssi', $rejectReason, $cancelledBy, $poId);
    $rej->execute();
    $rej->close();

    $db_conn->commit();
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("cancel-tp-purchase-order failed for PO {$poId}: " . $e->getMessage());
    respond(['success' => false, 'message' => 'Could not cancel this order — it may have already been actioned.'], 409);
}

respond(['success' => true, 'message' => 'Order cancelled.']);
