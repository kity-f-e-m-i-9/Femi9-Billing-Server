<?php
// List of company-defined Zones (see company/include/PartnerZones.php) for
// the marketing staff "Fill from Zone" quick-select — lets an admin pick a
// whole Zone instead of hand-picking each of its districts one by one.
//
// When manager_id + depth are given, the list is scoped to that manager's
// own assigned area — same rule get-ms-location-options.php uses for the
// district list itself (a district belongs to the manager if it's one of
// their own assigned locations, or its parent is) — so e.g. a DM under a
// given ASM only sees that ASM's own zone(s), not every zone in the company.
include("checksession.php");
require_once("include/PartnerZones.php");
header('Content-Type: application/json');
error_reporting(0);

ensurePartnerZonesTables($db_conn);

$manager_id = (int)($_GET['manager_id'] ?? 0);
$depth      = (int)($_GET['depth'] ?? 0);

if ($manager_id && $depth) {
    $stmt = $db_conn->prepare("
        SELECT DISTINCT z.id, z.name
        FROM partner_zones z
        JOIN partner_zone_districts zd ON zd.zone_id = z.id
        JOIN partner_location_nodes pln ON pln.id = zd.district_node_id AND pln.depth = ? AND pln.is_active = 1
        WHERE pln.id IN (SELECT location_id FROM marketing_staff_locations WHERE ms_id = ?)
           OR pln.parent_id IN (SELECT location_id FROM marketing_staff_locations WHERE ms_id = ?)
        ORDER BY z.name ASC
    ");
    $stmt->bind_param("iii", $depth, $manager_id, $manager_id);
    $stmt->execute();
    $zones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $zones = $db_conn->query("SELECT id, name FROM partner_zones ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
}
echo json_encode(array_map(fn($z) => ['id' => (int)$z['id'], 'name' => $z['name']], $zones));
