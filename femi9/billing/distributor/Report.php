<?php include("checksession.php");
include("config.php"); 
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");

$today_date=date("Y-m-d");
$today_number=date("d");
$Yesterday_date=date ("Y-m-d", strtotime("-1 day", strtotime($today_date)));

$start_date=date("Y-m-01");
$endDate = date('Y-m-t');

$lastmonth_only=date ("Y-m", strtotime("-1 month", strtotime($start_date)));
//$lastmonth_date_start="".$lastmonth_only."-01";
$lastmonth_date_start=date ("Y-m-d", strtotime("-1 month", strtotime($today_date)));
$lastmonth_date_end=$today_date;

//UPDATE RECEIPT USERS
$select_nonusers="select * from receipt where from_user_type='' and from_user_id=''";
$fetch_nonusers=mysqli_query($db_conn,$select_nonusers);
while($result_nonusers=mysqli_fetch_array($fetch_nonusers))
{
	$nonuser_invid=$result_nonusers['inv_id'];
	$nonuser_tousertype=$result_nonusers['to_user_type'];
	
	if($nonuser_invid!=NULL)
	{
$select_nonusers232="select * from user_invoice where inv_id='$nonuser_invid'";
$fetch_nonusers232=mysqli_query($db_conn,$select_nonusers232);
$result_nonusers232=mysqli_fetch_array($fetch_nonusers232);

$get_from_usertype=$result_nonusers232['from_user_type'];
$get_from_userid=$result_nonusers232['from_user_id'];
$get_to_usertype=$result_nonusers232['to_user_type'];
$get_to_userid=$result_nonusers232['to_user_id'];

$updatenonusers="update receipt set from_user_type='$get_from_usertype',from_user_id='$get_from_userid',to_user_type='$get_to_usertype',to_user_id='$get_to_userid' where inv_id='$nonuser_invid'";
mysqli_query($db_conn,$updatenonusers);
	}

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Responsive Admin Dashboard Template">
    <meta name="keywords" content="admin,dashboard">
    <meta name="author" content="stacks">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    
    <!-- Title -->
    <title>Report : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">

    
    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
	
	<style type="text/css">
	#dashanch{color:#000 !important;}
	#dashanch:hover{color:#1a06a6 !important;}
	#reportdash th{font-size:13px;font-weight:600;}
	#reportdash td{font-weight:700;font-size:14px;}
	</style>
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
                    <div class="container">
                        <div class="row">
                            <div class="col">
                                <div class="page-description" style="margin-left:-25px;">
                                    <h1>Report</h1>
                                </div>
                            </div>
                        </div>
						
						<!--------------------------------------------------------------------->
						<!--------------------------------------------------------------------->
						<?php include("report_company_sales.php"); ?>
						<h3><b>Sales</b></h3>
						<div class="row">
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
						<a href="overview-report1?frdate=<?=$today_date;?>&&todate=<?=$today_date;?>&&lable=1&&rptlable=1&&out1=<?=$today_invoice_count;?>&&out2=<?=$today_total_qty;?>&&out3=<?=$today_total_amount;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Today</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$today_invoice_count;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$today_total_qty;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$today_total_amount;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report1?frdate=<?=$Yesterday_date;?>&&todate=<?=$Yesterday_date;?>&&lable=2&&rptlable=1&&out1=<?=$yesterday_invoice_count;?>&&out2=<?=$yesterday_total_qty;?>&&out3=<?=$yesterday_total_amount;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Yesterday</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$yesterday_invoice_count;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$yesterday_total_qty;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$yesterday_total_amount;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										
										<a href="overview-report1?frdate=<?=$start_date;?>&&todate=<?=$endDate;?>&&lable=3&&rptlable=1&&out1=<?=$thismonth_invoice_count;?>&&out2=<?=$thismonth_total_qty;?>&&out3=<?=$thismonth_total_amount;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">This Month</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$thismonth_invoice_count;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$thismonth_total_qty;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$thismonth_total_amount;?></td>
												</tr>
												</table>
                                            </div>
											</a>
					
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report1?frdate=<?=$lastmonth_date_start;?>&&todate=<?=$lastmonth_date_end;?>&&lable=4&&rptlable=1&&out1=<?=$lastmonth_invoice_count;?>&&out2=<?=$lastmonth_total_qty;?>&&out3=<?=$lastmonth_total_amount;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Last Month till date</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$lastmonth_invoice_count;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$lastmonth_total_qty;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$lastmonth_total_amount;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
						
						
				<!--------------------------------------------------------------------->
				<!--------------------------------------------------------------------->
				<?php include("report_shop_purchase.php"); ?>
						<h3><b>Shop Sales</b></h3>
						<div class="row">
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report2?frdate=<?=$today_date;?>&&todate=<?=$today_date;?>&&lable=1&&rptlable=1&&out1=<?=$today_invoice_count_shop;?>&&out2=<?=$today_total_qty_shop;?>&&out3=<?=$today_total_amount_shop;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Today</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$today_invoice_count_shop;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$today_total_qty_shop;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$today_total_amount_shop;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report2?frdate=<?=$Yesterday_date;?>&&todate=<?=$Yesterday_date;?>&&lable=2&&rptlable=1&&out1=<?=$yesterday_invoice_count_shop;?>&&out2=<?=$yesterday_total_qty_shop;?>&&out3=<?=$yesterday_total_amount_shop;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Yesterday</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$yesterday_invoice_count_shop;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$yesterday_total_qty_shop;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$yesterday_total_amount_shop;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report2?frdate=<?=$start_date;?>&&todate=<?=$endDate;?>&&lable=3&&rptlable=1&&out1=<?=$thismonth_invoice_count_shop;?>&&out2=<?=$thismonth_total_qty_shop;?>&&out3=<?=$thismonth_total_amount_shop;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">This Month</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$thismonth_invoice_count_shop;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$thismonth_total_qty_shop;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$thismonth_total_amount_shop;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report2?frdate=<?=$lastmonth_date_start;?>&&todate=<?=$lastmonth_date_end;?>&&lable=4&&rptlable=1&&out1=<?=$lastmonth_invoice_count_shop;?>&&out2=<?=$lastmonth_total_qty_shop;?>&&out3=<?=$lastmonth_total_amount_shop;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Last Month till date</span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$lastmonth_invoice_count_shop;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$lastmonth_total_qty_shop;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$lastmonth_total_amount_shop;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
						
				
				<!--------------------------------------------------------------------->
				<!--------------------------------------------------------------------->
				<?php include("report_onboarded.php"); ?>
						<h3><b>Onboarded Count</b></h3>
						<div class="row">
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="overview-report4?frdate=<?=$start_date;?>&&todate=<?=$endDate;?>&&today=<?=$today_date;?>&&lable=4&&rptlable=1&&out1=<?=$count_shop_today;?>&&out2=<?=$count_shop_month;?>&&out3=<?=$countshp_Overall;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Shop (Retailers)</span>
												<table id="reportdash">
												<tr>
												<th>Today</th>
												<td>:&nbsp;<?=$count_shop_today;?></td>
												</tr>
												<tr>
												<th>This&nbsp;Month</th>
												<td>:&nbsp;<?=$count_shop_month;?></td>
												</tr>
												<tr>
												<th>Total</th>
												<td>:&nbsp;<?=$countshp_Overall;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
						
						
						
						
						<!--------------------------------------------------------------------->
						<!--------------------------------------------------------------------->
						<?php include("report_purchase_order.php"); 
						$poorder_link="overview-report5";
						?>
						<h3><b>Purchase Order</b></h3>
						<div class="row">
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
						<a href="<?=$poorder_link;?>?frdate=<?=$today_date;?>&&todate=<?=$today_date;?>&&lable=1&&rptlable=1&&out1=<?=$today_invoice_count_purchaseorder;?>&&out2=<?=$today_total_qty_purchaseorder;?>&&out3=<?=$today_total_amount_purchaseorder;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Today</span>
												<table id="reportdash">
												<tr>
												<th>Count</th>
												<td>:&nbsp;<?=$today_invoice_count_purchaseorder;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$today_total_qty_purchaseorder;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;<?=$today_total_amount_purchaseorder;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="<?=$poorder_link;?>?frdate=<?=$Yesterday_date;?>&&todate=<?=$Yesterday_date;?>&&lable=2&&rptlable=1&&out1=<?=$yesterday_invoice_count_purchaseorder;?>&&out2=<?=$yesterday_total_qty_purchaseorder;?>&&out3=<?=$yesterday_total_amount_purchaseorder;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Yesterday</span>
												<table id="reportdash">
												<tr>
												<th>Count</th>
												<td>:&nbsp;<?=$yesterday_invoice_count_purchaseorder;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$yesterday_total_qty_purchaseorder;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;<?=$yesterday_total_amount_purchaseorder;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										
										<a href="<?=$poorder_link;?>?frdate=<?=$start_date;?>&&todate=<?=$endDate;?>&&lable=3&&rptlable=1&&out1=<?=$thismonth_invoice_count_purchaseorder;?>&&out2=<?=$thismonth_total_qty_purchaseorder;?>&&out3=<?=$thismonth_total_amount_purchaseorder;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">This Month</span>
												<table id="reportdash">
												<tr>
												<th>Count</th>
												<td>:&nbsp;<?=$thismonth_invoice_count_purchaseorder;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$thismonth_total_qty_purchaseorder;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;<?=$thismonth_total_amount_purchaseorder;?></td>
												</tr>
												</table>
                                            </div>
											</a>
					
                                        </div>
                                    </div>
                                </div>
                            </div>
							
							
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
										<a href="<?=$poorder_link;?>?frdate=<?=$lastmonth_date_start;?>&&todate=<?=$lastmonth_date_end;?>&&lable=4&&rptlable=1&&out1=<?=$lastmonth_invoice_count_purchaseorder;?>&&out2=<?=$lastmonth_total_qty_purchaseorder;?>&&out3=<?=$lastmonth_total_amount_purchaseorder;?>" style="text-decoration:none;" id="dashanch">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Last Month till date</span>
												<table id="reportdash">
												<tr>
												<th>Count</th>
												<td>:&nbsp;<?=$lastmonth_invoice_count_purchaseorder;?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$lastmonth_total_qty_purchaseorder;?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;<?=$lastmonth_total_amount_purchaseorder;?></td>
												</tr>
												</table>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
						
						
						
						

                        <!--------------------------------------------------------->
						<!-------------OUTSTANDING---------------------->
						<!--------------------------------------------------------->
						<?php include("report_outstanding.php");?>
						<h3><b>Outstanding</b></h3>
						<div class="row">
							<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-icon widget-stats-icon-success">
                                                <i class="material-icons-outlined">person</i>
                                            </div>
											
						<a href="overview_outstanding?modelval=usr4&&out1=<?=base64_encode($Total_OUTST_VLSHOP_Show);?>" style="text-decoration:none;">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title">Shop</span>
                                                <span class="widget-stats-amount"><?=$Total_OUTST_VLSHOP_Show;?></span>
                                            </div>
											</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
						
						<!-------------CASH------------------->
						<?php //include("report_pge_cash_data.php");?>
						<?php //include("report_pge_cash.php");?>
						
						<!----------------------------------------->
						<?php include("report_pge_competitor_data.php");?>
						<h3><b>Competetion Stock Report</b></h3>
						<?php include("report_pge_competitor.php");?>
						
						<!------------------------------------------>
						<?php /* include("report_pge_outlet_data.php");?>
						<h3><b>Outlet Visit</b></h3>
						<?php include("report_pge_outlet.php"); */ ?>
						
						<!------------------------------------------>
						<?php /* include("report_pge_notpurchased_data.php");?>
						<h3><b>ONBOARDED OLS BUT NOT PURCHASED</b></h3>
						<?php include("report_pge_notpurchased.php"); */ ?>
						
						<!------------------------------------------>
						<?php /* include("report_pge_poraised_data.php");?>
						<h3><b>PO RAISED BUT PRODUCT NOT DELIVERED</b></h3>
						<?php include("report_pge_poraised.php"); */ ?>
						
						<!------------------------------------------>
						<?php /* include("report_pge_RETURN_data.php");?>
						<h3><b>RETURN STOCK REPORTS</b></h3>
						<?php include("report_pge_RETURN.php"); */ ?>
						
					<!--------------------end***--------------------------------------->

						
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/dashboard.js"></script>
</body>
</html>