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
$mode   = $_POST['mode'] ?? '';
$type   = ($_POST['type'] ?? 'napkin') === 'diaper' ? 'diaper' : 'napkin';

if (empty($enc_id) || !in_array($mode, ['disabled', 'all', 'cp_only'], true)) {
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

tpEnsurePickupModeColumn($db_conn);
$col = $type === 'diaper' ? 'pickup_mode_diaper' : 'pickup_mode_napkin';
$stmt = $db_conn->prepare("UPDATE territory_partners SET $col = ? WHERE id = ?");
$stmt->bind_param("si", $mode, $id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok, 'type' => $type, 'mode' => $mode]);
