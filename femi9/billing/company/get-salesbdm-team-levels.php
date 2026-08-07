<?php
include("checksession.php");
require_once("include/TeamLevelColors.php");
header('Content-Type: application/json');
error_reporting(0);

$db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_team_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_rank INT NOT NULL,
    level_name VARCHAR(50) NOT NULL,
    location_layer_id INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_level_rank (level_rank)
)");

$colorMap = getSalesBdmTeamLevelColorMap($db_conn);

// layer_depth is included so the client can tell whether a level's own layer
// has a "next depth" layer available (e.g. State -> District) — that's what
// powers the optional "also directly handle locations one level down" picker
// on the Add/Edit form for someone holding a higher-rank level (e.g. Chief BDM).
$res = $db_conn->query("
    SELECT l.id, l.level_rank, l.level_name, ll.depth AS layer_depth
    FROM salesbdm_team_levels l
    LEFT JOIN partner_location_layers ll ON ll.id = l.location_layer_id
    ORDER BY l.level_rank ASC
");
$levels = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $levels[] = [
            'id'          => (int)$row['id'],
            'rank'        => (int)$row['level_rank'],
            'name'        => $row['level_name'],
            'color'       => $colorMap[(int)$row['id']] ?? '#667eea',
            'layer_depth' => $row['layer_depth'] !== null ? (int)$row['layer_depth'] : null,
        ];
    }
}

echo json_encode($levels);
