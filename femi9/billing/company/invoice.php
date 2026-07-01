<?php include("checksession.php"); date_default_timezone_set("Asia/Kolkata"); error_reporting(0);
include("config.php");

//1.invoice-action.php
//2.invoice-action2.php
//3.del-inv-product.php
//4.invoice-submit.php

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Invoice : <?php echo $business_name;?></title>

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
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Invoice</h5>
                                    </div>
                                    <div class="card-body">
									
									<?php if(isset($_REQUEST['AddedSuccess'])){?><div class="alert alert-success">one product added success.</div><?php }?>
								
								<?php if(isset($_REQUEST['ItemAlreadyExists'])){?><div class="alert alert-danger">invalid product, already exists.</div><?php }?>
								
								<?php if(isset($_REQUEST['InvalidStock'])){?><div class="alert alert-danger">invalid qty, out of stock.</div><?php }?>
								
								<?php if(isset($_REQUEST['DeleteSuccess'])){?><div class="alert alert-danger">Deleted ! one product deleted success.</div><?php }?>
									
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
xmlhttp.open("GET","loadPrice.php?q="+str,true);
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

						
				<?php if(isset($_REQUEST['InvoiceID']))	{
					
					$Invoice_ID_encode=$_REQUEST['InvoiceID'];
					$Invoice_ID=base64_decode($_REQUEST['InvoiceID']);
					
		//get invoice details
		$select_InvoieDetails="select * from invoice where inv_id='$Invoice_ID'";
		$fetch_InvoieDetails=mysqli_query($db_conn,$select_InvoieDetails);
		$result_InvoieDetails=mysqli_fetch_array($fetch_InvoieDetails);
		
		//customer details
		$CustomerID=$result_InvoieDetails['customer_id'];
		$select_CUSTDetails="select * from customers where id='$CustomerID'";
		$fetch_CUSTDetails=mysqli_query($db_conn,$select_CUSTDetails);
		$result_CUSTDetails=mysqli_fetch_array($fetch_CUSTDetails);
				?>
									
									
<form action="invoice-action2" method="post" enctype="multipart/form-data">

<input type="hidden" name="inv_id" value="<?=$Invoice_ID;?>">

                                        <div class="example-container">
                                           <div class="example-content">
          
<label class="form-label">Customer Name*</label>
<select name="customer_id" class="form-control">
<option value="<?php echo $CustomerID;?>" hidden=""><?php echo $result_CUSTDetails['name'];?>, <?php echo $result_CUSTDetails['mobile'];?></option>
<?php 
$selectCusList="select * from customers where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' order by name asc";
$fetch_Customers_list=mysqli_query($db_conn,$selectCusList);
while($result_Customers_list=mysqli_fetch_array($fetch_Customers_list))
		{
?>
<option value="<?php echo $result_Customers_list['id'];?>"><?php echo $result_Customers_list['name'];?>, <?php echo $result_Customers_list['mobile'];?></option>
<?php }?>
</select>

<label class="form-label">Invoice Date*</label>
<input type="date" name="date" value="<?php echo $result_InvoieDetails['date'];?>" required="" class="form-control">
</br>


    <div class="item">
	<select required="" name="pr_id" required="" autofocus onChange="showPrice(this.value)">
<option value="" hidden="">Select Product</option>
<?php $select_Products_list="select * from products order by id asc";
		$fetch_Products_list=mysqli_query($db_conn,$select_Products_list);
										while($result_Products_list=mysqli_fetch_array($fetch_Products_list))
										{
											?>
<option value="<?php echo $result_Products_list['id'];?>"><?php echo $result_Products_list['productName'];?></option>
										<?php }?>
</select>

<span id="txtHintPrice">
<input type="number" min="0" name="amount" id="amount" onKeyup="totalkm()" required="" placeholder="Price"></span>

        <input type="number" min="0" name="qty" id="qty" onKeyup="totalkm()" required="" placeholder="Quantity">
		<input type="number" min="0" name="total" id="output" required="" placeholder="Total">
		 <button type="submit" name="addInvoice" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add</button>
    </div>

						
                                            </div>
                                        </div>
										</form>

<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->
<!--------------------------------------------------------------------------------------------->										


<div class="row">
                                            <div class="table-responsive">
                                                <table class="table invoice-table">
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">#</th>
                                                            <th scope="col">Product Description</th>
                                                            <th scope="col">MRP</th>
                                                            <th scope="col">Qty</th>
                                                            <th scope="col">Total</th>
                                                            <th scope="col"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
													<?php
	$select_INVProductDetails="select * from invoice_items where inv_id='$Invoice_ID' order by id desc";
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
<td>&#8377;<?php echo number_format($result_INVProductDetails['amount'],2,'.','');?></td>
<td><?=$result_INVProductDetails['qty'];?></td>
<td align="right"><?php echo number_format($TotalAMount,2,'.','');?></td>
<td>
<a href="del-inv-product?inv_id=<?php echo $Invoice_ID_encode;?>&&rowid=<?php echo $ItemRowid;?>"onclick="return confirm('You want to delete confirm?');"><span class="badge bg-danger">Remove</span></a>
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
												
<form action="invoice-submit" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">
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
										
									<?php }else{?>
									
		<form action="invoice-action" method="post" enctype="multipart/form-data">

<?php function GeraHash($qtd){ $Caracteres = '123456789'; 
$QuantidadeCaracteres = strlen($Caracteres); $QuantidadeCaracteres--; $Hash=NULL; 
for($x=1;$x<=$qtd;$x++){ $Posicao = rand(0,$QuantidadeCaracteres); $Hash .= substr($Caracteres,$Posicao,1); } 
return $Hash; } ; 
$inv_randum_number=GeraHash(6);
$randum_number=GeraHash(5);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("gis"); 
$inv_id="CMP".$inv_randum_number."/".$temp_date."/".$temp_time."";?>

<input type="hidden" name="randum_number" value="<?=$randum_number?>">
<input type="hidden" name="inv_id" value="<?=$inv_id?>">

                                        <div class="example-container">
                                           <div class="example-content">
          
<label class="form-label">Customer Name*</label>
<select required="" name="customer_id" class="form-control" autofocus>
<option value="" hidden="">Select</option>
<?php 
$selectCusList="select * from customers where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl' order by name asc";
$fetch_Customers_list=mysqli_query($db_conn,$selectCusList);
while($result_Customers_list=mysqli_fetch_array($fetch_Customers_list))
		{
?>
<option value="<?php echo $result_Customers_list['id'];?>"><?php echo $result_Customers_list['name'];?></option>
<?php }?>
</select>

<label class="form-label">Invoice Date*</label>
<input type="date" name="date" value="<?php echo date("Y-m-d");?>" required="" class="form-control">
</br>


    <div class="item">
	<select required="" name="pr_id" required="" onChange="showPrice(this.value)">
<option value="" hidden="">Select Product</option>
<?php $select_Products_list="select * from products order by id asc";
		$fetch_Products_list=mysqli_query($db_conn,$select_Products_list);
										while($result_Products_list=mysqli_fetch_array($fetch_Products_list))
										{
											?>
<option value="<?php echo $result_Products_list['id'];?>"><?php echo $result_Products_list['productName'];?></option>
										<?php }?>
</select>


<span id="txtHintPrice">
<input type="number" min="0" name="amount" id="amount" onKeyup="totalkm()" required="" placeholder="Price"></span>

        <input type="number" min="0" name="qty" id="qty" onKeyup="totalkm()" required="" placeholder="Quantity">
		<input type="number" min="0" name="total" id="output" required="" placeholder="Total">
		 <button type="submit" name="addInvoice" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add</button>
    </div>
						
                                            </div>
                                        </div>
										</form>
										
										<?php }?>
										
										
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