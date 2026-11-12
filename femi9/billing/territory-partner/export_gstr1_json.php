<?php
// GSTR-1 JSON export (GST offline tool format) for the logged-in Territory Partner.
// Built by company/include/Gstr1JsonHelper.php in TP mode (shop + customer sales and returns only).
ob_start();
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../company/include/Gstr1JsonHelper.php';

$from_date = date("Y-m-01", strtotime($_REQUEST['frdate'] ?? ''));
$to_date   = date("Y-m-t",  strtotime($_REQUEST['todate'] ?? ''));
$tp_id     = (int)$Login_user_IDvl;   // always the logged-in TP — never taken from the request

$data = build_gstr1_json($db_conn, $Login_user_TYPEvl, $tp_id, $from_date, $to_date, true);
ini_set('serialize_precision', '-1');
$json = json_encode($data, JSON_UNESCAPED_SLASHES);

$file = ($data['gstin'] ?: 'GSTR1') . '_GSTR1_' . strtoupper(date('M-Y', strtotime($to_date))) . '_' . date('dmY_His') . '.json';
ob_end_clean();
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $file . '"');
echo $json;
