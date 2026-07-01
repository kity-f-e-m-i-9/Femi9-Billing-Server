<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$q             = trim($_GET['q'] ?? '');
$exclude_cp_id = (int)($_GET['exclude_cp_id'] ?? 0);
$exclude_tp_id = (int)($_GET['exclude_tp_id'] ?? 0);
$filter_type   = in_array($_GET['filter_type'] ?? '', ['cp', 'tp']) ? $_GET['filter_type'] : '';
$is_default    = isset($_GET['default']) && $_GET['default'] === '1';
$mode          = $_GET['mode'] ?? '';

if (!$is_default && strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

if ($is_default) {
    if ($mode === 'cp_stock_filter') {
        // First 20 locations at layers where is_stock_location = 1
        $def_res = $db_conn->query("
            SELECT n.id, n.name, n.parent_id, n.depth, COALESCE(n.target_amount, 0) AS target_amount
            FROM partner_location_nodes n
            JOIN partner_location_layers l ON l.depth = n.depth AND l.is_stock_location = 1
            WHERE n.is_active = 1
            ORDER BY n.name ASC
            LIMIT 20
        ");
        $matches = $def_res ? $def_res->fetch_all(MYSQLI_ASSOC) : [];
    } elseif ($mode === 'tp_stock_filter') {
        // First 20 locations at layers where is_tp_filter_enabled = 1
        $def_res = $db_conn->query("
            SELECT n.id, n.name, n.parent_id, n.depth, COALESCE(n.target_amount, 0) AS target_amount
            FROM partner_location_nodes n
            JOIN partner_location_layers l ON l.depth = n.depth AND l.is_tp_filter_enabled = 1
            WHERE n.is_active = 1
            ORDER BY n.name ASC
            LIMIT 20
        ");
        $matches = $def_res ? $def_res->fetch_all(MYSQLI_ASSOC) : [];
    } else {
        $col     = ($filter_type === 'tp') ? 'is_tp_filter_enabled' : 'is_cp_filter_enabled';
        $def_res = $db_conn->query("
            SELECT n.id, n.name, n.parent_id, n.depth, COALESCE(n.target_amount, 0) AS target_amount
            FROM partner_location_nodes n
            JOIN partner_location_layers l ON l.depth = n.depth AND l.$col = 1
            WHERE n.is_active = 1
            ORDER BY n.name ASC
            LIMIT 20
        ");
        $matches = $def_res ? $def_res->fetch_all(MYSQLI_ASSOC) : [];
    }
} else {
    if ($mode === 'cp_stock_filter') {
        // Search locations at layers where is_stock_location = 1
        $like = '%' . $q . '%';
        $stmt = $db_conn->prepare("
            SELECT n.id, n.name, n.parent_id, n.depth, COALESCE(n.target_amount, 0) AS target_amount
            FROM partner_location_nodes n
            JOIN partner_location_layers l ON l.depth = n.depth AND l.is_stock_location = 1
            WHERE n.is_active = 1 AND (n.name LIKE ? OR n.code LIKE ?)
            ORDER BY n.name ASC
            LIMIT 60
        ");
        $stmt->bind_param("ss", $like, $like);
        $stmt->execute();
        $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } elseif ($mode === 'tp_stock_filter') {
        // Search locations at layers where is_tp_filter_enabled = 1
        $like = '%' . $q . '%';
        $stmt = $db_conn->prepare("
            SELECT n.id, n.name, n.parent_id, n.depth, COALESCE(n.target_amount, 0) AS target_amount
            FROM partner_location_nodes n
            JOIN partner_location_layers l ON l.depth = n.depth AND l.is_tp_filter_enabled = 1
            WHERE n.is_active = 1 AND (n.name LIKE ? OR n.code LIKE ?)
            ORDER BY n.name ASC
            LIMIT 60
        ");
        $stmt->bind_param("ss", $like, $like);
        $stmt->execute();
        $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $like = '%' . $q . '%';
        $stmt = $db_conn->prepare("
            SELECT id, name, parent_id, depth, COALESCE(target_amount, 0) AS target_amount
            FROM partner_location_nodes
            WHERE (name LIKE ? OR code LIKE ?) AND is_active = 1
            ORDER BY depth ASC, name ASC
            LIMIT 60
        ");
        $stmt->bind_param("ss", $like, $like);
        $stmt->execute();
        $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

if (empty($matches)) {
    echo json_encode([]);
    exit;
}

// Load all nodes into a map for path building (id → {name, parent_id})
$all = $db_conn->query("SELECT id, name, parent_id FROM partner_location_nodes WHERE is_active = 1");
$node_map = [];
if ($all) {
    while ($r = $all->fetch_assoc()) {
        $node_map[(int)$r['id']] = [
            'name'      => $r['name'],
            'parent_id' => $r['parent_id'] !== null ? (int)$r['parent_id'] : null,
        ];
    }
}

function buildPath(int $id, array $map): string {
    $parts = [];
    $cur   = $id;
    $limit = 12;
    while ($cur !== null && isset($map[$cur]) && $limit-- > 0) {
        array_unshift($parts, $map[$cur]['name']);
        $cur = $map[$cur]['parent_id'];
    }
    return implode(' › ', $parts);
}

// Determine which of the match IDs have children (non-leaf)
$match_ids = implode(',', array_map('intval', array_column($matches, 'id')));
$has_children = [];
$child_res = $db_conn->query("SELECT DISTINCT parent_id FROM partner_location_nodes WHERE parent_id IN ($match_ids) AND is_active=1");
if ($child_res) {
    while ($r = $child_res->fetch_assoc()) $has_children[(int)$r['parent_id']] = true;
}

// Determine taken nodes (assigned to another CP/TP)
$taken_map = [];
$cp_res = $db_conn->query("SELECT location_id, channel_partner_id FROM channel_partner_locations WHERE location_id IN ($match_ids)");
if ($cp_res) {
    while ($r = $cp_res->fetch_assoc()) {
        if ((int)$r['channel_partner_id'] !== $exclude_cp_id)
            $taken_map[(int)$r['location_id']] = true;
    }
}
$tp_res = $db_conn->query("SELECT location_id, territory_partner_id FROM territory_partner_locations WHERE location_id IN ($match_ids)");
if ($tp_res) {
    while ($r = $tp_res->fetch_assoc()) {
        if ((int)$r['territory_partner_id'] !== $exclude_tp_id)
            $taken_map[(int)$r['location_id']] = true;
    }
}

// Layer map: depth => layer flags
$layer_stock = [];
$lr2 = $db_conn->query("SELECT depth, is_stock_location, is_cp_filter_enabled, is_tp_filter_enabled FROM partner_location_layers");
if ($lr2) { while ($r = $lr2->fetch_assoc()) $layer_stock[(int)$r['depth']] = [
    'is_stock_location'    => (bool)(int)$r['is_stock_location'],
    'is_cp_filter_enabled' => (bool)(int)$r['is_cp_filter_enabled'],
    'is_tp_filter_enabled' => (bool)(int)$r['is_tp_filter_enabled'],
]; }

// Build response
$result = [];
foreach ($matches as $node) {
    $id    = (int)$node['id'];
    $depth = (int)$node['depth'];
    $layer_flags = $layer_stock[$depth] ?? [];

    // Hide nodes exclusively belonging to the other filter side
    if ($filter_type && $layer_flags) {
        $cp_on = $layer_flags['is_cp_filter_enabled'] ?? false;
        $tp_on = $layer_flags['is_tp_filter_enabled'] ?? false;
        if ($filter_type === 'cp' && $tp_on && !$cp_on) continue;
        if ($filter_type === 'tp' && $cp_on && !$tp_on) continue;
    }

    $result[] = [
        'id'                => $id,
        'name'              => $node['name'],
        'path'              => buildPath($id, $node_map),
        'is_stock_location'    => ($layer_stock[$depth] ?? [])['is_stock_location']    ?? false,
        'is_cp_filter_enabled' => ($mode === 'cp_stock_filter') ? true : (($layer_stock[$depth] ?? [])['is_cp_filter_enabled'] ?? false),
        'is_tp_filter_enabled' => ($mode === 'tp_stock_filter') ? true : (($layer_stock[$depth] ?? [])['is_tp_filter_enabled'] ?? false),
        'is_leaf'           => !isset($has_children[$id]),
        'is_taken'          => isset($taken_map[$id]),
        'target_amount'     => (float)$node['target_amount'],
    ];
}

echo json_encode($result);
