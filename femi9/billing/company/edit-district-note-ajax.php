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
$source = ($_POST['source'] ?? 'bdm') === 'tp' ? 'tp' : 'bdm';
$issueText = trim($_POST['issue_text'] ?? '');
$priority = $_POST['priority'] ?? 'normal';
$noteType = $_POST['note_type'] ?? 'tp';

if ($id <= 0 || $issueText === '') {
    respond(false, 'Describe the issue before saving.');
}
if (!in_array($priority, ['high', 'priority', 'normal'], true)) { $priority = 'normal'; }
if (!in_array($noteType, ['software', 'tp'], true)) { $noteType = 'tp'; }

if ($source === 'tp') {
    // tp_district_notes is the same shape as salesbdm_district_notes — its
    // own updateDistrictNote() can't be require_once'd here too (identical
    // function name), so this updates it directly instead.
    $db_conn->query("CREATE TABLE IF NOT EXISTS tp_district_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tp_id INT NOT NULL,
        district VARCHAR(150) NOT NULL,
        note_type ENUM('software','tp') NOT NULL DEFAULT 'tp',
        issue_text TEXT NOT NULL,
        priority ENUM('high','priority','normal') NOT NULL DEFAULT 'normal',
        photo_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_tp_created (tp_id, created_at),
        KEY idx_district (district),
        KEY idx_priority (priority),
        KEY idx_note_type (note_type)
    )");
    $chk = $db_conn->prepare("SELECT id FROM tp_district_notes WHERE id = ?");
    $chk->bind_param('i', $id);
    $chk->execute();
    $exists = (bool)$chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$exists) { respond(false, 'Could not save — this note may no longer exist.'); }

    $stmt = $db_conn->prepare("UPDATE tp_district_notes SET issue_text = ?, priority = ?, note_type = ? WHERE id = ?");
    $stmt->bind_param('sssi', $issueText, $priority, $noteType, $id);
    $stmt->execute();
    $ok = $stmt->error === '';
    $stmt->close();

    respond($ok, $ok ? '' : 'Could not save — this note may no longer exist.');
}

ensureDistrictNotesTable($db_conn);

// Company can edit any BDM's note — no ownership restriction, unlike the
// BDM's own edit-district-note-ajax.php.
$ok = updateDistrictNote($db_conn, $id, null, $issueText, $priority, $noteType);

respond($ok, $ok ? '' : 'Could not save — this note may no longer exist.');
