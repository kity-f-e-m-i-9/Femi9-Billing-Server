<?php
include("checksession.php");
include("config.php");
require_once("include/DistrictNotes.php");
error_reporting(0);
header('Content-Type: application/json');

ensureNoteStatusColumn($db_conn);

$id     = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
if (!in_array($status, ['in_progress', 'completed'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

// A BDM can only move their own notes — never trust the posted id alone.
$stmt = $db_conn->prepare("UPDATE salesbdm_district_notes SET status = ? WHERE id = ? AND bdm_id = ?");
$stmt->bind_param('sii', $status, $id, $salesBdmID);
$stmt->execute();
$ok = $stmt->affected_rows > 0;
$stmt->close();

echo json_encode(['success' => $ok]);
