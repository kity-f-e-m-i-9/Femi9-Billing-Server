<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner_edit');
require_once("include/PartnerZones.php");
error_reporting(0);
ensurePartnerZonesTables($db_conn);

function redirectWithMsg(string $key, string $msg): void {
    $_SESSION[$key] = $msg;
    header('Location: manage-zones.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirectWithMsg('errorMessage', 'Invalid request.'); }

$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $zoneId = (int)($_POST['zone_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $districtIds = array_values(array_unique(array_filter(array_map('intval', $_POST['district_ids'] ?? []))));

    if ($name === '') { redirectWithMsg('errorMessage', 'Zone name is required.'); }

    // Reject any district already claimed by a DIFFERENT zone — the picker
    // disables these client-side, but the server never trusts that alone.
    if (!empty($districtIds)) {
        $placeholders = implode(',', array_fill(0, count($districtIds), '?'));
        $types = str_repeat('i', count($districtIds));
        $params = $districtIds;
        $sql = "SELECT zd.district_node_id, z.name AS zone_name FROM partner_zone_districts zd
                JOIN partner_zones z ON z.id = zd.zone_id
                WHERE zd.district_node_id IN ($placeholders)";
        if ($zoneId > 0) { $sql .= " AND zd.zone_id != ?"; $types .= 'i'; $params[] = $zoneId; }
        $stmt = $db_conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $conflict = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($conflict) {
            redirectWithMsg('errorMessage', 'One of the selected districts is already assigned to "' . $conflict['zone_name'] . '". Remove it and try again.');
        }
    }

    if ($zoneId > 0) {
        $stmt = $db_conn->prepare("UPDATE partner_zones SET name = ? WHERE id = ?");
        $stmt->bind_param('si', $name, $zoneId);
        if (!$stmt->execute()) {
            $stmt->close();
            redirectWithMsg('errorMessage', 'A zone with that name already exists.');
        }
        $stmt->close();
        $del = $db_conn->prepare("DELETE FROM partner_zone_districts WHERE zone_id = ?");
        $del->bind_param('i', $zoneId);
        $del->execute();
        $del->close();
    } else {
        $stmt = $db_conn->prepare("INSERT INTO partner_zones (name) VALUES (?)");
        $stmt->bind_param('s', $name);
        if (!$stmt->execute()) {
            $stmt->close();
            redirectWithMsg('errorMessage', 'A zone with that name already exists.');
        }
        $zoneId = $stmt->insert_id;
        $stmt->close();
    }

    if (!empty($districtIds)) {
        $ins = $db_conn->prepare("INSERT IGNORE INTO partner_zone_districts (zone_id, district_node_id) VALUES (?, ?)");
        foreach ($districtIds as $did) {
            $ins->bind_param('ii', $zoneId, $did);
            $ins->execute();
        }
        $ins->close();
    }

    redirectWithMsg('successMessage', 'Zone saved.');
} elseif ($action === 'delete') {
    $zoneId = (int)($_POST['zone_id'] ?? 0);
    if ($zoneId > 0) {
        $del1 = $db_conn->prepare("DELETE FROM partner_zone_districts WHERE zone_id = ?");
        $del1->bind_param('i', $zoneId);
        $del1->execute();
        $del1->close();
        $del2 = $db_conn->prepare("DELETE FROM partner_zones WHERE id = ?");
        $del2->bind_param('i', $zoneId);
        $del2->execute();
        $del2->close();
    }
    redirectWithMsg('successMessage', 'Zone deleted.');
} else {
    redirectWithMsg('errorMessage', 'Unknown action.');
}
