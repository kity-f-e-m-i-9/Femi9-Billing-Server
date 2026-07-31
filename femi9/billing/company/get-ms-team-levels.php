<?php
include("checksession.php");
require_once("include/TeamLevelColors.php");
header('Content-Type: application/json');
error_reporting(0);

$db_conn->query("CREATE TABLE IF NOT EXISTS marketing_team_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_rank INT NOT NULL,
    level_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_level_rank (level_rank)
)");

$colorMap = getTeamLevelColorMap($db_conn);

$res = $db_conn->query("SELECT id, level_rank, level_name FROM marketing_team_levels ORDER BY level_rank ASC");
$levels = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $levels[] = [
            'id'    => (int)$row['id'],
            'rank'  => (int)$row['level_rank'],
            'name'  => $row['level_name'],
            'color' => $colorMap[(int)$row['id']] ?? '#667eea',
        ];
    }
}

echo json_encode($levels);
