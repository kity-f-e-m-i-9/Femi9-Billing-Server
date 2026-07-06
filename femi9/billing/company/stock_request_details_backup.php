<?php include("checksession.php"); 
date_default_timezone_set("Asia/Kolkata"); 
error_reporting(0);
include("config.php");

	$displaytitle="Stock Request Details";
	$lablenamedisplay="Stock Request";
	
	$get_req_id=$_REQUEST['reqid'];
	$get_req_id_DECODE=base64_decode($get_req_id);
	//
	 $select_requestdetails="select * from stock_request where reqid='$get_req_id_DECODE'";
	 $fetch_requestdetails=mysqli_query($db_conn,$select_requestdetails);
	 $result_requestdetails=mysqli_fetch_array($fetch_requestdetails);
	 
	 $verified_accounts=$result_requestdetails['verified'];
	
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
	<link href="../../assets/css/vlstyle.css" rel="stylesheet">

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
                            <div class="col-md-12">
                                <div class="card">
                                   
                                    <div class="card-body">
								<h1>
									<table class="headertble">
									<tr>
									<td><?=$displaytitle;?></td>
									<td><a href="stock_request_pending" title="Manage Request">&#9776;</a></td>
									</tr>
									</table>
									</h1>
									

<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->

<?php if(isset($_REQUEST['AddedSuccess'])){?><div class="alert alert-success">one product added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['ItemAlreadyExists'])){?><div class="alert alert-danger">invalid product, already exists.</div><?php }?>
								
								<?php if(isset($_REQUEST['InvalidStock'])){?><div class="alert alert-danger">invalid qty, out of stock.</div><?php }?>
								
								<?php if(isset($_REQUEST['DeleteSuccess'])){?><div class="alert alert-danger">Deleted ! one product deleted success.</div><?php }?>




<div class="card-footer">
                                        <div class="row invoice-summary">
<label class="form-label">Date of stock request</label>
<input type="date" value="<?=date("Y-m-d",strtotime($result_requestdetails['date']));?>" disabled required="" class="form-control">
</br>	
										
		<!-------------------------SHOP CURRENT STOCK-------------------------------->
		<div style="background:#fff;overflow:scroll;width:100%;margin-top:20px;">
		<table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">Product</th>
                                                            <th scope="col">Qty</th>
															<th scope="col"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
										<?php 
					$select_productCurrentst="select * from stock_request_items where reqid='$get_req_id_DECODE' order by id asc";
					$fetch_productCurrentst=mysqli_query($db_conn,$select_productCurrentst);
					while($result_productCurrentst=mysqli_fetch_array($fetch_productCurrentst))
										{
											$prid=$result_productCurrentst['prid'];
											//
											 $select_pramount="select * from products where id='$prid'";
	 $fetch_pramount=mysqli_query($db_conn,$select_pramount);
	 $result_pramount=mysqli_fetch_array($fetch_pramount);
											
											if($result_productCurrentst['qty']>0)
											{
										?>
        <input type="hidden" name="prid[]" value="<?=$prid; ?>"/>
        <tr>
        <td><a href="#" class="popup-trigger"><?php echo $result_pramount["productName"];?></a></td>
		<td><?=$result_productCurrentst['qty'];?></td>
		<td style="display:none;"><?=$result_productCurrentst['amount'];?></td>
		<td style="display:none;"><?=$result_productCurrentst['subtotal'];?></td>
		<td style="display:none;"><?=$result_productCurrentst['gsttotal'];?> (<?=$result_productCurrentst['gst'];?>%)</td>
		<td align="right" style="display:none;"><?=inr_format($result_productCurrentst['total'], 2);?></td></td>
		
		<td>
		<?php 
		if($verified_accounts!=0)
		{
		$countinvpr="select count(*) as numcountinvpr from user_invoice_items where inv_id='$get_req_id_DECODE' and pr_id='$prid'";
		$fetch_countinvpr=mysqli_query($db_conn,$countinvpr);
		$result_countinvpr=mysqli_fetch_array($fetch_countinvpr);
		if($result_countinvpr['numcountinvpr']==0){
		?>
		<a href="convert-invoice?reqid=<?=$get_req_id;?>&&prid=<?=$prid?>&&convertinvoice" style="text-decoration:none;" data-bs-toggle="tooltip" data-bs-placement="top" title="Add Product to Invoice"><i class="material-icons">add_circle_outline</i></a>
		<?php }else{ echo "<i class='material-icons-two-tone'>done</i>";}?>
		
		<?php }?>
		</td>
                                                </tr>
                                           
										<?php }?>
										<?php }?>
										
										 </tbody>
													
													</table>
													
													
													
													<div id="popup" class="popup">
    <h2>Stock Request Details</h2>
    <div id="popup-content">
        <!-- Content will be loaded dynamically -->
    </div>
    <a href="#" id="close-popup"><img src="../../assets/images/close 32.png"></a>
