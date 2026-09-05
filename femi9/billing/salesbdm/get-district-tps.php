<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
error_reporting(0);
header('Content-Type: application/json');

// Never trust the requested districts on their own — only districts this
// BDM is actually assigned to are ever queried, same posture as the note
// form itself (add-district-note.php) validates against.
$assignedDistricts = getBdmAssignedDistrictNames($db_conn, (int)$salesBdmID);

$requested = (array)($_GET['districts'] ?? []);
$requested = array_values(array_intersect(array_map('trim', $requested), $assignedDistricts));

if (empty($requested)) {
    echo json_encode(['tps' => []]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($requested), '?'));
$types = str_repeat('s', count($requested));
$normalized = array_map(fn($n) => mb_strtolower(trim($n)), $requested);

$stmt = $db_conn->prepare(
    "SELECT name, tp_id, branch_district, is_active
     FROM territory_partners
     WHERE LOWER(TRIM(branch_district)) IN ($placeholders)
     ORDER BY branch_district ASC, name ASC"
);
$stmt->bind_param($types, ...$normalized);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$tps = array_map(fn($r) => [
    'name'     => $r['name'],
    'tp_id'    => $r['tp_id'],
    'district' => $r['branch_district'],
    'active'   => (int)$r['is_active'] === 1,
], $rows);

echo json_encode(['tps' => $tps]);
