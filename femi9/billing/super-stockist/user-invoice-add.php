<?php include("checksession.php");
date_default_timezone_set("Asia/Kolkata");
error_reporting(0);
include("config.php");

$get_action=$_REQUEST['action'];
$_SESSION['ACTIONEDIT']=$get_action;

$getinvuser=$_REQUEST['invuser'];
//invuser = stockiest
//invuser = distributor
//invuser = super_distributor

if($getinvuser=="stockiest"){
    header("Location: user-manage-invoice.php?invuser=stockiest");
    exit;
}

if($getinvuser=="stockiest")
{
	$displaytitle="Invoice - Stockist";
	$lablenamedisplay="Stockist Name";
	$tablename="stockiest";
	$invidprefix="CMPST";
	}
else if($getinvuser=="super_distributor")
{
	$displaytitle="Invoice - Super Distributor";
	$lablenamedisplay="Super Distributor Name";
	$tablename="super_distributor";
	$invidprefix="CMPSD";
	}
	else if($getinvuser=="distributor")
{
	$displaytitle="Invoice - Distributor";
	$lablenamedisplay="Distributor Name";
	$tablename="distributor";
	$invidprefix="CMPDST";
	}
else
{
	//$displaytitle="Invoice - Shop";
	//$lablenamedisplay="Shop Name";
	//$tablename="shop";
	//$invidprefix="CMPSHP";
	}

//1.user-invoice-action.php
//2.user-invoice-action2.php
//3.user-del-inv-product.php
//4.invoice-submit.php

// ✅ For Super Stockist: No godown concept - they use their own account
// The godown_id will be set to the super stockist's own ID during invoice submission
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
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
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
	
	<?php include("validate-scripts.php"); ?>

<style type="text/css">
/* ============================================
   MODERN MINIMALISTIC BLUE THEME
   ============================================ */

