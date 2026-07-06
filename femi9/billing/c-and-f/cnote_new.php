<?php include("checksession.php"); 
date_default_timezone_set("Asia/Kolkata"); 
error_reporting(0);
include("config.php");

if($_REQUEST['invuser']!=NULL)
{
$getinvuser=$_REQUEST['invuser'];
$_SESSION['invuser']=$getinvuser;
}else{
	$getinvuser=$_SESSION['invuser'];
}

	$displaytitle="Add Stock Return";
	
	$InvoiceID=$_REQUEST['InvoiceID'];
	$invid_decode=base64_decode($InvoiceID);
	
	
	if($getinvuser=="customer")
	{
		
	//get invoice details
	$select_invoicedetails="select * from invoice where inv_id='$invid_decode'";
	$fetch_invoicedetails=mysqli_query($db_conn,$select_invoicedetails);
	$result_invoicedtails=mysqli_fetch_array($fetch_invoicedetails);
	
	//Return stock send users
	$fromusertype="customer";
	$fromuserid=$result_invoicedtails['customer_id'];
	
	//Return stock received users
	$tousertype=$result_invoicedtails['user_type'];
	$touserid=$result_invoicedtails['user_id'];
		
	}else{
	//ss, stockist, distributor, shop
	//get invoice details
	$select_invoicedetails="select * from user_invoice where inv_id='$invid_decode'";
	$fetch_invoicedetails=mysqli_query($db_conn,$select_invoicedetails);
	$result_invoicedtails=mysqli_fetch_array($fetch_invoicedetails);
	
	//Return stock send users
	$fromusertype=$result_invoicedtails['to_user_type'];
	$fromuserid=$result_invoicedtails['to_user_id'];
	
	//Return stock received users
	$tousertype=$result_invoicedtails['from_user_type'];
	$touserid=$result_invoicedtails['from_user_id'];
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
    <title><?php echo $displaytitle;?> : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">

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
                        <br/>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
									<?php if($getinvuser=="customer"){?>
		<a href="customer-user-manage-invoice" id="linkbackvl">&#8630;&nbsp;Go Back</a>
									<?php }else{?>
		<a href="user-manage-invoice?invuser=<?=$getinvuser;?>" id="linkbackvl">&#8630;&nbsp;Go Back</a>
									<?php }?>
									
								<h1>
									<table class="headertble">
									<tr>
									<td><?=$displaytitle;?>
									<br/>
					<div style="font-size:15px;margin-top:10px;">Invoice Number:-</div>
					<div style="font-size:22px;font-weight:600;color:blue;"><?=$result_invoicedtails['inv_number'];?></div>
					<div style="font-size:12px;margin-top:5px;"><?=$getinvuser;?></div>
									</td>
									<td>&nbsp;</td>
									</tr>
									</table>
									</h1>

<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->

<?php if(isset($_REQUEST['invalidqty'])){?><div class="alert alert-warning">Warning ! Invalid Qty</div><?php }?>

<?php if(isset($_REQUEST['productalreadyexists'])){?><div class="alert alert-warning">Warning ! This Product Already Returned!</div><?php }?>

<?php if(isset($_REQUEST['addedsuccess'])){?><div class="alert alert-success">Return product added success.</div><?php }?>

<?php if(isset($_REQUEST['DeleteSuccess'])){?><div class="alert alert-danger">Success ! Deleted</div><?php }?>


										<div class="card-footer">
                                        <div class="row invoice-summary">
										
	
	<div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <table id="datatable1" class="display" style="width:100%;">
										
                                            <thead>
                                                <tr>
													<th>Product Description</th>
                                                    <th>Sales Qty</th>
                                                </tr>
                                            </thead>
											
											<tbody>
		<?php 
		
		if($getinvuser=="customer")
	{
		$select_product_list="select * from invoice_items where inv_id='$invid_decode' order by id asc";
	}else{
		$select_product_list="select * from user_invoice_items where inv_id='$invid_decode' order by id asc";
	}
		$fetch_product_list=mysqli_query($db_conn,$select_product_list);
		while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
											$product_id=$result_product_list["pr_id"];
											
											//get product dtails
		$select_prdetails="select * from products where id='$product_id'";
	$fetch_prdetails=mysqli_query($db_conn,$select_prdetails);
	$result_prdetails=mysqli_fetch_array($fetch_prdetails);
	$productname=$result_prdetails['productName'];
	
											?>
                                                <tr>
													<td><?php echo $productname;?></td>
                                                    <td><?php echo $result_product_list["qty"];?></td>
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
                                        </table>
                                    
									</div>
									</div>
									</div>
									</div>
									
<form action="cnote_action.php" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">									
						
<?php 
$get_returnid=base64_decode($_REQUEST['returnid']);

if($_REQUEST['returnid']==NULL)
{
function GeraHash($qtd){ $Caracteres = '123456789'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } ; 
$randum_number=GeraHash(10);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("gis"); 
$return_id="".$randum_number."RTN".$temp_date."".$temp_time."";
}else{$return_id=$get_returnid;}
?>

<input type="hidden" name="returnid" value="<?=$return_id;?>">
<input type="hidden" name="invid" value="<?=$invid_decode;?>">
						
<input type="hidden" name="from_usertype" value="<?=$fromusertype;?>">
<input type="hidden" name="from_userid" value="<?=$fromuserid;?>">

<input type="hidden" name="to_usertype" value="<?=$tousertype;?>">
<input type="hidden" name="to_userid" value="<?=$touserid;?>">
									
									<label class="form-label">Product Name*</label>
<select required="" name="prid" class="form-control">
<option value="" hidden="">Select</option>
<?php 
if($getinvuser=="customer")
	{
		$select_product_list12="select * from invoice_items where inv_id='$invid_decode' order by id asc";
	}else{
$select_product_list12="select * from user_invoice_items where inv_id='$invid_decode' order by id asc";
	}
		$fetch_product_list12=mysqli_query($db_conn,$select_product_list12);
		while($result_product_list12=mysqli_fetch_array($fetch_product_list12))
										{
											
										$product_idshow=$result_product_list12["pr_id"];
										
$select_product_list="select * from products where id='$product_idshow'";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);
while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
										?>
<option value="<?=$result_product_list['id'];?>"><?=$result_product_list['productName'];?></option>
										<?php }?>
										<?php }?>
