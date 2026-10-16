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

// Primary match: a TP's actual Firka coverage (territory_partner_locations),
// walked up to its district ancestor — correctly surfaces a TP under EVERY
// district they actually hold Firkas in, which matters because a TP can
// cover Firkas across more than one district (e.g. one TP holding Firkas in
// both Coimbatore and Tiruppur). The old assigned_district/branch_district
// text field can only ever point to one district, so a cross-district TP
// would silently vanish from one district's roster while their Firka target
// still counted toward that district's total (getMsDistrictTargetAndFirkaStats
// sums by actual Firka location, not by this field) — a real mismatch found
// 2026-10-15 (Vinith Sangavi, TP id 96: Firkas in both Coimbatore and
// Tiruppur, assigned_district only said Coimbatore).
// Fallback: TPs with no territory_partner_locations rows at all yet (not
// backfilled with Firka assignments) are still matched the old way, by their
// assigned_district/branch_district text field, so they don't disappear from
// every list.
function getMsDistrictTpIds($db_conn, array $districtNames, bool $includeInactive = false): array {
    if (empty($districtNames)) return [];

    $placeholders = implode(',', array_fill(0, count($districtNames), '?'));
    $types = str_repeat('s', count($districtNames));
    $normalized = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);
    $activeClause = $includeInactive ? '' : 'is_active = 1 AND ';

    $districtDepthRow = $db_conn->query(
        "SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1"
    )->fetch_assoc();
    $districtDepth = (int)($districtDepthRow['depth'] ?? 0);

    $byFirkaIds = [];
    if ($districtDepth) {
        $stmtFirka = $db_conn->prepare("
            WITH RECURSIVE district_tree AS (
                SELECT id FROM partner_location_nodes WHERE depth = ? AND LOWER(TRIM(name)) IN ($placeholders)
                UNION ALL
                SELECT n.id FROM partner_location_nodes n JOIN district_tree dt ON n.parent_id = dt.id
            )
            SELECT DISTINCT tp.id
            FROM territory_partners tp
            JOIN territory_partner_locations tpl ON tpl.territory_partner_id = tp.id
            WHERE {$activeClause}tpl.location_id IN (SELECT id FROM district_tree)
        ");
        $typesFirka = 'i' . $types;
        $paramsFirka = array_merge([$districtDepth], $normalized);
        $stmtFirka->bind_param($typesFirka, ...$paramsFirka);
        $stmtFirka->execute();
        $byFirkaIds = array_map(fn($r) => (int)$r['id'], $stmtFirka->get_result()->fetch_all(MYSQLI_ASSOC));
        $stmtFirka->close();
    }

    $stmt = $db_conn->prepare("
        SELECT id FROM territory_partners
        WHERE {$activeClause}LOWER(TRIM(COALESCE(NULLIF(assigned_district,''), branch_district))) IN ($placeholders)
          AND id NOT IN (SELECT DISTINCT territory_partner_id FROM territory_partner_locations)
    ");
    $stmt->bind_param($types, ...$normalized);
    $stmt->execute();
    $byTextIds = array_map(fn($r) => (int)$r['id'], $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    $stmt->close();

    return array_values(array_unique(array_merge($byFirkaIds, $byTextIds)));
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

// Firka(s) a TP actually covers, from territory_partner_locations (one TP
// can hold several Firkas — up to a dozen+ in practice), plus the combined
// target_amount of those Firkas — for the "Firka" / "Target Amount" columns
// on the dashboard's Territory Partners roster. District itself is NOT
// resolved here; callers already have it cheaper from
// territory_partners.assigned_district / branch_district (see
// PartnerLocationDistrict.php's doc comment on why that's the canonical
// source, not a tree walk).
function getTpTalukFirkaLabels($db_conn, array $tpIds): array {
    $out = [];
    if (empty($tpIds)) return $out;

    $talukDepthRow = $db_conn->query(
        "SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'taluk%' ORDER BY depth ASC LIMIT 1"
    )->fetch_assoc();
    $firkaDepthRow = $db_conn->query(
        "SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'firka%' ORDER BY depth ASC LIMIT 1"
    )->fetch_assoc();
    $talukDepth = (int)($talukDepthRow['depth'] ?? 0);
    $firkaDepth = (int)($firkaDepthRow['depth'] ?? 0);
    if (!$talukDepth && !$firkaDepth) return $out;

    $idList = implode(',', array_map('intval', $tpIds));
    $rows = $db_conn->query(
        "SELECT tpl.territory_partner_id, n.id AS loc_id, n.depth, n.name, n.parent_id, n.target_amount
         FROM territory_partner_locations tpl
         JOIN partner_location_nodes n ON n.id = tpl.location_id
         WHERE tpl.territory_partner_id IN ($idList)"
    )->fetch_all(MYSQLI_ASSOC);
    if (empty($rows)) return $out;

    $taluks = [];
    $firkas = [];
    $targets = [];
    foreach ($rows as $r) {
        $tpId = (int)$r['territory_partner_id'];
        $depth = (int)$r['depth'];
        if ($depth === $firkaDepth) {
            $firkas[$tpId][$r['name']] = true;
            $targets[$tpId] = ($targets[$tpId] ?? 0.0) + (float)$r['target_amount'];
        } elseif ($depth === $talukDepth) {
            $taluks[$tpId][$r['name']] = true;
        }
    }

    foreach ($tpIds as $tpId) {
        $tpId = (int)$tpId;
        $tList = !empty($taluks[$tpId]) ? array_keys($taluks[$tpId]) : [];
        $fList = !empty($firkas[$tpId]) ? array_keys($firkas[$tpId]) : [];
        sort($tList);
        sort($fList);
        $out[$tpId] = ['taluks' => $tList, 'firkas' => $fList, 'target' => $targets[$tpId] ?? 0.0];
    }
    return $out;
}

// Same Firka-level data as getTpTalukFirkaLabels(), but grouped by each
// Firka's own district instead of flattened across all of them — lets a
// caller show a cross-district TP's amount scoped to just the viewer's own
// district(s) on their roster, with the rest attributed to whichever other
// district(s) it actually belongs to. [tp_id => [district_name => ['target' => float, 'firkas' => [names]]]]
function getTpFirkaDistrictSplit($db_conn, array $tpIds): array {
    $out = [];
    if (empty($tpIds)) return $out;
    $idList = implode(',', array_map('intval', $tpIds));

    $rows = $db_conn->query(
        "WITH RECURSIVE anc AS (
            SELECT id AS firka_id, id AS cur_id, parent_id FROM partner_location_nodes WHERE depth = 6
            UNION ALL
            SELECT a.firka_id, n.id, n.parent_id FROM partner_location_nodes n JOIN anc a ON n.id = a.parent_id
         )
         SELECT tpl.territory_partner_id AS tp_id, d.name AS district_name, f.name AS firka_name, f.target_amount
         FROM territory_partner_locations tpl
         JOIN partner_location_nodes f ON f.id = tpl.location_id AND f.depth = 6
         JOIN anc a ON a.firka_id = f.id
         JOIN partner_location_nodes d ON d.id = a.cur_id AND d.depth = 3
         WHERE tpl.territory_partner_id IN ($idList)"
    )->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as $r) {
        $tpId = (int)$r['tp_id'];
        $dn   = $r['district_name'];
        if (!isset($out[$tpId][$dn])) { $out[$tpId][$dn] = ['target' => 0.0, 'firkas' => []]; }
        $out[$tpId][$dn]['target'] += (float)$r['target_amount'];
        $out[$tpId][$dn]['firkas'][] = $r['firka_name'];
    }
    return $out;
}

// Which District Manager(s) — a marketing_staff whose team level name
// contains "District" — are directly assigned to a given district node, for
// labeling a cross-district TP's "the rest of this amount belongs to
// <district>, under <DM>" split note.
function getDistrictManagerNames($db_conn, array $districtNames): array {
    $out = [];
    if (empty($districtNames)) return $out;
    $placeholders = implode(',', array_fill(0, count($districtNames), '?'));
    $types = str_repeat('s', count($districtNames));
    $normalized = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);

    $stmt = $db_conn->prepare(
        "SELECT d.name AS district_name, ms.ms_name
         FROM marketing_staff_locations msl
         JOIN partner_location_nodes d ON d.id = msl.location_id AND d.depth = 3
         JOIN marketing_staff ms ON ms.id = msl.ms_id AND ms.deleted_at IS NULL
         JOIN marketing_team_levels tl ON tl.id = ms.team_level_id AND tl.level_name LIKE '%District%'
         WHERE LOWER(TRIM(d.name)) IN ($placeholders)"
    );
    $stmt->bind_param($types, ...$normalized);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $out[$r['district_name']][] = $r['ms_name'];
    }
    $stmt->close();
    return $out;
}
