<?php
// GSTR-1 JSON export (offline-tool format) — same data as GSTR1.php, built by
// include/Gstr1JsonHelper.php.
ob_start();
include("checksession.php"); require_once("include/GodownAccess.php");
include("config.php");
require_once __DIR__ . '/include/Gstr1JsonHelper.php';

$from_month = $_REQUEST['frdate'] ?? '';
$to_month   = $_REQUEST['todate'] ?? '';
$gid = (int)($_REQUEST['godown_id'] ?? 0);
if (!$gid || !is_godown_allowed($db_conn, $gid)) { ob_end_clean(); http_response_code(403); echo "Not allowed"; exit; }
$from_date = date("Y-m-01", strtotime($from_month));
$to_date   = date("Y-m-t", strtotime($to_month));

$data = build_gstr1_json($db_conn, $Login_user_TYPEvl, $gid, $from_date, $to_date);
$json = json_encode($data, JSON_UNESCAPED_SLASHES);

$file = ($data['gstin'] ?: 'GSTR1') . '_GSTR1_' . strtoupper(date('M-Y', strtotime($to_date))) . '_' . date('dmY_His') . '.json';
ob_end_clean();
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $file . '"');
echo $json;
