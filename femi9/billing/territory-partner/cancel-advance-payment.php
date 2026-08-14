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
    "SELECT id, status FROM tp_advance_payment_submissions WHERE id = ? AND territory_partner_id = ?"
);
$stmt->bind_param('ii', $submissionId, $tp_id);
$stmt->execute();
$sub = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sub) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Submission not found.']);
    exit;
}

// Only withdrawable while still awaiting company action — once accepted or
// rejected it's a closed record. Matches delete-purchase-order.php's
// 'waiting'-only rule; here it's the pre-decision statuses (draft +
// pending_review) rather than a single status, since a submission can be
// withdrawn either before or after being sent for review.
if (!in_array($sub['status'], ['draft', 'pending_review'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'This submission can no longer be cancelled — it has already been reviewed.']);
    exit;
}

// Clean up screenshot files from disk before the DB cascade removes the rows.
$files = $db_conn->prepare("SELECT file_path FROM tp_advance_payment_screenshots WHERE submission_id = ?");
$files->bind_param('i', $submissionId);
$files->execute();
$fileRows = $files->get_result()->fetch_all(MYSQLI_ASSOC);
$files->close();

$uploadDir = __DIR__ . '/advance_payment_screenshots/';
foreach ($fileRows as $f) {
    $path = $uploadDir . basename($f['file_path']);
    if (file_exists($path)) unlink($path);
}

$del = $db_conn->prepare(
    "DELETE FROM tp_advance_payment_submissions WHERE id = ? AND territory_partner_id = ? AND status IN ('draft','pending_review')"
);
$del->bind_param('ii', $submissionId, $tp_id);
$del->execute();
$ok = $del->affected_rows === 1;
$del->close();

if (!$ok) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Could not cancel this submission — it may have already been actioned.']);
    exit;
}

echo json_encode(['success' => true]);
