<?php
require_once "include/db-connect.php";
require_once "../shared/PartnerLocationDistrict.php";

$res = $db_conn->query("
    SELECT tp.id, GROUP_CONCAT(tpl.location_id ORDER BY tpl.id) AS loc_ids
    FROM territory_partners tp
    LEFT JOIN territory_partner_locations tpl ON tpl.territory_partner_id = tp.id
    WHERE tp.deleted_at IS NULL
    GROUP BY tp.id
");

$updated = 0; $skipped = 0; $noLocation = 0;
$stmt = $db_conn->prepare("UPDATE territory_partners SET assigned_district = ? WHERE id = ?");
while ($row = $res->fetch_assoc()) {
    if (empty($row['loc_ids'])) { $noLocation++; continue; }
    $locIds = array_map('intval', explode(',', $row['loc_ids']));
    $district = resolveAssignedDistrictFromLocations($db_conn, $locIds);
    if ($district === null) { $skipped++; continue; }
    $tpId = (int)$row['id'];
    $stmt->bind_param('si', $district, $tpId);
    $stmt->execute();
    $updated++;
}
echo "updated: $updated, skipped: $skipped, no location: $noLocation";
