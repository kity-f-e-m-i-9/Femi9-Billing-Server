<?php include("checksession.php");
include("config.php");
error_reporting(0);
require_once __DIR__ . "/../shared/ShopLocationChangeRequest.php";
header('Content-Type: application/json');

function respond(bool $ok, string $message = ''): void
{
	echo json_encode(['success' => $ok, 'message' => $message]);
	exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['request_location_change'])) {
	respond(false, 'Invalid request.');
}

ensureShopLocationChangeRequestsTable($db_conn);

$shop_id = (int)($_POST['shop_id'] ?? 0);
$ms_id = (int)($markeingSTFID ?? 0);
if ($shop_id <= 0 || $ms_id <= 0) {
	respond(false, 'Invalid request.');
}

$shopRes = mysqli_query($db_conn, "SELECT district_name, district_node_id, location_recapture_count FROM ms_shop WHERE id='$shop_id' LIMIT 1");
$shopRow = $shopRes ? mysqli_fetch_assoc($shopRes) : null;
if (!$shopRow) {
	respond(false, 'Shop not found.');
}
if ((int)$shopRow['location_recapture_count'] < 2) {
	respond(false, 'This shop has not reached the recapture limit yet.');
}

// Only one open request per shop at a time — a DM mashing the button
// shouldn't pile up duplicate rows for the BDM to sift through.
$dupRes = mysqli_query($db_conn, "SELECT id FROM ms_shop_location_requests WHERE shop_id='$shop_id' AND status='pending' LIMIT 1");
if ($dupRes && mysqli_num_rows($dupRes) > 0) {
	respond(true); // already pending — treat as success, nothing more to do
}

// district_name on ms_shop is a free-text field on edit-ss.php (unlike
// add_ss.php's picker) — a DM can type "Erode"/"ERODE"/"Erode " there, which
// would silently never match any BDM's assigned district name and the
// request would only ever surface in Company's unscoped view. Resolve the
// CANONICAL name from district_node_id (set from the picker at Add Shop
// time, and independent of whatever free text is currently in
// district_name) instead — the same partner_location_nodes source
// getBdmAssignedDistrictNames() reads from, so the spellings are guaranteed
// to match. Falls back to the free-text column only for a shop added before
// district_node_id existed.
$canonicalDistrict = $shopRow['district_name'] ?? '';
$nodeId = (int)($shopRow['district_node_id'] ?? 0);
if ($nodeId > 0) {
	$nodeRes = mysqli_query($db_conn, "SELECT name FROM partner_location_nodes WHERE id='$nodeId' LIMIT 1");
	$nodeRow = $nodeRes ? mysqli_fetch_assoc($nodeRes) : null;
	if ($nodeRow && !empty($nodeRow['name'])) {
		$canonicalDistrict = $nodeRow['name'];
	}
}
$district_name = mysqli_real_escape_string($db_conn, $canonicalDistrict);
$ok = mysqli_query($db_conn, "INSERT INTO ms_shop_location_requests (shop_id, ms_id, district_name, status) VALUES ('$shop_id','$ms_id','$district_name','pending')");

respond((bool)$ok, $ok ? '' : 'Could not save — please try again.');
