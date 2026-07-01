#!/usr/bin/env php
<?php
/**
 * Monthly Target Rewards Backfill - CLI Version
 * Fixed for db_conn variable
 * 
 * Usage:
 *   php monthly_target_backfill_cli.php --mode=dry_run --start=7 --end=11
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line\n");
}

$options = getopt('', ['mode:', 'start:', 'end:', 'user_type::', 'help']);

if (isset($options['help']) || empty($options)) {
    showHelp();
    exit(0);
}

if (!isset($options['mode']) || !isset($options['start']) || !isset($options['end'])) {
    echo "ERROR: Missing required parameters\n\n";
    showHelp();
    exit(1);
}

$mode = $options['mode'];
$startMonth = (int)$options['start'];
$endMonth = (int)$options['end'];
$userType = $options['user_type'] ?? '';

if (!in_array($mode, ['dry_run', 'execute', 'rollback'], true)) {
    echo "ERROR: Invalid mode. Must be: dry_run, execute, or rollback\n";
    exit(1);
}

if ($startMonth < 1 || $startMonth > 12 || $endMonth < 1 || $endMonth > 12) {
    echo "ERROR: Invalid month range. Must be between 1-12\n";
    exit(1);
}

if ($startMonth > $endMonth) {
    echo "ERROR: Start month cannot be after end month\n";
    exit(1);
}

if ($userType !== '' && !in_array($userType, ['super_stockiest', 'stockiest'], true)) {
    echo "ERROR: Invalid user_type. Must be: super_stockiest or stockiest\n";
    exit(1);
}

// Include required files - using relative path for company folder
$baseDir = dirname(__FILE__);
require_once($baseDir . '/include/db-connect.php');
require_once($baseDir . '/include/MonthlyTargetCalculator.class.php');

// Display header
echo "\n";
echo "========================================\n";
echo "MONTHLY TARGET REWARDS BACKFILL\n";
echo "========================================\n";
echo "Mode:        " . strtoupper($mode) . "\n";
echo "Period:      Month $startMonth to Month $endMonth, 2025\n";
echo "User Type:   " . ($userType ?: 'All (Super Stockists & Stockists)') . "\n";
echo "Time:        " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

if ($mode !== 'dry_run') {
    echo "WARNING: This will make changes to the database!\n";
    echo "Press ENTER to continue or Ctrl+C to cancel...\n";
    fgets(STDIN);
}

$startTime = microtime(true);

try {
    // Use db_conn from the included file
    $calculator = new MonthlyTargetCalculator($db_conn);
    
    echo "[INFO] Initialized calculator\n";
    
    if ($mode === 'rollback') {
        performRollback($db_conn, $calculator, $startMonth, $endMonth, $userType);
        echo "\n[SUCCESS] Rollback completed\n";
        exit(0);
    }
    
    $users = getEligibleUsers($db_conn, $userType);
    echo "[INFO] Found " . count($users) . " eligible users\n\n";
    
    if ($mode === 'execute') {
        $db_conn->begin_transaction();
        echo "[INFO] Transaction started\n\n";
    }
    
    $processedCount = 0;
    $rewardsGranted = 0;
    $totalPoints = 0;
    
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
                $monthName = getMonthName($month);
                
                if ($achievement['points_awarded'] > 0) {
                    echo "[SUCCESS] {$user['user_type']} [{$user['user_id']}] - $monthName: ";
                    echo "{$achievement['achieved_subordinates']}/{$achievement['total_subordinates']} achieved ";
                    echo "({$achievement['achievement_percentage']}%) - ";
                    echo "{$achievement['reward_tier']} - {$achievement['points_awarded']} points\n";
                    
                    $rewardsGranted++;
                    $totalPoints += $achievement['points_awarded'];
                } else {
                    if ($achievement['total_subordinates'] > 0) {
                        echo "[INFO] {$user['user_type']} [{$user['user_id']}] - $monthName: ";
                        echo "{$achievement['achieved_subordinates']}/{$achievement['total_subordinates']} achieved - No reward\n";
                    }
                }
                
            } catch (Exception $e) {
                echo "[ERROR] Failed processing {$user['user_type']} [{$user['user_id']}] for month $month: ";
                echo $e->getMessage() . "\n";
            }
        }
    }
    
    if ($mode === 'execute') {
        // Log audit BEFORE committing the transaction
        $calculator->logAudit('backfill_cli_complete', [
            'description' => "CLI backfill executed for months {$startMonth}-{$endMonth} 2025",
            'mode' => 'execute',
            'records' => $rewardsGranted,
            'admin' => 'cli_script',
            'notes' => "{$rewardsGranted} rewards granted, {$totalPoints} total points"
        ]);
        
        $db_conn->commit();
        echo "\n[INFO] Transaction committed\n";
    }
    
    echo "\n========================================\n";
    echo "SUMMARY\n";
    echo "========================================\n";
    echo "Total users processed:  $processedCount\n";
    echo "Rewards granted:        $rewardsGranted\n";
    echo "Total points awarded:   $totalPoints\n";
    echo "Execution time:         " . round(microtime(true) - $startTime, 2) . "s\n";
    echo "========================================\n\n";
    
    if ($mode === 'dry_run') {
        echo "[INFO] DRY RUN completed - No changes made to database\n";
        echo "[INFO] Run with --mode=execute to save these changes\n";
    } else {
        echo "[SUCCESS] Backfill completed successfully\n";
    }
    
} catch (Exception $e) {
    if (isset($db_conn) && $mode === 'execute') {
        $db_conn->rollback();
        echo "\n[ERROR] Transaction rolled back\n";
    }
    
    echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
    
} finally {
    if (isset($db_conn)) {
        $db_conn->close();
    }
}

exit(0);

function showHelp(): void
{
    echo <<<HELP

Monthly Target Rewards Backfill - CLI Tool

USAGE:
    php monthly_target_backfill_cli.php [OPTIONS]

REQUIRED OPTIONS:
    --mode=MODE         Execution mode: dry_run, execute, or rollback
    --start=MONTH       Start month (1-12)
    --end=MONTH         End month (1-12)

OPTIONAL OPTIONS:
    --user_type=TYPE    Filter by user type: super_stockiest or stockiest
    --help              Show this help message

EXAMPLES:
    php monthly_target_backfill_cli.php --mode=dry_run --start=7 --end=11
    php monthly_target_backfill_cli.php --mode=execute --start=7 --end=11
    php monthly_target_backfill_cli.php --mode=rollback --start=7 --end=11

HELP;
}

function getEligibleUsers(mysqli $conn, string $userType = ''): array
{
    $users = [];
    
    $userTypes = [];
    if ($userType === '') {
        $userTypes = ['super_stockiest', 'stockiest'];
    } else {
        $userTypes = [$userType];
    }
    
    foreach ($userTypes as $type) {
        $table = $type;
        
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

function performRollback(
    mysqli $conn,
    MonthlyTargetCalculator $calculator,
    int $startMonth,
    int $endMonth,
    string $userType
): void {
    echo "[WARNING] Starting rollback process\n\n";
    
    $conn->begin_transaction();
    
    try {
        $userTypeCondition = '';
        if ($userType !== '') {
            $userTypeCondition = $conn->real_escape_string($userType);
            $userTypeCondition = " AND user_type = '{$userTypeCondition}'";
        }
        
        $sql = "DELETE FROM monthly_target_rewards WHERE reward_year = 2025 AND reward_month BETWEEN {$startMonth} AND {$endMonth} {$userTypeCondition}";
        $conn->query($sql);
        $rewardsDeleted = $conn->affected_rows;
        echo "[INFO] Deleted {$rewardsDeleted} reward entries\n";
        
        $sql = "DELETE FROM monthly_target_achievements WHERE achievement_year = 2025 AND achievement_month BETWEEN {$startMonth} AND {$endMonth}";
        if ($userType !== '') {
            $sql .= " AND parent_type = '{$userTypeCondition}'";
        }
        $conn->query($sql);
        $achievementsDeleted = $conn->affected_rows;
        echo "[INFO] Deleted {$achievementsDeleted} achievement entries\n";
        
        $monthNames = [];
        for ($m = $startMonth; $m <= $endMonth; $m++) {
            $monthNames[] = "'" . getMonthName($m, true) . "'";
        }
        $monthList = implode(',', $monthNames);
        
        $sql = "DELETE FROM wallet_monthly_sls_report WHERE year = 2025 AND month IN ({$monthList}) AND commission_type = 'monthly_target_reward'";
        if ($userType !== '') {
            $sql .= " AND user_type = '{$userTypeCondition}'";
        }
        $conn->query($sql);
        $walletDeleted = $conn->affected_rows;
        echo "[INFO] Deleted {$walletDeleted} wallet entries\n";
        
        $conn->commit();
        
        $calculator->logAudit('rollback_cli_complete', [
            'description' => "CLI rollback executed for months {$startMonth}-{$endMonth} 2025",
            'mode' => 'rollback',
            'records' => $rewardsDeleted + $achievementsDeleted + $walletDeleted,
            'admin' => 'cli_script',
            'notes' => "Deleted {$rewardsDeleted} rewards, {$achievementsDeleted} achievements, {$walletDeleted} wallet entries"
        ]);
        
        echo "\n[SUCCESS] Rollback completed\n";
        echo "  Rewards deleted:       {$rewardsDeleted}\n";
        echo "  Achievements deleted:  {$achievementsDeleted}\n";
        echo "  Wallet entries deleted: {$walletDeleted}\n";
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

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