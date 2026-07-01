<?php
/**
 * Reward Points Dashboard - Territory Partner
 */
ob_start();

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

include("checksession.php");
include("config.php");

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
date_default_timezone_set("Asia/Kolkata");

if (!isset($Login_user_TYPEvl, $Login_user_IDvl, $business_name)) {
    die("Session variables not properly initialised. Please log in again.");
}

function validateDate_tp(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}

function executeQueryRow_tp(mysqli $conn, string $query, array $params, string $types): ?array {
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) return null;
        if (!empty($params)) {
            $refs = [];
            foreach ($params as $i => $val) {
                $refs[] = &$params[$i];
            }
            $stmt->bind_param($types, ...$refs);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?? [];
    } catch (\Throwable $e) {
        error_log('[TP reward-points] Query error: ' . $e->getMessage());
        return null;
    }
}

$daysInMonth     = (int) date('t');
$currentMonth    = date('m');
$defaultFromDate = date("Y-{$currentMonth}-01");
$defaultToDate   = date("Y-{$currentMonth}-{$daysInMonth}");

$currentFromDate = validateDate_tp($_POST['frdate'] ?? $_GET['frdate'] ?? null, $defaultFromDate);
$currentToDate   = validateDate_tp($_POST['todate'] ?? $_GET['todate'] ?? null, $defaultToDate);

if (strtotime($currentFromDate) > strtotime($currentToDate)) {
    [$currentFromDate, $currentToDate] = [$currentToDate, $currentFromDate];
}

$userType = mysqli_real_escape_string($db_conn, $Login_user_TYPEvl);
$userId   = mysqli_real_escape_string($db_conn, $Login_user_IDvl);

// 1. Purchase Points — total_amount of company stock bills to this TP / 100
$purchaseQuery = "
    SELECT COALESCE(SUM(total_amount) / 100, 0) AS purchase_points,
           COUNT(*) AS invoice_count
    FROM tp_invoices
    WHERE territory_partner_id = ? AND invoice_date BETWEEN ? AND ?
";
$purchaseResult = executeQueryRow_tp($db_conn, $purchaseQuery, [(int)$userId, $currentFromDate, $currentToDate], 'iss');
$purchasePoints = (float)($purchaseResult['purchase_points'] ?? 0);
$invoiceCount   = (int)($purchaseResult['invoice_count'] ?? 0);

// 2. Daily Login Points
$dailyQuery = "
    SELECT COALESCE(SUM(points_awarded), 0) AS daily_points, COUNT(*) AS days_rewarded, MAX(reward_date) AS last_reward_date
    FROM daily_login_rewards
    WHERE user_type = ? AND user_id = ? AND reward_date BETWEEN ? AND ?
";
$dailyResult    = executeQueryRow_tp($db_conn, $dailyQuery, [$userType, $userId, $currentFromDate, $currentToDate], 'ssss');
$dailyPoints    = (float)($dailyResult['daily_points'] ?? 0);
$daysRewarded   = (int)($dailyResult['days_rewarded'] ?? 0);
$lastRewardDate = !empty($dailyResult['last_reward_date']) ? date('d M Y', strtotime($dailyResult['last_reward_date'])) : 'N/A';

// 3. Return Deductions — returns against tp_invoices received in this date range
$returnQuery = "
    SELECT COALESCE(SUM(r.subtotal) / 100, 0) AS return_points, COUNT(DISTINCT r.invnumber) AS return_count
    FROM user_return_stock_items r
    WHERE r.invnumber IN (
        SELECT invoice_number FROM tp_invoices
        WHERE territory_partner_id = ? AND invoice_date BETWEEN ? AND ?
    ) AND r.from_usertype = ? AND r.from_userid = ?
";
$returnResult = executeQueryRow_tp($db_conn, $returnQuery, [(int)$userId, $currentFromDate, $currentToDate, $userType, $userId], 'issss');
$returnPoints = (float)($returnResult['return_points'] ?? 0);
$returnCount  = (int)($returnResult['return_count'] ?? 0);

// 4. No advance bonus for TPs
$totalAdvanceBonusPoints = 0.0;
$isAdvanceEligibleType   = false;

// 5. Grand Total
$totalPoints = max(0, $purchasePoints + $dailyPoints - $returnPoints);

$fmt = static fn(float $v): string => number_format($v, 2);
$formattedTotal    = $fmt($totalPoints);
$formattedPurchase = $fmt($purchasePoints);
$formattedDaily    = $fmt($dailyPoints);
$formattedReturn   = $fmt($returnPoints);

$displayFrom      = date('d M', strtotime($currentFromDate));
$displayTo        = date('d M Y', strtotime($currentToDate));
$safeBusinessName = htmlspecialchars($business_name, ENT_QUOTES, 'UTF-8');

