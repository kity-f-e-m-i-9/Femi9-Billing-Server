<?php
// Given a Zone id, return its district nodes {id, name} — used by the
// "Fill from Zone" button on Add/Edit Marketing Staff's Assign Location
// picker to auto-add every district in that zone (see company/include/PartnerZones.php
// for the zone->district mapping, same one Sales BDM's Zone field already uses).
include("checksession.php");
require_once("include/PartnerZones.php");
header('Content-Type: application/json');
error_reporting(0);

$zone_id = (int)($_GET['zone_id'] ?? 0);
if (!$zone_id) { echo json_encode([]); exit; }

ensurePartnerZonesTables($db_conn);
$ids = getZoneDistrictNodeIds($db_conn, $zone_id);
if (empty($ids)) { echo json_encode([]); exit; }

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$stmt = $db_conn->prepare("SELECT id, name FROM partner_location_nodes WHERE id IN ($placeholders) AND is_active = 1 ORDER BY name ASC");
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']], $rows));
