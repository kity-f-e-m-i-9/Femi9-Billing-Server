<?php include("checksession.php"); require_once("include/GodownAccess.php");
require_once("include/PermissionCheck.php"); requirePermission('products');
error_reporting(0);

// ── Datewise stock movement for every channel EXCEPT company, combined into
// one total — the mirror image of overstock_datewise.php's Company Profile
// view. Deliberately excludes:
//   - "Input Stock Qty" / "Sent Qty" (input_stock, demofreedamage,
//     internal_transfer) — these are all keyed to a company_godown id, there
//     is no equivalent concept for a TP/SS/Stockist/Distributor's own stock.
//   - OT-Sales and SALES-3 (company→TP tp_invoices) — both are inherently
//     company-outbound transactions, already covered by Company Overall
//     Stock, not a "users" concept.
// So only Sales Qty and Return Qty are shown. "Users" here = territory
// partner, super stockist, stockist, super distributor, distributor — every
// from_user_type/user_type value that appears in the sales tables besides
// 'company' (see user_invoice.from_user_type / invoice.user_type).
$user_channel_types = ['territory_partner', 'super_stockiest', 'stockiest', 'super_distributor', 'distributor'];
$in_list = "'" . implode("','", array_map([$db_conn, 'real_escape_string'], $user_channel_types)) . "'";

$get_from_date = $_REQUEST['frdate'] ?? date('Y-m-d', strtotime('-6 days'));
$get_to_date   = $_REQUEST['todate'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Overall Stock - Users : <?php echo $business_name;?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
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
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">

        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>

        <div class="app-container">

          <?php include("app-header.php");?>

            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td>Overall Stock - Users</td>
                                                <td><a href="overall-stock">&#8592; Company Overall Stock</a></td>
                                            </tr>
                                        </table>
                                    </h1>
                                    <h5><?=date("d-m-Y",strtotime($get_from_date));?> (to) <?=date("d-m-Y",strtotime($get_to_date));?>
                                    <br/>Territory Partner + Super Stockist + Stockist + Super Distributor + Distributor, combined
                                    </h5>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <form method="get" action="overall-stock-users.php">
                                            <div class="overviewcontainar">
                                                <div id="searchleftcont">
                                                    <label class="form-label">From Date</label>
                                                    <input type="date" required name="frdate" value="<?=$get_from_date;?>" class="form-control">
                                                </div>
                                                <div id="searchleftcont">
                                                    <label class="form-label">To Date</label>
                                                    <input type="date" required name="todate" value="<?=$get_to_date;?>" class="form-control">
                                                </div>
                                                <div id="searchbuttoncont">
                                                    <button type="submit" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
                                                </div>
                                            </div>
                                            <div style="clear:both;"></div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <div style="background:#fff;overflow:scroll;width:100%;">

                                        <?php
                                        $startTime = strtotime($get_from_date);
                                        $endTime   = strtotime($get_to_date);
                                        for ($i = $startTime; $i <= $endTime; $i += 86400):
                                            $report_date = date('Y-m-d', $i);
                                        ?>
                                        <h1 align="center"><?=date("d-m-Y", strtotime($report_date));?></h1>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Product Name</th>
                                                    <th style="text-align:right;">Sales Qty</th>
                                                    <th style="text-align:right;">Return Qty</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $select_productDetils = "select * from products order by id asc";
                                                $Fetch_productDetils = mysqli_query($db_conn, $select_productDetils);
                                                while ($Result_productDetils = mysqli_fetch_array($Fetch_productDetils)):
                                                    $report_prid = $Result_productDetils['id'];

                                                    // SALES-1 — user_invoice_items, non-company sellers only.
                                                    $q1 = mysqli_query($db_conn, "select sum(qty) from user_invoice_items where date='$report_date' and pr_id='$report_prid' and from_user_type IN ($in_list)");
                                                    $r1 = mysqli_fetch_array($q1);
                                                    $sls1 = $r1[0] ?? 0;

                                                    // SALES-2 — invoice_items, non-company sellers only.
                                                    $q2 = mysqli_query($db_conn, "select sum(qty) from invoice_items where date='$report_date' and pr_id='$report_prid' and user_type IN ($in_list)");
                                                    $r2 = mysqli_fetch_array($q2);
                                                    $sls2 = $r2[0] ?? 0;

                                                    $total_sales = (int)$sls1 + (int)$sls2;

                                                    // RETURN — user_return_stock_items, non-company recipients only.
                                                    $q3 = mysqli_query($db_conn, "select sum(qty) from user_return_stock_items where date='$report_date' and prid='$report_prid' and to_usertype IN ($in_list)");
                                                    $r3 = mysqli_fetch_array($q3);
                                                    $total_return = (int)($r3[0] ?? 0);

                                                    if ($total_sales == 0 && $total_return == 0) continue;
                                                ?>
                                                <tr>
                                                    <td><?php echo $Result_productDetils["productName"];?></td>
                                                    <td align="right"><?php echo $total_sales;?></td>
                                                    <td align="right"><?php echo $total_return;?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                        <?php endfor; ?>

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
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>

</html>
