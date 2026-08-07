<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
header('Content-Type: application/json');
error_reporting(0);

$exclude_tp_id = (int)($_GET['exclude_tp_id'] ?? 0);

// Only offer locations within this BDM's own assigned districts — a Sales
// BDM should only be able to place a new TP inside their own territory.
$districtNames = getBdmAssignedDistrictNames($db_conn, (int)$salesBdmID);
if (empty($districtNames)) {
    echo json_encode([]);
    exit;
}

// TP-assignable locations usually sit BELOW district level (e.g. Firka) —
// so the filter here has to check "is this node's enclosing district one of
// mine", not match the node's own name against a district name. Load the
// (small) full location tree once and walk each candidate's parent_id chain
// up to district depth, same approach as BdmTpScope's own resolution.
$districtDepthRow = $db_conn->query("SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1")->fetch_assoc();
$districtDepth = $districtDepthRow ? (int)$districtDepthRow['depth'] : 0;
$districtNamesLower = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);

$allNodesRaw = $db_conn->query("SELECT id, name, depth, parent_id FROM partner_location_nodes")->fetch_all(MYSQLI_ASSOC);
$nodeById = [];
foreach ($allNodesRaw as $n) { $nodeById[(int)$n['id']] = $n; }

function nodeInMyDistricts(int $nodeId, array &$nodeById, int $districtDepth, array $districtNamesLower): bool {
    $current = $nodeById[$nodeId] ?? null;
    $guard = 0;
    while ($current && $guard < 10) {
        if ((int)$current['depth'] === $districtDepth) {
            return in_array(mb_strtolower(trim($current['name'])), $districtNamesLower, true);
        }
        $current = $nodeById[(int)$current['parent_id']] ?? null;
        $guard++;
    }
    return false;
}

$candRes = $db_conn->query("
    SELECT pln.id, pln.name, pln.depth, COALESCE(pln.target_amount, 0) AS target_amount, pll.layer_name
    FROM partner_location_nodes pln
    JOIN partner_location_layers pll ON pll.depth = pln.depth
    WHERE pll.is_tp_filter_enabled = 1 AND pln.is_active = 1
    ORDER BY pll.depth ASC, pln.name ASC
");
$candidates = $candRes ? $candRes->fetch_all(MYSQLI_ASSOC) : [];

$nodes = [];
foreach ($candidates as $c) {
    if (nodeInMyDistricts((int)$c['id'], $nodeById, $districtDepth, $districtNamesLower)) {
        $nodes[] = $c;
    }
}

if (empty($nodes)) {
    echo json_encode([]);
    exit;
}

$ids = implode(',', array_map('intval', array_column($nodes, 'id')));
$taken_map = [];
$res = $db_conn->query("SELECT location_id FROM territory_partner_locations WHERE location_id IN ($ids)" . ($exclude_tp_id > 0 ? " AND territory_partner_id != $exclude_tp_id" : ''));
if ($res) {
    while ($r = $res->fetch_assoc()) { $taken_map[(int)$r['location_id']] = true; }
}

$result = [];
foreach ($nodes as $n) {
    $result[] = [
        'id'            => (int)$n['id'],
        'name'          => $n['name'],
        'depth'         => (int)$n['depth'],
        'target_amount' => (float)$n['target_amount'],
        'layer_name'    => $n['layer_name'],
        'is_taken'      => isset($taken_map[(int)$n['id']]),
    ];
}

echo json_encode($result);
?>
