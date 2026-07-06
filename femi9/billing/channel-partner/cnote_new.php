<?php
include("checksession.php");
include("config.php");
include("return-validation-functions.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$getinvuser = $_REQUEST['invuser'] ?? $_SESSION['invuser'] ?? '';
if (!empty($_REQUEST['invuser'])) { $_SESSION['invuser'] = $getinvuser; }

$displaytitle = "Add Stock Return";
$InvoiceID    = $_REQUEST['InvoiceID'] ?? '';
$invid_decode = mysqli_real_escape_string($db_conn, base64_decode($InvoiceID));
$get_returnid = isset($_REQUEST['returnid']) ? base64_decode($_REQUEST['returnid']) : '';

if ($getinvuser === 'customer') {
    $inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM invoice WHERE inv_id='$invid_decode' LIMIT 1"));
    $fromusertype = 'customer';
    $fromuserid   = $inv['customer_id'] ?? '';
    $tousertype   = $inv['user_type']   ?? 'territory_partner';
    $touserid     = $inv['user_id']     ?? $Login_user_IDvl;
} else {
    $inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_invoice WHERE inv_id='$invid_decode' LIMIT 1"));
    $fromusertype = $inv['to_user_type']   ?? '';
    $fromuserid   = $inv['to_user_id']     ?? '';
    $tousertype   = $inv['from_user_type'] ?? 'territory_partner';
    $touserid     = $inv['from_user_id']   ?? $Login_user_IDvl;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $displaytitle; ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        .qty-badge { display:inline-block;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600;margin-right:8px; }
        .qty-original  { background:#e3f2fd;color:#1976d2; }
        .qty-returned  { background:#fff3e0;color:#f57c00; }
        .qty-available { background:#e8f5e9;color:#388e3c; }
        .qty-none      { background:#ffebee;color:#d32f2f; }
        .product-qty-info { font-size:13px;color:#666;margin-top:5px; }
    </style>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <br/>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">

<?php if ($getinvuser === 'customer'): ?>
    <a href="customer-manage-invoice.php" id="linkbackvl">&#8630;&nbsp;Go Back</a>
<?php else: ?>
    <a href="shop-manage-invoice.php?invuser=<?php echo htmlspecialchars($getinvuser); ?>" id="linkbackvl">&#8630;&nbsp;Go Back</a>
<?php endif; ?>

<h1><table class="headertble"><tr><td>
    <?php echo $displaytitle; ?><br/>
    <div style="font-size:15px;margin-top:10px;">Invoice Number:-</div>
    <div style="font-size:22px;font-weight:600;color:blue;"><?php echo htmlspecialchars($inv['inv_number'] ?? ''); ?></div>
    <div style="font-size:12px;margin-top:5px;"><?php echo htmlspecialchars($getinvuser); ?></div>
</td></tr></table></h1>

<?php if (isset($_REQUEST['invalidqty'])): ?>
<div class="alert alert-danger">
    <strong>Invalid Quantity!</strong> You requested <strong><?php echo (int)($_REQUEST['requested'] ?? 0); ?></strong>
    but only <strong><?php echo (int)($_REQUEST['available'] ?? 0); ?></strong> available for return.
    <?php if ((int)($_REQUEST['already_returned'] ?? 0) > 0): ?>
        <br/><small>(<?php echo (int)$_REQUEST['already_returned']; ?> already returned)</small>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php if (isset($_REQUEST['productalreadyexists'])): ?>
<div class="alert alert-warning"><strong>Warning!</strong> This product is already in the current return note.</div>
<?php endif; ?>
<?php if (isset($_REQUEST['addedsuccess'])): ?>
<div class="alert alert-success"><strong>Success!</strong> Return product added.</div>
<?php endif; ?>
<?php if (isset($_REQUEST['DeleteSuccess'])): ?>
<div class="alert alert-danger"><strong>Deleted!</strong> Item removed.</div>
<?php endif; ?>

<div class="card-footer"><div class="row">
    <!-- Availability table -->
    <div class="col">
        <div class="card"><div class="card-body">
            <h5>Invoice Products - Return Availability</h5>
            <table class="table table-striped" style="width:100%;">
                <thead><tr><th>Product</th><th>Invoice Qty</th><th>Returned</th><th>Available</th></tr></thead>
                <tbody>
                <?php
                $items_q = ($getinvuser === 'customer')
                    ? "SELECT * FROM invoice_items WHERE inv_id='$invid_decode' ORDER BY id ASC"
                    : "SELECT * FROM user_invoice_items WHERE inv_id='$invid_decode' ORDER BY id ASC";
                $items_r = mysqli_query($db_conn, $items_q);
                while ($il = mysqli_fetch_array($items_r)) {
                    $pid = $il['pr_id'];
                    $pr  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT productName FROM products WHERE id='$pid' LIMIT 1"));
                    $av  = getReturnAvailability($db_conn, $invid_decode, $pid, $fromusertype, $get_returnid);
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($pr['productName']); ?></td>
                    <td><span class="qty-badge qty-original"><?php echo $av['original_qty']; ?></span></td>
                    <td><?php echo $av['returned_qty'] > 0 ? "<span class='qty-badge qty-returned'>{$av['returned_qty']}</span>" : '<span style="color:#999;">-</span>'; ?></td>
                    <td><?php echo $av['available_qty'] > 0 ? "<span class='qty-badge qty-available'>{$av['available_qty']}</span>" : "<span class='qty-badge qty-none'>0</span>"; ?></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>

<!-- Add return form -->
<form action="cnote_action.php" method="post" enctype="multipart/form-data" id="returnForm" onsubmit="return validateReturnForm();">
    <?php
    if (empty($get_returnid)) {
        $rn = '';
        for ($x = 0; $x < 10; $x++) { $rn .= rand(1,9); }
        $return_id = $rn . 'RTN' . date('dmygis');
    } else {
        $return_id = $get_returnid;
    }
    ?>
    <input type="hidden" name="returnid"       value="<?php echo htmlspecialchars($return_id); ?>">
    <input type="hidden" name="invid"          value="<?php echo htmlspecialchars($invid_decode); ?>">
    <input type="hidden" name="from_usertype"  value="<?php echo htmlspecialchars($fromusertype); ?>">
    <input type="hidden" name="from_userid"    value="<?php echo htmlspecialchars($fromuserid); ?>">
    <input type="hidden" name="to_usertype"    value="<?php echo htmlspecialchars($tousertype); ?>">
    <input type="hidden" name="to_userid"      value="<?php echo htmlspecialchars($touserid); ?>">

    <label class="form-label">Product Name*</label>
    <select required name="prid" id="productSelect" class="form-control" onchange="updateProductInfo()">
        <option value="" hidden>Select Product</option>
        <?php
        $drop_q = ($getinvuser === 'customer')
            ? "SELECT * FROM invoice_items WHERE inv_id='$invid_decode' ORDER BY id ASC"
            : "SELECT * FROM user_invoice_items WHERE inv_id='$invid_decode' ORDER BY id ASC";
        $drop_r = mysqli_query($db_conn, $drop_q);
        while ($dl = mysqli_fetch_array($drop_r)) {
            $dpid = $dl['pr_id'];
            $dpr  = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM products WHERE id='$dpid' LIMIT 1"));
            $dav  = getReturnAvailability($db_conn, $invid_decode, $dpid, $fromusertype, $get_returnid);
            $dis  = $dav['available_qty'] <= 0 ? 'disabled' : '';
            $lbl  = $dav['available_qty'] > 0 ? " (Available: {$dav['available_qty']})" : " (Fully Returned)";
        ?>
        <option value="<?php echo $dpr['id']; ?>"
                data-available="<?php echo $dav['available_qty']; ?>"
                data-original="<?php echo $dav['original_qty']; ?>"
                data-returned="<?php echo $dav['returned_qty']; ?>"
                <?php echo $dis; ?>>
            <?php echo htmlspecialchars($dpr['productName']) . $lbl; ?>
        </option>
        <?php } ?>
    </select><br/>

    <div id="productInfo" class="product-qty-info" style="display:none;">
        <strong>Qty Info:</strong> Original: <span id="infoOriginal">-</span> |
        Returned: <span id="infoReturned">-</span> |
        <span style="color:#388e3c;font-weight:600;">Available: <span id="infoAvailable">-</span></span>
    </div><br/>

    <label class="form-label">Return Qty*</label>
    <input type="number" required min="1" max="99999" name="returnqty" id="returnQty" class="form-control" placeholder="Enter qty"><br/>

    <label class="form-label">Damaged Qty</label>
    <input type="number" min="0" name="damaged_qty" class="form-control" value="0"><br/>

    <button type="submit" name="add-return" id="submitReturnBtn" class="btn btn-primary" style="width:100%;">
        <i class="material-icons">add</i> Add to Return
    </button>
</form>

<script>
var formSubmitted = false;
function validateReturnForm() {
    if (formSubmitted) { alert('Please wait...'); return false; }
    if (!confirm('Add this return item?')) return false;
    formSubmitted = true;
    setTimeout(function() {
        var b = document.getElementById('submitReturnBtn');
        b.disabled = true; b.innerHTML = 'Processing...';
    }, 10);
    return true;
}
function updateProductInfo() {
    var sel = document.getElementById('productSelect');
    var opt = sel.options[sel.selectedIndex];
    var info = document.getElementById('productInfo');
    var qty  = document.getElementById('returnQty');
    if (opt.value) {
        document.getElementById('infoOriginal').textContent  = opt.getAttribute('data-original');
        document.getElementById('infoReturned').textContent  = opt.getAttribute('data-returned');
        document.getElementById('infoAvailable').textContent = opt.getAttribute('data-available');
        qty.setAttribute('max', opt.getAttribute('data-available'));
        qty.setAttribute('placeholder', 'Max: ' + opt.getAttribute('data-available'));
        info.style.display = 'block';
    } else {
        info.style.display = 'none';
        qty.setAttribute('max','99999');
        qty.setAttribute('placeholder','Enter qty');
    }
}
</script>

<?php if (!empty($get_returnid)): ?>
<?php
$ret_master = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_return_stock WHERE returnid='$get_returnid' LIMIT 1"));
$ret_items  = mysqli_query($db_conn, "SELECT * FROM user_return_stock_items WHERE returnid='$get_returnid' ORDER BY id DESC");
$count_ri   = mysqli_num_rows($ret_items);
$TotalAMount123 = 0; $rd = 0;
?>
<div style="clear:both;"></div><br/>
<div class="row"><div class="table-responsive">
<h5>Return Items Added</h5>
<table class="table invoice-table table-striped">
    <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>MRP</th><th>Amount</th><th>GST</th><th>Total</th><th></th></tr></thead>
    <tbody>
    <?php while ($ri = mysqli_fetch_array($ret_items)):
        $pr2 = mysqli_fetch_array(mysqli_query($db_conn, "SELECT productName FROM products WHERE id='{$ri['prid']}' LIMIT 1"));
        $TotalAMount123 += $ri['total'];
        $ItemRowid = base64_encode($ri['id']);
    ?>
    <tr>
        <th><?php echo ++$rd; ?></th>
        <td><?php echo $pr2['productName']; ?></td>
        <td><?php echo $ri['qty']; ?></td>
        <td>&#8377;<?php echo inr_format($ri['amount'], 2); ?></td>
        <td><?php echo inr_format($ri['subtotal'], 2); ?></td>
        <td><?php echo inr_format($ri['gstamount_total'], 2); ?> (<?php echo $ri['gst_percentage']; ?>%)</td>
        <td><?php echo inr_format($ri['total'], 2); ?></td>
        <td>
            <a href="cnote_delete.php?returnid=<?php echo $_REQUEST['returnid']; ?>&rowid=<?php echo $ItemRowid; ?>&InvoiceID=<?php echo $InvoiceID; ?>&redirurl=cnote_new"
               onclick="return confirm('Remove this item?');">
                <span class="badge bg-danger">Remove</span>
            </a>
        </td>
    </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div></div>

<?php if ($count_ri > 0): ?>
<div class="card-footer"><div class="row invoice-summary">
<div class="col-lg-5"></div>
<script>function totalamount2(){var s=document.getElementById('subtotal2').value;var d=document.getElementById('discount2').value;document.getElementById('outputTotal2').value=(s*1)-(d*1);}</script>
<div class="col-lg-3"><div class="invoice-info">
    <form action="cnote_finish.php" method="post" enctype="multipart/form-data" onsubmit="return confirm('Complete this return?');">
        <input type="hidden" name="returnid" value="<?php echo $_REQUEST['returnid']; ?>">
        <input type="hidden" name="SubTotal" value="<?php echo $TotalAMount123; ?>">
        <p class="bold">Subtotal <span><input type="number" value="<?php echo $TotalAMount123; ?>" id="subtotal2" disabled></span></p><br/>
        <p class="bold">Discount <span>
            <input type="number" onkeyup="totalamount2()" id="discount2"
                   value="<?php echo $ret_master['discount'] ?: 0; ?>" min="0" name="discount" required>
        </span></p><br/>
        <p class="bold">Total <span>
            <?php $td = $TotalAMount123 - ($ret_master['discount'] ?: 0); ?>
            <input type="number" value="<?php echo $td; ?>" id="outputTotal2" disabled>
        </span></p>
        <div style="clear:both;"></div>
        <div class="invoice-info-actions">
            <button class="btn btn-primary" type="submit" name="invoice-submit" style="width:100%;">
                Complete Return
            </button>
        </div>
    </form>
</div></div>
</div></div>
<?php endif; ?>
<?php endif; ?>

</div><!-- card-footer -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
