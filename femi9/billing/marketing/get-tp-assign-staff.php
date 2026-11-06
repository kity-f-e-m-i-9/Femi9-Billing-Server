<?php
// Staff picker for the marketing-side "Manage Daily Assign District TPs" —
// unlike the company version (which lists everyone), this is scoped to the
// logged-in ASM/DM's own subordinates only (getMsSubtreeIds minus self), so
// an ASM can assign TPs for the DMs reporting to them but not for staff
// outside their team.
include("checksession.php");
require_once __DIR__ . '/include/TeamSubtree.php';
header('Content-Type: application/json');
error_reporting(0);

$msId = (int)$Login_user_IDvl;
$subtreeIds = array_values(array_diff(getMsSubtreeIds($db_conn, $msId), [$msId]));
if (empty($subtreeIds)) { echo json_encode([]); exit; }

$idList = implode(',', array_map('intval', $subtreeIds));
$res = $db_conn->query("
    SELECT ms.id, ms.ms_name, tl.level_name
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
    WHERE ms.id IN ($idList) AND ms.deleted_at IS NULL
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
