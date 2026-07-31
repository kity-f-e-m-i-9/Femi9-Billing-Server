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

$stmt = $db_conn->prepare(
    "SELECT file_path FROM tp_purchase_order_screenshots
     WHERE id = ? AND territory_partner_id = ? AND po_id IS NULL"
);
$stmt->bind_param('ii', $screenshotId, $tp_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Screenshot not found.']);
    exit;
}

$del = $db_conn->prepare(
    "DELETE FROM tp_purchase_order_screenshots WHERE id = ? AND territory_partner_id = ? AND po_id IS NULL"
);
$del->bind_param('ii', $screenshotId, $tp_id);
$del->execute();
$del->close();

$filePath = __DIR__ . '/po_screenshots/' . basename($row['file_path']);
if (file_exists($filePath)) unlink($filePath);

$totalStmt = $db_conn->prepare(
    "SELECT COALESCE(SUM(detected_amount), 0) AS total
     FROM tp_purchase_order_screenshots
     WHERE territory_partner_id = ? AND po_id IS NULL AND status = 'accepted'"
);
$totalStmt->bind_param('i', $tp_id);
$totalStmt->execute();
$acceptedTotal = (float)($totalStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalStmt->close();

echo json_encode(['success' => true, 'accepted_total' => $acceptedTotal]);
