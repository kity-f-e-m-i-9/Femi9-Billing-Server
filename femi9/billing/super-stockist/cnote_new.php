<?php 
/**
 * Credit Note Creation Page
 * Add return items against an invoice with quantity validation
 * 
 * SECURITY: Input sanitization, XSS prevention
 * FEATURE: Shows original qty, returned qty, available qty for each product
 */

include("checksession.php"); 
date_default_timezone_set("Asia/Kolkata"); 
error_reporting(0);
include("config.php");
include("advance-payment-functions.php"); // Include for return quantity functions

// Get invoice user type
if ($_REQUEST['invuser'] != NULL) {
    $getinvuser = $_REQUEST['invuser'];
    $_SESSION['invuser'] = $getinvuser;
} else {
    $getinvuser = $_SESSION['invuser'];
}

$displaytitle = "Add Stock Return";

// Sanitize and decode invoice ID
$InvoiceID = $_REQUEST['InvoiceID'] ?? '';
$invid_decode = base64_decode($InvoiceID);
$invid_decode = mysqli_real_escape_string($db_conn, $invid_decode);

// Get invoice details based on user type
if ($getinvuser == "customer") {
    $select_invoicedetails = "SELECT * FROM invoice WHERE inv_id='$invid_decode' LIMIT 1";
    $fetch_invoicedetails = mysqli_query($db_conn, $select_invoicedetails);
    $result_invoicedtails = mysqli_fetch_array($fetch_invoicedetails);
    
    // Return stock send users
    $fromusertype = "customer";
    $fromuserid = $result_invoicedtails['customer_id'];
    
    // Return stock received users
    $tousertype = $result_invoicedtails['user_type'];
    $touserid = $result_invoicedtails['user_id'];
} else {
    // ss, stockist, distributor, shop
    $select_invoicedetails = "SELECT * FROM user_invoice WHERE inv_id='$invid_decode' LIMIT 1";
    $fetch_invoicedetails = mysqli_query($db_conn, $select_invoicedetails);
    $result_invoicedtails = mysqli_fetch_array($fetch_invoicedetails);
    
    // Return stock send users
    $fromusertype = $result_invoicedtails['to_user_type'];
    $fromuserid = $result_invoicedtails['to_user_id'];
    
    // Return stock received users
    $tousertype = $result_invoicedtails['from_user_type'];
    $touserid = $result_invoicedtails['from_user_id'];
}

