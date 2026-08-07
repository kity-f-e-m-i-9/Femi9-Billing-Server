<?php
// Shared categorical color palette for Marketing Team Levels (SM/ASM/DM...).
// One color per level, assigned by rank order — kept identical everywhere
// (Manage Team Levels, Add/Edit Marketing Staff, Marketing Staff View) so a
// level always reads as the same color across the app.

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

// Same idea, same palette, for Sales BDM Team Levels — kept as a separate
// function (not shared state) since it's a completely independent hierarchy.
function getSalesBdmTeamLevelColorMap($db_conn): array {
    $palette = getTeamLevelColorPalette();
    $map = [];
    $res = $db_conn->query("SELECT id FROM salesbdm_team_levels ORDER BY level_rank ASC");
    if ($res) {
        $i = 0;
        while ($row = $res->fetch_assoc()) {
            $map[(int)$row['id']] = $palette[$i % count($palette)];
            $i++;
        }
    }
    return $map;
}
