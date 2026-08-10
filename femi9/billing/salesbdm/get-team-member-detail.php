<?php
include("checksession.php");
include("config.php");
require_once("include/TeamSubtree.php");
require_once("include/TeamTargetRollup.php");
header('Content-Type: application/json');
error_reporting(0);

$viewId = (int)($_GET['bdm_id'] ?? 0);
if (!$viewId) { echo json_encode(['error' => 'invalid_id']); exit; }

// Only allow drilling into someone in your own reporting chain (yourself
// included) — never an arbitrary bdm_id typed into the URL.
$mySubtree = getBdmSubtreeIds($db_conn, (int)$salesBdmID);
if (!in_array($viewId, $mySubtree, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

$stmt = $db_conn->prepare("SELECT bdm_name, bdm_mobile, zone FROM sales_bdm_staff WHERE id = ?");
$stmt->bind_param('i', $viewId);
$stmt->execute();
$bdm = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bdm) { echo json_encode(['error' => 'not_found']); exit; }

$rollup = getBdmRawTargetAchieved($db_conn, $viewId, $from, $to);
$tpBreakdown = getBdmTpBreakdown($db_conn, $viewId, $from, $to);

echo json_encode([
    'bdm_name' => $bdm['bdm_name'],
    'bdm_mobile' => $bdm['bdm_mobile'],
    'zone' => $bdm['zone'],
    'target' => $rollup['target'],
    'achieved' => $rollup['achieved'],
    'pct' => $rollup['target'] > 0 ? min(round($rollup['achieved'] / $rollup['target'] * 100, 1), 999) : 0,
    'tp_count' => $rollup['tp_count'],
    'tps' => $tpBreakdown,
]);
