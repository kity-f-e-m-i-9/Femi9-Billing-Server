<?php
// webhook.php

// Set expected API key
$expectedApiKey = '1234567890';

// Get all request headers
$headers = getallheaders();

// Check if 'apikey' header is present and valid
if (!isset($headers['apikey']) || $headers['apikey'] !== $expectedApiKey) {
    http_response_code(401); // Unauthorized
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized: Invalid or missing API key.'
    ]);
    exit;
}

// Read the raw POST body
$rawPayload = file_get_contents("php://input");

// Decode the JSON payload
$data = json_decode($rawPayload, true);

// Log or handle the webhook data (example: log to a file)
file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - Payload: " . $rawPayload . PHP_EOL, FILE_APPEND);

// Respond to webhook sender
http_response_code(200); // OK
echo json_encode([
    'status' => 'success',
    'message' => 'Webhook received successfully.',
    'received_data' => $data
]);
?>
