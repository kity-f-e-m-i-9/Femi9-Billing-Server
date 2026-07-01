<?php include("checksession.php"); 
header("Location: dashboard.php"); exit;
date_default_timezone_set("Asia/Kolkata"); 
error_reporting(0);
include("config.php");

$getinvuser="shop";
//invuser = shop

$get_action=$_REQUEST['action'];
$_SESSION['ACTIONEDIT']=$get_action;

	$displaytitle="Invoice - Shop";
	$lablenamedisplay="Shop Name";
	$tablename="shop";
	$invidprefix="CMPSHP";

//1.shop-user-invoice-action.php
//2.shop-user-invoice-action2.php
//3.shop-user-del-inv-product.php
//4.shop-invoice-submit.php

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
	<link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">

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
		
		<?php include("validate-scripts.php"); ?>
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
                                    <!----<div class="card-header">
                                        <h5 class="card-title"><?=$displaytitle;?></h5>
                                    </div>--->
                                    <div class="card-body">
									
									<?php if(isset($_REQUEST['AddedSuccess'])){?><div class="alert alert-success">one product added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['ItemAlreadyExists'])){?><div class="alert alert-danger">invalid product, already exists.</div><?php }?>
								
								<?php if(isset($_REQUEST['InvalidStock'])){?><div class="alert alert-danger">invalid qty, out of stock.</div><?php }?>
								
								<?php if(isset($_REQUEST['DeleteSuccess'])){?><div class="alert alert-danger">Deleted ! one product deleted success.</div><?php }?>
								
								<?php if(isset($_REQUEST['invoicealready'])){?><div class="alert alert-danger">Invoice Number already exists!</div>
									<?php }?>
									
									<?php if(isset($_REQUEST['InvoiceUpdatedSuccess'])){?><div class="alert alert-success">Invoice Number Updated Success!.</div><?php }?>
									
									<h1>
									<table class="headertble">
									<tr>
									<td><?php if($get_action=="edit") { echo "Update >"; }?>
									<?=$displaytitle;?></td>
									<td><a href="shop-user-manage-invoice.php" title="Add Invoice">&#9776;</a></td>
									</tr>
									</table>
									</h1>
									
