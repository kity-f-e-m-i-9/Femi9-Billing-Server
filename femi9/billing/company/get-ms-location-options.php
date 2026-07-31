<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$team_level_id = (int)($_GET['team_level_id'] ?? 0);
$manager_id    = (int)($_GET['manager_id'] ?? 0);
$exclude_ms_id = (int)($_GET['exclude_ms_id'] ?? 0);

function respond($nodes, $needsManager = false, $error = null) {
    echo json_encode(['nodes' => $nodes, 'needs_manager' => $needsManager, 'error' => $error]);
    exit;
}

if (!$team_level_id) {
    respond([], false, 'no_level');
}

$_chkLL = $db_conn->query("SHOW COLUMNS FROM marketing_team_levels LIKE 'location_layer_id'");
if ($_chkLL && $_chkLL->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_team_levels ADD COLUMN location_layer_id INT NULL DEFAULT NULL AFTER level_name");
}
$db_conn->query("CREATE TABLE IF NOT EXISTS marketing_staff_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ms_id INT NOT NULL,
    location_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ms_location (ms_id, location_id)
)");

$stmt_lvl = $db_conn->prepare("SELECT id, level_rank, location_layer_id FROM marketing_team_levels WHERE id = ?");
$stmt_lvl->bind_param("i", $team_level_id);
$stmt_lvl->execute();
$level = $stmt_lvl->get_result()->fetch_assoc();
$stmt_lvl->close();

if (!$level || !$level['location_layer_id']) {
    respond([], false, 'no_layer_linked');
}

$layer_id = (int)$level['location_layer_id'];
$stmt_layer = $db_conn->prepare("SELECT id, depth, is_ms_filter_enabled FROM partner_location_layers WHERE id = ?");
$stmt_layer->bind_param("i", $layer_id);
$stmt_layer->execute();
$layer = $stmt_layer->get_result()->fetch_assoc();
$stmt_layer->close();

if (!$layer) {
    respond([], false, 'no_layer_linked');
}

if (!$layer['is_ms_filter_enabled']) {
    respond([], false, 'layer_not_enabled');
}

$target_depth = (int)$layer['depth'];

// Is this the top-most configured team level (no level above it)?
$stmt_top = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM marketing_team_levels WHERE level_rank < ?");
$stmt_top->bind_param("i", $level['level_rank']);
$stmt_top->execute();
$is_top = ((int)$stmt_top->get_result()->fetch_assoc()['cnt']) === 0;
$stmt_top->close();

if (!$is_top && !$manager_id) {
    respond([], true);
}

if ($is_top) {
    $stmt_n = $db_conn->prepare("SELECT id, name FROM partner_location_nodes WHERE depth = ? AND is_active = 1 ORDER BY name ASC");
    $stmt_n->bind_param("i", $target_depth);
    $stmt_n->execute();
} else {
    // Manager's own assigned locations may sit at the SAME depth as this level
    // (e.g. ASM and DM both pick from the District layer — DM must stay within
    // the specific districts already assigned to their ASM), or at the depth
    // ABOVE (e.g. ASM's manager SM is assigned the whole State — ASM must stay
    // within districts that belong to that State). Match either case.
    $stmt_n = $db_conn->prepare("
        SELECT pln.id, pln.name
        FROM partner_location_nodes pln
        WHERE pln.depth = ?
          AND pln.is_active = 1
          AND (
              pln.id IN (SELECT location_id FROM marketing_staff_locations WHERE ms_id = ?)
              OR pln.parent_id IN (SELECT location_id FROM marketing_staff_locations WHERE ms_id = ?)
          )
        ORDER BY pln.name ASC
    ");
    $stmt_n->bind_param("iii", $target_depth, $manager_id, $manager_id);
    $stmt_n->execute();
}
$nodes = $stmt_n->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_n->close();

if (empty($nodes)) {
    respond([], false);
}

$ids = implode(',', array_map('intval', array_column($nodes, 'id')));

// The manager's own row is expected to overlap here (that's how a district
// gets offered to their DM in the first place) — don't count it as "taken".
$taken_map = [];
$res = $db_conn->query("SELECT location_id, ms_id FROM marketing_staff_locations WHERE location_id IN ($ids)");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $row_ms_id = (int)$r['ms_id'];
        if ($row_ms_id !== $exclude_ms_id && $row_ms_id !== $manager_id) {
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

respond($result, false);
