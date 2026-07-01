<?php
/**
 * Daily Reward System - Backfill Script
 * 
 * Purpose: Backfill daily rewards from November 1, 2025 to today
 * Logic: For each day, if user created a bill, they get 5 points
 * 
 * CORRECTED VERSION:
 * - Fixed: account_status column name (was 'status')
 * - Fixed: Added invoice table search for customer invoices
 * - Fixed: Uses correct user_id format for each table
 * 
 * Usage:
 *   php 03-backfill-rewards.php                    // Dry run (preview only)
 *   php 03-backfill-rewards.php --execute          // Execute backfill
 *   php 03-backfill-rewards.php --rollback         // Rollback all backfilled rewards
 *   php 03-backfill-rewards.php --execute --user-type=super_stockiest  // Specific user type only
 * 
 * @version 1.1.0 - CORRECTED
 * @created 2025-11-14
 * @updated 2025-11-17
 */

// Prevent execution from web browser
if (PHP_SAPI !== 'cli') {
    die("This script can only be run from command line\n");
}

// Set timezone
date_default_timezone_set("Asia/Kolkata");

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include dependencies
require_once __DIR__ . '/include/db-connect.php';
require_once __DIR__ . '/include/DailyRewardService.php';

// ============================================================================
// Configuration
// ============================================================================

$config = [
    'start_date' => '2025-11-01',
    /*'end_date' => date('Y-m-d'),*/
    'end_date' => '2025-11-30', // Today
    'dry_run' => true,
    'rollback' => false,
    'user_types' => ['super_stockiest', 'stockiest', 'distributor', 'super_distributor'],
    'batch_size' => 100, // Process 100 users at a time
];

// ============================================================================
// Parse Command Line Arguments
// ============================================================================

$options = getopt('', [
    'execute',
    'rollback',
    'user-type:',
    'start-date:',
    'end-date:',
    'help'
]);

if (isset($options['help'])) {
    displayHelp();
    exit(0);
}

if (isset($options['execute'])) {
    $config['dry_run'] = false;
}

if (isset($options['rollback'])) {
    $config['rollback'] = true;
    $config['dry_run'] = false;
}

if (isset($options['user-type'])) {
    $config['user_types'] = [$options['user-type']];
}

if (isset($options['start-date'])) {
    $config['start_date'] = $options['start-date'];
}

if (isset($options['end-date'])) {
    $config['end_date'] = $options['end-date'];
}

// ============================================================================
// Initialize
// ============================================================================

$rewardService = new DailyRewardService($db_conn);
$stats = [
    'total_processed' => 0,
    'successful' => 0,
    'failed' => 0,
    'skipped' => 0,
    'total_points' => 0,
    'errors' => []
];

// ============================================================================
// Display Header
// ============================================================================

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  DAILY REWARD SYSTEM - BACKFILL SCRIPT (CORRECTED v1.1)\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";

if ($config['rollback']) {
    echo "⚠️  ROLLBACK MODE - Will remove all backfilled rewards\n";
} elseif ($config['dry_run']) {
    echo "🔍 DRY RUN MODE - No changes will be made\n";
} else {
    echo "✅ EXECUTION MODE - Changes will be applied\n";
}

echo "\n";
echo "Configuration:\n";
echo "  Start Date: {$config['start_date']}\n";
echo "  End Date: {$config['end_date']}\n";
echo "  User Types: " . implode(', ', $config['user_types']) . "\n";
echo "\n";

// Confirm execution if not dry run
if (!$config['dry_run'] && !confirmExecution()) {
    echo "Operation cancelled.\n";
    exit(0);
}

// ============================================================================
// Main Execution
// ============================================================================

