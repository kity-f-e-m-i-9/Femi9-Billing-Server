<?php
// Saves (or clears) which Territory Partner covers one Firka FOR THAT
// CALENDAR DATE ONLY, from the "Manage Daily Assign District TPs"
// drill-down page. This is a temporary day-by-day roster, kept solely in
// tp_daily_assignments — it never writes to territory_partner_locations,
// which is that TP's own PERMANENT Firka ownership (used everywhere else,
// e.g. Manage Territory Partner / Monthly Target). Until 2026-11-11 this
// endpoint also wrote the permanent table on every save, so a company/ASM
// using this page daily was silently reassigning Firkas (and the target
// amount that comes with them) between TPs for good, every single time —
// not the "just today's roster" the page's own name implies. Permanent
// reassignment still happens, just only through the actual TP edit page.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms_edit');
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

// One row per (date, firka) — this IS the record now, not just a history
// log of some other live mapping. NULL territory_partner_id means "Vacant
// Area" for that date.
$stmt = $db_conn->prepare("
    INSERT INTO tp_daily_assignments (assign_date, location_id, territory_partner_id, assigned_by)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE territory_partner_id = VALUES(territory_partner_id), assigned_by = VALUES(assigned_by)
");
$stmt->bind_param('siis', $assign_date, $firka_id, $tp_id, $assigned_by);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'assigned_tp_id' => $tp_id]);
