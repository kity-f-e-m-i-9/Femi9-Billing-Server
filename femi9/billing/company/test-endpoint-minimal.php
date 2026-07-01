<?php
/**
 * Minimal Test Endpoint
 * Tests if basic JSON response works
 */

// Catch ALL output
ob_start();

header('Content-Type: application/json; charset=utf-8');

session_start();

// Simple test response
$response = [
    'success' => true,
    'message' => 'Endpoint is reachable',
    'session_check' => [
        'user_id' => $_SESSION['LOGIN_USER_ID'] ?? 'NOT SET',
        'user_type' => $_SESSION['LOGIN_USER_TYPE'] ?? 'NOT SET'
    ],
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'post_data' => $_POST,
    'get_data' => $_GET
];

// Clear any output
ob_end_clean();

echo json_encode($response, JSON_PRETTY_PRINT);
exit;
?>
