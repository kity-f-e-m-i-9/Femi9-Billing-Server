<?php include("checksession.php"); require_once("include/GodownAccess.php"); 
date_default_timezone_set("Asia/Kolkata"); 
error_reporting(0);
include("config.php");

$get_req_id=$_REQUEST['reqid'];
	$get_req_id_DECODE=base64_decode($get_req_id);
	//
	 $select_requestdetails="select * from stock_request where reqid='$get_req_id_DECODE'";
	 $fetch_requestdetails=mysqli_query($db_conn,$select_requestdetails);
	 $result_requestdetails=mysqli_fetch_array($fetch_requestdetails);
	 
	 $verified_accounts=$result_requestdetails['verified'];

$getinvuser=$result_requestdetails['fromusertype'];
//invuser = super_stockiest
//invuser = stockiest
//invuser = distributor
//invuser = shop
//invuser = outlet

if($getinvuser=="super_stockiest")
{
	$displaytitle="Stock Request : Invoice - Super Stockist";
	$lablenamedisplay="Super Stockist Name";
	$tablename="super_stockiest";
	$invidprefix="CMPSS";
	}
else if($getinvuser=="stockiest")
{
	$displaytitle="Stock Request : Invoice - Stockist";
	$lablenamedisplay="Stockist Name";
	$tablename="stockiest";
	$invidprefix="CMPST";
	}
else if($getinvuser=="distributor")
{
	$displaytitle="Stock Request : Invoice - Distributor";
	$lablenamedisplay="Distributor Name";
	$tablename="distributor";
	$invidprefix="CMPDST";
	}
	
	else if($getinvuser=="outlet")
{
	$displaytitle="Stock Request : Invoice - Outlet";
	$lablenamedisplay="Outlet Name";
	$tablename="outlet";
	$invidprefix="CMPOT";
	}
else
{
	//$displaytitle="Invoice - Shop";
	//$lablenamedisplay="Shop Name";
	//$tablename="shop";
	//$invidprefix="CMPSHP";
	}

//1.user-invoice-action-req.php
//2.user-invoice-action-req2.php
//3.user-del-inv-product-req.php
//4.invoice-submit-req.php

//get Godown Details
$select_Godowndetails="select * from company_godown where id='".$_REQUEST['gid']."' AND " . godown_finance_filter_sql($db_conn);
$fetch_Godowndetails=mysqli_query($db_conn,$select_Godowndetails);
$result_Godown=mysqli_fetch_array($fetch_Godowndetails);

//customer details
		$CustomerID=$result_requestdetails['fromuserid'];
		$select_CUSTDetails="select * from ".$tablename." where temp_id='$CustomerID'";
		$fetch_CUSTDetails=mysqli_query($db_conn,$select_CUSTDetails);
		$result_CUSTDetails=mysqli_fetch_array($fetch_CUSTDetails);
		
	$select_invoice_details="select * from user_invoice where inv_id='$get_req_id_DECODE'";
	$fetch_invoice_details=mysqli_query($db_conn,$select_invoice_details);
	$result_invoice_details=mysqli_fetch_array($fetch_invoice_details);
												
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
			<br/>
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <!-----<div class="card-header">
                                        <h5 class="card-title"><?=$displaytitle;?></h5>
                                    </div>---->
                                    <div class="card-body">
									
									<?php if(isset($_REQUEST['AddedSuccess'])){?><div class="alert alert-success">one product added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['ItemAlreadyExists'])){?><div class="alert alert-danger">invalid product, already exists.</div><?php }?>
								
								<?php if(isset($_REQUEST['InvalidStock'])){?><div class="alert alert-danger">invalid qty, out of stock.</div><?php }?>
								
								<?php if(isset($_REQUEST['DeleteSuccess'])){?><div class="alert alert-danger">Deleted ! one product deleted success.</div><?php }?>
								
								<?php if(isset($_REQUEST['stocknotupdated'])){?><div class="alert alert-danger">Please update opening stock (<?=$result_Godown['gname'];?>) !</div>
									<?php }?>
									
									<?php if(isset($_REQUEST['invoicealready'])){?><div class="alert alert-danger">Invoice Number already exists!</div>
									<?php }?>
								
								
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
						
