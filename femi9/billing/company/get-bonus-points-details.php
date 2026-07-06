<?php
/**
 * Get Bonus Points Details - Modal View
 * Femi9 Billing Application
 * 
 * Displays detailed breakdown of bonus points calculation
 * 
 * @author Femi9 Development Team
 * @version 1.0
 * @date 2026-02-11
 */

declare(strict_types=1);

require_once("checksession.php");
require_once("config.php");

// Security check
$logged_user_id = $_SESSION['LOGIN_USER_ID'] ?? '';
$logged_user_type = $_SESSION['LOGIN_USER_TYPE'] ?? '';

if (empty($logged_user_id) || empty($logged_user_type)) {
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit;
}

// CSRF token validation
$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || $csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
    echo '<div class="alert alert-danger">Invalid security token</div>';
    exit;
}

// Set charset
if ($db_conn) {
    mysqli_set_charset($db_conn, 'utf8mb4');
}

// ============================================================================
// GET BONUS POINTS DETAILS
// ============================================================================

$id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$id) {
    echo '<div class="alert alert-danger">Invalid bonus points record ID</div>';
    exit;
}

// Fetch bonus points record
$query = "SELECT 
    bph.*
FROM bonus_points_history bph
WHERE bph.id = ?
  AND bph.rolled_back_at IS NULL";

$stmt = $db_conn->prepare($query);

if (!$stmt) {
    error_log("Prepare failed in get-bonus-points-details.php: " . $db_conn->error);
    echo '<div class="alert alert-danger">Database error</div>';
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();
$stmt->close();

if (!$record) {
    echo '<div class="alert alert-warning">Bonus points record not found</div>';
    exit;
}

// Format helper function
function formatCurrency(float $amount): string {
    return '₹' . inr_format($amount, 2);
}

function formatUserType(string $type): string {
    return ucwords(str_replace('_', ' ', $type));
}

function formatDate(string $date): string {
    return date('d M Y, h:i A', strtotime($date));
}

// Extract data
$user_name = htmlspecialchars($record['user_name'], ENT_QUOTES, 'UTF-8');
$user_type = formatUserType($record['user_type']);
$month_year = date('F Y', strtotime($record['month_year'] . '-01'));
$category_name = htmlspecialchars($record['category_name'], ENT_QUOTES, 'UTF-8');
$monthly_target = formatCurrency((float)$record['monthly_target']);
$total_paid = formatCurrency((float)$record['total_advance_paid']);
$eligibility = $record['eligibility_status'] === 'eligible' ? 'Eligible ✅' : 'Not Eligible ❌';
$bonus_points = inr_format((float)$record['bonus_points_awarded'], 2);
$bonus_calculation = htmlspecialchars($record['bonus_calculation'], ENT_QUOTES, 'UTF-8');
$executed_by = htmlspecialchars($record['executed_by_user_name'], ENT_QUOTES, 'UTF-8');
$executed_by_type = formatUserType($record['executed_by_user_type']);
$executed_at = formatDate($record['executed_at']);
$execution_id = htmlspecialchars($record['execution_id'], ENT_QUOTES, 'UTF-8');

// Week data
$weeks = [
    1 => [
        'amount' => (float)$record['week1_amount'],
        'cumulative' => (float)$record['week1_cumulative'],
        'required' => (float)$record['week1_required'],
        'status' => $record['week1_status'],
        'label' => 'Week 1 (Day 1-7)',
        'target_pct' => 25
    ],
    2 => [
        'amount' => (float)$record['week2_amount'],
        'cumulative' => (float)$record['week2_cumulative'],
        'required' => (float)$record['week2_required'],
        'status' => $record['week2_status'],
        'label' => 'Week 2 (Day 8-14)',
        'target_pct' => 50
    ],
    3 => [
        'amount' => (float)$record['week3_amount'],
        'cumulative' => (float)$record['week3_cumulative'],
        'required' => (float)$record['week3_required'],
        'status' => $record['week3_status'],
        'label' => 'Week 3 (Day 15-21)',
        'target_pct' => 75
    ],
    4 => [
        'amount' => (float)$record['week4_amount'],
        'cumulative' => (float)$record['week4_cumulative'],
        'required' => (float)$record['week4_required'],
        'status' => $record['week4_status'],
        'label' => 'Week 4 (Day 22-End)',
        'target_pct' => $record['user_type'] === 'super_stockiest' ? 90 : 100
    ]
];

?>

<style>
.detail-card {
    background: #f8fafc;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid #667eea;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e5e7eb;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: #4b5563;
    font-size: 14px;
}

.detail-value {
    font-weight: 700;
    color: #111827;
    font-size: 15px;
}

.week-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    border: 2px solid #e5e7eb;
    transition: all 0.3s ease;
}

.week-card.pass {
    border-color: #10b981;
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
}

.week-card.fail {
    border-color: #ef4444;
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
}

.week-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.week-title {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
}

.week-status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
}

.week-status-badge.pass {
    background: #10b981;
    color: white;
}

.week-status-badge.fail {
    background: #ef4444;
    color: white;
}

.week-metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.metric-box {
    background: white;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid #e5e7eb;
}

.metric-label {
    font-size: 12px;
    color: #6b7280;
    font-weight: 500;
    margin-bottom: 4px;
}

.metric-value {
    font-size: 16px;
    font-weight: 700;
    color: #111827;
}

.eligibility-badge {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 24px;
    font-size: 16px;
    font-weight: 700;
}

