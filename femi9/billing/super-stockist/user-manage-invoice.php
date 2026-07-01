<?php include("checksession.php");
include("config.php");
error_reporting(0);

$getinvuser=$_REQUEST['invuser'];
//invuser = stockiest
//invuser = distributor
//invuser = shop

if($getinvuser=="stockiest")
{
	$displaytitle="Manage Invoice - Stockist";
	$lablenamedisplay="Stockist Name";
	$tablename="stockiest";
	}
else if($getinvuser=="super_distributor")
{
	$displaytitle="Manage Invoice - Super Distributor";
	$lablenamedisplay="Super Distributor Name";
	$tablename="super_distributor";
	}
	else if($getinvuser=="distributor")
{
	$displaytitle="Manage Invoice - Distributor";
	$lablenamedisplay="Distributor Name";
	$tablename="distributor";
	}
else
{
	//$displaytitle="Manage Invoice - Shop";
	//$lablenamedisplay="Shop Name";
	//$tablename="shop";
	}
	
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

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
</head>

<body>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">


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
								
                                    <h1>
									<table class="headertble">
									<tr>
									<td><?=$displaytitle;?></td>
									<td><a href="user-invoice-add.php?invuser=<?=$getinvuser;?>" title="Add Invoice">&#10011;</a></td>
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
													<th>Reward Points</th>
													<th>Print</th>
													<th>Edit</th>
													<th>Return (Credit&nbsp;Note)</th>
													<th>Delete</th>
												</tr>
                                            </thead>
											
											<tbody>
										<?php $select_product_list="select * from user_invoice where from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and to_user_type='$getinvuser' order by id desc";
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
											?>
                                            
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    
													
													<!--delivery note form open--->		
													<td><a href="#" id="linkcaption" data-bs-toggle="modal" data-bs-target="#exampleModalLive<?php echo $result_product_list["id"];?>">
													<?php echo $result_product_list["inv_number"];?></a></td>
													
													<div class="modal fade" id="exampleModalLive<?php echo $result_product_list["id"];?>" tabindex="-1" aria-labelledby="exampleModalLiveLabel" aria-hidden="true">
													
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLiveLabel">Update Delivery Note<br/>
																<?php echo $result_product_list["inv_number"];?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
									<form method="post" onsubmit="return confirm('Please make a confirm!');" enctype="multipart/form-data" action="dlnote_action">	
									
<?php 
$Select_DLDetails="select * from delivery_note where inv_id='".$result_product_list["inv_id"]."'";
$Fetch_DLDetails=mysqli_query($db_conn,$Select_DLDetails);
$Result_DLDetails=mysqli_fetch_array($Fetch_DLDetails);
?>
									
									<input type="hidden" name="inv_id" value="<?php echo $result_product_list["inv_id"];?>">
									<input type="hidden" name="inv_number" value="<?php echo $result_product_list["inv_number"];?>">
									<input type="hidden" name="inv_table" value="user">
															
                                                            <div class="example-content" style="padding:20px;">
                                                <div class="form-floating mb-3">
                                                    <input type="text" name="dl_note" placeholder="Delivery Note" class="form-control" id="floatingInput" value="<?=$Result_DLDetails['dl_note'];?>">
                                                    <label for="floatingInput">Delivery Note</label>
                                                </div>
                                                <div class="form-floating mb-3">
                                                    <input type="text" name="mode_pmnt" placeholder="Mode/Terms of Payment" class="form-control" id="floatingPassword" value="<?=$Result_DLDetails['mode_pmnt'];?>">
                                                    <label for="floatingPassword">Mode/Terms of Payment</label>
                                                </div>
												<div class="form-floating mb-3">
                                                    <input type="text" name="ref_no" placeholder="Reference No." class="form-control" id="floatingPassword" value="<?=$Result_DLDetails['ref_no'];?>">
                                                    <label for="floatingPassword">Reference No.</label>
                                                </div>
												<div class="form-floating mb-3">
                                                    <input type="date" placeholder="Reference Date." class="form-control" id="floatingPassword" name="ref_date" value="<?=$Result_DLDetails['ref_date'];?>">
                                                    <label for="floatingPassword">Reference Date.</label>
                                                </div>
												<div class="form-floating mb-3">
                                                    <input type="text" placeholder="Other References" class="form-control" id="floatingPassword" name="ot_ref" value="<?=$Result_DLDetails['ot_ref'];?>">
                                                    <label for="floatingPassword">Other References</label>
                                                </div>
												<div class="form-floating mb-3">
                                                    <input type="text" placeholder="Buyer's Order No." class="form-control" id="floatingPassword" name="order_no" value="<?=$Result_DLDetails['order_no'];?>">
                                                    <label for="floatingPassword">Buyer's Order No.</label>
                                                </div>
												<div class="form-floating mb-3">
                                                    <input type="date" placeholder="Dated" class="form-control" id="floatingPassword" name="dated" value="<?=$Result_DLDetails['dated'];?>">
                                                    <label for="floatingPassword">Dated</label>
                                                </div>
												<div class="form-floating mb-3">
                                                    <input type="text" placeholder="Dispatch Doc No." class="form-control" id="floatingPassword" name="dispatch_doc_no" value="<?=$Result_DLDetails['dispatch_doc_no'];?>">
                                                    <label for="floatingPassword">Dispatch Doc No.</label>
                                                </div>
												<div class="form-floating mb-3">
                                                    <input type="date" placeholder="Delivery Note Date" class="form-control" id="floatingPassword" name="dlnote_date" value="<?=$Result_DLDetails['dlnote_date'];?>">
                                                    <label for="floatingPassword">Delivery Note Date</label>
                                                </div>
												<div class="form-floating mb-3">
                                                    <input type="text" placeholder="Dispatched through" class="form-control" id="floatingPassword" name="dispatch_through" value="<?=$Result_DLDetails['dispatch_through'];?>">
                                                    <label for="floatingPassword">Dispatched through</label>
                                                </div>
												<div class="form-floating mb-3">
                                                    <input type="text" placeholder="Destination" class="form-control" id="floatingPassword" name="destination" value="<?=$Result_DLDetails['destination'];?>">
                                                    <label for="floatingPassword">Destination</label>
                                                </div>
												<div class="form-floating mb-3">
                                                    <input type="text" placeholder="Terms of Delivery" class="form-control" id="floatingPassword" name="terms" value="<?=$Result_DLDetails['terms'];?>">
                                                    <label for="floatingPassword">Terms of Delivery</label>
                                                </div>
												
												<button type="submit" name="UpdateDlNote" class="btn btn-primary"><i class="material-icons">update</i>Update</button>
												
                                            </div>
											
											</form>
                                                        </div>
                                                    </div>
													
                                                </div>
												<!--delivery note form close--->
												
												
													<td><?php echo $Cust_Name;?><br/>M:&nbsp;<?php echo $Cust_Mbile;?>
			
			<!------------UPDATE CUSTOMER------------->
			<?php 
			//COUNT return
			$select_count_return="select * from user_return_stock_items where invnumber='".$result_product_list["inv_id"]."'";
			$fetch_count_return=mysqli_query($db_conn,$select_count_return);
			$result_count_return=mysqli_num_rows($fetch_count_return);
			if($result_count_return==0){
			?>
													
													<a href="update_customer.php?invuser=<?=$getinvuser;?>&&InvoiceID=<?=$result_product_list["inv_id"];?>" style="text-decoration:none;"><span class='badge badge-style-bordered badge-primary'>Update</span>
													</a>
			<?php } else{ echo "<span id='cnlable'>-&nbsp;CN&nbsp;-</span>";}?>
			<!----------END CUSTOMER UPDATE***--------->
													
													</td>
													
													
													<td><?php echo date("d/M/Y",strtotime($result_product_list["date"]));?></td>
													
				<!-----<td><?php echo number_format($result_product_list["sub_total"],2,'.','');?></td>
				<td><?php
