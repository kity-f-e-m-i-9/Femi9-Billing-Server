<?php
// All active Marketing Staff, for MIS Report's "Marketing Staff" tab picker
// (mis-report.php). Mirrors get-salesbdm-list.php exactly.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
header('Content-Type: application/json');
error_reporting(0);

$rows = $db_conn->query("
    SELECT id, ms_name
    FROM marketing_staff
    WHERE account_status = 'active'
    ORDER BY ms_name ASC
")->fetch_all(MYSQLI_ASSOC);

echo json_encode(array_map(fn($r) => [
    'id'   => (int)$r['id'],
    'name' => trim($r['ms_name']),
], $rows));