.eligibility-badge.eligible {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.eligibility-badge.not-eligible {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.bonus-result {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 2px solid #f59e0b;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    margin-top: 24px;
}

.bonus-amount {
    font-size: 48px;
    font-weight: 800;
    color: #f59e0b;
    margin: 12px 0;
}

.calculation-formula {
    background: white;
    padding: 12px 20px;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    color: #374151;
    margin-top: 12px;
}
</style>

<div class="container-fluid">
    
    <!-- User Information -->
    <div class="detail-card">
        <h5 style="color: #667eea; font-weight: 700; margin-bottom: 20px;">📋 User Information</h5>
        <div class="detail-row">
            <span class="detail-label">User Name</span>
            <span class="detail-value"><?php echo $user_name; ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">User Type</span>
            <span class="detail-value"><?php echo $user_type; ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Category</span>
            <span class="detail-value"><?php echo $category_name; ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Month</span>
            <span class="detail-value"><?php echo $month_year; ?></span>
        </div>
    </div>

    <!-- Target & Achievement -->
    <div class="detail-card" style="border-left-color: #10b981;">
        <h5 style="color: #10b981; font-weight: 700; margin-bottom: 20px;">🎯 Target & Achievement</h5>
        <div class="detail-row">
            <span class="detail-label">Monthly Target</span>
            <span class="detail-value" style="color: #667eea;"><?php echo $monthly_target; ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Total Advance Paid</span>
            <span class="detail-value" style="color: #10b981;"><?php echo $total_paid; ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Achievement Percentage</span>
            <span class="detail-value" style="color: #f59e0b;">
                <?php 
                $achievement_pct = ((float)$record['total_advance_paid'] / (float)$record['monthly_target']) * 100;
                echo inr_format($achievement_pct, 2) . '%';
                ?>
            </span>
        </div>
    </div>

    <!-- Weekly Breakdown -->
    <h5 style="color: #667eea; font-weight: 700; margin: 24px 0 16px 0;">📅 Weekly Breakdown</h5>
    
    <?php foreach ($weeks as $week_num => $week): ?>
        <div class="week-card <?php echo $week['status']; ?>">
            <div class="week-header">
                <div class="week-title">
                    <?php echo $week['label']; ?>
                    <small style="color: #6b7280; font-weight: 500; font-size: 14px;">
                        (Target: <?php echo $week['target_pct']; ?>% of monthly)
                    </small>
                </div>
                <span class="week-status-badge <?php echo $week['status']; ?>">
                    <?php echo $week['status'] === 'pass' ? '✅ PASS' : '❌ FAIL'; ?>
                </span>
            </div>
            
            <div class="week-metrics">
                <div class="metric-box">
                    <div class="metric-label">Payment This Week</div>
                    <div class="metric-value"><?php echo formatCurrency($week['amount']); ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Cumulative Payment</div>
                    <div class="metric-value" style="color: #10b981;"><?php echo formatCurrency($week['cumulative']); ?></div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Required Amount</div>
                    <div class="metric-value" style="color: #667eea;"><?php echo formatCurrency($week['required']); ?></div>
                </div>
            </div>
            
            <?php if ($week['status'] === 'fail'): ?>
                <div style="margin-top: 12px; padding: 8px 12px; background: rgba(239, 68, 68, 0.1); border-radius: 6px;">
                    <small style="color: #dc2626; font-weight: 600;">
                        ⚠️ Shortfall: <?php echo formatCurrency($week['required'] - $week['cumulative']); ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- Eligibility Status -->
    <div style="text-align: center; margin: 32px 0 24px 0;">
        <h5 style="color: #4b5563; font-weight: 600; margin-bottom: 16px;">Eligibility Status</h5>
        <span class="eligibility-badge <?php echo $record['eligibility_status']; ?>">
            <?php echo $eligibility; ?>
        </span>
        <?php if ($record['eligibility_status'] === 'not_eligible'): ?>
            <p style="color: #dc2626; margin-top: 12px; font-weight: 500;">
                User failed to meet weekly target requirements
            </p>
        <?php endif; ?>
    </div>

    <!-- Bonus Calculation Result -->
    <div class="bonus-result">
        <h5 style="color: #92400e; font-weight: 700; margin: 0 0 8px 0;">🎁 Bonus Points Awarded</h5>
        <div class="bonus-amount"><?php echo $bonus_points; ?></div>
        <p style="color: #6b7280; margin: 0; font-weight: 500;">Reward Points</p>
        
        <?php if (!empty($bonus_calculation)): ?>
            <div class="calculation-formula">
                <strong>Calculation:</strong> <?php echo $bonus_calculation; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Execution Information -->
    <div class="detail-card" style="border-left-color: #6366f1; margin-top: 24px;">
        <h5 style="color: #6366f1; font-weight: 700; margin-bottom: 20px;">⚙️ Execution Information</h5>
        <div class="detail-row">
            <span class="detail-label">Execution ID</span>
            <span class="detail-value" style="font-family: monospace; font-size: 13px;"><?php echo $execution_id; ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Executed By</span>
            <span class="detail-value"><?php echo $executed_by; ?> (<?php echo $executed_by_type; ?>)</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Executed At</span>
            <span class="detail-value"><?php echo $executed_at; ?></span>
        </div>
    </div>

</div>

<?php
if (isset($db_conn) && $db_conn) {
    $db_conn->close();
}
?>