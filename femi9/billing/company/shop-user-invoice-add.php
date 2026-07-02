<?php include("checksession.php"); require_once("include/GodownAccess.php"); 
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

//get Godown Details
$select_Godowndetails="select * from company_godown where id='".$_REQUEST['gid']."' AND " . godown_finance_filter_sql($db_conn);
$fetch_Godowndetails=mysqli_query($db_conn,$select_Godowndetails);
$result_Godown=mysqli_fetch_array($fetch_Godowndetails);


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

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
                        <br/>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
									
									<?php if(isset($_REQUEST['AddedSuccess'])){?><div class="alert alert-success">one product added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['ItemAlreadyExists'])){?><div class="alert alert-danger">invalid product, already exists.</div><?php }?>
								
								<?php if(isset($_REQUEST['InvalidStock'])){?><div class="alert alert-danger">invalid qty, out of stock.</div><?php }?>
								
								<?php if(isset($_REQUEST['DeleteSuccess'])){?><div class="alert alert-danger">Deleted ! one product deleted success.</div><?php }?>
								
								<?php if(isset($_REQUEST['stocknotupdated'])){?><div class="alert alert-danger">Please update opening stock (<?=$result_Godown['gname'];?>) !</div>
									<?php }?>
									
									<?php if(isset($_REQUEST['invoicealready'])){?><div class="alert alert-danger">Invoice Number already exists!</div>
									<?php }?>
									
									<?php if(isset($_REQUEST['InvoiceUpdatedSuccess'])){?><div class="alert alert-success">Invoice Number Updated Success!.</div><?php }?>
								
								 <h1>
									<table class="headertble">
									<tr>
									<td>
									<?php if($get_action=="edit") { echo "Update >"; }?>
									<?php echo $displaytitle;?></td>
									<td><a href="shop-user-manage-invoice" title="Manage Invoice">&#9776;</a></td>
									</tr>
									</table>
									</h1>
									

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
	.item select{width:100%;margin-bottom:5px;}
	.item input[type=number]{width:100%;margin-bottom:5px;}
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
													
<form action="shop-user-invoice-action2" method="post" enctype="multipart/form-data">

<input type="hidden" name="inv_id" value="<?=$Invoice_ID;?>">
<input type="hidden" name="invuser" value="<?=$getinvuser?>">

<div class="example-container">
<div class="example-content">

<!------------------------------------------------------------------------------>
<!------------------------------------GODOWN------------------------------------>
				<label class="form-label">Company Profile</label>
                               <select name="godownid" class="form-control">
							   <option value="<?=$result_Godown['id'];?>"><?=$result_Godown['gname'];?></option>
							   </select>
							   <br/> <br/>
<!------------------------------------------------------------------------------>
<!------------------------------------------------------------------------------>
          
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
$selectCusList="select * from ".$tablename." where onboard_userTYPE='$onboard_userTYPE' order by name asc";
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
 <br/> <br/>

<label class="form-label">Invoice Date*</label>
<input type="date" readonly name="date" value="<?=$result_InvoieDetails['date'];?>" required="" class="form-control">
<br/>


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
 <br/> <br/>
 
 <input type="number" min="0" class="numberinput" name="qty" id="qty" onKeyup="totalkm()" required="" placeholder="Qty">
<span id="txtHintPrice">
<input type="number" min="0" name="amount" step="any" id="amount" onKeyup="totalkm()" required="" placeholder="Price"></span>

       
		<input type="number" min="0" step="any" name="total" id="output" required="" placeholder="Total" class="numberinput">
		
		<script>
  function discamount(){
   var output = document.getElementById('output').value;
   var discountpercentae = document.getElementById('discountpercentae').value;
   var outputdisaamount=(output*discountpercentae/100);
   document.getElementById('discountamount').value = outputdisaamount.toFixed(2); 
 }
</script>

<input type="number" min="0" id="discountpercentae" name="discount_percentage" onKeyup="discamount()" required="" placeholder="Disc(%)" class="numberinput">
<input type="number" min="0" id="discountamount" name="discount_amount" step="any" required="" placeholder="Disc(Rs.)" class="numberinput">

		 <button type="submit" name="addInvoice" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add</button>
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
															<th scope="col">HSN</th>
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
	
	//product details
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
<td><?=$result_ProductDetails123['hsn'];?></td>
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
			
<a href="shop-user-del-inv-product?inv_id=<?php echo $Invoice_ID_encode;?>&&rowid=<?php echo $ItemRowid;?>&&&&invuser=<?=$getinvuser;?>&&userid=<?=$CustomerID;?>&&actionremove" onclick="return confirm('You want to delete confirm?');"><span class="badge bg-danger">Remove</span></a>

<?php } else{ echo "<span id='cnlable'>-&nbsp;CN&nbsp;-</span>";}?>

</td>
<?php }?>
                                                        </tr>
                                                        
	<?php }?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
										
									
									<div>
                                        <div>
										
						
