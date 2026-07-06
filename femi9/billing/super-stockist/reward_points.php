<?php
/**
 * Reward Points Dashboard
 *
 * Displays user reward points with proper calculation logic:
 * - Purchase points:          subtotal / 100 (from invoices received)
 * - Daily login rewards:      5 pts/day for first invoice of the day
 * - Return deductions:        subtotal / 100, attributed to ORIGINAL invoice date
 * - Advance payment points:   (advance_amount / 100) * 10%
 *                             Sourced from bonus_points_history for executed bonus runs,
 *                             PLUS a live preview from advance_payments for the period.
 *
 * Security: CSRF protection, XSS prevention, SQL injection protection (prepared statements)
 * Performance: Optimised queries, output buffering to prevent whitespace before JSON
 */

ob_start(); // Prevent whitespace before any potential JSON response

// ============================================================================
// SECURITY HEADERS
// ============================================================================
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ============================================================================
// BOOTSTRAP
// ============================================================================
require_once("checksession.php");
require_once("config.php");

error_reporting(E_ALL);
ini_set('display_errors', 0);   // Never display errors in production
ini_set('log_errors', 1);

date_default_timezone_set("Asia/Kolkata");

// ============================================================================
// SESSION GUARD
// ============================================================================
if (!isset($Login_user_TYPEvl, $Login_user_IDvl, $business_name)) {
    error_log("Reward Points Dashboard: required session variables missing.");
    die("Session variables not properly initialised. Please log in again.");
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Validate and normalise a date string to Y-m-d.
 * Returns $default if the input is empty or unparseable.
 */
function validateDate(?string $date, string $default): string
{
    if (empty($date)) {
        return $default;
    }
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}

/**
 * Execute a prepared SELECT and return the first row as an associative array.
 * Returns null on preparation/execution failure.
 *
 * @param mysqli  $conn   Active database connection
 * @param string  $query  SQL with ? placeholders
 * @param array   $params Ordered parameter values
 * @param string  $types  MySQLi type string (s/i/d/b)
 */
function executeQueryRow(mysqli $conn, string $query, array $params, string $types): ?array
{
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("DB prepare failed: " . $conn->error . " | Query: " . substr($query, 0, 200));
        return null;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        error_log("DB execute failed: " . $stmt->error);
        $stmt->close();
        return null;
    }

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?? [];
}

// ============================================================================
// INPUT VALIDATION
// ============================================================================
$daysInMonth       = (int) date('t');
$currentMonth      = date('m');
$defaultFromDate   = date("Y-{$currentMonth}-01");
$defaultToDate     = date("Y-{$currentMonth}-{$daysInMonth}");

$currentFromDate = validateDate(
    $_POST['frdate'] ?? $_GET['frdate'] ?? null,
    $defaultFromDate
);
$currentToDate = validateDate(
    $_POST['todate'] ?? $_GET['todate'] ?? null,
    $defaultToDate
);

// Ensure chronological order
if (strtotime($currentFromDate) > strtotime($currentToDate)) {
    [$currentFromDate, $currentToDate] = [$currentToDate, $currentFromDate];
}

// Safe user identifiers for queries (prepared statements are the primary defence;
// escape is a belt-and-suspenders measure for any non-parameterised usage)
$userType = mysqli_real_escape_string($db_conn, $Login_user_TYPEvl);
$userId   = mysqli_real_escape_string($db_conn, $Login_user_IDvl);

// ============================================================================
// 1. PURCHASE POINTS
//    Source:  user_invoice_items JOIN user_invoice
//    Formula: SUM(subtotal) / 100
// ============================================================================
$purchaseQuery = "
    SELECT
        COALESCE(SUM(uii.subtotal) / 100, 0) AS purchase_points,
        COUNT(DISTINCT uii.inv_id)            AS invoice_count
    FROM user_invoice_items uii
    INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
    WHERE uii.to_user_type = ?
      AND uii.to_user_id   = ?
      AND uii.date         BETWEEN ? AND ?
      AND ui.rwpoints_enable = 1
";

$purchaseResult  = executeQueryRow($db_conn, $purchaseQuery,
    [$userType, $userId, $currentFromDate, $currentToDate], 'ssss');
$purchasePoints  = (float) ($purchaseResult['purchase_points'] ?? 0);
$invoiceCount    = (int)   ($purchaseResult['invoice_count']   ?? 0);

