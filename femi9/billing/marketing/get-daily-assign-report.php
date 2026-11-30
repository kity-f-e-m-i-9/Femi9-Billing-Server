<?php
// Marketing-side AJAX data source for daily-assign-report.php — scoped to
// the logged-in ASM/DM's own subtree (never company-wide). A specific ms_id
// must belong to that subtree or it's rejected; no ms_id means "my whole
// team" (every district under every subordinate), same rule
// tp-assign-firka-action.php already enforces for saves.
include("checksession.php");
require_once __DIR__ . '/include/TeamSubtree.php';
require_once __DIR__ . "/../company/include/DailyAssignReportData.php";
header('Content-Type: application/json');
error_reporting(0);

$loginMsId  = (int)$Login_user_IDvl;
$subtreeIds = array_values(array_diff(getMsSubtreeIds($db_conn, $loginMsId), [$loginMsId]));

$ms_id = (int)($_GET['ms_id'] ?? 0);
$from  = $_GET['from'] ?? '';
$to    = $_GET['to'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to   = date('Y-m-d'); }

if ($ms_id) {
    if (!in_array($ms_id, $subtreeIds, true)) { echo json_encode([]); exit; }
    echo json_encode(fetchDailyAssignRows($db_conn, $ms_id, $from, $to));
    exit;
}

echo json_encode(fetchDailyAssignRowsForMsIds($db_conn, $subtreeIds, $from, $to));
