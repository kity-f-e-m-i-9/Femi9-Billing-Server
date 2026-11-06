<?php
// Step 1 of the marketing-side drill-down — Districts assigned to the
// requested ms_id, which must be one of the logged-in ASM/DM's own
// subordinates (never trusted directly from the client).
include("checksession.php");
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/TeamSubtree.php';
header('Content-Type: application/json');
error_reporting(0);

$loginMsId = (int)$Login_user_IDvl;
$ms_id = (int)($_GET['ms_id'] ?? 0);
if (!$ms_id) { echo json_encode([]); exit; }

$subtreeIds = array_diff(getMsSubtreeIds($db_conn, $loginMsId), [$loginMsId]);
if (!in_array($ms_id, $subtreeIds, true)) { echo json_encode([]); exit; }

$districts = getMsAssignedDistricts($db_conn, $ms_id);
$result = [];
foreach ($districts as $d) {
    $result[] = ['id' => (int)$d['id'], 'name' => $d['name']];
}
echo json_encode($result);
