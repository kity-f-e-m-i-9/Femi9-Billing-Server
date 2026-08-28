<?php
/**
 * Resolves the actual district a Firka (or any partner_location_nodes row)
 * falls under, by walking parent_id up the tree to district depth.
 *
 * This backs territory_partners.assigned_district ONLY — a separate concept
 * from branch_district/delivery_district (real postal address fields a TP's
 * Billing/Shipping address can legitimately name a totally different place
 * from their actual sales territory). assigned_district auto-tracks
 * whichever Firka the TP is picked for in territory_partner_locations, and
 * is what salesbdm/include/BdmTpScope.php's getBdmAssignedTpIds() matches
 * a Sales BDM's districts against — never branch_district/delivery_district.
 */
function getDistrictNameForLocation(mysqli $db, int $locationId): ?string
{
    $districtDepthRow = $db->query("SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1")->fetch_assoc();
    if (!$districtDepthRow) return null;
    $districtDepth = (int)$districtDepthRow['depth'];

    $currentId = $locationId;
    $guard = 0;
    while ($currentId > 0 && $guard < 10) {
        $stmt = $db->prepare("SELECT name, depth, parent_id FROM partner_location_nodes WHERE id = ?");
        $stmt->bind_param('i', $currentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return null;
        if ((int)$row['depth'] === $districtDepth) {
            return $row['name'];
        }
        $currentId = (int)$row['parent_id'];
        $guard++;
    }
    return null;
}

/**
 * Given the Firka location_ids a TP is being assigned (from the Add/Edit TP
 * location picker), returns the district to store in assigned_district — or
 * null if it can't be determined (no locations picked, or they don't
 * resolve to a district). If the picked locations span more than one
 * district, the FIRST picked location's district wins — the common case is
 * a single Firka per TP.
 */
function resolveAssignedDistrictFromLocations(mysqli $db, array $locationIds): ?string
{
    foreach ($locationIds as $lid) {
        $name = getDistrictNameForLocation($db, (int)$lid);
        if ($name !== null) return $name;
    }
    return null;
}
