<?php
// Start output buffering to catch any unwanted output
ob_start();

// Suppress all errors from displaying (we'll handle them in JSON)
error_reporting(0);
ini_set('display_errors', 0);

// Include files
@include("checksession.php");
@include("config.php");
@require_once("include/GodownAccess.php");

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
        
        sendJSON(['success' => true, 'data' => $districts]);
        
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
// GET SELLERS BY TYPE
// ============================================
if($action == 'get_sellers') {
    $seller_type = isset($_GET['seller_type']) ? trim($_GET['seller_type']) : '';
    $state_id = isset($_GET['state_id']) ? intval($_GET['state_id']) : 0;
    $district_id = isset($_GET['district_id']) ? intval($_GET['district_id']) : 0;
    $taluk_id = isset($_GET['taluk_id']) ? intval($_GET['taluk_id']) : 0;
    
    if(empty($seller_type)) {
        sendJSON(['success' => false, 'message' => 'No seller type specified']);
    }
    
    try {
        // Special handling for COMPANY - show all companies
        if($seller_type == 'company') {
            $query = "SELECT id, gname as name FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY gname ASC LIMIT 1000";
            $result = mysqli_query($db_conn, $query);
            
            if(!$result) {
                sendJSON(['success' => false, 'message' => 'Query failed']);
            }
            
            $sellers = array();
            while($row = mysqli_fetch_assoc($result)) {
                $sellers[] = array(
                    'id' => $row['id'],
                    'name' => $row['name']
                );
            }
            
            sendJSON(['success' => true, 'data' => $sellers, 'count' => count($sellers)]);
        }
        
        // Configuration for other seller types
        $table_config = array(
            'super_stockiest' => array(
                'table' => 'super_stockiest',
                'id_field' => 'temp_id',
                'name_field' => 'name',
                'has_taluk' => false
            ),
            'stockiest' => array(
                'table' => 'stockiest',
                'id_field' => 'temp_id',
                'name_field' => 'name',
                'has_taluk' => true
            ),
            'distributor' => array(
                'table' => 'distributor',
                'id_field' => 'temp_id',
                'name_field' => 'name',
                'has_taluk' => true
            ),
            'super_distributor' => array(
                'table' => 'super_distributor',
                'id_field' => 'temp_id',
                'name_field' => 'name',
                'has_taluk' => true
            )
        );
        
        if(!isset($table_config[$seller_type])) {
            sendJSON(['success' => false, 'message' => 'Invalid seller type']);
        }
        
        $config = $table_config[$seller_type];
        
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
        
        // Add taluk filter (only if supported)
        if($taluk_id > 0 && $config['has_taluk']) {
            $where[] = "taluk_id = ?";
            $params[] = $taluk_id;
            $types .= "i";
        }
        
        // Build final query
        $query = "SELECT {$config['id_field']} as id, {$config['name_field']} as name 
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
        
        $sellers = array();
        while($row = $result->fetch_assoc()) {
            $sellers[] = array(
                'id' => $row['id'],
                'name' => $row['name']
            );
        }
        $stmt->close();
        
        sendJSON([
            'success' => true, 
            'data' => $sellers, 
            'count' => count($sellers),
            'filters' => array(
                'type' => $seller_type,
                'state' => $state_id,
                'district' => $district_id,
                'taluk' => $taluk_id
            )
        ]);
        
    } catch(Exception $e) {
        sendJSON(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
}



// ============================================
// GET BUYERS BY TYPE
// ============================================
if($action == 'get_buyers') {
    $buyer_type  = isset($_GET['buyer_type'])   ? trim($_GET['buyer_type'])   : '';
    $state_id    = isset($_GET['state_id'])     ? intval($_GET['state_id'])   : 0;
    $district_id = isset($_GET['district_id'])  ? intval($_GET['district_id']): 0;

    if(empty($buyer_type)) {
        sendJSON(['success' => false, 'message' => 'No buyer type specified']);
    }

    try {
        if($buyer_type == 'customer') {
            $r = mysqli_query($db_conn, "SELECT id, name FROM customers ORDER BY name ASC LIMIT 1000");
            $buyers = [];
            if($r) while($row = mysqli_fetch_assoc($r))
                $buyers[] = ['id' => $row['id'], 'name' => $row['name']];
            sendJSON(['success' => true, 'data' => $buyers]);
        }

        $table_map = [
            'shop'             => ['table' => 'shop',              'id_field' => 'temp_id', 'name_field' => 'name'],
            'distributor'      => ['table' => 'distributor',       'id_field' => 'temp_id', 'name_field' => 'name'],
            'super_distributor'=> ['table' => 'super_distributor', 'id_field' => 'temp_id', 'name_field' => 'name'],
            'stockiest'        => ['table' => 'stockiest',         'id_field' => 'temp_id', 'name_field' => 'name'],
            'super_stockiest'  => ['table' => 'super_stockiest',   'id_field' => 'temp_id', 'name_field' => 'name'],
            'candf'            => ['table' => 'c_and_f',           'id_field' => 'temp_id', 'name_field' => 'name'],
            'outlet'           => ['table' => 'outlet',            'id_field' => 'temp_id', 'name_field' => 'name'],
        ];

        if(!isset($table_map[$buyer_type])) {
            sendJSON(['success' => false, 'message' => 'Invalid buyer type']);
        }

        $cfg   = $table_map[$buyer_type];
        $where = ['1=1'];
        $params = [];
        $types  = '';

        if($state_id > 0) {
            $where[]  = 'state_id = ?';
            $params[] = $state_id;
            $types   .= 'i';
        }
        if($district_id > 0) {
            $where[]  = 'district_id = ?';
            $params[] = $district_id;
            $types   .= 'i';
        }

        $query = "SELECT {$cfg['id_field']} as id, {$cfg['name_field']} as name
                  FROM {$cfg['table']}
                  WHERE " . implode(' AND ', $where) . "
                  ORDER BY {$cfg['name_field']} ASC
                  LIMIT 1000";

        $stmt = $db_conn->prepare($query);
        if(!$stmt) sendJSON(['success' => false, 'message' => 'Query preparation failed']);

        if(!empty($params)) $stmt->bind_param($types, ...$params);

        $stmt->execute();
        $result = $stmt->get_result();

        $buyers = [];
        while($row = $result->fetch_assoc())
            $buyers[] = ['id' => $row['id'], 'name' => $row['name']];
        $stmt->close();

        sendJSON(['success' => true, 'data' => $buyers, 'count' => count($buyers)]);

    } catch(Exception $e) {
        sendJSON(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
}

// If we reach here, invalid action
sendJSON(['success' => false, 'message' => 'Invalid action: ' . $action]);
?>