<script>
function validateForm() {
    const amountInput = document.getElementById('receivableamount');
    const amount = parseFloat(amountInput.value);
    const errorSpan = document.getElementById('error');

    if (isNaN(amount) || amount < 0) {
        errorSpan.style.display = 'inline';
        return false;
    } else {
        errorSpan.style.display = 'none';
        return true;
    }
}

function receiptamount(){
    var totalbillamount = document.getElementById('outputTotalamount').value;
    var receivedamount = document.getElementById('receivedamount').value;
    document.getElementById('receivableamount').value = (totalbillamount*1)-(receivedamount*1); 
}
</script>
	
<form action="shop-user-invoice-submit" method="post" enctype="multipart/form-data" onsubmit="return validateForm()">				
				
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
												<td style="color:#bd2b0e;">:&nbsp;<b><?php echo date("d/M/Y",strtotime($result_InvoieDetails['date']));?></b></td>
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
   var subtotal = "<?=round($TotalAMount123);?>";
   var cucharge = document.getElementById('cucharge').value;
   document.getElementById('outputTotalamount').value = (subtotal*1)+(cucharge*1); 
 }
</script>

                                            <div class="col-lg-3">
                                                <div class="invoice-info">

<input type="hidden" name="invoice_id" value="<?=$Invoice_ID;?>"/>
<input type="hidden" name="SubTotal" value="<?=$TotalAMount123;?>"/>

           <p><b>Sub Total</b>
		   <input type="number" class="form-control" min="0" value="<?=$TotalAMount123;?>" style="width:100%;" id="subtotal" disabled>
			</p>
			
			<?php 
			//UPDATE INVOICE AMOUNT TO ZERO
			if($CountProducts==0 && $result_InvoieDetails['sub_total']>0)
			{
$update_zero_invoice="update user_invoice set sub_total='0',discount='0',total='0' where inv_id='$Invoice_ID'";
mysqli_query($db_conn,$update_zero_invoice);		
			}
			?>
           
		 <input type="hidden" name="discount" value="0"/>
		 <input type="hidden" name="roundoff" value="<?=number_format($roundoff,2,'.','');?>"/>
		
		<p><b>Round off</b>
		 <input type="number" min="0" class="form-control" step="any" value="<?=number_format($roundoff,2,'.','');?>" disabled>
		 </p>
				
<p><b>Courier Charges</b>
		 <input type="number" value="<?=$result_InvoieDetails['courier_charges'];?>" name="courier_charges" min="0" required="" onKeyup="totalamount()" id="cucharge" class="form-control">
		 </p>
		 
         <p><b>Total</b>
		 <input type="number" min="0" class="form-control" value="<?=$roundvalue;?>" style="width:100%;" id="outputTotalamount" disabled></p>

                                                    <?php
                                                    // ================================================================
                                                    // RECEIPT FIELDS — shown in BOTH new and edit mode.
                                                    //
                                                    // FIX: Original code sent receivedamount as a hidden field in
                                                    // edit mode (using $result_ReceiptDetails which was fetched only
                                                    // inside that block), hiding the received amount input and receipt
                                                    // method select from the user entirely.
                                                    //
                                                    // Correct behaviour: always show these fields. In edit mode the
                                                    // old receipts are deleted on resubmit, so a fresh received
                                                    // amount must be entered every time regardless of mode.
                                                    //
                                                    // Invoice date picker is shown only in edit mode (unchanged).
                                                    // ================================================================
                                                    ?>

		<p><b>Received Amount</b>
		 <input type="number" min="0" required="" step="any" id="receivedamount" class="form-control" style="width:100%;" onkeyup="receiptamount()" name="receivedamount" placeholder="0.00">
		 </p>
		 
		 <p><b>Receivable Amount</b>
		 <input type="number" min="0" id="receivableamount" class="form-control" readonly required="" style="width:100%;">
		 <p id="error" style="color: red; display: none; font-size:12px;">Value must be non-negative.</p>
		 </p>

		<?php if($get_action=="edit"): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
		<p><b>Invoice Date</b>
		 <input type="date" name="update_invoice_date" id="bookingDateu" class="form-control" value="<?=$result_InvoieDetails['date'];?>">
		 </p><br/>
		<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
		<script>
		flatpickr("#bookingDateu", {
		    dateFormat: "Y-m-d",
		    maxDate: "today"
		});
		</script>
		<?php endif; ?>

        <!-- Receipt method: always shown, no pre-fill from old receipt
             (old receipt is deleted on edit resubmit so pre-fill is meaningless) -->
		<div class="bold">Received Method<span>
		 <select name="receipt_method" required class="form-control">
		 <option value="" hidden="">Select</option>
		 <option>--None--</option>		 
		 <option>Cash</option>
		 <option>UPI</option>
		 <option>Bank Transfer</option>
		 <option>Deposit</option>
		 </select>
		 </span>
		 </div>

		<div class="bold">Remarks<span>
		 <textarea name="receipt_remarks" required class="form-control" placeholder="Payment remarks"></textarea>
		 </span>
		 </div>

		 
	<?php 
