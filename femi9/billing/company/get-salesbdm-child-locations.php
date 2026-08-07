<?php
// Powers the optional "also directly handle locations one level down" picker
// on Add/Edit Sales BDM — e.g. a Chief BDM (State-level) who also wants to
// personally hold specific Districts within their own State. Given the
// State(s) they've picked in the primary Assign Location picker, this returns
// the District-level children of those specific States (not of anyone's
// team-level layer in general), locking out ones already assigned elsewhere.
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$parent_ids     = $_GET['parent_ids'] ?? [];
$target_depth   = (int)($_GET['target_depth'] ?? 0);
$exclude_bdm_id = (int)($_GET['exclude_bdm_id'] ?? 0);

function respond($nodes, $error = null) {
    echo json_encode(['nodes' => $nodes, 'error' => $error]);
    exit;
}

if (empty($parent_ids) || !is_array($parent_ids) || !$target_depth) {
    respond([]);
}

$parent_ids = array_filter(array_map('intval', $parent_ids));
if (empty($parent_ids)) {
    respond([]);
}

$db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bdm_id INT NOT NULL,
    location_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bdm_location (bdm_id, location_id)
)");

$parentIdList = implode(',', $parent_ids);
$stmt_n = $db_conn->prepare("
    SELECT id, name FROM partner_location_nodes
    WHERE depth = ? AND is_active = 1 AND parent_id IN ($parentIdList)
    ORDER BY name ASC
");
$stmt_n->bind_param("i", $target_depth);
$stmt_n->execute();
$nodes = $stmt_n->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_n->close();

if (empty($nodes)) {
    respond([]);
}

$ids = implode(',', array_map('intval', array_column($nodes, 'id')));
$taken_map = [];
$res = $db_conn->query("SELECT location_id, bdm_id FROM salesbdm_locations WHERE location_id IN ($ids)");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $row_bdm_id = (int)$r['bdm_id'];
        if ($row_bdm_id !== $exclude_bdm_id) {
            $taken_map[(int)$r['location_id']] = true;
        }
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
