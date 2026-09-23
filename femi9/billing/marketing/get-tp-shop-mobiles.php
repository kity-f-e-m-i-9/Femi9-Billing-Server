<?php
// AJAX: mobile numbers of every shop a given TP already has on their own
// "Manage Shop" (shop table, onboard_userTYPE='territory_partner') — used by
// add_order.php's Get Order form to highlight, BEFORE the DM picks a shop,
// which of their own ms_shop entries would be reused/merged into an existing
// TP shop record (see OrderTpBridge.php's mobile-number dedupe) instead of
// creating a brand new one.
include("checksession.php");
include("config.php");
require_once("include/AssignedLocations.php");
error_reporting(0);
header('Content-Type: application/json');

$tp_id = (int)($_GET['tp_id'] ?? 0);

// Same ownership scoping as view-tp-shops.php — only a TP that actually
// covers part of THIS DM's own assigned territory, never an arbitrary id.
$assignedDistricts = getMsAssignedDistricts($db_conn, (int)$markeingSTFID);
$allTalukIds = [];
foreach ($assignedDistricts as $d) {
    foreach ($d['taluks'] as $t) { $allTalukIds[] = $t['id']; }
}

$allowed = false;
if ($tp_id > 0 && !empty($allTalukIds)) {
    $talukIdList = implode(',', array_map('intval', $allTalukIds));
    $chk = $db_conn->query(
        "SELECT 1 FROM territory_partner_locations tpl
         JOIN partner_location_nodes n ON n.id = tpl.location_id
         JOIN territory_partners tp ON tp.id = tpl.territory_partner_id
         WHERE tpl.territory_partner_id = $tp_id AND n.parent_id IN ($talukIdList) AND tp.is_active = 1
         LIMIT 1"
    );
    $allowed = $chk && $chk->num_rows > 0;
}

if (!$allowed) {
    echo json_encode(['success' => false, 'mobiles' => []]);
    exit;
}

$mobiles = [];
$res = $db_conn->query(
    "SELECT DISTINCT TRIM(mobile_number) AS mobile FROM shop
     WHERE onboard_userID = $tp_id AND onboard_userTYPE = 'territory_partner'
       AND deleted_at IS NULL AND mobile_number IS NOT NULL AND mobile_number != ''"
);
if ($res) {
    while ($row = $res->fetch_assoc()) { $mobiles[] = $row['mobile']; }
}

echo json_encode(['success' => true, 'mobiles' => $mobiles]);
