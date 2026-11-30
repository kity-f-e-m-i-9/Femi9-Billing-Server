<?php
// Shop Name options for the Invoice - Shop page's District->Shop cascade.
// 2026-11-20, user-requested (a TP covering Firkas in more than one district
// had every shop lumped into one unfiltered dropdown) — originally returned
// ONLY shops whose shop.district_id matched the clicked district.
//
// 2026-11-21: found that shop.district_id is stale for a large number of
// shops across the whole TP base (confirmed via a full scan: 133 of 195
// active TPs, 5,557 shop rows — e.g. a TP currently assigned to Erode +
// Namakkal Firkas had 116 of her own shops stored under district_id=8
// "Vellore", a district she isn't even assigned to any more, almost
// certainly left over from before an earlier Firka reassignment). Filtering
// strictly by district_id therefore silently hid real, invoiceable shops —
// clicking "Namakkal" showed zero shops even though she has shops there.
// Fixed by ALWAYS returning every one of this TP's own shops regardless of
// district, split into two groups so the matching ones still sort to the
// top: 'matched' (district_id = the clicked district) and 'other' (every
// other shop of hers, old/stale district_id and all) — nothing is ever
// hidden because of this known data issue, the clicked district just
// decides the ordering, not the visibility.
require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
require_once("include/TpDistrictScope.php");
header('Content-Type: application/json');
error_reporting(0);

$district_id = (int)($_GET['district_id'] ?? 0);
if (!$district_id) {
    echo json_encode(['matched' => [], 'other' => []]);
    exit;
}

$allowedDistricts = getTpAssignedDistricts($db_conn, (int)$Login_user_IDvl);
$allowedIds = array_column($allowedDistricts, 'id');
if (!in_array($district_id, $allowedIds, true)) {
    echo json_encode(['matched' => [], 'other' => []]);
    exit;
}

$pendingOrderShops = [];
$stmtPending = $db_conn->prepare("SELECT DISTINCT shop_id FROM tp_orders WHERE tp_id=? AND new_order='yes' AND voided_at IS NULL AND (invoiced_inv_id IS NULL OR invoiced_inv_id='')");
$stmtPending->bind_param('i', $Login_user_IDvl);
$stmtPending->execute();
foreach ($stmtPending->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $pendingOrderShops[(int)$row['shop_id']] = true;
}
$stmtPending->close();

// deleted_at IS NULL — same exclusion shop-manage.php's own listing already
// applies; without it a deleted shop (not shown in Manage Shop at all)
// could still be picked here and invoiced. 2026-11-21, found while
// verifying this dropdown's shop set matches Manage Shop's.
$stmt = $db_conn->prepare("SELECT id, temp_id, name, mobile_number, address, district_id FROM shop WHERE onboard_userID=? AND onboard_userTYPE=? AND deleted_at IS NULL ORDER BY name ASC");
$stmt->bind_param('is', $Login_user_IDvl, $Login_user_TYPEvl);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$matched = [];
$other = [];
foreach ($rows as $r) {
    if (isset($pendingOrderShops[(int)$r['id']])) { continue; }
    $shopRow = [
        'temp_id' => $r['temp_id'],
        'name'    => ucwords($r['name']),
        'mobile'  => $r['mobile_number'],
        'address' => ucwords((string)$r['address']),
    ];
    if ((int)$r['district_id'] === $district_id) {
        $matched[] = $shopRow;
    } else {
        $other[] = $shopRow;
    }
}
echo json_encode(['matched' => $matched, 'other' => $other]);
