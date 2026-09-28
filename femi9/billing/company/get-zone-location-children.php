<?php
// AJAX: children of one or more location nodes, for Manage Zones' cascading
// Country -> State -> District picker. parent_ids empty/0 means "top level"
// (COUNTRY). District-depth children include which zone (if any) already
// owns them, so the picker can disable/label an already-claimed district —
// except when it's already in THIS zone being edited (exclude_zone_id),
// which should still show as pickable/checked.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("include/PartnerZones.php");
header('Content-Type: application/json');
error_reporting(0);

$parentIdsRaw = $_GET['parent_ids'] ?? '';
$parentIds = array_values(array_filter(array_map('intval', explode(',', $parentIdsRaw))));
$excludeZoneId = (int)($_GET['exclude_zone_id'] ?? 0);

if (empty($parentIds)) {
    // Top level — COUNTRY depth (the shallowest depth in the tree).
    $rows = $db_conn->query("SELECT id, name, depth FROM partner_location_nodes WHERE depth = (SELECT MIN(depth) FROM partner_location_nodes) AND is_active = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
} else {
    $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
    $types = str_repeat('i', count($parentIds));
    $stmt = $db_conn->prepare("SELECT id, name, depth FROM partner_location_nodes WHERE parent_id IN ($placeholders) AND is_active = 1 ORDER BY name ASC");
    $stmt->bind_param($types, ...$parentIds);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$districtDepth = getZoneDistrictDepth($db_conn);
$assignedMap = getAssignedDistrictZoneNames($db_conn);
$excludeIds = $excludeZoneId > 0 ? getZoneDistrictNodeIds($db_conn, $excludeZoneId) : [];

foreach ($rows as &$r) {
    $r['is_district'] = ((int)$r['depth'] === $districtDepth);
    $r['assigned_zone_name'] = null;
    if ($r['is_district'] && isset($assignedMap[(int)$r['id']]) && !in_array((int)$r['id'], $excludeIds, true)) {
        $r['assigned_zone_name'] = $assignedMap[(int)$r['id']];
    }
}
unset($r);

echo json_encode($rows);
