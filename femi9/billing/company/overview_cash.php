<?php include("checksession.php");
include("config.php");
error_reporting(0);

$Report_LABLE="Cash Report";

if($_REQUEST['lable']==1 )
{$DISPLAY_LABLE="Super Stockist";
$usertypevl="super_stockiest";}

else if($_REQUEST['lable']==2)
{$DISPLAY_LABLE="Stockist";
$usertypevl="stockiest";}

else if($_REQUEST['lable']==4)
{$DISPLAY_LABLE="Super Distributor";
$usertypevl="super_distributor";}

else{$DISPLAY_LABLE="Distributor";
$usertypevl="distributor";}

$start_date=$_REQUEST['frdate'];
$endDate=$_REQUEST['todate'];	
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?=$Report_LABLE;?>  : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">


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
									<td><?=$Report_LABLE;?></td>
									<td><a href="Report">&#8592;&nbsp;Go&nbsp;Back</a></td>
									<td><a href="export_report_cash?frdate=<?=$start_date;?>&&todate=<?=$endDate;?>&&lable=<?=$_REQUEST['lable'];?>&&rptlable=<?=$_REQUEST['rptlable'];?>&&out1=<?=$_REQUEST['out1'];?>&&out2=<?=$_REQUEST['out2'];?>&&out3=<?=$_REQUEST['out3'];?>&&out4=<?=$_REQUEST['out4'];?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						<!-----<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title"><?=$DISPLAY_LABLE;?></span>
												<table id="reportdash">
												<tr>
												<th>Today</th>
												<td>:&nbsp;<b><?=$_REQUEST['out1'];?></b></td>
												</tr>
												<tr>
												<th>Yesterday</th>
												<td>:&nbsp;<?=$_REQUEST['out2'];?></td>
												</tr>
												<tr>
												<th>This&nbsp;Month</th>
												<td>:&nbsp;<?=$_REQUEST['out3'];?></td>
												</tr>
												<tr>
												<th>Last&nbsp;Month Till&nbsp;Date</th>
												<td>:&nbsp;<?=$_REQUEST['out4'];?></td>
												</tr>
												</table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>---->
							
<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
<input type="hidden" name="lable" value="<?=$_REQUEST['lable'];?>"/>
<input type="hidden" name="rptlable" value="<?=$_REQUEST['rptlable'];?>"/>
<input type="hidden" name="out1" value="<?=$_REQUEST['out1'];?>"/>
<input type="hidden" name="out2" value="<?=$_REQUEST['out2'];?>"/>
<input type="hidden" name="out3" value="<?=$_REQUEST['out3'];?>"/>
<input type="hidden" name="out4" value="<?=$_REQUEST['out4'];?>"/>

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=$start_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=$endDate;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>

							</div>
							<div style="clear:both;"></div>
							<br/>
							</form>
							
							
							
<?php
//----Continuos Serial Number In Next Page.......................
$num_rec_per_page=30;
if (isset($_GET["page"])) { $page  = $_GET["page"]; } else { $page=1; }; 
 $start_from = ($page-1) * $num_rec_per_page; 
$i= $start_from;
//---------------------------------------------------------------
//echo ++$i; 
?>
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
									
									<style type="text/css">
									#overflowon{width:100%;overflow-x:scroll !important;height:100%;overflow-y:hidden;}
									</style>
									
									<div id="overflowon">
									
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Inv Num</th>
													<th>Date</th>
													<th>Type</th>
													<th>ID</th>
                                                    <th>Name</th>
													<th>Mobile</th>
													<th>District</th>
													<th style="text-align:right;">Amount</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php									
										
