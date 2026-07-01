<?php
/**
 * Monthly Target Rewards - Real-time Processor
 * 
 * Automatically calculates and awards monthly target rewards when distributors
 * achieve their targets. Triggers after invoice creation.
 * 
 * @category Rewards
 * @package  MonthlyTargetRewards
 * @author   Femi9 Development Team
 * @created  2025-11-29
 * @location /includes/monthly_target_rewards_processor.php
 */

declare(strict_types=1);

class MonthlyTargetRewardsProcessor
{
    private mysqli $conn;
    private MonthlyTargetCalculator $calculator;
    private bool $enabled = true;
    
    /**
     * Constructor
     * 
     * @param mysqli $connection Database connection
     */
    public function __construct(mysqli $connection)
    {
        $this->conn = $connection;
        $this->calculator = new MonthlyTargetCalculator($connection);
        
        // Check if real-time processing is enabled
        $this->enabled = (bool)$this->calculator->getConfig('realtime_enabled', false);
    }
    
    /**
     * Process rewards after invoice creation
     * Called from invoice creation scripts
     * 
     * @param string $fromUserType User type who created the invoice
     * @param string $fromUserId   User ID who created the invoice
     * @param string $invoiceDate  Invoice date (Y-m-d format)
     * 
     * @return bool True if processed, false if skipped
     */
    public function processAfterInvoice(
        string $fromUserType,
        string $fromUserId,
        string $invoiceDate
    ): bool {
        // Skip if not enabled
        if (!$this->enabled) {
            return false;
        }
        
        // Only process for distributors and super_distributors
        if (!in_array($fromUserType, ['distributor', 'super_distributor'], true)) {
            return false;
        }
        
        // Check if date is December 2025 or later
        $realtimeStartDate = $this->calculator->getConfig('realtime_start_date', '2025-12-01');
        if ($invoiceDate < $realtimeStartDate) {
            return false;
        }
        
        try {
            // Get invoice month and year
            $timestamp = strtotime($invoiceDate);
            $month = (int)date('n', $timestamp);
            $year = (int)date('Y', $timestamp);
            
            // Get distributor's parent
            $parent = $this->getParentUser($fromUserType, $fromUserId);
            if (!$parent) {
                return false; // No parent found
            }
            
            // Check if this distributor has now achieved their target
            $hasAchievedTarget = $this->checkIfDistributorAchievedTarget(
                $fromUserType,
                $fromUserId,
                $month,
                $year
            );
            
            if (!$hasAchievedTarget) {
                return false; // Distributor hasn't achieved target yet
            }
            
            // Check if we've already recorded this achievement
            if ($this->isAchievementAlreadyRecorded($fromUserType, $fromUserId, $month, $year)) {
                return false; // Already recorded
            }
            
            // Save individual achievement
            $this->saveDistributorAchievement($fromUserType, $fromUserId, $month, $year, $parent);
            
            // Check if parent now qualifies for a reward
            $this->checkAndAwardParentReward($parent['user_type'], $parent['user_id'], $month, $year);
            
            return true;
            
        } catch (Exception $e) {
            // Log error but don't break invoice creation
            error_log("Monthly Target Rewards Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get parent user of a distributor
     * 
     * @param string $distributorType 'distributor' or 'super_distributor'
     * @param string $distributorId   Distributor's temp_id
     * 
     * @return array|null Parent info or null if not found
     */
    private function getParentUser(string $distributorType, string $distributorId): ?array
    {
        $table = $distributorType;
        
        $stmt = $this->conn->prepare("
            SELECT stockiest_id 
            FROM `{$table}` 
            WHERE temp_id = ?
        ");
        
        if (!$stmt) {
            return null;
        }
        
        $stmt->bind_param('s', $distributorId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stockiestId = $row['stockiest_id'];
            $stmt->close();
            
            // Determine parent type (super_stockiest or stockiest)
            $parentType = $this->getStockiestType($stockiestId);
            
            if ($parentType) {
                return [
                    'user_type' => $parentType,
                    'user_id' => $stockiestId
                ];
            }
        }
        
        $stmt->close();
        return null;
    }
    
    /**
     * Determine if stockiest_id is super_stockiest or stockiest
     * 
     * @param string $stockiestId Stockiest ID
     * 
     * @return string|null 'super_stockiest' or 'stockiest' or null
     */
    private function getStockiestType(string $stockiestId): ?string
    {
        // Check super_stockiest table
        $stmt = $this->conn->prepare("SELECT temp_id FROM super_stockiest WHERE temp_id = ?");
        if ($stmt) {
            $stmt->bind_param('s', $stockiestId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $stmt->close();
                return 'super_stockiest';
            }
            $stmt->close();
        }
        
        // Check stockiest table
        $stmt = $this->conn->prepare("SELECT temp_id FROM stockiest WHERE temp_id = ?");
        if ($stmt) {
            $stmt->bind_param('s', $stockiestId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $stmt->close();
                return 'stockiest';
            }
            $stmt->close();
        }
        
        return null;
    }
    
    /**
     * Check if distributor has achieved their monthly target
     * 
     * @param string $distributorType Distributor type
     * @param string $distributorId   Distributor ID
     * @param int    $month          Month
     * @param int    $year           Year
     * 
     * @return bool True if achieved
     */
    private function checkIfDistributorAchievedTarget(
        string $distributorType,
        string $distributorId,
        int $month,
        int $year
    ): bool {
        // Get category ID
        $table = $distributorType;
        $stmt = $this->conn->prepare("SELECT category_id FROM `{$table}` WHERE temp_id = ?");
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('s', $distributorId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$row = $result->fetch_assoc()) {
            $stmt->close();
            return false;
        }
        
        $categoryId = (int)$row['category_id'];
        $stmt->close();
        
        // Get target amount
        $categoryTable = $distributorType === 'distributor' ? 'distributor_category' : 'super_distributor_category';
        $stmt = $this->conn->prepare("SELECT amount FROM `{$categoryTable}` WHERE id = ?");
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('i', $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$row = $result->fetch_assoc()) {
            $stmt->close();
            return false;
        }
        
        $targetAmount = (float)$row['amount'];
        $stmt->close();
        
        // Calculate total sales
        $totalSales = 0.0;
        
        // From user_invoice
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(CAST(total AS DECIMAL(10,2))), 0) AS total_sales
            FROM user_invoice
            WHERE from_user_type = ?
            AND from_user_id = ?
            AND MONTH(date) = ?
            AND YEAR(date) = ?
        ");
        
        if ($stmt) {
            $stmt->bind_param('ssii', $distributorType, $distributorId, $month, $year);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $totalSales += (float)$row['total_sales'];
            }
            $stmt->close();
        }
        
        // From invoice
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(CAST(total AS DECIMAL(10,2))), 0) AS total_sales
            FROM invoice
            WHERE user_type = ?
            AND user_id = ?
            AND MONTH(date) = ?
            AND YEAR(date) = ?
        ");
        
        if ($stmt) {
            $stmt->bind_param('ssii', $distributorType, $distributorId, $month, $year);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $totalSales += (float)$row['total_sales'];
            }
            $stmt->close();
        }
        
        // Check if achieved
        return $totalSales >= $targetAmount;
    }
    
