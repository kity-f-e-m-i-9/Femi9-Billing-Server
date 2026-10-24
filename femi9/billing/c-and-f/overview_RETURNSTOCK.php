<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);

$Report_LABLE="RETURN STOCK REPORTS";

if($_REQUEST['lable']==1)
{
	$DISPLAY_LABLE="Super Stockist"; 			$usertypevl="super_stockiest"; $tblname="super_stockiest";
}

else if($_REQUEST['lable']==2)
{
	$DISPLAY_LABLE="Stockist"; 			$usertypevl="stockiest"; $tblname="stockiest";
}

else
{
	$DISPLAY_LABLE="Distributor";  		$usertypevl="distributor"; $tblname="distributor";
}


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
									<td><a href="export_report_return?frdate=<?=htmlspecialchars((string)($start_date), ENT_QUOTES, 'UTF-8', false);?>&&todate=<?=htmlspecialchars((string)($endDate), ENT_QUOTES, 'UTF-8', false);?>&&lable=<?=htmlspecialchars((string)($_REQUEST['lable']), ENT_QUOTES, 'UTF-8', false);?>&&rptlable=<?=htmlspecialchars((string)($_REQUEST['rptlable']), ENT_QUOTES, 'UTF-8', false);?>&&out1=<?=htmlspecialchars((string)($_REQUEST['out1']), ENT_QUOTES, 'UTF-8', false);?>&&out2=<?=htmlspecialchars((string)($_REQUEST['out2']), ENT_QUOTES, 'UTF-8', false);?>&&out3=<?=htmlspecialchars((string)($_REQUEST['out3']), ENT_QUOTES, 'UTF-8', false);?>&&out4=<?=htmlspecialchars((string)($_REQUEST['out4']), ENT_QUOTES, 'UTF-8', false);?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						<?php if($_REQUEST['setrigger']==NULL){?>
						<div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title"><?=$DISPLAY_LABLE;?></span>
												<table id="reportdash">
												<tr>
												<th>Today</th>
												<td>:&nbsp;<?=htmlspecialchars((string)($_REQUEST['out1']), ENT_QUOTES, 'UTF-8', false);?></td>
												</tr>
												<tr>
												<th>Yesterday</th>
												<td>:&nbsp;<?=htmlspecialchars((string)($_REQUEST['out2']), ENT_QUOTES, 'UTF-8', false);?></td>
												</tr>
												<tr>
												<th>This&nbsp;Month</th>
												<td>:&nbsp;<?=htmlspecialchars((string)($_REQUEST['out3']), ENT_QUOTES, 'UTF-8', false);?></td>
												</tr>
												<tr>
												<th>Last&nbsp;Month&nbsp;Till&nbsp;Date</th>
												<td>:&nbsp;<?=htmlspecialchars((string)($_REQUEST['out4']), ENT_QUOTES, 'UTF-8', false);?></td>
												</tr>
												</table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
						<?php }?>
						
						
