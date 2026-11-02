<?php
// Step 4 of the drill-down — for one Firka, report the currently assigned
// Territory Partner (if any; null means "Vacant Area"), plus the full list
// of active TPs so the admin can assign/reassign one from the dropdown.
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$firka_id = (int)($_GET['firka_id'] ?? 0);
if (!$firka_id) {
    echo json_encode(['assigned_tp_id' => null, 'assigned_tp_name' => null, 'all_tps' => []]);
    exit;
}

$assignedTpId = null;
$assignedTpName = null;
$stmt = $db_conn->prepare("
    SELECT tp.id, tp.name
    FROM territory_partner_locations tpl
    JOIN territory_partners tp ON tp.id = tpl.territory_partner_id
    WHERE tpl.location_id = ? AND tp.is_active = 1 AND tp.deleted_at IS NULL
    LIMIT 1
");
$stmt->bind_param('i', $firka_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($row) {
    $assignedTpId = (int)$row['id'];
    $assignedTpName = $row['name'];
}

$allTps = [];
$res = $db_conn->query("SELECT id, name FROM territory_partners WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC");
while ($r = $res->fetch_assoc()) {
    $allTps[] = ['id' => (int)$r['id'], 'name' => $r['name']];
}

echo json_encode([
    'assigned_tp_id'   => $assignedTpId,
    'assigned_tp_name' => $assignedTpName,
    'all_tps'          => $allTps,
]);
