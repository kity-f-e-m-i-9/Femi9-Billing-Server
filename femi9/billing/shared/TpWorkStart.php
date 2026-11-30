<?php
// Daily "work start" confirmation chain, bolted onto tp_daily_assignments
// (the "Daily Assign District TPs" day-by-day Firka->TP roster):
//
//   1. DM sends a work-start request to the TP assigned to one of today's
//      Firkas ("Daily Work Start" page) -> tp_approval_status='requested'.
//   2. The TP's own portal (territory-partner/checksession.php) blocks
//      everything except a mandatory confirm page until they Accept or Deny
//      that request for today.
//        - Accept -> tp_approval_status='accepted', portal unlocks.
//        - Deny (with a required reason) -> tp_approval_status='denied',
//          portal stays blocked; the DM sees the reason and can escalate.
//   3. The DM can also skip the TP entirely and go straight to ASM/Company
//      ("Ask ASM/Company") — either after a TP's denial, or directly for a
//      Firka whose TP is known to be unavailable, or a Vacant Area —
//      -> company_request_status='pending'.
//   4. ASM/Company resolves the request by confirming (or re-assigning) a
//      TP, which sets that TP's tp_approval_status straight to 'accepted'
//      (company already vouched — the TP doesn't need to confirm again) and
//      company_request_status='resolved'.
//
// Columns are added lazily (self-migrating schema pattern, same as every
// other new column in this app) since the base table itself is a one-time-
// schema table that may not exist on every environment yet.
function tpWorkStartEnsureColumns($db_conn): void {
    $cols = [
        'tp_approval_status'     => "ENUM('none','requested','accepted','denied') NOT NULL DEFAULT 'none'",
        'tp_denial_reason'       => "TEXT NULL DEFAULT NULL",
        'tp_requested_at'        => "DATETIME NULL DEFAULT NULL",
        'tp_responded_at'        => "DATETIME NULL DEFAULT NULL",
        'company_request_status' => "ENUM('none','pending','resolved') NOT NULL DEFAULT 'none'",
        'company_request_note'   => "TEXT NULL DEFAULT NULL",
        'company_requested_at'   => "DATETIME NULL DEFAULT NULL",
        'company_resolved_by'    => "VARCHAR(100) NULL DEFAULT NULL",
        'company_resolved_at'    => "DATETIME NULL DEFAULT NULL",
    ];
    foreach ($cols as $col => $def) {
        $chk = $db_conn->query("SHOW COLUMNS FROM tp_daily_assignments LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $db_conn->query("ALTER TABLE tp_daily_assignments ADD COLUMN `$col` $def");
        }
    }
}

