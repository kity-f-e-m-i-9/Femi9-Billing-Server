<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
require_once __DIR__ . '/../salesbdm/include/DistrictNotes.php';
header('Content-Type: application/json');
error_reporting(0);

function respond(bool $ok, string $message = ''): void
{
    echo json_encode(['success' => $ok, 'message' => $message]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$issueText = trim($_POST['issue_text'] ?? '');
$priority = $_POST['priority'] ?? 'normal';
$noteType = $_POST['note_type'] ?? 'tp';

if ($id <= 0 || $issueText === '') {
    respond(false, 'Describe the issue before saving.');
}
if (!in_array($priority, ['high', 'priority', 'normal'], true)) { $priority = 'normal'; }
if (!in_array($noteType, ['software', 'tp'], true)) { $noteType = 'tp'; }

ensureDistrictNotesTable($db_conn);

// Company can edit any BDM's note — no ownership restriction, unlike the
// BDM's own edit-district-note-ajax.php.
$ok = updateDistrictNote($db_conn, $id, null, $issueText, $priority, $noteType);

respond($ok, $ok ? '' : 'Could not save — this note may no longer exist.');
