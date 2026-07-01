#!/usr/bin/env php
<?php
/**
 * Automatic Receipt Update Cron Job - FINAL FIXED VERSION
 * Runs every 15 minutes to sync receipt data from invoice table
 * 
 * Setup: Add to crontab:
 * *
 */

// Prevent web access - only allow command line execution
if (isset($_SERVER['HTTP_HOST'])) {
    http_response_code(403);
    die("Access denied. This script runs automatically via cron job.\n");
}

// Change to script directory
chdir(dirname(__FILE__));

// Load configuration
if (file_exists("./config-fixed.php")) {
    require_once("./config-fixed.php");
} else {
    die("ERROR: config-fixed.php not found. Please check the path.\n");
}

// Configuration
$MAX_EXECUTION_TIME = 300; 
$BATCH_SIZE = 1000;
$MAX_RECORDS_PER_RUN = 5000;

// Set PHP limits
set_time_limit($MAX_EXECUTION_TIME);
ini_set('memory_limit', '256M');

// Script timing
$script_start = microtime(true);

// Status and logging setup
$status_dir = dirname(__FILE__) . '/status';
$status_file = $status_dir . '/cron-update-status.json';
$log_file = $status_dir . '/cron-update.log';

// Create status directory
if (!is_dir($status_dir)) {
    if (!mkdir($status_dir, 0755, true)) {
        die("ERROR: Could not create status directory: $status_dir\n");
    }
}