    /**
     * Check if achievement is already recorded
     * 
     * @param string $distributorType Distributor type
     * @param string $distributorId   Distributor ID
     * @param int    $month          Month
     * @param int    $year           Year
     * 
     * @return bool True if already recorded
     */
    private function isAchievementAlreadyRecorded(
        string $distributorType,
        string $distributorId,
        int $month,
        int $year
    ): bool {
        $stmt = $this->conn->prepare("
            SELECT id 
            FROM monthly_target_achievements
            WHERE distributor_type = ?
            AND distributor_id = ?
            AND achievement_month = ?
            AND achievement_year = ?
            AND target_achieved = 1
        ");
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param('ssii', $distributorType, $distributorId, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        
        return $exists;
    }
    
    /**
     * Save distributor achievement
     * 
     * @param string $distributorType Distributor type
     * @param string $distributorId   Distributor ID
     * @param int    $month          Month
     * @param int    $year           Year
     * @param array  $parent         Parent info
     * 
     * @return void
     */
    private function saveDistributorAchievement(
        string $distributorType,
        string $distributorId,
        int $month,
        int $year,
        array $parent
    ): void {
        // Use calculator to get full achievement data
        $achievement = $this->calculator->calculateMonthlyAchievements(
            $parent['user_type'],
            $parent['user_id'],
            $month,
            $year,
            true // Dry run to get data
        );
        
        // Find this specific distributor's data
        foreach ($achievement['subordinate_details'] as $detail) {
            if ($detail['distributor_id'] === $distributorId && $detail['target_achieved']) {
                // Save this achievement
                $stmt = $this->conn->prepare("
                    INSERT INTO monthly_target_achievements (
                        distributor_type, distributor_id, parent_type, parent_id,
                        achievement_month, achievement_year, category_id, target_amount,
                        total_sales, user_invoice_sales, invoice_sales, target_achieved,
                        achievement_percentage
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
                    ON DUPLICATE KEY UPDATE
                        target_achieved = 1,
                        calculated_at = CURRENT_TIMESTAMP
                ");
                
                if ($stmt) {
                    $stmt->bind_param(
                        'ssssiiddddd',
                        $detail['distributor_type'],
                        $detail['distributor_id'],
                        $parent['user_type'],
                        $parent['user_id'],
                        $month,
                        $year,
                        $detail['category_id'],
                        $detail['target_amount'],
                        $detail['total_sales'],
                        $detail['user_invoice_sales'],
                        $detail['invoice_sales'],
                        $detail['achievement_percentage']
                    );
                    $stmt->execute();
                    $stmt->close();
                }
                break;
            }
        }
    }
    
    /**
     * Check if parent qualifies for reward and award if eligible
     * 
     * @param string $parentType Parent user type
     * @param string $parentId   Parent user ID
     * @param int    $month      Month
     * @param int    $year       Year
     * 
     * @return void
     */
    private function checkAndAwardParentReward(
        string $parentType,
        string $parentId,
        int $month,
        int $year
    ): void {
        // Count how many subordinates have achieved this month
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) AS achieved_count
            FROM monthly_target_achievements
            WHERE parent_type = ?
            AND parent_id = ?
            AND achievement_month = ?
            AND achievement_year = ?
            AND target_achieved = 1
        ");
        
        if (!$stmt) {
            return;
        }
        
        $stmt->bind_param('ssii', $parentType, $parentId, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$row = $result->fetch_assoc()) {
            $stmt->close();
            return;
        }
        
        $achievedCount = (int)$row['achieved_count'];
        $stmt->close();
        
        // Determine if reward should be granted or updated
        $tier10Threshold = $this->calculator->getConfig('tier_10_threshold', 10);
        $tier5Threshold = $this->calculator->getConfig('tier_5_threshold', 5);
        
        $currentTier = 'none';
        $currentPoints = 0;
        
        if ($achievedCount >= $tier10Threshold) {
            $currentTier = 'tier_10';
            $currentPoints = $this->calculator->getConfig('tier_10_points', 250);
        } elseif ($achievedCount >= $tier5Threshold) {
            $currentTier = 'tier_5';
            $currentPoints = $this->calculator->getConfig('tier_5_points', 100);
        }
        
        // Only process if eligible for a reward
        if ($currentTier === 'none') {
            return;
        }
        
        // Check existing reward
        $existingReward = $this->getExistingReward($parentType, $parentId, $month, $year);
        
        if ($existingReward) {
            // Update if tier changed (e.g., from tier_5 to tier_10)
            if ($existingReward['reward_tier'] !== $currentTier) {
                $this->updateParentReward($parentType, $parentId, $month, $year, $achievedCount, $currentTier, $currentPoints);
            }
        } else {
            // Create new reward
            $this->createParentReward($parentType, $parentId, $month, $year, $achievedCount, $currentTier, $currentPoints);
        }
    }
    
    /**
     * Get existing reward for parent
     * 
     * @param string $parentType Parent type
     * @param string $parentId   Parent ID
     * @param int    $month      Month
     * @param int    $year       Year
     * 
     * @return array|null Existing reward or null
     */
    private function getExistingReward(string $parentType, string $parentId, int $month, int $year): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT reward_tier, points_awarded
            FROM monthly_target_rewards
            WHERE user_type = ?
            AND user_id = ?
            AND reward_month = ?
            AND reward_year = ?
        ");
        
        if (!$stmt) {
            return null;
        }
        
        $stmt->bind_param('ssii', $parentType, $parentId, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reward = $result->fetch_assoc();
        $stmt->close();
        
        return $reward ?: null;
    }
    
    /**
     * Create new parent reward
     * 
     * @param string $parentType     Parent type
     * @param string $parentId       Parent ID
     * @param int    $month          Month
     * @param int    $year           Year
     * @param int    $achievedCount  Number who achieved
     * @param string $tier           Reward tier
     * @param int    $points         Points awarded
     * 
     * @return void
     */
    private function createParentReward(
        string $parentType,
        string $parentId,
        int $month,
        int $year,
        int $achievedCount,
        string $tier,
        int $points
    ): void {
        // Calculate full achievement data
        $achievement = $this->calculator->calculateMonthlyAchievements(
            $parentType,
            $parentId,
            $month,
            $year,
            false // Execute mode - save to database
        );
    }
    
    /**
     * Update existing parent reward
     * 
     * @param string $parentType     Parent type
     * @param string $parentId       Parent ID
     * @param int    $month          Month
     * @param int    $year           Year
     * @param int    $achievedCount  Number who achieved
     * @param string $tier           New reward tier
     * @param int    $points         New points amount
     * 
     * @return void
     */
    private function updateParentReward(
        string $parentType,
        string $parentId,
        int $month,
        int $year,
        int $achievedCount,
        string $tier,
        int $points
    ): void {
        // Recalculate to update all data
        $this->calculator->calculateMonthlyAchievements(
            $parentType,
            $parentId,
            $month,
            $year,
            false // Execute mode - save to database
        );
    }
}
