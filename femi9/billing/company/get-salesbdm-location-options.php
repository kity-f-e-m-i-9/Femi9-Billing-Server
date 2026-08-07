<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$team_level_id  = (int)($_GET['team_level_id'] ?? 0);
$manager_id     = (int)($_GET['manager_id'] ?? 0);
$exclude_bdm_id = (int)($_GET['exclude_bdm_id'] ?? 0);

function respond($nodes, $needsManager = false, $error = null) {
    echo json_encode(['nodes' => $nodes, 'needs_manager' => $needsManager, 'error' => $error]);
    exit;
}

if (!$team_level_id) {
    respond([], false, 'no_level');
}

$db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_team_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_rank INT NOT NULL,
    level_name VARCHAR(50) NOT NULL,
    location_layer_id INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_level_rank (level_rank)
)");
$db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bdm_id INT NOT NULL,
    location_id INT NOT NULL,
    is_dual_role TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bdm_location (bdm_id, location_id)
)");
$_chkDualR = $db_conn->query("SHOW COLUMNS FROM salesbdm_locations LIKE 'is_dual_role'");
if ($_chkDualR && $_chkDualR->num_rows === 0) {
    $db_conn->query("ALTER TABLE salesbdm_locations ADD COLUMN is_dual_role TINYINT(1) NOT NULL DEFAULT 0 AFTER location_id");
}

$stmt_lvl = $db_conn->prepare("SELECT id, level_rank, location_layer_id FROM salesbdm_team_levels WHERE id = ?");
$stmt_lvl->bind_param("i", $team_level_id);
$stmt_lvl->execute();
$level = $stmt_lvl->get_result()->fetch_assoc();
$stmt_lvl->close();

if (!$level || !$level['location_layer_id']) {
    respond([], false, 'no_layer_linked');
}

$layer_id = (int)$level['location_layer_id'];
$_chkSbdm = $db_conn->query("SHOW COLUMNS FROM partner_location_layers LIKE 'is_salesbdm_filter_enabled'");
if ($_chkSbdm && $_chkSbdm->num_rows === 0) {
    $db_conn->query("ALTER TABLE partner_location_layers ADD COLUMN is_salesbdm_filter_enabled TINYINT(1) NOT NULL DEFAULT 0");
}
$stmt_layer = $db_conn->prepare("SELECT id, depth, is_salesbdm_filter_enabled FROM partner_location_layers WHERE id = ?");
$stmt_layer->bind_param("i", $layer_id);
$stmt_layer->execute();
$layer = $stmt_layer->get_result()->fetch_assoc();
$stmt_layer->close();

if (!$layer) {
    respond([], false, 'no_layer_linked');
}

if (!$layer['is_salesbdm_filter_enabled']) {
    respond([], false, 'layer_not_enabled');
}

$target_depth = (int)$layer['depth'];

// Is this the top-most configured team level (no level above it)?
$stmt_top = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM salesbdm_team_levels WHERE level_rank < ?");
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
    // Manager's own assigned locations may sit at the SAME depth as this level,
    // or at the depth ABOVE (their manager holds the parent node) — match either.
    $stmt_n = $db_conn->prepare("
        SELECT pln.id, pln.name
        FROM partner_location_nodes pln
        WHERE pln.depth = ?
          AND pln.is_active = 1
          AND (
              pln.id IN (SELECT location_id FROM salesbdm_locations WHERE bdm_id = ?)
              OR pln.parent_id IN (SELECT location_id FROM salesbdm_locations WHERE bdm_id = ?)
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

// The manager's own row is expected to overlap here (they hold the parent, or
// the same node) — don't count that as "taken" UNLESS it's a dual-role row,
// which means the manager has personally claimed that exact node for
// themselves and it must stay locked out from subordinates.
$taken_map = [];
$res = $db_conn->query("SELECT location_id, bdm_id, is_dual_role FROM salesbdm_locations WHERE location_id IN ($ids)");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $row_bdm_id = (int)$r['bdm_id'];
        if ($row_bdm_id === $exclude_bdm_id) {
            continue;
        }
        if ((int)$r['is_dual_role'] === 1 || $row_bdm_id !== $manager_id) {
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
