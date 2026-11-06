<?php
// Step 3 of the marketing-side drill-down — Firkas (depth 6) under one
// Division, walking Division -> Taluk -> Firka same as the company version.
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$division_id = (int)($_GET['division_id'] ?? 0);
if (!$division_id) {
    echo json_encode([]);
    exit;
}

$stmt = $db_conn->prepare("
    SELECT f.id, f.name
    FROM partner_location_nodes t
    JOIN partner_location_nodes f ON f.parent_id = t.id AND f.depth = 6 AND f.is_active = 1
    WHERE t.parent_id = ? AND t.depth = 5 AND t.is_active = 1
    ORDER BY f.name ASC
");
$stmt->bind_param('i', $division_id);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$result = [];
foreach ($rows as $r) {
    $result[] = ['id' => (int)$r['id'], 'name' => $r['name']];
}
echo json_encode($result);