<script type="text/javascript">
function showproductsreq(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintPrreq").innerHTML=xmlhttp.responseText;}}
var reqid="<?=$get_req_id?>";
xmlhttp.open("GET","loadproductreq.php?q="+str + '&reqid='+ reqid,true);
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


<?php 
$select_INVProductDetails="select * from user_invoice_items where inv_id='$get_req_id_DECODE' order by id desc";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	$numitemsCheck=mysqli_num_rows($fetch_INVProductDetails);
	?>

<form action="user-invoice-action-req" method="post" enctype="multipart/form-data">

<?php function GeraHash($qtd){ $Caracteres = '123456789'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } ; 
$inv_randum_number=GeraHash(10);
$randum_number=GeraHash(5);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("gis"); 
$inv_id="".$inv_randum_number."".$invidprefix."".$temp_date."".$temp_time."";?>

<input type="hidden" name="randum_number" value="<?=$randum_number?>">
<input type="hidden" name="inv_id" value="<?=$get_req_id_DECODE?>">
<input type="hidden" name="invuser" value="<?=$getinvuser?>">

                                        <div class="example-container">
                                           <div class="example-content">
										   
										   
										   <?php if($result_invoice_details['inv_number']==NULL){?>
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
<?php }?>
							   
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

<?php 
$select_countadditems="select count(*) as numitems from user_invoice_items where inv_id='$get_req_id_DECODE'";
$fetch_countadditems=mysqli_query($db_conn,$select_countadditems);
$result_countadditems=mysqli_fetch_array($fetch_countadditems);
		?>
	
				<label class="form-label">Company Profile Name</label>
				<?php if($result_countadditems['numitems']==0){?>
                               <select required="" autofocus name="godownid" class="form-control" onchange="checkopeningstock(this.value)">
							   <option value="" hidden="">Select</option>
							   <?php $select_Godown="select * from company_godown where " . godown_finance_filter_sql($db_conn) . " order by id asc";
							   $fetch_Godown=mysqli_query($db_conn,$select_Godown);
							   while($result_Godown=mysqli_fetch_array($fetch_Godown))
							   {?>
						   <option value="<?=$result_Godown['id'];?>"><?=$result_Godown['gname'];?></option>
							   <?php }?>
							   </select>
				<?php }else{?>
				<select required="" name="godownid" class="form-control">
							   <option value="<?=$_REQUEST['gid'];?>"><?=$result_Godown['gname'];?></option>
							   </select>
				<?php }?>
							   <br/>
							   <div id="opstock"></div>
<!------------------------------------------------------------------------------>
<!------------------------------------------------------------------------------>
							   
          
<script type="text/javascript">
function showstockavailable(str){
if (str==""){document.getElementById("txtHint").innerHTML="";return;}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
document.getElementById("txtHintstock").innerHTML=xmlhttp.responseText;}}
var invuser="<?=$getinvuser;?>";
xmlhttp.open("GET","loadstockcheck.php?q="+str + '&invuser='+ invuser,true);
xmlhttp.send();}
</script>


<label class="form-label"><?=$lablenamedisplay;?>*</label>
<select name="customer_id" class="form-control">
<option value="<?php echo $CustomerID;?>"><?php echo $result_CUSTDetails['name'];?>, <?php echo $result_CUSTDetails['mobile_number'];?></option>
</select><br/>



<!---Date--->	
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!----id="bookingDate"--->
<label class="form-label">Invoice Date*</label>
<?php if($result_invoice_details['date']==NULL){?>
<input type="date" name="date" id="bookingDate" value="<?php echo date("Y-m-d");?>" required="" class="form-control">
<?php }else{?>
<input type="date" name="date" value="<?php echo $result_invoice_details['date'];?>" readonly required="" class="form-control">
<?php }?>
</br>

 <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        flatpickr("#bookingDate", {
            dateFormat: "Y-m-d",
            maxDate: "today" // Disallow future dates
        });
    </script>
<!---Date-end***-->	





