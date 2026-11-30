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
$isPermanent = false;

// Has TODAY's roster been explicitly touched for this firka at all (whether
// that set a TP or explicitly cleared it to Vacant)? If so, that row IS the
// record — trust it as-is, same as before.
$stmtRow = $db_conn->prepare("SELECT id FROM tp_daily_assignments WHERE location_id = ? AND assign_date = ? LIMIT 1");
$stmtRow->bind_param('is', $firka_id, $assign_date);
$stmtRow->execute();
$hasRowToday = (bool)$stmtRow->get_result()->fetch_assoc();
$stmtRow->close();

if ($hasRowToday) {
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
} else {
    // Nothing recorded for today yet -- fall back to the TP's own PERMANENT
    // Firka ownership (territory_partner_locations), same fix as the
    // company-side version of this endpoint. 2026-11-20, user-reported.
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
        $isPermanent = true;
    }
}

$allTps = [];
$res = $db_conn->query("SELECT id, name FROM territory_partners WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC");
while ($r = $res->fetch_assoc()) {
    $allTps[] = ['id' => (int)$r['id'], 'name' => $r['name']];
}

echo json_encode([
    'assigned_tp_id'   => $assignedTpId,
    'assigned_tp_name' => $assignedTpName,
    'is_permanent'     => $isPermanent,
    'all_tps'          => $allTps,
]);
