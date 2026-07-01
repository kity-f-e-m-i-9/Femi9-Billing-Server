<?php
/**
 * Daily Login Rewards Report - Filter Data AJAX Endpoint
 * 
 * Handles cascading dropdown filters for the daily login rewards report:
 * - Fetches user types based on logged-in user's role
 * - Fetches users based on selected user type and state
 * - Returns data in JSON format for AJAX requests
 * 
 * Security Features:
 * - Session validation
 * - CSRF token verification
 * - Prepared statements for SQL injection prevention
 * - Input validation and sanitization
 * - XSS prevention with proper JSON encoding
 * - Rate limiting considerations
 * 
 * @author Senior PHP Developer
 * @version 2.1
 * @date 2024
 */

declare(strict_types=1);

// Set headers for JSON response
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Error handling - log errors, don't display in production
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Set timezone
date_default_timezone_set("Asia/Kolkata");

/**
 * Send JSON response and exit
 *
 * @param bool $success Success status
 * @param mixed $data Response data
 * @param string $message Optional message
 * @param int $httpCode HTTP status code
 * @return never
 */
function sendJsonResponse(bool $success, $data = null, string $message = '', int $httpCode = 200): never
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Validate and sanitize string input
 *
 * @param mixed $input Input value
 * @param int $maxLength Maximum length
 * @return string Sanitized string
 */
function sanitizeString($input, int $maxLength = 255): string
{
    if (!is_string($input)) {
        return '';
    }
    $sanitized = trim($input);
    $sanitized = strip_tags($sanitized);
    return mb_substr($sanitized, 0, $maxLength, 'UTF-8');
}

