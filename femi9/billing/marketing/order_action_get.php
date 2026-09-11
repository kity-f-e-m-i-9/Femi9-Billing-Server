<?php include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

include("RemoveSpecialChar.php");
include_once("include/OrderTpBridge.php");

if (isset($_REQUEST['add_order_get'])) {


	$ms_id=$_POST["ms_id"];
	$order_date=$_POST["order_date"];
	$order_id=$_POST["order_id"];
	$shop_id=$_POST["shop_id"];

	$marketing_tool=$_POST["marketing_tool"];
	$marketing_tool=RemoveSpecialChar($marketing_tool);

	$latitude=isset($_POST["latitude"]) && $_POST["latitude"]!=='' ? floatval($_POST["latitude"]) : null;
	$longitude=isset($_POST["longitude"]) && $_POST["longitude"]!=='' ? floatval($_POST["longitude"]) : null;

	// Looks up an address string via Google's forward Geocoding API — same
	// key/cacert setup as reverse-geocode.php (that one goes lat/lng->address,
	// this goes address->lat/lng). Returns [lat, lng] on a confident match,
	// null if the address couldn't be found/geocoded at all.
	function geocodeShopAddress(string $address): ?array {
		$apiKey = $_ENV['GOOGLE_GEOCODING_API_KEY'] ?? '';
		if ($apiKey === '' || trim($address) === '') return null;
		$url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . urlencode($apiKey);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		$bundledCaCert = __DIR__ . '/../shared/cacert.pem';
		if (file_exists($bundledCaCert)) { curl_setopt($ch, CURLOPT_CAINFO, $bundledCaCert); }
		$response = curl_exec($ch);
		curl_close($ch);
		if ($response === false) return null;
		$data = json_decode($response, true);
		if (!isset($data['status']) || $data['status'] !== 'OK' || empty($data['results'][0]['geometry']['location'])) return null;
		$loc = $data['results'][0]['geometry']['location'];
		return [(float)$loc['lat'], (float)$loc['lng']];
	}

	// Server-side mirror of add_order.php's client-side 75m check — never
	// trust the browser alone, since a POST here can bypass the JS entirely.
	// A shop with NO saved lat/lng gets geocoded from its own stored address
	// (Google Geocoding, not the DM's GPS) — ~73% of existing shops have no
	// location (added before this feature existed), so hard-blocking every
	// one of them would stop most Get Orders company-wide. A shop whose
	// address genuinely can't be geocoded (too vague/wrong) still blocks,
	// asking the DM to edit the shop and manually capture its location.
	// Confirmed 2026-09-11 after a 3.5km-away order went through unchecked
	// against exactly such a shop (the gap this whole feature closes).
	$shop_id_esc = (int)$shop_id;
	if ($latitude === null || $longitude === null) {
		$_SESSION['errorMessage'] = "Could not get your current location. Please enable location access and try again — Get Order requires you to be at the shop.";
		header("Location: add_order.php");
		exit;
	}
	$shopLocRes = mysqli_query($db_conn, "SELECT address, state_name, district_name, taluk_name, pincode, latitude, longitude FROM ms_shop WHERE id='$shop_id_esc' LIMIT 1");
	$shopLocRow = $shopLocRes ? mysqli_fetch_assoc($shopLocRes) : null;
	if (!$shopLocRow || $shopLocRow['latitude'] === null || $shopLocRow['latitude'] === '' || $shopLocRow['longitude'] === null || $shopLocRow['longitude'] === '') {
		$addressParts = array_filter([
			$shopLocRow['address'] ?? '', $shopLocRow['taluk_name'] ?? '',
			$shopLocRow['district_name'] ?? '', $shopLocRow['state_name'] ?? '', $shopLocRow['pincode'] ?? '',
		]);
		$geocoded = geocodeShopAddress(implode(', ', $addressParts));
		if ($geocoded === null) {
			$_SESSION['errorMessage'] = "This shop's address could not be automatically located. Please edit the shop and manually capture its location before taking a Get Order here.";
			header("Location: add_order.php");
			exit;
		}
		[$shopLat, $shopLng] = $geocoded;
		mysqli_query($db_conn, "UPDATE ms_shop SET latitude='" . $shopLat . "', longitude='" . $shopLng . "' WHERE id='$shop_id_esc'");
	} else {
		$shopLat = (float)$shopLocRow['latitude'];
		$shopLng = (float)$shopLocRow['longitude'];
	}
	$R = 6371000;
	$dLat = deg2rad($shopLat - $latitude);
	$dLng = deg2rad($shopLng - $longitude);
	$a = sin($dLat / 2) ** 2 + cos(deg2rad($latitude)) * cos(deg2rad($shopLat)) * sin($dLng / 2) ** 2;
	$distanceMeters = $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
	if ($distanceMeters > 75) {
		$distText = $distanceMeters >= 1000 ? round($distanceMeters / 1000, 2) . " km" : round($distanceMeters) . " m";
		$_SESSION['errorMessage'] = "You are $distText away from this shop's location. Get Order can only be submitted within 75 m of the shop.";
		header("Location: add_order.php");
		exit;
	}

	$latitude_sql=$latitude===null ? "NULL" : "'".$latitude."'";
	$longitude_sql=$longitude===null ? "NULL" : "'".$longitude."'";

	// Assign To TP — validate against active TPs; invalid/missing means "not assigned".
	$tp_id = (int)($_POST["tp_id"] ?? 0);
	if ($tp_id > 0) {
		$tp_id_esc = (int)$tp_id;
		$tpCheck = mysqli_query($db_conn, "select id from territory_partners where id='$tp_id_esc' and is_active=1 limit 1");
		if (!$tpCheck || mysqli_num_rows($tpCheck) === 0) { $tp_id = 0; }
	}
	$tp_id_sql = $tp_id > 0 ? "'".$tp_id."'" : "NULL";

	$product_id = implode("#",$_REQUEST['pr_id']);
$qty = implode("#",$_REQUEST['qty']);
$discount_pct = implode("#", $_REQUEST['discount_percentage'] ?? []);

$product_id_ex = explode ("#",$product_id);
$qty_ex = explode ("#",$qty);
$discount_pct_ex = explode ("#", $discount_pct);

$number = count($product_id_ex);
$insertedLines = []; // pr_id/qty of the lines actually inserted, for the TP bridge below
for ($i=0; $i<=$number; $i++)
{
     $product_id_value = $product_id_ex[$i];
     $qty_value = $qty_ex[$i];
	 $qty_value = RemoveSpecialChar($qty_value);
	 $discount_value = isset($discount_pct_ex[$i]) ? (float)$discount_pct_ex[$i] : 0;
	 if ($discount_value < 0) { $discount_value = 0; }
	 if ($discount_value > 100) { $discount_value = 100; }

	 if($product_id_value!=NULL)
	 {

$select_count_dist="select count(*) as numShop from ms_orders where order_id='$order_id' and pr_id='$product_id_value'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
$result_count_dist=mysqli_fetch_array($fetc_count_dist);
if($result_count_dist['numShop']==0)
	{

        $sql="insert into ms_orders (order_id,shop_id,ms_id,tp_id,order_date,new_order,noorder_reason,marketing_tool,pr_id,qty,discount_percentage,latitude,longitude) values ('$order_id','$shop_id','$ms_id',$tp_id_sql,'$order_date','yes','nil','$marketing_tool',
		'$product_id_value','$qty_value','$discount_value',$latitude_sql,$longitude_sql)";
		mysqli_query($db_conn,$sql);
		$insertedLines[] = ['pr_id' => (int)$product_id_value, 'qty' => (int)$qty_value, 'discount_percentage' => $discount_value];

	}


	 }

}

	// Best-effort mirror into the TP's own field-order pipeline — the
	// ms_orders rows above are already saved regardless of this outcome.
	if ($tp_id > 0 && !empty($insertedLines)) {
		bridgeOrderToTp($db_conn, $tp_id, $ms_id, $shop_id, $order_id, $order_date, $insertedLines);
	}

	$_SESSION['successMessage']="Product order details added successfully!";
	echo "<script>window.location='manage_order_product';</script>";

}
?>