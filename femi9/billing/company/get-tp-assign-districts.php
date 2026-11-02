<?php
// Step 1 of the "Manage Daily Assign District TPs" drill-down — given an
// ms_id, return the Districts assigned to them (reuses the same
// State/District/Taluk-collapsing logic the marketing portal's own Add
// Shop picker uses, so "assigned to this DM" means the same thing
// everywhere in the app).
include("checksession.php");
require_once __DIR__ . "/../marketing/include/AssignedLocations.php";
header('Content-Type: application/json');
error_reporting(0);

$ms_id = (int)($_GET['ms_id'] ?? 0);
if (!$ms_id) {
    echo json_encode([]);
    exit;
}

$districts = getMsAssignedDistricts($db_conn, $ms_id);
$result = [];
foreach ($districts as $d) {
    $result[] = ['id' => (int)$d['id'], 'name' => $d['name']];
}
echo json_encode($result);
