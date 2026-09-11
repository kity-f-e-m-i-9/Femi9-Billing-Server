<?php include("checksession.php");
include("config.php");
error_reporting(0);
require_once __DIR__ . "/../shared/ShopLocationChangeRequest.php";
require_once __DIR__ . "/include/AssignedLocations.php";
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

$shopRes = mysqli_query($db_conn, "SELECT district_name, taluk_name, location_recapture_count FROM ms_shop WHERE id='$shop_id' LIMIT 1");
$shopRow = $shopRes ? mysqli_fetch_assoc($shopRes) : null;
if (!$shopRow) {
	respond(false, 'Shop not found.');
}
if ((int)$shopRow['location_recapture_count'] < 2) {
	respond(false, 'This shop has not reached the recapture limit yet.');
}

// Route by the REQUESTING DM's own assigned district (marketing_staff_locations,
// via getMsAssignedDistricts() — the same source add_ss.php's district picker
// uses), not the shop's own stored district_name — that field is free-text on
// edit-ss.php (unlike add_ss.php's picker) and a DM can type "Erode"/"ERODE"/
// "Erode " there, which would silently never match any BDM's assigned
// district name and the request would only ever surface in Company's
// unscoped view. A DM's own assignment is always drawn from the same clean
// partner_location_nodes tree getBdmAssignedDistrictNames() reads from, so
// the spellings are guaranteed to match.
$canonicalDistrict = '';
$assignedDistricts = getMsAssignedDistricts($db_conn, $ms_id);
if (count($assignedDistricts) === 1) {
	$canonicalDistrict = $assignedDistricts[0]['name'];
} elseif (count($assignedDistricts) > 1) {
	// DM assigned to more than one district — narrow down using the shop's
	// own taluk/district text as a best-effort tie-breaker, since those are
	// still meaningful hints even though they're free text.
	$shopTaluk = mb_strtolower(trim($shopRow['taluk_name'] ?? ''));
	$shopDistrict = mb_strtolower(trim($shopRow['district_name'] ?? ''));
	foreach ($assignedDistricts as $d) {
		foreach ($d['taluks'] as $t) {
			if (mb_strtolower(trim($t['name'])) === $shopTaluk) { $canonicalDistrict = $d['name']; break 2; }
		}
	}
	if ($canonicalDistrict === '') {
		foreach ($assignedDistricts as $d) {
			if (mb_strtolower(trim($d['name'])) === $shopDistrict) { $canonicalDistrict = $d['name']; break; }
		}
	}
	if ($canonicalDistrict === '') {
		$canonicalDistrict = $assignedDistricts[0]['name'];
	}
}
// Fallback for the rare case a DM has no location assignment at all — fall
// back to whatever's in the shop's own free-text district_name so the
// request still has something to route on (worst case it only surfaces in
// Company's unscoped view instead of a specific BDM's).
if ($canonicalDistrict === '') {
	$canonicalDistrict = $shopRow['district_name'] ?? '';
}
$district_name = mysqli_real_escape_string($db_conn, $canonicalDistrict);

// Only one open request per shop at a time — the "does a pending one already
// exist" check and the insert happen as a single atomic statement (rather
// than a separate SELECT then INSERT) so two near-simultaneous clicks/tabs
// can't both pass the check and both insert a duplicate pending row.
$ok = mysqli_query($db_conn, "
	INSERT INTO ms_shop_location_requests (shop_id, ms_id, district_name, status)
	SELECT '$shop_id', '$ms_id', '$district_name', 'pending' FROM DUAL
	WHERE NOT EXISTS (
		SELECT 1 FROM ms_shop_location_requests WHERE shop_id='$shop_id' AND status='pending'
	)
");

respond((bool)$ok, $ok ? '' : 'Could not save — please try again.');
