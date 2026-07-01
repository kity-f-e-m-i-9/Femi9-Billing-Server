<?php
/**
 * Get Users By Type - With District Information
 * Femi9 Billing Application
 * 
 * Returns: temp_id, name, mobile_number, district_name
 * 
 * @author Femi9 Development Team
 * @version 5.1 - Added district information
 * @date 2025-12-31
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');

session_start();

include("checksession.php");
include("config.php");

$response = [
    'success' => false,
    'users' => [],
    'message' => ''
];

try {
    // Check database connection
    if (!isset($db_conn) || !$db_conn) {
        throw new Exception('Database connection failed');
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['LOGIN_USER_ID']) || !isset($_SESSION['LOGIN_USER_TYPE'])) {
        throw new Exception('Please login first');
    }
    
    $logged_user_type = $_SESSION['LOGIN_USER_TYPE'];
    
    // Validate request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request');
    }
    
    // Get input
    $user_type = trim($_POST['user_type'] ?? '');
    $company_id = intval($_POST['company_id'] ?? 0);
    
    // Validate
    $allowed_types = ['super_stockiest', 'stockiest', 'distributor', 'super_distributor', 'c_and_f'];
    if (!in_array($user_type, $allowed_types, true)) {
        throw new Exception('Invalid user type');
    }
    
    if ($company_id <= 0) {
        throw new Exception('Invalid company');
    }
    
    $table_name = $user_type;
    
    // Check table exists
    $check_table = $db_conn->query("SHOW TABLES LIKE '$table_name'");
    if (!$check_table || $check_table->num_rows === 0) {
        throw new Exception("Table not found");
    }
    
    // Build WHERE conditions
    $where_conditions = [
        "u.deleted_at IS NULL",
        "u.account_status = 'active'"
    ];
    
    // Company-specific filter
    if ($logged_user_type === 'company') {
        $where_conditions[] = "u.onboard_userTYPE = 'company'";
        $where_conditions[] = "u.onboard_userID = 'company'";
    }
    
    $where_sql = implode(" AND ", $where_conditions);
    
    // Query with district JOIN
    $query = "SELECT 
                u.temp_id, 
                u.name, 
                u.mobile_number, 
                u.email, 
                u.district_id,
                d.dist_name as district_name
              FROM `$table_name` u
              LEFT JOIN district d ON u.district_id = d.id
              WHERE $where_sql
              ORDER BY u.name ASC 
              LIMIT 1000";
    
    $result = $db_conn->query($query);
    
    if (!$result) {
        throw new Exception('Query failed: ' . $db_conn->error);
    }
    
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'temp_id' => $row['temp_id'],
            'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
            'mobile_number' => $row['mobile_number'] ?? '',
            'email' => $row['email'] ?? '',
            'district_id' => $row['district_id'] ?? '',
            'district_name' => $row['district_name'] ? htmlspecialchars($row['district_name'], ENT_QUOTES, 'UTF-8') : ''
        ];
    }
    
    $response['success'] = true;
    $response['users'] = $users;
    $response['message'] = count($users) . ' users found';
    
} catch (Exception $e) {
    error_log("get-users-by-type.php Error: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>