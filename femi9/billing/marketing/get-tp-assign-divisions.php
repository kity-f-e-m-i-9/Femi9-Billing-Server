<?php
// Step 2 of the marketing-side drill-down — Divisions (depth 4) under one
// District. Read-only location-tree data, same as the company version.
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$district_id = (int)($_GET['district_id'] ?? 0);
if (!$district_id) {
    echo json_encode([]);
    exit;
}

$stmt = $db_conn->prepare("SELECT id, name FROM partner_location_nodes WHERE parent_id = ? AND depth = 4 AND is_active = 1 ORDER BY name ASC");
$stmt->bind_param('i', $district_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$result = [];
foreach ($rows as $r) {
    $result[] = ['id' => (int)$r['id'], 'name' => $r['name']];
}
echo json_encode($result);
