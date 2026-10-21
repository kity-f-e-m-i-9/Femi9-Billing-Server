<?php
// List of company-defined Zones (see company/include/PartnerZones.php) for
// the marketing staff "Fill from Zone" quick-select — lets an admin pick a
// whole Zone instead of hand-picking each of its districts one by one.
include("checksession.php");
require_once("include/PartnerZones.php");
header('Content-Type: application/json');
error_reporting(0);

ensurePartnerZonesTables($db_conn);

$zones = $db_conn->query("SELECT id, name FROM partner_zones ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
echo json_encode(array_map(fn($z) => ['id' => (int)$z['id'], 'name' => $z['name']], $zones));
