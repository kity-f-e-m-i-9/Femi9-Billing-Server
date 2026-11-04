<?php
// A zone's own district list (id + name), for salesbdm_edit.php's "Zone"
// field to auto-fill the Assign Location picker the moment a zone is
// picked -- read-only lookup, never writes back to partner_zone_districts,
// so freely adding/removing from the picker afterward never touches the
// zone's own district composition.
include("checksession.php");
require_once("include/PartnerZones.php");
header('Content-Type: application/json');
error_reporting(0);

$zone_id = (int)($_GET['zone_id'] ?? 0);
if (!$zone_id) { echo json_encode([]); exit; }

$ids = getZoneDistrictNodeIds($db_conn, $zone_id);
if (empty($ids)) { echo json_encode([]); exit; }

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$stmt = $db_conn->prepare("SELECT id, name FROM partner_location_nodes WHERE id IN ($placeholders) ORDER BY name");
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']], $rows));
