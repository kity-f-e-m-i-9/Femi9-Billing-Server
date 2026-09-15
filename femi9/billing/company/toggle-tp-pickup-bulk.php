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

$allow = array_key_exists('allow', $_POST) ? (int)$_POST['allow'] : -1;
$type  = ($_POST['type'] ?? 'napkin') === 'diaper' ? 'diaper' : 'napkin';
if (!in_array($allow, [0, 1], true)) {
    echo json_encode(['success' => false]); exit;
}

tpEnsureSelfPickupColumn($db_conn);
$col = $type === 'diaper' ? 'allow_self_pickup_diaper' : 'allow_self_pickup_napkin';

// A Sales BDM session may only bulk-toggle TPs inside their own assigned
// districts — same scoping as the single-TP toggle.
if (($Login_user_TYPEvl ?? '') === 'salesbdm') {
    require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';
    $myTpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID, true);
    if (empty($myTpIds)) { echo json_encode(['success' => true, 'affected' => 0]); exit; }
    $idList = implode(',', array_map('intval', $myTpIds));
    $db_conn->query("UPDATE territory_partners SET $col = $allow WHERE id IN ($idList)");
} else {
    $db_conn->query("UPDATE territory_partners SET $col = $allow");
}

echo json_encode(['success' => true, 'type' => $type, 'allow' => $allow, 'affected' => $db_conn->affected_rows]);
