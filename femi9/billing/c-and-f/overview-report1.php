<?php include("checksession.php");
include("config.php");
error_reporting(0);

if($_REQUEST['lable']==1 && $_REQUEST['rptlable']==1)
{
	$DISPLAY_LABLE="Today";
	$Report_LABLE="Sales From Company";
}
else if($_REQUEST['lable']==2 && $_REQUEST['rptlable']==1)
{
	$DISPLAY_LABLE="Yesterday";
	$Report_LABLE="Sales From Company";
}
else if($_REQUEST['lable']==3 && $_REQUEST['rptlable']==1)
{
	$DISPLAY_LABLE="This Month";
	$Report_LABLE="Sales From Company";
}
else
{
	$DISPLAY_LABLE="Last Month till date";
	$Report_LABLE="Sales From Company";
	
}

$from_date=$_REQUEST['frdate'];
$to_date=$_REQUEST['todate'];
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
									<td><a href="export_report1?frdate=<?=$from_date;?>&&todate=<?=$to_date;?>&&lable=<?=$_REQUEST['lable'];?>&&rptlable=<?=$_REQUEST['rptlable'];?>&&out1=<?=$_REQUEST['out1'];?>&&out2=<?=$_REQUEST['out2'];?>&&out3=<?=$_REQUEST['out3'];?>" title="Export"><img src="../../assets/images/excel-3-32.png"></a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
						<?php if($_REQUEST['setrigger']==NULL){?>
						<div class="row">
                            <div class="col-xl-3">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <div class="widget-stats-container d-flex">
                                            <div class="widget-stats-content flex-fill">
                                                <span class="widget-stats-title"><?=$DISPLAY_LABLE;?></span>
												<table id="reportdash">
												<tr>
												<th>Invoice&nbsp;Count</th>
												<td>:&nbsp;<?=$_REQUEST['out1'];?></td>
												</tr>
												<tr>
												<th>Product&nbsp;Qty</th>
												<td>:&nbsp;<?=$_REQUEST['out2'];?></td>
												</tr>
												<tr>
												<th>Total&nbsp;Amount</th>
												<td>:&nbsp;&#x20B9;<?=$_REQUEST['out3'];?></td>
												</tr>
												</table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
							</div>
						<?php }?>
							
							
						<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
<input type="hidden" name="lable" value="<?=$_REQUEST['lable'];?>"/>
<input type="hidden" name="rptlable" value="<?=$_REQUEST['rptlable'];?>"/>
<input type="hidden" name="out1" value="<?=$_REQUEST['out1'];?>"/>
<input type="hidden" name="out2" value="<?=$_REQUEST['out2'];?>"/>
<input type="hidden" name="out3" value="<?=$_REQUEST['out3'];?>"/>
<input type="hidden" name="setrigger" value="1"/>

							<div class="overviewcontainar">
							<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=$from_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=$to_date;?>" class="form-control" onkeypress="restrictSpecialChars(event)">
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
                                                    <th>Inv Number</th>
													<th>User Type</th>
													<th>Name</th>
													<th>Date</th>
													<!----<th>Sub Total</th>
													<th>Discount</th>---->
													<th>Total Amount</th>
													<th>Product Qty</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $select_product_list="select * from user_invoice where date between '$from_date' and '$to_date' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and sub_total>0 order by id asc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
											$tousertype=$result_product_list['to_user_type'];
											
if($tousertype=="super_stockiest"){$tablename="super_stockiest"; $userTypee="Super Stockist";}
else if($tousertype=="stockiest"){$tablename="stockiest"; $userTypee="Stockist";}
else if($tousertype=="distributor"){$tablename="distributor"; $userTypee="Distributor";}
else if($tousertype=="outlet"){$tablename="outlet"; $userTypee="Outlet";}
else{$tablename="shop"; $userTypee="Shop";}
											
											//customer details
											$CuSTID=$result_product_list['to_user_id'];
										
$select_Customers="select * from ".$tablename." where temp_id='$CuSTID'";
										$fetch_Customers=mysqli_query($db_conn,$select_Customers);
										$result_Customers=mysqli_fetch_array($fetch_Customers);
										//
										$Cust_Name=$result_Customers['name'];
										$Cust_Mbile=$result_Customers['mobile_number'];
											
//product Qty
$inv_id=$result_product_list['inv_id'];

