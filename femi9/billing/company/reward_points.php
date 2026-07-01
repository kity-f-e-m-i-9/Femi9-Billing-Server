<?php

/**
 * Reward Points Admin Panel - UPDATED WITH advance_bonus_points COLUMN
 * 
 * Shows aggregated reward points for all users of selected type with:
 * - Purchase points (with proper return deductions) - ONLY for invoices with rwpoints_enable = 1
 * - Daily login points
 * - Advance Bonus Points (from bonus_points_history, rolled_back_at IS NULL)
 * - Sales points - ONLY for invoices with rwpoints_enable = 1
 * - Deducted points column showing returns
 * 
 * TOTAL REWARD POINTS = Purchase Points + Daily Login + Advance Bonus - Purchase Returns
 * SORTING: Entries sorted by Total Reward Points (descending)
 */

// Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

// Session and Configuration
require_once("checksession.php");
require_once("config.php");

// Error Reporting (disable display_errors in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ============================================================================
// INPUT VALIDATION & SANITIZATION
// ============================================================================

$allowed_user_types = ['super_stockiest', 'stockiest', 'super_distributor', 'distributor'];
$getinvuser = $_REQUEST['femiusr'] ?? 'distributor';

if (!in_array($getinvuser, $allowed_user_types, true)) {
    die("Invalid user type");
}

$user_type_config = [
    'super_stockiest' => ['label' => 'Super Stockist', 'table' => 'super_stockiest'],
    'stockiest'       => ['label' => 'Stockist',       'table' => 'stockiest'],
    'super_distributor' => ['label' => 'Super Distributor', 'table' => 'super_distributor'],
    'distributor'     => ['label' => 'Distributor',    'table' => 'distributor']
];

$lablenamedisplay = $user_type_config[$getinvuser]['label'];
$tablename        = $user_type_config[$getinvuser]['table'];

// Date Configuration
date_default_timezone_set("Asia/Kolkata");
$numberOfDays    = (int)date('t');
$current_month   = date('m');

function validateDate(?string $date, string $default): string {
    if (empty($date)) return $default;
    $timestamp = strtotime($date);
    if ($timestamp === false) return $default;
    return date('Y-m-d', $timestamp);
}

$default_from_date = date("Y-{$current_month}-01");
$default_to_date   = date("Y-{$current_month}-{$numberOfDays}");

$current_from_date = validateDate($_POST['frdate'] ?? $_GET['frdate'] ?? null, $default_from_date);
$current_to_date   = validateDate($_POST['todate'] ?? $_GET['todate'] ?? null, $default_to_date);

if (strtotime($current_from_date) > strtotime($current_to_date)) {
    [$current_from_date, $current_to_date] = [$current_to_date, $current_from_date];
}