</div>

<script src="../../assets/js/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function(){
    // Show popup when button is clicked
    $('.popup-trigger').click(function(){
        var rowData = $(this).closest('tr').find('td').map(function(){
            return $(this).text();
        }).get();

        // Populate popup content with row data
       $('#popup-content').html("<p>Product Name : <b>" + rowData[0] + "</b></p><p>Qty : <b>" + rowData[1] + "</b></p><p>Amount : <b>" + rowData[2] + "</b></p><p>Subtotal : <b>" + rowData[3] + "</b></p><p>GST : <b>" + rowData[4] + "</b></p><p>Total : <b>" + rowData[5] + "</b></p>");

        // Show the popup
        $('#popup').fadeIn();
    });

    // Close popup when close button is clicked
    $('#close-popup').click(function(){
        $('#popup').fadeOut();
    });
});
</script>
			
			
			
													</div>
												
		<!------------------------------------------------------------------------------>
		<!------------------------------------------------------------------------------>
		
<?php 
$select_InvoieDetails="select * from user_invoice where inv_id='$get_req_id_DECODE'";
		$fetch_InvoieDetails=mysqli_query($db_conn,$select_InvoieDetails);
		$result_InvoieDetails=mysqli_fetch_array($fetch_InvoieDetails);
		if($result_InvoieDetails['inv_number']!=NULL){
		?>


<div class="table-responsive">
                                                <table class="table invoice-table">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">#</th>
                                                            <th scope="col">Product Description</th>
                                                            <th scope="col">Qty</th>
															<th scope="col">MRP</th>
                                                            <th scope="col">Total</th>
                                                            <th scope="col"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
													<?php
	$select_INVProductDetails="select * from user_invoice_items where inv_id='$get_req_id_DECODE' order by id desc";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	while($result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails))
	{
	
	//product dteails
		$InV_Product_ID=$result_INVProductDetails['pr_id'];
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
<td align="right"><?php echo inr_format($TotalAMount, 2);?></td>
<td>
<a href="del-inv-product2?rowid=<?=$ItemRowid;?>&&reqid=<?=$get_req_id;?>&&truncate"onclick="return confirm('You want to delete confirm?');"><span class="badge bg-danger">Remove</span></a>
</td>
                                                        </tr>
                                                        
	<?php }?>
                                                    </tbody>
                                                </table>
                                            </div>
											
									
<!----------------------------------Footer--------------------------------------->									
											
										<div class="">
                                        <div class="row invoice-summary">
                                            <div class="col-lg-4">
                                                <div class="invoice-info">
												<h3>Invoice Number</h3>
												<h6><?php echo $result_InvoieDetails['inv_number'];?></h6>
												<hr/>
												
												<h3>Invoice Date</h3>
												<h6><?php echo date("d/M/Y",strtotime($result_InvoieDetails['date']));?></h6>
												
                                                </div>
												<hr/>
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
<form action="convert-invoice-submit" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">
<input type="hidden" name="invoice_id" value="<?=$get_req_id_DECODE;?>"/>
<input type="hidden" name="SubTotal" value="<?=$TotalAMount123;?>"/>

			<h4>Sub Total</h4>
			<p><input type="number" min="0" value="<?=$TotalAMount123;?>" class="footerinput1" id="subtotal" disabled></p>
								

<h4>Discount</h4>								
           <p>
	<input type="number" onkeyup="totalamount()" value="0" class="footerinput2" id="discount" min="0" name="discount" required="">
	</p>

         <h4>Total</h4>
		 <p>
		 <input type="number" min="0" value="<?=$TotalAMount123;?>" class="footerinput1" id="outputTotalamount" disabled>
		 </p>
													<div style="clear:both;"></div>
                                                    <div class="invoice-info-actions">
     <button class="btn btn-primary footersubact" type="submit" name="invoice-submit" style="width:100%;">Submit Invoice</button>
                                                    </div>
													
													</form>
													
                                                </div>
                                            </div>
											
											
											
                                        </div>
                                    </div>
		<?php }?>

<!----------------------------------Footer--------------end****------------------------->


<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
		
                                           
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