<?php
include("checksession.php");
include("config.php");
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");

$today_date       = date("Y-m-d");
$today_number     = date("d");
$Yesterday_date   = date("Y-m-d", strtotime("-1 day", strtotime($today_date)));
$start_date       = date("Y-m-01");
$endDate          = date('Y-m-t');
$lastmonth_date_start = date("Y-m-d", strtotime("-1 month", strtotime($today_date)));
$lastmonth_date_end   = $today_date;

$advBalance = 0;

// ---- Sales Report (user_invoice) ----
function tp_sales_count($db, $utype, $uid, $from, $to) {
    $q = "SELECT COUNT(*) as cnt, COALESCE(SUM(sub_total),0) as subtotal, COALESCE(SUM(total),0) as total FROM user_invoice WHERE date BETWEEN '$from' AND '$to' AND from_user_type='$utype' AND from_user_id='$uid' AND sub_total>0";
    $r = mysqli_fetch_array(mysqli_query($db, $q));
    return [$r['cnt'], $r['subtotal'], $r['total']];
}
function tp_sales_qty($db, $utype, $uid, $from, $to) {
    $q = "SELECT COALESCE(SUM(qty),0) as qty FROM user_invoice_items WHERE date BETWEEN '$from' AND '$to' AND from_user_type='$utype' AND from_user_id='$uid'";
    $r = mysqli_fetch_array(mysqli_query($db, $q));
    return $r['qty'];
}

$ut = $Login_user_TYPEvl;
$ui = $Login_user_IDvl;

[$today_invoice_count, , $today_total_amount] = tp_sales_count($db_conn, $ut, $ui, $today_date, $today_date);
$today_total_qty = tp_sales_qty($db_conn, $ut, $ui, $today_date, $today_date);

[$yesterday_invoice_count, , $yesterday_total_amount] = tp_sales_count($db_conn, $ut, $ui, $Yesterday_date, $Yesterday_date);
$yesterday_total_qty = tp_sales_qty($db_conn, $ut, $ui, $Yesterday_date, $Yesterday_date);

[$thismonth_invoice_count, , $thismonth_total_amount] = tp_sales_count($db_conn, $ut, $ui, $start_date, $endDate);
$thismonth_total_qty = tp_sales_qty($db_conn, $ut, $ui, $start_date, $endDate);

[$lastmonth_invoice_count, , $lastmonth_total_amount] = tp_sales_count($db_conn, $ut, $ui, $lastmonth_date_start, $lastmonth_date_end);
$lastmonth_total_qty = tp_sales_qty($db_conn, $ut, $ui, $lastmonth_date_start, $lastmonth_date_end);

// ---- Shop Sales ----
function tp_shop_sales_count($db, $uid, $from, $to) {
    $q = "SELECT COUNT(*) as cnt, COALESCE(SUM(sub_total),0) as subtotal, COALESCE(SUM(total),0) as total FROM user_invoice WHERE date BETWEEN '$from' AND '$to' AND from_user_id='$uid' AND to_user_type='shop' AND sub_total>0";
    $r = mysqli_fetch_array(mysqli_query($db, $q));
    return [$r['cnt'], $r['subtotal'], $r['total']];
}
function tp_shop_sales_qty($db, $uid, $from, $to) {
    $q = "SELECT COALESCE(SUM(qty),0) as qty FROM user_invoice_items WHERE date BETWEEN '$from' AND '$to' AND from_user_id='$uid'";
    $r = mysqli_fetch_array(mysqli_query($db, $q));
    return $r['qty'];
}

[$today_invoice_count_shop, , $today_total_amount_shop] = tp_shop_sales_count($db_conn, $ui, $today_date, $today_date);
$today_total_qty_shop = tp_shop_sales_qty($db_conn, $ui, $today_date, $today_date);

[$yesterday_invoice_count_shop, , $yesterday_total_amount_shop] = tp_shop_sales_count($db_conn, $ui, $Yesterday_date, $Yesterday_date);
$yesterday_total_qty_shop = tp_shop_sales_qty($db_conn, $ui, $Yesterday_date, $Yesterday_date);

[$thismonth_invoice_count_shop, , $thismonth_total_amount_shop] = tp_shop_sales_count($db_conn, $ui, $start_date, $endDate);
$thismonth_total_qty_shop = tp_shop_sales_qty($db_conn, $ui, $start_date, $endDate);

[$lastmonth_invoice_count_shop, , $lastmonth_total_amount_shop] = tp_shop_sales_count($db_conn, $ui, $lastmonth_date_start, $lastmonth_date_end);
$lastmonth_total_qty_shop = tp_shop_sales_qty($db_conn, $ui, $lastmonth_date_start, $lastmonth_date_end);