$safe_business_name = htmlspecialchars($business_name ?? 'Femi9', ENT_QUOTES, 'UTF-8');
$safe_report_label  = htmlspecialchars($Report_LABLE ?? 'Reward Points Report', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Reward Points Admin Panel">
    
    <title><?php echo $safe_report_label; ?> : <?php echo $safe_business_name; ?></title>

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
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    
    <style>
    .deducted-points  { color: #dc2626; font-weight: 600; }
    .total-points     { color: #2563eb; font-weight: 700; font-size: 1.05em; }
    .sales-points     { color: #059669; font-weight: 600; }
    .advance-bonus    { color: #7c3aed; font-weight: 600; }
    #overflowon {
        width: 100%;
        overflow-x: auto !important;
        height: 100%;
        overflow-y: hidden;
    }
    .btn-export { background:#1d6f42; color:#fff; border:none; }
    .btn-export:hover { background:#155734; color:#fff; }
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
                                                <td>
                                                    Reward Points<br/>
                                                    <h3><?php echo htmlspecialchars($lablenamedisplay); ?></h3>
                                                </td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>
						
                        <!-- Date Filter Form -->
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
                            <input type="hidden" name="femiusr" value="<?php echo htmlspecialchars($getinvuser); ?>"/>
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
                                    &nbsp;
                                    <a href="reward_points_export.php?femiusr=<?php echo urlencode($getinvuser); ?>&frdate=<?php echo urlencode($current_from_date); ?>&todate=<?php echo urlencode($current_to_date); ?>" class="btn btn-export">
                                        <i class="material-icons">download</i>Export Excel
                                    </a>
                                </div>
                            </div>
                            <div style="clear:both;"></div>
                            <br/>
                        </form>

<?php
// ============================================================================
// DATABASE CONNECTION AND QUERIES
// ============================================================================

try {
    $pdo = new PDO(
        "mysql:host={$servername};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );

    // ============================================================================
    // QUERY 1: PURCHASE POINTS WITH PROPER RETURN DEDUCTION
    // Filters by rwpoints_enable = 1; returns deducted on original invoice date
    // ============================================================================
    $query_purchase = "
        SELECT 
            invoice_data.to_user_id as user_id,
            COALESCE(SUM(invoice_data.purchase_points), 0) as gross_purchase_points,
            COALESCE(SUM(return_data.return_points), 0) as deducted_purchase_points,
            GREATEST(
                COALESCE(SUM(invoice_data.purchase_points), 0) - COALESCE(SUM(return_data.return_points), 0),
                0
            ) as net_purchase_points
        FROM (
            SELECT 
                uii.to_user_id,
                uii.inv_id,
                SUM(uii.subtotal) / 100 as purchase_points
            FROM user_invoice_items uii
            INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
            WHERE uii.date BETWEEN :from_date1 AND :to_date1
                AND uii.to_user_type = :user_type1
                AND ui.rwpoints_enable = 1
            GROUP BY uii.to_user_id, uii.inv_id
        ) invoice_data
        LEFT JOIN (
            SELECT 
                r.from_userid,
                r.invnumber,
                SUM(r.subtotal) / 100 as return_points
            FROM user_return_stock_items r
            WHERE r.invnumber IN (
                SELECT DISTINCT uii.inv_id 
                FROM user_invoice_items uii
                INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
                WHERE uii.date BETWEEN :from_date2 AND :to_date2
                    AND uii.to_user_type = :user_type2
                    AND ui.rwpoints_enable = 1
            )
            AND r.from_usertype = :user_type3
            GROUP BY r.from_userid, r.invnumber
        ) return_data 
            ON return_data.from_userid = invoice_data.to_user_id 
            AND return_data.invnumber = invoice_data.inv_id
        GROUP BY invoice_data.to_user_id
    ";

    $stmt_purchase = $pdo->prepare($query_purchase);
    $stmt_purchase->execute([
        ':from_date1' => $current_from_date,
        ':to_date1'   => $current_to_date,
        ':user_type1' => $getinvuser,
        ':from_date2' => $current_from_date,
        ':to_date2'   => $current_to_date,
        ':user_type2' => $getinvuser,
        ':user_type3' => $getinvuser
    ]);
    $purchase_results = $stmt_purchase->fetchAll();

    // ============================================================================
    // QUERY 2: USER-TO-USER SALES POINTS
    // Filters by rwpoints_enable = 1; uses subtotal/100 formula
    // ============================================================================
    $query_user_sales = "
        SELECT 
            invoice_data.from_user_id as user_id,
            COALESCE(SUM(invoice_data.sales_points), 0) as gross_user_sales_points,
            COALESCE(SUM(return_data.return_points), 0) as deducted_user_sales_points,
            GREATEST(
                COALESCE(SUM(invoice_data.sales_points), 0) - COALESCE(SUM(return_data.return_points), 0),
                0
            ) as net_user_sales_points
        FROM (
            SELECT 
                uii.from_user_id,
                uii.inv_id,
                SUM(uii.subtotal) / 100 as sales_points
            FROM user_invoice_items uii
            INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
            WHERE uii.date BETWEEN :from_date1 AND :to_date1
                AND uii.from_user_type = :user_type1
                AND ui.rwpoints_enable = 1
            GROUP BY uii.from_user_id, uii.inv_id
        ) invoice_data
        LEFT JOIN (
            SELECT 
                r.to_userid,
                r.invnumber,
                SUM(r.subtotal) / 100 as return_points
            FROM user_return_stock_items r
            WHERE r.invnumber IN (
                SELECT DISTINCT uii.inv_id 
                FROM user_invoice_items uii
                INNER JOIN user_invoice ui ON uii.inv_id = ui.inv_id
                WHERE uii.date BETWEEN :from_date2 AND :to_date2
                    AND uii.from_user_type = :user_type2
                    AND ui.rwpoints_enable = 1
            )
            AND r.to_usertype = :user_type3
            GROUP BY r.to_userid, r.invnumber
        ) return_data 
            ON return_data.to_userid = invoice_data.from_user_id 
            AND return_data.invnumber = invoice_data.inv_id
        GROUP BY invoice_data.from_user_id
    ";

    $stmt_user_sales = $pdo->prepare($query_user_sales);
    $stmt_user_sales->execute([
        ':from_date1' => $current_from_date,
        ':to_date1'   => $current_to_date,
        ':user_type1' => $getinvuser,
        ':from_date2' => $current_from_date,
        ':to_date2'   => $current_to_date,
        ':user_type2' => $getinvuser,
        ':user_type3' => $getinvuser
    ]);
    $user_sales_results = $stmt_user_sales->fetchAll();

    // ============================================================================
    // QUERY 3: CUSTOMER SALES POINTS
    // NOTE: invoice table does NOT have rwpoints_enable — calculates for ALL customer sales
    // ============================================================================
    $check_table = "SHOW TABLES LIKE 'return_stock_items'";
    $table_exists = $pdo->query($check_table)->fetch();

    if ($table_exists) {
        $query_customer_sales = "
            SELECT 
                invoice_data.user_id,
                COALESCE(SUM(invoice_data.sales_points), 0) as gross_customer_sales_points,
                COALESCE(SUM(return_data.return_points), 0) as deducted_customer_sales_points,
                GREATEST(
                    COALESCE(SUM(invoice_data.sales_points), 0) - COALESCE(SUM(return_data.return_points), 0),
                    0
                ) as net_customer_sales_points
            FROM (
                SELECT 
                    ii.user_id,
                    ii.inv_id,
                    SUM(ii.subtotal) / 100 as sales_points
                FROM invoice_items ii
                WHERE ii.date BETWEEN :from_date1 AND :to_date1
                    AND ii.user_type = :user_type1
                GROUP BY ii.user_id, ii.inv_id
            ) invoice_data
            LEFT JOIN (
                SELECT 
                    r.user_id,
                    r.invnumber,
                    SUM(r.subtotal) / 100 as return_points
                FROM return_stock_items r
                WHERE r.invnumber IN (
                    SELECT DISTINCT ii.inv_id 
                    FROM invoice_items ii
                    WHERE ii.date BETWEEN :from_date2 AND :to_date2
                        AND ii.user_type = :user_type2
                )
                AND r.user_type = :user_type3
                GROUP BY r.user_id, r.invnumber
            ) return_data 
                ON return_data.user_id = invoice_data.user_id 
                AND return_data.invnumber = invoice_data.inv_id
            GROUP BY invoice_data.user_id
        ";

        $stmt_customer_sales = $pdo->prepare($query_customer_sales);
        $stmt_customer_sales->execute([
            ':from_date1' => $current_from_date,
            ':to_date1'   => $current_to_date,
            ':user_type1' => $getinvuser,
            ':from_date2' => $current_from_date,
            ':to_date2'   => $current_to_date,
            ':user_type2' => $getinvuser,
            ':user_type3' => $getinvuser
        ]);
    } else {
        $query_customer_sales = "
            SELECT 
                ii.user_id,
                SUM(ii.subtotal) / 100 as gross_customer_sales_points,
                0 as deducted_customer_sales_points,
                SUM(ii.subtotal) / 100 as net_customer_sales_points
            FROM invoice_items ii
            WHERE ii.date BETWEEN :from_date AND :to_date
                AND ii.user_type = :user_type
            GROUP BY ii.user_id
        ";

        $stmt_customer_sales = $pdo->prepare($query_customer_sales);
        $stmt_customer_sales->execute([
            ':from_date' => $current_from_date,
            ':to_date'   => $current_to_date,
            ':user_type' => $getinvuser
        ]);
    }
    $customer_sales_results = $stmt_customer_sales->fetchAll();

    // ============================================================================
    // QUERY 4: DAILY LOGIN POINTS
    // ============================================================================
    $query_daily_points = "
        SELECT 
            user_id,
            SUM(points_awarded) as daily_points
        FROM daily_login_rewards
        WHERE user_type = :user_type
            AND reward_date BETWEEN :from_date AND :to_date
        GROUP BY user_id
        HAVING daily_points > 0
    ";

    $stmt_daily = $pdo->prepare($query_daily_points);
    $stmt_daily->execute([
        ':user_type' => $getinvuser,
        ':from_date' => $current_from_date,
        ':to_date'   => $current_to_date
    ]);
    $daily_points_results = $stmt_daily->fetchAll();

    // ============================================================================
    // QUERY 5: ADVANCE PAYMENT BONUS POINTS
    // Source: bonus_points_history
    // Filter: user_type match, month_year within selected date range, rolled_back_at IS NULL
    // month_year is VARCHAR 'YYYY-MM' representing the month the bonus was EARNED FOR.
    // We do NOT filter by executed_at because bonuses for April are run in May --
    // filtering by executed_at would misattribute them to the wrong month.
    // Only applicable for super_stockiest and stockiest -- skipped for others.
    // ============================================================================
    $advance_bonus_results    = [];
    $advance_bonus_user_types = ['super_stockiest', 'stockiest'];

    if (in_array($getinvuser, $advance_bonus_user_types, true)) {

        // Build all YYYY-MM values that fall within the selected date range
        $month_years = [];
        $loop_ts     = strtotime(date('Y-m-01', strtotime($current_from_date)));
        $end_ts      = strtotime(date('Y-m-01', strtotime($current_to_date)));

        while ($loop_ts <= $end_ts) {
            $month_years[] = date('Y-m', $loop_ts);
            $loop_ts = strtotime('+1 month', $loop_ts);
        }

        if (!empty($month_years)) {
            $my_placeholders = str_repeat('?,', count($month_years) - 1) . '?';

            $query_advance_bonus = "
                SELECT
                    user_id,
                    SUM(bonus_points_awarded) as advance_bonus_points
                FROM bonus_points_history
                WHERE user_type = ?
                    AND month_year IN ({$my_placeholders})
                    AND rolled_back_at IS NULL
                GROUP BY user_id
                HAVING advance_bonus_points > 0
            ";

            $stmt_advance_bonus = $pdo->prepare($query_advance_bonus);
            $stmt_advance_bonus->execute(array_merge([$getinvuser], $month_years));
            $advance_bonus_results = $stmt_advance_bonus->fetchAll();
        }
    }

    // ============================================================================
    // ORGANIZE RESULTS INTO LOOKUP ARRAYS
    // ============================================================================
    $purchase_data       = [];
    $user_sales_data     = [];
    $customer_sales_data = [];
    $daily_login_points  = [];
    $advance_bonus_data  = [];
    $all_users           = [];

    foreach ($purchase_results as $row) {
        $purchase_data[$row['user_id']] = [
            'gross'    => $row['gross_purchase_points'],
            'deducted' => $row['deducted_purchase_points'],
            'net'      => $row['net_purchase_points']
        ];
        $all_users[$row['user_id']] = true;
    }

    foreach ($user_sales_results as $row) {
        $user_sales_data[$row['user_id']] = [
            'gross'    => $row['gross_user_sales_points'],
            'deducted' => $row['deducted_user_sales_points'],
            'net'      => $row['net_user_sales_points']
        ];
        $all_users[$row['user_id']] = true;
    }

    foreach ($customer_sales_results as $row) {
        $customer_sales_data[$row['user_id']] = [
            'gross'    => $row['gross_customer_sales_points'],
            'deducted' => $row['deducted_customer_sales_points'],
            'net'      => $row['net_customer_sales_points']
        ];
        $all_users[$row['user_id']] = true;
    }

    foreach ($daily_points_results as $row) {
        $daily_login_points[$row['user_id']] = $row['daily_points'];
        $all_users[$row['user_id']] = true;
    }

    foreach ($advance_bonus_results as $row) {
        $advance_bonus_data[$row['user_id']] = $row['advance_bonus_points'];
        $all_users[$row['user_id']] = true;
    }

    $user_ids = array_keys($all_users);

    if (empty($user_ids)) {
        $combined_users = [];
    } else {
        // ============================================================================
        // QUERY 6: FETCH ALL USER DETAILS IN ONE QUERY
        // ============================================================================
        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
        $query_users  = "
            SELECT 
                u.temp_id,
                u.name,
                u.mobile_number,
                u.district_id,
                COALESCE(d.dist_name, u.district_id, 'Nil') as district_name
            FROM {$tablename} u
            LEFT JOIN district d ON u.district_id = d.id
            WHERE u.temp_id IN ($placeholders)
        ";

        $stmt_users  = $pdo->prepare($query_users);
        $stmt_users->execute($user_ids);
        $user_details = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

        $user_lookup = [];
        foreach ($user_details as $user) {
            $user_lookup[$user['temp_id']] = $user;
        }

        // ============================================================================
        // QUERY 7: FETCH STOCKIST CATEGORIES IF NEEDED
        // ============================================================================
        $category_lookup = [];
        if ($getinvuser === "stockiest") {
            $query_categories = "
                SELECT 
                    sr.stockist_id,
                    COALESCE(sc.catname, '') as catname
                FROM stockist_referral sr
                LEFT JOIN stockist_category sc ON sr.st_cat_id = sc.id
                WHERE sr.stockist_id IN ($placeholders)
            ";

            try {
                $stmt_categories = $pdo->prepare($query_categories);
                $stmt_categories->execute($user_ids);
                $category_results = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);

                foreach ($category_results as $cat) {
                    $category_lookup[$cat['stockist_id']] = $cat['catname'];
                }
            } catch (PDOException $e) {
                error_log("Category query error: " . $e->getMessage());
            }
        }

        // ============================================================================
        // COMBINE ALL DATA
        // Total Reward Points = Purchase Points + Daily Login + Advance Bonus - Purchase Returns
        // ============================================================================
        $combined_users = [];
        foreach ($user_ids as $user_id) {

            $purchase_pts     = $purchase_data[$user_id]['net']      ?? 0;
            $purchase_deducted = $purchase_data[$user_id]['deducted'] ?? 0;

            $user_sales_pts      = $user_sales_data[$user_id]['net']      ?? 0;
            $user_sales_deducted = $user_sales_data[$user_id]['deducted'] ?? 0;

            $customer_sales_pts      = $customer_sales_data[$user_id]['net']      ?? 0;
            $customer_sales_deducted = $customer_sales_data[$user_id]['deducted'] ?? 0;

            $daily_pts         = $daily_login_points[$user_id] ?? 0;
            $advance_bonus_pts = $advance_bonus_data[$user_id] ?? 0;

            $total_sales_pts      = $user_sales_pts + $customer_sales_pts;
            $total_sales_deducted = $user_sales_deducted + $customer_sales_deducted;

            // Total Reward Points = Purchase(net) + Daily Login + Advance Bonus
            // Note: purchase_pts is already net (gross - returns), and purchase_deducted
            // is shown separately as "Purchase Returns" for transparency.
            $total_purchase_points = $purchase_pts + $daily_pts + $advance_bonus_pts;

            $total_deducted = $purchase_deducted;

            // Overall grand total (purchase side + sales side)
            $overall_total = $total_purchase_points + $total_sales_pts;

            if ($purchase_pts > 0 || $total_sales_pts > 0 || $daily_pts > 0 
                || $advance_bonus_pts > 0 || $total_deducted > 0 || $total_sales_deducted > 0) {

                $combined_users[] = [
                    'user_id'                => $user_id,
                    'product_purchase_points' => $purchase_pts,
                    'daily_login_points'      => $daily_pts,
                    'advance_bonus_points'    => $advance_bonus_pts,
                    'deducted_points'         => $total_deducted,
                    'total_purchase_points'   => $total_purchase_points,
                    'total_sales_points'      => $total_sales_pts,
                    'total_sales_deducted'    => $total_sales_deducted,
                    'overall_total'           => $overall_total,
                    'user_details'            => $user_lookup[$user_id] ?? null,
                    'category'               => $category_lookup[$user_id] ?? ''
                ];
            }
        }

        // Sort by Total Reward Points descending
        usort($combined_users, function($a, $b) {
            return $b['total_purchase_points'] <=> $a['total_purchase_points'];
        });
    }

} catch (PDOException $e) {
    error_log("Reward Points Admin Error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>";
    echo "<strong>Database Error:</strong> Unable to fetch reward points data. Please check the error log.";
    echo "</div>";
    $combined_users = [];
}
?>
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div id="overflowon">
                                            <table id="datatable1" class="display" style="width:100%;">
                                                <thead>
                                                    <tr>
                                                        <th>S.No</th>
                                                        <th>Name</th>
                                                        <th>District</th>
                                                        <?php if($getinvuser === "stockiest"): ?>
                                                        <th>Category</th>
                                                        <?php endif; ?>
                                                        <th>Purchase Points</th>
                                                        <th>Daily Login</th>
                                                        <th class="advance-bonus">Advance Bonus</th>
                                                        <th class="deducted-points">Purchase Returns</th>
                                                        <th class="total-points">Total Reward Points</th>
                                                        <th class="sales-points">Sales Points</th>
                                                        <th class="deducted-points">Sales Returns</th>
                                                    </tr>
                                                </thead>
											
                                                <tbody>
<?php
$sn = 0;
if (!empty($combined_users)) {
    foreach ($combined_users as $user_data) {

        $user_details = $user_data['user_details'];
        if (!$user_details) continue;

        $sn++;
        $user_name    = strtoupper(htmlspecialchars($user_details['name'],          ENT_QUOTES, 'UTF-8'));
        $user_mobile  = htmlspecialchars($user_details['mobile_number'],            ENT_QUOTES, 'UTF-8');
        $district_name = htmlspecialchars($user_details['district_name'],           ENT_QUOTES, 'UTF-8');
        $category     = htmlspecialchars($user_data['category'],                    ENT_QUOTES, 'UTF-8');

        $product_purchase_points = $user_data['product_purchase_points'];
        $daily_login_pts         = $user_data['daily_login_points'];
        $advance_bonus_pts       = $user_data['advance_bonus_points'];
        $deducted_points         = $user_data['deducted_points'];
        $total_purchase_points   = $user_data['total_purchase_points'];
        $sales_points_final      = $user_data['total_sales_points'];
        $sales_deducted          = $user_data['total_sales_deducted'];
?>
                                                    <tr>
                                                        <td><?php echo $sn; ?></td>
                                                        <td>
                                                            <?php echo $user_name; ?><br/>
                                                            <small><?php echo $user_mobile; ?></small>
                                                        </td>
                                                        <td><?php echo $district_name; ?></td>
                                                        <?php if($getinvuser === "stockiest"): ?>
                                                        <td><?php echo $category; ?></td>
                                                        <?php endif; ?>
                                                        <td><?php echo number_format($product_purchase_points, 2); ?></td>
                                                        <td><?php echo number_format($daily_login_pts, 2); ?></td>
                                                        <td class="advance-bonus">
                                                            <?php echo number_format($advance_bonus_pts, 2); ?>
                                                        </td>
                                                        <td class="deducted-points">
                                                            <?php echo $deducted_points > 0
                                                                ? '-' . number_format($deducted_points, 2)
                                                                : '0.00'; ?>
                                                        </td>
                                                        <td class="total-points">
                                                            <strong><?php echo number_format($total_purchase_points, 2); ?></strong>
                                                        </td>
                                                        <td class="sales-points"><?php echo number_format($sales_points_final, 2); ?></td>
                                                        <td class="deducted-points">
                                                            <?php echo $sales_deducted > 0
                                                                ? '-' . number_format($sales_deducted, 2)
                                                                : '0.00'; ?>
                                                        </td>
                                                    </tr>
<?php
    }
} else {
    $colspan = 10;
    if ($getinvuser === "stockiest") $colspan++;
    echo "<tr><td colspan='{$colspan}' class='text-center'>No data found for selected period</td></tr>";
}
?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
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
    <script src="../../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/datatables.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form     = document.querySelector('form');
        const fromDate = document.getElementById('frdate');
        const toDate   = document.getElementById('todate');

        if (form && fromDate && toDate) {
            form.addEventListener('submit', function(e) {
                if (new Date(fromDate.value) > new Date(toDate.value)) {
                    e.preventDefault();
                    alert('From Date cannot be later than To Date');
                    fromDate.focus();
                }
            });
        }
    });
    </script>
</body>
</html>