// Shared save path for both company/tp-assign-firka-action.php and
// marketing/tp-assign-firka-action.php -- the work-start chain is only
// reset back to 'none' when the TP actually changes (or this is a brand new
// row). Re-saving the SAME TP for the SAME (date, Firka) -- e.g. an admin
// opening the assign modal and clicking Save without changing anything --
// must NOT wipe an already-'accepted'/'requested' status and force the TP
// through the confirm gate again for no real reassignment.
function tpDailyAssignSave($db_conn, string $assignDate, int $firkaId, ?int $tpId, string $assignedBy): void {
    tpWorkStartEnsureColumns($db_conn);

    $stmtChk = $db_conn->prepare("SELECT territory_partner_id FROM tp_daily_assignments WHERE assign_date = ? AND location_id = ?");
    $stmtChk->bind_param('si', $assignDate, $firkaId);
    $stmtChk->execute();
    $existing = $stmtChk->get_result()->fetch_assoc();
    $stmtChk->close();

    $existingTpId = ($existing && $existing['territory_partner_id'] !== null) ? (int)$existing['territory_partner_id'] : null;
    $tpUnchanged = ($existing !== null) && ($existingTpId === $tpId);

    if ($tpUnchanged) {
        $stmt = $db_conn->prepare("UPDATE tp_daily_assignments SET assigned_by = ? WHERE assign_date = ? AND location_id = ?");
        $stmt->bind_param('ssi', $assignedBy, $assignDate, $firkaId);
        $stmt->execute();
        $stmt->close();
        return;
    }

    $stmt = $db_conn->prepare("
        INSERT INTO tp_daily_assignments (
            assign_date, location_id, territory_partner_id, assigned_by,
            tp_approval_status, tp_denial_reason, tp_requested_at, tp_responded_at,
            company_request_status, company_request_note, company_requested_at, company_resolved_by, company_resolved_at
        )
        VALUES (?, ?, ?, ?, 'none', NULL, NULL, NULL, 'none', NULL, NULL, NULL, NULL)
        ON DUPLICATE KEY UPDATE territory_partner_id = VALUES(territory_partner_id), assigned_by = VALUES(assigned_by),
            tp_approval_status = 'none', tp_denial_reason = NULL, tp_requested_at = NULL, tp_responded_at = NULL,
            company_request_status = 'none', company_request_note = NULL, company_requested_at = NULL,
            company_resolved_by = NULL, company_resolved_at = NULL
    ");
    $stmt->bind_param('siis', $assignDate, $firkaId, $tpId, $assignedBy);
    $stmt->execute();
    $stmt->close();
}

// Today's Firka assignments under $locationScopeIds (District/Division depth
// nodes), for the DM's "Daily Work Start" page — one row per Firka, whatever
// its current tp_approval_status/company_request_status is, so the page can
// render the right action button per row.
function fetchTodayWorkStartRows($db_conn, array $locationScopeIds): array {
    if (empty($locationScopeIds)) return [];
    $scopeList = implode(',', array_map('intval', $locationScopeIds));
    $stmt = $db_conn->prepare("
        WITH RECURSIVE node_tree AS (
            SELECT id FROM partner_location_nodes WHERE id IN ($scopeList)
            UNION ALL
            SELECT n.id FROM partner_location_nodes n JOIN node_tree nt ON n.parent_id = nt.id
        )
        SELECT tda.id, tda.location_id, tda.territory_partner_id,
               tda.tp_approval_status, tda.tp_denial_reason,
               tda.company_request_status, tda.company_request_note,
               f.name AS firka_name, d.name AS district_name,
               tp.name AS tp_name, tp.tp_id AS tp_code
        FROM tp_daily_assignments tda
        JOIN partner_location_nodes f  ON f.id  = tda.location_id
        JOIN partner_location_nodes t  ON t.id  = f.parent_id
        JOIN partner_location_nodes dv ON dv.id = t.parent_id
        JOIN partner_location_nodes d  ON d.id  = dv.parent_id
        LEFT JOIN territory_partners tp ON tp.id = tda.territory_partner_id
        WHERE tda.assign_date = CURDATE()
          AND tda.location_id IN (SELECT id FROM node_tree)
        ORDER BY f.name ASC
    ");
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Pending company_request_status='pending' rows for the "Work Start
// Requests" page — optionally scoped to $locationScopeIds (ASM's own
// subtree); null means company's unrestricted, org-wide view.
function fetchPendingCompanyWorkStartRequests($db_conn, ?array $locationScopeIds): array {
    $sql = "
        SELECT tda.id, tda.assign_date, tda.territory_partner_id, tda.company_request_note,
               f.id AS firka_id, f.name AS firka_name, d.name AS district_name,
               tp.name AS tp_name, tp.tp_id AS tp_code
        FROM tp_daily_assignments tda
        JOIN partner_location_nodes f  ON f.id  = tda.location_id
        JOIN partner_location_nodes t  ON t.id  = f.parent_id
        JOIN partner_location_nodes dv ON dv.id = t.parent_id
        JOIN partner_location_nodes d  ON d.id  = dv.parent_id
        LEFT JOIN territory_partners tp ON tp.id = tda.territory_partner_id
        WHERE tda.company_request_status = 'pending'
    ";
    $types = ''; $params = [];
    if ($locationScopeIds !== null) {
        if (empty($locationScopeIds)) return [];
        $placeholders = implode(',', array_fill(0, count($locationScopeIds), '?'));
        $sql .= " AND d.id IN ($placeholders)";
        $types .= str_repeat('i', count($locationScopeIds));
        foreach ($locationScopeIds as $id) { $params[] = (int)$id; }
    }
    $sql .= " ORDER BY tda.company_requested_at ASC";

    $stmt = $db_conn->prepare($sql);
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Does this DM have any of today's own Firka rows still unresolved? Used by
// marketing/checksession.php's gate (mirrors the TP-side gate query above) —
// 'accepted' or 'resolved' = done; 'none'/'requested'/'denied'/'pending' all
// still block, since the DM must wait for the actual TP/Company outcome, not
// just submit a choice (2026-11-20, user request — hard-lock until resolved).
function dmHasBlockingWorkStartToday($db_conn, array $locationScopeIds): bool {
    if (empty($locationScopeIds)) return false;
    $scopeList = implode(',', array_map('intval', $locationScopeIds));
    $stmt = $db_conn->prepare("
        WITH RECURSIVE node_tree AS (
            SELECT id FROM partner_location_nodes WHERE id IN ($scopeList)
            UNION ALL
            SELECT n.id FROM partner_location_nodes n JOIN node_tree nt ON n.parent_id = nt.id
        )
        SELECT 1 FROM tp_daily_assignments tda
        WHERE tda.assign_date = CURDATE()
          AND tda.location_id IN (SELECT id FROM node_tree)
          AND tda.tp_approval_status <> 'accepted'
          AND tda.company_request_status <> 'resolved'
        LIMIT 1
    ");
    $stmt->execute();
    $hasBlocking = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $hasBlocking;
}

// Company-only emergency override: which DMs (marketing staff with their own
// direct location assignment) are currently locked out of the portal by
// today's gate? Walks every such DM and reuses the exact same
// dmHasBlockingWorkStartToday() check the gate itself runs, so this list can
// never drift out of sync with what's actually locking someone. Small staff
// count (tens, not thousands) makes the per-DM scope lookup cheap enough to
// just loop (2026-11-20, user request — company needs to see + release a
// stuck DM when the normal TP/Company approval chain is running late).
function listLockedDmsToday($db_conn): array {
    require_once __DIR__ . '/../marketing/include/AssignedLocations.php';

    $dmIds = [];
    $res = $db_conn->query("SELECT DISTINCT ms_id FROM marketing_staff_locations");
    while ($r = $res->fetch_assoc()) { $dmIds[] = (int)$r['ms_id']; }
    if (empty($dmIds)) return [];

    $idList = implode(',', $dmIds);
    $staffRes = $db_conn->query("SELECT id, ms_name FROM marketing_staff WHERE id IN ($idList) AND deleted_at IS NULL");
    $staffById = [];
    while ($r = $staffRes->fetch_assoc()) { $staffById[(int)$r['id']] = $r['ms_name']; }

    $locked = [];
    foreach ($dmIds as $msId) {
        if (!isset($staffById[$msId])) continue; // inactive/deleted
        $scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $msId);
        $scopeIds = array_column($scopeNodes, 'id');
        if (dmHasBlockingWorkStartToday($db_conn, $scopeIds)) {
            $locked[] = ['ms_id' => $msId, 'ms_name' => $staffById[$msId]];
        }
    }
    usort($locked, fn($a, $b) => strcasecmp($a['ms_name'], $b['ms_name']));
    return $locked;
}

// The actual override — forces every one of this DM's still-blocking rows
// for TODAY straight to resolved, bypassing the normal TP-accept / Company-
// resolve chain entirely. Only meant for "the approval process is running
// late and the DM genuinely can't work" — a deliberate, auditable bypass
// (company_resolved_by records who did it), not a replacement for the normal
// per-Firka resolve flow in work-start-requests.php. Returns rows affected.
function forceReleaseDmWorkLockToday($db_conn, array $locationScopeIds, string $resolvedBy): int {
    if (empty($locationScopeIds)) return 0;
    $scopeList = implode(',', array_map('intval', $locationScopeIds));

    // MariaDB doesn't support a WITH RECURSIVE CTE feeding an UPDATE
    // statement directly (works fine for SELECT, which is all the other
    // functions in this file use it for) -- resolve the node ids first, then
    // UPDATE against that plain id list.
    $nodeRes = $db_conn->query("
        WITH RECURSIVE node_tree AS (
            SELECT id FROM partner_location_nodes WHERE id IN ($scopeList)
            UNION ALL
            SELECT n.id FROM partner_location_nodes n JOIN node_tree nt ON n.parent_id = nt.id
        )
        SELECT id FROM node_tree
    ");
    $nodeIds = [];
    while ($r = $nodeRes->fetch_assoc()) { $nodeIds[] = (int)$r['id']; }
    if (empty($nodeIds)) return 0;
    $nodeList = implode(',', $nodeIds);

    $stmt = $db_conn->prepare("
        UPDATE tp_daily_assignments
        SET tp_approval_status = 'accepted', tp_denial_reason = NULL, tp_responded_at = NOW(),
            company_request_status = 'resolved', company_resolved_by = ?, company_resolved_at = NOW()
        WHERE assign_date = CURDATE()
          AND location_id IN ($nodeList)
          AND tp_approval_status <> 'accepted'
          AND company_request_status <> 'resolved'
    ");
    $stmt->bind_param('s', $resolvedBy);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

// Tomorrow's Firka(s) for one TP — a non-blocking "heads up" reminder shown
// on territory-partner/dashboard.php once it's past 4:30pm, same reminder
// marketing/dashboard.php already shows the DM (2026-11-12, user request).
// Purely informational: no Accept/Deny here, that only happens once it's
// actually "today" (see work-start-confirm.php).
function fetchTpTomorrowFirkas($db_conn, int $tpId, string $date): array {
    $stmt = $db_conn->prepare("
        SELECT f.name AS firka_name, d.name AS district_name
        FROM tp_daily_assignments tda
        JOIN partner_location_nodes f  ON f.id  = tda.location_id
        JOIN partner_location_nodes t  ON t.id  = f.parent_id
        JOIN partner_location_nodes dv ON dv.id = t.parent_id
        JOIN partner_location_nodes d  ON d.id  = dv.parent_id
        WHERE tda.assign_date = ? AND tda.territory_partner_id = ?
        ORDER BY f.name ASC
    ");
    $stmt->bind_param('si', $date, $tpId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Today's still-blocking rows for one TP (both 'requested' — needs an
// Accept/Deny decision — and 'denied' — already decided, but still blocking
// until ASM/Company resolves it, see checksession.php's matching gate
// query). work-start-confirm.php renders these two statuses differently
// (buttons vs. a "waiting" message), so both are returned with their status.
// Empty array = nothing blocking, portal behaves exactly as before this
// feature existed.
function fetchTpPendingWorkStartRequests($db_conn, int $tpId): array {
    $stmt = $db_conn->prepare("
        SELECT tda.id, tda.tp_approval_status, tda.company_request_status, f.name AS firka_name, d.name AS district_name
        FROM tp_daily_assignments tda
        JOIN partner_location_nodes f  ON f.id  = tda.location_id
        JOIN partner_location_nodes t  ON t.id  = f.parent_id
        JOIN partner_location_nodes dv ON dv.id = t.parent_id
        JOIN partner_location_nodes d  ON d.id  = dv.parent_id
        WHERE tda.assign_date = CURDATE()
          AND tda.territory_partner_id = ?
          AND tda.tp_approval_status IN ('requested', 'denied')
        ORDER BY f.name ASC
    ");
    $stmt->bind_param('i', $tpId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}
