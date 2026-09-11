<?php
// Called from add_order.php right when a DM picks a shop with no saved
// lat/lng, so the distance warning shows immediately on selection instead
// of only after they've filled in products and hit Add (order_action_get.php
// does the same geocode+save at submit time — this just surfaces it earlier
// so a DM who's clearly nowhere near the shop doesn't waste time on a form
// that's going to get rejected anyway).
include("checksession.php");
include("config.php");
error_reporting(0);
header('Content-Type: application/json');

$shop_id = (int)($_GET['shop_id'] ?? 0);
if ($shop_id <= 0) {
    echo json_encode(['error' => 'Invalid shop']);
    exit;
}

$res = mysqli_query($db_conn, "SELECT address, state_name, district_name, taluk_name, pincode, latitude, longitude FROM ms_shop WHERE id='$shop_id' LIMIT 1");
$row = $res ? mysqli_fetch_assoc($res) : null;
if (!$row) {
    echo json_encode(['error' => 'Shop not found']);
    exit;
}

// Already has a saved location — nothing to geocode, just hand it back.
if ($row['latitude'] !== null && $row['latitude'] !== '' && $row['longitude'] !== null && $row['longitude'] !== '') {
    echo json_encode(['lat' => (float)$row['latitude'], 'lng' => (float)$row['longitude'], 'source' => 'saved']);
    exit;
}

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

$addressParts = array_filter([
    $row['address'] ?? '', $row['taluk_name'] ?? '',
    $row['district_name'] ?? '', $row['state_name'] ?? '', $row['pincode'] ?? '',
]);
$geocoded = geocodeShopAddress(implode(', ', $addressParts));

if ($geocoded === null) {
    // Not saved to ms_shop here — order_action_get.php is the single source
    // of truth for actually persisting a shop's location (this endpoint is
    // read-only/preview), so a DM who never submits doesn't leave the shop
    // half-updated from a preview that was never acted on.
    echo json_encode(['error' => 'Could not locate this shop\'s address']);
    exit;
}

echo json_encode(['lat' => $geocoded[0], 'lng' => $geocoded[1], 'source' => 'geocoded']);
