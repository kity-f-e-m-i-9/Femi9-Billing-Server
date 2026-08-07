<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
header('Content-Type: application/json');
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'csrf']); exit;
}

$enc_id     = $_POST['id']   ?? '';
$new_status = array_key_exists('status', $_POST) ? (int)$_POST['status'] : -1;

if (empty($enc_id) || !in_array($new_status, [0, 1])) {
    echo json_encode(['success' => false]); exit;
}

$id = (int)base64_decode($enc_id);
if (!$id) { echo json_encode(['success' => false]); exit; }

// Ownership check — only allow toggling a TP inside this BDM's own districts.
$myTpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID);
if (!in_array($id, $myTpIds, true)) {
    echo json_encode(['success' => false]); exit;
}

$stmt = $db_conn->prepare("UPDATE territory_partners SET is_active = ? WHERE id = ?");
$stmt->bind_param("ii", $new_status, $id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'new_status' => $new_status]);
?>
