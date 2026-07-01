<?php
/**
 * geo_layers.php
 * Loads partner_location_nodes filtered to this TP's assigned territory.
 * Outputs: $geoNodes (array), $layers (array), $maxDepth (int)
 * Requires: $db_conn, $Login_user_IDvl already set.
 */

$_tp_id = (int)$Login_user_IDvl;

// TP's assigned location node IDs
$_stmtAss = $db_conn->prepare(
    "SELECT location_id FROM territory_partner_locations WHERE territory_partner_id=?"
);
$_stmtAss->bind_param('i', $_tp_id);
$_stmtAss->execute();
$_assignedIds = array_column($_stmtAss->get_result()->fetch_all(MYSQLI_ASSOC), 'location_id');
$_stmtAss->close();

// All active nodes
$_allNodes = [];
$_res = $db_conn->query(
    "SELECT id, parent_id, depth, name FROM partner_location_nodes WHERE is_active=1"
);
while ($_row = $_res->fetch_assoc()) {
    $_allNodes[(int)$_row['id']] = [
        'id'        => (int)$_row['id'],
        'parent_id' => $_row['parent_id'] !== null ? (int)$_row['parent_id'] : null,
        'depth'     => (int)$_row['depth'],
        'name'      => $_row['name'],
    ];
}

// For each assigned node: collect ancestors + self + all descendants
$_allowed = [];
foreach ($_assignedIds as $_locId) {
    $_locId = (int)$_locId;
    if (!isset($_allNodes[$_locId])) continue;

    // Walk up to root
    $_cur = $_locId;
    while ($_cur !== null && isset($_allNodes[$_cur])) {
        $_allowed[$_cur] = true;
        $_cur = $_allNodes[$_cur]['parent_id'];
    }

    // BFS downward
    $_q = [$_locId];
    while (!empty($_q)) {
        $_c = array_shift($_q);
        $_allowed[$_c] = true;
        foreach ($_allNodes as $_id => $_nd) {
            if ($_nd['parent_id'] === $_c) $_q[] = $_id;
        }
    }
}

// Filtered nodes at depth >= 2
$geoNodes = [];
foreach ($_allNodes as $_id => $_nd) {
    if ($_nd['depth'] >= 2 && isset($_allowed[$_id])) {
        $geoNodes[] = $_nd;
    }
}

// Layer definitions depth >= 2
$layers = [];
$_lr = $db_conn->query(
    "SELECT depth, layer_name FROM partner_location_layers WHERE depth >= 2 ORDER BY depth ASC"
);
while ($_row = $_lr->fetch_assoc()) {
    $layers[] = ['depth' => (int)$_row['depth'], 'layer_name' => $_row['layer_name']];
}
$maxDepth = !empty($layers) ? max(array_column($layers, 'depth')) : 3;

// depth → shop table column name
$depthToField = [2 => 'state_id', 3 => 'district_id', 4 => 'taluk_id', 5 => 'firka_id'];
