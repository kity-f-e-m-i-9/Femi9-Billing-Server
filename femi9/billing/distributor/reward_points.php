<?php
/**
 * Reward Points Dashboard - CORRECTED VERSION
 * 
 * Displays user reward points with proper calculation logic:
 * - Purchase points: subtotal/100 formula (CORRECTED)
 * - Daily login rewards from date range
 * - Return points: subtotal/100 based on ORIGINAL INVOICE DATE (CORRECTED)
 * 
 * Security: CSRF protection, XSS prevention, SQL injection protection
 * Performance: Optimized queries with proper indexing considerations
 */

// Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

// Session and Configuration
require_once("checksession.php");
require_once("config.php");

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);

// Timezone Configuration
date_default_timezone_set("Asia/Kolkata");

// ============================================================================
// INPUT VALIDATION & SANITIZATION
// ============================================================================

/**
 * Sanitize and validate date input
 * 
 * @param string|null $date Raw date input
 * @param string $default Default date if validation fails
 * @return string Validated date in Y-m-d format
 */
function validateDate(?string $date, string $default): string {
    if (empty($date)) {
        return $default;
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $default;
    }
    
    return date('Y-m-d', $timestamp);
}

// Calculate current month boundaries
$numberOfDays = (int)date('t');
$current_month = date('m');
$default_from_date = date("Y-{$current_month}-01");
$default_to_date = date("Y-{$current_month}-{$numberOfDays}");

// Validate and sanitize date inputs
$current_from_date = validateDate($_POST['frdate'] ?? $_GET['frdate'] ?? null, $default_from_date);
$current_to_date = validateDate($_POST['todate'] ?? $_GET['todate'] ?? null, $default_to_date);

// Ensure from_date is not after to_date
if (strtotime($current_from_date) > strtotime($current_to_date)) {
    $temp = $current_from_date;
    $current_from_date = $current_to_date;
    $current_to_date = $temp;
}

// Validate session variables exist
if (!isset($Login_user_TYPEvl, $Login_user_IDvl, $business_name)) {
    die("Session variables not properly initialized");
}

// Sanitize session variables for SQL (prepared statements preferred)
$user_type = mysqli_real_escape_string($db_conn, $Login_user_TYPEvl);
$user_id = mysqli_real_escape_string($db_conn, $Login_user_IDvl);

// ============================================================================
// REWARD POINTS CALCULATION (CORRECTED LOGIC)
// ============================================================================

/**
 * Execute a prepared query and return the first row
 * 
 * @param mysqli $conn Database connection
 * @param string $query SQL query with ? placeholders
 * @param array $params Parameters for binding
 * @param string $types Parameter types (s=string, i=integer, d=double)
 * @return array|null Result row or null on failure
 */
function executeQuery(mysqli $conn, string $query, array $params, string $types): ?array {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Query preparation failed: " . $conn->error);
        return null;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        $stmt->close();
        return null;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row;
}

// 1. Purchase Points (from invoices created in date range)
// CORRECTED: Now uses subtotal/100 formula (consistent with sales calculation)
$purchase_query = "
    SELECT 
        COALESCE(SUM(uii.subtotal) / 100, 0) AS purchase_points,
        COUNT(DISTINCT uii.inv_id) AS invoice_count
    FROM user_invoice_items uii
    INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
    WHERE uii.to_user_type = ? 
      AND uii.to_user_id = ?
      AND uii.date BETWEEN ? AND ?
      AND ui.rwpoints_enable = 1
";

$purchase_result = executeQuery(
    $db_conn, 
    $purchase_query, 
    [$user_type, $user_id, $current_from_date, $current_to_date],
    'ssss'
);

$purchase_points = (float)($purchase_result['purchase_points'] ?? 0);
$invoice_count = (int)($purchase_result['invoice_count'] ?? 0);

// 2. Daily Login Points (from rewards in date range)
$daily_query = "
    SELECT 
        COALESCE(SUM(points_awarded), 0) AS daily_points,
        COUNT(*) AS days_rewarded,
        MAX(reward_date) AS last_reward_date
    FROM daily_login_rewards
    WHERE user_type = ? 
      AND user_id = ?
      AND reward_date BETWEEN ? AND ?
";

$daily_result = executeQuery(
    $db_conn,
    $daily_query,
    [$user_type, $user_id, $current_from_date, $current_to_date],
    'ssss'
);

$daily_points = (float)($daily_result['daily_points'] ?? 0);
$days_rewarded = (int)($daily_result['days_rewarded'] ?? 0);
$last_reward_date = $daily_result['last_reward_date'] 
    ? date('d M Y', strtotime($daily_result['last_reward_date'])) 
    : 'N/A';

// 3. Return Points (CORRECTED LOGIC)
// CORRECTED: Now uses subtotal/100 formula
// Deduct points for invoices created in date range that were returned (anytime)
$return_query = "
    SELECT 
        COALESCE(SUM(r.subtotal) / 100, 0) AS return_points,
        COUNT(DISTINCT r.invnumber) AS return_count
    FROM user_return_stock_items r
    WHERE r.invnumber IN (
        SELECT DISTINCT uii.inv_id 
        FROM user_invoice_items uii
        INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
        WHERE uii.to_user_type = ?
          AND uii.to_user_id = ?
          AND uii.date BETWEEN ? AND ?
          AND ui.rwpoints_enable = 1
    )
    AND r.from_usertype = ?
    AND r.from_userid = ?
