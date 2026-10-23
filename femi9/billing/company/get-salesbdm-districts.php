<?php
// The districts a given Sales BDM's own Zone covers — for MIS Report's
// "Sales BDM" tab (mis-report.php), to narrow the embedded dashboard
// (view-bdm-dashboard.php) down to one district instead of their whole zone.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('report');
require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';
header('Content-Type: application/json');
error_reporting(0);

$bdm_id = (int)($_GET['bdm_id'] ?? 0);
if (!$bdm_id) { echo json_encode([]); exit; }

$names = getBdmAssignedDistrictNames($db_conn, $bdm_id);
sort($names);
echo json_encode(array_values($names));
