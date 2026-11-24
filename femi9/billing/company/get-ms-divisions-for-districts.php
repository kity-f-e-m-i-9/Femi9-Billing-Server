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
// Same exclusion get-ms-location-options.php already applies — this DM's own
// manager naturally "holds" the whole district (that's how it got offered to
// this DM in the first place), so a division under it must NOT show locked
// just because the manager's own district-level row exists. Without this,
// every division under a manager-held district showed permanently locked for
// every one of that manager's own subordinates. 2026-11-21, user-reported
// (live-confirmed via DevTools: district_ids=3, exclude_ms_id=71, all 3
// divisions came back is_taken:true even though nobody but the DM's own
// manager — ms_id 64 — held anything under Chengalpattu).
$manager_id = (int)($_GET['manager_id'] ?? 0);

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
    SELECT id, name, parent_id FROM partner_location_nodes
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

// A division counts as taken either because it's DIRECTLY assigned to
// someone else, or because its own PARENT DISTRICT is assigned to someone
// else (a full-district assignment covers every division under it) — in
// both cases excluding this DM's own existing row and their manager's own
// row (the manager naturally "holds" the district that's why it was offered
// here in the first place; that must never itself count as a conflict).
$ids = implode(',', array_map('intval', array_column($nodes, 'id')));
$allLocationIds = array_unique(array_merge($district_ids, array_map('intval', array_column($nodes, 'id'))));
$allIdsList = implode(',', $allLocationIds);

$taken_map = [];
$district_taken_map = [];
$res = $db_conn->query("SELECT location_id, ms_id FROM marketing_staff_locations WHERE location_id IN ($allIdsList)");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $row_ms_id = (int)$r['ms_id'];
        if ($row_ms_id === $exclude_ms_id || $row_ms_id === $manager_id) { continue; }
        $loc_id = (int)$r['location_id'];
        if (in_array($loc_id, $district_ids, true)) {
            $district_taken_map[$loc_id] = true;
        } else {
            $taken_map[$loc_id] = true;
        }
    }
}

$result = [];
foreach ($nodes as $node) {
    $nodeId = (int)$node['id'];
    $result[] = [
        'id'       => $nodeId,
        'name'     => $node['name'],
        'is_taken' => isset($taken_map[$nodeId]) || isset($district_taken_map[(int)$node['parent_id']]),
    ];
}

respond($result);
