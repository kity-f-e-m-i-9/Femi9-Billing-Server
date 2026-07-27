<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$exclude_ms_id = (int)($_GET['exclude_ms_id'] ?? 0);

$_chk = $db_conn->query("SHOW TABLES LIKE 'marketing_staff_locations'");
if ($_chk && $_chk->num_rows === 0) {
    $db_conn->query("
        CREATE TABLE marketing_staff_locations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ms_id INT NOT NULL,
            location_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_ms_location (ms_id, location_id)
        )
    ");
}

$stmt = $db_conn->prepare("
    SELECT pln.id, pln.name, pln.depth, pll.layer_name
    FROM partner_location_nodes pln
    JOIN partner_location_layers pll ON pll.depth = pln.depth
    WHERE pll.is_ms_filter_enabled = 1 AND pln.is_active = 1
    ORDER BY pll.depth ASC, pln.name ASC
");
$stmt->execute();
$nodes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($nodes)) {
    echo json_encode([]);
    exit;
}

$ids = implode(',', array_map('intval', array_column($nodes, 'id')));

$taken_map = [];
$ms_res = $db_conn->query("SELECT location_id, ms_id FROM marketing_staff_locations WHERE location_id IN ($ids)");
if ($ms_res) {
    while ($r = $ms_res->fetch_assoc()) {
        if ((int)$r['ms_id'] !== $exclude_ms_id)
            $taken_map[(int)$r['location_id']] = true;
    }
}

$result = [];
foreach ($nodes as $node) {
    $result[] = [
        'id'         => (int)$node['id'],
        'name'       => $node['name'],
        'layer_name' => $node['layer_name'],
        'is_taken'   => isset($taken_map[(int)$node['id']]),
    ];
}

echo json_encode($result);