<div class="item">
<?php if($_REQUEST['gid']==NULL){?>
<select required="" name="pr_id" required="" class="prinput" onChange="showproductsreq(this.value)">
<?php }else{?>
<select required="" name="pr_id" required="" autofocus class="prinput" onChange="showproductsreq(this.value)">
<?php }?>
<option value="" hidden="">Select Product</option>
<?php 
$select_requestdetails12="select * from stock_request_items where reqid='$get_req_id_DECODE' and qty>0";
	 $fetch_requestdetails12=mysqli_query($db_conn,$select_requestdetails12);
	 while($result_requestdetails12=mysqli_fetch_array($fetch_requestdetails12))
		 {
			 
			 $getprid=$result_requestdetails12['prid'];
	 
	    $select_Products_list="select * from products where id='$getprid'";
		$fetch_Products_list=mysqli_query($db_conn,$select_Products_list);
		$result_Products_list=mysqli_fetch_array($fetch_Products_list);
										
											?>
<option value="<?php echo $result_Products_list['id'];?>"><?php echo $result_Products_list['productName'];?></option>
										<?php }?>
</select>

<span id="txtHintPrreq">

<input type="number" min="0" name="qty" id="qty" onKeyup="totalkm()" required="" placeholder="Qty" class="numberinput">
<input type="number" min="0" name="amount" id="amount" onKeyup="totalkm()" required="" placeholder="Price">

		<input type="number" min="0" name="total" id="output" required="" placeholder="Total" class="numberinput">
		
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

</span>
		
		
		<span id="txtHintstock">
		 <button type="submit" name="addInvoice" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add</button>
		 </span>
		 
		 
    </div>
	
						
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
                                                            <th scope="col">Product</th>
															<th scope="col">HSN</th>
                                                            <th scope="col">Qty</th>
															<th scope="col">MRP</th>
															<th scope="col">Discount</th>
															<th scope="col">Amount</th>
															<th scope="col">GST</th>
                                                            <th scope="col">Total</th>
                                                            <th scope="col"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
													<?php
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
<td><?=$result_ProductDetails123['hsn'];?></td>
<td><?=$result_INVProductDetails['qty'];?></td>
<td><?=$result_INVProductDetails['amount'];?></td>
<td><?=$result_INVProductDetails['discount_amount'];?>(<?=$result_INVProductDetails['discount_percentage'];?>%)</td>
<td>&#8377;<?php echo number_format($result_INVProductDetails['subtotal'],2,'.','');?></td>
<td><?=$result_INVProductDetails['gstamount_total'];?>(<?=$result_INVProductDetails['gst_percentage'];?>%)</td>
<td align="right"><?php echo number_format($TotalAMount,2,'.','');?></td>
<td>
<a href="user-del-inv-product-req?inv_id=<?php echo $get_req_id;?>&&rowid=<?php echo $ItemRowid;?>&&invuser=<?=$getinvuser;?>&&userid=<?=$CustomerID;?>&&actionremove"onclick="return confirm('You want to delete confirm?');"><span class="badge bg-danger">Remove</span></a>
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
                                                <div class="invoice-info">
												
				   <div>
				   <b>Delivery Address:-</b> <br/><?=$result_requestdetails['delivery_address'];?>
				   </div>
                   <div style="margin-top:10px;"><b>Invoice Number:-</b> <br/><?=$result_invoice_details['inv_number'];?></div>
                   <div style="margin-top:10px;">
				   <b>Invoice Date:-</b> <br/><?php if($result_invoice_details['date']!=NULL){ echo date("d/M/Y",strtotime($result_invoice_details['date'])); }?>
				   </div>
				   
                                                </div>
                                            </div>
                                            <div class="col-lg-5"></div>
											
											<?php
											
