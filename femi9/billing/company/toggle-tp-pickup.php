<?php
include("checksession.php");
require_once __DIR__ . '/../shared/TpCourierPayment.php';
header('Content-Type: application/json');
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'csrf']); exit;
}

$enc_id = $_POST['id'] ?? '';
$allow  = array_key_exists('allow', $_POST) ? (int)$_POST['allow'] : -1;

if (empty($enc_id) || !in_array($allow, [0, 1])) {
    echo json_encode(['success' => false]); exit;
}

$id = (int)base64_decode($enc_id);
if (!$id) { echo json_encode(['success' => false]); exit; }

// A Sales BDM session may only toggle a TP inside their own assigned districts.
if (($Login_user_TYPEvl ?? '') === 'salesbdm') {
    require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';
    $_myTpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID, true);
    if (!in_array($id, $_myTpIds, true)) { echo json_encode(['success' => false]); exit; }
}

tpEnsureSelfPickupColumn($db_conn);
$stmt = $db_conn->prepare("UPDATE territory_partners SET allow_self_pickup = ? WHERE id = ?");
$stmt->bind_param("ii", $allow, $id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok, 'allow_self_pickup' => $allow]);
