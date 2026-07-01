<?php
// Start output buffering to catch any unwanted output
ob_start();

// Suppress all errors from displaying (we'll handle them in JSON)
error_reporting(0);
ini_set('display_errors', 0);

// Include files
@include("checksession.php");
@include("config.php");

// Clear any output that might have been generated
ob_clean();

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Function to send JSON response and exit
function sendJSON($data) {
    echo json_encode($data);
    exit;
}

// Check if database connection exists
if(!isset($db_conn) || !$db_conn) {
    sendJSON(['success' => false, 'message' => 'Database connection failed']);
}

// Get action parameter
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if(empty($action)) {
    sendJSON(['success' => false, 'message' => 'No action specified']);
}

// ============================================
// TEST ENDPOINT
// ============================================
if($action == 'test') {
    sendJSON([
        'success' => true, 
        'message' => 'Stock Filter API is working',
        'data' => [
            'php_version' => phpversion(),
            'timestamp' => date('Y-m-d H:i:s'),
            'db_connected' => mysqli_ping($db_conn)
        ]
    ]);
}

// ============================================
// GET DISTRICTS BY STATE
// ============================================
if($action == 'get_districts') {
    $state_id = isset($_GET['state_id']) ? intval($_GET['state_id']) : 0;
    
    if($state_id <= 0) {
        sendJSON(['success' => false, 'message' => 'Invalid state ID']);
    }
    
    try {
        $query = "SELECT DISTINCT id, dist_name FROM district WHERE state_id = ? ORDER BY dist_name ASC";
        $stmt = $db_conn->prepare($query);
        
        if(!$stmt) {
            sendJSON(['success' => false, 'message' => 'Query preparation failed']);
        }
        
        $stmt->bind_param("i", $state_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $districts = array();
        while($row = $result->fetch_assoc()) {
            $districts[] = array(
                'id' => $row['id'],
                'name' => $row['dist_name']
            );
        }
        $stmt->close();
        
        sendJSON(['success' => true, 'data' => $districts, 'count' => count($districts)]);
        
    } catch(Exception $e) {
        sendJSON(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
}

// ============================================
// GET TALUKS BY DISTRICT
// ============================================
if($action == 'get_taluks') {
    $district_id = isset($_GET['district_id']) ? intval($_GET['district_id']) : 0;
    
    if($district_id <= 0) {
        sendJSON(['success' => false, 'message' => 'Invalid district ID']);
    }
    
    try {
        $query = "SELECT DISTINCT id, taluk FROM taluk WHERE dist_id = ? ORDER BY taluk ASC";
        $stmt = $db_conn->prepare($query);
        
        if(!$stmt) {
            sendJSON(['success' => false, 'message' => 'Query preparation failed']);
        }
        
        $stmt->bind_param("i", $district_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $taluks = array();
        while($row = $result->fetch_assoc()) {
            $taluks[] = array(
                'id' => $row['id'],
                'name' => $row['taluk']
            );
        }
        $stmt->close();
        
        sendJSON(['success' => true, 'data' => $taluks, 'count' => count($taluks)]);
        
    } catch(Exception $e) {
        sendJSON(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
}

// ============================================
// GET STOCK USERS BY TYPE (distributor, stockist, etc.)
// ============================================
if($action == 'get_stock_users') {
    $user_type = isset($_GET['user_type']) ? trim($_GET['user_type']) : '';
    $state_id = isset($_GET['state_id']) ? intval($_GET['state_id']) : 0;
    $district_id = isset($_GET['district_id']) ? intval($_GET['district_id']) : 0;
    $taluk_id = isset($_GET['taluk_id']) ? intval($_GET['taluk_id']) : 0;
    
    if(empty($user_type)) {
        sendJSON(['success' => false, 'message' => 'No user type specified']);
    }
    
    try {
        // Configuration for user types
        $table_config = array(
            'distributor' => array(
                'table' => 'distributor',
                'id_field' => 'temp_id',
                'name_field' => 'name',
                'mobile_field' => 'mobile_number',
                'has_taluk' => true
            ),
            'super_distributor' => array(
                'table' => 'super_distributor',
                'id_field' => 'temp_id',
                'name_field' => 'name',
                'mobile_field' => 'mobile_number',
                'has_taluk' => true
            ),
            'stockiest' => array(
                'table' => 'stockiest',
                'id_field' => 'temp_id',
                'name_field' => 'name',
                'mobile_field' => 'mobile_number',
                'has_taluk' => true
            ),
            'super_stockiest' => array(
                'table' => 'super_stockiest',
                'id_field' => 'temp_id',
                'name_field' => 'name',
                'mobile_field' => 'mobile_number',
                'has_taluk' => false
            )
        );
        
        if(!isset($table_config[$user_type])) {
            sendJSON(['success' => false, 'message' => 'Invalid user type']);
        }
        
        $config = $table_config[$user_type];
        
        // Build WHERE clause
        $where = array("account_status = 'active'");
        $params = array();
        $types = "";
        
        // Add state filter
        if($state_id > 0) {
            $where[] = "state_id = ?";
            $params[] = $state_id;
            $types .= "i";
        }
        
        // Add district filter
        if($district_id > 0) {
            $where[] = "district_id = ?";
            $params[] = $district_id;
            $types .= "i";
        }
        
        // Add taluk filter (only if supported by this user type)
        if($taluk_id > 0 && $config['has_taluk']) {
            $where[] = "taluk_id = ?";
            $params[] = $taluk_id;
            $types .= "i";
        }
        
        // Build final query
        $query = "SELECT {$config['id_field']} as id, 
                         {$config['name_field']} as name,
                         {$config['mobile_field']} as mobile
                  FROM {$config['table']} 
                  WHERE " . implode(" AND ", $where) . "
                  ORDER BY {$config['name_field']} ASC 
                  LIMIT 1000";
        
        $stmt = $db_conn->prepare($query);
        
        if(!$stmt) {
            sendJSON(['success' => false, 'message' => 'Query preparation failed']);
        }
        
        // Bind parameters if any
        if(!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = array();
        while($row = $result->fetch_assoc()) {
            $users[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'mobile' => $row['mobile']
            );
        }
        $stmt->close();
        
        sendJSON([
            'success' => true, 
            'data' => $users, 
            'count' => count($users),
            'message' => count($users) . ' users found',
            'filters' => array(
                'type' => $user_type,
                'state' => $state_id,
                'district' => $district_id,
                'taluk' => $taluk_id
            )
        ]);
        
    } catch(Exception $e) {
        sendJSON(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
}

// If we reach here, invalid action
sendJSON(['success' => false, 'message' => 'Invalid action: ' . $action]);
?>