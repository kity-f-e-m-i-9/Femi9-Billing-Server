<?php include("checksession.php");
include("config.php");
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
    <title>Manage Internal Stock Transfer : <?php echo $business_name;?></title>

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
$sucMessage = $_SESSION['sucMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $sucMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['sucMessage']); } ?>


								<?php /* if(isset($_REQUEST['addesuccess'])){?><div class="alert alert-success">Internal Stock Transfer details added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['updatedSuccess'])){?><div class="alert alert-info">Changes saved success.</div><?php }?>
								
								<?php if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-warning">Deleted ! one Internal Stock Transfer details deleted success.</div><?php } */?>
								
                                    <h1>
									<table class="headertble">
									<tr>
									<td>Manage Internal Stock Transfer</td>
									<td><a href="internal_transfer" title="Add Internal Stock Transfer">&#10011;</a></td>
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
									
									?>
									
<form method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF'];?>">
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
<button type="submit" name="sedatas" class="btn btn-primary"><i class="material-icons">search</i>Search</button>
</div>

<div id="searchbuttoncont">&nbsp;
<button type="button" onclick="javascript:window.location='internal_transfer_manage';" name="sedatas" class="btn btn-danger">Reset</button>
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
													<th>Send From</th>
													<th>Send To</th>
													<th>Invoice Date</th>
                                                    <th>Invoice Number</th>
				<?php $select_prdetails_header="select * from `products` order by `id` asc";
				$fetch_prdetails_header=mysqli_query($db_conn,$select_prdetails_header);
				while($result_prdetails_header=mysqli_fetch_array($fetch_prdetails_header)){?>
				<th><?=$result_prdetails_header['productName'];?></th>
				<?php }?>
				<th>Entered by</th>
													<th>Details</th>
													<th>Print</th>
													
                                                </tr>
                                            </thead>
											
											<tbody>
										<?php 
									$select_product_list12="select distinct`tempid` from `internal_transfer` where date between '$se_fromDate' and '$se_toDate'";
										$fetch_product_list12=mysqli_query($db_conn,$select_product_list12);
										while($ResultRecords12=mysqli_fetch_array($fetch_product_list12))
										{
											
											$tempid=$ResultRecords12["tempid"];
											
										$select_product_list="select * from internal_transfer where tempid='$tempid'";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										$ResultRecords=mysqli_fetch_array($fetch_product_list);
											
											//product details
											$product_id=$ResultRecords['product_id'];
											$select_productDetils="select * from products where id='$product_id'";
						$Fetch_productDetils=mysqli_query($db_conn,$select_productDetils);
						$Result_productDetils=mysqli_fetch_array($Fetch_productDetils);
						
						//SEND FROM
						$send_from=$ResultRecords['send_from'];
						$select_godowndetails="select * from company_godown where id='$send_from' AND " . godown_finance_filter_sql($db_conn);
						$fetch_godowndetails=mysqli_query($db_conn,$select_godowndetails);
						$result_godowndetails=mysqli_fetch_array($fetch_godowndetails);
						
						//SEND TO
						$send_to=$ResultRecords['send_to'];
						$select_godowndetails2="select * from company_godown where id='$send_to' AND " . godown_finance_filter_sql($db_conn);
						$fetch_godowndetails2=mysqli_query($db_conn,$select_godowndetails2);
						$result_godowndetails2=mysqli_fetch_array($fetch_godowndetails2);
						
						$select_INVOICE="select * from internal_transfer_invoice where tempid='$tempid'";
										$fetch_INVOICE=mysqli_query($db_conn,$select_INVOICE);
										$result_INVOICE=mysqli_fetch_array($fetch_INVOICE);
										
										//TOTAL qty
										$select_sUM_QTY="select sum(qty) from internal_transfer where tempid='$tempid'";
										$fetch_sUM_QTY=mysqli_query($db_conn,$select_sUM_QTY);
										$result_sUM_QTY=mysqli_fetch_array($fetch_sUM_QTY);
											?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
													<td><?php echo $result_godowndetails["gname"];?></td>
													<td><?php echo $result_godowndetails2["gname"];?></td>
					<td><?php echo date("d/M/Y",strtotime($ResultRecords["date"]));?></td>
                    <td><?php echo $result_INVOICE["inv_number"];?></td>
					
					<!------------------------PRODUCT WISE SALES QTY------------------------------->
				<?php $select_prdetails_header="select * from `products` order by `id` asc";
				$fetch_prdetails_header=mysqli_query($db_conn,$select_prdetails_header);
				while($result_prdetails_header=mysqli_fetch_array($fetch_prdetails_header)){
					
					$prid_header=$result_prdetails_header['id'];
					
					//SALES QTY
					$select_SUM_QTY="select qty from internal_transfer where tempid='$tempid' and product_id='$prid_header'";
					$fetch_SUM_QTY=mysqli_query($db_conn,$select_SUM_QTY);
					$result_SUM_QTY=mysqli_fetch_array($fetch_SUM_QTY);
					if($result_SUM_QTY['qty']!=NULL){ $slsqty=$result_SUM_QTY['qty'];} else{ $slsqty="0";}
					
					$net_sls_qty=$slsqty;
						
				?>
				<th><?=$net_sls_qty;?></th>
				<?php }?>
				<!-------------------------------------------------------------------->
				
				<td><?=$ResultRecords["username"];?><br/><?=ucwords($ResultRecords["usertype"]);?></td>
													
													<td>
<a href="internal_transfer_details?tempid=<?=$tempid;?>"><img src="../../assets/images/details-32.png"/></a>
													</td>
													
													<td>
<a href="internal_transfer_print?tempid=<?=$tempid;?>" title="Print">
<img src="../../assets/images/print32.png"/></a>
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
</body>

</html>