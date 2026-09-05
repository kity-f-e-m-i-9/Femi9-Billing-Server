<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
require_once __DIR__ . '/../salesbdm/include/DistrictNotes.php';
error_reporting(0);
header('Content-Type: application/json');

ensureNoteStatusColumn($db_conn);

$id     = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
if (!in_array($status, ['in_progress', 'completed'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

// Company oversees every BDM's notes — no bdm_id ownership restriction here,
// unlike the BDM's own update-note-status.php.
$stmt = $db_conn->prepare("UPDATE salesbdm_district_notes SET status = ? WHERE id = ?");
$stmt->bind_param('si', $status, $id);
$stmt->execute();
$ok = $stmt->affected_rows > 0;
$stmt->close();

echo json_encode(['success' => $ok]);
