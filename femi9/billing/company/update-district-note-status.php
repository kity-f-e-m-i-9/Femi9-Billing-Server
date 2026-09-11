<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
require_once __DIR__ . '/../salesbdm/include/DistrictNotes.php';
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

// Company oversees every BDM's notes — no bdm_id ownership restriction here,
// unlike the BDM's own update-note-status.php.
if ($status === 'completed') {
    $stmt = $db_conn->prepare("UPDATE salesbdm_district_notes SET status = ?, resolution_note = ? WHERE id = ?");
    $stmt->bind_param('ssi', $status, $resolutionNote, $id);
} else {
    $stmt = $db_conn->prepare("UPDATE salesbdm_district_notes SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
}
$stmt->execute();
$ok = $stmt->affected_rows > 0;
$stmt->close();

echo json_encode(['success' => $ok]);
