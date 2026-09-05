<?php
// District Notes — a Sales BDM's own field-issue log, one note per district
// with a priority and an optional photo, reviewed later on Manage Notes
// (date/district/priority filtered). Self-migrating like every other table
// in this codebase.

function ensureDistrictNotesTable($db_conn): void {
    $db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_district_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bdm_id INT NOT NULL,
        district VARCHAR(150) NOT NULL,
        issue_text TEXT NOT NULL,
        priority ENUM('high','priority','normal') NOT NULL DEFAULT 'normal',
        photo_path VARCHAR(255) NULL,
        tp_names TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_bdm_created (bdm_id, created_at),
        KEY idx_district (district),
        KEY idx_priority (priority)
    )");
    // Added after the table already existed on some installs — self-migrate
    // rather than assume every environment recreated it from scratch.
    $col = $db_conn->query("SHOW COLUMNS FROM salesbdm_district_notes LIKE 'tp_names'");
    if ($col && $col->num_rows === 0) {
        $db_conn->query("ALTER TABLE salesbdm_district_notes ADD COLUMN tp_names TEXT NULL AFTER photo_path");
    }
    $col2 = $db_conn->query("SHOW COLUMNS FROM salesbdm_district_notes LIKE 'note_type'");
    if ($col2 && $col2->num_rows === 0) {
        $db_conn->query("ALTER TABLE salesbdm_district_notes ADD COLUMN note_type ENUM('software','tp') NOT NULL DEFAULT 'tp' AFTER district");
        $db_conn->query("ALTER TABLE salesbdm_district_notes ADD KEY idx_note_type (note_type)");
    }
}

// A BDM can "fix" a district so it auto-fills every time they add a note,
// instead of re-picking it from the list on every single save — persisted on
// their own staff row (not the session) so it survives logging out and back
// in, same as any other durable per-BDM preference in this codebase.
function ensureFixedDistrictColumn($db_conn): void {
    $col = $db_conn->query("SHOW COLUMNS FROM sales_bdm_staff LIKE 'fixed_note_district'");
    if ($col && $col->num_rows === 0) {
        $db_conn->query("ALTER TABLE sales_bdm_staff ADD COLUMN fixed_note_district VARCHAR(150) NULL DEFAULT NULL AFTER bdm_mobile");
    }
}

// Stored as a comma-separated list — a BDM can fix more than one district at
// once (an issue that spans districts shouldn't force two separate saves).
// Returns [] when nothing is fixed, never null, so callers can always loop.
function getFixedNoteDistricts($db_conn, int $bdmId): array {
    $stmt = $db_conn->prepare("SELECT fixed_note_district FROM sales_bdm_staff WHERE id = ?");
    $stmt->bind_param('i', $bdmId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $val = trim($row['fixed_note_district'] ?? '');
    if ($val === '') { return []; }
    return array_values(array_filter(array_map('trim', explode(',', $val))));
}

function setFixedNoteDistricts($db_conn, int $bdmId, array $districts): void {
    $val = empty($districts) ? null : implode(',', $districts);
    $stmt = $db_conn->prepare("UPDATE sales_bdm_staff SET fixed_note_district = ? WHERE id = ?");
    $stmt->bind_param('si', $val, $bdmId);
    $stmt->execute();
    $stmt->close();
}

// Workflow status — starts 'open', a BDM (or company, on the oversight page)
// moves it to 'in_progress' then 'completed' as the field issue gets worked.
function ensureNoteStatusColumn($db_conn): void {
    $col = $db_conn->query("SHOW COLUMNS FROM salesbdm_district_notes LIKE 'status'");
    if ($col && $col->num_rows === 0) {
        $db_conn->query("ALTER TABLE salesbdm_district_notes ADD COLUMN status ENUM('open','in_progress','completed') NOT NULL DEFAULT 'open' AFTER priority");
        $db_conn->query("ALTER TABLE salesbdm_district_notes ADD KEY idx_status (status)");
    }
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
    return $type === 'software' ? 'Software Issue' : 'TPs Issue';
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