try {
    
    if ($config['rollback']) {
        performRollback($db_conn, $config, $stats);
    } else {
        performBackfill($db_conn, $rewardService, $config, $stats);
    }
    
} catch (Exception $e) {
    echo "\n❌ Fatal Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

// ============================================================================
// Display Summary
// ============================================================================

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";
echo "Total Processed: {$stats['total_processed']}\n";
echo "Successful: {$stats['successful']}\n";
echo "Failed: {$stats['failed']}\n";
echo "Skipped: {$stats['skipped']}\n";
echo "Total Points Awarded: {$stats['total_points']}\n";
echo "\n";

if (!empty($stats['errors'])) {
    echo "Errors:\n";
    foreach ($stats['errors'] as $error) {
        echo "  - $error\n";
    }
    echo "\n";
}

if ($config['dry_run']) {
    echo "💡 This was a dry run. Use --execute to apply changes.\n";
}

echo "\n";
exit(0);

// ============================================================================
// Functions
// ============================================================================

/**
 * Perform backfill operation
 */
function performBackfill($db, $rewardService, $config, &$stats) {
    echo "Starting backfill process...\n\n";
    
    // Get date range
    $startDate = new DateTime($config['start_date']);
    $endDate = new DateTime($config['end_date']);
    $totalDays = $startDate->diff($endDate)->days + 1;
    
    echo "Processing {$totalDays} days...\n\n";
    
    // Process each user type
    foreach ($config['user_types'] as $userType) {
        echo "Processing user type: {$userType}\n";
        echo str_repeat("-", 60) . "\n";
        
        // Get all users of this type
        $users = getUsersByType($db, $userType);
        echo "Found " . count($users) . " users\n\n";
        
        // Process each user
        foreach ($users as $user) {
            processUserBackfill($db, $rewardService, $user, $userType, $config, $stats);
        }
        
        echo "\n";
    }
}

/**
 * Get users by type
 * FIXED: Uses 'account_status' instead of 'status'
 */
function getUsersByType($db, $userType) {
    $tableMap = [
        'super_stockiest' => 'super_stockiest',
        'stockiest' => 'stockiest',
        'distributor' => 'distributor',
        'super_distributor' => 'super_distributor'
    ];
    
    if (!isset($tableMap[$userType])) {
        return [];
    }
    
    $table = $tableMap[$userType];
    
    // FIXED: Changed 'status' to 'account_status'
    $query = "SELECT temp_id, name, mobile_number FROM `{$table}` WHERE account_status = 'active'";
    $result = mysqli_query($db, $query);
    
    if (!$result) {
        echo "  ⚠️  Error querying {$table}: " . mysqli_error($db) . "\n";
        return [];
    }
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    
    return $users;
}

/**
 * Process backfill for a single user
 */
function processUserBackfill($db, $rewardService, $user, $userType, $config, &$stats) {
    $userId = $user['temp_id'];
    $userName = $user['name'];
    
    echo "  Processing: {$userName} ({$userId})\n";
    
    // Get user's invoices in date range
    $invoices = getUserInvoices($db, $userType, $userId, $config['start_date'], $config['end_date']);
    
    if (empty($invoices)) {
        echo "    No invoices found\n";
        $stats['skipped']++;
        return;
    }
    
    // Group invoices by date
    $invoicesByDate = [];
    foreach ($invoices as $invoice) {
        $date = date('Y-m-d', strtotime($invoice['date']));
        if (!isset($invoicesByDate[$date])) {
            $invoicesByDate[$date] = [];
        }
        $invoicesByDate[$date][] = $invoice;
    }
    
    echo "    Found invoices on " . count($invoicesByDate) . " days\n";
    
    // Award reward for each day
    $awarded = 0;
    foreach ($invoicesByDate as $date => $dayInvoices) {
        // Use first invoice of the day
        $firstInvoice = $dayInvoices[0];
        
        // Check if already rewarded
        if ($rewardService->hasReceivedRewardToday($userType, $userId, $date)) {
            echo "    {$date}: Already rewarded (skipped)\n";
            $stats['skipped']++;
            continue;
        }
        
        // Award reward
        if (!$config['dry_run']) {
            $result = $rewardService->awardDailyReward(
                $userType,
                $userId,
                $firstInvoice['inv_id'],
                $firstInvoice['inv_number'],
                $date
            );
            
            if ($result['success']) {
                echo "    {$date}: ✅ Awarded 5 points (Invoice: {$firstInvoice['inv_number']})\n";
                $stats['successful']++;
                $stats['total_points'] += 5;
                $awarded++;
            } else {
                echo "    {$date}: ❌ Failed - {$result['message']}\n";
                $stats['failed']++;
                $stats['errors'][] = "{$userName} ({$date}): {$result['message']}";
            }
        } else {
            echo "    {$date}: 🔍 Would award 5 points (Invoice: {$firstInvoice['inv_number']})\n";
            $awarded++;
            $stats['total_points'] += 5;
        }
        
        $stats['total_processed']++;
    }
    
    echo "    Total: {$awarded} days would receive rewards\n\n";
}

/**
 * Get user invoices in date range
 * FIXED: Now searches BOTH tables (user_invoice AND invoice)
 */
function getUserInvoices($db, $userType, $userId, $startDate, $endDate) {
    $invoices = [];
    
    // 1. Get B2B invoices from user_invoice table
    $stmt1 = mysqli_prepare($db,
        "SELECT inv_id, inv_number, date 
         FROM user_invoice 
         WHERE from_user_type = ? 
           AND from_user_id = ?
           AND DATE(date) >= ?
           AND DATE(date) <= ?
         ORDER BY date ASC, id ASC"
    );
    
    if ($stmt1) {
        mysqli_stmt_bind_param($stmt1, "ssss", $userType, $userId, $startDate, $endDate);
        mysqli_stmt_execute($stmt1);
        $result1 = mysqli_stmt_get_result($stmt1);
        
        while ($row = mysqli_fetch_assoc($result1)) {
            $invoices[] = $row;
        }
        
        mysqli_stmt_close($stmt1);
    }
    
    // 2. Get customer invoices from invoice table
    // FIXED: Now also searches invoice table for customer invoices
    $stmt2 = mysqli_prepare($db,
        "SELECT inv_id, inv_number, date 
         FROM invoice 
         WHERE user_type = ? 
           AND user_id = ?
           AND DATE(date) >= ?
           AND DATE(date) <= ?
         ORDER BY date ASC, inv_id ASC"
    );
    
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, "ssss", $userType, $userId, $startDate, $endDate);
        mysqli_stmt_execute($stmt2);
        $result2 = mysqli_stmt_get_result($stmt2);
        
        while ($row = mysqli_fetch_assoc($result2)) {
            $invoices[] = $row;
        }
        
        mysqli_stmt_close($stmt2);
    }
    
    return $invoices;
}

