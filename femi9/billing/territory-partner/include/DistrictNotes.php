<?php
// District Notes — a Territory Partner's own field-issue log, one note per
// district with a priority and an optional photo, reviewed later on Manage
// Notes (date/district/priority filtered). Mirrors
// salesbdm/include/DistrictNotes.php's salesbdm_district_notes table, just
// scoped to tp_district_notes/tp_id instead of bdm_id — a TP's own district
// list is resolved fresh every time from their real location assignments
// (see TpDistrictScope.php), so there's no "fix a district" pinning concept
// here the way there is for a BDM (nothing to fix — it's already just
// whatever their assignment says, usually exactly one district).

function ensureDistrictNotesTable($db_conn): void {
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
}

// Workflow status — starts 'open', the TP (or company, on the oversight
// page) moves it to 'in_progress' then 'completed' as the field issue gets
// worked.
function ensureNoteStatusColumn($db_conn): void {
    $col = $db_conn->query("SHOW COLUMNS FROM tp_district_notes LIKE 'status'");
    if ($col && $col->num_rows === 0) {
        $db_conn->query("ALTER TABLE tp_district_notes ADD COLUMN status ENUM('open','in_progress','completed') NOT NULL DEFAULT 'open' AFTER priority");
        $db_conn->query("ALTER TABLE tp_district_notes ADD KEY idx_status (status)");
    }
}

// What was actually done to resolve the issue — required at the moment a
// note is marked completed (not optional), so "Completed" always has an
// answer to "what did you do about it" instead of just a silent status flip.
function ensureResolutionNoteColumn($db_conn): void {
    $col = $db_conn->query("SHOW COLUMNS FROM tp_district_notes LIKE 'resolution_note'");
    if ($col && $col->num_rows === 0) {
        $db_conn->query("ALTER TABLE tp_district_notes ADD COLUMN resolution_note VARCHAR(500) NULL AFTER status");
    }
}

// Corrects the note's own content after the fact (a typo, wrong priority,
// wrong type) — deliberately leaves district/photo untouched, since changing
// those is a bigger structural edit than "fix what I typed", and leaves
// status/resolution_note alone too (that's its own separate workflow, not
// part of a content edit). $tpId is null for Company's own edit endpoint
// (if one is ever added), which may correct any TP's note; passed for the
// TP's own endpoint, which may only touch its own notes.
function updateDistrictNote($db_conn, int $id, ?int $tpId, string $issueText, string $priority, string $noteType): bool {
    if ($tpId !== null) {
        $chk = $db_conn->prepare("SELECT id FROM tp_district_notes WHERE id = ? AND tp_id = ?");
        $chk->bind_param('ii', $id, $tpId);
    } else {
        $chk = $db_conn->prepare("SELECT id FROM tp_district_notes WHERE id = ?");
        $chk->bind_param('i', $id);
    }
    $chk->execute();
    $exists = (bool)$chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$exists) { return false; }

    $stmt = $db_conn->prepare("UPDATE tp_district_notes SET issue_text = ?, priority = ?, note_type = ? WHERE id = ?");
    $stmt->bind_param('sssi', $issueText, $priority, $noteType, $id);
    $stmt->execute();
    $ok = $stmt->error === '';
    $stmt->close();
    return $ok;
}

function districtNoteStatusLabel(string $status): string {
    switch ($status) {
        case 'in_progress': return 'In Progress';
        case 'completed': return 'Completed';
        default: return 'Open';
    }
}

/** [background, text] colour pair for a status badge. */
function districtNoteStatusColors(string $status): array {
    switch ($status) {
        case 'in_progress': return ['#dbeafe', '#1e40af'];
        case 'completed': return ['#d1fae5', '#065f46'];
        default: return ['#f3f4f6', '#6b7280'];
    }
}

function districtNoteTypeLabel(string $type): string {
    return $type === 'software' ? 'Software Issue' : 'Field Issue';
}

function districtNotePriorityLabel(string $priority): string {
    switch ($priority) {
        case 'high': return 'High Priority';
        case 'priority': return 'Medium';
        default: return 'Normal';
    }
}

/** [background, text] colour pair for a priority badge. */
function districtNotePriorityColors(string $priority): array {
    switch ($priority) {
        case 'high': return ['#fee2e2', '#991b1b'];
        case 'priority': return ['#fef3c7', '#92400e'];
        default: return ['#e5e7eb', '#374151'];
    }
}
