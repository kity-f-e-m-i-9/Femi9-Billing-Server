<?php
include("checksession.php");
include("config.php");
require_once("include/TeamSubtree.php");
header('Content-Type: application/json');
error_reporting(0);

// Security: only ever return stats for people inside the caller's own
// subtree — a marketing login must never be able to peek at another
// branch's numbers by passing arbitrary ms_ids.
$allowedIds = getMsSubtreeIds($db_conn, (int)$markeingSTFID);
$allowedMap = array_flip($allowedIds);

$idsParam = $_GET['ms_ids'] ?? '';
$requested = array_filter(array_map('intval', explode(',', $idsParam)));
$ids = array_values(array_filter($requested, fn($id) => isset($allowedMap[$id])));

if (empty($ids)) {
    echo json_encode([]);
    exit;
}

$idList = implode(',', $ids);
$stats = [];
foreach ($ids as $id) {
    $stats[$id] = ['shops' => 0, 'gotOrder' => 0, 'noOrder' => 0];
}

$resShops = $db_conn->query("SELECT ms_id, COUNT(*) AS cnt FROM ms_shop WHERE ms_id IN ($idList) GROUP BY ms_id");
if ($resShops) {
    while ($row = $resShops->fetch_assoc()) {
        $stats[(int)$row['ms_id']]['shops'] = (int)$row['cnt'];
    }
}

$resGot = $db_conn->query("SELECT ms_id, COUNT(DISTINCT order_id) AS cnt FROM ms_orders WHERE ms_id IN ($idList) AND new_order='yes' GROUP BY ms_id");
if ($resGot) {
    while ($row = $resGot->fetch_assoc()) {
        $stats[(int)$row['ms_id']]['gotOrder'] = (int)$row['cnt'];
    }
}

$resNo = $db_conn->query("SELECT ms_id, COUNT(*) AS cnt FROM ms_orders WHERE ms_id IN ($idList) AND new_order='no' GROUP BY ms_id");
if ($resNo) {
    while ($row = $resNo->fetch_assoc()) {
        $stats[(int)$row['ms_id']]['noOrder'] = (int)$row['cnt'];
    }
}

echo json_encode($stats);
