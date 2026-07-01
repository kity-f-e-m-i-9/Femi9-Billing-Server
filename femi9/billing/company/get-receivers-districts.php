<?php
/**
 * Get Receivers and Districts for Filters (Enhanced with Company Support)
 * Femi9 Billing Application
 * 
 * Description: Returns receiver names and districts for filter dropdowns
 * Security: Session validation, prepared statements, input validation
 * Performance: Optimized queries, efficient data retrieval
 * 
 * @author Femi9 Development Team
 * @version 3.0
 * @date 2025-01-13
 */

declare(strict_types=1);

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

session_start();

require_once 'checksession.php';
require_once 'config.php';

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
    if (!isset($db_conn) || !($db_conn instanceof mysqli) || $db_conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    // Check authentication
    if (!isset($_SESSION['LOGIN_USER_ID'], $_SESSION['LOGIN_USER_TYPE'])) {
        http_response_code(401);
        throw new Exception('Authentication required');
    }

    $logged_user_id = $_SESSION['LOGIN_USER_ID'];
    $logged_user_type = $_SESSION['LOGIN_USER_TYPE'];

    // Set database charset
    if (!$db_conn->set_charset('utf8mb4')) {
        throw new Exception('Error setting charset: ' . $db_conn->error);
    }

    // Get and validate action
    $action = $_GET['action'] ?? '';
    $allowed_actions = ['get_receivers', 'get_receiver_districts', 'get_receivers_by_district'];
    
    if (!in_array($action, $allowed_actions, true)) {
        throw new Exception('Invalid action');
    }

    // ========================================================================
    // VALIDATION HELPER FUNCTIONS
    // ========================================================================
    
    /**
     * Validate user type against whitelist
     * 
     * @param string|null $type User type to validate
     * @return string Validated type or empty string
     */
    function validateReceiverType(?string $type): string {
        $allowed = ['company', 'c_and_f', 'super_stockiest', 'stockiest', 'distributor', 'super_distributor'];
        return in_array($type, $allowed, true) ? $type : '';
    }

    /**
     * Validate district ID
     * 
     * @param mixed $id District ID to validate
     * @return int Validated ID or 0
     */
    function validateDistrictId($id): int {
        return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    }

    // ========================================================================
    // ACTION: GET RECEIVERS (to_user)
    // ========================================================================
    
    if ($action === 'get_receivers') {
        $receiver_type = validateReceiverType($_GET['receiver_type'] ?? '');
        
        // Special handling for company receiver type
        if ($receiver_type === 'company') {
            // For company, get unique company entries from advance_payments
            $query = "SELECT DISTINCT 
                        ap.to_user_id as id,
                        ap.to_user_name as name,
                        'company' as type
                      FROM advance_payments ap
                      WHERE ap.deleted_at IS NULL 
                        AND ap.to_user_type = ?";
            
            $params = [$receiver_type];
            $param_types = 's';
            
            // Permission-based filtering for non-company users
            if ($logged_user_type !== 'company') {
                $query .= " AND (ap.to_user_id = ? OR ap.from_user_id = ?)";
                $params[] = $logged_user_id;
                $params[] = $logged_user_id;
                $param_types .= 'ss';
            }
            
            $query .= " ORDER BY ap.to_user_name ASC";
            
            $stmt = $db_conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Query preparation failed: ' . $db_conn->error);
            }
            
            $stmt->bind_param($param_types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception('Query execution failed: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $receivers = [];
            
            while ($row = $result->fetch_assoc()) {
                $receivers[] = [
                    'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                    'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                    'type' => 'company'
                ];
            }
            
            $stmt->close();
            
            $response['success'] = true;
            $response['data'] = $receivers;
        }
        // For other receiver types, query from advance_payments
        else {
            $query = "SELECT DISTINCT 
                        ap.to_user_id,
                        ap.to_user_name,
                        ap.to_user_type
                      FROM advance_payments ap
                      WHERE ap.deleted_at IS NULL";
            
            $params = [];
            $param_types = '';
            
            // Permission-based filtering for non-company users
            if ($logged_user_type !== 'company') {
                $query .= " AND (ap.to_user_id = ? OR ap.from_user_id = ?)";
                $params[] = $logged_user_id;
                $params[] = $logged_user_id;
                $param_types .= 'ss';
            }
            
            // Filter by receiver type if specified
            if (!empty($receiver_type)) {
                $query .= " AND ap.to_user_type = ?";
                $params[] = $receiver_type;
                $param_types .= 's';
            }
            
            $query .= " ORDER BY ap.to_user_name ASC";
            
            $stmt = $db_conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Query preparation failed: ' . $db_conn->error);
            }
            
            if (!empty($params)) {
                $stmt->bind_param($param_types, ...$params);
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Query execution failed: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $receivers = [];
            
            while ($row = $result->fetch_assoc()) {
                $receivers[] = [
                    'id' => htmlspecialchars($row['to_user_id'], ENT_QUOTES, 'UTF-8'),
                    'name' => htmlspecialchars($row['to_user_name'], ENT_QUOTES, 'UTF-8'),
                    'type' => htmlspecialchars($row['to_user_type'], ENT_QUOTES, 'UTF-8')
                ];
            }
            
            $stmt->close();
            
            $response['success'] = true;
            $response['data'] = $receivers;
        }
    }
    
    // ========================================================================
    // ACTION: GET DISTRICTS
    // ========================================================================
    
    elseif ($action === 'get_receiver_districts') {
        /**
         * Performance note: This query uses UNION to efficiently get distinct districts
         * from all user tables. Indexes on district_id columns improve performance.
         */
        
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
    // ACTION: GET RECEIVERS BY DISTRICT
    // ========================================================================
    
    elseif ($action === 'get_receivers_by_district') {
        $district_id = validateDistrictId($_GET['district_id'] ?? 0);
        $receiver_type = validateReceiverType($_GET['receiver_type'] ?? '');
        
        if ($district_id === 0) {
            throw new Exception('Valid district ID required');
        }
        
        // Company doesn't have districts, so skip if company type is selected
        if ($receiver_type === 'company') {
            $response['success'] = true;
            $response['data'] = [];
            $response['message'] = 'Company type does not have district associations';
        } else {
            $receivers = [];
            
            /**
             * Query optimization: Use prepared statements for each table
             * Only query tables matching the receiver_type filter
             */
            
            // C&F Agents
            if (empty($receiver_type) || $receiver_type === 'c_and_f') {
                $stmt = $db_conn->prepare(
                    "SELECT temp_id as id, name, 'c_and_f' as type 
                     FROM c_and_f 
                     WHERE district_id = ? 
                       AND deleted_at IS NULL
                     ORDER BY name ASC"
                );
                
                if ($stmt) {
                    $stmt->bind_param('i', $district_id);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $receivers[] = [
                                'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                                'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                                'type' => 'c_and_f'
                            ];
                        }
                    }
                    $stmt->close();
                }
            }
            
            // Super Stockists
            if (empty($receiver_type) || $receiver_type === 'super_stockiest') {
                $stmt = $db_conn->prepare(
                    "SELECT temp_id as id, name, 'super_stockiest' as type 
                     FROM super_stockiest 
                     WHERE district_id = ? 
                       AND deleted_at IS NULL
                     ORDER BY name ASC"
                );
                
                if ($stmt) {
                    $stmt->bind_param('i', $district_id);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $receivers[] = [
                                'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                                'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                                'type' => 'super_stockiest'
                            ];
                        }
                    }
                    $stmt->close();
                }
            }
            
            // Stockists
            if (empty($receiver_type) || $receiver_type === 'stockiest') {
                $stmt = $db_conn->prepare(
                    "SELECT temp_id as id, name, 'stockiest' as type 
                     FROM stockiest 
                     WHERE district_id = ? 
                       AND deleted_at IS NULL
                     ORDER BY name ASC"
                );
                
                if ($stmt) {
                    $stmt->bind_param('i', $district_id);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $receivers[] = [
                                'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                                'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                                'type' => 'stockiest'
                            ];
                        }
                    }
                    $stmt->close();
                }
            }
            
            // Distributors (district_id is VARCHAR)
            if (empty($receiver_type) || $receiver_type === 'distributor') {
                $stmt = $db_conn->prepare(
                    "SELECT temp_id as id, name, 'distributor' as type 
                     FROM distributor 
                     WHERE CAST(district_id AS UNSIGNED) = ? 
                       AND deleted_at IS NULL
                     ORDER BY name ASC"
                );
                
                if ($stmt) {
                    $stmt->bind_param('i', $district_id);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $receivers[] = [
                                'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                                'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                                'type' => 'distributor'
                            ];
                        }
                    }
                    $stmt->close();
                }
            }
            
            // Super Distributors (district_id is VARCHAR)
            if (empty($receiver_type) || $receiver_type === 'super_distributor') {
                $stmt = $db_conn->prepare(
                    "SELECT temp_id as id, name, 'super_distributor' as type 
                     FROM super_distributor 
                     WHERE CAST(district_id AS UNSIGNED) = ? 
                       AND deleted_at IS NULL
                     ORDER BY name ASC"
                );
                
                if ($stmt) {
                    $stmt->bind_param('i', $district_id);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()) {
                            $receivers[] = [
                                'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                                'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
                                'type' => 'super_distributor'
                            ];
                        }
                    }
                    $stmt->close();
                }
            }
            
            $response['success'] = true;
            $response['data'] = $receivers;
        }
    }

} catch (Exception $e) {
    // Security: Log full error server-side
    error_log(sprintf(
        "[%s] get-receivers-districts-2.php Error: %s | Action: %s | User: %s | IP: %s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $action ?? 'unknown',
        $_SESSION['LOGIN_USER_ID'] ?? 'unknown',
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ));
    
    // Return user-friendly error
    $response['error'] = $e->getMessage();
    $response['success'] = false;
}

// ========================================================================
// CLEANUP AND RESPONSE
// ========================================================================

// Close database connection
if (isset($db_conn) && $db_conn instanceof mysqli) {
    $db_conn->close();
}

// Output JSON response with proper encoding
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;