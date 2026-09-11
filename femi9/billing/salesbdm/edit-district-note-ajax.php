<?php
include("checksession.php");
include("config.php");
require_once("include/DistrictNotes.php");
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

// A BDM may only edit their own notes — never trust the posted id alone.
$ok = updateDistrictNote($db_conn, $id, (int)$salesBdmID, $issueText, $priority, $noteType);

respond($ok, $ok ? '' : 'Could not save — this note may not belong to you, or no longer exists.');
