<?php
// AJAX data source for daily-assign-report.php — lists tp_daily_assignments
// rows for a date range, optionally scoped to one DM's assigned districts
// (same district-resolving rule as get-tp-assign-districts.php / the
// marketing portal's Add Shop picker).
include("checksession.php");
require_once __DIR__ . "/include/DailyAssignReportData.php";
header('Content-Type: application/json');
error_reporting(0);

$ms_id = (int)($_GET['ms_id'] ?? 0);
$from  = $_GET['from'] ?? '';
$to    = $_GET['to'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-01'); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $to   = date('Y-m-d'); }

echo json_encode(fetchDailyAssignRows($db_conn, $ms_id, $from, $to));
