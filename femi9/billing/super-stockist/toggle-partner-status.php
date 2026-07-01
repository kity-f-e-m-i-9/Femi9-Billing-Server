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

if ($type !== 'tp' || empty($enc_id) || !in_array($new_status, [0, 1])) {
    echo json_encode(['success' => false]); exit;
}

$id = (int)base64_decode($enc_id);
if (!$id) { echo json_encode(['success' => false]); exit; }

// Ownership check
$own = $db_conn->prepare("SELECT id FROM territory_partners WHERE id=? AND onboard_ss_id=?");
$own->bind_param("is", $id, $Login_user_IDvl);
$own->execute();
if ($own->get_result()->num_rows === 0) {
    $own->close();
    echo json_encode(['success' => false, 'error' => 'unauthorized']); exit;
}
$own->close();

$stmt = $db_conn->prepare("UPDATE territory_partners SET is_active = ? WHERE id = ? AND onboard_ss_id = ?");
$stmt->bind_param("iis", $new_status, $id, $Login_user_IDvl);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok, 'new_status' => $new_status]);
