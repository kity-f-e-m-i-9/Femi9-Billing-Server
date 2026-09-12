<?php
// Resolves which DISTRICTS a Territory Partner's own location assignments
// (territory_partner_locations, at Firka depth or deeper — see
// add-purchase-order.php's own assignment reads) fall under, by walking each
// assigned node's parent_id chain up to the district-depth ancestor. Mirrors
// salesbdm/include/BdmTpScope.php's getBdmAssignedDistrictNames() — same
// partner_location_nodes tree, same walk-up-to-district logic — just scoped
// to a TP's own assignments instead of a BDM's.
//
// A TP is normally assigned to a single Firka (and therefore a single
// district), but nothing stops two Firka assignments landing in different
// districts, so this always returns a list (usually of length 1) rather than
// assuming one.

function getTpAssignedDistrictNames($db_conn, int $tpId): array {
    $districtDepthRow = $db_conn->query("SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1")->fetch_assoc();
    if (!$districtDepthRow) return [];
    $districtDepth = (int)$districtDepthRow['depth'];

    $stmt = $db_conn->prepare("
        SELECT n.id, n.name, n.depth, n.parent_id
        FROM territory_partner_locations tpl
        JOIN partner_location_nodes n ON n.id = tpl.location_id
        WHERE tpl.territory_partner_id = ?
    ");
    $stmt->bind_param('i', $tpId);
    $stmt->execute();
    $assigned = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $names = [];
    foreach ($assigned as $node) {
        $depth = (int)$node['depth'];
        if ($depth === $districtDepth) {
            $names[] = $node['name'];
        } elseif ($depth < $districtDepth) {
            // Shallower than district (e.g. State) — not a real-world case
            // for a TP's own assignment, but skip rather than guess.
            continue;
        } else {
            // Deeper than district (Firka, or below) — walk parent_id up to
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