$select_sumprqty="select sum(qty) from user_invoice_items where inv_id='$inv_id'";
$fetch_sumprqty=mysqli_query($db_conn,$select_sumprqty);
$result_sumprqty=mysqli_fetch_array($fetch_sumprqty);
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo $result_product_list["inv_number"];?></td>
													<td><?php echo $userTypee;?></td>
													<td><?php echo $Cust_Name;?><br/><b>M:</b>&nbsp;<?php echo $Cust_Mbile;?></td>
													<td><?php echo date("d/M/Y",strtotime($result_product_list["date"]));?></td>
													
				<!------<td><?php echo inr_format($result_product_list["sub_total"], 2);?></td>
				<td><?php
$discount=$result_product_list["discount"]+$result_product_list["credit"];
				echo inr_format($discount, 2);?>
				</td>---->
				
				<?php
				$TotalAmount=$result_product_list["total"];
				$TotalAmount123+=$TotalAmount;
				
				if($result_sumprqty[0]!=NULL){
				$TotalPrQty=$result_sumprqty[0];
				}else{$TotalPrQty="0";}
				$TotalPrQty123+=$TotalPrQty;
				?>
				<td align="right"><?php echo inr_format($TotalAmount, 2);?></td>
				<td align="right"><?=$TotalPrQty;?></td>
													
													
                                                </tr>
                                           
										<?php }?>
										
										
<!-------------------------------------------------------------------------------------->
<!----------------------------------CUSTOMER INVOICE------------------------------------>	
<!-------------------------------------------------------------------------------------->				
										
										<?php $select_product_listcUSTOMER="select * from invoice where date between '$from_date' and '$to_date' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' and sub_total>0 order by id asc";
			$fetch_product_listCUTOMER=mysqli_query($db_conn,$select_product_listcUSTOMER);
			while($result_product_listCUSTOMER=mysqli_fetch_array($fetch_product_listCUTOMER))
										{
											
											//customer details
											$CuSTID=$result_product_listCUSTOMER['customer_id'];
										if($CuSTID==0)
										{
											$Cust_Name123="Walking Customer";
											
										}else{
$select_Customers="select * from customers where id='$CuSTID'";
										$fetch_Customers=mysqli_query($db_conn,$select_Customers);
										$result_Customers=mysqli_fetch_array($fetch_Customers);
										//
										$Cust_Name123=$result_Customers['name'];
										$Cust_Mbile123=$result_Customers['mobile'];
										}
											
//product Qty
$inv_id=$result_product_listCUSTOMER['inv_id'];

$select_sumprqty="select sum(qty) from invoice_items where inv_id='$inv_id'";
$fetch_sumprqty=mysqli_query($db_conn,$select_sumprqty);
$result_sumprqty=mysqli_fetch_array($fetch_sumprqty);
?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo $result_product_listCUSTOMER["inv_number"];?></td>
													<td>Customer</td>
													<td><?php echo $Cust_Name123;?><br/><b>M:</b>&nbsp;<?php echo $Cust_Mbile123;?></td>
													<td><?php echo date("d/M/Y",strtotime($result_product_listCUSTOMER["date"]));?></td>
													
				<!------<td><?php echo inr_format($result_product_listCUSTOMER["sub_total"], 2);?></td>
				<td><?php
$discount=$result_product_listCUSTOMER["discount"]+$result_product_listCUSTOMER["credit"];
				echo inr_format($discount, 2);?>
				</td>---->
				
				<?php
				$TotalAmountCUS=$result_product_listCUSTOMER["total"];
				$TotalAmount123CUS+=$TotalAmountCUS;
				
				$TotalPrQtyCUS=$result_sumprqty[0];
				$TotalPrQty123CUS+=$TotalPrQtyCUS;
				?>
				<td align="right"><?php echo inr_format($TotalAmountCUS, 2);?></td>
				<td align="right"><?=$TotalPrQtyCUS;?></td>
													
													
                                                </tr>
                                           
										<?php }
										
										$GrandTotal_amount=$TotalAmount123+$TotalAmount123CUS;
										$GrandTotal_Qty=$TotalPrQty123+$TotalPrQty123CUS;
										?>
										
										 </tbody>
										 
										 <tfoot>
										 <tr>
										 <td colspan="5">Grand Total</td>
				<td align="right"><b><?php echo inr_format($GrandTotal_amount, 2);?></b></td>
				<td align="right"><b><?=$GrandTotal_Qty;?></b></td>
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