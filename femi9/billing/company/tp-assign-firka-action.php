<?php
// Saves (or clears) which Territory Partner covers one Firka, from the
// "Manage Daily Assign District TPs" drill-down page. territory_partner_
// locations.location_id is UNIQUE (tp_tables.sql) -- a firka maps to at
// most one TP -- so reassigning here just overwrites whoever had it.
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

// Daily history log — one row per (date, firka), kept even after the
// live mapping above is later overwritten, so past assignments can be
// looked up by date range / month / DM on the report page.
$stmt = $db_conn->prepare("
    INSERT INTO tp_daily_assignments (assign_date, location_id, territory_partner_id, assigned_by)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE territory_partner_id = VALUES(territory_partner_id), assigned_by = VALUES(assigned_by)
");
$stmt->bind_param('siis', $assign_date, $firka_id, $tp_id, $assigned_by);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'assigned_tp_id' => $tp_id]);