// ============================================================================
// 2. DAILY LOGIN POINTS
//    Source:  daily_login_rewards
// ============================================================================
$dailyQuery = "
    SELECT
        COALESCE(SUM(points_awarded), 0) AS daily_points,
        COUNT(*)                         AS days_rewarded,
        MAX(reward_date)                 AS last_reward_date
    FROM daily_login_rewards
    WHERE user_type  = ?
      AND user_id    = ?
      AND reward_date BETWEEN ? AND ?
";

$dailyResult    = executeQueryRow($db_conn, $dailyQuery,
    [$userType, $userId, $currentFromDate, $currentToDate], 'ssss');
$dailyPoints    = (float) ($dailyResult['daily_points']   ?? 0);
$daysRewarded   = (int)   ($dailyResult['days_rewarded']  ?? 0);
$lastRewardDate = !empty($dailyResult['last_reward_date'])
    ? date('d M Y', strtotime($dailyResult['last_reward_date']))
    : 'N/A';

// ============================================================================
// 3. RETURN DEDUCTIONS
//    Logic: Deduct points for returns on invoices whose ORIGINAL DATE falls
//           within the selected range (not the return date).
//    Formula: SUM(subtotal) / 100
// ============================================================================
$returnQuery = "
    SELECT
        COALESCE(SUM(r.subtotal) / 100, 0) AS return_points,
        COUNT(DISTINCT r.invnumber)         AS return_count
    FROM user_return_stock_items r
    WHERE r.invnumber IN (
        SELECT DISTINCT uii.inv_id
        FROM user_invoice_items uii
        INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
        WHERE uii.to_user_type     = ?
          AND uii.to_user_id       = ?
          AND uii.date             BETWEEN ? AND ?
          AND ui.rwpoints_enable   = 1
    )
    AND r.from_usertype = ?
    AND r.from_userid   = ?
";

$returnResult  = executeQueryRow($db_conn, $returnQuery,
    [$userType, $userId, $currentFromDate, $currentToDate, $userType, $userId], 'ssssss');
$returnPoints  = (float) ($returnResult['return_points'] ?? 0);
$returnCount   = (int)   ($returnResult['return_count']  ?? 0);

// ============================================================================
// 4. ADVANCE PAYMENT BONUS POINTS
//    Source:  bonus_points_history (executed bonus runs only)
//    Formula: SUM(bonus_points_awarded) for eligible, non-rolled-back runs
//             whose month_year overlaps the selected date range.
//    Only applicable to Super Stockist and Stockist user types.
// ============================================================================
$totalAdvanceBonusPoints = 0.0;
$isAdvanceEligibleType   = in_array(strtolower($userType), ['super_stockiest', 'stockiest'], true);

if ($isAdvanceEligibleType) {
    $bonusAwardedQuery = "
        SELECT COALESCE(SUM(bph.bonus_points_awarded), 0) AS total_advance_bonus
        FROM bonus_points_history bph
        WHERE bph.user_id            = ?
          AND bph.user_type          = ?
          AND bph.eligibility_status = 'eligible'
          AND bph.rolled_back_at     IS NULL
          AND STR_TO_DATE(CONCAT(bph.month_year, '-01'), '%Y-%m-%d')
              BETWEEN DATE_FORMAT(?, '%Y-%m-01')
                  AND LAST_DAY(?)
    ";

    $bonusAwardedResult      = executeQueryRow($db_conn, $bonusAwardedQuery,
        [$userId, $userType, $currentFromDate, $currentToDate], 'ssss');
    $totalAdvanceBonusPoints = (float) ($bonusAwardedResult['total_advance_bonus'] ?? 0);
}

// ============================================================================
// 5. GRAND TOTAL
//    Purchase + Daily + Advance Bonus - Returns
// ============================================================================
$totalPoints = max(0, $purchasePoints + $dailyPoints + $totalAdvanceBonusPoints - $returnPoints);

// ============================================================================
// FORMAT FOR DISPLAY
// ============================================================================
$fmt = static fn(float $v): string => inr_format($v, 2);

$formattedTotal         = $fmt($totalPoints);
$formattedPurchase      = $fmt($purchasePoints);
$formattedDaily         = $fmt($dailyPoints);
$formattedReturn        = $fmt($returnPoints);
$formattedTotalAdvance  = $fmt($totalAdvanceBonusPoints);

