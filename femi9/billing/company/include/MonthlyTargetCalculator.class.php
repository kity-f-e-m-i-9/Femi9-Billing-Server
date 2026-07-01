<?php
/**
 * Monthly Target Achievement Calculator
 * 
 * Calculates monthly target achievements for distributors/super_distributors
 * and determines reward eligibility for their parent Super Stockists/Stockists
 * 
 * @category Rewards
 * @package  MonthlyTargetRewards
 * @author   Femi9 Development Team
 * @created  2025-11-29
 */

declare(strict_types=1);

class MonthlyTargetCalculator
{
    private mysqli $conn;
    private array $config;
    private array $logs = [];
    
    /**
     * Constructor
     * 
     * @param mysqli $connection Database connection
     */
    public function __construct(mysqli $connection)
    {
        $this->conn = $connection;
        $this->loadConfig();
    }
    
    /**
     * Load configuration from database
     * 
     * @return void
     */
    private function loadConfig(): void
    {
        $stmt = $this->conn->prepare("
            SELECT setting_key, setting_value, data_type 
            FROM monthly_target_config 
            WHERE is_active = 1
        ");
        
        if (!$stmt) {
            throw new RuntimeException("Failed to prepare config query: " . $this->conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $this->config = [];
        while ($row = $result->fetch_assoc()) {
            $value = $row['setting_value'];
            
            // Type casting based on data_type
            switch ($row['data_type']) {
                case 'integer':
                    $value = (int)$value;
                    break;
                case 'boolean':
                    $value = (bool)$value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $this->config[$row['setting_key']] = $value;
        }
        
        $stmt->close();
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key     Configuration key
     * @param mixed  $default Default value if not found
     * 
     * @return mixed Configuration value
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Get distributor's category target amount
     * 
     * @param string $distributorType 'distributor' or 'super_distributor'
     * @param int    $categoryId      Category ID
     * 
     * @return float Target amount
     */
    private function getCategoryTarget(string $distributorType, int $categoryId): float
    {
        $table = $distributorType === 'distributor' 
            ? 'distributor_category' 
            : 'super_distributor_category';
        
        $stmt = $this->conn->prepare("SELECT amount FROM `{$table}` WHERE id = ?");
        
        if (!$stmt) {
            throw new RuntimeException("Failed to prepare category query: " . $this->conn->error);
        }
        
        $stmt->bind_param('i', $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return (float)$row['amount'];
        }
        
        $stmt->close();
        return 0.0;
    }
    
    /**
     * Calculate total sales for a distributor in a specific month
     * 
     * @param string $distributorType 'distributor' or 'super_distributor'
     * @param string $distributorId   Distributor's temp_id
     * @param int    $month          Month (1-12)
     * @param int    $year           Year
     * 
     * @return array ['user_invoice_sales' => float, 'invoice_sales' => float, 'total' => float]
     */
    private function calculateMonthlySales(
        string $distributorType, 
        string $distributorId, 
        int $month, 
        int $year
    ): array {
        $sales = [
            'user_invoice_sales' => 0.0,
            'invoice_sales' => 0.0,
            'total' => 0.0
        ];
        
        // Calculate PURCHASES from user_invoice table (B2B - bought from parent)
        // Check to_user_type because distributor is the BUYER
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(CAST(total AS DECIMAL(10,2))), 0) AS total_sales
            FROM user_invoice
            WHERE to_user_type = ?
            AND to_user_id = ?
            AND MONTH(date) = ?
            AND YEAR(date) = ?
        ");
        
        if (!$stmt) {
            throw new RuntimeException("Failed to prepare user_invoice query: " . $this->conn->error);
        }
        
        $stmt->bind_param('ssii', $distributorType, $distributorId, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $sales['user_invoice_sales'] = (float)$row['total_sales'];
        }
        $stmt->close();
        
        // Note: invoice table (B2C) is not used for purchases
        // Only user_invoice tracks distributor purchases from their parent
        
        // Calculate total (only from user_invoice for purchases)
        $sales['total'] = $sales['user_invoice_sales'];
        
        return $sales;
    }
    
    /**
     * Get all distributors under a parent (Super Stockist or Stockist)
     * 
     * @param string $parentType 'super_stockiest' or 'stockiest'
     * @param string $parentId   Parent's temp_id
     * 
     * @return array List of distributors with their details
     */
    private function getSubordinateDistributors(string $parentType, string $parentId): array
    {
        $distributors = [];
        
        // Determine which distributor types to fetch based on parent type
        $distributorTypes = ['distributor', 'super_distributor'];
        
        foreach ($distributorTypes as $distType) {
            // Use onboard_userID and onboard_userTYPE to find subordinates
            $stmt = $this->conn->prepare("
                SELECT 
                    temp_id,
                    name,
                    category_id,
                    '{$distType}' AS distributor_type
                FROM `{$distType}`
                WHERE onboard_userID = ?
                AND onboard_userTYPE = ?
                AND account_status = 'active'
            ");
            
            if (!$stmt) {
                throw new RuntimeException("Failed to prepare {$distType} query: " . $this->conn->error);
            }
            
            $stmt->bind_param('ss', $parentId, $parentType);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $distributors[] = $row;
            }
            
            $stmt->close();
        }
        
        return $distributors;
    }
    
    /**
     * Calculate achievements for a specific month and parent user
     * 
     * @param string $parentType 'super_stockiest' or 'stockiest'
     * @param string $parentId   Parent's temp_id
     * @param int    $month      Month (1-12)
     * @param int    $year       Year
     * @param bool   $dryRun     If true, don't save to database
     * 
     * @return array Achievement results
     */
    public function calculateMonthlyAchievements(
        string $parentType,
        string $parentId,
        int $month,
        int $year,
        bool $dryRun = false
    ): array {
        $subordinates = $this->getSubordinateDistributors($parentType, $parentId);
        
        $achievementData = [
            'parent_type' => $parentType,
            'parent_id' => $parentId,
            'month' => $month,
            'year' => $year,
            'total_subordinates' => count($subordinates),
            'achieved_subordinates' => 0,
            'achievement_percentage' => 0.0,
            'reward_tier' => 'none',
            'points_awarded' => 0,
            'subordinate_details' => []
        ];
        
        // Calculate each subordinate's achievement
        foreach ($subordinates as $subordinate) {
            $categoryTarget = $this->getCategoryTarget(
                $subordinate['distributor_type'],
                (int)$subordinate['category_id']
            );
            
            $sales = $this->calculateMonthlySales(
                $subordinate['distributor_type'],
                $subordinate['temp_id'],
                $month,
                $year
            );
            
            $targetAchieved = $sales['total'] >= $categoryTarget;
            $achievementPercentage = $categoryTarget > 0 
                ? ($sales['total'] / $categoryTarget) * 100 
                : 0.0;
            
            if ($targetAchieved) {
                $achievementData['achieved_subordinates']++;
            }
            
            $subordinateDetail = [
                'distributor_type' => $subordinate['distributor_type'],
                'distributor_id' => $subordinate['temp_id'],
                'distributor_name' => $subordinate['name'],
                'category_id' => $subordinate['category_id'],
                'target_amount' => $categoryTarget,
                'total_sales' => $sales['total'],
                'user_invoice_sales' => $sales['user_invoice_sales'],
                'invoice_sales' => $sales['invoice_sales'],
                'target_achieved' => $targetAchieved,
                'achievement_percentage' => round($achievementPercentage, 2)
            ];
            
            $achievementData['subordinate_details'][] = $subordinateDetail;
            
            // Save individual achievement (if not dry run)
            if (!$dryRun) {
                $this->saveIndividualAchievement($parentType, $parentId, $month, $year, $subordinateDetail);
            }
        }
        
        // Calculate achievement percentage
        if ($achievementData['total_subordinates'] > 0) {
            $achievementData['achievement_percentage'] = round(
                ($achievementData['achieved_subordinates'] / $achievementData['total_subordinates']) * 100,
                2
            );
        }
        
        // Determine reward tier and points
        $achievedCount = $achievementData['achieved_subordinates'];
        $tier10Threshold = $this->getConfig('tier_10_threshold', 10);
        $tier5Threshold = $this->getConfig('tier_5_threshold', 5);
        
        if ($achievedCount >= $tier10Threshold) {
            $achievementData['reward_tier'] = 'tier_10';
            $achievementData['points_awarded'] = $this->getConfig('tier_10_points', 250);
        } elseif ($achievedCount >= $tier5Threshold) {
            $achievementData['reward_tier'] = 'tier_5';
            $achievementData['points_awarded'] = $this->getConfig('tier_5_points', 100);
        }
        
        // Save parent's reward (if not dry run and points awarded)
        if (!$dryRun && $achievementData['points_awarded'] > 0) {
            $this->saveParentReward($achievementData);
        }
        
        return $achievementData;
    }
    
    /**
     * Save individual distributor achievement
     * 
     * @param string $parentType Parent user type
     * @param string $parentId   Parent user ID
     * @param int    $month      Month
     * @param int    $year       Year
     * @param array  $detail     Achievement detail
     * 
     * @return void
     */
    private function saveIndividualAchievement(
        string $parentType,
        string $parentId,
        int $month,
        int $year,
        array $detail
    ): void {
        $stmt = $this->conn->prepare("
            INSERT INTO monthly_target_achievements (
                distributor_type, distributor_id, parent_type, parent_id,
                achievement_month, achievement_year, category_id, target_amount,
                total_sales, user_invoice_sales, invoice_sales, target_achieved,
                achievement_percentage
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                target_amount = VALUES(target_amount),
                total_sales = VALUES(total_sales),
                user_invoice_sales = VALUES(user_invoice_sales),
                invoice_sales = VALUES(invoice_sales),
                target_achieved = VALUES(target_achieved),
                achievement_percentage = VALUES(achievement_percentage),
                calculated_at = CURRENT_TIMESTAMP
        ");
        
        if (!$stmt) {
            throw new RuntimeException("Failed to prepare achievement insert: " . $this->conn->error);
        }
        
        $stmt->bind_param(
            'ssssiidddddid',
            $detail['distributor_type'],
            $detail['distributor_id'],
            $parentType,
            $parentId,
            $month,
            $year,
            $detail['category_id'],
            $detail['target_amount'],
            $detail['total_sales'],
            $detail['user_invoice_sales'],
            $detail['invoice_sales'],
            $detail['target_achieved'],
            $detail['achievement_percentage']
        );
        
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Save parent's reward entry
     * 
     * @param array $achievementData Achievement data
     * 
     * @return void
     */
    private function saveParentReward(array $achievementData): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO monthly_target_rewards (
                user_type, user_id, reward_month, reward_year, reward_date,
                total_subordinates, achieved_subordinates, achievement_percentage,
                reward_tier, points_awarded, processed_by
            ) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'backfill')
            ON DUPLICATE KEY UPDATE
                total_subordinates = VALUES(total_subordinates),
                achieved_subordinates = VALUES(achieved_subordinates),
                achievement_percentage = VALUES(achievement_percentage),
                reward_tier = VALUES(reward_tier),
                points_awarded = VALUES(points_awarded),
                reward_date = CURDATE()
        ");
        
        if (!$stmt) {
            throw new RuntimeException("Failed to prepare reward insert: " . $this->conn->error);
        }
        
        $stmt->bind_param(
            'ssiiiidsi',
            $achievementData['parent_type'],
            $achievementData['parent_id'],
            $achievementData['month'],
            $achievementData['year'],
            $achievementData['total_subordinates'],
            $achievementData['achieved_subordinates'],
            $achievementData['achievement_percentage'],
            $achievementData['reward_tier'],
            $achievementData['points_awarded']
        );
        
        $stmt->execute();
        $stmt->close();
        
        // Also add to wallet_monthly_sls_report for points integration
        $this->addRewardToWallet($achievementData);
    }
    
    /**
     * Add reward points to wallet system
     * 
     * @param array $achievementData Achievement data
     * 
     * @return void
     */
    private function addRewardToWallet(array $achievementData): void
    {
        $monthName = date('F', mktime(0, 0, 0, $achievementData['month'], 1));
        
        $stmt = $this->conn->prepare("
            INSERT INTO wallet_monthly_sls_report (
                user_type, user_id, from_date, to_date, month, year,
                total_sls_amount, target_sls_amount, target_reached,
                refer_by_usertype, refer_by_userid,
                commission_percentage, commission_amount, commission_type,
                remarks
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                0, 0, 'no',
                'system', 'monthly_target_reward',
                0, ?, 'monthly_target_reward',
                ?
            )
        ");
        
        if (!$stmt) {
            throw new RuntimeException("Failed to prepare wallet insert: " . $this->conn->error);
        }
        
        $fromDate = sprintf('%04d-%02d-01', $achievementData['year'], $achievementData['month']);
        $toDate = date('Y-m-t', strtotime($fromDate));
        $remarks = sprintf(
            'Monthly Target Achievement Reward: %d/%d subordinates achieved target',
            $achievementData['achieved_subordinates'],
            $achievementData['total_subordinates']
        );
        
        $stmt->bind_param(
            'sssssids',
            $achievementData['parent_type'],
            $achievementData['parent_id'],
            $fromDate,
            $toDate,
            $monthName,
            $achievementData['year'],
            $achievementData['points_awarded'],
            $remarks
        );
        
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Log an audit entry
     * 
     * @param string $actionType Action type
     * @param array  $details    Additional details
     * 
     * @return void
     */
    public function logAudit(string $actionType, array $details = []): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO monthly_target_audit_log (
                action_type, action_description, user_type, user_id,
                reward_month, reward_year, points_amount, execution_mode,
                records_affected, admin_user, ip_address, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new RuntimeException("Failed to prepare audit log: " . $this->conn->error);
        }
        
        // Extract values into variables (required for bind_param)
        // Important: For nullable columns, use NULL instead of empty string
        $description = $details['description'] ?? null;
        $userType = $details['user_type'] ?? null;
        $userId = $details['user_id'] ?? null;
        $month = $details['month'] ?? null;
        $year = $details['year'] ?? null;
        $points = $details['points'] ?? null;
        $mode = $details['mode'] ?? null; // NULL is valid for ENUM
        $records = $details['records'] ?? 0;
        $admin = $details['admin'] ?? null;
        $ip = $details['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $notes = $details['notes'] ?? null;
        
        $stmt->bind_param(
            'ssssiiisisss',
            $actionType,
            $description,
            $userType,
            $userId,
            $month,
            $year,
            $points,
            $mode,
            $records,
            $admin,
            $ip,
            $notes
        );
        
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Add log message
     * 
     * @param string $message Log message
     * @param string $type    Log type (info/success/error/warning)
     * 
     * @return void
     */
    public function addLog(string $message, string $type = 'info'): void
    {
        $this->logs[] = [
            'message' => $message,
            'type' => $type,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get all logs
     * 
     * @return array
     */
    public function getLogs(): array
    {
        return $this->logs;
    }
    
    /**
     * Clear all logs
     * 
     * @return void
     */
    public function clearLogs(): void
    {
        $this->logs = [];
    }
}