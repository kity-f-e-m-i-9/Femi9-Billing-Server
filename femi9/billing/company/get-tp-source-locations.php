<?php
include("checksession.php");
header('Content-Type: application/json');
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'company') {
    echo json_encode(['status' => 'unauthorized', 'sources' => []]); exit;
}

$tp_id = (int)($_GET['tp_id'] ?? 0);
if (!$tp_id) { echo json_encode(['status' => 'error', 'sources' => []]); exit; }

// Get all locations assigned to this TP
$stmt = $db_conn->prepare("SELECT location_id FROM territory_partner_locations WHERE territory_partner_id = ?");
$stmt->bind_param("i", $tp_id);
$stmt->execute();
$tp_locations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($tp_locations)) {
    echo json_encode(['status' => 'no_tp_locations', 'sources' => [], 'message' => 'This Territory Partner has no locations assigned.']);
    exit;
}

$source_map = [];

foreach ($tp_locations as $row) {
    $loc_id = (int)$row['location_id'];

    // Walk up the tree from this TP location to find the nearest CP-assigned ancestor
    $res = $db_conn->query("
        WITH RECURSIVE ancestors AS (
            SELECT id, parent_id, 0 AS steps
            FROM partner_location_nodes
            WHERE id = $loc_id
            UNION ALL
            SELECT n.id, n.parent_id, a.steps + 1
            FROM partner_location_nodes n
            INNER JOIN ancestors a ON n.id = a.parent_id
            WHERE a.parent_id IS NOT NULL
        )
        SELECT a.id AS location_id, pln.name AS location_name,
               cp.id AS cp_db_id, cp.cp_id AS cp_code, cp.name AS cp_name
        FROM ancestors a
        JOIN channel_partner_locations cpl ON cpl.location_id = a.id
        JOIN channel_partners cp ON cp.id = cpl.channel_partner_id
        JOIN partner_location_nodes pln ON pln.id = a.id
        ORDER BY a.steps ASC
        LIMIT 1
    ");

    if ($res && ($found = $res->fetch_assoc())) {
        $cid = (int)$found['cp_db_id'];
        if (!isset($source_map[$cid])) {
            $source_map[$cid] = [
                'location_id'   => (int)$found['location_id'],
                'location_name' => $found['location_name'],
                'cp_db_id'      => $cid,
                'cp_code'       => $found['cp_code'],
                'cp_name'       => $found['cp_name'],
            ];
        }
    }
}

if (empty($source_map)) {
    echo json_encode(['status' => 'no_cp_found', 'sources' => [], 'message' => 'No Channel Partner location covers this Territory Partner\'s territory. Assign a CP first.']);
    exit;
}

echo json_encode(['status' => 'ok', 'sources' => array_values($source_map)]);