<form method="post" enctype="multipart/form-data" action="<?=htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES);?>">
<input type="hidden" name="lable" value="<?=htmlspecialchars((string)($_REQUEST['lable']), ENT_QUOTES, 'UTF-8', false);?>"/>
<input type="hidden" name="rptlable" value="<?=htmlspecialchars((string)($_REQUEST['rptlable']), ENT_QUOTES, 'UTF-8', false);?>"/>
<input type="hidden" name="out1" value="<?=htmlspecialchars((string)($_REQUEST['out1']), ENT_QUOTES, 'UTF-8', false);?>"/>
<input type="hidden" name="out2" value="<?=htmlspecialchars((string)($_REQUEST['out2']), ENT_QUOTES, 'UTF-8', false);?>"/>
<input type="hidden" name="out3" value="<?=htmlspecialchars((string)($_REQUEST['out3']), ENT_QUOTES, 'UTF-8', false);?>"/>
<input type="hidden" name="out4" value="<?=htmlspecialchars((string)($_REQUEST['out4']), ENT_QUOTES, 'UTF-8', false);?>"/>
<input type="hidden" name="setrigger" value="1"/>

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=htmlspecialchars((string)($start_date), ENT_QUOTES, 'UTF-8', false);?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=htmlspecialchars((string)($endDate), ENT_QUOTES, 'UTF-8', false);?>" class="form-control" onkeypress="restrictSpecialChars(event)">
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
									
									<!-----------------------Super STOCKIST-------------------------------->
									<?PHP if($_REQUEST['lable']==1){?>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Inv Num</th>
													<th>Date of Return</th>
													<th>Qty of Return</th>
													<th>Total Return Amount(Rs.)</th>
													<th>ID</th>
                                                    <th>SS Name</th>
													<th>Mobile</th>
													<th>District</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php
$select_market_SSCASH_VLSS_THISMONTH="select * from user_return_stock where from_usertype=? and date between ? and ? and to_usertype=? and to_userid=?";
$fetch_market_SSCASH_VLSS_THISMONTH=db_q($db_conn,$select_market_SSCASH_VLSS_THISMONTH,[$usertypevl,$start_date,$endDate,$Login_user_TYPEvl,$Login_user_IDvl]);
while($result_market_SSCASH_VLSS_THISMONTH=mysqli_fetch_array($fetch_market_SSCASH_VLSS_THISMONTH))
{
	
	$to_user_id=$result_market_SSCASH_VLSS_THISMONTH['from_userid'];
	$returnid=$result_market_SSCASH_VLSS_THISMONTH['returnid'];
	
	//qty of returned
	$select_sumqty="select sum(qty) from user_return_stock_items where returnid=?";
	$fetch_sumqty=db_q($db_conn,$select_sumqty,[$returnid]);
	$result_sumqty=mysqli_fetch_array($fetch_sumqty);
	if($result_sumqty[0]!=NULL){$TotalReturnQTY=$result_sumqty[0];}
	else{$TotalReturnQTY="0";}
	
	//Total stock return (Rs.)
	$Select_sumreturnamount="select sum(total) from user_return_stock_items where returnid=?";
	$Fetch_sumreturnamount=db_q($db_conn,$Select_sumreturnamount,[$returnid]);
	$Result_sumreturnamount=mysqli_fetch_array($Fetch_sumreturnamount);
	if($Result_sumreturnamount[0]!=NULL){$TotalReturnAmount=$Result_sumreturnamount[0];}
	else{$TotalReturnAmount="0";}
	
	$selectRcd_VLSS1="select * from ".$tblname." where temp_id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
	
										
	$useridtext=$resultRcd_VLSS1["useridtext"];
	$usernametext=$resultRcd_VLSS1["name"];
											
											$state_id_VLSS1=$resultRcd_VLSS1['state_id'];
											$district_id_VLSS1=$resultRcd_VLSS1['district_id'];
											$taluk_id_VLSS2=$resultRcd_VLSS1['taluk_id'];

										$selectrecords_VLSS1="select * from district where state_id=? and id=?";
										$fetchrecords_VLSS1=db_q($db_conn,$selectrecords_VLSS1,[$state_id_VLSS1,$district_id_VLSS1]);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
										$taluk_id_VLSS2=$resultRcd_VLSS1['taluk_id'];
										$selectrecords_VLSS2_taluk="select * from taluk where state_id=? and dist_id=? and id=?";
$fetchrecords_VLSS2_taluk=db_q($db_conn,$selectrecords_VLSS2_taluk,[$state_id_VLSS1,$district_id_VLSS1,$taluk_id_VLSS2]);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];
										

?>
                                            
                                                <tr>
                            <td><?php echo ++$i; ?></td>
							<td><?=htmlspecialchars((string)($result_market_SSCASH_VLSS_THISMONTH['invnumber']), ENT_QUOTES, 'ISO-8859-1', false);?></td>
							<td><?=date("d/m/Y",strtotime($result_market_SSCASH_VLSS_THISMONTH['date']));?></td>
							<td><?=$TotalReturnQTY;?></td>
							<td><?=inr_format($TotalReturnAmount, 2);?></td>
							<td><?=$useridtext;?></td>
							<td><?=$usernametext;?></td>
							<td><?=htmlspecialchars((string)($resultRcd_VLSS1["mobile_number"]), ENT_QUOTES, 'ISO-8859-1', false);?></td>
							<td><?=$district_name_VLSS1;?></td>
				
                                        </tr>
                                           
										<?php }?>
										
                                        </table>
										<?PHP }?>
										
									
										
										<!-----------------------STOCKIST-------------------------------->
									<?PHP if($_REQUEST['lable']==2){?>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Inv Num</th>
													<th>Date of Return</th>
													<th>Qty of Return</th>
													<th>Total Return Amount(Rs.)</th>
													<th>ID</th>
                                                    <th>Name</th>
													<th>Mobile</th>
													<th>District</th>
													<th>Taluk</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php
$select_market_SSCASH_VLSS_THISMONTH="select * from user_return_stock where from_usertype=? and date between ? and ? and to_usertype=? and to_userid=?";
$fetch_market_SSCASH_VLSS_THISMONTH=db_q($db_conn,$select_market_SSCASH_VLSS_THISMONTH,[$usertypevl,$start_date,$endDate,$Login_user_TYPEvl,$Login_user_IDvl]);
while($result_market_SSCASH_VLSS_THISMONTH=mysqli_fetch_array($fetch_market_SSCASH_VLSS_THISMONTH))
{
	
	$to_user_id=$result_market_SSCASH_VLSS_THISMONTH['from_userid'];
	$returnid=$result_market_SSCASH_VLSS_THISMONTH['returnid'];
	
	//qty of returned
	$select_sumqty="select sum(qty) from user_return_stock_items where returnid=?";
	$fetch_sumqty=db_q($db_conn,$select_sumqty,[$returnid]);
	$result_sumqty=mysqli_fetch_array($fetch_sumqty);
	if($result_sumqty[0]!=NULL){$TotalReturnQTY=$result_sumqty[0];}
	else{$TotalReturnQTY="0";}
	
	//Total stock return (Rs.)
	$Select_sumreturnamount="select sum(total) from user_return_stock_items where returnid=?";
	$Fetch_sumreturnamount=db_q($db_conn,$Select_sumreturnamount,[$returnid]);
	$Result_sumreturnamount=mysqli_fetch_array($Fetch_sumreturnamount);
	if($Result_sumreturnamount[0]!=NULL){$TotalReturnAmount=$Result_sumreturnamount[0];}
	else{$TotalReturnAmount="0";}
	
	$selectRcd_VLSS1="select * from ".$tblname." where temp_id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
	
										
	$useridtext=$resultRcd_VLSS1["useridtext"];
	$usernametext=$resultRcd_VLSS1["name"];
											
											$state_id_VLSS1=$resultRcd_VLSS1['state_id'];
											$district_id_VLSS1=$resultRcd_VLSS1['district_id'];
											$taluk_id_VLSS2=$resultRcd_VLSS1['taluk_id'];

										$selectrecords_VLSS1="select * from district where state_id=? and id=?";
										$fetchrecords_VLSS1=db_q($db_conn,$selectrecords_VLSS1,[$state_id_VLSS1,$district_id_VLSS1]);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
										$taluk_id_VLSS2=$resultRcd_VLSS1['taluk_id'];
										$selectrecords_VLSS2_taluk="select * from taluk where state_id=? and dist_id=? and id=?";
$fetchrecords_VLSS2_taluk=db_q($db_conn,$selectrecords_VLSS2_taluk,[$state_id_VLSS1,$district_id_VLSS1,$taluk_id_VLSS2]);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];
										

?>
                                            
                                                <tr>
                            <td><?php echo ++$i; ?></td>
							<td><?=htmlspecialchars((string)($result_market_SSCASH_VLSS_THISMONTH['invnumber']), ENT_QUOTES, 'ISO-8859-1', false);?></td>
							<td><?=date("d/m/Y",strtotime($result_market_SSCASH_VLSS_THISMONTH['date']));?></td>
							<td><?=$TotalReturnQTY;?></td>
							<td><?=inr_format($TotalReturnAmount, 2);?></td>
							<td><?=$useridtext;?></td>
							<td><?=$usernametext;?></td>
							<td><?=htmlspecialchars((string)($resultRcd_VLSS1["mobile_number"]), ENT_QUOTES, 'ISO-8859-1', false);?></td>
							<td><?=$district_name_VLSS1;?></td>
							<td><?=$taluk_name_VLSS2;?></td>
				
                                        </tr>
                                           
										<?php }?>
										
                                        </table>
										<?PHP }?>
										
										

