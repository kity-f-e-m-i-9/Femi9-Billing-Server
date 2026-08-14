<?php
/**
 * Get TP Bonus Filter Data - Dynamic Filter Dropdown
 * Territory Partner counterpart of get-bonus-filter-data.php.
 *
 * Provides the TP Name dropdown for tp-bonus-advance-payments.php.
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once("checksession.php");
require_once("config.php");

$logged_user_id   = $_SESSION['LOGIN_USER_ID']   ?? '';
$logged_user_type = $_SESSION['LOGIN_USER_TYPE'] ?? '';

if (empty($logged_user_id) || empty($logged_user_type)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

if ($db_conn) {
    mysqli_set_charset($db_conn, 'utf8mb4');
}

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'data' => []];

if ($action === 'get_users') {
    $users = [];

    $query = "SELECT tp.tp_id AS id, tp.name FROM territory_partners tp ORDER BY tp.name ASC";
    $result = $db_conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'id'   => $row['id'],
                'name' => $row['name'] . ' (' . $row['id'] . ')',
                'type' => 'territory_partner',
            ];
        }
    }

    $response['data']    = $users;
    $response['success'] = true;
} else {
    $response['error'] = 'Invalid action';
}

echo json_encode($response);

if (isset($db_conn) && $db_conn) {
    $db_conn->close();
}
