<?php
/**
 * Monthly Target Rewards Backfill Processor
 * 
 * Handles AJAX requests for backfill operations
 * 
 * @category Rewards
 * @package  MonthlyTargetRewards
 * @author   Femi9 Development Team
 * @created  2025-11-29
 * @location /company/process_monthly_target_backfill.php
 */

declare(strict_types=1);

// Security: Only allow Company users
session_start();
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'company') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid session']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// CSRF Token validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit();
}

// Include required files
require_once('../includes/connection.php');
require_once('../includes/MonthlyTargetCalculator.class.php');

// Set JSON header
header('Content-Type: application/json');

try {
    // Validate input parameters
    $mode = $_POST['mode'] ?? '';
    $startMonth = isset($_POST['start_month']) ? (int)$_POST['start_month'] : 0;
    $endMonth = isset($_POST['end_month']) ? (int)$_POST['end_month'] : 0;
    $userType = $_POST['user_type'] ?? ''; // Optional: 'super_stockiest', 'stockiest', or empty for both
    
    // Validation
    if (!in_array($mode, ['dry_run', 'execute', 'rollback'], true)) {
        throw new InvalidArgumentException('Invalid execution mode');
    }
    
    if ($startMonth < 1 || $startMonth > 12 || $endMonth < 1 || $endMonth > 12) {
        throw new InvalidArgumentException('Invalid month range');
    }
    
    if ($startMonth > $endMonth) {
        throw new InvalidArgumentException('Start month cannot be after end month');
    }
    
    if ($userType !== '' && !in_array($userType, ['super_stockiest', 'stockiest'], true)) {
        throw new InvalidArgumentException('Invalid user type');
    }
    
    // Start timing
    $startTime = microtime(true);
    
    // Initialize calculator
    $calculator = new MonthlyTargetCalculator($conn);
    
    // Response array
    $response = [
        'success' => true,
        'mode' => $mode,
        'stats' => [
            'total_users' => 0,
            'rewards_granted' => 0,
            'total_points' => 0,
            'execution_time' => 0
        ],
        'logs' => [],
        'details' => []
    ];
    
    // Handle rollback mode
    if ($mode === 'rollback') {
        $response = handleRollback($conn, $calculator, $startMonth, $endMonth, $userType, $_SESSION['username']);
        $response['stats']['execution_time'] = round(microtime(true) - $startTime, 2);
        echo json_encode($response);
        exit();
    }
    
    // Get all Super Stockists and Stockists
    $users = getEligibleUsers($conn, $userType);
    
    $calculator->addLog("Starting {$mode} process for " . count($users) . " users", 'info');
    $calculator->addLog("Period: Month {$startMonth} to Month {$endMonth}, Year 2025", 'info');
    
    // Begin transaction for execute mode
    if ($mode === 'execute') {
        $conn->begin_transaction();
    }
    
    $processedCount = 0;
    $rewardsGranted = 0;
    $totalPoints = 0;
    
    // Process each user for each month
    foreach ($users as $user) {
        for ($month = $startMonth; $month <= $endMonth; $month++) {
            try {
                $isDryRun = ($mode === 'dry_run');
                
                $achievement = $calculator->calculateMonthlyAchievements(
                    $user['user_type'],
                    $user['user_id'],
                    $month,
                    2025,
                    $isDryRun
                );
                
                $processedCount++;
                
                // Log results
                $monthName = getMonthName($month);
                $logMessage = sprintf(
                    "%s [%s] - %s 2025: %d/%d subordinates achieved (%s%%) - %s - %d points",
                    $user['user_type'],
                    $user['user_id'],
                    $monthName,
                    $achievement['achieved_subordinates'],
                    $achievement['total_subordinates'],
                    number_format($achievement['achievement_percentage'], 2),
                    $achievement['reward_tier'],
                    $achievement['points_awarded']
                );
                
                if ($achievement['points_awarded'] > 0) {
                    $calculator->addLog($logMessage, 'success');
                    $rewardsGranted++;
                    $totalPoints += $achievement['points_awarded'];
                    
                    // Add to details
                    $response['details'][] = $achievement;
                } else {
                    $calculator->addLog($logMessage, 'info');
                }
                
            } catch (Exception $e) {
                $errorMsg = sprintf(
                    "Error processing %s [%s] for month %d: %s",
                    $user['user_type'],
                    $user['user_id'],
                    $month,
                    $e->getMessage()
                );
                $calculator->addLog($errorMsg, 'error');
            }
        }
    }
    
    // Commit or rollback transaction
    if ($mode === 'execute') {
        $conn->commit();
        $calculator->addLog("Transaction committed successfully", 'success');
        
        // Log audit entry
        $calculator->logAudit('backfill_complete', [
            'description' => "Backfill executed for months {$startMonth}-{$endMonth} 2025",
            'mode' => 'execute',
            'records' => $rewardsGranted,
            'admin' => $_SESSION['username'],
            'notes' => "{$rewardsGranted} rewards granted, {$totalPoints} total points"
        ]);
    }
    
    // Summary log
    $calculator->addLog("Process completed", 'success');
    $calculator->addLog("Total users processed: {$processedCount}", 'info');
    $calculator->addLog("Rewards granted: {$rewardsGranted}", 'info');
    $calculator->addLog("Total points awarded: {$totalPoints}", 'info');
    
    // Populate response
    $response['stats']['total_users'] = count($users);
    $response['stats']['rewards_granted'] = $rewardsGranted;
    $response['stats']['total_points'] = $totalPoints;
    $response['stats']['execution_time'] = round(microtime(true) - $startTime, 2);
    $response['logs'] = $calculator->getLogs();
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'logs' => isset($calculator) ? $calculator->getLogs() : []
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

/**
 * Get eligible users for processing
 * 
 * @param mysqli $conn     Database connection
 * @param string $userType Optional user type filter
 * 
 * @return array List of users
 */
function getEligibleUsers(mysqli $conn, string $userType = ''): array
{
    $users = [];
    
    // Determine which user types to fetch
    $userTypes = [];
    if ($userType === '') {
        $userTypes = ['super_stockiest', 'stockiest'];
    } else {
        $userTypes = [$userType];
    }
    
    foreach ($userTypes as $type) {
        $table = $type; // Table names match user types
        
        $stmt = $conn->prepare("
            SELECT 
                temp_id AS user_id,
                name,
                '{$type}' AS user_type
            FROM `{$table}`
            WHERE account_status = 'active'
            ORDER BY temp_id
        ");
        
        if (!$stmt) {
            throw new RuntimeException("Failed to prepare {$type} query: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        $stmt->close();
    }
    
    return $users;
}

/**
 * Handle rollback operation
 * 
 * @param mysqli                   $conn       Database connection
 * @param MonthlyTargetCalculator  $calculator Calculator instance
 * @param int                      $startMonth Start month
 * @param int                      $endMonth   End month
 * @param string                   $userType   Optional user type filter
 * @param string                   $adminUser  Admin username
 * 
 * @return array Response data
 */
function handleRollback(
    mysqli $conn,
    MonthlyTargetCalculator $calculator,
    int $startMonth,
    int $endMonth,
    string $userType,
    string $adminUser
): array {
    $response = [
        'success' => true,
        'mode' => 'rollback',
        'stats' => [
            'total_users' => 0,
            'rewards_deleted' => 0,
            'achievements_deleted' => 0,
            'wallet_entries_deleted' => 0
        ],
        'logs' => []
    ];
    
    $calculator->addLog("Starting rollback process", 'warning');
    
    $conn->begin_transaction();
    
    try {
        // Build WHERE clause for user type filter
        $userTypeCondition = '';
        if ($userType !== '') {
            $userTypeCondition = $conn->real_escape_string($userType);
            $userTypeCondition = " AND user_type = '{$userTypeCondition}'";
        }
        
        // Delete from monthly_target_rewards
        $sql = "
            DELETE FROM monthly_target_rewards
            WHERE reward_year = 2025
            AND reward_month BETWEEN {$startMonth} AND {$endMonth}
            {$userTypeCondition}
        ";
        $conn->query($sql);
        $rewardsDeleted = $conn->affected_rows;
        $calculator->addLog("Deleted {$rewardsDeleted} reward entries", 'warning');
        
        // Delete from monthly_target_achievements
        $sql = "
            DELETE FROM monthly_target_achievements
            WHERE achievement_year = 2025
            AND achievement_month BETWEEN {$startMonth} AND {$endMonth}
        ";
        if ($userType !== '') {
            $sql .= " AND parent_type = '{$userTypeCondition}'";
        }
        $conn->query($sql);
        $achievementsDeleted = $conn->affected_rows;
        $calculator->addLog("Deleted {$achievementsDeleted} achievement entries", 'warning');
        
        // Delete from wallet_monthly_sls_report (monthly target rewards only)
        $monthNames = [];
        for ($m = $startMonth; $m <= $endMonth; $m++) {
            $monthNames[] = "'" . getMonthName($m, true) . "'";
        }
        $monthList = implode(',', $monthNames);
        
        $sql = "
            DELETE FROM wallet_monthly_sls_report
            WHERE year = 2025
            AND month IN ({$monthList})
            AND commission_type = 'monthly_target_reward'
        ";
        if ($userType !== '') {
            $sql .= " AND user_type = '{$userTypeCondition}'";
        }
        $conn->query($sql);
        $walletDeleted = $conn->affected_rows;
        $calculator->addLog("Deleted {$walletDeleted} wallet entries", 'warning');
        
        $conn->commit();
        
        // Log audit entry
        $calculator->logAudit('rollback_complete', [
            'description' => "Rollback executed for months {$startMonth}-{$endMonth} 2025",
            'mode' => 'rollback',
            'records' => $rewardsDeleted + $achievementsDeleted + $walletDeleted,
            'admin' => $adminUser,
            'notes' => "Deleted {$rewardsDeleted} rewards, {$achievementsDeleted} achievements, {$walletDeleted} wallet entries"
        ]);
        
        $calculator->addLog("Rollback completed successfully", 'success');
        
        $response['stats']['rewards_deleted'] = $rewardsDeleted;
        $response['stats']['achievements_deleted'] = $achievementsDeleted;
        $response['stats']['wallet_entries_deleted'] = $walletDeleted;
        $response['logs'] = $calculator->getLogs();
        
    } catch (Exception $e) {
        $conn->rollback();
        $calculator->addLog("Rollback failed: " . $e->getMessage(), 'error');
        
        $response['success'] = false;
        $response['error'] = $e->getMessage();
        $response['logs'] = $calculator->getLogs();
    }
    
    return $response;
}

/**
 * Get month name
 * 
 * @param int  $month Month number (1-12)
 * @param bool $full  Return full name if true, abbreviated if false
 * 
 * @return string Month name
 */
function getMonthName(int $month, bool $full = false): string
{
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    
    $monthsShort = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
    ];
    
    return $full ? ($months[$month] ?? (string)$month) : ($monthsShort[$month] ?? (string)$month);
}
