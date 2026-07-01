<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$parent_id     = (isset($_GET['parent_id']) && $_GET['parent_id'] !== '') ? (int)$_GET['parent_id'] : null;
$exclude_cp_id = isset($_GET['exclude_cp_id']) ? (int)$_GET['exclude_cp_id'] : 0;
$exclude_tp_id = isset($_GET['exclude_tp_id']) ? (int)$_GET['exclude_tp_id'] : 0;
$filter_type   = in_array($_GET['filter_type'] ?? '', ['cp', 'tp']) ? $_GET['filter_type'] : '';

// Layer map: depth => {layer_name, is_stock_location, is_cp_filter_enabled, is_tp_filter_enabled}
$layer_data = [];
$lr = $db_conn->query("SELECT depth, layer_name, is_stock_location, is_cp_filter_enabled, is_tp_filter_enabled FROM partner_location_layers ORDER BY depth");
if ($lr) { while ($row = $lr->fetch_assoc()) $layer_data[(int)$row['depth']] = $row; }

// Fetch nodes at this level
if ($parent_id === null) {
    $stmt = $db_conn->prepare("
        SELECT id, name, depth, parent_id, COALESCE(target_amount, 0) AS target_amount
        FROM partner_location_nodes
        WHERE parent_id IS NULL AND is_active = 1
        ORDER BY name
    ");
    $stmt->execute();
} else {
    $stmt = $db_conn->prepare("
        SELECT id, name, depth, parent_id, COALESCE(target_amount, 0) AS target_amount
        FROM partner_location_nodes
        WHERE parent_id = ? AND is_active = 1
        ORDER BY name
    ");
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
}
$nodes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($nodes)) {
    echo json_encode([]);
    exit;
}

// Batch: which of these nodes have children?
$ids     = array_column($nodes, 'id');
$in      = implode(',', array_map('intval', $ids));
$has_children = [];
$child_res = $db_conn->query("
    SELECT DISTINCT parent_id
    FROM partner_location_nodes
    WHERE parent_id IN ($in) AND is_active = 1
");
if ($child_res) {
    while ($r = $child_res->fetch_assoc()) {
        $has_children[(int)$r['parent_id']] = true;
    }
}

// Batch: which of these nodes are taken (by any channel partner or territory partner)?
$taken_map = [];
$cp_res = $db_conn->query("SELECT location_id, channel_partner_id FROM channel_partner_locations WHERE location_id IN ($in)");
if ($cp_res) {
    while ($r = $cp_res->fetch_assoc()) {
        if ((int)$r['channel_partner_id'] !== $exclude_cp_id) {
            $taken_map[(int)$r['location_id']] = true;
        }
    }
}
$tp_res = $db_conn->query("SELECT location_id, territory_partner_id FROM territory_partner_locations WHERE location_id IN ($in)");
if ($tp_res) {
    while ($r = $tp_res->fetch_assoc()) {
        if ((int)$r['territory_partner_id'] !== $exclude_tp_id) {
            $taken_map[(int)$r['location_id']] = true;
        }
    }
}

// Build response
$result = [];
foreach ($nodes as $node) {
    $id      = (int)$node['id'];
    $is_leaf = !isset($has_children[$id]);
    $is_taken = isset($taken_map[$id]);
    $depth = (int)$node['depth'];
    $layer = $layer_data[$depth] ?? null;

    // Hide nodes exclusively belonging to the other filter side
    if ($filter_type && $layer) {
        $cp_on = (bool)(int)$layer['is_cp_filter_enabled'];
        $tp_on = (bool)(int)$layer['is_tp_filter_enabled'];
        if ($filter_type === 'cp' && $tp_on && !$cp_on) continue;
        if ($filter_type === 'tp' && $cp_on && !$tp_on) continue;
    }

    $result[] = [
        'id'                => $id,
        'name'              => $node['name'],
        'depth'             => $depth,
        'layer_name'        => $layer ? $layer['layer_name'] : null,
        'is_stock_location'    => $layer ? (bool)(int)$layer['is_stock_location']    : false,
        'is_cp_filter_enabled' => $layer ? (bool)(int)$layer['is_cp_filter_enabled'] : false,
        'is_tp_filter_enabled' => $layer ? (bool)(int)$layer['is_tp_filter_enabled'] : false,
        'is_leaf'           => $is_leaf,
        'is_taken'          => $is_taken,
        'target_amount'     => (float)$node['target_amount'],
    ];
}

echo json_encode($result);