$usertype=$result_invoice_details['to_user_type'];
		$userid=$result_invoice_details['to_user_id'];
		
		//get credit amount
		$selectcredit_amount="select * from return_credit where usertype='$usertype' and userid='$userid'";
		$fetchcredit_amount=mysqli_query($db_conn,$selectcredit_amount);
		$resultcredit_amount=mysqli_fetch_array($fetchcredit_amount);
		$creditamount=$resultcredit_amount['credit_amount'];
		if($creditamount!=NULL)
		{
		if($TotalAMount123>$creditamount){$creditless=$creditamount;}
		else{$creditless=$TotalAMount123;}
		}else{
			
			$creditless="0";
		}
		
		$totalshowed=$TotalAMount123-$creditless;
		?>
		
											<?php 
		$unround_value=$totalshowed;
		$roundvalue=round($unround_value);
		$roundoff=$roundvalue-$unround_value;
		
		$script_unround=$TotalAMount123-$creditless;
		$script_round=round($script_unround);
		?>
		
											<script>
  function totalamount(){
   var roundtotal = "<?php echo $script_round;?>";
   var cucharge = document.getElementById('cucharge').value;
   document.getElementById('outputTotalamount').value = (roundtotal*1)+(cucharge*1); 
 }
</script>

                                            <div class="col-lg-3">
                                                <div class="invoice-info">
												
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
				alert('Please make a confirm!');
                return true; // Allow form submission
            }
        }
    </script>
	
<form action="invoice-submit-req" method="post" enctype="multipart/form-data" onsubmit="return validateForm()">

<input type="hidden" name="invoice_id" value="<?=$get_req_id_DECODE;?>"/>
<input type="hidden" name="SubTotal" value="<?=$TotalAMount123;?>"/>

<?php $_SESSION['INVOICEFINISH']="1";?>

            <p class="bold">Subtotal <span>
			<input type="number" min="0" step="any" value="<?=number_format($TotalAMount123,2,'.','');?>" id="subtotal" disabled class="form-control"></span>
			</p>
			<br/>
													
           <!------<p class="bold">Discount <span>
		  <input type="number" step="any" onkeyup="totalamount()" value="0" id="discount" min="0" name="discount" required="">
		 </span></p>
		 <br/>------->
		 
		 
		 <!-----<p class="bold">Credit <span>
		  <input type="number" step="any" readonly value="<?=$creditless;?>" id="credit" min="0" name="credit" required="">
		 </span></p>
		<br/>--->
		
		<input type="hidden" name="discount" value="0"/>
		<input type="hidden" name="credit" value="0"/>
		
        
		<input type="hidden" name="roundoff" value="<?=number_format($roundoff,2,'.','');?>"/>
		
		<p class="bold">Round off <span>
		 <input type="number" min="0" class="form-control" step="any" value="<?=number_format($roundoff,2,'.','');?>" disabled>
		 </span>
		 </p><br/>
		 
		 <p class="bold">Courier Charges <span>
		 <input type="number" value="<?=$result_InvoieDetails['courier_charges'];?>" name="courier_charges" min="0" required="" onKeyup="totalamount()" id="cucharge" class="form-control">
		 </span>
		 </p><br/>
		 
		 <p class="bold">Total <span>
		 <input type="number" min="0" step="any" value="<?=number_format($roundvalue,2,'.','');?>" id="outputTotalamount" disabled class="form-control">
		 </span>
		 </p><br/>
		 
		 
		 <script>
  function receiptamount(){
   var totalbillamount = document.getElementById('outputTotalamount').value;
   var receivedamount = document.getElementById('receivedamount').value;
   document.getElementById('receivableamount').value = (totalbillamount*1)-(receivedamount*1); 
 }
</script>

		 <div class="bold">Received Amount<span>
		 <input type="number" min="0" required="" class="form-control" id="receivedamount" onkeyup="receiptamount()" name="receivedamount">
		 </span>
		 </div>
		 
		 <div class="bold">Receivable Amount<span>
		 <input type="number" min="0" id="receivableamount" value="0" disabled class="form-control">
		 </span>
		 <span id="error" style="color: red; display: none;font-size:12px;">Value must be non-negative.</span>
		 </div>
		 
		 
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
		 <textarea name="receipt_remarks" required class="form-control"></textarea>
		 </span>
		 </div>
		 
													<div style="clear:both;"></div>
                                                    <div class="invoice-info-actions">
     <?php if($numitemsCheck>0){?>
	 <button class="btn btn-primary" type="submit" name="invoice-submit">Submit Invoice</button>
	 <?php }else{ echo "<span style='color:#F00;'>Products are empty !</span>";}?>
                                                    </div>
													
													</form>
													
                                                </div>
                                            </div>
											
                                        </div>
                                    </div>
										
										
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
										
										
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