$select_market_SSCASH_VLSS_THISMONTH="select distinct inv_id from receipt where from_user_type='$usertypevl' and date between '$start_date' and '$endDate'";
$fetch_market_SSCASH_VLSS_THISMONTH=mysqli_query($db_conn,$select_market_SSCASH_VLSS_THISMONTH);
while($result_market_SSCASH_VLSS_THISMONTH=mysqli_fetch_array($fetch_market_SSCASH_VLSS_THISMONTH))
{
	
	$receipt_inv_id=$result_market_SSCASH_VLSS_THISMONTH['inv_id'];
	//
	$select_ReceiptDetails="select * from receipt where inv_id='$receipt_inv_id'";
	$fetch_ReceiptDetails=mysqli_query($db_conn,$select_ReceiptDetails);
	$result_ReceiptDetails=mysqli_fetch_array($fetch_ReceiptDetails);
	$to_user_type=$result_ReceiptDetails['to_user_type'];
	$to_user_id=$result_ReceiptDetails['to_user_id'];
	
if($to_user_type=="stockiest")
{$tablename="stockiest";}
else if($to_user_type=="distributor")
{$tablename="distributor";}
else{$tablename="shop";}
	
	
	//get invoice Number
	if($to_user_type=="stockiest" || $to_user_type=="distributor" || $to_user_type=="shop")
	{
	$select_inv_number="select * from user_invoice where inv_id='$receipt_inv_id'";
	$fetch_inv_number=mysqli_query($db_conn,$select_inv_number);
	$result_inv_number=mysqli_fetch_array($fetch_inv_number);
	
	$inv_number=$result_inv_number['inv_number'];
	$inv_date=$result_inv_number['date'];
	
	$selectRcd_VLSS1="select * from ".$tablename." where temp_id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
										
	$useridtext=$resultRcd_VLSS1["useridtext"];
	$usernametext=$resultRcd_VLSS1["name"];
	$mobilenumber=$resultRcd_VLSS1["mobile_number"];
	$state_id_VLSS1=$resultRcd_VLSS1['state_id'];
	$district_id_VLSS1=$resultRcd_VLSS1['district_id'];

										$selectrecords_VLSS1="select * from district where state_id='$state_id_VLSS1' and id='$district_id_VLSS1'";
										$fetchrecords_VLSS1=mysqli_query($db_conn,$selectrecords_VLSS1);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
	
	}else{
		//CUSTOMER
	$select_inv_number="select * from invoice where inv_id='$receipt_inv_id'";
	$fetch_inv_number=mysqli_query($db_conn,$select_inv_number);
	$result_inv_number=mysqli_fetch_array($fetch_inv_number);
	
	$inv_number=$result_inv_number['inv_number'];
	$inv_date=$result_inv_number['date'];
	
	$selectRcd_VLSS1="select * from customers where id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
										
	$useridtext="---";
	$usernametext=$resultRcd_VLSS1["name"];
	$district_name_VLSS1="---";
	$mobilenumber=$resultRcd_VLSS1["mobile"];
	}
										
$select_marketstock_VLDIST_VLSS1="select sum(received) from receipt where inv_id='$receipt_inv_id'";
$fetch_marketstock_VLDIST_VLSS1=mysqli_query($db_conn,$select_marketstock_VLDIST_VLSS1);
$result_marketstock_VLDIST_VLSS1=mysqli_fetch_array($fetch_marketstock_VLDIST_VLSS1);

if($result_marketstock_VLDIST_VLSS1[0]!=NULL){
$Total_available_stock_VLSS1=number_format($result_marketstock_VLDIST_VLSS1[0],2,'.','');
}else{$Total_available_stock_VLSS1="0";}

if($Total_available_stock_VLSS1>0)
{

$Total_available_stock123_VLSS1+=$Total_available_stock_VLSS1;
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
													<td><?=$inv_number;?></td>
													<td><?=date("d/m/Y",strtotime($inv_date));?></td>
							<td><?=$to_user_type;?></td>
							<td><?=$useridtext;?></td>
							<td><?=$usernametext;?></td>
							<td><?=$mobilenumber;?></td>
							<td><?=$district_name_VLSS1;?></td>
							<td align="right"><?=$Total_available_stock_VLSS1;?></td>
				
                                        </tr>
                                           
										<?php }?>
										<?php }?>
										 
										 <tfoot>
										 <tr>
										 <td colspan="8">Grand Total</td>
				<td align="right"><b><?=number_format($Total_available_stock123_VLSS1,2,'.','');?></b></td>
										 </tr>
										 </tfoot>
                                        </table>
										
										
										
										

		
										
										
										</div><!--overflow on end***-->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Javascripts -->
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
</body>

</html>