// ---- Onboarded Counts ----
$count_shop_today = mysqli_fetch_array(mysqli_query($db_conn, "SELECT COUNT(*) as c FROM shop WHERE DATE(created_at)='$today_date' AND onboard_userID='$ui' AND onboard_userTYPE='territory_partner'"))['c'] ?? 0;
$count_shop_month = mysqli_fetch_array(mysqli_query($db_conn, "SELECT COUNT(*) as c FROM shop WHERE DATE(created_at) BETWEEN '$start_date' AND '$endDate' AND onboard_userID='$ui' AND onboard_userTYPE='territory_partner'"))['c'] ?? 0;
$countshp_Overall = mysqli_fetch_array(mysqli_query($db_conn, "SELECT COUNT(*) as c FROM shop WHERE onboard_userID='$ui' AND onboard_userTYPE='territory_partner'"))['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Report : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
    #dashanch { color:#000 !important; }
    #dashanch:hover { color:#1a06a6 !important; }
    #reportdash th { font-size:13px; font-weight:600; }
    #reportdash td { font-weight:700; font-size:14px; }
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
                <div class="container">
                    <div class="row">
                        <div class="col">
                            <div class="page-description" style="margin-left:-25px;">
                                <h1>Report</h1>
                            </div>
                        </div>
                    </div>

                    <h3><b>Sales</b></h3>
                    <div class="row">
                        <?php
                        $sales_periods = [
                            ['Today', $today_date, $today_date, $today_invoice_count, $today_total_qty, $today_total_amount],
                            ['Yesterday', $Yesterday_date, $Yesterday_date, $yesterday_invoice_count, $yesterday_total_qty, $yesterday_total_amount],
                            ['This Month', $start_date, $endDate, $thismonth_invoice_count, $thismonth_total_qty, $thismonth_total_amount],
                            ['Last Month till date', $lastmonth_date_start, $lastmonth_date_end, $lastmonth_invoice_count, $lastmonth_total_qty, $lastmonth_total_amount],
                        ];
                        foreach ($sales_periods as $sp) { ?>
                        <div class="col-xl-3">
                            <div class="card widget widget-stats">
                                <div class="card-body">
                                    <div class="widget-stats-container d-flex">
                                        <div class="widget-stats-content flex-fill">
                                            <span class="widget-stats-title"><?php echo $sp[0]; ?></span>
                                            <table id="reportdash">
                                                <tr><th>Invoice&nbsp;Count</th><td>:&nbsp;<?php echo $sp[3]; ?></td></tr>
                                                <tr><th>Product&nbsp;Qty</th><td>:&nbsp;<?php echo $sp[4]; ?></td></tr>
                                                <tr><th>Total&nbsp;Amount</th><td>:&nbsp;&#x20B9;<?php echo number_format($sp[5], 2); ?></td></tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>

                    <h3><b>Shop Sales</b></h3>
                    <div class="row">
                        <?php
                        $shop_periods = [
                            ['Today', $today_invoice_count_shop, $today_total_qty_shop, $today_total_amount_shop],
                            ['Yesterday', $yesterday_invoice_count_shop, $yesterday_total_qty_shop, $yesterday_total_amount_shop],
                            ['This Month', $thismonth_invoice_count_shop, $thismonth_total_qty_shop, $thismonth_total_amount_shop],
                            ['Last Month till date', $lastmonth_invoice_count_shop, $lastmonth_total_qty_shop, $lastmonth_total_amount_shop],
                        ];
                        foreach ($shop_periods as $sp) { ?>
                        <div class="col-xl-3">
                            <div class="card widget widget-stats">
                                <div class="card-body">
                                    <div class="widget-stats-container d-flex">
                                        <div class="widget-stats-content flex-fill">
                                            <span class="widget-stats-title"><?php echo $sp[0]; ?></span>
                                            <table id="reportdash">
                                                <tr><th>Invoice&nbsp;Count</th><td>:&nbsp;<?php echo $sp[1]; ?></td></tr>
                                                <tr><th>Product&nbsp;Qty</th><td>:&nbsp;<?php echo $sp[2]; ?></td></tr>
                                                <tr><th>Total&nbsp;Amount</th><td>:&nbsp;&#x20B9;<?php echo number_format($sp[3], 2); ?></td></tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>

                    <h3><b>Onboarded Count</b></h3>
                    <div class="row">
                        <div class="col-xl-3">
                            <div class="card widget widget-stats">
                                <div class="card-body">
                                    <div class="widget-stats-container d-flex">
                                        <div class="widget-stats-content flex-fill">
                                            <span class="widget-stats-title">Shop (Retailers)</span>
                                            <table id="reportdash">
                                                <tr><th>Today</th><td>:&nbsp;<?php echo $count_shop_today; ?></td></tr>
                                                <tr><th>This&nbsp;Month</th><td>:&nbsp;<?php echo $count_shop_month; ?></td></tr>
                                                <tr><th>Total</th><td>:&nbsp;<?php echo $countshp_Overall; ?></td></tr>
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
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
