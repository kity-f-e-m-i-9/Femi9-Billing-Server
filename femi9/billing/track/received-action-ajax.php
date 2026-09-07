<?php
include("checksession.php");
include("config.php");
error_reporting(0);

header('Content-Type: application/json');

// Same tp_courier_payments table and Approve/Reject semantics as
// company/courier-payment-review-action-ajax.php — a Track reviewer's
// Accept/Reject IS the company's own courier-payment review, not a
// separate parallel approval. A reject here also makes the "Pay Courier
// Amount Again" button reappear on the TP's own manage-purchase-orders.php
// (that page's retry logic keys off this same status column).
$id       = (int)($_POST['courier_payment_id'] ?? 0);
$action   = $_POST['action'] ?? '';
$reviewedBy = 'track:' . ($_SESSION['LOGIN_USER'] ?? '');

if ($id < 1 || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$stmt = $db_conn->prepare("SELECT id, status FROM tp_courier_payments WHERE id = ? AND status = 'pending_review'");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'This screenshot has already been reviewed.']);
    exit;
}

if ($action === 'reject') {
    $reason = trim($_POST['reason'] ?? '');
    $reason = $reason !== '' ? $reason : 'Rejected by reviewer.';
    $upd = $db_conn->prepare("UPDATE tp_courier_payments SET status='rejected', rejection_reason=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
    $upd->bind_param('ssi', $reason, $reviewedBy, $id);
    $upd->execute();
    $upd->close();
    echo json_encode(['success' => true, 'message' => 'Screenshot rejected.']);
    exit;
}

// Approve — same "needs a confirmed amount if none was auto-detected"
// safety as the company-side endpoint, so an approved row with no amount
// can never silently count as ₹0 toward the pool.
$confirmedAmountRaw = $_POST['confirmed_amount'] ?? null;
if ($confirmedAmountRaw !== null && $confirmedAmountRaw !== '') {
    $confirmedAmount = round((float)$confirmedAmountRaw, 2);
    $upd = $db_conn->prepare("UPDATE tp_courier_payments SET status='accepted', detected_amount=?, rejection_reason=NULL, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
    $upd->bind_param('dsi', $confirmedAmount, $reviewedBy, $id);
} else {
    $upd = $db_conn->prepare("UPDATE tp_courier_payments SET status='accepted', rejection_reason=NULL, reviewed_by=?, reviewed_at=NOW() WHERE id=? AND detected_amount IS NOT NULL");
    $upd->bind_param('si', $reviewedBy, $id);
}
$upd->execute();
$ok = $upd->affected_rows === 1;
$upd->close();

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'This screenshot has no readable amount — enter the confirmed amount to approve it.']);
    exit;
}
echo json_encode(['success' => true, 'message' => 'Screenshot approved.']);