</select>
<br/>

<label class="form-label">Returned Qty*</label>
<input type="number" required="" min="0" max="99999" name="returnqty" class="form-control">
<br/>

<label class="form-label">Damaged Qty*</label>
<input type="number" required="" min="0" max="99999" name="damaged_qty" class="form-control">
<br/>

<button type="submit" name="add-return" class="btn btn-primary" style="width:100%;"><i class="material-icons">add</i>Add</button>
		
		</form>
		
		
		<?php if($_REQUEST['returnid']!=NULL){?>
<!----------------------------------------------------------------------------->			<!----------------------------------------------------------------------------->
<!----------------------------------------------------------------------------->		
		
		<?php 
		$select_InvoieDetails234="select * from user_return_stock where returnid='$get_returnid'";
		$fetch_InvoieDetails234=mysqli_query($db_conn,$select_InvoieDetails234);
		$resultReturnDtails=mysqli_fetch_array($fetch_InvoieDetails234);
		?>
		
		<div style="clear:both;"></div>
		<br/>
		<div class="row">
                                            <div class="table-responsive">
                                                <table class="table invoice-table">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">#</th>
                                                            <th scope="col">Product Description</th>
                                                            <th scope="col">Qty</th>
															<th scope="col">MRP</th>
                                                            <th scope="col">Amount</th>
															<th scope="col">GST</th>
															<th scope="col">Total</th>
                                                            <th scope="col"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
													<?php
	$select_INVProductDetails="select * from user_return_stock_items where returnid='$get_returnid' order by id desc";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	$count_products_return=mysqli_num_rows($fetch_INVProductDetails);
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
<td><?=$result_ProductDetails123['productName'];?></td>
<td><?=$result_INVProductDetails['qty'];?></td>
<td>&#8377;<?php echo inr_format($result_INVProductDetails['amount'], 2);?></td>
<td align="right"><?php echo inr_format($result_INVProductDetails['subtotal'], 2);?></td>
<td><?=inr_format($result_INVProductDetails['gstamount_total'], 2);?> (<?=$result_INVProductDetails['gst_percentage'];?>%)</td>
<td align="right"><?php echo inr_format($TotalAMount, 2);?></td>
<td>
<a href="cnote_delete.php?returnid=<?=$_REQUEST['returnid'];?>&&rowid=<?=$ItemRowid;?>&&InvoiceID=<?=$InvoiceID;?>&&redirurl=cnote_new&&ActionDel"onclick="return confirm('You want to delete confirm?');"><span class="badge bg-danger">Remove</span></a>
</td>
                                                        </tr>
                                                        
	<?php }?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
										
										
										<div class="card-footer">
                                        <div class="row invoice-summary">
                                            <div class="col-lg-4">
                                              
                                            </div>
                                            <div class="col-lg-5"></div>
											
											<script>
  function totalamount(){
   var subtotal = document.getElementById('subtotal').value;
   var discount = document.getElementById('discount').value;
   document.getElementById('outputTotalamount').value = (subtotal*1)-(discount*1); 
 }
