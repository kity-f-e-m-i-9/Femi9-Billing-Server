<?php include("checksession.php"); include 'config.php'; error_reporting(0);
if($result_LoGuserDtails['user_position']==0){
	echo "<script>window.location='dashboard.php';</script>";exit;
}

	$displaytitle="Stockist Sales Report";
	$lablenamedisplay="Stockist Name";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title><?=$displaytitle;?>  : <?php echo $business_name;?></title>

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
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

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
								<?php 
						if($_REQUEST['frdate']!=NULL)
						{
$from_date=$_REQUEST['frdate'];
$to_date=$_REQUEST['todate'];
						}
						else{
$to_date=date("Y-m-d");
$from_date = date ("Y-m-d", strtotime("-2 days", strtotime($to_date)));
						}
?>
<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
<input type="hidden" name="invuser" value="<?=$_REQUEST['invuser'];?>">

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
							
							
                                    <h1>
									<table class="headertble">
									<tr>
									<td><?=$displaytitle;?></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
						
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
                                        <table id="datatable1" class="display" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>Invoice Number</th>
													<th><?=$lablenamedisplay;?></th>
													<th>Invoice Date</th>
													<th>Invoice Amount</th>
													<th>Billing To</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $select_product_list="select * from user_invoice where from_user_type='stockiest' and date between '$from_date' and '$to_date' order by id desc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											//Stockist details
											$from_user_id=$result_product_list['from_user_id'];
										$select_From2="select * from stockiest where temp_id='$from_user_id'";
										$fetch_From2=mysqli_query($db_conn,$select_From2);
										$result_From2=mysqli_fetch_array($fetch_From2);
										//
										$Cust_Name=$result_From2['name'];
										$Cust_Mbile=$result_From2['mobile_number'];
										
//District Details
$select_district="select dist_name from district where id='".$result_From2['district_id']."'";
$fetch_district=mysqli_query($db_conn,$select_district);
$result_district=mysqli_fetch_array($fetch_district);
$from_district=$result_district['dist_name'];
//Taluk Details
$select_taluk="select taluk from taluk where id='".$result_From2['taluk_id']."'";
$fetch_taluk=mysqli_query($db_conn,$select_taluk);
$result_taluk=mysqli_fetch_array($fetch_taluk);
$from_taluk=$result_taluk['taluk'];
											
											$RowID_encode=base64_encode($result_product_list["id"]);
											$INVID_encode=base64_encode($result_product_list["inv_id"]);

//Billing To Details 
$to_user_id=$result_product_list['to_user_id'];
$to_user_type=$result_product_list['to_user_type'];

											if($to_user_type=='super_distributor')
											{
												$to_tableName='super_distributor';
											}
											elseif($to_user_type=='distributor')
											{
												$to_tableName='distributor';
											}
											else
											//$to_user_type=='shop'
											{
												$to_tableName='shop';
											}
											
										$select_To2="select * from ".$to_tableName." where temp_id='$to_user_id'";
										$fetch_To2=mysqli_query($db_conn,$select_To2);
										$result_To2=mysqli_fetch_array($fetch_To2);
										
										$Billing_to_Name=$result_To2['name'];
										$Billing_to_Mobile=$result_To2['mobile_number'];
										
										
//District Details : Billing To
if(is_numeric($result_To2['district_id']))
{
$select_district2="select dist_name from district where id='".$result_To2['district_id']."'";
$fetch_district2=mysqli_query($db_conn,$select_district2);
$result_district2=mysqli_fetch_array($fetch_district2);
$to_district=$result_district2['dist_name'];
}
										else{
										$to_district=$result_To2['district_id'];	
										}
//Taluk Details : Billing To
if(is_numeric($result_To2['taluk_id']))
{
$select_taluk2="select taluk from taluk where id='".$result_To2['taluk_id']."'";
$fetch_taluk2=mysqli_query($db_conn,$select_taluk2);
$result_taluk2=mysqli_fetch_array($fetch_taluk2);
$to_taluk=$result_taluk2['taluk'];
}
else{
	$to_taluk=$result_To2['taluk_id'];
}
											
?>       
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo $result_product_list["inv_number"];?></td>
													
													<td><?php echo $Cust_Name;?><br/>M:&nbsp;<?php echo $Cust_Mbile;?><br/>
													<?=ucwords($from_district);?><br/>
													<?=ucwords($from_taluk);?>
													</td>
													
	<td><?php echo date("d/M/Y",strtotime($result_product_list["date"]));?></td>
	
	<?php 
	//receipt details
$totalamount=$result_product_list["total"];
$selectcountreceipt="select sum(received) from receipt where inv_id='".$result_product_list["inv_id"]."'";
$fetchcountreceipt=mysqli_query($db_conn,$selectcountreceipt);
$resulcountreceipt=mysqli_fetch_array($fetchcountreceipt);
$Total_Receipt_amount=$resulcountreceipt[0];
if($Total_Receipt_amount==0)
{
	$msgpayment="<span class='badge badge-style-bordered badge-danger'>Not Paid</span>";
}
else if($Total_Receipt_amount>0 && $totalamount==$Total_Receipt_amount)
{
	$msgpayment="<span class='badge badge-style-bordered badge-success'>Fully Paid</span>";
}else{
	$msgpayment="<span class='badge badge-style-bordered badge-warning'>partially Paid</span>";
}
?>
				<td><?php echo inr_format($result_product_list["total"], 2);?>
				</td>
				
				<td>
				<?=ucwords($to_user_type);?><br/>
				<?=$Billing_to_Name;?><br/>
				<?=ucwords($Billing_to_Mobile);?><br/>
				<?=ucwords($to_district);?><br/>
				<?=ucwords($to_taluk);?>
				</td>
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
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