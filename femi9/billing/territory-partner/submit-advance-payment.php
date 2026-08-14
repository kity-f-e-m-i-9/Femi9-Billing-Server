<?php
include("checksession.php");
include("config.php");
error_reporting(0);

header('Content-Type: application/json');

$tp_id = (int)$Login_user_IDvl;
$submissionId = (int)($_POST['submission_id'] ?? 0);

if ($submissionId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid submission.']);
    exit;
}

$stmt = $db_conn->prepare(
    "SELECT id FROM tp_advance_payment_submissions WHERE id = ? AND territory_partner_id = ? AND status = 'draft'"
);
$stmt->bind_param('ii', $submissionId, $tp_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Submission not found, or it has already been submitted.']);
    exit;
}

$cntStmt = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM tp_advance_payment_screenshots WHERE submission_id = ?");
$cntStmt->bind_param('i', $submissionId);
$cntStmt->execute();
$screenshotCount = (int)($cntStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$cntStmt->close();

if ($screenshotCount < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Upload at least one payment screenshot before submitting.']);
    exit;
}

$upd = $db_conn->prepare(
    "UPDATE tp_advance_payment_submissions SET status='pending_review', submitted_at=NOW() WHERE id=? AND status='draft'"
);
$upd->bind_param('i', $submissionId);
$upd->execute();
$ok = $upd->affected_rows === 1;
$upd->close();

if (!$ok) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This submission has already been submitted.']);
    exit;
}

echo json_encode(['success' => true]);
