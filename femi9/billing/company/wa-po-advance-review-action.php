<?php
/**
 * Company-side approve/reject action for wa_po_advance_payment_submissions
 * — the WhatsApp-automation subsystem's OWN review action, separate from
 * (and not touching) the existing tp-advance-submission-review-action-ajax.php
 * which reviews the unrelated tp_advance_payment_submissions table.
 *
 * On approve: creates a wa_po_advance_payments row (mirrors how the TP
 * system inserts into tp_advance_payments on accept) and fires the
 * outbound Wati notification via triggerWaPoPaymentApprovalNotification().
 * On reject: just records the rejection reason.
 */
include("checksession.php");
include("config.php");
error_reporting(0);

require_once __DIR__ . '/../shared/WatiNotifier.php';

header('Content-Type: application/json');

function respond(array $payload, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode($payload);
    exit;
}

$submissionId = (int)($_POST['submission_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));
$rejectionReason = trim((string)($_POST['rejection_reason'] ?? ''));
$reviewer = $_SESSION['LOGIN_USER'] ?? 'company';

if ($submissionId <= 0) respond(['success' => false, 'message' => 'submission_id is required'], 400);
if (!in_array($action, ['approve', 'reject'], true)) respond(['success' => false, 'message' => 'action must be approve or reject'], 400);
if ($action === 'reject' && $rejectionReason === '') respond(['success' => false, 'message' => 'rejection_reason is required to reject'], 400);

$stmt = $db_conn->prepare("SELECT id, user_category, user_id, amount, status FROM wa_po_advance_payment_submissions WHERE id = ?");
$stmt->bind_param('i', $submissionId);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$submission) respond(['success' => false, 'message' => 'Submission not found'], 404);
if ($submission['status'] !== 'pending_review') {
    respond(['success' => false, 'message' => 'Only pending_review submissions can be actioned (current status: ' . $submission['status'] . ')'], 400);
}

if ($action === 'approve') {
    $db_conn->begin_transaction();
    try {
        $ins = $db_conn->prepare(
            "INSERT INTO wa_po_advance_payments (user_category, user_id, amount, balance_amount, status)
             VALUES (?, ?, ?, ?, 'active')"
        );
        $amount = (float)$submission['amount'];
        $ins->bind_param('sidd', $submission['user_category'], $submission['user_id'], $amount, $amount);
        $ins->execute();
        $ins->close();

        $upd = $db_conn->prepare(
            "UPDATE wa_po_advance_payment_submissions SET status = 'accepted', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?"
        );
        $upd->bind_param('si', $reviewer, $submissionId);
        $upd->execute();
        $upd->close();

        $db_conn->commit();
    } catch (\Throwable $e) {
        $db_conn->rollback();
        respond(['success' => false, 'message' => 'Could not approve submission — please try again.'], 500);
    }

    $notifyResult = triggerWaPoPaymentApprovalNotification($db_conn, $submissionId);

    respond([
        'success' => true,
        'submission_id' => $submissionId,
        'status' => 'accepted',
        'notification' => $notifyResult,
    ]);
} else {
    $upd = $db_conn->prepare(
        "UPDATE wa_po_advance_payment_submissions SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?"
    );
    $upd->bind_param('ssi', $rejectionReason, $reviewer, $submissionId);
    $upd->execute();
    $upd->close();

    respond(['success' => true, 'submission_id' => $submissionId, 'status' => 'rejected']);
}
