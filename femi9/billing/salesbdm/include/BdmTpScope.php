<?php
// Resolves which Territory Partners fall under a Sales BDM's own PERSONAL
// district assignment(s) — used everywhere except "Our Team" (dashboard, TP
// Purchase Order, Advance Payment Report, Add/Manage/Edit Territory
// Partner).
//
// As of 2026-10-02, a BDM's personal district scope is derived ENTIRELY
// from their assigned Zone (sales_bdm_staff.zone, a company-defined group
// of districts — see company/include/PartnerZones.php). The old manual
// "Assign Location" picker AND the "Dual Role" picker ("also personally
// handle some locations one level down") have both been removed from
// Add/Edit Sales BDM — Zone alone drives this now. This also means a Chief
// BDM whose Zone covers an entire state personally sees every district in
// it — there's no more "shallower-than-district assignment represents the
// team, not personal territory" special case, since a Zone is always built
// from actual district nodes (never a bare State node), so there's nothing
// to collapse.
//
// There's no FK between the location tree and territory_partners — TPs only
// carry a free-text branch_district — so this matches district NAMES
// (case-insensitive, trimmed) rather than ids.

function getBdmAssignedDistrictNames($db_conn, int $bdmId): array {
    require_once __DIR__ . '/../../company/include/PartnerZones.php';
    ensurePartnerZonesTables($db_conn);

    $zoneRow = $db_conn->query("SELECT zone FROM sales_bdm_staff WHERE id = " . (int)$bdmId)->fetch_assoc();
    $zoneName = trim($zoneRow['zone'] ?? '');
    if ($zoneName === '') return [];

    $stmt = $db_conn->prepare("SELECT id FROM partner_zones WHERE name = ?");
    $stmt->bind_param('s', $zoneName);
    $stmt->execute();
    $zone = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$zone) return [];

    return getZoneDistrictNames($db_conn, (int)$zone['id']);
}

// Full district-level target potential across the given district names —
// every Firka's target_amount, regardless of whether that Firka currently
// has a TP or whether that TP is active/inactive. Same formula (and same
// pll.is_tp_filter_enabled=1 + pln.is_active=1 restriction, so a stray
// target_amount at a non-assignable depth like Division never gets double-
// counted) as salesbdm/dashboard.php's own "District Total Target" card —
// factored out here so salesbdm/include/TeamTargetRollup.php's team-rollup
// figure can show the exact same number a BDM sees on their own dashboard,
// instead of the smaller "only Firkas that already have a TP" figure it used
// to compute.
function getBdmDistrictTotalTarget($db_conn, array $districtNames): float {
    if (empty($districtNames)) return 0.0;

    $districtDepthRow = $db_conn->query(
        "SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1"
    )->fetch_assoc();
    $districtDepth = (int)($districtDepthRow['depth'] ?? 0);
    if (!$districtDepth) return 0.0;

    $dn = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);
    $ph = implode(',', array_fill(0, count($dn), '?'));
    $types = 'i' . str_repeat('s', count($dn));
    $params = array_merge([$districtDepth], $dn);

    $stmt = $db_conn->prepare(
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
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (float)($stmt->get_result()->fetch_assoc()['total_target'] ?? 0);
    $stmt->close();

    return $total;
}

// $districtNamesOverride lets a caller narrow the match down to a subset of
// this BDM's own districts (e.g. salesbdm/dashboard.php's "view one
// district only" filter, picked from company's Sales BDM report tab)
// instead of their whole zone — null (the default, used everywhere else)
// keeps the normal "every district this BDM's zone covers" behavior.
function getBdmAssignedTpIds($db_conn, int $bdmId, bool $includeInactive = false, ?array $districtNamesOverride = null): array {
    $districtNames = $districtNamesOverride !== null ? $districtNamesOverride : getBdmAssignedDistrictNames($db_conn, $bdmId);
    if (empty($districtNames)) return [];

    $placeholders = implode(',', array_fill(0, count($districtNames), '?'));
    $types = str_repeat('s', count($districtNames));
    $normalized = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);

    // Self-migrating: see db_migrations/2026_08_28_tp_assigned_district.sql.
    // assigned_district is a SEPARATE concept from branch_district/
    // delivery_district (which are real postal-address fields someone can
    // legitimately fill with a totally different place — e.g. a TP's GST-
    // registered office address vs. the sales territory they cover).
    // assigned_district instead auto-tracks the district of whichever Firka
    // the TP is actually picked for in territory_partner_locations (see
    // territory-partner-action.php's resolveAssignedDistrictFromLocations()
    // call) — that's the only thing this BDM-territory match should ever be
    // based on. Falls back to branch_district only for a TP whose
    // assigned_district hasn't been backfilled/set yet, so this doesn't
    // silently drop every existing TP from every BDM's list the moment this
    // column is introduced.
    $_col = $db_conn->query("SHOW COLUMNS FROM territory_partners LIKE 'assigned_district'");
    if ($_col && $_col->num_rows === 0) {
        $db_conn->query("ALTER TABLE territory_partners ADD COLUMN assigned_district VARCHAR(100) DEFAULT NULL AFTER branch_district");
    }

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

// Every TP covered by this BDM's own Zone PLUS every TP covered by any of
// their subordinates' Zones (their whole "Our Team" subtree) — used where a
// manager needs visibility into their team's TPs regardless of whether the
// TP's own directly-matched BDM has some separate per-feature permission
// flag enabled. E.g. the Shop Invoice Permission Requests review queue: a
// Sales BDM might not be marked "eligible" to review these, but their
// manager (Chief BDM), if eligible, should still see requests for TPs under
// that subordinate — not just TPs the Chief BDM's own Zone happens to
// cover directly. Confirmed 2026-10-02.
function getBdmSubtreeAssignedTpIds($db_conn, int $bdmId, bool $includeInactive = false): array {
    require_once __DIR__ . '/TeamSubtree.php';
    $subtreeIds = getBdmSubtreeIds($db_conn, $bdmId);
    $tpIds = [];
    foreach ($subtreeIds as $memberId) {
        foreach (getBdmAssignedTpIds($db_conn, $memberId, $includeInactive) as $tpId) {
            $tpIds[$tpId] = true;
        }
    }
    return array_keys($tpIds);
}

// Checks whether a single location node (at ANY depth — a TP is normally
// assigned at Firka level, below District) falls within one of this BDM's
// assigned districts, by walking its parent_id chain up to district depth.
// Used to validate location_ids submitted from the Add TP picker.
function isLocationInBdmDistricts($db_conn, int $bdmId, int $locationId): bool {
    $districtNames = array_map(fn($n) => mb_strtolower(trim($n)), getBdmAssignedDistrictNames($db_conn, $bdmId));
    if (empty($districtNames)) return false;

    $districtDepthRow = $db_conn->query("SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1")->fetch_assoc();
    if (!$districtDepthRow) return false;
    $districtDepth = (int)$districtDepthRow['depth'];

    $currentId = $locationId;
    $guard = 0;
    while ($currentId > 0 && $guard < 10) {
        $stmt = $db_conn->prepare("SELECT name, depth, parent_id FROM partner_location_nodes WHERE id = ?");
        $stmt->bind_param('i', $currentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return false;
        if ((int)$row['depth'] === $districtDepth) {
            return in_array(mb_strtolower(trim($row['name'])), $districtNames, true);
        }
        $currentId = (int)$row['parent_id'];
        $guard++;
    }
    return false;
}
?>
