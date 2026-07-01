<?php include("checksession.php");
include("config.php");
error_reporting(0);

$getinvuser="shop";

//invuser = shop
$displaytitle="Manage Invoice - Shop";
$lablenamedisplay="Shop Name";
$tablename="shop";
	
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
    <style>
        .action-link { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;text-decoration:none;padding:0; }
        .action-link:hover { background:#f3f4f6;border-color:#d1d5db; }
        .action-link.delete:hover { background:#fef2f2;border-color:#fecaca; }
        .actions-group { display:inline-flex;align-items:center;gap:5px;white-space:nowrap; }
    </style>
</head>

<body>


<?php
// Check for error message in session
if (isset($_SESSION['successMessage'])) {
$successMessage = $_SESSION['successMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'success',
                          title: 'Success',
                          text: '<?php echo $successMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['successMessage']); } ?>


<?php
// Check for error message in session
if (isset($_SESSION['errorMessage'])) {
$errorMessage = $_SESSION['errorMessage'];
?>
                      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                      <script>
                        Swal.fire({
                          icon: 'error',
                          title: 'Warning',
                          text: '<?php echo $errorMessage; ?>',
                          confirmButtonText: 'OK'
                        });
					</script>
<?php  unset($_SESSION['errorMessage']); } ?>


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
								
								<?php if(isset($_REQUEST['updatedSuccess'])){?><div class="alert alert-info">Changes saved success.</div><?php }?>
								
								<?php if(isset($_REQUEST['deletedDone'])){?><div class="alert alert-warning">Deleted ! one Invoice details deleted success.</div><?php }?>
								
								
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
									<td><a href="shop-user-invoice-add" title="Add Invoice">&#10011;</a></td>
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
													<th>Entered by</th>
													<th>Print</th>
													<th>Actions</th>
													<th>Return (Credit&nbsp;Note)</th>
													<th>Actions</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $select_product_list="select * from user_invoice where from_user_type='$Login_user_TYPEvl' and to_user_type='$getinvuser' and date between '$from_date' and '$to_date' order by id desc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											//customer details
											$CuSTID=$result_product_list['to_user_id'];
										$select_Customers="select * from ".$tablename." where temp_id='$CuSTID'";
										$fetch_Customers=mysqli_query($db_conn,$select_Customers);
										$result_Customers=mysqli_fetch_array($fetch_Customers);
										//
										$Cust_Name=$result_Customers['name'];
										$Cust_Mbile=$result_Customers['mobile_number'];
											
											$RowID_encode=base64_encode($result_product_list["id"]);
											$INVID_encode=base64_encode($result_product_list["inv_id"]);
											
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
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo $result_product_list["inv_number"];?></td>
													<td><?php echo $Cust_Name;?><br/>M:&nbsp;<?php echo $Cust_Mbile;?>
													
													<!------------UPDATE CUSTOMER------------->
			<?php 
			//COUNT return
			$select_count_return="select * from user_return_stock_items where invnumber='".$result_product_list["inv_id"]."'";
			$fetch_count_return=mysqli_query($db_conn,$select_count_return);
			$result_count_return=mysqli_num_rows($fetch_count_return);
			if($result_count_return==0){
			?>
													
													<a href="update_customer2.php?invuser=<?=$getinvuser;?>&&InvoiceID=<?=$result_product_list["inv_id"];?>" style="text-decoration:none;"><span class='badge badge-style-bordered badge-primary'>Update</span>
													</a>
			<?php } else{ echo "<span id='cnlable'>-&nbsp;CN&nbsp;-</span>";}?>
			<!----------END CUSTOMER UPDATE***--------->
			
			</td>
													<td><?php echo date("d/M/Y",strtotime($result_product_list["date"]));?></td>
													
				<!-----<td><?php echo number_format($result_product_list["sub_total"],2,'.','');?></td>
				<td><?php echo number_format($result_product_list["discount"],2,'.','');?></td>--->
				<td><?php echo number_format($result_product_list["total"],2,'.','');?>
				<br/><a href="add-receipt?invid=<?=$result_product_list["inv_id"];?>&&invuser=<?=$getinvuser;?>"><?=$msgpayment;?></a>
				</td>
				
				
				<td><?=$result_product_list['usertype'];?><br/><?=$result_product_list['username'];?></td>
													
									
													<td>
													<?php if($result_product_list["sub_total"]>0){?>
<a href="shop-user-invoice-print?invoiceid=<?php echo base64_encode($result_product_list["inv_id"]);?>" title="Print">
<img src="../../assets/images/print32.png"/></a>
<?php }else{?>
<span class='badge badge-style-bordered badge-danger'>Incomplete</span>
<?php }?>
													</td>
													
													
																										<td>
													    <div class="actions-group">
													        <a href="shop-user-invoice-add?invuser=<?=$vld1;?>&&action=edit&&InvoiceID=<?=$vld2;?>&&gid=<?=$vld3;?>" class="action-link" title="Edit"><i class="material-icons-outlined" style="font-size:17px;color:#667eea;">edit</i></a>
													    </div>
													</td>
													
													<td>
													<?php if($result_product_list["sub_total"]>0){?>
<a href="cnote_new.php?invuser=<?=$getinvuser;?>&&InvoiceID=<?=$INVID_encode;?>">
<span class="badge badge-warning">Return</span></a>
<?php } else{ echo "---";}?>
</td>


<td>
    <div class="actions-group">
        <a href="delinvoice?invtype=shop&&invuser=<?=$getinvuser;?>&&invid=<?=$INVID_encode;?>" class="action-link delete" title="Delete" onclick="return confirm('You want to delete confirm?');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
    </div>
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