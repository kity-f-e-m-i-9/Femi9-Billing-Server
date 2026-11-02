<?php
// List all marketing staff (with team level name) for the "Manage Daily
// Assign District TPs" page's staff picker — not filtered to a single
// level, since the admin may want to drill into any ASM/DM/SM's assigned
// territory, not just DMs.
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$_chkDel = $db_conn->query("SHOW COLUMNS FROM marketing_staff LIKE 'deleted_at'");
if ($_chkDel && $_chkDel->num_rows === 0) {
    $db_conn->query("ALTER TABLE marketing_staff ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
}

$res = $db_conn->query("
    SELECT ms.id, ms.ms_name, tl.level_name
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
    WHERE ms.deleted_at IS NULL
    ORDER BY ms.ms_name ASC
");
$result = [];
while ($r = $res->fetch_assoc()) {
    $result[] = [
        'id'   => (int)$r['id'],
        'name' => $r['ms_name'] . ($r['level_name'] ? ' (' . $r['level_name'] . ')' : ''),
    ];
}
echo json_encode($result);
