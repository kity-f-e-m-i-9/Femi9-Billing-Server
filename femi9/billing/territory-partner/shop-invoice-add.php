<?php
include("checksession.php");
date_default_timezone_set("Asia/Kolkata");
error_reporting(0);
include("config.php");

$getinvuser    = "shop";
$get_action    = $_REQUEST['action'] ?? '';
$_SESSION['ACTIONEDIT'] = $get_action;
$displaytitle      = "Invoice - Shop";
$lablenamedisplay  = "Shop Name";
$tablename         = "shop";
$invidprefix       = "CMPSHP";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $displaytitle; ?> : <?php echo $business_name; ?></title>
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
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <?php include("validate-scripts.php"); ?>
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
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">

<?php if (isset($_REQUEST['AddedSuccess'])): ?><div class="alert alert-success">one product added success.</div><?php endif; ?>
<?php if (isset($_REQUEST['ItemAlreadyExists'])): ?><div class="alert alert-danger">invalid product, already exists.</div><?php endif; ?>
<?php if (isset($_REQUEST['InvalidStock'])): ?><div class="alert alert-danger">invalid qty, out of stock.</div><?php endif; ?>
<?php if (isset($_REQUEST['DeleteSuccess'])): ?><div class="alert alert-danger">Deleted ! one product deleted success.</div><?php endif; ?>
<?php if (isset($_REQUEST['invoicealready'])): ?><div class="alert alert-danger">Invoice Number already exists!</div><?php endif; ?>
<?php if (isset($_REQUEST['InvoiceUpdatedSuccess'])): ?><div class="alert alert-success">Invoice Number Updated Success!.</div><?php endif; ?>

<h1><table class="headertble"><tr>
    <td><?php if ($get_action == "edit") { echo "Update >"; } ?><?php echo $displaytitle; ?></td>
    <td><a href="shop-manage-invoice.php" title="Manage">&#9776;</a></td>
</tr></table></h1>

<?php { ?>

<script type="text/javascript">
function showPrice(str) {
    if (str == "") { return; }
    if (window.XMLHttpRequest) { xmlhttp = new XMLHttpRequest(); }
    else { xmlhttp = new ActiveXObject("Microsoft.XMLHTTP"); }
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            document.getElementById("txtHintPrice").innerHTML = xmlhttp.responseText;
        }
    };
    var invuser = "<?php echo $getinvuser; ?>";
    xmlhttp.open("GET", "loadPrice.php?q=" + str + '&invuser=' + invuser, true);
    xmlhttp.send();
}
function totalkm() {
    var textValue1 = document.getElementById('amount').value;
    var textValue2 = document.getElementById('qty').value;
    document.getElementById('output').value = (textValue1 * textValue2);
}
</script>