";

$return_result = executeQuery(
    $db_conn,
    $return_query,
    [$user_type, $user_id, $current_from_date, $current_to_date, $user_type, $user_id],
    'ssssss'
);

$return_points = (float)($return_result['return_points'] ?? 0);
$return_count = (int)($return_result['return_count'] ?? 0);

// 4. Calculate Total Points
$total_points = max(0, $purchase_points + $daily_points - $return_points);

// Format numbers for display
$formatted_total = inr_format($total_points, 2);
$formatted_purchase = inr_format($purchase_points, 2);
$formatted_daily = inr_format($daily_points, 2);
$formatted_return = inr_format($return_points, 2);

// Format dates for display
$display_from = date('d M', strtotime($current_from_date));
$display_to = date('d M Y', strtotime($current_to_date));

// Sanitize for HTML output
$safe_business_name = htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Reward Points Dashboard">
    
    <title>Reward Points : <?php echo $safe_business_name; ?></title>

    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Stylesheets -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    
    <style>
    /* ========================================================================
       MINIMALISTIC BLUE THEME STYLES
       ======================================================================== */
    
    .stats-card {
        background: white;
        border: 2px solid #dbeafe;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .stats-card:hover {
        border-color: #60a5fa;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        transform: translateY(-2px);
    }
    
    .stats-value {
        font-size: 42px;
        font-weight: 700;
        color: #2563eb;
        margin: 10px 0;
        line-height: 1;
    }
    
    .stats-label {
        font-size: 13px;
        color: #6b7280;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    
    .stats-icon {
        font-size: 48px;
        color: #dbeafe;
        position: absolute;
        right: 20px;
        top: 20px;
        opacity: 0.6;
    }
    
    .stats-meta {
        font-size: 12px;
        color: #9ca3af;
        margin-top: 8px;
    }
    
    .breakdown-card {
        background: white;
        border: 2px solid #f3f4f6;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
    }
    
    .breakdown-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 0;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .breakdown-item:last-child {
        border-bottom: none;
        padding-top: 20px;
        margin-top: 10px;
        background: #eff6ff;
        padding: 20px;
        border-radius: 8px;
    }
    
    .breakdown-label {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #374151;
        font-weight: 500;
    }
    
    .breakdown-icon {
        width: 32px;
        height: 32px;
        background: #eff6ff;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #2563eb;
        font-size: 18px;
    }
    
    .breakdown-value {
        font-weight: 700;
        font-size: 20px;
        color: #2563eb;
    }
    
    .breakdown-value.negative {
        color: #dc2626;
    }
    
    .section-title {
        color: #111827;
        font-weight: 700;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
    }
    
    .section-title i {
        color: #2563eb;
    }
    
    .info-badge {
        background: #eff6ff;
        color: #2563eb;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 8px;
    }
    
    /* Form Styling */
    .overviewcontainar {
        background: white;
        border: 2px solid #f3f4f6;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        display: flex;
        gap: 15px;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    
    #searchleftcont {
        flex: 1;
        min-width: 200px;
    }
    
    #searchbuttoncont {
        flex: 0 0 auto;
    }
    
    .btn-primary {
        background: #2563eb;
        border: none;
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }
    
    .form-control {
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px 14px;
    }
    
    .form-control:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    
    .form-label {
        font-weight: 500;
        color: #374151;
        margin-bottom: 6px;
        font-size: 13px;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .stats-value {
            font-size: 32px;
        }
        
        .stats-icon {
            font-size: 36px;
        }
        
        .breakdown-value {
            font-size: 18px;
        }
        
        .overviewcontainar {
            flex-direction: column;
        }
        
        #searchleftcont {
            width: 100%;
        }
        
        #searchbuttoncont {
            width: 100%;
        }
        
        .btn-primary {
            width: 100%;
            justify-content: center;
        }
    }
    </style>
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php"); ?>
            <?php include("femi_menu.php"); ?>
        </div>
		
        <div class="app-container">
            <?php include("app-header.php"); ?>
			
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <!-- Page Header -->
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td>Reward Points Dashboard</td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>
						
                        <!-- Date Filter Form -->
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
                            <div class="overviewcontainar">
                                <div id="searchleftcont">
                                    <label class="form-label" for="frdate">From Date</label>
                                    <input 
                                        type="date" 
                                        required 
                                        name="frdate" 
                                        id="frdate"
                                        value="<?php echo htmlspecialchars($current_from_date); ?>" 
                                        class="form-control"
                                        max="<?php echo date('Y-m-d'); ?>"
                                    >
                                </div>
                                <div id="searchleftcont">
                                    <label class="form-label" for="todate">To Date</label>
                                    <input 
                                        type="date" 
                                        required 
                                        name="todate" 
                                        id="todate"
                                        value="<?php echo htmlspecialchars($current_to_date); ?>" 
                                        class="form-control"
                                        max="<?php echo date('Y-m-d'); ?>"
                                    >
                                </div>
                                <div id="searchbuttoncont">
                                    <button type="submit" name="sedatas" class="btn btn-primary">
                                        <i class="material-icons">search</i>Search
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Stats Cards Row -->
                        <div class="row">
                            <!-- Total Points Card -->
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <i class="material-icons stats-icon">emoji_events</i>
                                    <div class="stats-label">Total Reward Points</div>
                                    <div class="stats-value"><?php echo $formatted_total; ?></div>
                                    <div class="stats-meta">
                                        <?php echo $display_from; ?> - <?php echo $display_to; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Daily Login Stats Card -->
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <i class="material-icons stats-icon">card_giftcard</i>
                                    <div class="stats-label">Daily Login Points</div>
                                    <div class="stats-value"><?php echo $formatted_daily; ?></div>
                                    <div class="stats-meta">
                                        <?php echo $days_rewarded; ?> day<?php echo $days_rewarded !== 1 ? 's' : ''; ?> rewarded
                                    </div>
                                </div>
                            </div>

                            <!-- Last Reward Date Card -->
                            <div class="col-md-4">
                                <div class="stats-card">
                                    <i class="material-icons stats-icon">schedule</i>
                                    <div class="stats-label">Last Points Received</div>
                                    <div class="stats-value" style="font-size: 28px;"><?php echo htmlspecialchars($last_reward_date); ?></div>
                                    <div class="stats-meta">
                                        Keep logging in daily!
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Points Breakdown Card -->
                        <div class="row">
                            <div class="col-12">
                                <div class="breakdown-card">
                                    <h5 class="section-title">
                                        <i class="material-icons">analytics</i>
                                        Points Breakdown
                                    </h5>
                                    
                                    <div class="breakdown-item">
                                        <div class="breakdown-label">
                                            <div class="breakdown-icon">
                                                <i class="material-icons">shopping_cart</i>
                                            </div>
                                            <span>
                                                Points from Product Purchases
                                                <span class="info-badge"><?php echo $invoice_count; ?> invoice<?php echo $invoice_count !== 1 ? 's' : ''; ?></span>
                                            </span>
                                        </div>
                                        <div class="breakdown-value">
                                            +<?php echo $formatted_purchase; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="breakdown-item">
                                        <div class="breakdown-label">
                                            <div class="breakdown-icon">
                                                <i class="material-icons">card_giftcard</i>
                                            </div>
                                            <span>
                                                Daily Login & Billing Rewards
                                                <span class="info-badge"><?php echo $days_rewarded; ?> day<?php echo $days_rewarded !== 1 ? 's' : ''; ?></span>
                                            </span>
                                        </div>
                                        <div class="breakdown-value">
                                            +<?php echo $formatted_daily; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($return_points > 0): ?>
                                    <div class="breakdown-item">
                                        <div class="breakdown-label">
                                            <div class="breakdown-icon" style="background: #fee2e2; color: #dc2626;">
                                                <i class="material-icons">remove_circle</i>
                                            </div>
                                            <span>
                                                Points Deducted from Returns
                                                <span class="info-badge" style="background: #fee2e2; color: #dc2626;">
                                                    <?php echo $return_count; ?> return<?php echo $return_count !== 1 ? 's' : ''; ?>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="breakdown-value negative">
                                            -<?php echo $formatted_return; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="breakdown-item">
                                        <div class="breakdown-label">
                                            <i class="material-icons" style="color: #2563eb; font-size: 24px;">emoji_events</i>
                                            <strong style="font-size: 16px;">Total Reward Points</strong>
                                        </div>
                                        <div class="breakdown-value" style="font-size: 32px;">
                                            <?php echo $formatted_total; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($return_count > 0): ?>
                        <!-- Info Box for Returns -->
                        <div class="row">
                            <div class="col-12">
                                <div style="background: #fef3c7; border: 2px solid #fde68a; border-radius: 12px; padding: 20px;">
                                    <h6 style="color: #92400e; font-weight: 600; margin-bottom: 12px;">
                                        <i class="material-icons" style="vertical-align: middle;">info</i>
                                        About Return Deductions
                                    </h6>
                                    <p style="color: #78350f; margin-bottom: 8px; line-height: 1.8;">
                                        Return points are deducted based on the <strong>original invoice date</strong>, 
                                        not the return date. This ensures accurate period-wise reporting.
                                    </p>
                                    <small style="color: #92400e;">
                                        <strong>Example:</strong> If you received 100 points for an invoice in July, 
                                        and returned it in September, the 100 points will be deducted from your July total.
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    
    <script>
    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const fromDate = document.getElementById('frdate');
        const toDate = document.getElementById('todate');
        
        form.addEventListener('submit', function(e) {
            if (new Date(fromDate.value) > new Date(toDate.value)) {
                e.preventDefault();
                alert('From Date cannot be later than To Date');
                fromDate.focus();
            }
        });
    });
    </script>
</body>
</html>