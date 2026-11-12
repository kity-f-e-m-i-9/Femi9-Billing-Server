<?php
// Step 4 of the marketing-side drill-down — who's on the temporary daily
// roster for one Firka ON THE SELECTED DATE (if anyone), plus the full
// active-TP list to assign/reassign from. Reads tp_daily_assignments, NOT
// territory_partner_locations (that TP's own separate, permanent Firka
// ownership) — this page only ever manages the day-by-day roster.
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

$firka_id = (int)($_GET['firka_id'] ?? 0);
$assign_date = $_GET['assign_date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $assign_date)) {
    $assign_date = date('Y-m-d');
}
if (!$firka_id) {
    echo json_encode(['assigned_tp_id' => null, 'assigned_tp_name' => null, 'all_tps' => []]);
    exit;
}

$assignedTpId = null;
$assignedTpName = null;
$stmt = $db_conn->prepare("
    SELECT tp.id, tp.name
    FROM tp_daily_assignments tda
    JOIN territory_partners tp ON tp.id = tda.territory_partner_id
    WHERE tda.location_id = ? AND tda.assign_date = ? AND tp.is_active = 1 AND tp.deleted_at IS NULL
    LIMIT 1
");
$stmt->bind_param('is', $firka_id, $assign_date);
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