<style type="text/css">
.curstockvl { width:100%; border-collapse:collapse; }
.curstockvl th { font-weight:bold; padding:5px; font-size:16px; color:blue; }
.curstockvl td { font-weight:bold; padding:5px; }
#add { background:green; border:1px solid green; }
#add:hover, #add:focus { background:#DDD; color:#000; border:1px solid #000; }
.item { margin-bottom:6px; }
.item select { margin-right:10px; float:left; padding:6px; width:400px; border-radius:4px; border:1px solid #000; }
.item input[type=number] { margin-right:10px; float:left; width:100px; padding:5px; border-radius:4px; border:1px solid #000; }
select:focus, input[type=number]:focus { background:#fffa8f; }
@media(max-width:768px) {
    .item select { width:100%; margin-bottom:10px; }
    .item input[type=number] { width:100%; margin-bottom:10px; }
}
</style>

<?php if (isset($_REQUEST['InvoiceID'])) {

    $Invoice_ID_encode = $_REQUEST['InvoiceID'];
    $Invoice_ID = base64_decode($_REQUEST['InvoiceID']);

    $result_InvoieDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_invoice WHERE inv_id='$Invoice_ID'"));

    // Receipt check
    $totalamount = $result_InvoieDetails["total"];
    $Total_Receipt_amount = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(received) FROM receipt WHERE inv_id='$Invoice_ID'"))[0];
    $amount_received_fully = ($Total_Receipt_amount > 0 && $totalamount == $Total_Receipt_amount) ? "1" : "0";

    $CustomerID = $result_InvoieDetails['to_user_id'];
    $result_CUSTDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM $tablename WHERE temp_id='$CustomerID'"));
?>

<form action="shop-invoice-action2.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="inv_id" value="<?php echo $Invoice_ID; ?>">
<input type="hidden" name="invuser" value="<?php echo $getinvuser; ?>">
<div class="example-container"><div class="example-content">

<label class="form-label"><?php echo $lablenamedisplay; ?>*</label>
<?php
$totalcountitems = mysqli_fetch_array(mysqli_query($db_conn, "SELECT COUNT(*) AS n FROM user_invoice_items WHERE inv_id='$Invoice_ID'"))['n'];
?>
<select name="customer_id" class="form-control">
<option value="<?php echo $CustomerID; ?>" hidden><?php echo $result_CUSTDetails['name']; ?>, <?php echo $result_CUSTDetails['mobile_number']; ?></option>
<?php if ($totalcountitems == 0) {
    $res_shops = mysqli_query($db_conn, "SELECT * FROM $tablename WHERE onboard_userID='$Login_user_IDvl' AND onboard_userTYPE='$Login_user_TYPEvl' ORDER BY name ASC");
    while ($r = mysqli_fetch_array($res_shops)) { ?>
<option value="<?php echo $r['temp_id']; ?>"><?php echo ucwords($r['name']); ?>, <?php echo $r['mobile_number']; ?></option>
<?php } } ?>
</select>

<label class="form-label">Invoice Date*</label>
<input type="date" readonly name="date" value="<?php echo $result_InvoieDetails['date']; ?>" required class="form-control">
<br/>

<?php if ($amount_received_fully == 0) { ?>
<div class="item">
<select required name="pr_id" class="prinput" style="width:100%;" autofocus onchange="showPrice(this.value)">
<option value="" hidden>Select Product</option>
<?php
$res_prods = mysqli_query($db_conn, "SELECT p.id, p.productName FROM products p INNER JOIN territory_partner_stock tps ON tps.product_id = p.id AND tps.territory_partner_id = '$Login_user_IDvl' AND tps.closing_qty > 0 ORDER BY p.id ASC");
while ($rp = mysqli_fetch_array($res_prods)) { ?>
<option value="<?php echo $rp['id']; ?>"><?php echo $rp['productName']; ?></option>
<?php } ?>
</select>
<br/><br/>
<input type="number" min="0" name="qty" id="qty" onkeyup="totalkm()" required placeholder="Qty" class="numberinput">
<span id="txtHintPrice"><input type="number" min="0" name="amount" step="any" id="amount" onkeyup="totalkm()" required placeholder="Price"></span>
<input type="number" min="0" step="any" name="total" id="output" class="numberinput" required placeholder="Total">
<script>
function discamount() {
    var output = document.getElementById('output').value;
    var discountpercentae = document.getElementById('discountpercentae').value;
    document.getElementById('discountamount').value = (output * discountpercentae / 100).toFixed(2);
}
</script>
<input type="number" min="0" step="any" id="discountpercentae" name="discount_percentage" onkeyup="discamount()" required placeholder="Disc(%)" class="numberinput">
<input type="number" min="0" id="discountamount" name="discount_amount" step="any" required placeholder="Disc(Rs.)" class="numberinput">
<button type="submit" name="addInvoice2" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add</button>
</div>
<?php } ?>

</div></div></form>

<!-- Items table -->
<div class="row"><div class="table-responsive">
<table class="table">
    <thead><tr>
        <th>#</th><th>Product Description</th><th>Qty</th><th>MRP</th>
        <th>Discount</th><th>Amount</th><th>GST</th><th>Total</th>
        <?php if ($amount_received_fully == 0) { ?><th></th><?php } ?>
    </tr></thead>
    <tbody>
<?php
$TotalAMount123 = 0; $CountProducts = 0; $rd = 0;
$res_items = mysqli_query($db_conn, "SELECT * FROM user_invoice_items WHERE inv_id='$Invoice_ID' ORDER BY id DESC");
$CountProducts = mysqli_num_rows($res_items);
while ($ri = mysqli_fetch_array($res_items)) {
    $InV_Product_ID = $ri['pr_id'];
    $pr = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM products WHERE id='$InV_Product_ID'"));
    $TotalAMount = $ri['total'];
    $TotalAMount123 += $TotalAMount;
    $ItemRowid = base64_encode($ri['id']);
?>
    <tr>
        <th><?php echo ++$rd; ?></th>
        <td><?php echo $pr['productName']; ?></td>
        <td><?php echo $ri['qty']; ?></td>
        <td>&#8377;<?php echo number_format($ri['amount'], 2, '.', ''); ?></td>
        <td><?php echo $ri['discount_amount']; ?>(<?php echo $ri['discount_percentage']; ?>%)</td>
        <td>&#8377;<?php echo number_format($ri['subtotal'], 2, '.', ''); ?></td>
        <td><?php echo $ri['gstamount_total']; ?>(<?php echo $ri['gst_percentage']; ?>%)</td>
        <td align="right"><?php echo number_format($TotalAMount, 2, '.', ''); ?></td>
        <?php if ($amount_received_fully == 0) { ?>
        <td>
        <?php
        $cnt_ret = mysqli_num_rows(mysqli_query($db_conn, "SELECT * FROM user_return_stock_items WHERE invnumber='$Invoice_ID' AND prid='$InV_Product_ID'"));
        if ($cnt_ret == 0) { ?>
        <a href="shop-del-inv-product.php?invid=<?php echo $Invoice_ID_encode; ?>&&rowid=<?php echo $ItemRowid; ?>&&invuser=<?php echo $getinvuser; ?>&&actionremove" onclick="return confirm('You want to delete confirm?');"><span class="badge bg-danger">Remove</span></a>
        <?php } else { echo "<span id='cnlable'>-&nbsp;CN&nbsp;-</span>"; } ?>
        </td>
        <?php } ?>
    </tr>
<?php } ?>
    </tbody>
</table>
</div></div>

<!-- Submit form -->
<div><div>
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
</script>

<form action="shop-invoice-submit.php" method="post" enctype="multipart/form-data" onsubmit="return validateForm()">

<div><div class="invoice-info">
<hr/>
<table style="width:100%;">
<tr>
    <td>Inv Num</td>
    <td style="color:#bd2b0e;">:&nbsp;
    <?php if ($get_action == "edit") { ?>
    <a href="#" id="linkcaption" data-bs-toggle="modal" data-bs-target="#exampleModalLive"><span><?php echo $result_InvoieDetails['inv_number']; ?></span></a>
    <?php } else { ?>
    <span><?php echo $result_InvoieDetails['inv_number']; ?></span>
    <?php } ?>
    </td>
</tr>
<tr>
    <td>Date</td>
    <td style="color:#bd2b0e;">:&nbsp;<b><?php echo date("d/M/Y", strtotime($result_InvoieDetails['date'])); ?></b></td>
</tr>
</table>
<hr/>
</div></div>

<div class="col-lg-5"></div>

<?php
$unround_value = $TotalAMount123 + $result_InvoieDetails['courier_charges'];
$roundvalue    = round($unround_value);
$roundoff      = $roundvalue - $unround_value;

// Zero out invoice if items removed
if ($CountProducts == 0 && $result_InvoieDetails['sub_total'] > 0) {
    mysqli_query($db_conn, "UPDATE user_invoice SET sub_total='0',discount='0',total='0' WHERE inv_id='$Invoice_ID'");
}
?>

<script>
function totalamount() {
    var roundtotal = <?php echo round($TotalAMount123); ?>;
    var cucharge   = parseFloat(document.getElementById('cucharge').value) || 0;
    var newtotal   = roundtotal + cucharge;
    document.getElementById('outputTotalamount').value = newtotal.toFixed(2);
    receiptamount();
}
</script>

<div class="col-lg-3"><div class="invoice-info">

<input type="hidden" name="invoice_id" value="<?php echo $Invoice_ID; ?>">
<input type="hidden" name="SubTotal" value="<?php echo $TotalAMount123; ?>">

<p><b>Subtotal</b>
<input type="number" step="any" class="form-control" min="0" value="<?php echo $TotalAMount123; ?>" style="width:100%;" id="subtotal" disabled>
</p>

<input type="hidden" name="discount" value="0">

<input type="hidden" name="roundoff" value="<?php echo number_format($roundoff, 2, '.', ''); ?>">
<p><b>Round off</b>
<input type="number" min="0" class="form-control" step="any" value="<?php echo number_format($roundoff, 2, '.', ''); ?>" disabled>
</p>

<p><b>Courier Charges</b>
<input type="number" value="<?php echo $result_InvoieDetails['courier_charges']; ?>" name="courier_charges" min="0" required onkeyup="totalamount()" id="cucharge" class="form-control">
</p>

<p><b>Total</b>
<input type="number" min="0" class="form-control" step="any" value="<?php echo number_format($roundvalue, 2, '.', ''); ?>" id="outputTotalamount" disabled>
</p>

<?php
$result_ReceiptDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM receipt WHERE inv_id='$Invoice_ID' ORDER BY id ASC"));
$already_received = (float)(mysqli_fetch_array(mysqli_query($db_conn, "SELECT COALESCE(SUM(received),0) AS total_received FROM receipt WHERE inv_id='$Invoice_ID'"))['total_received']);
$balance_due = max(0, (float)$roundvalue - $already_received);
?>

<script>
function receiptamount() {
    var totalbillamount = parseFloat(document.getElementById('outputTotalamount').value) || 0;
    var alreadyreceived = <?php echo number_format($already_received, 2, '.', ''); ?>;
    var balancedue      = totalbillamount - alreadyreceived;
    if (balancedue < 0) balancedue = 0;
    var receivedamount  = parseFloat(document.getElementById('receivedamount').value) || 0;
    var receivable      = balancedue - receivedamount;
    document.getElementById('receivableamount').value = receivable.toFixed(2);
    document.getElementById('receivedamount').setAttribute('max', balancedue.toFixed(2));
    document.getElementById('receivedamount').placeholder = 'Max: ' + balancedue.toFixed(2);
}
</script>

<?php if ($already_received > 0) { ?>
<p><b>Invoice Total</b>
<input type="number" step="any" class="form-control" style="width:100%;" value="<?php echo number_format($roundvalue, 2, '.', ''); ?>" disabled>
</p>
<p><b>Already Received</b>
<input type="number" step="any" class="form-control" style="width:100%;background:#d1fae5;" value="<?php echo number_format($already_received, 2, '.', ''); ?>" disabled>
</p>
<p><b>Balance Due</b>
<input type="number" step="any" class="form-control" style="width:100%;background:#fee2e2;font-weight:bold;" value="<?php echo number_format($balance_due, 2, '.', ''); ?>" disabled>
</p>
<?php } ?>

<p><b>Received Amount</b>
<input type="number" min="0" required step="any" max="<?php echo number_format($balance_due, 2, '.', ''); ?>" id="receivedamount" class="form-control" style="width:100%;" onkeyup="receiptamount()" name="receivedamount" placeholder="Max: <?php echo number_format($balance_due, 2, '.', ''); ?>">
</p>
<p><b>Receivable Amount</b>
<input type="number" min="0" id="receivableamount" class="form-control" readonly required style="width:100%;">
<span id="error" style="color:red;display:none;font-size:12px;">Value must be non-negative.</span>
</p>

<div class="bold">Received Method<span>
<select name="receipt_method" required class="form-control">
<?php if ($result_ReceiptDetails['receipt_method'] == NULL) { ?>
<option value="" hidden>Select</option>
<?php } else { ?>
<option value="<?php echo $result_ReceiptDetails['receipt_method']; ?>" hidden><?php echo $result_ReceiptDetails['receipt_method']; ?></option>
<?php } ?>
<option>--None--</option>
<option>Cash</option>
<option>UPI</option>
<option>Bank Transfer</option>
<option>Deposit</option>
</select>
</span></div>

<?php $show_remarks = $result_ReceiptDetails['receipt_remarks'] ?? ''; ?>
<div class="bold">Remarks<span>
<textarea name="receipt_remarks" required class="form-control"><?php echo $show_remarks; ?></textarea>
</span></div>

<div style="clear:both;"></div>
<?php if ($amount_received_fully == 0) { ?>
<div class="invoice-info-actions">
<?php if ($CountProducts > 0) { ?>
<button class="btn btn-primary" type="submit" name="invoice-submit" style="width:100%;">Submit Invoice</button>
<?php } ?>
</div>
<?php } else { ?>
<span class='badge badge-style-bordered badge-success'>Not editable ! Fully Paid Invoices</span>
<?php } ?>

</form>
</div></div>
</div></div>

<?php } else { // New invoice form ?>

<form action="shop-invoice-action.php" method="post" enctype="multipart/form-data">
<?php
function GeraHashInv($qtd) {
    $chars = '123456789';
    $len = strlen($chars) - 1;
    $hash = '';
    for ($x = 1; $x <= $qtd; $x++) { $hash .= substr($chars, rand(0, $len), 1); }
    return $hash;
}
$inv_randum_number = GeraHashInv(10);
$randum_number     = GeraHashInv(3);
$inv_id = $inv_randum_number . $invidprefix . date("dmygis");
?>
<input type="hidden" name="randum_number" value="<?php echo $randum_number; ?>">
<input type="hidden" name="inv_id" value="<?php echo $inv_id; ?>">
<input type="hidden" name="invuser" value="<?php echo $getinvuser; ?>">

<div class="example-container"><div class="example-content">

<script type="text/javascript">
function showInvoiceDuplicate(str) {
    if (str == "") { return; }
    if (window.XMLHttpRequest) { xmlhttp = new XMLHttpRequest(); }
    else { xmlhttp = new ActiveXObject("Microsoft.XMLHTTP"); }
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            document.getElementById("txtHintInvoice").innerHTML = xmlhttp.responseText;
        }
    };
    xmlhttp.open("GET", "loadInvoiceNumberUSER.php?q=" + str, true);
    xmlhttp.send();
}
</script>

<label class="form-label">Invoice Number *</label>
<input type="text" onkeyup="showInvoiceDuplicate(this.value)" name="inv_number" autofocus required onkeypress="restrictSpecialChars(event)" class="form-control">
<br/>
<span id="txtHintInvoice"></span>

<label class="form-label"><?php echo $lablenamedisplay; ?>*</label>
<select required name="customer_id" class="form-control" autofocus>
<option value="" hidden>Select</option>
<?php
$res_shops = mysqli_query($db_conn, "SELECT * FROM $tablename WHERE onboard_userTYPE='$Login_user_TYPEvl' AND onboard_userID='$Login_user_IDvl' ORDER BY name ASC");
while ($r = mysqli_fetch_array($res_shops)) { ?>
<option value="<?php echo $r['temp_id']; ?>"><?php echo ucwords($r['name']); ?>, <?php echo $r['mobile_number']; ?>, <?php echo ucwords($r['address']); ?></option>
<?php } ?>
</select>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<label class="form-label">Invoice Date*</label>
<input id="bookingDate" style="margin-bottom:10px;" type="date" name="date" value="<?php echo date("Y-m-d"); ?>" required class="form-control">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>flatpickr("#bookingDate", { dateFormat: "Y-m-d", maxDate: "today" });</script>

<div class="item">
<select required name="pr_id" style="width:100%;" onchange="showPrice(this.value)" class="prinput">
<option value="" hidden>Select Product</option>
<?php
$res_prods = mysqli_query($db_conn, "SELECT p.id, p.productName FROM products p INNER JOIN territory_partner_stock tps ON tps.product_id = p.id AND tps.territory_partner_id = '$Login_user_IDvl' AND tps.closing_qty > 0 ORDER BY p.id ASC");
while ($rp = mysqli_fetch_array($res_prods)) { ?>
<option value="<?php echo $rp['id']; ?>"><?php echo $rp['productName']; ?></option>
<?php } ?>
</select>
<br/><br/>
<input type="number" min="0" name="qty" id="qty" onkeyup="totalkm()" required placeholder="Qty" class="numberinput">
<span id="txtHintPrice"><input type="number" min="0" name="amount" step="any" id="amount" onkeyup="totalkm()" required placeholder="Price"></span>
<input type="number" min="0" step="any" name="total" id="output" required placeholder="Total" class="numberinput">
<script>
function discamount() {
    var output = document.getElementById('output').value;
    var discountpercentae = document.getElementById('discountpercentae').value;
    document.getElementById('discountamount').value = (output * discountpercentae / 100).toFixed(2);
}
</script>
<input type="number" min="0" step="any" id="discountpercentae" name="discount_percentage" onkeyup="discamount()" required placeholder="Disc(%)" class="numberinput">
<input type="number" min="0" id="discountamount" name="discount_amount" step="any" required placeholder="Disc(Rs.)" class="numberinput">
<span id="txtHintstock">
<button type="submit" name="addInvoice" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add</button>
</span>
</div>

</div></div></form>

<?php } // end new/existing ?>

<?php } // end stock check ?>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invoice number edit modal (shown in edit mode) -->
<div class="modal fade" id="exampleModalLive" tabindex="-1" aria-labelledby="exampleModalLiveLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Invoice Number<br/><?php echo $result_InvoieDetails['inv_number'] ?? ''; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" onsubmit="return confirm('Please make a confirm!');" enctype="multipart/form-data" action="update_invoice_action.php">
                <input type="hidden" name="invuser"   value="<?php echo $_REQUEST['invuser']   ?? ''; ?>">
                <input type="hidden" name="InvoiceID" value="<?php echo $_REQUEST['InvoiceID'] ?? ''; ?>">
                <input type="hidden" name="action"    value="<?php echo $_REQUEST['action']    ?? ''; ?>">
                <input type="hidden" name="gid"       value="<?php echo $_REQUEST['gid']       ?? ''; ?>">
                <input type="hidden" name="redirurl"  value="shop-invoice-add">
                <input type="hidden" name="tblenme"   value="1">
                <div class="example-content" style="padding:20px;">
                    <div class="form-floating mb-3">
                        <input type="text" name="invnumber" placeholder="Invoice Number" class="form-control" required onkeypress="restrictSpecialChars(event)">
                        <label>Invoice Number</label>
                    </div>
                    <button type="submit" name="updateInvoiceNum" class="btn btn-primary"><i class="material-icons">update</i>Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
