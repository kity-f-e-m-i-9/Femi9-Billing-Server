<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$exclude_cp_id = (int)($_GET['exclude_cp_id'] ?? 0);

$stmt = $db_conn->prepare("
    SELECT pln.id, pln.name, pln.depth, COALESCE(pln.target_amount, 0) AS target_amount, pll.layer_name
    FROM partner_location_nodes pln
    JOIN partner_location_layers pll ON pll.depth = pln.depth
    WHERE pll.is_cp_filter_enabled = 1 AND pln.is_active = 1
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
$cp_res = $db_conn->query("SELECT location_id, channel_partner_id FROM channel_partner_locations WHERE location_id IN ($ids)");
if ($cp_res) {
    while ($r = $cp_res->fetch_assoc()) {
        if ((int)$r['channel_partner_id'] !== $exclude_cp_id)
            $taken_map[(int)$r['location_id']] = true;
    }
}

$result = [];
foreach ($nodes as $node) {
    $result[] = [
        'id'            => (int)$node['id'],
        'name'          => $node['name'],
        'layer_name'    => $node['layer_name'],
        'target_amount' => (float)$node['target_amount'],
        'is_taken'      => isset($taken_map[(int)$node['id']]),
    ];
}

echo json_encode($result);