<?php
$select_count_opstock13="select count(*) as numopstock12 from stock where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
$fetch_count_opstock13=mysqli_query($db_conn,$select_count_opstock13);
$result_count_opstock13=mysqli_fetch_array($fetch_count_opstock13);
if($result_count_opstock13['numopstock12']==0)
{
?>
<div class="alert alert-danger">Please update opening stock ! <a href="op-stock.php">Click here</a></div>
<?php }else{?>

<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
						
<script type="text/javascript">
function showPrice(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintPrice").innerHTML=xmlhttp.responseText;}}
var invuser="<?=$getinvuser;?>";
xmlhttp.open("GET","loadPrice.php?q="+str + '&invuser='+ invuser,true);
xmlhttp.send();}
</script>


<script>
  function totalkm(){
   var textValue1 = document.getElementById('amount').value;
   var textValue2 = document.getElementById('qty').value;
   document.getElementById('output').value = (textValue1*textValue2); 
 }
</script>


<style type="text/css">
.curstockvl{width:100%;border-collapse:collapse;}
.curstockvl th{font-weight:bold;padding:5px;font-size:16px;color:blue;}
.curstockvl td{font-weight:bold;padding:5px;}

#add{background:green;border:1px solid green;}
#add:hover,#add:focus{background:#DDD;color:#000;border:1px solid #000;}

.item{margin-bottom:6px;}

.item select{margin-right:10px;float:left;padding:6px;width:400px;border-radius:4px;border:1px solid #000;}
.item input[type=number]{margin-right:10px;float:left;width:100px;padding:5px;border-radius:4px;border:1px solid #000;}

select:focus, input[type=number]:focus{background:#fffa8f;}

@media(max-width:768px)
{
	.item select{width:100%;margin-bottom:10px;}
	.item input[type=number]{width:100%;margin-bottom:10px;}
}
</style>

						
		<?php if(isset($_REQUEST['InvoiceID']))	{
					
		$Invoice_ID_encode=$_REQUEST['InvoiceID'];
		$Invoice_ID=base64_decode($_REQUEST['InvoiceID']);
					
		//get invoice details
		$select_InvoieDetails="select * from user_invoice where inv_id='$Invoice_ID'";
		$fetch_InvoieDetails=mysqli_query($db_conn,$select_InvoieDetails);
		$result_InvoieDetails=mysqli_fetch_array($fetch_InvoieDetails);
		
		//RECEIPT AMOUNT		
$totalamount=$result_InvoieDetails["total"];
$selectcountreceipt="select sum(received) from receipt where inv_id='".$Invoice_ID."'";
$fetchcountreceipt=mysqli_query($db_conn,$selectcountreceipt);
$resulcountreceipt=mysqli_fetch_array($fetchcountreceipt);
$Total_Receipt_amount=$resulcountreceipt[0];
if($Total_Receipt_amount>0 && $totalamount==$Total_Receipt_amount){ $amount_received_fully="1";}
else{ $amount_received_fully="0";}

		//customer details
		$CustomerID=$result_InvoieDetails['to_user_id'];
		$select_CUSTDetails="select * from ".$tablename." where temp_id='$CustomerID'";
		$fetch_CUSTDetails=mysqli_query($db_conn,$select_CUSTDetails);
		$result_CUSTDetails=mysqli_fetch_array($fetch_CUSTDetails);
		?>
													
<form action="shop-user-invoice-action2.php" method="post" enctype="multipart/form-data">

<input type="hidden" name="inv_id" value="<?=$Invoice_ID;?>">
<input type="hidden" name="invuser" value="<?=$getinvuser?>">

<div class="example-container">
<div class="example-content">
          
<label class="form-label"><?=$lablenamedisplay;?>*</label>

<?php
$select_countitems="select count(*) as numitemscount from user_invoice_items where inv_id='$Invoice_ID'";
$fetch_countitems=mysqli_query($db_conn,$select_countitems);
$result_countitems=mysqli_fetch_array($fetch_countitems);
$totalcountitems=$result_countitems['numitemscount'];
?>

<select name="customer_id" class="form-control">
<option value="<?php echo $CustomerID;?>" hidden=""><?php echo $result_CUSTDetails['name'];?>, <?php echo $result_CUSTDetails['mobile_number'];?></option>
<?php 
if($totalcountitems==0)
{
$selectCusList="select * from ".$tablename." where onboard_userID='$onboard_userID' order by name asc";
$fetch_Customers_list=mysqli_query($db_conn,$selectCusList);
while($result_Customers_list=mysqli_fetch_array($fetch_Customers_list))
		{
?>
<option value="<?php echo $result_Customers_list['temp_id'];?>"><?php echo ucwords($result_Customers_list['name']);?>, <?php echo $result_Customers_list['mobile_number'];?></option>
<?php
 }
}
?>
</select>

<label class="form-label">Invoice Date*</label>
<?php if($get_action=="edit") {?>
<input type="date" readonly name="date" value="<?=$result_InvoieDetails['date'];?>" required="" class="form-control">
<?php }else{?>
<input type="date" readonly name="date" value="<?=$result_InvoieDetails['date'];?>" required="" class="form-control">
<?php }?>
</br>



<?php if($amount_received_fully==0){?>
    <div class="item">
	<select required="" name="pr_id" required="" class="prinput" style="width:100%;" autofocus onChange="showPrice(this.value)">
<option value="" hidden="">Select Product</option>
<?php $select_Products_list="select * from products order by id asc";
		$fetch_Products_list=mysqli_query($db_conn,$select_Products_list);
										while($result_Products_list=mysqli_fetch_array($fetch_Products_list))
										{
											?>
<option value="<?php echo $result_Products_list['id'];?>"><?php echo $result_Products_list['productName'];?></option>
										<?php }?>
</select>
<br/><br/>

 <input type="number" min="0" name="qty" id="qty" onKeyup="totalkm()" required="" placeholder="Qty" class="numberinput">
<span id="txtHintPrice">
<input type="number" min="0" name="amount" step="any" id="amount" onKeyup="totalkm()" required="" placeholder="Price"></span>

       
		<input type="number" min="0" step="any" name="total" id="output" class="numberinput" required="" placeholder="Total">
		
		<script>
  function discamount(){
   var output = document.getElementById('output').value;
   var discountpercentae = document.getElementById('discountpercentae').value;
   var outputdisaamount=(output*discountpercentae/100);
   document.getElementById('discountamount').value = outputdisaamount.toFixed(2); 
 }
</script>

<input type="number" min="0" step="any" id="discountpercentae" name="discount_percentage" onKeyup="discamount()" required="" placeholder="Disc(%)" class="numberinput">
<input type="number" min="0" id="discountamount" name="discount_amount" step="any" required="" placeholder="Disc(Rs.)" class="numberinput">
		
		 <button type="submit" name="addInvoice2" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add</button>
    </div>
<?php }?>

						
                                            </div>
                                        </div>
										</form>

<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->										
				

<div class="row">
                                            <div class="table-responsive">
                                                <table class="table">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">#</th>
                                                            <th scope="col">Product Description</th>
                                                            <th scope="col">Qty</th>
															<th scope="col">MRP</th>
															<th scope="col">Discount</th>
															<th scope="col">Amount</th>
															<th scope="col">GST</th>
                                                            <th scope="col">Total</th>
															<?php if($amount_received_fully==0){?>
                                                            <th scope="col"></th>
															<?php }?>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
													<?php
	$select_INVProductDetails="select * from user_invoice_items where inv_id='$Invoice_ID' order by id desc";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	$CountProducts=mysqli_num_rows($fetch_INVProductDetails);
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
<td>&#8377;<?php echo number_format($result_INVProductDetails['amount'],2,'.','');?></td>
<td><?=$result_INVProductDetails['discount_amount'];?>(<?=$result_INVProductDetails['discount_percentage'];?>%)</td>
<td>&#8377;<?php echo number_format($result_INVProductDetails['subtotal'],2,'.','');?></td>
<td><?=$result_INVProductDetails['gstamount_total'];?>(<?=$result_INVProductDetails['gst_percentage'];?>%)</td>
<td align="right"><?php echo number_format($TotalAMount,2,'.','');?></td>

<?php if($amount_received_fully==0){?>
<td>

<?php 
			//COUNT return
			$select_count_return="select * from user_return_stock_items where invnumber='$Invoice_ID' and prid='$InV_Product_ID'";
			$fetch_count_return=mysqli_query($db_conn,$select_count_return);
			$result_count_return=mysqli_num_rows($fetch_count_return);
			if($result_count_return==0){
			?>
			
<a href="shop-user-del-inv-product.php?inv_id=<?php echo $Invoice_ID_encode;?>&&rowid=<?php echo $ItemRowid;?>&&&&invuser=<?=$getinvuser;?>&&userid=<?=$CustomerID;?>&&actionremove"onclick="return confirm('You want to delete confirm?');"><span class="badge bg-danger">Remove</span></a>

<?php } else{ echo "<span id='cnlable'>-&nbsp;CN&nbsp;-</span>";}?>

</td>
<?php }?>
                                                        </tr>
                                                        
	<?php }?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
										
										
										<?php /*?>
										<div class="card-footer">
                                        <div class="row invoice-summary">
                                            <div class="col-lg-4">
                                                <div class="invoice-info">
                   <p>Invoice Number: <span><?php echo $result_InvoieDetails['inv_number'];?></span></p>
                   <p>Invoice Date: <span><?php echo date("d/M/Y",strtotime($result_InvoieDetails['date']));?></span></p>
                                                </div>
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
<form action="shop-user-invoice-submit.php" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">
<input type="hidden" name="invoice_id" value="<?=$Invoice_ID;?>"/>
<input type="hidden" name="SubTotal" value="<?=$TotalAMount123;?>"/>

            <p class="bold">Subtotal <span>
			<input type="number" min="0" value="<?=$TotalAMount123;?>" id="subtotal" disabled></span>
			</p>
			<br/>
													
           <p class="bold">Discount <span>
		   <?php if($result_InvoieDetails['discount']==0){?>
		  <input type="number" onkeyup="totalamount()" id="discount" min="0" name="discount" required="">
		   <?php }else{ ?>
		   <input type="number" value="<?=$result_InvoieDetails['discount'];?>" onkeyup="totalamount()" id="discount" min="0" name="discount" required="">
		   <?php }?>
		 </span></p>
													<br/>
         <p class="bold">Total <span>
		 <?php if($result_InvoieDetails['discount']==0){?>
		 <input type="number" min="0" value="<?=$TotalAMount123;?>" id="outputTotalamount" disabled>
		 <?php }else{ 
		 $TotalAmount_display=$TotalAMount123-$result_InvoieDetails['discount'];
		 ?>
		  <input type="number" min="0" value="<?=$TotalAmount_display;?>" id="outputTotalamount" disabled>
		   <?php }?>
		 </span>
		 </p>
													<div style="clear:both;"></div>
                                                    <div class="invoice-info-actions">
     <button class="btn btn-primary" type="submit" name="invoice-submit">Submit Invoice</button>
                                                    </div>
													
													</form>
													
                                                </div>
                                            </div>
											
											
											
                                        </div>
                                    </div>
									<?php */?>
									
									
									
									<div><!----class="card-footer"---->
                                        <div><!---class="row invoice-summary"---->
										
						
<script>
        function validateForm() {
            const amountInput = document.getElementById('receivableamount');
            const amount = parseFloat(amountInput.value);
            const errorSpan = document.getElementById('error');

            if (isNaN(amount) || amount < 0) {
                errorSpan.style.display = 'inline';
                return false; // Prevent form submission
            } else {
                errorSpan.style.display = 'none';
                return true; // Allow form submission
            }
        }
    </script>
	
		<form action="shop-user-invoice-submit.php" method="post" enctype="multipart/form-data" onsubmit="return validateForm()">		
				
<?php /*?>				
		<!-------------------------SHOP CURRENT STOCK-------------------------------->
		<br/><br/>
		<h1 style="color:#223c93;">Current Stock</h1>
		<table class="curstockvl">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">Product</th>
                                                            <th scope="col">Qty</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
										<?php 
					$select_productCurrentst="select * from products order by id asc";
					$fetch_productCurrentst=mysqli_query($db_conn,$select_productCurrentst);
					while($result_productCurrentst=mysqli_fetch_array($fetch_productCurrentst))
										{
										?>
                    <input type="hidden" name="cr_prid[]" value="<?php echo $result_productCurrentst['id']; ?>"/>
                                                <tr>
                                 <td><?php echo $result_productCurrentst["productName"];?></td>
		<td><input type="number" name="cr_qty[]" value="0" class="form-control" required="" style="border-color:#000 !important;" placeholder="Qty" min="0"/></td>
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
													
													</table>
		
		
		
		<!-------------------------SHOP COMPETITOR STOCK-------------------------------->
		<br/><br/>
												
												<h1 style="color:#223c93;">Competitor Stock</h1>
		<table class="curstockvl">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">Brand</th>
                                                            <th scope="col">Qty</th>
															<th scope="col">Pantyliner</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
										<?php 
					$select_compebrand="select * from competitor_brand order by id asc";
					$fetch_compebrand=mysqli_query($db_conn,$select_compebrand);
					while($result_compebrand=mysqli_fetch_array($fetch_compebrand))
										{
										?>
                    <input type="hidden" name="cst_prid[]" value="<?php echo $result_compebrand['id']; ?>"/>
                                                <tr>
                                 <td><?php echo $result_compebrand["brand"];?></td>
		<td><input type="number" name="cst_qty[]" value="0" class="form-control" required="" style="border-color:#000 !important;" placeholder="Qty" min="0"/></td>
		<td><input type="text" name="cst_panty[]" class="form-control" style="border-color:#000 !important;" placeholder="Pantyliner"/></td>
                                                </tr>
                                           
										<?php }?>
										
										 </tbody>
													
													</table>
												
												<?php */?>
		<!------------------------------------------------------------------------------>
		<!------------------------------------------------------------------------------>
		
		
                                            <div>
                                                <div class="invoice-info">
												<hr/>
												<table style="width:100%;">
												<tr>
												<td>Inv Num</td>
												<td style="color:#bd2b0e;">:&nbsp;
												
												<?php if($get_action=="edit") {?>
				   <a href="#" id="linkcaption" data-bs-toggle="modal" data-bs-target="#exampleModalLive">
				   <span><?php echo $result_InvoieDetails['inv_number'];?></span>
				   </a>
				   <?php }else{?>
				   <span><?php echo $result_InvoieDetails['inv_number'];?></span>
				   <?php }?>
				   </td>
												</tr>
												<tr>
												<td>Date</td>
												<td style="color:#bd2b0e;">:&nbsp;<b><?php echo date("d/M/Y",strtotime($result_InvoieDetails['date']));?></span></b></td>
												</tr>
												</table>
												
												<hr/>
                   
                                                </div>
                                            </div>
                                            <div class="col-lg-5"></div>
											
											<?php 
		$unround_value=$TotalAMount123+$result_InvoieDetails['courier_charges'];
		$roundvalue=round($unround_value);
		$roundoff=$roundvalue-$unround_value;	
		?>
		
<script>
function totalamount(){
    var roundtotal = <?php echo round($TotalAMount123); ?>;
    var cucharge   = parseFloat(document.getElementById('cucharge').value) || 0;
    var newtotal   = roundtotal + cucharge;
    document.getElementById('outputTotalamount').value = newtotal.toFixed(2);

    // Recalculate receivable whenever total changes
    receiptamount();
}
</script>

                                            <div class="col-lg-3">
                                                <div class="invoice-info">

<input type="hidden" name="invoice_id" value="<?=$Invoice_ID;?>"/>
<input type="hidden" name="SubTotal" value="<?=$TotalAMount123;?>"/>

            <p><b>Subtotal</b>
			<input type="number" step="any" class="form-control" min="0" value="<?=$TotalAMount123;?>" style="width:100%;" id="subtotal" disabled>
			</p>
			
			
			<?php 
			//UPDATE INVOICE AMOUNT TO ZERO
			if($CountProducts==0 && $result_InvoieDetails['sub_total']>0)
			{
$update_zero_invoice="update user_invoice set sub_total='0',discount='0',total='0' where inv_id='$Invoice_ID'";
mysqli_query($db_conn,$update_zero_invoice);		
			}
			?>
													
           <!-------<p class="bold">Discount <span></p>
		   <p>
		   <?php if($result_InvoieDetails['discount']==0){?>
		  <input type="number" step="any" onkeyup="totalamount()" class="form-control" value="0" id="discount" style="width:100%;" min="0" name="discount" required="">
		   <?php }else{ ?>
		   <input type="number" step="any" value="<?=$result_InvoieDetails['discount'];?>" style="width:100%;" onkeyup="totalamount()" id="discount" class="form-control" min="0" name="discount" required="">
		   <?php }?>
		 </span></p>---->
		 <input type="hidden" name="discount" value="0"/>
													
         <!-----<p class="bold">Total <span></p>
		 <p>
		 <?php if($result_InvoieDetails['discount']==0){?>
		 <input type="number" step="any" min="0" class="form-control" value="<?=$TotalAMount123;?>" style="width:100%;" id="outputTotalamount" disabled>
		 <?php }else{ 
		 $TotalAmount_display=$TotalAMount123-$result_InvoieDetails['discount'];
		 ?>
		  <input type="number" step="any" min="0" class="form-control" value="<?=$TotalAmount_display;?>" style="width:100%;" id="outputTotalamount" disabled>
		   <?php }?>
		 </span>
		 </p>---->
		<input type="hidden" name="roundoff" value="<?=number_format($roundoff,2,'.','');?>"/>
		
		<p><b>Round off</b>
		 <input type="number" min="0" class="form-control" step="any" value="<?=number_format($roundoff,2,'.','');?>" disabled>
		 </p>
		 
		 <p><b>Courier Charges</b>
		 <input type="number" value="<?=$result_InvoieDetails['courier_charges'];?>" name="courier_charges" min="0" required="" onKeyup="totalamount()" id="cucharge" class="form-control">
		 </p>
		 
		 <p><b>Total</b>
		 <input type="number" min="0" class="form-control" step="any" value="<?=number_format($roundvalue,2,'.','');?>" id="outputTotalamount" disabled>
		 </p>
		 
<?php
// Fetch all existing receipts for this invoice
$select_ReceiptDetails = "SELECT * FROM receipt WHERE inv_id = '" . $Invoice_ID . "' ORDER BY id ASC";
$fetch_ReceiptDetails   = mysqli_query($db_conn, $select_ReceiptDetails);
$result_ReceiptDetails  = mysqli_fetch_array($fetch_ReceiptDetails);

// Calculate already received amount
$select_sum_receipt = "SELECT COALESCE(SUM(received), 0) AS total_received FROM receipt WHERE inv_id = '" . $Invoice_ID . "'";
$fetch_sum_receipt  = mysqli_query($db_conn, $select_sum_receipt);
$result_sum_receipt = mysqli_fetch_array($fetch_sum_receipt);
$already_received   = (float)$result_sum_receipt['total_received'];

// Balance = invoice total minus what's already been received
$balance_due = (float)$roundvalue - $already_received;
if ($balance_due < 0) $balance_due = 0;
?>

<script>
function receiptamount(){
    // Always read live total (includes courier charges typed by user)
    var totalbillamount = parseFloat(document.getElementById('outputTotalamount').value) || 0;
    var alreadyreceived = <?= number_format($already_received, 2, '.', '') ?>;
    var balancedue      = totalbillamount - alreadyreceived;
    if (balancedue < 0) balancedue = 0;

    var receivedamount  = parseFloat(document.getElementById('receivedamount').value) || 0;
    var receivable      = balancedue - receivedamount;
    document.getElementById('receivableamount').value = receivable.toFixed(2);

    // Keep max in sync so browser validation stays correct
    document.getElementById('receivedamount').setAttribute('max', balancedue.toFixed(2));
    document.getElementById('receivedamount').placeholder = 'Max: ' + balancedue.toFixed(2);
}
</script>

<!-------------------------------------------------------------->

<?php if ($already_received > 0): ?>
<p><b>Invoice Total</b>
    <input type="number" step="any" class="form-control" style="width:100%;"
           value="<?= number_format($roundvalue, 2, '.', '') ?>" disabled>
</p>

<p><b>Already Received</b>
    <input type="number" step="any" class="form-control" style="width:100%;background:#d1fae5;"
           value="<?= number_format($already_received, 2, '.', '') ?>" disabled>
</p>

<p><b>Balance Due</b>
    <input type="number" step="any" class="form-control" style="width:100%;background:#fee2e2;font-weight:bold;"
           value="<?= number_format($balance_due, 2, '.', '') ?>" disabled>
</p>
<?php endif; ?>

<p><b>Received Amount</b>
    <input type="number" min="0" required="" step="any"
           max="<?= number_format($balance_due, 2, '.', '') ?>"
           id="receivedamount" class="form-control" style="width:100%;"
           onkeyup="receiptamount()" name="receivedamount"
           placeholder="Max: <?= number_format($balance_due, 2, '.', '') ?>">
</p>

<p><b>Receivable Amount</b>
    <input type="number" min="0" id="receivableamount" class="form-control"
           readonly required="" style="width:100%;">
    <span id="error" style="color:red;display:none;font-size:12px;">
        Value must be non-negative.
    </span>
</p>

<!-------------------------------------------------------------->	


<div class="bold">Received Method<span>
		 <select name="receipt_method" required class="form-control">
		 <?php if($result_ReceiptDetails['receipt_method']==NULL){?>
		 <option value="" hidden="">Select</option>
		 <?php }else{?>
		 <option value="<?=$result_ReceiptDetails['receipt_method'];?>" hidden=""><?=$result_ReceiptDetails['receipt_method'];?></option>
		 <?php }?>
		 <option>--None--</option>
		 <option>Cash</option>
		 <option>UPI</option>
		 <option>Bank Transfer</option>
		 <option>Deposit</option>
		 </select>
		 </span>
		 </div>
 
 <?php if($result_ReceiptDetails['receipt_remarks']!=NULL){ 
 $show_remarks=$result_ReceiptDetails['receipt_remarks'];}
 else{ $show_remarks="";}?>
<div class="bold">Remarks<span>
		 <textarea name="receipt_remarks" required class="form-control"><?=$show_remarks;?></textarea>
		 </span>
		 </div> 
		 
		 
													<div style="clear:both;"></div>
													<?php if($amount_received_fully==0){?>
                                                    <div class="invoice-info-actions">
     <!----<button class="btn btn-primary" type="submit" name="invoice-submit" style="width:100%;">Submit Invoice</button>---->
	 
	 <?php if($CountProducts>0){?>
     <button class="btn btn-primary" type="submit" name="invoice-submit" style="width:100%;">Submit Invoice</button>
													<?php }?>
													
                                                    </div>
													<?php }else{?>
													<span class='badge badge-style-bordered badge-success'>Not editable ! Fully Paid Invoices</span>
													<?php }?>
													
													</form>
													
                                                </div>
                                            </div>
											
											
											
                                        </div>
                                    </div>
										
									<?php }else{?>
									
		<form action="shop-user-invoice-action.php" method="post" enctype="multipart/form-data">

<?php function GeraHash($qtd){ $Caracteres = '123456789'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } ; 
$inv_randum_number=GeraHash(10);
$randum_number=GeraHash(3);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("gis"); 
$inv_id="".$inv_randum_number."".$invidprefix."".$temp_date."".$temp_time."";?>

<input type="hidden" name="randum_number" value="<?=$randum_number?>">
<input type="hidden" name="inv_id" value="<?=$inv_id?>">
<input type="hidden" name="invuser" value="<?=$getinvuser?>">

                                        <div class="example-container">
                                           <div class="example-content">

<!-------------INVOICE NUMBER------------->	
<!--load_InvoiceNumber_customer.php--->									
<script type="text/javascript">
function showInvoiceDuplicate(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintInvoice").innerHTML=xmlhttp.responseText;}}
xmlhttp.open("GET","loadInvoiceNumberUSER.php?q="+str,true);
xmlhttp.send();}
</script>
			<label class="form-label">Invoice Number *</label>
            <input type="text" onKeyup="showInvoiceDuplicate(this.value)"; name="inv_number" autofocus required="" onkeypress="restrictSpecialChars(event)" class="form-control">
			<br/>
			<span id="txtHintInvoice"></span>
			

<label class="form-label"><?=$lablenamedisplay;?>*</label>
<select required="" name="customer_id" class="form-control" autofocus>
<option value="" hidden="">Select</option>
<?php 
$selectCusList="select * from ".$tablename." where onboard_userTYPE='$onboard_userTYPE' and onboard_userID='$onboard_userID' order by name asc";
$fetch_Customers_list=mysqli_query($db_conn,$selectCusList);
while($result_Customers_list=mysqli_fetch_array($fetch_Customers_list))
		{
?>
<option value="<?php echo $result_Customers_list['temp_id'];?>"><?php echo ucwords($result_Customers_list['name']);?>, <?php echo $result_Customers_list['mobile_number'];?>, <?php echo ucwords($result_Customers_list['address']);?></option>
<?php }?>
</select>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!--id="bookingDate"-->
<label class="form-label">Invoice Date*</label>
<input id="bookingDate" style="margin-bottom:10px;" type="date" name="date" value="<?php echo date("Y-m-d");?>" required="" class="form-control">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr("#bookingDate", {
        dateFormat: "Y-m-d",
            maxDate: "today" // Disallow future dates
        });
</script>



<?php if($amount_received_fully==0){?>
    <div class="item">
	<select required="" name="pr_id" style="width:100%;" required="" onChange="showPrice(this.value)" class="prinput">
<option value="" hidden="">Select Product</option>
<?php $select_Products_list="select * from products order by id asc";
$fetch_Products_list=mysqli_query($db_conn,$select_Products_list);
while($result_Products_list=mysqli_fetch_array($fetch_Products_list))
{
?>
<option value="<?=$result_Products_list['id'];?>"><?=$result_Products_list['productName'];?></option>
<?php }?>
</select>
<br/><br/>

<input type="number" min="0" name="qty" id="qty" onKeyup="totalkm()" required="" placeholder="Qty" class="numberinput">
<span id="txtHintPrice">
<input type="number" min="0" name="amount" step="any" id="amount" onKeyup="totalkm()" required="" placeholder="Price">
</span>

		<input type="number" min="0" step="any" name="total" id="output" required="" placeholder="Total" class="numberinput">
		
		<script>
  function discamount(){
   var output = document.getElementById('output').value;
   var discountpercentae = document.getElementById('discountpercentae').value;
   var outputdisaamount=(output*discountpercentae/100);
   document.getElementById('discountamount').value = outputdisaamount.toFixed(2); 
 }
</script>

<input type="number" min="0" step="any" id="discountpercentae" name="discount_percentage" onKeyup="discamount()" required="" placeholder="Disc(%)" class="numberinput">
<input type="number" min="0" id="discountamount" name="discount_amount" step="any" required="" placeholder="Disc(Rs.)" class="numberinput">
		
		
		<span id="txtHintstock">
		 <button type="submit" name="addInvoice" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add</button>
		 </span>
		 
		 
    </div>
<?php }?>
						
                                            </div>
                                        </div>
										</form>
										
										<?php }?>
										
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
										
										<?php }?>
										
										<!--------INVOICE NUMBER EDIT FORM OPEN-------->
				   <div class="modal fade" id="exampleModalLive<?php echo $result_product_list["id"];?>" tabindex="-1" aria-labelledby="exampleModalLiveLabel" aria-hidden="true">
													
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLiveLabel">Update Invoice Number<br/>
																<?php echo $result_InvoieDetails['inv_number'];?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
				<form method="post" onsubmit="return confirm('Please make a confirm!');" enctype="multipart/form-data" action="update_invoice_action">	
									
				<input type="hidden" name="invuser" value="<?php echo $_REQUEST['invuser'];?>">
				<input type="hidden" name="InvoiceID" value="<?php echo $_REQUEST['InvoiceID'];?>">
				<input type="hidden" name="action" value="<?php echo $_REQUEST['action'];?>">
				<input type="hidden" name="gid" value="<?php echo $_REQUEST['gid'];?>">
				<input type="hidden" name="redirurl" value="shop-user-invoice-add">
				<input type="hidden" name="tblenme" value="1">
															
                                                 <div class="example-content" style="padding:20px;">
                                                <div class="form-floating mb-3">
                                                    <input type="text" name="invnumber" placeholder="Invoice Number" class="form-control" id="floatingInput" required="" onkeypress="restrictSpecialChars(event)">
                                                    <label for="floatingInput">Invoice Number</label>
                                                </div>
												
												<button type="submit" name="updateInvoiceNum" class="btn btn-primary"><i class="material-icons">update</i>Update</button>
                                            </div>
											</form>
                                                        </div>
                                                    </div>
                                                </div>
												<!--------INVOICE NUMBER EDIT FORM OPEN---END***----->
										
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
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="../../assets/js/pages/select2.js"></script>
</body>

</html>