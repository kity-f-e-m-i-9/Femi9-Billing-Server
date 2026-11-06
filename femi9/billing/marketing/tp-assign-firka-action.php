<?php
// Marketing-side twin of company/tp-assign-firka-action.php. The write path
// is the one place this feature can't just trust the client: firka_id is
// re-checked server-side against the union of Districts assigned to the
// logged-in ASM/DM's own subtree (self + every subordinate), so this can
// never save against a Firka outside their team's territory even if the
// client-side dropdowns were bypassed.
require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/TeamSubtree.php';
header('Content-Type: application/json');
error_reporting(0);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$firka_id    = (int)($_POST['firka_id'] ?? 0);
$tp_id       = isset($_POST['tp_id']) && $_POST['tp_id'] !== '' ? (int)$_POST['tp_id'] : null;
$assign_date = $_POST['assign_date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $assign_date)) {
    $assign_date = date('Y-m-d');
}
$assigned_by = $_SESSION['LOGIN_USER_NAME'] ?? '';

if (!$firka_id) {
    echo json_encode(['success' => false, 'error' => 'firka_id required']);
    exit;
}

// ── Scope check ──────────────────────────────────────────────────────────
$loginMsId = (int)$Login_user_IDvl;
$subtreeIds = getMsSubtreeIds($db_conn, $loginMsId);
$allowedDistrictIds = [];
foreach ($subtreeIds as $sid) {
    foreach (getMsAssignedDistricts($db_conn, $sid) as $d) {
        $allowedDistrictIds[(int)$d['id']] = true;
    }
}

$stmtDist = $db_conn->prepare("
    SELECT d.id
    FROM partner_location_nodes f
    JOIN partner_location_nodes t  ON t.id = f.parent_id
    JOIN partner_location_nodes dv ON dv.id = t.parent_id
    JOIN partner_location_nodes d  ON d.id = dv.parent_id
    WHERE f.id = ?
");
$stmtDist->bind_param('i', $firka_id);
$stmtDist->execute();
$distRow = $stmtDist->get_result()->fetch_assoc();
$stmtDist->close();
$firkaDistrictId = $distRow ? (int)$distRow['id'] : null;

if ($firkaDistrictId === null || !isset($allowedDistrictIds[$firkaDistrictId])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'This Firka is outside your team\'s territory.']);
    exit;
}

if ($tp_id === null) {
    // "Vacant Area" selected — clear any existing assignment for this firka.
    $stmt = $db_conn->prepare("DELETE FROM territory_partner_locations WHERE location_id = ?");
    $stmt->bind_param('i', $firka_id);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $db_conn->prepare("
        INSERT INTO territory_partner_locations (territory_partner_id, location_id)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE territory_partner_id = VALUES(territory_partner_id)
    ");
    $stmt->bind_param('ii', $tp_id, $firka_id);
    $stmt->execute();
    $stmt->close();
}

// Daily history log — same table the company page writes to, so both
// pages' assignments show up together on daily-assign-report.php.
$stmt = $db_conn->prepare("
    INSERT INTO tp_daily_assignments (assign_date, location_id, territory_partner_id, assigned_by)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE territory_partner_id = VALUES(territory_partner_id), assigned_by = VALUES(assigned_by)
");
$stmt->bind_param('siis', $assign_date, $firka_id, $tp_id, $assigned_by);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'assigned_tp_id' => $tp_id]);