$advBalance = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reward Points : <?php echo $safeBusinessName; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
    <style>
    .stats-card { background:#fff; border:2px solid #dbeafe; border-radius:12px; padding:25px; margin-bottom:20px; transition:all .3s ease; position:relative; overflow:hidden; }
    .stats-card:hover { border-color:#60a5fa; box-shadow:0 4px 12px rgba(59,130,246,.15); transform:translateY(-2px); }
    .stats-value { font-size:42px; font-weight:700; color:#2563eb; margin:10px 0; line-height:1; }
    .stats-label { font-size:13px; color:#6b7280; font-weight:500; text-transform:uppercase; letter-spacing:.5px; margin-bottom:5px; }
    .stats-icon { font-size:48px; color:#dbeafe; position:absolute; right:20px; top:20px; opacity:.6; }
    .stats-meta { font-size:12px; color:#9ca3af; margin-top:8px; }
    .breakdown-card { background:#fff; border:2px solid #f3f4f6; border-radius:12px; padding:25px; margin-bottom:20px; }
    .breakdown-item { display:flex; justify-content:space-between; align-items:center; padding:18px 0; border-bottom:1px solid #f3f4f6; }
    .breakdown-item:last-child { border-bottom:none; padding:20px; margin-top:10px; background:#eff6ff; border-radius:8px; }
    .breakdown-label { display:flex; align-items:center; gap:12px; color:#374151; font-weight:500; }
    .breakdown-icon { width:32px; height:32px; background:#eff6ff; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#2563eb; font-size:18px; }
    .breakdown-value { font-weight:700; font-size:20px; color:#2563eb; }
    .breakdown-value.negative { color:#dc2626; }
    .section-title { color:#111827; font-weight:700; margin-bottom:20px; display:flex; align-items:center; gap:10px; font-size:18px; }
    .section-title i { color:#2563eb; }
    .info-badge { background:#eff6ff; color:#2563eb; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; margin-left:8px; }
    .overviewcontainar { background:#fff; border:2px solid #f3f4f6; border-radius:12px; padding:20px; margin-bottom:25px; display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap; }
    #searchleftcont { flex:1; min-width:200px; }
    #searchbuttoncont { flex:0 0 auto; }
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
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1><table class="headertble"><tr><td>Reward Points Dashboard</td></tr></table></h1>
                            </div>
                        </div>
                    </div>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" novalidate>
                        <div class="overviewcontainar">
                            <div id="searchleftcont">
                                <label class="form-label" for="frdate">From Date</label>
                                <input type="date" required name="frdate" id="frdate" value="<?php echo htmlspecialchars($currentFromDate); ?>" class="form-control" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div id="searchleftcont">
                                <label class="form-label" for="todate">To Date</label>
                                <input type="date" required name="todate" id="todate" value="<?php echo htmlspecialchars($currentToDate); ?>" class="form-control" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div id="searchbuttoncont">
                                <button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
                            </div>
                        </div>
                    </form>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stats-card">
                                <i class="material-icons stats-icon">emoji_events</i>
                                <div class="stats-label">Total Reward Points</div>
                                <div class="stats-value"><?php echo $formattedTotal; ?></div>
                                <div class="stats-meta"><?php echo $displayFrom; ?> – <?php echo $displayTo; ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card">
                                <i class="material-icons stats-icon">card_giftcard</i>
                                <div class="stats-label">Daily Login Points</div>
                                <div class="stats-value"><?php echo $formattedDaily; ?></div>
                                <div class="stats-meta"><?php echo $daysRewarded; ?> day<?php echo $daysRewarded !== 1 ? 's' : ''; ?> rewarded</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card">
                                <i class="material-icons stats-icon">schedule</i>
                                <div class="stats-label">Last Points Received</div>
                                <div class="stats-value" style="font-size:28px;"><?php echo htmlspecialchars($lastRewardDate, ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="stats-meta">Keep logging in daily!</div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="breakdown-card">
                                <h5 class="section-title"><i class="material-icons">analytics</i>Points Breakdown</h5>
                                <div class="breakdown-item">
                                    <div class="breakdown-label">
                                        <div class="breakdown-icon"><i class="material-icons">shopping_cart</i></div>
                                        <span>Points from Product Purchases<span class="info-badge"><?php echo $invoiceCount; ?> invoice<?php echo $invoiceCount !== 1 ? 's' : ''; ?></span></span>
                                    </div>
                                    <div class="breakdown-value">+<?php echo $formattedPurchase; ?></div>
                                </div>
                                <div class="breakdown-item">
                                    <div class="breakdown-label">
                                        <div class="breakdown-icon"><i class="material-icons">card_giftcard</i></div>
                                        <span>Daily Login &amp; Billing Rewards<span class="info-badge"><?php echo $daysRewarded; ?> day<?php echo $daysRewarded !== 1 ? 's' : ''; ?></span></span>
                                    </div>
                                    <div class="breakdown-value">+<?php echo $formattedDaily; ?></div>
                                </div>
                                <?php if ($returnPoints > 0): ?>
                                <div class="breakdown-item">
                                    <div class="breakdown-label">
                                        <div class="breakdown-icon" style="background:#fee2e2; color:#dc2626;"><i class="material-icons">remove_circle</i></div>
                                        <span>Points Deducted from Returns<span class="info-badge" style="background:#fee2e2; color:#dc2626;"><?php echo $returnCount; ?> return<?php echo $returnCount !== 1 ? 's' : ''; ?></span></span>
                                    </div>
                                    <div class="breakdown-value negative">−<?php echo $formattedReturn; ?></div>
                                </div>
                                <?php endif; ?>
                                <div class="breakdown-item">
                                    <div class="breakdown-label">
                                        <i class="material-icons" style="color:#2563eb; font-size:24px;">emoji_events</i>
                                        <strong style="font-size:16px;">Total Reward Points</strong>
                                    </div>
                                    <div class="breakdown-value" style="font-size:32px;"><?php echo $formattedTotal; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
