<?php
/**
 * Get Payers and Districts for Filters
 * Femi9 Billing Application
 * 
 * Description: Returns payer names and districts for filter dropdowns
 * Actions: get_payers, get_payer_districts, get_payers_by_district
 * Security: Session validation, prepared statements, input validation
 * 
 * @author Femi9 Development Team
 * @version 1.0
 * @date 2025-01-13
 */

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

session_start();

include("checksession.php");
include("config.php");

/**
 * Response structure
 */
$response = [
    'success' => false,
    'data' => []
];

try {
    // ========================================================================
    // SECURITY VALIDATIONS
    // ========================================================================
    
    // Check database connection
    if (!isset($db_conn) || !$db_conn) {
        throw new Exception('Database connection failed');
    }

    // Check authentication
    if (!isset($_SESSION['LOGIN_USER_ID']) || !isset($_SESSION['LOGIN_USER_TYPE'])) {
        throw new Exception('Authentication required');
    }

    $logged_user_id = $_SESSION['LOGIN_USER_ID'];
    $logged_user_type = $_SESSION['LOGIN_USER_TYPE'];

    // Set database charset
    mysqli_set_charset($db_conn, 'utf8mb4');

    // Get and validate action
    $action = $_GET['action'] ?? '';
    $allowed_actions = ['get_payers', 'get_payer_districts', 'get_payers_by_district'];
    
    if (!in_array($action, $allowed_actions, true)) {
        throw new Exception('Invalid action');
    }

    // ========================================================================
    // VALIDATION HELPER FUNCTIONS
    // ========================================================================
    
    /**
     * Validate payer type against whitelist
     */
    function validatePayerType($type) {
        $allowed = ['c_and_f', 'super_stockiest', 'stockiest', 'distributor', 'super_distributor'];
        return in_array($type, $allowed, true) ? $type : '';
    }

    /**
     * Validate district ID
     */
    function validateDistrictId($id) {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        return ($id !== false && $id > 0) ? $id : 0;
    }

    // ========================================================================
    // ACTION: GET PAYER DISTRICTS
    // ========================================================================
    
    if ($action === 'get_payer_districts') {
        $query = "SELECT DISTINCT d.id, d.dist_name
                  FROM district d
                  WHERE d.id IN (
                      SELECT DISTINCT district_id 
                      FROM (
                          SELECT cf.district_id 
                          FROM c_and_f cf 
                          WHERE cf.district_id IS NOT NULL 
                            AND cf.district_id != 0
                            AND cf.deleted_at IS NULL
                          
                          UNION
                          
                          SELECT ss.district_id 
                          FROM super_stockiest ss 
                          WHERE ss.district_id IS NOT NULL 
                            AND ss.district_id != 0
                            AND ss.deleted_at IS NULL
                          
                          UNION
                          
                          SELECT s.district_id 
                          FROM stockiest s 
                          WHERE s.district_id IS NOT NULL 
                            AND s.district_id != 0
                            AND s.deleted_at IS NULL
                          
                          UNION
                          
                          SELECT CAST(d.district_id AS UNSIGNED) as district_id
                          FROM distributor d 
                          WHERE d.district_id IS NOT NULL 
                            AND d.district_id != '' 
                            AND d.district_id != '0'
                            AND d.deleted_at IS NULL
                          
                          UNION
                          
                          SELECT CAST(sd.district_id AS UNSIGNED) as district_id
                          FROM super_distributor sd 
                          WHERE sd.district_id IS NOT NULL 
                            AND sd.district_id != '' 
                            AND sd.district_id != '0'
                            AND sd.deleted_at IS NULL
                      ) AS all_districts
                  )
                  ORDER BY d.dist_name ASC";
        
        $result = $db_conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $db_conn->error);
        }
        
        $districts = [];
        while ($row = $result->fetch_assoc()) {
            $districts[] = [
                'id' => (int)$row['id'],
                'name' => htmlspecialchars($row['dist_name'], ENT_QUOTES, 'UTF-8')
            ];
        }
        
        $response['success'] = true;
        $response['data'] = $districts;
    }
    
    // ========================================================================
    // ACTION: GET PAYERS (from_user)
    // ========================================================================
    
    elseif ($action === 'get_payers') {
        $payer_type = validatePayerType($_GET['payer_type'] ?? '');
        
        // Get payers from advance_payments table (they have made payments)
        $query = "SELECT DISTINCT 
                    ap.from_user_id as id,
                    ap.from_user_name as name,
                    ap.from_user_type as type
                  FROM advance_payments ap
                  WHERE ap.deleted_at IS NULL";
        
        $params = [];
        $types = "";
        
        // Permission-based filtering for non-company users
        if ($logged_user_type !== 'company') {
            $escaped_user_id = $db_conn->real_escape_string($logged_user_id);
            $query .= " AND (ap.to_user_id = '$escaped_user_id' OR ap.from_user_id = '$escaped_user_id')";
        }
        
        // Filter by payer type if specified
        if (!empty($payer_type)) {
            $query .= " AND ap.from_user_type = '" . $db_conn->real_escape_string($payer_type) . "'";
        }
        
        $query .= " ORDER BY ap.from_user_name ASC";
        
        $result = $db_conn->query($query);
        
        if (!$result) {
            throw new Exception('Query failed: ' . $db_conn->error);
        }
        
        $payers = [];
        
        while ($row = $result->fetch_assoc()) {
            $payers[] = [
                'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                'type' => htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8')
            ];
        }
        
        $response['success'] = true;
        $response['data'] = $payers;
    }
    
    // ========================================================================
    // ACTION: GET PAYERS BY DISTRICT
    // ========================================================================
    
    elseif ($action === 'get_payers_by_district') {
        $district_id = validateDistrictId($_GET['district_id'] ?? 0);
        $payer_type = validatePayerType($_GET['payer_type'] ?? '');
        
        if ($district_id === 0) {
            throw new Exception('Valid district ID required');
        }
        
        $payers = [];
        
        // C&F Agents
        if (empty($payer_type) || $payer_type === 'c_and_f') {
            $query = "SELECT temp_id as id, name, 'c_and_f' as type 
                     FROM c_and_f 
                     WHERE district_id = " . $district_id . " 
                       AND deleted_at IS NULL
                     ORDER BY name ASC";
            
            $result = $db_conn->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $payers[] = [
                        'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                        'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                        'type' => 'c_and_f'
                    ];
                }
            }
        }
        
        // Super Stockists
        if (empty($payer_type) || $payer_type === 'super_stockiest') {
            $query = "SELECT temp_id as id, name, 'super_stockiest' as type 
                     FROM super_stockiest 
                     WHERE district_id = " . $district_id . " 
                       AND deleted_at IS NULL
                     ORDER BY name ASC";
            
            $result = $db_conn->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $payers[] = [
                        'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                        'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                        'type' => 'super_stockiest'
                    ];
                }
            }
        }
        
        // Stockists
        if (empty($payer_type) || $payer_type === 'stockiest') {
            $query = "SELECT temp_id as id, name, 'stockiest' as type 
                     FROM stockiest 
                     WHERE district_id = " . $district_id . " 
                       AND deleted_at IS NULL
                     ORDER BY name ASC";
            
            $result = $db_conn->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $payers[] = [
                        'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                        'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                        'type' => 'stockiest'
                    ];
                }
            }
        }
        
        // Distributors (district_id is VARCHAR)
        if (empty($payer_type) || $payer_type === 'distributor') {
            $query = "SELECT temp_id as id, name, 'distributor' as type 
                     FROM distributor 
                     WHERE CAST(district_id AS UNSIGNED) = " . $district_id . " 
                       AND deleted_at IS NULL
                     ORDER BY name ASC";
            
            $result = $db_conn->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $payers[] = [
                        'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                        'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                        'type' => 'distributor'
                    ];
                }
            }
        }
        
        // Super Distributors (district_id is VARCHAR)
        if (empty($payer_type) || $payer_type === 'super_distributor') {
            $query = "SELECT temp_id as id, name, 'super_distributor' as type 
                     FROM super_distributor 
                     WHERE CAST(district_id AS UNSIGNED) = " . $district_id . " 
                       AND deleted_at IS NULL
                     ORDER BY name ASC";
            
            $result = $db_conn->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $payers[] = [
                        'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                        'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                        'type' => 'super_distributor'
                    ];
                }
            }
        }
        
        $response['success'] = true;
        $response['data'] = $payers;
    }

} catch (Exception $e) {
    // Log error
    error_log("get-payers-districts.php Error: " . $e->getMessage());
    
    // Return error
    $response['error'] = $e->getMessage();
    $response['success'] = false;
}

// Close database connection
if (isset($db_conn) && $db_conn) {
    $db_conn->close();
}

// Output JSON response
echo json_encode($response);
exit;
?>
