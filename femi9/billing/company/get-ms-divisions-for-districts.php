<?php
// Given a set of District node ids (the ones already selected on the Assign
// Location picker before switching to "Division"), return every Division
// directly under them. Scoped to THOSE specific districts rather than the
// team level's/manager's whole district scope, so switching to Division
// narrows down from whatever districts this particular person already had,
// instead of re-offering every division under the manager's entire area.
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

function respond($nodes, $error = null) {
    echo json_encode(['nodes' => $nodes, 'error' => $error]);
    exit;
}

$district_ids_raw = (string)($_GET['district_ids'] ?? '');
$exclude_ms_id = (int)($_GET['exclude_ms_id'] ?? 0);

$district_ids = array_values(array_filter(array_map('intval', explode(',', $district_ids_raw))));
if (empty($district_ids)) {
    respond([], 'no_districts');
}

$district_ph = implode(',', array_fill(0, count($district_ids), '?'));
$district_types = str_repeat('i', count($district_ids));

// Division depth = one below whichever depth these given ids actually sit
// at (normally District, depth 3) — looked up from the nodes themselves
// rather than assumed, so this stays correct if the location tree is ever
// renumbered.
$stmt_depth = $db_conn->prepare("SELECT MIN(depth) AS d FROM partner_location_nodes WHERE id IN ($district_ph)");
$stmt_depth->bind_param($district_types, ...$district_ids);
$stmt_depth->execute();
$district_depth = (int)($stmt_depth->get_result()->fetch_assoc()['d'] ?? 0);
$stmt_depth->close();
if (!$district_depth) {
    respond([], 'no_districts');
}
$division_depth = $district_depth + 1;

$stmt_n = $db_conn->prepare("
    SELECT id, name FROM partner_location_nodes
    WHERE depth = ? AND is_active = 1 AND parent_id IN ($district_ph)
    ORDER BY name ASC
");
$stmt_n->bind_param('i' . $district_types, $division_depth, ...$district_ids);
$stmt_n->execute();
$nodes = $stmt_n->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_n->close();

if (empty($nodes)) {
    respond([]);
}

$ids = implode(',', array_map('intval', array_column($nodes, 'id')));
$taken_map = [];
$res = $db_conn->query("SELECT location_id, ms_id FROM marketing_staff_locations WHERE location_id IN ($ids)");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        if ((int)$r['ms_id'] !== $exclude_ms_id) { $taken_map[(int)$r['location_id']] = true; }
    }
}

$result = [];
foreach ($nodes as $node) {
    $result[] = [
        'id'       => (int)$node['id'],
        'name'     => $node['name'],
        'is_taken' => isset($taken_map[(int)$node['id']]),
    ];
}

respond($result);
