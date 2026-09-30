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

// A Sales BDM session may only toggle a TP/CP inside their own assigned districts.
if (($Login_user_TYPEvl ?? '') === 'salesbdm') {
    if ($type === 'tp') {
        require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';
        $_myTpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID, true);
        if (!in_array($id, $_myTpIds, true)) { echo json_encode(['success' => false]); exit; }
    } elseif ($type === 'cp') {
        require_once __DIR__ . '/../salesbdm/include/BdmCpScope.php';
        $_myCpIds = getBdmAssignedCpIds($db_conn, (int)$salesBdmID, true);
        if (!in_array($id, $_myCpIds, true)) { echo json_encode(['success' => false]); exit; }
    } else {
        echo json_encode(['success' => false]); exit;
    }
}

$table = $type === 'tp' ? 'territory_partners' : 'channel_partners';

$old_status = null;
if ($type === 'tp') {
    require_once __DIR__ . '/../shared/TpStatusHistory.php';
    $oldRes = $db_conn->prepare("SELECT is_active FROM territory_partners WHERE id = ?");
    $oldRes->bind_param("i", $id);
    $oldRes->execute();
    $old_status = $oldRes->get_result()->fetch_assoc()['is_active'] ?? null;
    $oldRes->close();
}

$stmt  = $db_conn->prepare("UPDATE `$table` SET is_active = ? WHERE id = ?");
$stmt->bind_param("ii", $new_status, $id);
$ok = $stmt->execute();
$stmt->close();

if ($ok && $type === 'tp' && $old_status !== null) {
    logTpStatusChange($db_conn, $id, (int)$old_status, $new_status);
}

echo json_encode(['success' => $ok, 'new_status' => $new_status]);
