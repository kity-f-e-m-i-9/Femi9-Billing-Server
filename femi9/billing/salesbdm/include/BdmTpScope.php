<?php
// Resolves which Territory Partners fall under a Sales BDM's own PERSONAL
// district assignment(s) — used everywhere except "Our Team" (dashboard, TP
// Purchase Order, Advance Payment Report, Add/Manage/Edit Territory
// Partner). A Chief BDM's broader state-level assignment represents which
// region their TEAM covers, not their own personal territory, so a
// shallower-than-district entry (e.g. State) is intentionally NOT expanded
// into every child district here — only that Chief BDM's own direct
// district-or-deeper assignments count. Their team's combined footprint
// still shows in full under "Our Team" (my-team.php), which walks each
// team member's own assignment individually rather than going through this
// function's state-node handling at all.
//
// There's no FK between the location tree and territory_partners — TPs only
// carry a free-text branch_district — so this matches district NAMES
// (case-insensitive, trimmed) rather than ids.

function getBdmAssignedDistrictNames($db_conn, int $bdmId): array {
    $db_conn->query("CREATE TABLE IF NOT EXISTS salesbdm_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bdm_id INT NOT NULL,
        location_id INT NOT NULL,
        is_dual_role TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_bdm_location (bdm_id, location_id)
    )");

    $districtDepthRow = $db_conn->query("SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1")->fetch_assoc();
    if (!$districtDepthRow) return [];
    $districtDepth = (int)$districtDepthRow['depth'];

    $stmt = $db_conn->prepare("
        SELECT n.id, n.name, n.depth, n.parent_id
        FROM salesbdm_locations sl
        JOIN partner_location_nodes n ON n.id = sl.location_id
        WHERE sl.bdm_id = ?
    ");
    $stmt->bind_param('i', $bdmId);
    $stmt->execute();
    $assigned = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $names = [];
    foreach ($assigned as $node) {
        $depth = (int)$node['depth'];
        if ($depth === $districtDepth) {
            $names[] = $node['name'];
        } elseif ($depth < $districtDepth) {
            // Shallower than district (e.g. State) — represents the region
            // this person's TEAM covers, not their own personal territory,
            // so it deliberately contributes nothing to this personal scope.
            continue;
        } else {
            // Deeper than district (e.g. Taluk/Firka) — walk parent_id up to
            // the enclosing district.
            $currentId = (int)$node['parent_id'];
            $currentDepth = $depth - 1;
            $guard = 0;
            while ($currentId > 0 && $currentDepth > $districtDepth && $guard < 10) {
                $upStmt = $db_conn->prepare("SELECT id, name, depth, parent_id FROM partner_location_nodes WHERE id = ?");
                $upStmt->bind_param('i', $currentId);
                $upStmt->execute();
                $up = $upStmt->get_result()->fetch_assoc();
                $upStmt->close();
                if (!$up) break;
                $currentId = (int)$up['parent_id'];
                $currentDepth = (int)$up['depth'] - 1;
                $guard++;
                if ((int)$up['depth'] === $districtDepth) { $names[] = $up['name']; break; }
            }
        }
    }

    return array_values(array_unique($names));
}

function getBdmAssignedTpIds($db_conn, int $bdmId, bool $includeInactive = false): array {
    $districtNames = getBdmAssignedDistrictNames($db_conn, $bdmId);
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
