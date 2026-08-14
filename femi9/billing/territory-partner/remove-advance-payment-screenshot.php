<?php
include("checksession.php");
include("config.php");
error_reporting(0);

header('Content-Type: application/json');

$tp_id = (int)$Login_user_IDvl;
$screenshotId = (int)($_POST['screenshot_id'] ?? 0);

if ($screenshotId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid screenshot id.']);
    exit;
}

// Only removable while the parent submission is still an unsubmitted draft
// owned by this TP — once submitted for review (or beyond), the screenshot
// is part of a record a company reviewer may already be looking at.
$stmt = $db_conn->prepare(
    "SELECT s.file_path FROM tp_advance_payment_screenshots s
     JOIN tp_advance_payment_submissions sub ON sub.id = s.submission_id
     WHERE s.id = ? AND sub.territory_partner_id = ? AND sub.status = 'draft'"
);
$stmt->bind_param('ii', $screenshotId, $tp_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Screenshot not found, or its submission can no longer be edited.']);
    exit;
}

$del = $db_conn->prepare("DELETE FROM tp_advance_payment_screenshots WHERE id = ?");
$del->bind_param('i', $screenshotId);
$del->execute();
$del->close();

$filePath = __DIR__ . '/advance_payment_screenshots/' . basename($row['file_path']);
if (file_exists($filePath)) unlink($filePath);

echo json_encode(['success' => true]);
