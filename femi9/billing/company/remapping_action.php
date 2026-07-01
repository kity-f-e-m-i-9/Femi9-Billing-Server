<?php
/**
 * Customer Remapping Action Handler
 * Processes the remapping of customers from one user to another
 * 
 * Security features:
 * - CSRF token validation
 * - SQL injection prevention with prepared statements
 * - Transaction support for data integrity
 * - Input validation and sanitization
 * - Audit logging
 * 
 * @author Senior PHP Developer
 * @version 2.0
 */

declare(strict_types=1);

// Session and security checks
require_once "checksession.php";

// Set timezone
date_default_timezone_set("Asia/Kolkata");

/**
 * Validate CSRF token
 */
function validate_csrf_token(): bool
{
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
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
 * Validate customer IDs (must be numeric)
 */
function validate_customer_ids(array $customerIds): array
{
    $validIds = [];
    foreach ($customerIds as $id) {
        if (is_numeric($id) && (int)$id > 0) {
            $validIds[] = (int)$id;
        }
    }
    return $validIds;
}

/**
 * Log remapping activity for audit trail
 */
function log_remapping_activity(
    mysqli $db_conn,
    array $customerIds,
    string $fromUserType,
    string $fromUserId,
    string $toUserType,
    string $toUserId,
    int $adminUserId
): void {
    $timestamp = date('Y-m-d H:i:s');
    $customerCount = count($customerIds);
    $customerIdsList = implode(',', $customerIds);
    $actionType = 'customer_remap';
    
    $query = "INSERT INTO remapping_audit_log 
              (admin_user_id, from_user_type, from_user_id, to_user_type, to_user_id, 
               customer_ids, customer_count, action_date, action_type) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db_conn->prepare($query);
    
    if ($stmt) {
    
    $stmt->bind_param(
        "isssssiss",  
        $adminUserId,      
        $fromUserType,     
        $fromUserId,      
        $toUserType,       
        $toUserId,         
        $customerIdsList,  
        $customerCount,    
        $timestamp,       
        $actionType        
    );
    $stmt->execute();
    $stmt->close();
} else {
    error_log("Failed to log remapping activity: " . $db_conn->error);
}
}

/**
 * Perform customer remapping with transaction
 */
function remap_customers(
    mysqli $db_conn,
    array $customerIds,
    string $toUserType,
    string $toUserId
): bool {
    // Start transaction
    $db_conn->begin_transaction();
    
    try {
        // Prepare update statement - using actual column names
        $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
        $query = "UPDATE customers 
                  SET user_type = ?, 
                      user_id = ?
                  WHERE id IN ($placeholders)";
        
        $stmt = $db_conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $db_conn->error);
        }
        
        // Build bind_param types string
        $types = 'ss' . str_repeat('i', count($customerIds));
        
        // Build parameters array
        $params = [$toUserType, $toUserId];
        foreach ($customerIds as $id) {
            $params[] = $id;
        }
        
        // Bind parameters dynamically
        $stmt->bind_param($types, ...$params);
        
        // Execute update
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute update: " . $stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        // Commit transaction
        $db_conn->commit();
        
        return $affected_rows > 0;
        
    } catch (Exception $e) {
        // Rollback on error
        $db_conn->rollback();
        error_log("Remapping error: " . $e->getMessage());
        return false;
    }
}

// Process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['REMAPPING_CUSTOMERS'])) {
    
    // Validate CSRF token
    if (!validate_csrf_token()) {
        $_SESSION['error_message'] = 'Invalid request. Security token mismatch.';
        header("Location: remapping_customer.php");
        exit;
    }
    
    // Get and validate inputs
    $customerIds = $_POST['customerid'] ?? [];
    $fromUserType = $_POST['from_usertype'] ?? '';
    $fromUserId = $_POST['from_userid'] ?? '';
    $toUserType = $_POST['to_usertype'] ?? '';
    $toUserId = $_POST['to_userid'] ?? '';
    
    // Validate inputs
    $errors = [];
    
    if (empty($customerIds) || !is_array($customerIds)) {
        $errors[] = 'No customers selected for remapping.';
    }
    
    if (!validate_user_type($fromUserType)) {
        $errors[] = 'Invalid source user type.';
    }
    
    if (!validate_user_type($toUserType)) {
        $errors[] = 'Invalid target user type.';
    }
    
    if (empty($fromUserId)) {
        $errors[] = 'Invalid source user ID.';
    }
    
    if (empty($toUserId)) {
        $errors[] = 'Invalid target user ID.';
    }
    
    // Validate customer IDs
    $validCustomerIds = validate_customer_ids($customerIds);
    if (empty($validCustomerIds)) {
        $errors[] = 'Invalid customer IDs provided.';
    }
    
    // If validation fails, redirect with error
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(' ', $errors);
        header("Location: remapping_customer.php");
        exit;
    }
    
    // Perform remapping
    $success = remap_customers($db_conn, $validCustomerIds, $toUserType, $toUserId);
    
    if ($success) {
        // Log the activity (assuming admin user ID is stored in session)
        $adminUserId = $_SESSION['user_id'] ?? 0;
        log_remapping_activity(
            $db_conn,
            $validCustomerIds,
            $fromUserType,
            $fromUserId,
            $toUserType,
            $toUserId,
            $adminUserId
        );
        
        // Set success message
        $_SESSION['success_message'] = count($validCustomerIds) . ' customer(s) remapped successfully.';
        
        // Regenerate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // Redirect with success
        header("Location: remapping_customer.php?mappingsuccess=1");
        exit;
        
    } else {
        // Set error message
        $_SESSION['error_message'] = 'Failed to remap customers. Please try again.';
        
        // Redirect with error
        header("Location: remapping_customer.php");
        exit;
    }
    
} else {
    // Invalid request method
    header("Location: remapping_customer.php");
    exit;
}