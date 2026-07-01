<?php
/**
 * Daily Login & Billing Reward Service
 * 
 * Purpose: Award 5 points to users who login and create their first bill of the day
 * User Types: super_stockiest, stockiest, distributor, super_distributor
 * 
 * @version 1.0.0
 * @created 2025-11-14
 */

class DailyRewardService {
    
    private $db;
    private $pointsPerDay = 1;
    private $eligibleUserTypes = ['super_stockiest', 'stockiest', 'distributor', 'super_distributor'];
    
    /**
     * Constructor
     * @param mysqli $db_connection Database connection
     */
    public function __construct($db_connection) {
        if (!$db_connection || !($db_connection instanceof mysqli)) {
            throw new Exception('Valid database connection required');
        }
        $this->db = $db_connection;
        $this->loadConfiguration();
    }
    
    /**
     * Load configuration from database
     */
    private function loadConfiguration(): void {
        $query = "SELECT setting_key, setting_value FROM daily_reward_config";
        $result = mysqli_query($this->db, $query);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                if ($row['setting_key'] === 'reward_points_per_day') {
                    $this->pointsPerDay = (int)$row['setting_value'];
                } elseif ($row['setting_key'] === 'eligible_user_types') {
                    $this->eligibleUserTypes = explode(',', $row['setting_value']);
                }
            }
        }
    }
    
    /**
     * Check if reward system is enabled
     * @return bool
     */
    public function isRewardSystemEnabled(): bool {
        $stmt = mysqli_prepare($this->db, 
            "SELECT setting_value FROM daily_reward_config 
             WHERE setting_key = 'reward_enabled' LIMIT 1"
        );
        
        if (!$stmt) {
            return false;
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $row && $row['setting_value'] == '1';
    }
    
    /**
     * Check if user is eligible for rewards
     * @param string $userType User type (e.g., 'super_stockiest')
     * @return bool
     */
    public function isUserTypeEligible(string $userType): bool {
        return in_array($userType, $this->eligibleUserTypes);
    }
    
    /**
     * Check if user has already received reward today
     * @param string $userType User type
     * @param string $userId User ID (temp_id)
     * @param string|null $date Optional date (default: today)
     * @return bool True if already rewarded
     */
    public function hasReceivedRewardToday(string $userType, string $userId, ?string $date = null): bool {
        $checkDate = $date ?? date('Y-m-d');
        
        $stmt = mysqli_prepare($this->db,
            "SELECT id FROM daily_login_rewards 
             WHERE user_type = ? 
               AND user_id = ? 
               AND reward_date = ?
             LIMIT 1"
        );
        
        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($this->db));
        }
        
        mysqli_stmt_bind_param($stmt, "sss", $userType, $userId, $checkDate);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = mysqli_fetch_assoc($result) !== null;
        mysqli_stmt_close($stmt);
        
        return $exists;
    }
    
    /**
     * Check if user has logged in today
     * @param string $userType User type
     * @param string $userId User ID
     * @return bool
     */
    public function hasLoggedInToday(string $userType, string $userId): bool {
        // Map user types to their respective tables
        $tableMap = [
            'super_stockiest' => 'super_stockiest',
            'stockiest' => 'stockiest',
            'distributor' => 'distributor',
            'super_distributor' => 'super_distributor'
        ];
        
        if (!isset($tableMap[$userType])) {
            return false;
        }
        
        $table = $tableMap[$userType];
        $today = date('Y-m-d');
        
        $stmt = mysqli_prepare($this->db,
            "SELECT last_login FROM `{$table}` 
             WHERE temp_id = ? 
             LIMIT 1"
        );
        
        if (!$stmt) {
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "s", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$row || !$row['last_login']) {
            return false;
        }
        
        $lastLoginDate = date('Y-m-d', strtotime($row['last_login']));
        return $lastLoginDate === $today;
    }
    
    /**
     * Award daily reward points to user
     * @param string $userType User type
     * @param string $userId User ID (temp_id)
     * @param string $invoiceId Invoice ID
     * @param string $invoiceNumber Human-readable invoice number
     * @param string|null $rewardDate Optional date (default: today)
     * @return array Result with success status and message
     */
    public function awardDailyReward(
        string $userType, 
        string $userId, 
        string $invoiceId, 
        string $invoiceNumber,
        ?string $rewardDate = null
    ): array {
        
        $rewardDate = $rewardDate ?? date('Y-m-d');
        
        // Validation
        if (empty($userType) || empty($userId) || empty($invoiceId)) {
            return [
                'success' => false,
                'message' => 'Invalid parameters provided'
            ];
        }
        
        // Check if system is enabled
        if (!$this->isRewardSystemEnabled()) {
            return [
                'success' => false,
                'message' => 'Reward system is currently disabled'
            ];
        }
        
        // Check if user type is eligible
        if (!$this->isUserTypeEligible($userType)) {
            return [
                'success' => false,
                'message' => 'User type not eligible for rewards'
            ];
        }
        
        // Check if already rewarded today
        if ($this->hasReceivedRewardToday($userType, $userId, $rewardDate)) {
            return [
                'success' => false,
                'message' => 'Reward already received for this date',
                'already_rewarded' => true
            ];
        }
        
        // Check if user logged in today (for current date only)
        if ($rewardDate === date('Y-m-d')) {
            if (!$this->hasLoggedInToday($userType, $userId)) {
                return [
                    'success' => false,
                    'message' => 'User has not logged in today'
                ];
            }
        }
        
        // Start transaction
        mysqli_begin_transaction($this->db);
        
        try {
            // Insert reward tracking entry (NO WALLET!)
            $rewardId = $this->insertRewardTracking(
                $userType, 
                $userId, 
                $rewardDate, 
                $invoiceId, 
                $invoiceNumber
            );
            
            if (!$rewardId) {
                throw new Exception('Failed to insert reward tracking');
            }
            
            // Log audit trail
            $this->logAuditTrail(
                'daily_reward', 
                $userType, 
                $userId, 
                $rewardDate, 
                $this->pointsPerDay,
                "Invoice: {$invoiceNumber}"
            );
            
            // Commit transaction
            mysqli_commit($this->db);
            
            return [
                'success' => true,
                'message' => "Daily reward of {$this->pointsPerDay} points awarded successfully!",
                'points_awarded' => $this->pointsPerDay,
                'reward_id' => $rewardId
            ];
            
        } catch (Exception $e) {
            // Rollback on error
            mysqli_rollback($this->db);
            
            // Log error
            error_log("Daily Reward Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error awarding reward: ' . $e->getMessage()
            ];
        }
    }
    
    
    /**
     * Insert tracking entry (NO WALLET INSERTION!)
     * @return int|false Reward tracking ID or false on failure
     */
    private function insertRewardTracking(
        string $userType, 
        string $userId, 
        string $rewardDate, 
        string $invoiceId, 
        string $invoiceNumber
    ) {
        $stmt = mysqli_prepare($this->db,
            "INSERT INTO daily_login_rewards (
                user_type, user_id, reward_date,
                points_awarded, invoice_id, invoice_number,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        
        if (!$stmt) {
            throw new Exception('Failed to prepare tracking insert: ' . mysqli_error($this->db));
        }
        
        mysqli_stmt_bind_param($stmt, "sssiss",
            $userType, $userId, $rewardDate,
            $this->pointsPerDay, $invoiceId, $invoiceNumber
        );
        
        $success = mysqli_stmt_execute($stmt);
        
        if (!$success) {
            throw new Exception('Failed to execute tracking insert: ' . mysqli_stmt_error($stmt));
        }
        
        $rewardId = mysqli_insert_id($this->db);
        mysqli_stmt_close($stmt);
        
        return $rewardId;
    }
    
    private function logAuditTrail(
    string $actionType, 
    string $userType, 
    string $userId, 
    string $rewardDate, 
    int $points,
    ?string $notes = null
): void {
    $stmt = mysqli_prepare($this->db,
        "INSERT INTO daily_reward_audit_log (
            action_type, user_type, user_id, reward_date,
            points_amount, invoice_id, invoice_number, created_at
        ) VALUES (?, ?, ?, ?, ?, '', ?, NOW())"
    );
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssds",
            $actionType, $userType, $userId, $rewardDate,
            $points, $notes
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
    
    /**
     * Get reward statistics for a user
     * @param string $userType User type
     * @param string $userId User ID
     * @return array Statistics
     */
    public function getUserRewardStats(string $userType, string $userId): array {
        $stmt = mysqli_prepare($this->db,
            "SELECT 
                COUNT(*) as total_rewards,
                SUM(points_awarded) as total_points,
                MAX(reward_date) as last_reward_date,
                MIN(reward_date) as first_reward_date
             FROM daily_login_rewards
             WHERE user_type = ? AND user_id = ?"
        );
        
        if (!$stmt) {
            return [];
        }
        
        mysqli_stmt_bind_param($stmt, "ss", $userType, $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $stats ?: [];
    }
    
    /**
     * Get reward history for a user
     * @param string $userType User type
     * @param string $userId User ID
     * @param int $limit Limit results
     * @return array Reward history
     */
    public function getUserRewardHistory(string $userType, string $userId, int $limit = 30): array {
        $stmt = mysqli_prepare($this->db,
            "SELECT 
                reward_date, points_awarded, invoice_number,
                created_at, notes
             FROM daily_login_rewards
             WHERE user_type = ? AND user_id = ?
             ORDER BY reward_date DESC
             LIMIT ?"
        );
        
        if (!$stmt) {
            return [];
        }
        
        mysqli_stmt_bind_param($stmt, "ssi", $userType, $userId, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $history = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        
        return $history;
    }
}