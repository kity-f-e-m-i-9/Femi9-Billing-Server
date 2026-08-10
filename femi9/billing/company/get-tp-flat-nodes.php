<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$exclude_tp_id = (int)($_GET['exclude_tp_id'] ?? 0);

$candRes = $db_conn->query("
    SELECT pln.id, pln.name, pln.depth, COALESCE(pln.target_amount, 0) AS target_amount, pll.layer_name
    FROM partner_location_nodes pln
    JOIN partner_location_layers pll ON pll.depth = pln.depth
    WHERE pll.is_tp_filter_enabled = 1 AND pln.is_active = 1
    ORDER BY pll.depth ASC, pln.name ASC
");
$nodes = $candRes ? $candRes->fetch_all(MYSQLI_ASSOC) : [];

// A Sales BDM session only gets locations within their own assigned
// districts — a BDM should only be able to place a TP inside their territory.
if (($Login_user_TYPEvl ?? '') === 'salesbdm') {
    require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';
    $districtNames = getBdmAssignedDistrictNames($db_conn, (int)$salesBdmID);
    if (empty($districtNames)) {
        echo json_encode([]);
        exit;
    }
    $districtDepthRow = $db_conn->query("SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1")->fetch_assoc();
    $districtDepth = $districtDepthRow ? (int)$districtDepthRow['depth'] : 0;
    $districtNamesLower = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);

    $allNodesRaw = $db_conn->query("SELECT id, name, depth, parent_id FROM partner_location_nodes")->fetch_all(MYSQLI_ASSOC);
    $nodeById = [];
    foreach ($allNodesRaw as $n) { $nodeById[(int)$n['id']] = $n; }

    $nodes = array_values(array_filter($nodes, function ($c) use (&$nodeById, $districtDepth, $districtNamesLower) {
        $current = $nodeById[(int)$c['id']] ?? null;
        $guard = 0;
        while ($current && $guard < 10) {
            if ((int)$current['depth'] === $districtDepth) {
                return in_array(mb_strtolower(trim($current['name'])), $districtNamesLower, true);
            }
            $current = $nodeById[(int)$current['parent_id']] ?? null;
            $guard++;
        }
        return false;
    }));
}

if (empty($nodes)) {
    echo json_encode([]);
    exit;
}

$ids = implode(',', array_map('intval', array_column($nodes, 'id')));

$taken_map = [];
$tp_res = $db_conn->query("SELECT location_id, territory_partner_id FROM territory_partner_locations WHERE location_id IN ($ids)");
if ($tp_res) {
    while ($r = $tp_res->fetch_assoc()) {
        if ((int)$r['territory_partner_id'] !== $exclude_tp_id)
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
