<?php
// AJAX shop search for order-confirm-shop.php's "No, pick a different shop"
// path — 2026-12-01, user-requested. Scoped to this TP's own shop list,
// same convention get-shop-list-by-district.php already uses
// (onboard_userID/onboard_userTYPE). Filters by every word in the query
// having to appear somewhere in the shop name (order-independent, so "medi
// santosh" still matches "Santosh Medicals") — a plain substring match
// would miss that, and a true fuzzy/edit-distance match is unnecessary
// complexity for a TP's own, usually-small shop list.
include("checksession.php");
include("config.php");
header('Content-Type: application/json');
error_reporting(0);

$tp_id = (string)$Login_user_IDvl;
$q = trim($_GET['q'] ?? '');
if ($q === '') { echo json_encode([]); exit; }

$words = array_values(array_filter(preg_split('/\s+/', $q)));
if (empty($words)) { echo json_encode([]); exit; }

$conditions = [];
$types = 'ss';
$params = [$tp_id, 'territory_partner'];
foreach ($words as $w) {
    $conditions[] = 's.name LIKE ?';
    $types .= 's';
    $params[] = '%' . $w . '%';
}
$whereWords = implode(' AND ', $conditions);

$stmt = $db_conn->prepare("
    SELECT s.id, s.name, s.mobile_number, COALESCE(dn.name, '') AS district
    FROM shop s
    LEFT JOIN partner_location_nodes dn ON dn.id = s.district_id
    WHERE s.onboard_userID = ? AND s.onboard_userTYPE = ? AND s.deleted_at IS NULL
      AND ($whereWords)
    ORDER BY s.name ASC
    LIMIT 25
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$out = [];
foreach ($rows as $r) {
    $out[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'mobile' => $r['mobile_number'], 'district' => $r['district']];
}
echo json_encode($out);
