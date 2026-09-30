<?php
// Historical is_active tracking for territory_partners — the table itself
// has no audit trail of when is_active flipped, only created_at/deleted_at
// (true point-in-time facts) plus a single current is_active flag with no
// history behind it. Every future is_active change is now logged here so
// date-scoped reports (e.g. Our Team Report's "Active TPs" column) can ask
// "was this TP active as of date X" instead of always showing today's live
// status regardless of the selected month/date range. Dates before this
// table existed have no log rows for a given TP, so getTpActiveCountAsOf()
// falls back to that TP's CURRENT is_active for those — same behavior as
// before this fix, just for the untracked period; there is no way to
// reconstruct is_active history from before this table started logging.

function ensureTpStatusLogTable($db_conn): void {
    static $done = false;
    if ($done) return;
    $db_conn->query("
        CREATE TABLE IF NOT EXISTS territory_partner_status_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            territory_partner_id INT UNSIGNED NOT NULL,
            old_is_active TINYINT(1) NOT NULL,
            new_is_active TINYINT(1) NOT NULL,
            changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tp_changed (territory_partner_id, changed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $done = true;
}

// Call this right after any successful UPDATE that can change
// territory_partners.is_active — logs a row only when the value actually
// changed, so unrelated saves (e.g. editing address with the same status)
// don't pollute the history.
function logTpStatusChange($db_conn, int $tpId, int $oldStatus, int $newStatus): void {
    if ($oldStatus === $newStatus) return;
    ensureTpStatusLogTable($db_conn);
    $stmt = $db_conn->prepare("
        INSERT INTO territory_partner_status_log (territory_partner_id, old_is_active, new_is_active)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param('iii', $tpId, $oldStatus, $newStatus);
    $stmt->execute();
    $stmt->close();
}

// Among $tpIds, returns one row per TP that EXISTED as of $asOfDate (Y-m-d,
// treated as end-of-day) and wasn't yet soft-deleted then — each row
// carries a 'status_as_of' (0/1) computed from the most recent logged
// status change at-or-before that date, falling back to the TP's CURRENT
// is_active when there's no log row that far back. Powers both the
// Active-TPs count (getTpActiveCountAsOf) and the Active/Inactive TP list
// check pages (company + salesbdm tp-active-status-check.php).
function getTpStatusRowsAsOf($db_conn, array $tpIds, string $asOfDate): array {
    if (empty($tpIds)) return [];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate)) $asOfDate = date('Y-m-d');
    ensureTpStatusLogTable($db_conn);
    $idList = implode(',', array_map('intval', $tpIds));

    $stmt = $db_conn->prepare("
        SELECT tp.id, tp.name, tp.tp_id, tp.company_name,
               COALESCE(NULLIF(tp.assigned_district,''), tp.branch_district) AS district,
               tp.is_active AS current_is_active,
               (SELECT l.new_is_active FROM territory_partner_status_log l
                WHERE l.territory_partner_id = tp.id AND l.changed_at <= CONCAT(?, ' 23:59:59')
                ORDER BY l.changed_at DESC, l.id DESC LIMIT 1) AS logged_is_active
        FROM territory_partners tp
        WHERE tp.id IN ($idList)
          AND tp.created_at <= CONCAT(?, ' 23:59:59')
          AND (tp.deleted_at IS NULL OR tp.deleted_at > CONCAT(?, ' 23:59:59'))
        ORDER BY tp.name ASC
    ");
    $stmt->bind_param('sss', $asOfDate, $asOfDate, $asOfDate);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$r) {
        $r['status_as_of'] = $r['logged_is_active'] !== null ? (int)$r['logged_is_active'] : (int)$r['current_is_active'];
    }
    unset($r);
    return $rows;
}

// Among $tpIds, counts how many were "active" as of $asOfDate — see
// getTpStatusRowsAsOf() for exactly what "active as of" means here.
function getTpActiveCountAsOf($db_conn, array $tpIds, string $asOfDate): int {
    $count = 0;
    foreach (getTpStatusRowsAsOf($db_conn, $tpIds, $asOfDate) as $r) {
        if ($r['status_as_of'] === 1) $count++;
    }
    return $count;
}
