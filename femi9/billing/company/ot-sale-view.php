<?php include("checksession.php");
require_once("include/GodownAccess.php");
error_reporting(0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Manage OT Sales : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

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
    <style>
        .action-link { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;text-decoration:none;padding:0; }
        .action-link:hover { background:#f3f4f6;border-color:#d1d5db; }
        .action-link.delete:hover { background:#fef2f2;border-color:#fecaca; }
        .actions-group { display:inline-flex;align-items:center;gap:5px;white-space:nowrap; }
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
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
								
								
								<?php 
// Check for error message in session
if (isset($_SESSION['sucMessage'])) {
$errorMessage = $_SESSION['sucMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $errorMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['sucMessage']); } ?>


								<?php /* if(isset($_REQUEST['addesuccess'])){?><div class="alert alert-success">OT Sales details added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['updatedSuccess'])){?><div class="alert alert-info">Changes saved success.</div><?php }?>
								
								<?php if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-warning">Deleted ! one OT Sales details deleted success.</div><?php } */?>
								
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Manage OT Sales</td>
									<td><a href="ot-sale-add" title="Add Customer">&#10011;</a></td>
									</tr>
									</table>
									</h1>
									<br/>
									<?php 
									if($_REQUEST['frdate']==NULL && $_REQUEST['todate']==NULL)
									{
										$se_fromDate=date("Y-m-d");
										$se_toDate=date("Y-m-d");
										
									}else{
										$se_fromDate=$_REQUEST['frdate'];
										$se_toDate=$_REQUEST['todate'];
									}
									
									$se_invoice=trim($_REQUEST['se_invoice'] ?? '');
									?>
									
<form method="post" enctype="multipart/form-data" action="ot-sale-view">
<div class="overviewcontainar">
<div id="searchleftcont">
<label class="form-label">From Date</label>
<input type="date" required="" name="frdate" value="<?=$se_fromDate;?>" class="form-control">
</div>
<div id="searchleftcont">
<label class="form-label">To Date</label>
<input type="date" required="" name="todate" value="<?=$se_toDate;?>" class="form-control">
</div>

<div id="searchbuttoncont">
<button type="submit" name="seinvoice" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>

<div id="searchbuttoncont">&nbsp;
<button type="button" onclick="javascript:window.location='ot-sale-view';" class="btn btn-danger">Reset</button>
</div>
							</div>
							<div style="clear:both;"></div>
							<br/>
							</form>	
							
							<br/>
<form method="post" enctype="multipart/form-data" action="ot-sale-view">
<div class="overviewcontainar">
<div id="searchleftcont">
<label class="form-label">Invoice Number</label>
<input type="text" required="" name="se_invoice" value="<?=$se_invoice;?>" class="form-control">
</div>

<div id="searchbuttoncont">
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>

<div id="searchbuttoncont">&nbsp;
<button type="button" onclick="javascript:window.location='ot-sale-view';" name="sedatas" class="btn btn-danger">Reset</button>
</div>
							</div>
							<div style="clear:both;"></div>
							<br/>
							</form>	
							
							
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
                                        <div style="overflow-x:scroll;">
                                         <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
													<th>Company Profile</th>
													<th>Category</th>
													<th>Coupon Code</th>
													<th>Commission(Rs.)</th>
													<th>Date</th>
                                                    <th>Order Number</th>
													<th>Invoice Number</th>
													<th>Customer Name</th>
													<th>Customer Mobile</th>
													<th>State</th>
													<th>Address</th>
													
				<?php 
				$select_prdetails_header="select * from `products` order by `id` asc";
				$fetch_prdetails_header=mysqli_query($db_conn,$select_prdetails_header);
				while($result_prdetails_header=mysqli_fetch_array($fetch_prdetails_header)){?>
				<th><?=$result_prdetails_header['productName'];?></th>
				<?php }?>

													<th>Amount Status</th>
													<th>Entered by</th>
													<th>Actions</th>
													<th>Details</th>
													<th>Print</th>
													<th>Return</th>
                                                </tr>
                                            </thead>
											
											<tbody>
<?php
if(empty($se_invoice))
{
$select_product_list="select distinct tempid from ot_sales where date between '$se_fromDate' and '$se_toDate' and godownid IN (" . godown_ids_subquery($db_conn) . ")";
}else
{
$select_product_list="select distinct ot_sales.tempid from ot_sales join ot_sales_invoice on ot_sales_invoice.tempid=ot_sales.tempid where ot_sales_invoice.inv_number='".mysqli_real_escape_string($db_conn,$se_invoice)."' and ot_sales.godownid IN (" . godown_ids_subquery($db_conn) . ")";
}


$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
						$tempid=$result_product_list["tempid"];
						$select_productDetils="select * from ot_sales where tempid='$tempid'";
						$Fetch_productDetils=mysqli_query($db_conn,$select_productDetils);
						$Result_productDetils=mysqli_fetch_array($Fetch_productDetils);
						
						//GODOWN DETAILS
						$godownid=$Result_productDetils['godownid'];
						$select_godowndetails="select * from company_godown where id='$godownid' AND " . godown_finance_filter_sql($db_conn);
						$fetch_godowndetails=mysqli_query($db_conn,$select_godowndetails);
						$result_godowndetails=mysqli_fetch_array($fetch_godowndetails);
						
						//Ot sales Inovice
						$select_productDetils122="select * from ot_sales_invoice where tempid='$tempid'";
						$Fetch_productDetils122=mysqli_query($db_conn,$select_productDetils122);
						$Result_productDetils122=mysqli_fetch_array($Fetch_productDetils122);
						$ot_invoice_number=$Result_productDetils122['inv_number'];
						?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
				<td><?php echo $result_godowndetails["gname"];?></td>
													<td><?php echo $Result_productDetils["cat"];?></td>
													
													<td><?php echo $Result_productDetils122["coupon_code"];?></td>
													<td><?php echo $Result_productDetils122["website_commission"];?></td>
													
					<td><?php echo date("d/m/y",strtotime($Result_productDetils["date"]));?></td>
					<td><?php echo $Result_productDetils["order_number"];?></td>
					<td><?php echo $ot_invoice_number;?></td>
					
					<td><?php echo $Result_productDetils["customer_name"];?></td>
					<td><?php echo $Result_productDetils["customer_mobile"];?></td>
					
					<?php 
$get_stateID=$Result_productDetils['state_id'];
if($get_stateID!=NULL && $get_stateID!=0)
{
$select_stateList12="select * from `state` where id='$get_stateID'";
$fetch_staeList12=mysqli_query($db_conn,$select_stateList12);
$result_stateList12=mysqli_fetch_array($fetch_staeList12);
$STName=$result_stateList12['st_name'];
}else{
	$STName="<a href='ot-sale-edit?tempid=".base64_encode($tempid)."'>Update State</a>";
}
?>
					<td><?php echo $STName;?></td>
					<td><?php echo $Result_productDetils["customer_address"];?></td>
					
					
				<!------------------------PRODUCT WISE SALES QTY------------------------------->
				<?php $select_prdetails_header="select * from `products` order by `id` asc";
				$fetch_prdetails_header=mysqli_query($db_conn,$select_prdetails_header);
				while($result_prdetails_header=mysqli_fetch_array($fetch_prdetails_header)){
					
					$prid_header=$result_prdetails_header['id'];
					
					//SALES QTY
					$select_SUM_QTY="select qty from ot_sales where tempid='$tempid' and prid='$prid_header'";
					$fetch_SUM_QTY=mysqli_query($db_conn,$select_SUM_QTY);
					$result_SUM_QTY=mysqli_fetch_array($fetch_SUM_QTY);
					if($result_SUM_QTY['qty']!=NULL){ $slsqty=$result_SUM_QTY['qty'];} else{ $slsqty="0";}
					
					//SALES Return QTY
					/*
					$select_Return_QTY="select qty from ot_sales_return where tempid='$tempid' and prid='$prid_header'";
					$fetch_Return_QTY=mysqli_query($db_conn,$select_Return_QTY);
					$result_Return_QTY=mysqli_fetch_array($fetch_Return_QTY);
					if($result_Return_QTY['qty']!=NULL){ $slsRtnqty=$result_Return_QTY['qty'];} else{ $slsRtnqty="0";}
					
					
					$net_sls_qty=$slsqty-$slsRtnqty;
					*/
					
					$net_sls_qty=$slsqty;
						
				?>
				<th><?=$net_sls_qty;?></th>
				<?php }?>
				<!-------------------------------------------------------------------->

<td>
<?php if($Result_productDetils["amount_received"]==0){?>
<a href="JavaScript:newPopup('ot-sale-amount.php?tempid=<?php echo base64_encode($tempid);?>');" data-bs-toggle="tooltip" data-bs-placement="top" title="View Details" style="font-weight:bold;text-decoration:none;color:blue;" onclick="return confirm('You want to confirm Received the amount?');">
<span class="badge badge-danger">Not&nbsp;Received</span></a>
<?php }else{?>
<span class="badge badge-success">Received</span>
<?php }?>
</td>

<script type="text/javascript">
function newPopup(url) {
	popupWindow = window.open(
		url,'popUpWindow','height=100,width=100,left=350,top=200,resizable=yes,scrollbars=yes,toolbar=yes,menubar=no,location=no,directories=no,status=yes')
}
</script>

<td><?=$Result_productDetils["username"];?><br/><?=ucwords($Result_productDetils["usertype"]);?></td>
							

		
																										<td>
													    <div class="actions-group">
													        <a href="ot-sale-edit?tempid=<?php echo base64_encode($tempid);?>" class="action-link" title="Edit"><i class="material-icons-outlined" style="font-size:17px;color:#667eea;">edit</i></a>
													    </div>
													</td>
													
													
													<td>
<a href="ot-sale-details?tempid=<?=$tempid;?>"><img src="../../assets/images/details-32.png"/></a>
													</td>
													
													
													<td>
<a href="ot-sale-print?tempid=<?=$tempid;?>" title="Print">
<img src="../../assets/images/print32.png"/></a>
													</td>
													
													<td>
													<a href="ot-sale-return.php?tempid=<?php echo base64_encode($tempid);?>"><span class="badge badge-warning">Return</span></a>
													</td>
													
                                                </tr>
                                           
										<?php }?>
										
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
    <script>
        // Remove DataTables' own built-in search box — it only filters rows already
        // loaded on screen and was getting confused with the From Date/To Date and
        // Invoice Number search forms above, which do the real server-side search.
        $(document).ready(function () {
            $('#datatable1_filter').remove();
        });
    </script>
</body>

</html>