// Get or generate return ID
$get_returnid = isset($_REQUEST['returnid']) ? base64_decode($_REQUEST['returnid']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($displaytitle);?> : <?php echo htmlspecialchars($business_name);?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    
    <style>
        .qty-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
        }
        .qty-original { background-color: #e3f2fd; color: #1976d2; }
        .qty-returned { background-color: #fff3e0; color: #f57c00; }
        .qty-available { background-color: #e8f5e9; color: #388e3c; }
        .qty-none { background-color: #ffebee; color: #d32f2f; }
        .product-qty-info {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
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
                        <br/>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <?php if ($getinvuser == "customer") { ?>
                                            <a href="customer-user-manage-invoice" id="linkbackvl">&#8630;&nbsp;Go Back</a>
                                        <?php } else { ?>
                                            <a href="user-manage-invoice?invuser=<?=htmlspecialchars($getinvuser);?>" id="linkbackvl">&#8630;&nbsp;Go Back</a>
                                        <?php } ?>
                                        
                                        <h1>
                                            <table class="headertble">
                                                <tr>
                                                    <td>
                                                        <?=htmlspecialchars($displaytitle);?>
                                                        <br/>
                                                        <div style="font-size:15px;margin-top:10px;">Invoice Number:-</div>
                                                        <div style="font-size:22px;font-weight:600;color:blue;">
                                                            <?=htmlspecialchars($result_invoicedtails['inv_number']);?>
                                                        </div>
                                                        <div style="font-size:12px;margin-top:5px;">
                                                            <?=htmlspecialchars($getinvuser);?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </h1>

                                        <!-- ALERT MESSAGES -->
                                        <?php if (isset($_REQUEST['invalidqty'])) { 
                                            $available = (int)($_REQUEST['available'] ?? 0);
                                            $requested = (int)($_REQUEST['requested'] ?? 0);
                                            $already_returned = (int)($_REQUEST['already_returned'] ?? 0);
                                        ?>
                                            <div class="alert alert-danger">
                                                <strong>Invalid Quantity!</strong><br/>
                                                You requested to return <strong><?=$requested;?></strong> units, 
                                                but only <strong><?=$available;?></strong> units are available for return.
                                                <?php if ($already_returned > 0) { ?>
                                                    <br/><small>(<?=$already_returned;?> units have already been returned against this invoice)</small>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>

                                        <?php if (isset($_REQUEST['productalreadyexists'])) { ?>
                                            <div class="alert alert-warning">
                                                <strong>Warning!</strong> This product is already added to the current return note.
                                            </div>
                                        <?php } ?>

                                        <?php if (isset($_REQUEST['addedsuccess'])) { ?>
                                            <div class="alert alert-success">
                                                <strong>Success!</strong> Return product added successfully.
                                            </div>
                                        <?php } ?>

                                        <?php if (isset($_REQUEST['DeleteSuccess'])) { ?>
                                            <div class="alert alert-danger">
                                                <strong>Deleted!</strong> Item removed from return.
                                            </div>
                                        <?php } ?>

                                        <div class="card-footer">
                                            <div class="row invoice-summary">
                                                <div class="row">
                                                    <div class="col">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <h5>Invoice Products - Return Availability</h5>
                                                                <table id="datatable1" class="display table table-striped" style="width:100%;">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Product Description</th>
                                                                            <th>Invoice Qty</th>
                                                                            <th>Returned</th>
                                                                            <th>Available</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php 
                                                                        // Get invoice products list
                                                                        if ($getinvuser == "customer") {
                                                                            $select_product_list = "SELECT * FROM invoice_items WHERE inv_id='$invid_decode' ORDER BY id ASC";
                                                                        } else {
                                                                            $select_product_list = "SELECT * FROM user_invoice_items WHERE inv_id='$invid_decode' ORDER BY id ASC";
                                                                        }
                                                                        
                                                                        $fetch_product_list = mysqli_query($db_conn, $select_product_list);
                                                                        
                                                                        while ($result_product_list = mysqli_fetch_array($fetch_product_list)) {
                                                                            $product_id = $result_product_list["pr_id"];
                                                                            
                                                                            // Get product details
                                                                            $select_prdetails = "SELECT * FROM products WHERE id='$product_id' LIMIT 1";
                                                                            $fetch_prdetails = mysqli_query($db_conn, $select_prdetails);
                                                                            $result_prdetails = mysqli_fetch_array($fetch_prdetails);
                                                                            $productname = $result_prdetails['productName'];
                                                                            
                                                                            // Get return availability
                                                                            $availability = getReturnAvailability(
                                                                                $db_conn,
                                                                                $invid_decode,
                                                                                $product_id,
                                                                                $fromusertype,
                                                                                $get_returnid
                                                                            );
                                                                        ?>
                                                                        <tr>
                                                                            <td><?php echo htmlspecialchars($productname); ?></td>
                                                                            <td>
                                                                                <span class="qty-badge qty-original">
                                                                                    <?php echo $availability['original_qty']; ?>
                                                                                </span>
                                                                            </td>
                                                                            <td>
                                                                                <?php if ($availability['returned_qty'] > 0) { ?>
                                                                                    <span class="qty-badge qty-returned">
                                                                                        <?php echo $availability['returned_qty']; ?>
                                                                                    </span>
                                                                                <?php } else { ?>
                                                                                    <span style="color:#999;">-</span>
                                                                                <?php } ?>
                                                                            </td>
                                                                            <td>
                                                                                <?php if ($availability['available_qty'] > 0) { ?>
                                                                                    <span class="qty-badge qty-available">
                                                                                        <?php echo $availability['available_qty']; ?>
                                                                                    </span>
                                                                                <?php } else { ?>
                                                                                    <span class="qty-badge qty-none">0</span>
                                                                                <?php } ?>
                                                                            </td>
                                                                        </tr>
                                                                        <?php } ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- ADD RETURN FORM -->
                                                <form action="cnote_action.php" method="post" enctype="multipart/form-data" id="returnForm" onSubmit="return validateReturnForm();">
                                                    
                                                    <?php 
                                                    // Generate or use existing return ID
                                                    if ($_REQUEST['returnid'] == NULL) {
                                                        function GeraHash($qtd) { 
                                                            $Caracteres = '123456789'; 
                                                            $QuantidadeCaracteres = strlen($Caracteres); 
                                                            $QuantidadeCaracteres--; 
                                                            $Hash = NULL; 
                                                            for ($x = 1; $x <= $qtd; $x++) { 
                                                                $Posicao = rand(0, $QuantidadeCaracteres); 
                                                                $Hash .= substr($Caracteres, $Posicao, 1); 
                                                            } 
                                                            return $Hash; 
                                                        }
                                                        $randum_number = GeraHash(10);
                                                        $temp_date = date("dmy");
                                                        $temp_time = date("gis"); 
                                                        $return_id = $randum_number . "RTN" . $temp_date . $temp_time;
                                                    } else {
                                                        $return_id = $get_returnid;
                                                    }
                                                    ?>

                                                    <input type="hidden" name="returnid" value="<?=htmlspecialchars($return_id);?>">
                                                    <input type="hidden" name="invid" value="<?=htmlspecialchars($invid_decode);?>">
                                                    <input type="hidden" name="from_usertype" value="<?=htmlspecialchars($fromusertype);?>">
                                                    <input type="hidden" name="from_userid" value="<?=htmlspecialchars($fromuserid);?>">
                                                    <input type="hidden" name="to_usertype" value="<?=htmlspecialchars($tousertype);?>">
                                                    <input type="hidden" name="to_userid" value="<?=htmlspecialchars($touserid);?>">

                                                    <label class="form-label">Product Name*</label>
                                                    <select required="" name="prid" id="productSelect" class="form-control" onchange="updateProductInfo()">
                                                        <option value="" hidden="">Select Product</option>
                                                        <?php 
                                                        // Get product list for dropdown with availability
                                                        if ($getinvuser == "customer") {
                                                            $select_product_list12 = "SELECT * FROM invoice_items WHERE inv_id='$invid_decode' ORDER BY id ASC";
                                                        } else {
                                                            $select_product_list12 = "SELECT * FROM user_invoice_items WHERE inv_id='$invid_decode' ORDER BY id ASC";
                                                        }
                                                        
                                                        $fetch_product_list12 = mysqli_query($db_conn, $select_product_list12);
                                                        
                                                        while ($result_product_list12 = mysqli_fetch_array($fetch_product_list12)) {
                                                            $product_idshow = $result_product_list12["pr_id"];
                                                            
                                                            $select_product_list = "SELECT * FROM products WHERE id='$product_idshow' LIMIT 1";
                                                            $fetch_product_list = mysqli_query($db_conn, $select_product_list);
                                                            
                                                            while ($result_product_list = mysqli_fetch_array($fetch_product_list)) {
                                                                // Get availability for this product
                                                                $availability = getReturnAvailability(
                                                                    $db_conn,
                                                                    $invid_decode,
                                                                    $product_idshow,
                                                                    $fromusertype,
                                                                    $get_returnid
                                                                );
                                                                
                                                                $disabled = ($availability['available_qty'] <= 0) ? 'disabled' : '';
                                                                $available_text = ($availability['available_qty'] > 0) 
                                                                    ? " (Available: " . $availability['available_qty'] . ")" 
                                                                    : " (Fully Returned)";
                                                        ?>
                                                            <option value="<?=$result_product_list['id'];?>" 
                                                                    data-available="<?=$availability['available_qty'];?>"
                                                                    data-original="<?=$availability['original_qty'];?>"
                                                                    data-returned="<?=$availability['returned_qty'];?>"
                                                                    <?=$disabled;?>>
                                                                <?=htmlspecialchars($result_product_list['productName']) . $available_text;?>
                                                            </option>
                                                        <?php 
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                    <br/>
                                                    
                                                    <!-- Product Info Display -->
                                                    <div id="productInfo" class="product-qty-info" style="display:none;">
                                                        <strong>Product Quantity Info:</strong><br/>
                                                        Original Qty: <span id="infoOriginal">-</span> | 
                                                        Already Returned: <span id="infoReturned">-</span> | 
                                                        <span style="color:#388e3c; font-weight:600;">
                                                            Available for Return: <span id="infoAvailable">-</span>
                                                        </span>
                                                    </div>
                                                    <br/>

                                                    <label class="form-label">Return Qty*</label>
                                                    <input type="number" required="" min="1" max="99999" name="returnqty" id="returnQty" class="form-control" placeholder="Enter return quantity">
                                                    <small class="form-text text-muted">Maximum available quantity will be validated on submit</small>
                                                    <br/>

                                                    <label class="form-label">Damaged Qty</label>
                                                    <input type="number" min="0" max="99999" name="damaged_qty" class="form-control" value="0" placeholder="Enter damaged quantity">
                                                    <br/>

                                                    <button type="submit" name="add-return" id="submitReturnBtn" class="btn btn-primary" style="width:100%;">
                                                        <i class="material-icons">add</i> Add to Return
                                                    </button>
                                                </form>

                                                <script>
                                                // Prevent double-click and validate form
                                                var formSubmitted = false;
                                                
                                                function validateReturnForm() {
                                                    // Prevent double submission
                                                    if (formSubmitted) {
                                                        alert('Please wait... Your request is being processed.');
                                                        return false;
                                                    }
                                                    
                                                    // Confirm action
                                                    if (!confirm('Are you sure you want to add this return item?')) {
                                                        return false;
                                                    }
                                                    
                                                    // Mark as submitted (but don't disable button yet - it prevents form data)
                                                    formSubmitted = true;
                                                    
                                                    // Disable button AFTER form submission starts (setTimeout)
                                                    setTimeout(function() {
                                                        var btn = document.getElementById('submitReturnBtn');
                                                        btn.disabled = true;
                                                        btn.innerHTML = '<i class="material-icons">hourglass_empty</i> Processing...';
                                                    }, 10);
                                                    
                                                    return true;
                                                }
                                                
                                                function updateProductInfo() {
                                                    var select = document.getElementById('productSelect');
                                                    var selectedOption = select.options[select.selectedIndex];
                                                    var infoDiv = document.getElementById('productInfo');
                                                    var returnQtyInput = document.getElementById('returnQty');
                                                    
                                                    if (selectedOption.value) {
                                                        var original = selectedOption.getAttribute('data-original');
                                                        var returned = selectedOption.getAttribute('data-returned');
                                                        var available = selectedOption.getAttribute('data-available');
                                                        
                                                        document.getElementById('infoOriginal').textContent = original;
                                                        document.getElementById('infoReturned').textContent = returned;
                                                        document.getElementById('infoAvailable').textContent = available;
                                                        
                                                        // Update max attribute
                                                        returnQtyInput.setAttribute('max', available);
                                                        returnQtyInput.setAttribute('placeholder', 'Max: ' + available);
                                                        
                                                        infoDiv.style.display = 'block';
                                                    } else {
                                                        infoDiv.style.display = 'none';
                                                        returnQtyInput.setAttribute('max', '99999');
                                                        returnQtyInput.setAttribute('placeholder', 'Enter return quantity');
                                                    }
                                                }
                                                </script>

                                                <?php if ($_REQUEST['returnid'] != NULL) { ?>
                                                <!-- RETURN ITEMS TABLE -->
                                                <?php 
                                                $select_InvoieDetails234 = "SELECT * FROM user_return_stock WHERE returnid='$get_returnid' LIMIT 1";
                                                $fetch_InvoieDetails234 = mysqli_query($db_conn, $select_InvoieDetails234);
                                                $resultReturnDtails = mysqli_fetch_array($fetch_InvoieDetails234);
                                                ?>

                                                <div style="clear:both;"></div>
                                                <br/>
                                                <div class="row">
                                                    <div class="table-responsive">
                                                        <h5>Return Items Added</h5>
                                                        <table class="table invoice-table table-striped">
                                                            <thead>
                                                                <tr>
                                                                    <th scope="col">#</th>
                                                                    <th scope="col">Product Description</th>
                                                                    <th scope="col">Qty</th>
                                                                    <th scope="col">MRP</th>
                                                                    <th scope="col">Amount</th>
                                                                    <th scope="col">GST</th>
                                                                    <th scope="col">Total</th>
                                                                    <th scope="col">Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $select_INVProductDetails = "SELECT * FROM user_return_stock_items WHERE returnid='$get_returnid' ORDER BY id DESC";
                                                                $fetch_INVProductDetails = mysqli_query($db_conn, $select_INVProductDetails);
                                                                $count_products_return = mysqli_num_rows($fetch_INVProductDetails);
                                                                $rd = 0;
                                                                $TotalAMount123 = 0;
                                                                
                                                                while ($result_INVProductDetails = mysqli_fetch_array($fetch_INVProductDetails)) {
                                                                    // Product details
                                                                    $InV_Product_ID = $result_INVProductDetails['prid'];
                                                                    $select_ProductDetails123 = "SELECT * FROM products WHERE id='$InV_Product_ID' LIMIT 1";
                                                                    $fetch_ProductDetails123 = mysqli_query($db_conn, $select_ProductDetails123);
                                                                    $result_ProductDetails123 = mysqli_fetch_array($fetch_ProductDetails123);
                                                                    
                                                                    $TotalAMount = $result_INVProductDetails['total'];
                                                                    $TotalAMount123 += $TotalAMount;
                                                                    
                                                                    $ItemRowid = base64_encode($result_INVProductDetails['id']);
                                                                ?>
                                                                <tr>
                                                                    <th scope="row"><?php echo ++$rd;?></th>
                                                                    <td><?=htmlspecialchars($result_ProductDetails123['productName']);?></td>
                                                                    <td><?=$result_INVProductDetails['qty'];?></td>
                                                                    <td>&#8377;<?php echo number_format($result_INVProductDetails['amount'], 2, '.', '');?></td>
                                                                    <td align="right"><?php echo number_format($result_INVProductDetails['subtotal'], 2, '.', '');?></td>
                                                                    <td><?=number_format($result_INVProductDetails['gstamount_total'], 2, '.', '');?> (<?=$result_INVProductDetails['gst_percentage'];?>%)</td>
                                                                    <td align="right"><?php echo number_format($TotalAMount, 2, '.', '');?></td>
                                                                    <td>
                                                                        <a href="cnote_delete.php?returnid=<?=$_REQUEST['returnid'];?>&&rowid=<?=$ItemRowid;?>&&InvoiceID=<?=$InvoiceID;?>&&redirurl=cnote_new&&ActionDel" 
                                                                           onclick="return confirm('Are you sure you want to remove this item?');">
                                                                            <span class="badge bg-danger">Remove</span>
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                                <?php } ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <!-- RETURN SUMMARY -->
                                                <div class="card-footer">
                                                    <div class="row invoice-summary">
                                                        <div class="col-lg-4"></div>
                                                        <div class="col-lg-5"></div>

                                                        <script>
                                                        function totalamount() {
                                                            var subtotal = document.getElementById('subtotal').value;
                                                            var discount = document.getElementById('discount').value;
                                                            document.getElementById('outputTotalamount').value = (subtotal * 1) - (discount * 1); 
                                                        }
                                                        </script>

                                                        <div class="col-lg-3">
                                                            <div class="invoice-info">
                                                                <?php if ($count_products_return > 0) { ?>
                                                                <form action="cnote_finish.php" method="post" enctype="multipart/form-data" onSubmit="return confirm('Please confirm the return submission!');">
                                                                    <input type="hidden" name="returnid" value="<?=$_REQUEST['returnid'];?>"/>
                                                                    <input type="hidden" name="SubTotal" value="<?=$TotalAMount123;?>"/>

                                                                    <p class="bold">Subtotal 
                                                                        <span>
                                                                            <input type="number" min="0" value="<?=$TotalAMount123;?>" id="subtotal" disabled>
                                                                        </span>
                                                                    </p>
                                                                    <br/>

                                                                    <p class="bold">Discount 
                                                                        <span>
                                                                            <?php if ($resultReturnDtails['discount'] == 0) { ?>
                                                                                <input type="number" onkeyup="totalamount()" id="discount" value="0" min="0" name="discount" required="">
                                                                            <?php } else { ?>
                                                                                <input type="number" value="<?=$resultReturnDtails['discount'];?>" onkeyup="totalamount()" id="discount" min="0" name="discount" required="">
                                                                            <?php } ?>
                                                                        </span>
                                                                    </p>
                                                                    <br/>

                                                                    <p class="bold">Total 
                                                                        <span>
                                                                            <?php if ($resultReturnDtails['discount'] == 0) { ?>
                                                                                <input type="number" min="0" value="<?=$TotalAMount123;?>" id="outputTotalamount" disabled>
                                                                            <?php } else { 
                                                                                $TotalAmount_display = $TotalAMount123 - $resultReturnDtails['discount'];
                                                                            ?>
                                                                                <input type="number" min="0" value="<?=$TotalAmount_display;?>" id="outputTotalamount" disabled>
                                                                            <?php } ?>
                                                                        </span>
                                                                    </p>
                                                                    
                                                                    <div style="clear:both;"></div>
                                                                    <div class="invoice-info-actions">
                                                                        <button class="btn btn-primary" type="submit" name="invoice-submit" style="width:100%;">
                                                                            Submit Return
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                                <?php } ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php } ?>

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

    <!-- Javascripts -->
    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>