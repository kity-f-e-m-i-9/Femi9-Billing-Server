<?php
include("checksession.php");
include("config.php");
require_once("include/DistrictNotes.php");
error_reporting(0);
header('Content-Type: application/json');

ensureNoteStatusColumn($db_conn);
ensureResolutionNoteColumn($db_conn);

$id     = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
if (!in_array($status, ['in_progress', 'completed'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

// Completing a note requires saying what was actually done about it — not
// optional, so "Completed" never means a silent status flip with no record
// of the resolution.
$resolutionNote = trim((string)($_POST['resolution_note'] ?? ''));
if ($status === 'completed') {
    if ($resolutionNote === '') {
        echo json_encode(['success' => false, 'message' => 'Describe what was done before marking this completed.']);
        exit;
    }
    if (mb_strlen($resolutionNote) > 500) { $resolutionNote = mb_substr($resolutionNote, 0, 500); }
}

// A BDM can only move their own notes — never trust the posted id alone.
if ($status === 'completed') {
    $stmt = $db_conn->prepare("UPDATE salesbdm_district_notes SET status = ?, resolution_note = ? WHERE id = ? AND bdm_id = ?");
    $stmt->bind_param('ssii', $status, $resolutionNote, $id, $salesBdmID);
} else {
    $stmt = $db_conn->prepare("UPDATE salesbdm_district_notes SET status = ? WHERE id = ? AND bdm_id = ?");
    $stmt->bind_param('sii', $status, $id, $salesBdmID);
}
$stmt->execute();
$ok = $stmt->affected_rows > 0;
$stmt->close();

echo json_encode(['success' => $ok]);