// Logging function
function logMessage($message, $write_to_file = true) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    
    echo $logEntry;
    
    if ($write_to_file && is_writable(dirname($log_file))) {
        file_put_contents($log_file, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Status update function
function updateStatus($status_data) {
    global $status_file;
    
    try {
        $status_data['timestamp'] = time();
        $status_data['readable_time'] = date('Y-m-d H:i:s');
        
        if (is_writable(dirname($status_file))) {
            file_put_contents($status_file, json_encode($status_data, JSON_PRETTY_PRINT), LOCK_EX);
        }
    } catch (Exception $e) {
        logMessage("Warning: Could not update status file: " . $e->getMessage());
    }
}

logMessage("=== AUTOMATIC RECEIPT SYNC STARTED ===");

try {
    // Verify database connection
    if (!isset($db_conn)) {
        if (isset($GLOBALS['db_conn'])) {
            $db_conn = $GLOBALS['db_conn'];
        } elseif (function_exists('getDatabaseConnection')) {
            $db_conn = getDatabaseConnection();
        } else {
            if (isset($host) && isset($username) && isset($password) && isset($database)) {
                $db_conn = mysqli_connect($host, $username, $password, $database);
            } else {
                throw new Exception("Database configuration not found. Please check config-fixed.php");
            }
        }
    }
    
    // Check connection
    if (!$db_conn || mysqli_connect_error()) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }
    
    logMessage("Database connection established");
    
    // Set charset
    mysqli_set_charset($db_conn, "utf8");
    
    // Update status
    updateStatus([
        'status' => 'running',
        'last_run' => date('Y-m-d H:i:s'),
        'message' => 'Checking for pending updates...'
    ]);
    
    // Check pending records (using invoice table for customer invoices)
    $check_query = "SELECT COUNT(*) as count FROM receipt WHERE (from_user_type = '' OR from_user_type IS NULL) AND (from_user_id = '' OR from_user_id IS NULL)";
    $check_result = mysqli_query($db_conn, $check_query);
    
    if (!$check_result) {
        throw new Exception("Check query failed: " . mysqli_error($db_conn));
    }
    
    $pending_data = mysqli_fetch_array($check_result);
    $pending_count = $pending_data['count'] ?? 0;
    
    logMessage("Found $pending_count receipt records needing updates");
    
    if ($pending_count == 0) {
        logMessage("No updates needed. All receipt records are synchronized.");
        
        updateStatus([
            'status' => 'complete',
            'last_run' => date('Y-m-d H:i:s'),
            'pending_count' => 0,
            'total_updated' => 0,
            'execution_time' => round(microtime(true) - $script_start, 2),
            'message' => 'All data synchronized',
            'next_run' => date('Y-m-d H:i:s', strtotime('+15 minutes'))
        ]);
        
        logMessage("=== SCRIPT COMPLETED (NO UPDATES NEEDED) ===");
        exit(0);
    }
    
    // Limit records to process
    $records_to_process = min($pending_count, $MAX_RECORDS_PER_RUN);
    
    logMessage("Processing up to $records_to_process records in batches of $BATCH_SIZE");
    
    // Update status
    updateStatus([
        'status' => 'processing',
        'last_run' => date('Y-m-d H:i:s'),
        'pending_count' => $pending_count,
        'processing_count' => $records_to_process,
        'message' => "Processing $records_to_process records..."
    ]);
    
    $total_updated = 0;
    $batch_number = 1;
    
    // Process in batches using the invoice table (correct table for customer invoices)
    while ($total_updated < $records_to_process) {
        $current_batch_size = min($BATCH_SIZE, $records_to_process - $total_updated);
        
        logMessage("Processing batch #$batch_number (size: $current_batch_size)");
        
        // Step 1: Get receipt IDs that need updating (with LIMIT)
        $id_query = "
        SELECT r.id 
        FROM receipt r 
        WHERE (r.from_user_type = '' OR r.from_user_type IS NULL) 
        AND (r.from_user_id = '' OR r.from_user_id IS NULL)
        LIMIT $current_batch_size";
        
        $id_result = mysqli_query($db_conn, $id_query);
        
        if (!$id_result) {
            throw new Exception("ID selection failed: " . mysqli_error($db_conn));
        }
        
        $receipt_ids = [];
        while ($row = mysqli_fetch_array($id_result)) {
            $receipt_ids[] = $row['id'];
        }
        
        if (empty($receipt_ids)) {
            logMessage("No more records to update in this batch");
            break;
        }
        
        $ids_string = implode(',', $receipt_ids);
        
        // Step 2: Update the specific receipt IDs using invoice table (FIXED!)
        $batch_query = "
        UPDATE receipt r 
        INNER JOIN invoice i ON r.inv_id = i.inv_id 
        SET r.from_user_type = i.user_type,
            r.from_user_id = i.user_id
        WHERE r.id IN ($ids_string)";
        
        $batch_start = microtime(true);
        $batch_result = mysqli_query($db_conn, $batch_query);
        $batch_time = round(microtime(true) - $batch_start, 2);
        
        if (!$batch_result) {
            throw new Exception("Batch update failed: " . mysqli_error($db_conn));
        }
        
        $batch_updated = mysqli_affected_rows($db_conn);
        $total_updated += $batch_updated;
        
        logMessage("Batch #$batch_number: $batch_updated records updated in {$batch_time}s");
        
        // Update progress
        $progress_percent = $records_to_process > 0 ? round(($total_updated / $records_to_process) * 100, 1) : 100;
        updateStatus([
            'status' => 'processing',
            'last_run' => date('Y-m-d H:i:s'),
            'pending_count' => $pending_count,
            'total_updated' => $total_updated,
            'progress_percent' => $progress_percent,
            'current_batch' => $batch_number,
            'message' => "Processing... $progress_percent% complete"
        ]);
        
        $batch_number++;
        
        // Safety check - if no records were updated, break
        if ($batch_updated == 0) {
            logMessage("No more records to update in this batch");
            break;
        }
        
        // Small delay to prevent overwhelming the database
        usleep(100000); // 0.1 seconds
        
        // Time limit check
        if ((microtime(true) - $script_start) > ($MAX_EXECUTION_TIME - 30)) {
            logMessage("Approaching time limit, stopping gracefully");
            break;
        }
    }
    
    // Verify remaining count
    $verify_result = mysqli_query($db_conn, $check_query);
    if ($verify_result) {
        $verify_data = mysqli_fetch_array($verify_result);
        $remaining_count = $verify_data['count'] ?? 0;
    } else {
        $remaining_count = 'unknown';
        logMessage("Warning: Could not verify remaining count");
    }
    
    $script_time = round(microtime(true) - $script_start, 2);
    $memory_peak = round(memory_get_peak_usage() / 1024 / 1024, 2);
    
    logMessage("=== UPDATE COMPLETED ===");
    logMessage("Records updated: $total_updated");
    logMessage("Records remaining: $remaining_count");
    logMessage("Execution time: {$script_time}s");
    logMessage("Peak memory: {$memory_peak}MB");
    
    // Final status update
    $final_status = [
        'status' => ($remaining_count === 0 || $remaining_count === '0') ? 'complete' : 'partial',
        'last_run' => date('Y-m-d H:i:s'),
        'total_updated' => $total_updated,
        'remaining_count' => $remaining_count,
        'execution_time' => $script_time,
        'memory_usage' => $memory_peak,
        'batches_processed' => $batch_number - 1,
        'next_run' => date('Y-m-d H:i:s', strtotime('+15 minutes'))
    ];
    
    if ($remaining_count === 0 || $remaining_count === '0') {
        $final_status['message'] = 'All receipt records synchronized successfully';
    } else {
        $final_status['message'] = "$remaining_count records remaining for next run";
    }
    
    updateStatus($final_status);
    
    // Clean up old log entries
    if (file_exists($log_file) && is_writable($log_file)) {
        $log_content = file($log_file);
        if (count($log_content) > 100) {
            $recent_logs = array_slice($log_content, -100);
            file_put_contents($log_file, implode('', $recent_logs), LOCK_EX);
        }
    }
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    
    // Update error status
    updateStatus([
        'status' => 'error',
        'last_run' => date('Y-m-d H:i:s'),
        'error' => $e->getMessage(),
        'message' => 'Update failed - will retry in 15 minutes',
        'next_retry' => date('Y-m-d H:i:s', strtotime('+15 minutes'))
    ]);
    
    exit(1);
    
} finally {
    // Clean up database connection
    if (isset($db_conn) && $db_conn instanceof mysqli) {
        mysqli_close($db_conn);
        logMessage("Database connection closed");
    }
    
    logMessage("=== SCRIPT COMPLETED ===");
}

?>