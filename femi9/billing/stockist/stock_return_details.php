<?php include("checksession.php");
include("config.php");
 error_reporting(0);
 
 $returnid=$_REQUEST['returnid'];
 $returnid_decode=base64_decode($returnid);
 //
 $select_details123="select * from user_return_stock where returnid='$returnid_decode'";
				$fetch_details123=mysqli_query($db_conn,$select_details123);
				$result_details123=mysqli_fetch_array($fetch_details123);
				
				$urlname=$_REQUEST['pagename'];
 
 ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Stock Return Details : <?php echo $business_name;?></title>

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
								
								<a href="<?=$urlname;?>.php" id="linkbackvl">&#8630;&nbsp;Go Back</a>
								
                                    <h1>
									Stock Return Details
									<br/>
									<div style="font-size:14px;margin-top:10px;">Invoice Number:-</div>
									<div style="font-size:17px;"><?=$result_details123['invnumber'];?></div>
									<br/>
									
									<?php 
			if($result_details123['status']=="pending")
			{
			echo "<span class='badge badge-style-bordered badge-danger'>Pending</span>";
			}
			else if($result_details123['status']=="accept")
			{
			echo "<span class='badge badge-style-bordered badge-success'>Accept</span>";
			}else{
				echo "<span class='badge badge-style-bordered badge-danger'>Rejected</span>";
			}
			?>
			
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
                                   <div class="card-body" style="overflow:scroll !important;">
                
<form action="stock_return_update" method="GET" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">
				
<table id="datatable1">
                                            <thead>
                                                <tr>
                                                            <th scope="col">#</th>
															<th>ID</th>
                                                            <th scope="col">Product Description</th>
                                                            <th scope="col">Qty</th>
															<th scope="col">Damaged Qty</th>
															<th scope="col">MRP</th>
                                                            <th scope="col">Amount</th>
															<th scope="col">GST</th>
															<th scope="col">Total</th>
                                                        </tr>
                                            </thead>
											
											<tbody>
													<?php
	$select_INVProductDetails="select * from user_return_stock_items where returnid='$returnid_decode' order by id desc";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	while($result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails))
	{
	
	//product dteails
		$InV_Product_ID=$result_INVProductDetails['prid'];
		$select_ProductDetails123="select * from products where id='$InV_Product_ID'";
		$fetch_ProductDetails123=mysqli_query($db_conn,$select_ProductDetails123);
		$result_ProductDetails123=mysqli_fetch_array($fetch_ProductDetails123);
		
		$TotalAMount=$result_INVProductDetails['total'];
		$TotalAMount123+=$TotalAMount;
		
		$ItemRowid=base64_encode($result_INVProductDetails['id']);
	?>
                                                        <tr>
<th scope="row"><?php echo $rd=$rd+1;?></th>
<td><input type="text" readonly class="form-control" style="width:50px;" name="rtnid[]" value="<?=$result_INVProductDetails['id'];?>"></td>

<td><?=$result_ProductDetails123['productName'];?></td>
<td><?=$result_INVProductDetails['qty'];?></td>

<td>
<?php if($result_details123['status']=="pending"){?>
<input type="number" name="damaged_qty[]" class="form-control" style="width:100px;" min="0" max="<?=$result_INVProductDetails['qty'];?>" required="">
<?php }else{?>
<span style="color:red;"><?=$result_INVProductDetails['damaged_qty'];?></span>
<?php }?>
</td>

<td>&#8377;<?php echo number_format($result_INVProductDetails['amount'],2,'.','');?></td>
<td>&#8377;<?php echo number_format($result_INVProductDetails['subtotal'],2,'.','');?></td>
<td><?=number_format($result_INVProductDetails['gstamount_total'],2,'.','');?> (<?=$result_INVProductDetails['gst_percentage'];?>%)</td>
<td align="right"><?php echo number_format($TotalAMount,2,'.','');?></td>

                                                        </tr>
                                                        
	<?php }?>
                                                    </tbody>
													</table>
													
													
													<?php if($result_details123['status']=="pending"){?>
													
													<h1>Update Status</h1>
													

<input type="hidden" name="returnid" value="<?=$returnid;?>">	
<input type="hidden" name="urlname" value="<?=$urlname;?>">									   

                                        <div class="example-container">
                                        <div class="example-content">
          
<label class="form-label">Status*</label>
<select required="" name="updatestatus" class="form-control">
<option value="" hidden="">Select</option>
<option value="accept">Accept</option>
<option value="reject">Reject</option>
</select>
<br/>
																
<button type="submit" name="add-customer" class="btn btn-primary"><i class="material-icons">add</i>Update</button>
												
                                            </div>
                                        </div>
										
										
													<?php }?>
										
										
										</form>
										
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