</script>

                                            <div class="col-lg-3">
                                                <div class="invoice-info">
												
												<?php if($count_products_return>0){?>
												
<form action="cnote_finish.php" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">
<input type="hidden" name="returnid" value="<?=$_REQUEST['returnid'];?>"/>
<input type="hidden" name="SubTotal" value="<?=$TotalAMount123;?>"/>


            <p class="bold">Subtotal <span>
			<input type="number" min="0" value="<?=$TotalAMount123;?>" id="subtotal" disabled></span>
			</p>
			<br/>
													
           <p class="bold">Discount <span>
		   <?php if($resultReturnDtails['discount']==0){?>
		  <input type="number" onkeyup="totalamount()" id="discount" value="0" min="0" name="discount" required="">
		   <?php }else{ ?>
		   <input type="number" value="<?=$resultReturnDtails['discount'];?>" onkeyup="totalamount()" id="discount" min="0" name="discount" required="">
		   <?php }?>
		 </span></p>
													<br/>
         <p class="bold">Total <span>
		 <?php if($resultReturnDtails['discount']==0){?>
		 <input type="number" min="0" value="<?=$TotalAMount123;?>" id="outputTotalamount" disabled>
		 <?php }else{ 
		 $TotalAmount_display=$TotalAMount123-$resultReturnDtails['discount'];
		 ?>
		  <input type="number" min="0" value="<?=$TotalAmount_display;?>" id="outputTotalamount" disabled>
		   <?php }?>
		 </span>
		 </p>
													<div style="clear:both;"></div>
                                                    <div class="invoice-info-actions">
													
     <button class="btn btn-primary" type="submit" name="invoice-submit" style="width:100%;">Submit Return</button>
													
                                                    </div>
													
													</form>
													
													<?php } 
													
													/* else {?><p style="color:red;">Please enter any one return values !</p><?php } */ 
													?>
													
                                                </div>
                                            </div>
											
                                        </div>
                                    </div>
										
			<!----------------------------------------------------------------------------->			<!----------------------------------------------------------------------------->
			<!----------------------------------------------------------------------------->
			
		<?php }?>
										
                                            <div class="col-lg-5"></div>
											
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
        </div>
    </div>
    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>