<!-----------------------DISTRIBUTOR-------------------------------->
									<?PHP if($_REQUEST['lable']==3){?>
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Inv Num</th>
													<th>Date of Return</th>
													<th>Qty of Return</th>
													<th>Total Return Amount(Rs.)</th>
													<th>ID</th>
                                                    <th>Name</th>
													<th>Mobile</th>
													<th>District</th>
													<th>Taluk</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php
$select_market_SSCASH_VLSS_THISMONTH="select * from user_return_stock where from_usertype=? and date between ? and ? and to_usertype=? and to_userid=?";
$fetch_market_SSCASH_VLSS_THISMONTH=db_q($db_conn,$select_market_SSCASH_VLSS_THISMONTH,[$usertypevl,$start_date,$endDate,$Login_user_TYPEvl,$Login_user_IDvl]);
while($result_market_SSCASH_VLSS_THISMONTH=mysqli_fetch_array($fetch_market_SSCASH_VLSS_THISMONTH))
{
	
	$to_user_id=$result_market_SSCASH_VLSS_THISMONTH['from_userid'];
	$returnid=$result_market_SSCASH_VLSS_THISMONTH['returnid'];
	
	//qty of returned
	$select_sumqty="select sum(qty) from user_return_stock_items where returnid=?";
	$fetch_sumqty=db_q($db_conn,$select_sumqty,[$returnid]);
	$result_sumqty=mysqli_fetch_array($fetch_sumqty);
	if($result_sumqty[0]!=NULL){$TotalReturnQTY=$result_sumqty[0];}
	else{$TotalReturnQTY="0";}
	
	//Total stock return (Rs.)
	$Select_sumreturnamount="select sum(total) from user_return_stock_items where returnid=?";
	$Fetch_sumreturnamount=db_q($db_conn,$Select_sumreturnamount,[$returnid]);
	$Result_sumreturnamount=mysqli_fetch_array($Fetch_sumreturnamount);
	if($Result_sumreturnamount[0]!=NULL){$TotalReturnAmount=$Result_sumreturnamount[0];}
	else{$TotalReturnAmount="0";}
	
	$selectRcd_VLSS1="select * from ".$tblname." where temp_id='$to_user_id'";
										$fetchRcd_VLSS1=mysqli_query($db_conn,$selectRcd_VLSS1);
										$resultRcd_VLSS1=mysqli_fetch_array($fetchRcd_VLSS1);
	
										
	$useridtext=$resultRcd_VLSS1["useridtext"];
	$usernametext=$resultRcd_VLSS1["name"];
											
											$state_id_VLSS1=$resultRcd_VLSS1['state_id'];
											$district_id_VLSS1=$resultRcd_VLSS1['district_id'];
											$taluk_id_VLSS2=$resultRcd_VLSS1['taluk_id'];

										$selectrecords_VLSS1="select * from district where state_id=? and id=?";
										$fetchrecords_VLSS1=db_q($db_conn,$selectrecords_VLSS1,[$state_id_VLSS1,$district_id_VLSS1]);
										$resultrecords_VLSS1=mysqli_fetch_array($fetchrecords_VLSS1);
										$district_name_VLSS1=$resultrecords_VLSS1['dist_name'];
										
										$taluk_id_VLSS2=$resultRcd_VLSS1['taluk_id'];
										$selectrecords_VLSS2_taluk="select * from taluk where state_id=? and dist_id=? and id=?";
$fetchrecords_VLSS2_taluk=db_q($db_conn,$selectrecords_VLSS2_taluk,[$state_id_VLSS1,$district_id_VLSS1,$taluk_id_VLSS2]);
$resultrecords_VLSS2_taluk=mysqli_fetch_array($fetchrecords_VLSS2_taluk);
$taluk_name_VLSS2=$resultrecords_VLSS2_taluk['taluk'];
										

?>
                                            
                                                <tr>
                            <td><?php echo ++$i; ?></td>
							<td><?=htmlspecialchars((string)($result_market_SSCASH_VLSS_THISMONTH['invnumber']), ENT_QUOTES, 'ISO-8859-1', false);?></td>
							<td><?=date("d/m/Y",strtotime($result_market_SSCASH_VLSS_THISMONTH['date']));?></td>
							<td><?=$TotalReturnQTY;?></td>
							<td><?=inr_format($TotalReturnAmount, 2);?></td>
							<td><?=$useridtext;?></td>
							<td><?=$usernametext;?></td>
							<td><?=htmlspecialchars((string)($resultRcd_VLSS1["mobile_number"]), ENT_QUOTES, 'ISO-8859-1', false);?></td>
							<td><?=$district_name_VLSS1;?></td>
							<td><?=$taluk_name_VLSS2;?></td>
				
                                        </tr>
                                           
										<?php }?>
										
                                        </table>
										<?PHP }?>
										
			<!------------------END***----------------------------------------->		
										
										
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