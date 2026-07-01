<?php
/**
 * Get Bonus Filter Data - Dynamic Filter Dropdowns
 * Femi9 Billing Application
 * 
 * Provides data for district and user filter dropdowns
 * 
 * @author Femi9 Development Team
 * @version 1.0
 * @date 2026-02-11
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once("checksession.php");
require_once("config.php");

// Security check
$logged_user_id = $_SESSION['LOGIN_USER_ID'] ?? '';
$logged_user_type = $_SESSION['LOGIN_USER_TYPE'] ?? '';

if (empty($logged_user_id) || empty($logged_user_type)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Set charset
if ($db_conn) {
    mysqli_set_charset($db_conn, 'utf8mb4');
}

// ============================================================================
// VALIDATION FUNCTIONS
// ============================================================================

function validateUserType(?string $type): string {
    $allowedTypes = ['super_stockiest', 'stockiest', ''];
    return in_array($type ?? '', $allowedTypes, true) ? $type : '';
}

function validateInt($value): int {
    $filtered = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['default' => 0, 'min_range' => 0]
    ]);
    return $filtered !== false ? $filtered : 0;
}

// ============================================================================
// GET ACTION
// ============================================================================

$action = $_GET['action'] ?? '';

$response = ['success' => false, 'data' => []];

// ============================================================================
// GET USERS
// ============================================================================

if ($action === 'get_users') {
    $user_type = validateUserType($_GET['user_type'] ?? '');
    $district_id = validateInt($_GET['district_id'] ?? 0);
    
    $users = [];
    
    try {
        if ($user_type === 'super_stockiest' || empty($user_type)) {
            // Get super stockiest users
            $query = "SELECT ss.temp_id AS id, ss.name 
                     FROM super_stockiest ss
                     WHERE ss.deleted_at IS NULL 
                       AND ss.account_status = 'active'";
            
            if ($district_id > 0) {
                $query .= " AND ss.district_id = ?";
            }
            
            $query .= " ORDER BY ss.name ASC";
            
            $stmt = $db_conn->prepare($query);
            
            if ($stmt) {
                if ($district_id > 0) {
                    $stmt->bind_param("i", $district_id);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $users[] = [
                        'id' => $row['id'],
                        'name' => $row['name'] . ' (Super Stockist)',
                        'type' => 'super_stockiest'
                    ];
                }
                
                $stmt->close();
            }
        }
        
        if ($user_type === 'stockiest' || empty($user_type)) {
            // Get stockiest users
            $query = "SELECT st.temp_id AS id, st.name 
                     FROM stockiest st
                     WHERE st.deleted_at IS NULL 
                       AND st.account_status = 'active'";
            
            if ($district_id > 0) {
                $query .= " AND st.district_id = ?";
            }
            
            $query .= " ORDER BY st.name ASC";
            
            $stmt = $db_conn->prepare($query);
            
            if ($stmt) {
                if ($district_id > 0) {
                    $stmt->bind_param("i", $district_id);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $users[] = [
                        'id' => $row['id'],
                        'name' => $row['name'] . ' (Stockist)',
                        'type' => 'stockiest'
                    ];
                }
                
                $stmt->close();
            }
        }
        
        $response['data'] = $users;
        $response['success'] = true;
        
    } catch (Exception $e) {
        error_log("Error in get_users: " . $e->getMessage());
        $response['error'] = 'Failed to fetch users';
    }
}

// ============================================================================
// INVALID ACTION
// ============================================================================

else {
    $response['error'] = 'Invalid action';
}

// ============================================================================
// RETURN RESPONSE
// ============================================================================

echo json_encode($response);

if (isset($db_conn) && $db_conn) {
    $db_conn->close();
}
?>