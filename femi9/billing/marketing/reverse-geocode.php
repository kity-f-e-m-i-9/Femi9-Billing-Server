<?php
include("checksession.php");
error_reporting(0);
header('Content-Type: application/json');

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;

if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coordinates']);
    exit;
}

$apiKey = $_ENV['GOOGLE_GEOCODING_API_KEY'] ?? '';
if ($apiKey === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Geocoding not configured']);
    exit;
}

$url = 'https://maps.googleapis.com/maps/api/geocode/json?latlng=' . urlencode($lat . ',' . $lng) . '&key=' . urlencode($apiKey);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
// Bundled CA cert — avoids relying on the host server's (often outdated) system CA store.
curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/../shared/cacert.pem');
$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Geocoding request failed', 'detail' => $curlError]);
    exit;
}

$data = json_decode($response, true);

if (!isset($data['status']) || $data['status'] !== 'OK' || empty($data['results'])) {
    echo json_encode(['name' => $lat . ', ' . $lng, 'postcode' => '']);
    exit;
}

$result = $data['results'][0];
$name = $result['formatted_address'] ?? ($lat . ', ' . $lng);
$postcode = '';

if (!empty($result['address_components'])) {
    foreach ($result['address_components'] as $component) {
        if (in_array('postal_code', $component['types'], true)) {
            $postcode = $component['long_name'];
            break;
        }
    }
}

echo json_encode(['name' => $name, 'postcode' => $postcode]);
