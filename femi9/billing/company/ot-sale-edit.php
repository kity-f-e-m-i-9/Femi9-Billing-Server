<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); 
include("config.php"); 
date_default_timezone_set("Asia/Kolkata");

$tempid=base64_decode($_REQUEST['tempid']);

$select_Invoice="select * from ot_sales_invoice where tempid=?";
$fetch_Invoice=db_q($db_conn,$select_Invoice,[$tempid]);
$result_Invoice=mysqli_fetch_array($fetch_Invoice);

$select_Records="select * from ot_sales where tempid=?";
$fetch_Records=db_q($db_conn,$select_Records,[$tempid]);
$result_Records=mysqli_fetch_array($fetch_Records);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <!-- Title -->
    <title>Update OT Sales : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

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
                                     <h1>
									<table class="headertble">
									<tr>
									<td>Update OT Sales</td>
									<td><a href="ot-sale-view" title="Manage OT Sales">&#9776;</a></td>
									</tr>
									</table>
									</h1>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <!----<div class="card-header">
                                        <h5 class="card-title">Basic Input</h5>
                                    </div>--->
                                    <div class="card-body">
									
	 <?php include("validate-scripts.php");?>	 
<form action="ot-sale-action" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please make a confirm!');">

<input type="hidden" name="tempid" value="<?=htmlspecialchars((string)($tempid), ENT_QUOTES, 'UTF-8', false)?>">

                                        <div class="example-container">
                                        <div class="example-content">
								
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
								
<label class="form-label">Date*</label>
<input type="date" required="" id="bookingDate" name="date" value="<?=htmlspecialchars((string)($result_Records['date']), ENT_QUOTES, 'ISO-8859-1', false);?>" class="form-control">
<br/>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr("#bookingDate", {
        dateFormat: "Y-m-d",
            maxDate: "today" // Disallow future dates
        });
</script>
          
