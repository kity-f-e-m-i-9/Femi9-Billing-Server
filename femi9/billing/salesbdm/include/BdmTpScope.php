<?php
// Resolves which Territory Partners fall under a Sales BDM's assigned
// locations. There's no FK between the location tree and territory_partners
// — TPs only carry a free-text branch_district — so this matches district
// NAMES (case-insensitive, trimmed) rather than ids. A BDM's assigned
// locations can sit at any depth (state, district, or a dual-role district),
// so each one is first resolved down/up to its enclosing set of district
// names before the TP match runs.

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
            // Shallower than district (e.g. State) — pull every child district.
            $childStmt = $db_conn->prepare("SELECT name FROM partner_location_nodes WHERE parent_id = ? AND depth = ? AND is_active = 1");
            $childStmt->bind_param('ii', $node['id'], $districtDepth);
            $childStmt->execute();
            $children = $childStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $childStmt->close();
            foreach ($children as $c) { $names[] = $c['name']; }
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

function getBdmAssignedTpIds($db_conn, int $bdmId): array {
    $districtNames = getBdmAssignedDistrictNames($db_conn, $bdmId);
    if (empty($districtNames)) return [];

    $placeholders = implode(',', array_fill(0, count($districtNames), '?'));
    $types = str_repeat('s', count($districtNames));
    $normalized = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);

    $stmt = $db_conn->prepare("
        SELECT id FROM territory_partners
        WHERE is_active = 1 AND LOWER(TRIM(branch_district)) IN ($placeholders)
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
