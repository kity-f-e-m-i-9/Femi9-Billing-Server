<?php
ob_start();
include("checksession.php");
include("config.php");
require_once("include/TeamSubtree.php");

$allowedIds = getMsSubtreeIds($db_conn, (int)$markeingSTFID);
$allowedMap = array_flip($allowedIds);

$idsParam = $_GET['ms_ids'] ?? '';
$requested = array_filter(array_map('intval', explode(',', $idsParam)));
$ids = array_values(array_filter($requested, fn($id) => isset($allowedMap[$id])));

$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';
$fromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) ? $fromDate : '';
$toDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) ? $toDate : '';
$hasDateFilter = ($fromDate !== '' && $toDate !== '');

$file = "Team-Shops.csv";
$csv_content = "Shop Name,Owner (Marketing Staff),Mobile Number,State,District,Taluk,Category,Get Order,No Order\n";

if (!empty($ids)) {
    $idList = implode(',', $ids);
    $shopDateWhere = $hasDateFilter ? " AND DATE(s.created_at) BETWEEN '$fromDate' AND '$toDate'" : '';
    $stmt = $db_conn->query("
        SELECT s.id, s.name AS shop_name, s.mobile_number, s.state_name, s.district_name, s.taluk_name, s.shop_cat,
               ms.ms_name AS owner_name
        FROM ms_shop s
        JOIN marketing_staff ms ON ms.id = s.ms_id
        WHERE s.ms_id IN ($idList)$shopDateWhere
        ORDER BY ms.ms_name ASC, s.name ASC
    ");
    $shops = $stmt ? $stmt->fetch_all(MYSQLI_ASSOC) : [];

    $gotMap = []; $noMap = [];
    if (!empty($shops)) {
        $shopIdList = implode(',', array_column($shops, 'id'));
        $orderDateWhere = $hasDateFilter ? " AND order_date BETWEEN '$fromDate' AND '$toDate'" : '';
        $resGot = $db_conn->query("SELECT shop_id, COUNT(DISTINCT order_id) AS cnt FROM ms_orders WHERE shop_id IN ($shopIdList) AND new_order='yes'$orderDateWhere GROUP BY shop_id");
        if ($resGot) { while ($r = $resGot->fetch_assoc()) { $gotMap[(int)$r['shop_id']] = (int)$r['cnt']; } }
        $resNo = $db_conn->query("SELECT shop_id, COUNT(*) AS cnt FROM ms_orders WHERE shop_id IN ($shopIdList) AND new_order='no'$orderDateWhere GROUP BY shop_id");
        if ($resNo) { while ($r = $resNo->fetch_assoc()) { $noMap[(int)$r['shop_id']] = (int)$r['cnt']; } }
    }

    foreach ($shops as $shop) {
        $gotCount = $gotMap[(int)$shop['id']] ?? 0;
        $noCount  = $noMap[(int)$shop['id']] ?? 0;
        $csv_content .= '"' . str_replace('"', '""', $shop['shop_name']) . '",' .
                         '"' . str_replace('"', '""', $shop['owner_name']) . '",' .
                         '"' . $shop['mobile_number'] . '",' .
                         '"' . str_replace('"', '""', $shop['state_name']) . '",' .
                         '"' . str_replace('"', '""', $shop['district_name']) . '",' .
                         '"' . str_replace('"', '""', $shop['taluk_name']) . '",' .
                         '"' . str_replace('"', '""', $shop['shop_cat']) . '",' .
                         '"' . $gotCount . '",' .
                         '"' . $noCount . "\"\n";
    }
}

ob_end_clean();

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$file");
echo $csv_content;
exit;
