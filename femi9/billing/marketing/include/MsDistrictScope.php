<?php
// Resolves a marketing staff member's assigned district(s) into TP scope and
// district-level target/Firka-coverage stats, for the marketing dashboard.
// Mirrors femi9/billing/salesbdm/include/BdmTpScope.php's TP-matching
// approach and salesbdm/dashboard.php's recursive district-tree target/Firka
// queries, just parameterized by district names instead of a BDM id.

require_once __DIR__ . '/AssignedLocations.php';

function getMsAssignedDistrictNames($db_conn, int $msId): array {
    $districts = getMsAssignedDistricts($db_conn, $msId);
    return array_column($districts, 'name');
}

// Matches territory_partners the same way getBdmAssignedTpIds() does — by
// district NAME (case-insensitive, trimmed) against assigned_district,
// falling back to branch_district for TPs not yet backfilled — since there's
// no FK from territory_partners into the location tree.
function getMsDistrictTpIds($db_conn, array $districtNames, bool $includeInactive = false): array {
    if (empty($districtNames)) return [];

    $placeholders = implode(',', array_fill(0, count($districtNames), '?'));
    $types = str_repeat('s', count($districtNames));
    $normalized = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);

    $activeClause = $includeInactive ? '' : 'is_active = 1 AND ';
    $stmt = $db_conn->prepare("
        SELECT id FROM territory_partners
        WHERE {$activeClause}LOWER(TRIM(COALESCE(NULLIF(assigned_district,''), branch_district))) IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$normalized);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(fn($r) => (int)$r['id'], $rows);
}

// District Total Target + Firka coverage counts for a set of district names —
// ported from salesbdm/dashboard.php's "District Total Target" /
// "District / Firka coverage counts" / "Target split by Firka TP status"
// blocks (lines ~419-501), just scoped by district name list instead of a
// BDM's zone-derived district list.
function getMsDistrictTargetAndFirkaStats($db_conn, array $districtNames): array {
    $stats = [
        'district_total_target'    => 0.0,
        'firka_total_count'        => 0,
        'firka_filled_count'       => 0,
        'firka_vacant_count'       => 0,
        'target_active_amount'     => 0.0,
        'target_inactive_amount'   => 0.0,
        'target_unassigned_amount' => 0.0,
    ];
    if (empty($districtNames)) return $stats;

    $districtDepthRow = $db_conn->query(
        "SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1"
    )->fetch_assoc();
    $districtDepth = (int)($districtDepthRow['depth'] ?? 0);
    if (!$districtDepth) return $stats;

    $dn = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);
    $ph = implode(',', array_fill(0, count($dn), '?'));
    $types = 'i' . str_repeat('s', count($dn));
    $params = array_merge([$districtDepth], $dn);

    $stmtTarget = $db_conn->prepare(
        "WITH RECURSIVE district_tree AS (
            SELECT id FROM partner_location_nodes WHERE depth = ? AND LOWER(TRIM(name)) IN ($ph)
            UNION ALL
            SELECT n.id FROM partner_location_nodes n JOIN district_tree dt ON n.parent_id = dt.id
         )
         SELECT COALESCE(SUM(pln.target_amount),0) AS total_target
         FROM partner_location_nodes pln
         JOIN partner_location_layers pll ON pll.depth = pln.depth
         WHERE pln.id IN (SELECT id FROM district_tree) AND pll.is_tp_filter_enabled = 1 AND pln.is_active = 1"
    );
    $stmtTarget->bind_param($types, ...$params);
    $stmtTarget->execute();
    $stats['district_total_target'] = (float)($stmtTarget->get_result()->fetch_assoc()['total_target'] ?? 0);
    $stmtTarget->close();

    $stmtFirka = $db_conn->prepare(
        "WITH RECURSIVE district_tree AS (
            SELECT id FROM partner_location_nodes WHERE depth = ? AND LOWER(TRIM(name)) IN ($ph)
            UNION ALL
            SELECT n.id FROM partner_location_nodes n JOIN district_tree dt ON n.parent_id = dt.id
         )
         SELECT
             COUNT(*) AS total_firkas,
             COUNT(DISTINCT tpl.location_id) AS filled_firkas
         FROM partner_location_nodes pln
         JOIN partner_location_layers pll ON pll.depth = pln.depth
         LEFT JOIN territory_partner_locations tpl ON tpl.location_id = pln.id
         WHERE pln.id IN (SELECT id FROM district_tree) AND pll.is_tp_filter_enabled = 1 AND pln.is_active = 1"
    );
    $stmtFirka->bind_param($types, ...$params);
    $stmtFirka->execute();
    $firkaRow = $stmtFirka->get_result()->fetch_assoc();
    $stmtFirka->close();
    $stats['firka_total_count']  = (int)($firkaRow['total_firkas'] ?? 0);
    $stats['firka_filled_count'] = (int)($firkaRow['filled_firkas'] ?? 0);
    $stats['firka_vacant_count'] = $stats['firka_total_count'] - $stats['firka_filled_count'];

    $stmtSplit = $db_conn->prepare(
        "WITH RECURSIVE district_tree AS (
            SELECT id FROM partner_location_nodes WHERE depth = ? AND LOWER(TRIM(name)) IN ($ph)
            UNION ALL
            SELECT n.id FROM partner_location_nodes n JOIN district_tree dt ON n.parent_id = dt.id
         )
         SELECT pln.id, pln.target_amount, MAX(tp.is_active) AS has_active_tp, COUNT(tpl.location_id) AS tp_count
         FROM partner_location_nodes pln
         JOIN partner_location_layers pll ON pll.depth = pln.depth
         LEFT JOIN territory_partner_locations tpl ON tpl.location_id = pln.id
         LEFT JOIN territory_partners tp ON tp.id = tpl.territory_partner_id
         WHERE pln.id IN (SELECT id FROM district_tree) AND pll.is_tp_filter_enabled = 1 AND pln.is_active = 1
         GROUP BY pln.id, pln.target_amount"
    );
    $stmtSplit->bind_param($types, ...$params);
    $stmtSplit->execute();
    foreach ($stmtSplit->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $val = (float)($r['target_amount'] ?? 0);
        if ((int)($r['tp_count'] ?? 0) === 0) {
            $stats['target_unassigned_amount'] += $val;
        } elseif ((int)($r['has_active_tp'] ?? 0) === 1) {
            $stats['target_active_amount'] += $val;
        } else {
            $stats['target_inactive_amount'] += $val;
        }
    }
    $stmtSplit->close();

    return $stats;
}

