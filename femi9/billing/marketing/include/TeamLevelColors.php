<?php
// Shared categorical color palette for Marketing Team Levels (SM/ASM/DM...).
// Mirrors company/include/TeamLevelColors.php so a level reads as the same
// color in both the company admin panel and this marketing self-service app.

function getTeamLevelColorPalette(): array {
    return ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
}

function getTeamLevelColorMap($db_conn): array {
    $palette = getTeamLevelColorPalette();
    $map = [];
    $res = $db_conn->query("SELECT id FROM marketing_team_levels ORDER BY level_rank ASC");
    if ($res) {
        $i = 0;
        while ($row = $res->fetch_assoc()) {
            $map[(int)$row['id']] = $palette[$i % count($palette)];
            $i++;
        }
    }
    return $map;
}
