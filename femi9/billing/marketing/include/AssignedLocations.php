<?php
// Resolves the districts (and taluks under them) a marketing staff member
// (DM) has actually been assigned via marketing_staff_locations, against the
// clean partner_location_nodes hierarchy — used to scope the Add Shop form's
// District/Taluk pickers instead of the old free-text state/district/taluk
// inputs (which is how the "Erode / Erode- / Erodr" duplicate-spelling mess
// happened in the first place).
//
// An assignment can be made at STATE, DISTRICT, or TALUK depth:
//   - STATE   -> every district under that state is offered.
//   - DISTRICT -> that district itself is offered.
//   - TALUK   -> its parent district is offered (so the DM can still pick
//                any taluk in that district, not just the assigned one).
// Taluk's real parent is a DIVISION node (depth 4, hidden from this UI), so
// district->taluk lookups walk through that intermediate layer.

function getMsAssignedDistricts($db_conn, int $msId): array {
    $stmtAss = $db_conn->prepare(
        "SELECT location_id FROM marketing_staff_locations WHERE ms_id=?"
    );
    $stmtAss->bind_param('i', $msId);
    $stmtAss->execute();
    $assignedIds = array_column($stmtAss->get_result()->fetch_all(MYSQLI_ASSOC), 'location_id');
    $stmtAss->close();

    if (empty($assignedIds)) {
        return [];
    }

    // Load the whole active tree once — small table, cheaper than N queries.
    $allNodes = [];
    $res = $db_conn->query("SELECT id, parent_id, depth, name FROM partner_location_nodes WHERE is_active=1");
    while ($row = $res->fetch_assoc()) {
        $allNodes[(int)$row['id']] = [
            'id'        => (int)$row['id'],
            'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
            'depth'     => (int)$row['depth'],
            'name'      => $row['name'],
        ];
    }

    $districtIds = [];
    foreach ($assignedIds as $locId) {
        $locId = (int)$locId;
        if (!isset($allNodes[$locId])) { continue; }
        $node = $allNodes[$locId];

        if ($node['depth'] === 3) {
            $districtIds[$locId] = true;
        } elseif ($node['depth'] === 2) {
            foreach ($allNodes as $nId => $n) {
                if ($n['depth'] === 3 && $n['parent_id'] === $locId) { $districtIds[$nId] = true; }
            }
        } else {
            // TALUK (or anything deeper/shallower) — walk up to the nearest DISTRICT ancestor.
            $cur = $node;
            while ($cur !== null && $cur['depth'] !== 3) {
                $cur = $cur['parent_id'] !== null ? ($allNodes[$cur['parent_id']] ?? null) : null;
            }
            if ($cur !== null) { $districtIds[$cur['id']] = true; }
        }
    }

    if (empty($districtIds)) {
        return [];
    }

    // Taluks grouped by their grandparent district (skipping the DIVISION layer).
    $talukByDistrict = [];
    foreach ($allNodes as $n) {
        if ($n['depth'] !== 5 || $n['parent_id'] === null) { continue; }
        $division = $allNodes[$n['parent_id']] ?? null;
        if (!$division || $division['parent_id'] === null) { continue; }
        $distId = $division['parent_id'];
        if (isset($districtIds[$distId])) {
            $talukByDistrict[$distId][] = ['id' => $n['id'], 'name' => $n['name']];
        }
    }

    $result = [];
    foreach (array_keys($districtIds) as $distId) {
        $dist = $allNodes[$distId] ?? null;
        if (!$dist) { continue; }
        $state = $dist['parent_id'] !== null ? ($allNodes[$dist['parent_id']] ?? null) : null;
        $taluks = $talukByDistrict[$distId] ?? [];
        usort($taluks, fn($a, $b) => strcmp($a['name'], $b['name']));
        $result[] = [
            'id'         => $dist['id'],
            'name'       => $dist['name'],
            'state_id'   => $state['id'] ?? 0,
            'state_name' => $state['name'] ?? '',
            'taluks'     => $taluks,
        ];
    }
    usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));

    return $result;
}

// Like getMsAssignedDistricts() above, but for TARGET-SCOPING purposes
// instead of the Add Shop form's District/Taluk pickers — a District OR a
// DIVISION assignment is kept as-is here (not collapsed up to its enclosing
// district), so a DM assigned a specific Division sees/rolls up just that
// division's Firka target, not the whole district's. Used by
// company/include/TeamTargetRollup.php and marketing/dashboard.php.
//
//   - STATE    -> every district under that state (same as getMsAssignedDistricts()).
//   - DISTRICT -> that district itself.
//   - DIVISION -> that division itself (the one depth this function adds).
//   - anything else (a rare direct Taluk/Firka assignment) -> walk up to the
//     nearest DISTRICT ancestor, same safety net as getMsAssignedDistricts().
//
// Returns [{id, name, depth}].
function getMsAssignedTargetScopeNodes($db_conn, int $msId): array {
    $stmtAss = $db_conn->prepare(
        "SELECT location_id FROM marketing_staff_locations WHERE ms_id=?"
    );
    $stmtAss->bind_param('i', $msId);
    $stmtAss->execute();
    $assignedIds = array_column($stmtAss->get_result()->fetch_all(MYSQLI_ASSOC), 'location_id');
    $stmtAss->close();

    if (empty($assignedIds)) {
        return [];
    }

    $allNodes = [];
    $res = $db_conn->query("SELECT id, parent_id, depth, name FROM partner_location_nodes WHERE is_active=1");
    while ($row = $res->fetch_assoc()) {
        $allNodes[(int)$row['id']] = [
            'id'        => (int)$row['id'],
            'parent_id' => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
            'depth'     => (int)$row['depth'],
            'name'      => $row['name'],
        ];
    }

    $scopeIds = [];
    foreach ($assignedIds as $locId) {
        $locId = (int)$locId;
        if (!isset($allNodes[$locId])) { continue; }
        $node = $allNodes[$locId];

        if ($node['depth'] === 3 || $node['depth'] === 4) {
            // DISTRICT or DIVISION — this DM's own assignment, used directly.
            $scopeIds[$locId] = true;
        } elseif ($node['depth'] === 2) {
            // STATE — expand to every district under it.
            foreach ($allNodes as $nId => $n) {
                if ($n['depth'] === 3 && $n['parent_id'] === $locId) { $scopeIds[$nId] = true; }
            }
        } else {
            // TALUK/FIRKA (or anything deeper/shallower) — walk up to the
            // nearest DISTRICT ancestor, same fallback as getMsAssignedDistricts().
            $cur = $node;
            while ($cur !== null && $cur['depth'] !== 3) {
                $cur = $cur['parent_id'] !== null ? ($allNodes[$cur['parent_id']] ?? null) : null;
            }
            if ($cur !== null) { $scopeIds[$cur['id']] = true; }
        }
    }

    $result = [];
    foreach (array_keys($scopeIds) as $id) {
        $n = $allNodes[$id] ?? null;
        if (!$n) { continue; }
        $result[] = ['id' => $n['id'], 'name' => $n['name'], 'depth' => $n['depth']];
    }
    usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));

    return $result;
}