/**
 * Perform rollback operation
 * Note: Only removes from daily_login_rewards (no wallet entries)
 */
function performRollback($db, $config, &$stats) {
    echo "⚠️  Starting rollback process...\n\n";
    echo "This will remove all rewards between {$config['start_date']} and {$config['end_date']}\n\n";
    
    if (!confirmExecution("Are you ABSOLUTELY sure you want to rollback? ")) {
        echo "Rollback cancelled.\n";
        exit(0);
    }
    
    // Start transaction
    mysqli_begin_transaction($db);
    
    try {
        // Get all rewards in date range
        $stmt = mysqli_prepare($db,
            "SELECT id, user_type, user_id, reward_date, points_awarded
             FROM daily_login_rewards
             WHERE reward_date >= ? AND reward_date <= ?"
        );
        
        mysqli_stmt_bind_param($stmt, "ss", $config['start_date'], $config['end_date']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $rewardsToRemove = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rewardsToRemove[] = $row;
        }
        mysqli_stmt_close($stmt);
        
        echo "Found " . count($rewardsToRemove) . " rewards to remove\n\n";
        
        // Remove each reward
        foreach ($rewardsToRemove as $reward) {
            // Delete reward tracking (no wallet to delete in corrected version)
            $deleteReward = mysqli_prepare($db,
                "DELETE FROM daily_login_rewards WHERE id = ?"
            );
            mysqli_stmt_bind_param($deleteReward, "i", $reward['id']);
            mysqli_stmt_execute($deleteReward);
            mysqli_stmt_close($deleteReward);
            
            echo "Removed: {$reward['user_type']} - {$reward['user_id']} - {$reward['reward_date']}\n";
            
            $stats['successful']++;
            $stats['total_points'] -= $reward['points_awarded'];
        }
        
        // Log audit trail
        $actionType = 'backfill_rollback';
        $notes = "Rolled back rewards from {$config['start_date']} to {$config['end_date']}";
        $ipAddress = 'CLI';
        $adminUser = 'system';
        
        $auditStmt = mysqli_prepare($db,
            "INSERT INTO daily_reward_audit_log 
             (action_type, notes, ip_address, admin_user, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        
        if ($auditStmt) {
            mysqli_stmt_bind_param($auditStmt, "ssss", $actionType, $notes, $ipAddress, $adminUser);
            mysqli_stmt_execute($auditStmt);
            mysqli_stmt_close($auditStmt);
        }
        
        // Commit transaction
        mysqli_commit($db);
        
        echo "\n✅ Rollback completed successfully\n";
        
    } catch (Exception $e) {
        mysqli_rollback($db);
        throw $e;
    }
}

/**
 * Confirm execution
 */
function confirmExecution($message = "Are you sure you want to continue? ") {
    echo "\n";
    echo $message . "(yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    return strtolower($line) === 'yes';
}

/**
 * Display help
 */
function displayHelp() {
    echo "\n";
    echo "Daily Reward System - Backfill Script (CORRECTED v1.1)\n";
    echo "========================================================\n";
    echo "\n";
    echo "Fixes in this version:\n";
    echo "  ✅ Fixed account_status column name (was 'status')\n";
    echo "  ✅ Added invoice table search for customer invoices\n";
    echo "  ✅ Improved error handling and logging\n";
    echo "\n";
    echo "Usage:\n";
    echo "  php 03-backfill-rewards.php [options]\n";
    echo "\n";
    echo "Options:\n";
    echo "  --execute              Execute the backfill (default is dry-run)\n";
    echo "  --rollback             Rollback all backfilled rewards\n";
    echo "  --user-type=TYPE       Process specific user type only\n";
    echo "  --start-date=DATE      Start date (YYYY-MM-DD)\n";
    echo "  --end-date=DATE        End date (YYYY-MM-DD)\n";
    echo "  --help                 Display this help message\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php 03-backfill-rewards.php\n";
    echo "    (Dry run - preview what would happen)\n";
    echo "\n";
    echo "  php 03-backfill-rewards.php --execute\n";
    echo "    (Execute backfill for all user types)\n";
    echo "\n";
    echo "  php 03-backfill-rewards.php --execute --user-type=stockiest\n";
    echo "    (Execute for stockists only)\n";
    echo "\n";
    echo "  php 03-backfill-rewards.php --rollback\n";
    echo "    (Remove all backfilled rewards)\n";
    echo "\n";
}