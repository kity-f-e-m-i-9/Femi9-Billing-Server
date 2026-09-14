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
$source = ($_POST['source'] ?? 'bdm') === 'tp' ? 'tp' : 'bdm';
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

// tp_district_notes' status/resolution_note columns are the same
// self-migrating shape as salesbdm_district_notes' — ensured here directly
// rather than require_once'ing territory-partner/include/DistrictNotes.php,
// whose function names collide with the ones already loaded above.
if ($source === 'tp') {
    $tpCol = $db_conn->query("SHOW COLUMNS FROM tp_district_notes LIKE 'status'");
    if ($tpCol && $tpCol->num_rows === 0) {
        $db_conn->query("ALTER TABLE tp_district_notes ADD COLUMN status ENUM('open','in_progress','completed') NOT NULL DEFAULT 'open' AFTER priority");
    }
    $tpCol2 = $db_conn->query("SHOW COLUMNS FROM tp_district_notes LIKE 'resolution_note'");
    if ($tpCol2 && $tpCol2->num_rows === 0) {
        $db_conn->query("ALTER TABLE tp_district_notes ADD COLUMN resolution_note VARCHAR(500) NULL AFTER status");
    }
}

$table = $source === 'tp' ? 'tp_district_notes' : 'salesbdm_district_notes';

// Company oversees every BDM's AND every TP's notes — no ownership
// restriction here, unlike each role's own update-note-status.php.
if ($status === 'completed') {
    $stmt = $db_conn->prepare("UPDATE $table SET status = ?, resolution_note = ? WHERE id = ?");
    $stmt->bind_param('ssi', $status, $resolutionNote, $id);
} else {
    $stmt = $db_conn->prepare("UPDATE $table SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
}
$stmt->execute();
$ok = $stmt->affected_rows > 0;
$stmt->close();

echo json_encode(['success' => $ok]);
