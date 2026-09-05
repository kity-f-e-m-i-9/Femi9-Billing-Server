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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_bdm_created (bdm_id, created_at),
        KEY idx_district (district),
        KEY idx_priority (priority)
    )");
}

function districtNotePriorityLabel(string $priority): string {
    switch ($priority) {
        case 'high': return 'High Priority';
        case 'priority': return 'Priority';
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
