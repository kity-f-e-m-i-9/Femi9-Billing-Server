<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'csrf']); exit;
}

$type       = $_POST['type'] ?? '';
$enc_id     = $_POST['id']   ?? '';
$new_status = array_key_exists('status', $_POST) ? (int)$_POST['status'] : -1;

if (!in_array($type, ['tp', 'cp']) || empty($enc_id) || !in_array($new_status, [0, 1])) {
    echo json_encode(['success' => false]); exit;
}

$id = (int)base64_decode($enc_id);
if (!$id) { echo json_encode(['success' => false]); exit; }

$table = $type === 'tp' ? 'territory_partners' : 'channel_partners';
$stmt  = $db_conn->prepare("UPDATE `$table` SET is_active = ? WHERE id = ?");
$stmt->bind_param("ii", $new_status, $id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok, 'new_status' => $new_status]);