$discount=$result_product_list["discount"]+$result_product_list["credit"];
				echo number_format($discount,2,'.','');?>
				</td>--->
				
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
				<td><?php echo number_format($result_product_list["total"],2,'.','');?>
				<br/><a href="add-receipt.php?invid=<?=$result_product_list["inv_id"];?>&&invuser=<?=$getinvuser;?>"><?=$msgpayment;?></a>
				</td>
				
				
				<td>
				<?php if($result_product_list["sub_total"]>0){?>
				
				<?php if($result_product_list['rwpoints_enable']==1){?>
				<a href="update_rwpermission?invoiceid=<?php echo base64_encode($result_product_list["inv_id"]);?>&&rwst=<?=$result_product_list['rwpoints_enable'];?>&&invuser=<?=$getinvuser?>&&invnumber=<?=base64_encode($result_product_list["inv_number"]);?>" onclick="return confirm('You want to confirm update to Disabled?');"><span class='badge badge-style-bordered badge-success'>Enable</span></a>
				<?php }else{?>
				<a href="update_rwpermission?invoiceid=<?php echo base64_encode($result_product_list["inv_id"]);?>&&rwst=<?=$result_product_list['rwpoints_enable'];?>&&invuser=<?=$getinvuser?>&&invnumber=<?=base64_encode($result_product_list["inv_number"]);?>" onclick="return confirm('You want to confirm update to Enable?');"><span class='badge badge-style-bordered badge-danger'>Disabled</span></a>
				<?php }?>
				
				<?php }else{ echo "---";}?>
				</td>
													
									
													
													<td>
													<?php if($result_product_list["sub_total"]>0){?>
<a href="user-invoice-print?invoiceid=<?php echo base64_encode($result_product_list["inv_id"]);?>" title="Print">
<img src="../../assets/images/print32.png"/></a>
<?php }else{?>
<span class='badge badge-style-bordered badge-danger'>Incomplete</span>
<?php }?>
													</td>
													
													<td>
<a href="user-invoice-add.php?invuser=<?=$getinvuser;?>&&InvoiceID=<?=$INVID_encode;?>&&action=edit" title="Edit"><img src="../../assets/images/edit-32.png"/></a>
													</td>
													
													<td>
						<?php if($result_product_list["sub_total"]>0){?>
<a href="cnote_new.php?invuser=<?=$getinvuser;?>&&InvoiceID=<?=$INVID_encode;?>">
<span class="badge badge-warning">Return</span></a>
<?php } else{ echo "---";}?>
</td>


<td>
													<?php if($result_product_list["sub_total"]==0){?>
<a href="delinvoice?invtype=noncustomer&&invuser=<?=$getinvuser;?>&&invid=<?=$INVID_encode;?>"onclick="return confirm('You want to delete confirm?');" title="Delete">
<img src="../../assets/images/delete-32.png"/></a>
<?php } else{ echo "---";}?>

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