<?php
/**
 * Reward Points (Sales) - Territory Partner
 */
include("checksession.php");
include("config.php");
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
date_default_timezone_set("Asia/Kolkata");

$advBalance = 0;

function validateDate_tpsls(?string $date, string $default): string {
    if (empty($date)) return $default;
    $timestamp = strtotime($date);
    return ($timestamp === false) ? $default : date('Y-m-d', $timestamp);
}

$numberOfDays     = (int)date('t');
$current_month    = date('m');
$default_from     = date("Y-{$current_month}-01");
$default_to       = date("Y-{$current_month}-{$numberOfDays}");

$current_from_date = validateDate_tpsls($_REQUEST['frdate'] ?? null, $default_from);
$current_to_date   = validateDate_tpsls($_REQUEST['todate'] ?? null, $default_to);

if (strtotime($current_from_date) > strtotime($current_to_date)) {
    [$current_from_date, $current_to_date] = [$current_to_date, $current_from_date];
}

$safe_business_name = htmlspecialchars($business_name ?? 'Femi9', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reward Points (Sales) : <?php echo $safe_business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
    <style>
    #overflowon { width:100%; overflow-x:auto !important; height:100%; overflow-y:hidden; }
    .points-card { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); border-radius:15px; padding:30px; color:white; box-shadow:0 10px 30px rgba(102,126,234,.3); margin-bottom:20px; }
    .points-value { font-size:48px; font-weight:700; margin:15px 0; }
    .points-label { font-size:14px; opacity:.9; text-transform:uppercase; letter-spacing:1px; }
    .breakdown-card { background:white; border-radius:15px; padding:25px; box-shadow:0 4px 15px rgba(0,0,0,.1); }
    .breakdown-item { display:flex; justify-content:space-between; padding:15px 0; border-bottom:1px solid #f0f0f0; }
    .breakdown-item:last-child { border-bottom:none; margin-top:10px; padding:20px; background:#f8f9fa; border-radius:10px; }
    .breakdown-label { color:#6c757d; font-weight:500; }
    .breakdown-value { font-weight:700; font-size:18px; color:#059669; }
    .breakdown-value.negative { color:#dc2626; }
    .breakdown-value.total { font-size:24px; color:#2563eb; }
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
                                <h1><table class="headertble"><tr><td>Reward Points (Sales)</td></tr></table></h1>
                            </div>
                        </div>
                    </div>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
                        <div class="overviewcontainar">
                            <div id="searchleftcont">
                                <label class="form-label" for="frdate">From Date</label>
                                <input type="date" required name="frdate" id="frdate" value="<?php echo htmlspecialchars($current_from_date); ?>" class="form-control" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div id="searchleftcont">
                                <label class="form-label" for="todate">To Date</label>
                                <input type="date" required name="todate" id="todate" value="<?php echo htmlspecialchars($current_to_date); ?>" class="form-control" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div id="searchbuttoncont">
                                <button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
                            </div>
                        </div>
                        <div style="clear:both;"></div><br/>
                    </form>
<?php
$user_type = mysqli_real_escape_string($db_conn, $Login_user_TYPEvl);
$user_id   = mysqli_real_escape_string($db_conn, $Login_user_IDvl);

function safeQuery_tpsls(mysqli $conn, string $sql): float {
    try {
        $res = mysqli_query($conn, $sql);
        if (!$res) return 0.0;
        $row = mysqli_fetch_array($res);
        return (float)($row[0] ?? 0);
    } catch (\Throwable $e) {
        error_log('[TP reward-points-sls] Query error: ' . $e->getMessage());
        return 0.0;
    }
}

// 1. Shop (user-to-user) Sales Points — invoice sub_total / 100
$total_points_users = safeQuery_tpsls($db_conn,
    "SELECT COALESCE(SUM(sub_total) / 100, 0) FROM user_invoice
     WHERE from_user_type = '$user_type' AND from_user_id = '$user_id'
     AND date BETWEEN '$current_from_date' AND '$current_to_date'"
);

// 2. Customer Sales Points — invoice sub_total / 100
$total_points_customers = safeQuery_tpsls($db_conn,
    "SELECT COALESCE(SUM(sub_total) / 100, 0) FROM invoice
     WHERE user_type = '$user_type' AND user_id = '$user_id'
     AND date BETWEEN '$current_from_date' AND '$current_to_date'"
);

$total_combined_points = $total_points_users + $total_points_customers;

// 3. Return Deductions (shop invoices)
$user_return_points = safeQuery_tpsls($db_conn,
    "SELECT COALESCE(SUM(r.subtotal) / 100, 0) FROM user_return_stock_items r
     WHERE r.invnumber IN (
         SELECT inv_id FROM user_invoice
         WHERE from_user_type = '$user_type' AND from_user_id = '$user_id'
         AND date BETWEEN '$current_from_date' AND '$current_to_date'
     ) AND r.to_usertype = '$user_type' AND r.to_userid = '$user_id'"
);

$check_table = "SHOW TABLES LIKE 'return_stock_items'";
$table_exists = mysqli_query($db_conn, $check_table);
if (mysqli_num_rows($table_exists) > 0) {
    $customer_return_points = safeQuery_tpsls($db_conn,
        "SELECT COALESCE(SUM(r.subtotal) / 100, 0) FROM return_stock_items r
         WHERE r.invnumber IN (
             SELECT inv_id FROM invoice
             WHERE user_type = '$user_type' AND user_id = '$user_id'
             AND date BETWEEN '$current_from_date' AND '$current_to_date'
         ) AND r.user_type = '$user_type' AND r.user_id = '$user_id'"
    );
} else {
    $customer_return_points = 0;
}

$total_points_returns = $user_return_points + $customer_return_points;
$pointsdeffer = $total_combined_points - $total_points_returns;
$PointShow = ($pointsdeffer > 0) ? number_format($pointsdeffer, 2) : "0.00";

$formatted_gross           = number_format($total_combined_points, 2);
$formatted_returns         = number_format($total_points_returns, 2);
$formatted_user_sales      = number_format($total_points_users, 2);
$formatted_customer_sales  = number_format($total_points_customers, 2);

$display_from = date('d M', strtotime($current_from_date));
$display_to   = date('d M Y', strtotime($current_to_date));
?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="points-card">
                                <div class="points-label">Total Sales Points</div>
                                <div class="points-value"><?php echo $PointShow; ?></div>
                                <div style="opacity:.8;font-size:13px;"><?php echo $display_from; ?> - <?php echo $display_to; ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="breakdown-card">
                                <h6 style="color:#111827;font-weight:600;margin-bottom:20px;">
                                    <i class="material-icons" style="vertical-align:middle;color:#2563eb;">analytics</i> Points Breakdown
                                </h6>
                                <div class="breakdown-item">
                                    <span class="breakdown-label"><i class="material-icons" style="font-size:18px;vertical-align:middle;color:#059669;">people</i> User-to-User Sales</span>
                                    <span class="breakdown-value">+<?php echo $formatted_user_sales; ?></span>
                                </div>
                                <div class="breakdown-item">
                                    <span class="breakdown-label"><i class="material-icons" style="font-size:18px;vertical-align:middle;color:#059669;">shopping_cart</i> Customer Sales</span>
                                    <span class="breakdown-value">+<?php echo $formatted_customer_sales; ?></span>
                                </div>
                                <?php if ($total_points_returns > 0): ?>
                                <div class="breakdown-item">
                                    <span class="breakdown-label"><i class="material-icons" style="font-size:18px;vertical-align:middle;color:#dc2626;">remove_circle</i> Returns Deducted</span>
                                    <span class="breakdown-value negative">-<?php echo $formatted_returns; ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="breakdown-item">
                                    <span class="breakdown-label"><i class="material-icons" style="font-size:20px;vertical-align:middle;color:#2563eb;">emoji_events</i> <strong>Net Sales Points</strong></span>
                                    <span class="breakdown-value total"><?php echo $PointShow; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row" style="margin-top:20px;">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div id="overflowon">
                                        <table id="datatable1" class="display" style="width:100%;">
                                            <thead><tr><th>S.No</th><th>Description</th><th>Points</th></tr></thead>
                                            <tbody>
                                                <tr><td>1</td><td>User-to-User Sales Points</td><td style="color:#059669;font-weight:600;">+<?php echo $formatted_user_sales; ?></td></tr>
                                                <tr><td>2</td><td>Customer Sales Points</td><td style="color:#059669;font-weight:600;">+<?php echo $formatted_customer_sales; ?></td></tr>
                                                <tr><td>3</td><td>Gross Sales Points</td><td style="color:#2563eb;font-weight:600;"><?php echo $formatted_gross; ?></td></tr>
                                                <?php if ($total_points_returns > 0): ?>
                                                <tr><td>4</td><td>Returns Deducted (Based on Invoice Date)</td><td style="color:#dc2626;font-weight:600;">-<?php echo $formatted_returns; ?></td></tr>
                                                <?php endif; ?>
                                                <tr style="background:#eff6ff;">
                                                    <td><strong><?php echo $total_points_returns > 0 ? '5' : '4'; ?></strong></td>
                                                    <td><strong>Net Sales Points</strong></td>
                                                    <td style="color:#2563eb;font-weight:700;font-size:18px;"><?php echo $PointShow; ?></td>
                                                </tr>
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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
</body>
</html>