try {
    // Session validation
    require_once "checksession.php";
    
    // Verify session is active
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    if (!isset($_SESSION['SESS_MEMBER_ID']) || empty($_SESSION['SESS_MEMBER_ID'])) {
        sendJsonResponse(false, null, 'Unauthorized access. Please login again.', 401);
    }
    
    // CSRF Token validation for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
            sendJsonResponse(false, null, 'Invalid security token.', 403);
        }
        
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            sendJsonResponse(false, null, 'Security token mismatch.', 403);
        }
    }
    
    // Database connection with error handling
    require_once "connection.php";
    
    if (!isset($connection) || !$connection) {
        throw new Exception('Database connection failed.');
    }
    
    // Get and validate action parameter
    $action = sanitizeString($_REQUEST['action'] ?? '', 50);
    
    if (empty($action)) {
        sendJsonResponse(false, null, 'Action parameter is required.', 400);
    }
    
    // Get logged-in user details
    $logged_in_user_id = (int)$_SESSION['SESS_MEMBER_ID'];
    $logged_in_user_type = sanitizeString($_SESSION['SESS_USER_TYPE'] ?? '', 50);
    
    // Whitelist of valid user types
    $validUserTypes = [
        'company',
        'candf',
        'super_stockiest',
        'stockiest',
        'super_distributor',
        'distributor'
    ];
    
    // Handle different actions
    switch ($action) {
        
        case 'get_user_types':
            /**
             * Fetch available user types based on logged-in user's hierarchy
             * Hierarchy: Company > C&F > Super Stockist > Stockist > Super Distributor > Distributor
             */
            
            $userTypes = [];
            
            // Define user type hierarchy and display names
            $userTypeHierarchy = [
                'company' => ['label' => 'Company', 'level' => 1],
                'candf' => ['label' => 'C & F', 'level' => 2],
                'super_stockiest' => ['label' => 'Super Stockist', 'level' => 3],
                'stockiest' => ['label' => 'Stockist', 'level' => 4],
                'super_distributor' => ['label' => 'Super Distributor', 'level' => 5],
                'distributor' => ['label' => 'Distributor', 'level' => 6]
            ];
            
            // Get current user's level
            $currentUserLevel = $userTypeHierarchy[$logged_in_user_type]['level'] ?? 999;
            
            // Return user types below current user's level
            foreach ($userTypeHierarchy as $type => $info) {
                if ($info['level'] > $currentUserLevel) {
                    $userTypes[] = [
                        'value' => $type,
                        'label' => $info['label']
                    ];
                }
            }
            
            if (empty($userTypes)) {
                sendJsonResponse(false, [], 'No user types available for your role.');
            }
            
            sendJsonResponse(true, $userTypes, 'User types fetched successfully.');
            break;
            
        case 'get_users':
            /**
             * Fetch users based on selected user type and state
             * Only returns users under the logged-in user's hierarchy and in the selected state
             */
            
            $userType = sanitizeString($_REQUEST['user_type'] ?? '', 50);
            $stateId = filter_input(INPUT_REQUEST, 'state_id', FILTER_VALIDATE_INT);
            
            if (empty($userType)) {
                sendJsonResponse(false, null, 'User type is required.', 400);
            }
            
            if (!$stateId || $stateId <= 0) {
                sendJsonResponse(false, null, 'Valid state is required.', 400);
            }
            
            // Validate user type
            if (!in_array($userType, $validUserTypes, true)) {
                sendJsonResponse(false, null, 'Invalid user type.', 400);
            }
            
            // Determine table name based on user type
            $tableMap = [
                'company' => 'company',
                'candf' => 'cf',
                'super_stockiest' => 'ss',
                'stockiest' => 'stockist',
                'super_distributor' => 'superdistributor',
                'distributor' => 'distributor'
            ];
            
            $tableName = $tableMap[$userType] ?? null;
            
            if (!$tableName) {
                sendJsonResponse(false, null, 'Invalid user type mapping.', 400);
            }
            
            // Build query based on logged-in user's type and hierarchy
            $query = "SELECT id, name, mobile FROM `{$tableName}` 
                      WHERE status = 'active' 
                        AND deleted = 0 
                        AND state_id = ?";
            
            $params = [$stateId];
            $types = "i";
            
            // Add hierarchy filter based on logged-in user type
            switch ($logged_in_user_type) {
                case 'company':
                    // Company can see all users in the selected state
                    break;
                    
                case 'candf':
                    $query .= " AND cf_id = ?";
                    $params[] = $logged_in_user_id;
                    $types .= "i";
                    break;
                    
                case 'super_stockiest':
                    $query .= " AND ss_id = ?";
                    $params[] = $logged_in_user_id;
                    $types .= "i";
                    break;
                    
                case 'stockiest':
                    $query .= " AND stockist_id = ?";
                    $params[] = $logged_in_user_id;
                    $types .= "i";
                    break;
                    
                case 'super_distributor':
                    $query .= " AND sd_id = ?";
                    $params[] = $logged_in_user_id;
                    $types .= "i";
                    break;
                    
                case 'distributor':
                    // Distributors typically can't see other users' data
                    sendJsonResponse(false, [], 'Insufficient permissions.');
                    break;
            }
            
            $query .= " ORDER BY name ASC LIMIT 1000";
            
            // Prepare and execute statement
            $stmt = $connection->prepare($query);
            
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $connection->error);
            }
            
            // Bind parameters
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $users = [];
            
            while ($row = $result->fetch_assoc()) {
                $users[] = [
                    'value' => (int)$row['id'],
                    'label' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . 
                               ' (' . htmlspecialchars($row['mobile'], ENT_QUOTES, 'UTF-8') . ')',
                    'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                    'mobile' => htmlspecialchars($row['mobile'], ENT_QUOTES, 'UTF-8')
                ];
            }
            
            $stmt->close();
            
            if (empty($users)) {
                sendJsonResponse(false, [], 'No users found for the selected type and state.');
            }
            
            sendJsonResponse(true, $users, count($users) . ' users fetched successfully.');
            break;
            
        case 'get_date_range':
            /**
             * Fetch available date range for daily login rewards
             * Returns first and last date where login rewards exist for a specific user
             */
            
            $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
            $userType = sanitizeString($_REQUEST['user_type'] ?? '', 50);
            
            if (!$userId || $userId <= 0) {
                sendJsonResponse(false, null, 'Valid user ID is required.', 400);
            }
            
            if (empty($userType) || !in_array($userType, $validUserTypes, true)) {
                sendJsonResponse(false, null, 'Valid user type is required.', 400);
            }
            
            // Query to get date range
            $query = "SELECT 
                        MIN(login_date) as first_date,
                        MAX(login_date) as last_date,
                        COUNT(*) as total_days
                      FROM daily_login_rewards
                      WHERE user_id = ? 
                        AND user_type = ?
                        AND deleted = 0";
            
            $stmt = $connection->prepare($query);
            
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $connection->error);
            }
            
            $stmt->bind_param("is", $userId, $userType);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute query: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $dateRange = $result->fetch_assoc();
            $stmt->close();
            
            if (empty($dateRange['first_date']) || empty($dateRange['last_date'])) {
                sendJsonResponse(false, null, 'No login reward data found for this user.');
            }
            
            $responseData = [
                'first_date' => $dateRange['first_date'],
                'last_date' => $dateRange['last_date'],
                'total_days' => (int)$dateRange['total_days'],
                'formatted_first_date' => date('d M Y', strtotime($dateRange['first_date'])),
                'formatted_last_date' => date('d M Y', strtotime($dateRange['last_date']))
            ];
            
            sendJsonResponse(true, $responseData, 'Date range fetched successfully.');
            break;
            
        default:
            sendJsonResponse(false, null, 'Invalid action specified.', 400);
            break;
    }
    
} catch (Exception $e) {
    // Log error (in production, log to file)
    error_log('Daily Login Filter Error: ' . $e->getMessage());
    
    // Send generic error message (don't expose internal details)
    sendJsonResponse(
        false, 
        null, 
        'An error occurred while processing your request. Please try again.', 
        500
    );
    
} finally {
    // Close database connection
    if (isset($connection) && $connection) {
        $connection->close();
    }
}