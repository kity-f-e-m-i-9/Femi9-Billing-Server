<?php
// Company-only delete for a single row on daily-assign-report.php
// (tp_daily_assignments) -- 2026-11-30, user request ("intha page la delete
// pannura mathiri venum ... ithula company login ku mattum than venum").
// Deliberately NOT exposed on the marketing side -- same restriction pattern
// as release-dm-worklock-action.php. tp_daily_assignments has no deleted_at
// column (see tp-daily-assignment-schema.sql) and no other table has a
// foreign key into its id, so a plain hard delete is safe here.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms_edit');
header('Content-Type: application/json');
error_reporting(0);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$stmt = $db_conn->prepare("DELETE FROM tp_daily_assignments WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$deleted = $stmt->affected_rows > 0;
$stmt->close();

echo json_encode(['success' => $deleted, 'error' => $deleted ? null : 'Entry not found']);