<label class="form-label">Category*</label>
<select required="" name="catname" class="form-control">
<option value="<?=htmlspecialchars((string)($result_Records['cat']), ENT_QUOTES, 'ISO-8859-1', false);?>" hidden=""><?=htmlspecialchars((string)($result_Records['cat']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
<?php $select_product_list="select * from ot_cat";
	$fetch_product_list=mysqli_query($db_conn,$select_product_list);
	while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											?>
<option value="<?=htmlspecialchars((string)($result_product_list['cat']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?=htmlspecialchars((string)($result_product_list['cat']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
										<?php }?>
</select>
<br/>			


<?php 
$get_stateID=$result_Records['state_id'];
if($get_stateID!=NULL && $get_stateID!=0)
{
$select_stateList12="select * from `state` where id=?";
$fetch_staeList12=db_q($db_conn,$select_stateList12,[$get_stateID]);
$result_stateList12=mysqli_fetch_array($fetch_staeList12);
$STName=$result_stateList12['st_name'];
}else{
	$STName="";
}
?>

<label for="exampleInputEmail1" class="form-label">State Name*</label>
                               <select required="" name="state_id" class="form-control">
							   <option value="<?=$get_stateID;?>" hidden=""><?=$STName;?></option>
							   <?php $select_stateList="select * from `state` order by `st_name` asc";
							   $fetch_staeList=mysqli_query($db_conn,$select_stateList);
							   while($result_stateList=mysqli_fetch_array($fetch_staeList))
							   {?>
						   <option value="<?php echo htmlspecialchars((string)($result_stateList['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?php echo htmlspecialchars((string)($result_stateList['st_name']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
							   <?php }?>
							   </select>
							   <br/>	
							   
							   <input type="hidden" name="admin_state_id" value="<?=$Config_Admin_State;?>">

<label class="form-label">Invoice Number*</label>
            <input type="text" required="" name="inv_number" value="<?=htmlspecialchars((string)($result_Invoice['inv_number']), ENT_QUOTES, 'ISO-8859-1', false);?>" onkeypress="restrictSpecialChars(event)" class="form-control">
			<br/>	


 <label class="form-label">Customer Name*</label>
            <input type="text" required="" name="customer_name" onkeypress="restrictSpecialChars(event)" class="form-control" value="<?=htmlspecialchars((string)($result_Records['customer_name']), ENT_QUOTES, 'ISO-8859-1', false);?>">
			<br/>
			
			<label class="form-label">Customer Mobile</label>
           <input type="text" name="customer_mobile" onChange="showMobileNumber(this.value)" onkeypress="restrictnumber(event)" pattern="[1-9]{1}[0-9]{9}" class="form-control" maxlength="10" value="<?=htmlspecialchars((string)($result_Records['customer_mobile']), ENT_QUOTES, 'ISO-8859-1', false);?>">
			<br/>
			
			<label class="form-label">Billing Address*</label>
            <textarea name="customer_address" class="form-control" onkeypress="restrictSpecialChars(event)" required="required"><?=htmlspecialchars((string)($result_Records['customer_address']), ENT_QUOTES, 'ISO-8859-1', false);?></textarea>
			<br/>
			
			<label class="form-label">Shipping Address*</label>
            <textarea name="shipping_address" class="form-control" onkeypress="restrictSpecialChars(event)" required="required"><?=htmlspecialchars((string)($result_Records['shipping_address']), ENT_QUOTES, 'ISO-8859-1', false);?></textarea>
			<br/>
			
			<label class="form-label">GST Number</label>
            <input type="text" name="gst_number" value="<?=htmlspecialchars((string)($result_Records['gst_number']), ENT_QUOTES, 'ISO-8859-1', false);?>" maxlength="15" onkeypress="restrictGSTIN(event)" class="form-control">	
			<br/>
			
			<label class="form-label">Order Number</label>
            <input type="text" name="order_number" value="<?=htmlspecialchars((string)($result_Records['order_number']), ENT_QUOTES, 'ISO-8859-1', false);?>" onkeypress="restrictSpecialChars(event)" class="form-control">
			<br/>
			
			<?php 
			if($result_Records['order_date']!="1991-01-01"){ $ORderDate=$result_Records['order_date'];}
			else{$ORderDate="";}?>
			<label class="form-label">Order Date</label>
            <input type="date" name="order_date" value="<?=$ORderDate;?>" class="form-control">
			<br/>
			
			<?php 
			if($result_Records['ship_date']!="1991-01-01"){ $ShipDate=$result_Records['ship_date'];}
			else{$ShipDate="";}?>
			<label class="form-label">Ship Date</label>
            <input type="date" name="ship_date" value="<?=$ShipDate;?>" class="form-control">
			<br/>
			
			<label class="form-label">Courier Charges(Rs.) *</label>
            <input type="number" min="0" name="courier_charges" value="<?=htmlspecialchars((string)($result_Invoice['courier_charges']), ENT_QUOTES, 'ISO-8859-1', false);?>" required="" class="form-control">
			<br/>

			<h5>Product Details</h5>
			<div class="table-responsive">
			<table class="table table-bordered" id="editProductsTable">
			    <thead>
			        <tr><th>Product</th><th>Qty</th><th>Rate(Rs.)</th><th>Discount(Rs.)</th></tr>
			    </thead>
			    <tbody>
			    <?php
			    // Editing qty here adjusts `stock` (closing_qty/sales_qty) by only the
			    // delta on Update -- see ot-sale-action.php's updateRecord handler.
			    $select_edit_items = "SELECT os.id, os.prid, os.qty, os.price, os.discount, p.productName
			                           FROM ot_sales os
			                           JOIN products p ON p.id = os.prid
			                           WHERE os.tempid=? ORDER BY os.id ASC";
			    $fetch_edit_items = db_q($db_conn,$select_edit_items,[$tempid]);
			    while ($itemRow = mysqli_fetch_assoc($fetch_edit_items)) {
			    ?>
			        <tr>
			            <td>
			                <?=htmlspecialchars($itemRow['productName']);?>
			                <input type="hidden" name="item_id[]" value="<?=htmlspecialchars((string)($itemRow['id']), ENT_QUOTES, 'ISO-8859-1', false);?>">
			            </td>
			            <td><input type="number" min="1" step="1" name="item_qty[]" value="<?=htmlspecialchars((string)($itemRow['qty']), ENT_QUOTES, 'ISO-8859-1', false);?>" class="form-control" required></td>
			            <td><input type="number" min="0" step="any" name="item_price[]" value="<?=htmlspecialchars((string)($itemRow['price']), ENT_QUOTES, 'ISO-8859-1', false);?>" class="form-control" required></td>
			            <td><input type="number" min="0" step="any" name="item_discount[]" value="<?=htmlspecialchars((string)($itemRow['discount']), ENT_QUOTES, 'ISO-8859-1', false);?>" class="form-control"></td>
			        </tr>
			    <?php } ?>
			    </tbody>
			</table>
			</div>
			<br/>

			<span id="opstock">
<button type="submit" name="updateRecord" class="btn btn-primary"><i class="material-icons">update</i>Update</button>
</span>
												
                                            </div>
                                        </div>
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