$displayFrom       = date('d M',   strtotime($currentFromDate));
$displayTo         = date('d M Y', strtotime($currentToDate));
$safeBusinessName  = htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Reward Points Dashboard">
    <title>Reward Points : <?php echo $safeBusinessName; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png">

    <style>
    /* =========================================================
       MINIMALISTIC BLUE THEME
       ========================================================= */
    .stats-card {
        background: #fff;
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
        box-shadow: 0 4px 12px rgba(59,130,246,.15);
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
        letter-spacing: .5px;
        margin-bottom: 5px;
    }
    .stats-icon {
        font-size: 48px;
        color: #dbeafe;
        position: absolute;
        right: 20px;
        top: 20px;
        opacity: .6;
    }
    .stats-meta { font-size: 12px; color: #9ca3af; margin-top: 8px; }

    /* Breakdown card */
    .breakdown-card {
        background: #fff;
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
        padding: 20px;
        margin-top: 10px;
        background: #eff6ff;
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
        width: 32px; height: 32px;
        background: #eff6ff;
        border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        color: #2563eb;
        font-size: 18px;
    }
    .breakdown-value { font-weight: 700; font-size: 20px; color: #2563eb; }
    .breakdown-value.negative { color: #dc2626; }

    .section-title {
        color: #111827;
        font-weight: 700;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 18px;
    }
    .section-title i { color: #2563eb; }

    .info-badge {
        background: #eff6ff;
        color: #2563eb;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 8px;
    }

    /* Filter form */
    .overviewcontainar {
        background: #fff;
        border: 2px solid #f3f4f6;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        display: flex;
        gap: 15px;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    #searchleftcont  { flex: 1; min-width: 200px; }
    #searchbuttoncont { flex: 0 0 auto; }

    .btn-primary {
        background: #2563eb; border: none;
        padding: 10px 24px; border-radius: 8px;
        font-weight: 500;
        display: flex; align-items: center; gap: 8px;
    }
    .btn-primary:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37,99,235,.3);
    }
    .form-control {
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px 14px;
    }
    .form-control:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    }
    .form-label { font-weight: 500; color: #374151; margin-bottom: 6px; font-size: 13px; }

    @media (max-width: 768px) {
        .stats-value { font-size: 32px; }
        .stats-icon  { font-size: 36px; }
        .breakdown-value { font-size: 18px; }
        .overviewcontainar { flex-direction: column; }
        #searchleftcont, #searchbuttoncont, .btn-primary { width: 100%; }
        .btn-primary { justify-content: center; }
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
                                        <tr><td>Reward Points Dashboard</td></tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Date Filter Form -->
                    <form method="post"
                          action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>"
                          novalidate>
                        <div class="overviewcontainar">
                            <div id="searchleftcont">
                                <label class="form-label" for="frdate">From Date</label>
                                <input type="date" required name="frdate" id="frdate"
                                       value="<?php echo htmlspecialchars($currentFromDate); ?>"
                                       class="form-control"
                                       max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div id="searchleftcont">
                                <label class="form-label" for="todate">To Date</label>
                                <input type="date" required name="todate" id="todate"
                                       value="<?php echo htmlspecialchars($currentToDate); ?>"
                                       class="form-control"
                                       max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div id="searchbuttoncont">
                                <button type="submit" name="sedatas" class="btn btn-primary">
                                    <i class="material-icons">search</i>Search
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- ======================================================
                         SUMMARY STAT CARDS
                         ====================================================== -->
                    <div class="row">

                        <!-- Total Reward Points -->
                        <div class="col-md-4">
                            <div class="stats-card">
                                <i class="material-icons stats-icon">emoji_events</i>
                                <div class="stats-label">Total Reward Points</div>
                                <div class="stats-value"><?php echo $formattedTotal; ?></div>
                                <div class="stats-meta">
                                    <?php echo $displayFrom; ?> – <?php echo $displayTo; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Daily Login Points -->
                        <div class="col-md-4">
                            <div class="stats-card">
                                <i class="material-icons stats-icon">card_giftcard</i>
                                <div class="stats-label">Daily Login Points</div>
                                <div class="stats-value"><?php echo $formattedDaily; ?></div>
                                <div class="stats-meta">
                                    <?php echo $daysRewarded; ?> day<?php echo $daysRewarded !== 1 ? 's' : ''; ?> rewarded
                                </div>
                            </div>
                        </div>

                        <!-- Last Reward Date -->
                        <div class="col-md-4">
                            <div class="stats-card">
                                <i class="material-icons stats-icon">schedule</i>
                                <div class="stats-label">Last Points Received</div>
                                <div class="stats-value" style="font-size:28px;">
                                    <?php echo htmlspecialchars($lastRewardDate, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="stats-meta">Keep logging in daily!</div>
                            </div>
                        </div>

                        <!-- Advance Payment Bonus Points (SS / Stockist only) -->
                        <?php if ($isAdvanceEligibleType): ?>
                        <div class="col-md-4">
                            <div class="stats-card" style="border-color:#d1fae5;">
                                <i class="material-icons stats-icon" style="color:#d1fae5;">account_balance_wallet</i>
                                <div class="stats-label" style="color:#065f46;">Advance Payment Bonus</div>
                                <div class="stats-value" style="color:#059669;">
                                    <?php echo $formattedTotalAdvance; ?>
                                </div>
                                <div class="stats-meta">
                                    <?php echo $displayFrom; ?> – <?php echo $displayTo; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div><!-- /row summary cards -->

                    <!-- ======================================================
                         POINTS BREAKDOWN TABLE
                         ====================================================== -->
                    <div class="row">
                        <div class="col-12">
                            <div class="breakdown-card">
                                <h5 class="section-title">
                                    <i class="material-icons">analytics</i>
                                    Points Breakdown
                                </h5>

                                <!-- Purchase points -->
                                <div class="breakdown-item">
                                    <div class="breakdown-label">
                                        <div class="breakdown-icon">
                                            <i class="material-icons">shopping_cart</i>
                                        </div>
                                        <span>
                                            Points from Product Purchases
                                            <span class="info-badge">
                                                <?php echo $invoiceCount; ?>
                                                invoice<?php echo $invoiceCount !== 1 ? 's' : ''; ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="breakdown-value">+<?php echo $formattedPurchase; ?></div>
                                </div>

                                <!-- Daily login -->
                                <div class="breakdown-item">
                                    <div class="breakdown-label">
                                        <div class="breakdown-icon">
                                            <i class="material-icons">card_giftcard</i>
                                        </div>
                                        <span>
                                            Daily Login &amp; Billing Rewards
                                            <span class="info-badge">
                                                <?php echo $daysRewarded; ?>
                                                day<?php echo $daysRewarded !== 1 ? 's' : ''; ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="breakdown-value">+<?php echo $formattedDaily; ?></div>
                                </div>

                                <!-- Advance bonus points -->
                                <?php if ($isAdvanceEligibleType && $totalAdvanceBonusPoints > 0): ?>
                                <div class="breakdown-item">
                                    <div class="breakdown-label">
                                        <div class="breakdown-icon"
                                             style="background:#d1fae5; color:#059669;">
                                            <i class="material-icons">account_balance_wallet</i>
                                        </div>
                                        <span>Advance Payment Bonus Points</span>
                                    </div>
                                    <div class="breakdown-value" style="color:#059669;">
                                        +<?php echo $formattedTotalAdvance; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Return deductions -->
                                <?php if ($returnPoints > 0): ?>
                                <div class="breakdown-item">
                                    <div class="breakdown-label">
                                        <div class="breakdown-icon"
                                             style="background:#fee2e2; color:#dc2626;">
                                            <i class="material-icons">remove_circle</i>
                                        </div>
                                        <span>
                                            Points Deducted from Returns
                                            <span class="info-badge"
                                                  style="background:#fee2e2; color:#dc2626;">
                                                <?php echo $returnCount; ?>
                                                return<?php echo $returnCount !== 1 ? 's' : ''; ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="breakdown-value negative">
                                        −<?php echo $formattedReturn; ?>
                                    </div>
                                </div>
                                <?php endif; ?>


                                <!-- Grand total row -->
                                <div class="breakdown-item">
                                    <div class="breakdown-label">
                                        <i class="material-icons"
                                           style="color:#2563eb; font-size:24px;">emoji_events</i>
                                        <strong style="font-size:16px;">Total Reward Points</strong>
                                    </div>
                                    <div class="breakdown-value" style="font-size:32px;">
                                        <?php echo $formattedTotal; ?>
                                    </div>
                                </div>

                            </div><!-- /breakdown-card -->
                        </div>
                    </div>


                </div><!-- /container-fluid -->
            </div><!-- /content-wrapper -->
        </div><!-- /app-content -->
    </div><!-- /app-container -->
</div><!-- /app -->

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
document.addEventListener('DOMContentLoaded', function () {
    const form    = document.querySelector('form');
    const fromDt  = document.getElementById('frdate');
    const toDt    = document.getElementById('todate');

    form.addEventListener('submit', function (e) {
        if (new Date(fromDt.value) > new Date(toDt.value)) {
            e.preventDefault();
            alert('From Date cannot be later than To Date.');
            fromDt.focus();
        }
    });
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>