// Row-level detail of every vacant Firka (no TP assigned at all) across the
// given districts — [{district, firka, target_amount}], for the "Vacant
// Firkas" tile's drill-down panel. Same district_tree pattern as above, just
// returning rows instead of an aggregate.
function getMsVacantFirkas($db_conn, array $districtNames): array {
    if (empty($districtNames)) return [];

    $districtDepthRow = $db_conn->query(
        "SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1"
    )->fetch_assoc();
    $districtDepth = (int)($districtDepthRow['depth'] ?? 0);
    if (!$districtDepth) return [];

    $dn = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);
    $ph = implode(',', array_fill(0, count($dn), '?'));
    $types = 'i' . str_repeat('s', count($dn));
    $params = array_merge([$districtDepth], $dn);

    $stmt = $db_conn->prepare(
        "WITH RECURSIVE district_tree AS (
            SELECT id, id AS district_id, name AS district_name
            FROM partner_location_nodes
            WHERE depth = ? AND LOWER(TRIM(name)) IN ($ph)
            UNION ALL
            SELECT n.id, dt.district_id, dt.district_name
            FROM partner_location_nodes n JOIN district_tree dt ON n.parent_id = dt.id
         )
         SELECT dt.district_name, pln.name AS firka_name, pln.target_amount
         FROM partner_location_nodes pln
         JOIN district_tree dt ON dt.id = pln.id
         JOIN partner_location_layers pll ON pll.depth = pln.depth
         LEFT JOIN territory_partner_locations tpl ON tpl.location_id = pln.id
         WHERE pll.is_tp_filter_enabled = 1 AND pln.is_active = 1
         GROUP BY pln.id, pln.name, dt.district_name, pln.target_amount
         HAVING COUNT(tpl.location_id) = 0
         ORDER BY pln.target_amount DESC"
    );
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}