/* Alert Messages */
.alert {
    border-radius: 10px;
    border: none;
    padding: 15px 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

.alert-info {
    background: #dbeafe;
    color: #1e40af;
    border-left: 4px solid #3b82f6;
}

.alert-warning {
    background: #fef3c7;
    color: #92400e;
    border-left: 4px solid #f59e0b;
}

/* Page Title */
.page-title-modern {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px 25px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.page-title-modern h1 {
    color: #1e293b;
    font-size: 24px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-title-modern h1 i {
    color: #2563eb;
    font-size: 28px;
}

.page-title-modern .action-edit {
    color: #f59e0b;
    font-weight: 600;
    background: #fef3c7;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 14px;
}

.page-title-modern .menu-link {
    background: #2563eb;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 20px;
    transition: all 0.3s ease;
}

.page-title-modern .menu-link:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* Form Sections */
.form-section {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.form-section:hover {
    border-color: #bfdbfe;
}

.section-header {
    color: #475569;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f1f5f9;
}

.section-header i {
    color: #2563eb;
    font-size: 18px;
}

/* Form Controls */
.form-label {
    font-weight: 500;
    color: #374151;
    margin-bottom: 8px;
    font-size: 14px;
    display: block;
}

.form-label .required {
    color: #ef4444;
    margin-left: 3px;
}

.form-control, .form-select {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    background: white;
}

.form-control:hover, .form-select:hover {
    border-color: #bfdbfe;
}

/* Product Add Section */
.product-add-section {
    background: #f8fafc;
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
}

.product-add-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1.2fr 1.2fr 1fr 1fr 0.8fr;
    gap: 15px;
    align-items: end;
}

.input-group-modern {
    display: flex;
    flex-direction: column;
}

.input-group-modern label {
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
    font-size: 14px;
}

.btn-add {
    background: #2563eb;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
    width: 100%;
    justify-content: center;
}

.btn-add:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-add i {
    font-size: 18px;
}

/* Products Table */
.products-table-wrapper {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-top: 25px;
    overflow-x: auto;
}

.products-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.products-table thead {
    background: #f8fafc;
}

.products-table thead th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #475569;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e5e7eb;
}

.products-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    font-size: 14px;
}

.products-table tbody tr:hover {
    background: #f8fafc;
}

.product-name {
    font-weight: 600;
    color: #1e293b;
}

.text-right {
    text-align: right;
}

.badge-success {
    background: #d1fae5;
    color: #065f46;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Delete Button */
.btn-delete {
    background: #fee2e2;
    color: #991b1b;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-delete:hover {
    background: #ef4444;
    color: white;
}

/* Invoice Summary */
.invoice-summary-card {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 25px;
    margin-top: 25px;
}

.invoice-info {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.invoice-info p {
    margin: 0;
    color: #475569;
    font-size: 14px;
}

.invoice-info strong {
    color: #1e293b;
    font-weight: 600;
}

.invoice-info .bold {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.invoice-info .bold:last-child {
    border-bottom: none;
    padding-top: 15px;
    border-top: 2px solid #e5e7eb;
}

.invoice-info .bold strong {
    font-size: 16px;
    color: #1e293b;
}

.invoice-info-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

/* Buttons */
.btn-primary {
    background: #2563eb;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-primary i {
    font-size: 18px;
}

.btn-secondary {
    background: #e5e7eb;
    color: #374151;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.btn-secondary:hover {
    background: #d1d5db;
}

/* Modal */
.modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}

.modal-header {
    border-bottom: 2px solid #f1f5f9;
    padding: 20px 25px;
}

.modal-title {
    color: #1e293b;
    font-weight: 700;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    border-top: 2px solid #f1f5f9;
    padding: 20px 25px;
}

/* Responsive */
@media (max-width: 768px) {
    .product-add-grid {
        grid-template-columns: 1fr;
    }
    
    .page-title-modern {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .invoice-info .bold {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
}
</style>

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
									
									<?php if(isset($_REQUEST['AddedSuccess'])){?>
									<div class="alert alert-success">
									    <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">check_circle</i>
									    Product added successfully!
									</div>
									<?php }?>
								
								<?php if(isset($_REQUEST['ItemAlreadyExists'])){?>
								<div class="alert alert-danger">
								    <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">error</i>
								    Invalid product - already exists in this invoice.
								</div>
								<?php }?>
								
								<?php if(isset($_REQUEST['InvalidStock'])){?>
								<div class="alert alert-danger">
								    <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">inventory_2</i>
								    Invalid quantity - out of stock.
								</div>
								<?php }?>
								
								<?php if(isset($_REQUEST['DeleteSuccess'])){?>
								<div class="alert alert-danger">
								    <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">delete</i>
								    Product deleted successfully!
								</div>
								<?php }?>
								
								<?php if(isset($_REQUEST['invoicealready'])){?>
								<div class="alert alert-danger">
								    <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">warning</i>
								    Invoice number already exists!
								</div>
									<?php }?>
									
									<?php if(isset($_REQUEST['InvoiceUpdatedSuccess'])){?>
									<div class="alert alert-success">
									    <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">check_circle</i>
									    Invoice number updated successfully!
									</div>
									<?php }?>
									
									<!-- Page Title -->
									<div class="page-title-modern">
									    <h1>
									        <i class="material-icons">receipt_long</i>
									        <?php if($_REQUEST['action']=="edit"){ ?>
									        <span class="action-edit">UPDATE</span>
									        <?php } ?>
									        <?=$displaytitle;?>
									    </h1>
									    <a href="user-manage-invoice?invuser=<?=$getinvuser;?>" class="menu-link" title="Manage Invoices">
									        <i class="material-icons">list</i>
									    </a>
									</div>
									
<!--------------------------------------------------------------------------------------------->
<!-- JAVASCRIPT FUNCTIONS -->
<!--------------------------------------------------------------------------------------------->
						
<script type="text/javascript">
function showPrice(str){
if (str==""){
    document.getElementById("amount").value="";
    document.getElementById("amount").removeAttribute("readonly");
    return;
}
if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
if (xmlhttp.readyState==4 && xmlhttp.status==200){
    try {
        // Parse JSON response
        var response = JSON.parse(xmlhttp.responseText);
        
        // Set price value
        document.getElementById("amount").value = response.price;
        
        // Set readonly attribute based on customer type
        var amountField = document.getElementById("amount");
        if (response.readonly) {
            amountField.setAttribute("readonly", "readonly");
            amountField.style.backgroundColor = "#f3f4f6"; // Light gray background
            amountField.style.cursor = "not-allowed";
        } else {
            amountField.removeAttribute("readonly");
            amountField.style.backgroundColor = "";
            amountField.style.cursor = "";
        }
        
        // Trigger totalkm to calculate total
        totalkm();
    } catch(e) {
        console.error("Error parsing price response:", e);
        document.getElementById("amount").value = "0.00";
    }
}}
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

<!--------------------------------------------------------------------------------------------->
<!-- EDIT MODE: EXISTING INVOICE -->
<!--------------------------------------------------------------------------------------------->
						
<?php if(isset($_REQUEST['InvoiceID'])) {
					
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
													
<form action="user-invoice-action2" method="post" enctype="multipart/form-data">

<input type="hidden" name="inv_id" value="<?=$Invoice_ID;?>">
<input type="hidden" name="invuser" value="<?=$getinvuser?>">
<!-- ✅ For Super Stockist: Use their own ID as godown -->
<input type="hidden" name="godownid" value="<?=$onboard_userID;?>">

<!-- Invoice Details Section -->
<div class="form-section">
    <div class="section-header">
        <i class="material-icons">info</i>
        Invoice Details
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label"><?=$lablenamedisplay;?> <span class="required">*</span></label>
            
            <?php
            $select_countitems="select count(*) as numitemscount from user_invoice_items where inv_id='$Invoice_ID'";
            $fetch_countitems=mysqli_query($db_conn,$select_countitems);
            $result_countitems=mysqli_fetch_array($fetch_countitems);
            $totalcountitems=$result_countitems['numitemscount'];
            ?>
            
            <select name="<?php echo $totalcountitems>0 ? '' : 'customer_id'; ?>" class="form-control" <?php if($totalcountitems>0){echo 'disabled';}?> id="customer-select-edit">
                <option value="<?php echo $CustomerID;?>"><?php echo $result_CUSTDetails['name'];?>, <?php echo $result_CUSTDetails['mobile_number'];?></option>
                <?php 
                if($totalcountitems==0) {
                    // ✅ Show only entities onboarded by this super stockist
                    $selectCusList="select * from ".$tablename." where onboard_userTYPE='$onboard_userTYPE' and onboard_userID='$onboard_userID' and account_status='active' order by name asc";
                    $fetch_Customers_list=mysqli_query($db_conn,$selectCusList);
                    while($result_Customers_list=mysqli_fetch_array($fetch_Customers_list)) {
                        $user_districtID=$result_Customers_list['district_id'];
                        $select_User_districtName="select * from district where id='$user_districtID'";
                        $fetch_user_districtName=mysqli_query($db_conn,$select_User_districtName);
                        $result_user_districtName=mysqli_fetch_array($fetch_user_districtName);
                        $user_districtName=$result_user_districtName['dist_name'];
                        
                        if($getinvuser=="stockiest"){
                            $UserName_SHOW="".strtoupper($result_Customers_list['name'])." (".strtoupper($user_districtName)."), ".$result_Customers_list['mobile_number']."";
                        }else{
                            $UserName_SHOW="".strtoupper($result_Customers_list['name']).", ".$result_Customers_list['mobile_number']."";	
                        }
                ?>
                <option value="<?php echo $result_Customers_list['temp_id'];?>"><?php echo $UserName_SHOW;?></option>
                <?php
                    }
                }
                ?>
            </select>
            
            <?php 
            // ✅ When disabled, add hidden input to preserve customer_id value
            if($totalcountitems > 0) { 
            ?>
            <input type="hidden" name="customer_id" value="<?php echo $CustomerID; ?>">
            <?php } ?>
            
            <!-- ✅ ADVANCE BALANCE DISPLAY (Only for Stockists) -->
            <?php if($getinvuser=="stockiest"){ ?>
            <div id="balance-display-container" style="margin-top: 15px; display: none;">
                <div id="balance-display"></div>
            </div>
            <?php } ?>
        </div>
        
        <div class="col-md-6 mb-3">
            <label class="form-label">Invoice Date <span class="required">*</span></label>
            <input type="date" readonly name="date" value="<?=$result_InvoieDetails['date'];?>" required="" class="form-control">
        </div>
    </div>
</div>

<?php if($amount_received_fully==0){?>
<!-- ✅ Product Section (Conditionally shown for non-stockists or when stockist has balance) -->
<div id="product-add-section" style="display: block;">
    <div class="product-add-section">
        <div class="section-header" style="border: none; padding-bottom: 15px;">
            <i class="material-icons">add_shopping_cart</i>
            Add Product to Invoice
        </div>
        
        <div class="product-add-grid">
            <div class="input-group-modern">
                <label>Product <span class="required">*</span></label>
                <select required name="pr_id" class="form-control" onChange="showPrice(this.value)">
                    <option value="" hidden="">Select Product</option>
                    <?php 
                    $select_Products_list="select * from products order by id asc";
                    $fetch_Products_list=mysqli_query($db_conn,$select_Products_list);
                    while($result_Products_list=mysqli_fetch_array($fetch_Products_list)) {
                    ?>
                    <option value="<?php echo $result_Products_list['id'];?>"><?php echo $result_Products_list['productName'];?></option>
                    <?php }?>
                </select>
            </div>
            
            <div class="input-group-modern">
                <label>Qty <span class="required">*</span></label>
                <input type="number" min="0" name="qty" id="qty" onKeyup="totalkm()" required placeholder="0" class="form-control">
            </div>
            
            <div class="input-group-modern">
                <label>Price <span class="required">*</span></label>
                <input type="number" min="0" step="any" name="amount" id="amount" onKeyup="totalkm()" placeholder="0.00" class="form-control" required>
            </div>
            
            <div class="input-group-modern">
                <label>Total</label>
                <input type="number" min="0" name="total" id="output" readonly step="any" required placeholder="0.00" class="form-control">
            </div>
            
            <script>
            function discamount(){
                var output = document.getElementById('output').value;
                var discountpercentae = document.getElementById('discountpercentae').value;
                var outputdisaamount=(output*discountpercentae/100);
                document.getElementById('discountamount').value = outputdisaamount.toFixed(2); 
            }
            </script>
            
            <div class="input-group-modern">
                <label>Disc(%)</label>
                <input type="number" min="0" step="any" id="discountpercentae" name="discount_percentage" onKeyup="discamount()" required placeholder="0" class="form-control">
            </div>
            
            <div class="input-group-modern">
                <label>Disc(₹)</label>
                <input type="number" min="0" id="discountamount" name="discount_amount" step="any" required placeholder="0.00" class="form-control">
            </div>
            
            <div class="input-group-modern" style="display: flex; align-items: flex-end;">
                <span id="txtHintstock">
                    <button type="submit" name="addInvoice2" class="btn-add">
                        <i class="material-icons">add</i> Add
                    </button>
                </span>
            </div>
        </div>
    </div>
</div>
<?php }?>

</form>

<!--------------------------------------------------------------------------------------------->
<!-- INVOICE PRODUCTS LIST -->
<!--------------------------------------------------------------------------------------------->

<?php 
$select_product_list="select * from user_invoice_items where inv_id='$Invoice_ID'";
$fetch_product_list=mysqli_query($db_conn,$select_product_list);

$select_count_list="select count(*) as numofproducts from user_invoice_items where inv_id='$Invoice_ID'";
$fetch_count_list=mysqli_query($db_conn,$select_count_list);
$result_count_list=mysqli_fetch_array($fetch_count_list);
$CountProducts=$result_count_list['numofproducts'];

if($CountProducts>0){
?>

<div class="products-table-wrapper">
    <table class="products-table">
        <thead>
            <tr>
                <th>S.No</th>
                <th>Product Name</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Price</th>
                <th class="text-right">Total</th>
                <th class="text-right">Disc(%)</th>
                <th class="text-right">Disc(₹)</th>
                <th class="text-right">Final</th>
                <?php if($amount_received_fully==0){?>
                <th class="text-right">Action</th>
                <?php }?>
            </tr>
        </thead>
        <tbody>
        <?php 
        $sno=1;
        $TotalAMount123=0;
        while($result_product_list=mysqli_fetch_array($fetch_product_list)){
            $select_pr_details="select * from products where id='".$result_product_list['pr_id']."'";
            $fetch_pr_details=mysqli_query($db_conn,$select_pr_details);
            $result_pr_details=mysqli_fetch_array($fetch_pr_details);
            
            $finalamount=$result_product_list['subtotal']-$result_product_list['discount_amount'];
            $TotalAMount123=$TotalAMount123+$finalamount;
            
            $ItemRowid=base64_encode($result_product_list['id']);

        ?>
            <tr>
                <td><?php echo $sno;?></td>
                <td class="product-name"><?php echo $result_pr_details['productName'];?></td>
                <td class="text-right"><?php echo $result_product_list['qty'];?></td>
                <td class="text-right">₹<?php echo number_format($result_product_list['amount'],2);?></td>
                <td class="text-right">₹<?php echo number_format($result_product_list['subtotal'],2);?></td>
                <td class="text-right"><?php echo $result_product_list['discount_percentage'];?>%</td>
                <td class="text-right">₹<?php echo number_format($result_product_list['discount_amount'],2);?></td>
                <td class="text-right"><strong>₹<?php echo number_format($finalamount,2);?></strong></td>
                <?php if($amount_received_fully==0){?>
                <td class="text-right">
                    <a href="user-del-inv-product?inv_id=<?php echo $Invoice_ID_encode;?>&&rowid=<?php echo $ItemRowid;?>&&&&invuser=<?=$getinvuser;?>&&userid=<?=$CustomerID;?>&&actionremove" 
                       onclick="return confirm('Delete this product from invoice?');" 
                       class="badge-remove">
                       <i class="material-icons" style="font-size: 14px; vertical-align: middle;">delete</i> Remove
                    </a>
                </td>
                <?php }?>
            </tr>
        <?php 
        $sno++;
        }?>
        </tbody>
    </table>
</div>

<!--------------------------------------------------------------------------------------------->
<!-- INVOICE SUMMARY -->
<!--------------------------------------------------------------------------------------------->

<div class="invoice-summary-card">
    <div class="row">
        <div class="col-lg-6">
            <div class="invoice-info">
                <p><strong>Invoice Number:</strong> 
                    <?php if($get_action=="edit") {?>
                    <a href="#" id="linkcaption" data-bs-toggle="modal" data-bs-target="#exampleModalLive">
                        <?php echo $result_InvoieDetails['inv_number'];?> <i class="material-icons" style="font-size: 16px;">edit</i>
                    </a>
                    <?php }else{?>
                    <span><?php echo $result_InvoieDetails['inv_number'];?></span>
                    <?php }?>
                </p>
                
                <!-- Modal for Invoice Number Update -->
                <div class="modal fade" id="exampleModalLive" tabindex="-1" aria-labelledby="exampleModalLiveLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="exampleModalLiveLabel">
                                    Update Invoice Number<br/>
                                    <small class="text-muted"><?php echo $result_InvoieDetails['inv_number'];?></small>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post" onsubmit="return confirm('Confirm update?');" action="update_invoice_action">	
                                <input type="hidden" name="invuser" value="<?php echo $_REQUEST['invuser'];?>">
                                <input type="hidden" name="InvoiceID" value="<?php echo $_REQUEST['InvoiceID'];?>">
                                <input type="hidden" name="action" value="<?php echo $_REQUEST['action'];?>">
                                <input type="hidden" name="redirurl" value="user-invoice-add">
                                <input type="hidden" name="tblenme" value="1">
                                
                                <div class="modal-body">
                                    <label class="form-label">New Invoice Number</label>
                                    <input type="text" name="invnumber" placeholder="Enter invoice number" class="form-control" required onkeypress="restrictSpecialChars(event)">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="updateInvoiceNum" class="btn btn-primary">
                                        <i class="material-icons">update</i> Update
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <p><strong>Invoice Date:</strong> <span><?php echo date("d/M/Y",strtotime($result_InvoieDetails['date']));?></span></p>
            </div>
        </div>
        
        <div class="col-lg-6">
            <?php
            if($get_action=="edit") {
                $creditless="0";
                $totalshowed=$TotalAMount123-$creditless+$result_InvoieDetails['courier_charges'];
            }else{
                $usertype=$result_InvoieDetails['to_user_type'];
                $userid=$result_InvoieDetails['to_user_id'];
                
                $selectcredit_amount="select * from return_credit where usertype='$usertype' and userid='$userid'";
                $fetchcredit_amount=mysqli_query($db_conn,$selectcredit_amount);
                $resultcredit_amount=mysqli_fetch_array($fetchcredit_amount);
                $creditamount="0";
                if($creditamount!=NULL) {
                    if($TotalAMount123>$creditamount){$creditless=$creditamount;}
                    else{$creditless=$TotalAMount123;}
                }else{
                    $creditless="0";
                }
                $totalshowed=$TotalAMount123-$creditless;
            }
            
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
            
            function validateForm() {
                // ✅ Check if user type requires advance payment (only stockist for super stockist login)
                const customerType = '<?php echo $getinvuser; ?>';
                const advanceTypes = ['stockiest'];
                const requiresAdvance = advanceTypes.includes(customerType);
                
                // Skip validation for advance payment users (no manual receipt entry)
                if (requiresAdvance) {
                    return true;
                }
                
                // Validate for other user types (distributor, super_distributor)
                const amountInput = document.getElementById('receivableamount');
                if (!amountInput) return true; // Element doesn't exist
                
                const amount = parseFloat(amountInput.value);
                const errorSpan = document.getElementById('error');
            
                if (isNaN(amount) || amount < 0) {
                    if (errorSpan) errorSpan.style.display = 'inline';
                    return false;
                } else {
                    if (errorSpan) errorSpan.style.display = 'none';
                    return true;
                }
            }
            
            function receiptamount(){
                var totalbillamount = document.getElementById('outputTotalamount').value;
                var receivedamount = document.getElementById('receivedamount').value;
                document.getElementById('receivableamount').value = (totalbillamount*1)-(receivedamount*1); 
            }
            </script>
            
            <form action="user-invoice-submit" id="myForm" method="post" enctype="multipart/form-data" onsubmit="return validateForm()">
                <input type="hidden" name="invoice_id" value="<?=$Invoice_ID;?>"/>
                <input type="hidden" name="SubTotal" value="<?=$TotalAMount123;?>"/>
                <?php $_SESSION['INVOICEFINISH']="1";?>
                
                <input type="hidden" name="discount" value="0"/>
                <input type="hidden" name="credit" value="0"/>
                <input type="hidden" name="roundoff" value="<?=number_format($roundoff,2,'.','');?>"/>
                
                <?php 
                if($CountProducts==0 && $result_InvoieDetails['sub_total']>0) {
                    $update_zero_invoice="update user_invoice set sub_total='0',discount='0',total='0' where inv_id='$Invoice_ID'";
                    mysqli_query($db_conn,$update_zero_invoice);		
                }
                ?>
                
                <div class="invoice-info">
                    <div class="bold">
                        Subtotal
                        <span><input type="number" min="0" step="any" value="<?=number_format($TotalAMount123,2,'.','');?>" disabled class="form-control"></span>
                    </div>
                    
                    <div class="bold">
                        Round off
                        <span><input type="number" min="0" step="any" value="<?=number_format($roundoff,2,'.','');?>" disabled class="form-control"></span>
                    </div>
                    
                    <div class="bold">
                        Courier Charges
                        <span><input type="number" value="<?=$result_InvoieDetails['courier_charges'];?>" name="courier_charges" min="0" required onKeyup="totalamount()" id="cucharge" class="form-control"></span>
                    </div>
                    
                    <div class="bold">
                        <strong>Total</strong>
                        <span><input type="number" min="0" step="any" value="<?=number_format($roundvalue,2,'.','');?>" id="outputTotalamount" disabled class="form-control"></span>
                    </div>
                    
                    <?php 
                        // ✅ Check if advance payment is mandatory (only for stockist in SS login)
                        require_once("advance-payment-functions.php");
                        $is_advance_mandatory = ($getinvuser == "stockiest");
                        
                        if($get_action=="edit") {
                            // Edit mode - always show for all user types
                        ?>
                            
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
    // Always read from the live total (which includes courier charges)
    var totalbillamount = parseFloat(document.getElementById('outputTotalamount').value) || 0;
    var alreadyreceived = <?= number_format($already_received, 2, '.', '') ?>;
    var balancedue      = totalbillamount - alreadyreceived;
    if (balancedue < 0) balancedue = 0;

    var receivedamount  = parseFloat(document.getElementById('receivedamount').value) || 0;
    var receivable      = balancedue - receivedamount;
    document.getElementById('receivableamount').value = receivable.toFixed(2);

    // Also update the max attribute on received amount input
    document.getElementById('receivedamount').setAttribute('max', balancedue.toFixed(2));
    document.getElementById('receivedamount').setAttribute('placeholder', 'Max: ' + balancedue.toFixed(2));
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
                            
                            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
                            <div class="bold">
                                Invoice Date
                                <span><input type="date" name="update_invoice_date" class="form-control" id="bookingDateu" value="<?=$result_InvoieDetails['date'];?>"></span>
                            </div>
                            
                            <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
                            <script>
                            flatpickr("#bookingDateu", {
                                dateFormat: "Y-m-d",
                                maxDate: "today"
                            });
                            </script>
                        <?php 
                        } else {
                            // New invoice mode - hide receipt fields ONLY for Stockist
                            if(!$is_advance_mandatory) {
                                // Show receipt fields for Distributor, Super Distributor
                        ?>
                            <div class="bold">
                                Received Amount
                                <span><input type="number" min="0" required step="any" id="receivedamount" onkeyup="receiptamount()" name="receivedamount" class="form-control"></span>
                            </div>
                            
                            <div class="bold">
                                Receivable Amount
                                <span>
                                    <input type="number" min="0" required id="receivableamount" readonly class="form-control">
                                    <span id="error" style="color: #ef4444; display: none; font-size: 12px; margin-top: 4px;">Value must be non-negative</span>
                                </span>
                            </div>
                            
                            <div class="bold">
                                Received Method
                                <span>
                                    <select name="receipt_method" required class="form-control">
                                        <option value="" hidden>Select Method</option>
                                        <option>--None--</option>
                                        <option>Cash</option>
                                        <option>UPI</option>
                                        <option>Bank Transfer</option>
                                        <option>Deposit</option>
                                    </select>
                                </span>
                            </div>
                            
                            <div class="bold">
                                Remarks
                                <span><textarea name="receipt_remarks" required class="form-control" rows="2" placeholder="Payment remarks"></textarea></span>
                            </div>
                        <?php 
                            } else {
                                // For Stockist - hide receipt fields, auto-filled by system
                        ?>
                            <!-- Hidden fields - auto-filled for advance payment users -->
                            <input type="hidden" name="receivedamount" value="0">
                            <input type="hidden" name="receipt_method" value="Advance Payment">
                            <input type="hidden" name="receipt_remarks" value="Paid via advance payment adjustment">
                            
                            <!-- Show info message -->
                            <div class="alert alert-info" style="margin-top: 20px;">
                                <i class="material-icons" style="vertical-align: middle; font-size: 20px;">info</i>
                                <strong>Payment via Advance Balance</strong>
                                <br/><small>Invoice amount will be automatically deducted from advance payment balance upon submission.</small>
                            </div>
                        <?php 
                            }
                        }
                        ?>
                    
                    <?php 
                    if($get_action=="edit") { 
                        $sumbitlable="Update Invoice";
                    }else{
                        $sumbitlable="Submit Invoice"; 
                    }
                    ?>
                    
                    <?php if($amount_received_fully==0){?>
                    <div class="invoice-info-actions">
                        <?php if($CountProducts>0){?>
                        <button class="btn btn-primary" type="submit" name="invoice-submit" onClick="return confirm('Confirm submission?');">
                            <i class="material-icons">check_circle</i>
                            <?=$sumbitlable;?>
                        </button>
                        <?php }?>
                    </div>
                    <?php }else{?>
                    <div style="margin-top: 20px;">
                        <span class='badge-success'>
                            <i class="material-icons" style="font-size: 16px; vertical-align: middle;">lock</i>
                            Not editable - Fully Paid Invoice
                        </span>
                    </div>
                    <?php }?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php } // Close CountProducts>0 check ?>

<?php } else { 
// NEW INVOICE MODE
?>

<form action="user-invoice-action" method="post" enctype="multipart/form-data">

<?php 
function GeraHash($qtd){ 
    $Caracteres = '123456789'; 
    $QuantidadeCaracteres = strlen($Caracteres); 
    $QuantidadeCaracteres--; 
    $Hash=NULL; 
    for($x=1;$x<=$qtd;$x++){ 
        $Posicao = rand(0,$QuantidadeCaracteres); 
        $Hash .= substr($Caracteres,$Posicao,1); 
    } 
    return $Hash; 
}

$inv_randum_number=GeraHash(10);
$randum_number=GeraHash(3);
date_default_timezone_set("Asia/Kolkata");
$temp_date=date("dmy");
$temp_time=date("gis"); 
$inv_id="".$inv_randum_number."".$invidprefix."".$temp_date."".$temp_time."";
?>

<input type="hidden" name="randum_number" value="<?=$randum_number?>">
<input type="hidden" name="inv_id" value="<?=$inv_id?>">
<input type="hidden" name="invuser" value="<?=$getinvuser?>">
<input type="hidden" name="username" value="<?=$_SESSION['LOGIN_USER'];?>">
<input type="hidden" name="usertype" value="<?=$Result_Log_users_Dtails134['usertype'];?>">
<!-- ✅ For Super Stockist: Use their own ID as godown -->
<input type="hidden" name="godownid" value="<?=$onboard_userID;?>">

<!-- New Invoice Form Section -->
<div class="form-section">
    <div class="section-header">
        <i class="material-icons">edit_document</i>
        Create New Invoice
    </div>
    
    <div class="row">
        <!-- Invoice Number -->
        <div class="col-md-6 mb-3">
            <label class="form-label">Invoice Number <span class="required">*</span></label>
            <input type="text" onKeyup="showInvoiceDuplicate(this.value)" name="inv_number" autofocus required onkeypress="restrictSpecialChars(event)" class="form-control" placeholder="Enter invoice number">
            <span id="txtHintInvoice"></span>
        </div>
        
        <script type="text/javascript">
        function showInvoiceDuplicate(str){
            if (str==""){document.getElementById("txtHint").innerHTML="";return;}
            if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
            xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
            if (xmlhttp.readyState==4 && xmlhttp.status==200){
            document.getElementById("txtHintInvoice").innerHTML=xmlhttp.responseText;}}
            xmlhttp.open("GET","loadInvoiceNumberUSER.php?q="+str,true);
            xmlhttp.send();
        }
        </script>
        
        <!-- Customer Selection -->
        <div class="col-md-6 mb-3">
            <label class="form-label"><?=$lablenamedisplay;?> <span class="required">*</span></label>
            <select required name="customer_id" class="js-states form-control" tabindex="-1" style="display: none; width: 100%" onchange="showstockavailable(this.value)" id="customer-select">
                <option value="" hidden>Select Customer</option>
                <?php 
                // ✅ Show only entities onboarded by this super stockist
                $selectCusList="select * from ".$tablename." where onboard_userTYPE='$onboard_userTYPE' and onboard_userID='$onboard_userID' and account_status='active' order by name asc";
                
                $fetch_Customers_list=mysqli_query($db_conn,$selectCusList);
                while($result_Customers_list=mysqli_fetch_array($fetch_Customers_list)) {
                    $user_districtID=$result_Customers_list['district_id'];
                    $select_User_districtName="select * from district where id='$user_districtID'";
                    $fetch_user_districtName=mysqli_query($db_conn,$select_User_districtName);
                    $result_user_districtName=mysqli_fetch_array($fetch_user_districtName);
                    $user_districtName=$result_user_districtName['dist_name'];
                    
                    if($getinvuser=="stockiest"){
                        $UserName_SHOW="".strtoupper($result_Customers_list['name'])." (".strtoupper($user_districtName)."), ".$result_Customers_list['mobile_number']."";
                    }else{
                        $UserName_SHOW="".strtoupper($result_Customers_list['name']).", ".$result_Customers_list['mobile_number']."";	
                    }
                ?>
                <option value="<?=$result_Customers_list['temp_id'];?>"><?=$UserName_SHOW;?></option>
                <?php }?>
            </select>
            
            <!-- ✅ ADVANCE BALANCE DISPLAY (Only for Stockists) -->
            <?php if($getinvuser=="stockiest"){ ?>
            <div id="balance-display-container" style="margin-top: 15px; display: none;">
                <div id="balance-display"></div>
            </div>
            <?php } ?>
        </div>
        
        <script type="text/javascript">
        function showstockavailable(str){
            if (str==""){document.getElementById("txtHint").innerHTML="";return;}
            if (window.XMLHttpRequest){xmlhttp=new XMLHttpRequest();}else{
            xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");}xmlhttp.onreadystatechange=function(){
            if (xmlhttp.readyState==4 && xmlhttp.status==200){
            document.getElementById("txtHintstock").innerHTML=xmlhttp.responseText;}}
            var invuser="<?=$getinvuser;?>";
            xmlhttp.open("GET","loadstockcheck.php?q="+str + '&invuser='+ invuser,true);
            xmlhttp.send();
        }
        </script>
        
        <!-- Invoice Date -->
        <div class="col-md-6 mb-3">
            <label class="form-label">Invoice Date <span class="required">*</span></label>
            <input type="date" id="bookingDate" name="date" value="<?php echo date("Y-m-d");?>" required class="form-control">
        </div>
        
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <script>
        flatpickr("#bookingDate", {
            dateFormat: "Y-m-d",
            maxDate: "today"
        });
        </script>
    </div>
</div>

<!-- ✅ Product Section (Conditionally shown for non-stockists or when stockist has balance) -->
<div id="product-add-section" style="display: block;">
    <div class="product-add-section">
        <div class="section-header" style="border: none; padding-bottom: 15px;">
            <i class="material-icons">add_shopping_cart</i>
            Add Product to Invoice
        </div>
        
        <div class="product-add-grid">
            <div class="input-group-modern">
                <label>Product <span class="required">*</span></label>
                <select required name="pr_id" class="form-control" onchange="showPrice(this.value)">
                    <option value="" hidden>Select Product</option>
                    <?php 
                    $select_Products_list="select * from products order by id asc";
                    $fetch_Products_list=mysqli_query($db_conn,$select_Products_list);
                    while($result_Products_list=mysqli_fetch_array($fetch_Products_list)) {
                    ?>
                    <option value="<?=$result_Products_list['id'];?>"><?=$result_Products_list['productName'];?></option>
                    <?php }?>
                </select>
            </div>
            
            <div class="input-group-modern">
                <label>Qty <span class="required">*</span></label>
                <input type="number" min="0" name="qty" id="qty" onKeyup="totalkm()" required placeholder="0" class="form-control">
            </div>
            
            <div class="input-group-modern">
                <label>Price <span class="required">*</span></label>
                <input type="number" min="0" name="amount" id="amount" onKeyup="totalkm()" step="any" placeholder="0.00" class="form-control" required>
            </div>
            
            <div class="input-group-modern">
                <label>Total</label>
                <input type="number" min="0" name="total" id="output" readonly step="any" required placeholder="0.00" class="form-control">
            </div>
            
            <script>
            function discamount(){
                var output = document.getElementById('output').value;
                var discountpercentae = document.getElementById('discountpercentae').value;
                var outputdisaamount=(output*discountpercentae/100);
                document.getElementById('discountamount').value = outputdisaamount.toFixed(2); 
            }
            </script>
            
            <div class="input-group-modern">
                <label>Disc(%)</label>
                <input type="number" min="0" step="any" id="discountpercentae" name="discount_percentage" onKeyup="discamount()" required placeholder="0" class="form-control">
            </div>
            
            <div class="input-group-modern">
                <label>Disc(₹)</label>
                <input type="number" min="0" id="discountamount" name="discount_amount" step="any" required placeholder="0.00" class="form-control">
            </div>
            
            <div class="input-group-modern" style="display: flex; align-items: flex-end;">
                <span id="txtHintstock">
                    <button type="submit" name="addInvoice" class="btn-add">
                        <i class="material-icons">add</i> Add
                    </button>
                </span>
            </div>
        </div>
    </div>
</div>

</form>

<?php }?>

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
    
    <!-- ✅ ADVANCE BALANCE CHECK JAVASCRIPT (Only for Stockists) -->
    <?php if($getinvuser=="stockiest"){ ?>
    <script>
    // ============================================================================
    // ADVANCE BALANCE CHECK - Stockist Only (Super Stockist Login)
    // ============================================================================
    
    let currentBalance = 0;
    let isMandatory = false;
    let canProceed = false;
    
    function checkAdvanceBalance() {
        const customerSelect = document.querySelector('select[name="customer_id"]');
        const productSection = document.getElementById('product-add-section');
        const balanceContainer = document.getElementById('balance-display-container');
        const balanceDisplay = document.getElementById('balance-display');
        
        if (!customerSelect) {
            console.log('Customer select not found');
            return;
        }
        
        const customerId = customerSelect.value;
        const customerType = '<?php echo $getinvuser; ?>';
        // ✅ For Super Stockist: Use their own ID (not godown)
        const toUserId = '<?php echo $onboard_userID; ?>';
        
        if (!customerId) {
            if (balanceContainer) balanceContainer.style.display = 'none';
            if (productSection) productSection.style.display = 'block';
            return;
        }
        
        if (balanceDisplay && balanceContainer) {
            balanceContainer.style.display = 'block';
            balanceDisplay.innerHTML = `
                <div class="alert alert-info">
                    <i class="material-icons" style="vertical-align: middle; font-size: 20px;">hourglass_empty</i>
                    Checking advance balance...
                </div>
            `;
        }
        
        fetch('get-advance-balance.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `customer_id=${encodeURIComponent(customerId)}&customer_type=${encodeURIComponent(customerType)}&to_user_id=${encodeURIComponent(toUserId)}`
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            console.log('Balance check response:', data);
            
            currentBalance = parseFloat(data.balance) || 0;
            isMandatory = data.is_mandatory || false;
            canProceed = data.can_proceed || false;
            
            if (balanceDisplay && balanceContainer) {
                if (!isMandatory) {
                    balanceContainer.style.display = 'none';
                    if (productSection) productSection.style.display = 'block';
                    console.log('Advance payment not mandatory');
                } else if (canProceed) {
                    balanceDisplay.innerHTML = `
                        <div class="alert alert-success" style="border-left: 4px solid #10b981;">
                            <i class="material-icons" style="vertical-align: middle; font-size: 24px; color: #10b981;">account_balance_wallet</i>
                            <strong>Available Advance Balance:</strong> ₹${currentBalance.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                            <br/><small style="color: #065f46;">Products can be added. Balance will be adjusted on invoice submission.</small>
                        </div>
                    `;
                    balanceContainer.style.display = 'block';
                    if (productSection) productSection.style.display = 'block';
                } else {
                    const userTypeLabel = customerType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    balanceDisplay.innerHTML = `
                        <div class="alert alert-danger" style="border-left: 4px solid #ef4444;">
                            <i class="material-icons" style="vertical-align: middle; font-size: 24px; color: #ef4444;">warning</i>
                            <strong>No Advance Balance Available!</strong>
                            <br/>${data.message || 'Please add advance payment before creating invoice.'}
                            <br/><small style="color: #991b1b;">
                                <strong>Note:</strong> ${userTypeLabel} invoices require advance payment. Current balance: ₹0.00
                            </small>
                            <br/><br/>
                            <a href="add-advance-payment.php" class="btn btn-primary btn-sm">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">add</i>
                                Add Advance Payment
                            </a>
                        </div>
                    `;
                    balanceContainer.style.display = 'block';
                    if (productSection) productSection.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Balance check error:', error);
            if (balanceDisplay && balanceContainer) {
                balanceDisplay.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="material-icons">error</i> Could not check balance. Please refresh and try again.
                    </div>
                `;
                balanceContainer.style.display = 'block';
                if (productSection) productSection.style.display = 'block';
            }
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Advance balance check initialized (Stockist only)');
        
        const customerSelect = document.querySelector('select[name="customer_id"]');
        
        if (customerSelect) {
            customerSelect.addEventListener('change', function() {
                console.log('Customer changed:', this.value);
                checkAdvanceBalance();
            });
            
            $(customerSelect).on('select2:select', function() {
                console.log('Customer selected via Select2');
                checkAdvanceBalance();
            });
        }
        
        <?php if(isset($_REQUEST['InvoiceID'])): ?>
        console.log('Edit mode - checking balance');
        setTimeout(checkAdvanceBalance, 500);
        <?php endif; ?>
        
        if (customerSelect && customerSelect.value) {
            console.log('Pre-filled value - checking balance');
            setTimeout(checkAdvanceBalance, 500);
        }
    });
    </script>
    <?php } ?>
    <!-- END ADVANCE BALANCE CHECK -->
    
</body>
</html>