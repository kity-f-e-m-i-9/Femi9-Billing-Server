<?php
// Shared query logic for the "Daily Assign Report" page — used by both the
// AJAX JSON endpoint (get-daily-assign-report.php) and the Excel export
// (export-daily-assign-report-xlsx.php) so the two never drift apart.
require_once __DIR__ . "/../../marketing/include/AssignedLocations.php";

// Batch-resolves "which DM owns this Firka" for a whole result set in one
// extra query (not per-row) — 2026-11-30, user-requested ("entha dm ku
// assign panni irukangalo antha dm name also venum"). A DM can be assigned
// at District depth OR Division depth (see the "Division-scoped DM" bug
// fixed elsewhere this session — a DM isn't always a District-depth node),
// so every row's own District/Division/Taluk/Firka node ids are all
// candidates; marketing_team_levels.level_rank=3 ("District Manager") picks
// out the DM specifically, never an ASM/SM who may also be assigned at the
// same District node. If more than one active DM is ever assigned at the
// same node (shouldn't normally happen), the first one found wins.
function attachDmNamesToDailyAssignRows($db_conn, array &$rows): void {
    if (empty($rows)) { return; }
    $locIds = [];
    foreach ($rows as $r) {
        foreach (['district_id', 'division_id', 'taluk_id', 'firka_id'] as $k) {
            if (!empty($r[$k])) { $locIds[(int)$r[$k]] = true; }
        }
    }
    $dmByLocationId = [];
    if (!empty($locIds)) {
        $idList = implode(',', array_map('intval', array_keys($locIds)));
        $res = $db_conn->query("
            SELECT msl.location_id, ms.ms_name
            FROM marketing_staff_locations msl
            JOIN marketing_staff ms ON ms.id = msl.ms_id
            JOIN marketing_team_levels tl ON tl.id = ms.team_level_id AND tl.level_rank = 3
            WHERE msl.location_id IN ($idList) AND ms.account_status = 'active' AND ms.deleted_at IS NULL
        ");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $lid = (int)$row['location_id'];
                if (!isset($dmByLocationId[$lid])) { $dmByLocationId[$lid] = $row['ms_name']; }
            }
        }
    }
    foreach ($rows as &$r) {
        $r['dm_name'] = $dmByLocationId[(int)($r['district_id'] ?? 0)]
            ?? $dmByLocationId[(int)($r['division_id'] ?? 0)]
            ?? $dmByLocationId[(int)($r['taluk_id'] ?? 0)]
            ?? $dmByLocationId[(int)($r['firka_id'] ?? 0)]
            ?? 'Unassigned';
        unset($r['district_id'], $r['division_id'], $r['taluk_id'], $r['firka_id']);
    }
    unset($r);
}

function fetchDailyAssignRows($db_conn, int $ms_id, string $from, string $to): array {
    $sql = "
        SELECT da.assign_date, d.id AS district_id, d.name AS district_name,
               dv.id AS division_id, dv.name AS division_name,
               t.id AS taluk_id, f.id AS firka_id, f.name AS firka_name,
               COALESCE(tp.name, 'Vacant Area') AS tp_name,
               da.assigned_by
        FROM tp_daily_assignments da
        JOIN partner_location_nodes f  ON f.id  = da.location_id
        JOIN partner_location_nodes t  ON t.id  = f.parent_id
        JOIN partner_location_nodes dv ON dv.id = t.parent_id
        JOIN partner_location_nodes d  ON d.id  = dv.parent_id
        LEFT JOIN territory_partners tp ON tp.id = da.territory_partner_id
        WHERE da.assign_date BETWEEN ? AND ?
    ";

    $types  = 'ss';
    $params = [$from, $to];

    if ($ms_id) {
        $districts = getMsAssignedDistricts($db_conn, $ms_id);
        $distIds = array_column($districts, 'id');
        if (empty($distIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($distIds), '?'));
        $sql .= " AND d.id IN ($placeholders)";
        $types .= str_repeat('i', count($distIds));
        foreach ($distIds as $id) { $params[] = $id; }
    }

    $sql .= " ORDER BY da.assign_date DESC, d.name ASC, f.name ASC";

    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    attachDmNamesToDailyAssignRows($db_conn, $rows);

    return $rows;
}

// Marketing-side "my team" variant — used when an ASM/DM views the report
// with no single staff member selected, so it must show everyone across
// their own subtree's districts, never company-wide. $msIds must already be
// the caller's own verified subtree (see marketing/get-daily-assign-report.php).
function fetchDailyAssignRowsForMsIds($db_conn, array $msIds, string $from, string $to): array {
    $distIds = [];
    foreach ($msIds as $msId) {
        foreach (getMsAssignedDistricts($db_conn, (int)$msId) as $d) {
            $distIds[(int)$d['id']] = true;
        }
    }
    $distIds = array_keys($distIds);
    if (empty($distIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($distIds), '?'));
    $sql = "
        SELECT da.assign_date, d.id AS district_id, d.name AS district_name,
               dv.id AS division_id, dv.name AS division_name,
               t.id AS taluk_id, f.id AS firka_id, f.name AS firka_name,
               COALESCE(tp.name, 'Vacant Area') AS tp_name,
               da.assigned_by
        FROM tp_daily_assignments da
        JOIN partner_location_nodes f  ON f.id  = da.location_id
        JOIN partner_location_nodes t  ON t.id  = f.parent_id
        JOIN partner_location_nodes dv ON dv.id = t.parent_id
        JOIN partner_location_nodes d  ON d.id  = dv.parent_id
        LEFT JOIN territory_partners tp ON tp.id = da.territory_partner_id
        WHERE da.assign_date BETWEEN ? AND ?
          AND d.id IN ($placeholders)
        ORDER BY da.assign_date DESC, d.name ASC, f.name ASC
    ";

    $types  = 'ss' . str_repeat('i', count($distIds));
    $params = array_merge([$from, $to], $distIds);

    $stmt = $db_conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    attachDmNamesToDailyAssignRows($db_conn, $rows);

    return $rows;
}