if($get_action=="edit") { $sumbitlable="Update Invoice";}else{$sumbitlable="Submit Invoice"; }
	?>
		 
		<div style="clear:both;"></div>
		<?php if($amount_received_fully==0){?>
                                                    
		<div class="invoice-info-actions">
		<?php if($CountProducts>0){?>
        <button class="btn btn-primary" type="submit" onclick="return confirm('Please make a confirm!');" name="invoice-submit" style="width:100%;"><?=$sumbitlable;?></button>
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

<input type="hidden" name="username" value="<?=$_SESSION['LOGIN_USER'];?>">
<input type="hidden" name="usertype" value="<?=$Result_Log_users_Dtails134['usertype'];?>">

                                        <div class="example-container">
                                           <div class="example-content">
										   
										   <!-------------INVOICE NUMBER------------->										
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
			
										   
										   <!------------------------------------------------------------------------------>
<!------------------------------------GODOWN------------------------------------>
<script type="text/javascript">
function checkopeningstock(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("opstock").innerHTML=xmlhttp.responseText;}}
xmlhttp.open("GET","loadopeningstock.php?q="+str,true);
xmlhttp.send();}
</script>
				<label class="form-label">Company Profile</label>
                               <select required="" autofocus name="godownid" class="form-control" onchange="checkopeningstock(this.value)">
							   <option value="" hidden="">Select</option>
							   <?php $select_Godown="select * from company_godown where " . godown_finance_filter_sql($db_conn) . " order by id asc";
							   $fetch_Godown=mysqli_query($db_conn,$select_Godown);
							   while($result_Godown=mysqli_fetch_array($fetch_Godown))
							   {?>
						   <option value="<?=$result_Godown['id'];?>"><?=$result_Godown['gname'];?></option>
							   <?php }?>
							   </select>
							   <br/> <br/>
							   <div id="opstock"></div>
<!------------------------------------------------------------------------------>
<!------------------------------------------------------------------------------>


<label class="form-label"><?=$lablenamedisplay;?>*</label>
<select required="" name="customer_id" class="form-control">
<option value="" hidden="">Select</option>
<?php 
$selectCusList="select * from ".$tablename." where onboard_userTYPE='$onboard_userTYPE' order by name asc";
$fetch_Customers_list=mysqli_query($db_conn,$selectCusList);
while($result_Customers_list=mysqli_fetch_array($fetch_Customers_list))
		{
?>
<option value="<?php echo $result_Customers_list['temp_id'];?>"><?php echo ucwords($result_Customers_list['name']);?>, <?php echo $result_Customers_list['mobile_number'];?>, <?php echo ucwords($result_Customers_list['address']);?></option>
<?php }?>
</select>
 <br/> <br/>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<label class="form-label">Invoice Date*</label>
<input type="date" id="bookingDate" name="date" value="<?php echo date("Y-m-d");?>" required="" class="form-control">
</br>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr("#bookingDate", {
        dateFormat: "Y-m-d",
            maxDate: "today"
        });
</script>


<?php if($amount_received_fully==0){?>

    <div class="item">
	<select required="" name="pr_id" class="js-states form-control" tabindex="-1" style="display: none;width:100%;" onchange="showPrice(this.value)">
<option value="" hidden="">Select Product</option>
<?php $select_Products_list="select * from products order by id asc";
		$fetch_Products_list=mysqli_query($db_conn,$select_Products_list);
										while($result_Products_list=mysqli_fetch_array($fetch_Products_list))
										{
											?>
<option value="<?php echo $result_Products_list['id'];?>"><?php echo $result_Products_list['productName'];?></option>
										<?php }?>
</select>
 <br/> <br/>

<input type="number" class="numberinput" min="0" name="qty" id="qty" onKeyup="totalkm()" required="" placeholder="Qty">
<span id="txtHintPrice">
<input type="number" min="0" name="amount" step="any" id="amount" onKeyup="totalkm()" required="" placeholder="Price"></span>

        
		<input type="number" min="0" name="total" step="any" id="output" required="" placeholder="Total" class="numberinput">
		
		<script>
  function discamount(){
   var output = document.getElementById('output').value;
   var discountpercentae = document.getElementById('discountpercentae').value;
   var outputdisaamount=(output*discountpercentae/100);
   document.getElementById('discountamount').value = outputdisaamount.toFixed(2); 
 }
</script>

<input type="number" min="0" id="discountpercentae" name="discount_percentage" onKeyup="discamount()" required="" placeholder="Disc(%)" class="numberinput">
<input type="number" min="0" id="discountamount" name="discount_amount" step="any" required="" placeholder="Disc(Rs.)" class="numberinput">

		 <button type="submit" name="addInvoice" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add</button>
    </div>
	
<?php }?>
						
                                            </div>
                                        </div>
										</form>
										
										<?php }?>
										
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
										
				<!--------INVOICE NUMBER EDIT MODAL-------->
				<div class="modal fade" id="exampleModalLive" tabindex="-1" aria-labelledby="exampleModalLiveLabel" aria-hidden="true">
													
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
				<!--------INVOICE NUMBER EDIT MODAL END-------->
												
										
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