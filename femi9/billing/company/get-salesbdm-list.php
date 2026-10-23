<?php
// All active Sales BDMs, for MIS Report's "Sales BDM" tab picker (mis-report.php).
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
header('Content-Type: application/json');
error_reporting(0);

$rows = $db_conn->query("
    SELECT id, bdm_name, zone
    FROM sales_bdm_staff
    WHERE account_status = 'active'
    ORDER BY bdm_name ASC
")->fetch_all(MYSQLI_ASSOC);

echo json_encode(array_map(fn($r) => [
    'id'   => (int)$r['id'],
    'name' => trim($r['bdm_name']),
    'zone' => $r['zone'],
], $rows));
