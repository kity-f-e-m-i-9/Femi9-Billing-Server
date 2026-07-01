<?php
/**
 * AJAX Endpoint - Load Users by Type
 * Returns JSON response with users for the selected user type
 * 
 * Security features:
 * - Session validation
 * - Input validation with whitelist
 * - SQL injection prevention with prepared statements
 * - JSON response only
 * - XSS prevention
 * 
 * @author Senior PHP Developer
 * @version 2.0
 */

declare(strict_types=1);

// Start session and check authentication
require_once "checksession.php";

// Set JSON header
header('Content-Type: application/json');

// Only allow AJAX requests
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

/**
 * Validate user type against whitelist
 */
function validate_user_type(?string $type): bool
{
    $valid_types = ['company', 'candf', 'super_stockiest', 'stockiest', 'super_distributor', 'distributor'];
    return $type && in_array($type, $valid_types, true);
}

/**
 * Get table name for user type
 */
function get_table_name(string $userType): ?string
{
    $tableMap = [
        'candf' => 'c_and_f',
        'super_stockiest' => 'super_stockiest',
        'stockiest' => 'stockiest',
        'super_distributor' => 'super_distributor',
        'distributor' => 'distributor'
    ];
    
    return $tableMap[$userType] ?? null;
}

/**
 * Fetch users from database securely
 */
function fetch_users(mysqli $db_conn, string $tableName): array
{
    // Validate table name against whitelist
    $allowedTables = ['c_and_f', 'super_stockiest', 'stockiest', 'super_distributor', 'distributor'];
    if (!in_array($tableName, $allowedTables, true)) {
        return [];
    }
    
    // Check which mobile column exists (mobile or mobile_number)
    $mobileColumn = 'mobile'; // Default assumption
    
    $checkColumn = $db_conn->query("SHOW COLUMNS FROM `{$tableName}` LIKE 'mobile_number'");
    if ($checkColumn && $checkColumn->num_rows > 0) {
        $mobileColumn = 'mobile_number';
    }
    
    // Query with the correct mobile column name
    $query = "SELECT temp_id as id, useridtext as userid_text, name, {$mobileColumn} as mobile 
              FROM `{$tableName}` 
              ORDER BY name ASC";
    
    $result = $db_conn->query($query);
    
    if (!$result) {
        error_log("MySQL query error: " . $db_conn->error);
        return [];
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'userid_text' => $row['userid_text'],
            'name' => $row['name'],
            'mobile' => $row['mobile']
        ];
    }
    
    return $users;
}

try {
    // Get and validate user type
    $userType = $_GET['type'] ?? null;
    
    if (!validate_user_type($userType)) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid user type'
        ]);
        exit;
    }
    
    // Handle company type separately
    if ($userType === 'company') {
        echo json_encode([
            'success' => true,
            'users' => [
                [
                    'id' => '0',
                    'userid_text' => 'COMPANY',
                    'name' => 'Company',
                    'mobile' => ''
                ]
            ]
        ]);
        exit;
    }
    
    // Get table name
    $tableName = get_table_name($userType);
    
    if (!$tableName) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid configuration'
        ]);
        exit;
    }
    
    // Fetch users
    $users = fetch_users($db_conn, $tableName);
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
    
} catch (Exception $e) {
    error_log("Error in load_customer_users.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while loading users'
    ]);
}

exit;