<?php
include("checksession.php");
include("config.php");
error_reporting(0);

header('Content-Type: application/json');

$tp_id = (int)$Login_user_IDvl;
$id = (int)($_POST['id'] ?? 0);

if ($id < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid screenshot.']);
    exit;
}

// Only an unlinked (still draft/pool), not-yet-accepted screenshot belonging
// to this TP can be removed — an accepted one has already counted toward
// unlocking a PO submission and must not silently disappear from the pool.
$stmt = $db_conn->prepare(
    "SELECT id, file_path FROM tp_courier_payments
     WHERE id = ? AND territory_partner_id = ? AND po_id IS NULL AND status != 'accepted'"
);
$stmt->bind_param('ii', $id, $tp_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'This screenshot cannot be removed.']);
    exit;
}

$del = $db_conn->prepare("DELETE FROM tp_courier_payments WHERE id = ?");
$del->bind_param('i', $id);
$del->execute();
$del->close();

$path = __DIR__ . '/courier_payment_screenshots/' . $row['file_path'];
if (is_file($path)) @unlink($path);

echo json_encode(['success' => true]);
