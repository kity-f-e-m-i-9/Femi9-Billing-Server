<?php
include("checksession.php");
date_default_timezone_set("Asia/Kolkata");
error_reporting(0);
include("config.php");

$get_action = $_REQUEST['action'] ?? '';
$_SESSION['ACTIONEDIT'] = $get_action;

$displaytitle     = "Invoice - Customer";
$lablenamedisplay = "Customer Name";
$tablename        = "customers";
$invidprefix      = "CMPCUST";

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
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <?php include("validate-scripts.php"); ?>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar"><?php include("logo.php"); ?><?php include("femi_menu.php"); ?></div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">

<?php if (isset($_REQUEST['addesuccess'])): ?><div class="alert alert-success">Customer added successfully.</div><?php endif; ?>
<?php if (isset($_REQUEST['alreadyexists'])): ?><div class="alert alert-danger">Customer details already exists!</div><?php endif; ?>
<?php if (isset($_REQUEST['AddedSuccess'])): ?><div class="alert alert-success">Product added successfully.</div><?php endif; ?>
<?php if (isset($_REQUEST['ItemAlreadyExists'])): ?><div class="alert alert-danger">Invalid product, already exists.</div><?php endif; ?>
<?php if (isset($_REQUEST['InvalidStock'])): ?><div class="alert alert-danger">Invalid qty, out of stock.</div><?php endif; ?>
<?php if (isset($_REQUEST['DeleteSuccess'])): ?><div class="alert alert-danger">Product deleted successfully.</div><?php endif; ?>
<?php if (isset($_REQUEST['invoicealready'])): ?><div class="alert alert-danger">Invoice Number already exists!</div><?php endif; ?>
<?php if (isset($_REQUEST['InvoiceUpdatedSuccess'])): ?><div class="alert alert-success">Invoice Number updated successfully.</div><?php endif; ?>

<h1><table class="headertble"><tr>
    <td><?php if ($get_action == "edit") echo "Update > "; echo $displaytitle; ?></td>
    <td><a href="customer-manage-invoice.php" title="Manage Invoice">&#9776;</a></td>
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
    xmlhttp.open("GET", "loadPrice.php?q=" + str + "&invuser=customer", true);
    xmlhttp.send();
}
function totalkm() {
    var a = document.getElementById('amount').value;
    var q = document.getElementById('qty').value;
    document.getElementById('output').value = (a * q);
}
function discamount() {
    var output = document.getElementById('output').value;
    var dp = document.getElementById('discountpercentae').value;
    document.getElementById('discountamount').value = (output * dp / 100).toFixed(2);
}
function totalamount() {
    var roundtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    var cucharge   = parseFloat(document.getElementById('cucharge').value) || 0;
    document.getElementById('outputTotalamount').value = (roundtotal + cucharge).toFixed(2);
    receiptamount();
}
</script>

<style>
#add { background:green; border:1px solid green; }
#add:hover, #add:focus { background:#DDD; color:#000; border:1px solid #000; }
.item { margin-bottom:6px; }
.item select { margin-right:10px; float:left; padding:6px; width:400px; border-radius:4px; border:1px solid #000; }
.item input[type=number] { margin-right:10px; float:left; width:100px; padding:5px; border-radius:4px; border:1px solid #000; }
select:focus, input[type=number]:focus { background:#fffa8f; }
@media(max-width:768px) { .item select, .item input[type=number] { width:100%; margin-bottom:10px; } }
</style>

<?php
if (isset($_REQUEST['InvoiceID'])) {
    // ---- EDIT / ADD-ITEM MODE ----
    $Invoice_ID_encode = $_REQUEST['InvoiceID'];
    $Invoice_ID        = base64_decode($_REQUEST['InvoiceID']);

    $res_inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM invoice WHERE inv_id='$Invoice_ID' LIMIT 1"));

    $totalamount         = (float)($res_inv['total'] ?? 0);
    $Total_Receipt_amount = (float)(mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(received) FROM receipt WHERE inv_id='$Invoice_ID'"))[0]);
    $amount_received_fully = ($Total_Receipt_amount > 0 && $totalamount == $Total_Receipt_amount) ? "1" : "0";

    $CustomerID = $res_inv['customer_id'] ?? 0;
    if ($CustomerID != 0) {
        $res_cust          = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM $tablename WHERE id='$CustomerID' LIMIT 1"));
        $inv_customer_name   = $res_cust['name']   ?? '';
        $inv_customer_mobile = $res_cust['mobile']  ?? '';
    } else {
        $inv_customer_name   = "Walking Customer";
        $inv_customer_mobile = "";
    }
?>
<form action="customer-invoice-action2.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="inv_id" value="<?php echo $Invoice_ID; ?>">
<div class="example-container"><div class="example-content">

<label class="form-label"><?php echo $lablenamedisplay; ?>*</label>
<?php
$cnt_items = (int)(mysqli_fetch_array(mysqli_query($db_conn, "SELECT COUNT(*) AS n FROM invoice_items WHERE inv_id='$Invoice_ID'"))['n']);
?>
<select name="customer_id" class="form-control">
<option value="<?php echo $CustomerID; ?>" hidden><?php echo htmlspecialchars($inv_customer_name); ?>, <?php echo htmlspecialchars($inv_customer_mobile); ?></option>
<?php if ($cnt_items == 0) {
    $res_custs = mysqli_query($db_conn, "SELECT * FROM $tablename WHERE user_id='$Login_user_IDvl' ORDER BY name ASC");
    while ($rc = mysqli_fetch_array($res_custs)) {
?>
<option value="<?php echo $rc['id']; ?>"><?php echo ucwords($rc['name']); ?>, <?php echo $rc['mobile']; ?></option>
<?php } } ?>
</select>

<label class="form-label">Invoice Date*</label>
<input type="date" readonly name="date" value="<?php echo $res_inv['date']; ?>" required class="form-control">
<br/>

<?php if ($amount_received_fully == 0): ?>
<div class="item">
<select required name="pr_id" style="width:100%;" class="prinput" autofocus onchange="showPrice(this.value)">
<option value="" hidden>Select Product</option>
<?php
$res_prods = mysqli_query($db_conn, "SELECT p.id, p.productName FROM products p INNER JOIN territory_partner_stock tps ON tps.product_id = p.id AND tps.territory_partner_id = '$Login_user_IDvl' AND tps.closing_qty > 0 ORDER BY p.id ASC");
while ($rp = mysqli_fetch_array($res_prods)) {
?>
<option value="<?php echo $rp['id']; ?>"><?php echo $rp['productName']; ?></option>
<?php } ?>
</select>
<br/><br/>
<input type="number" min="0" name="qty" id="qty" onkeyup="totalkm()" required placeholder="Qty" class="numberinput">
<span id="txtHintPrice"><input type="number" min="0" step="any" name="amount" id="amount" onkeyup="totalkm()" required placeholder="Price"></span>
<input type="number" min="0" step="any" name="total" id="output" class="numberinput" required placeholder="Total" readonly>
<input type="number" min="0" step="any" id="discountpercentae" name="discount_percentage" onkeyup="discamount()" required placeholder="Disc(%)" class="numberinput">
<input type="number" min="0" id="discountamount" name="discount_amount" step="any" required placeholder="Disc(Rs.)" class="numberinput">
<button type="submit" name="addInvoice2" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add</button>
</div>
<?php endif; ?>

</div></div>
</form>
<hr/>

<?php
$TotalAMount123 = 0;
$res_items = mysqli_query($db_conn, "SELECT ii.*, p.productName, p.hsn FROM invoice_items ii LEFT JOIN products p ON ii.pr_id=p.id WHERE ii.inv_id='$Invoice_ID' ORDER BY ii.id DESC");
$CountProducts = mysqli_num_rows($res_items);
$rd = 0;
?>
<div class="row"><div class="table-responsive">
    <table class="table">
        <thead><tr>
            <th>#</th><th>Product</th><th>HSN</th><th>Qty</th><th>MRP</th><th>Discount</th><th>Amount</th><th>GST</th><th>Total</th>
            <?php if ($amount_received_fully == 0): ?><th></th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php while ($ri = mysqli_fetch_array($res_items)) {
            $TotalAMount123 += (float)$ri['total'];
            $ItemRowid = base64_encode($ri['id']);
        ?>
        <tr>
            <td><?php echo ++$rd; ?></td>
            <td><?php echo htmlspecialchars($ri['productName']); ?></td>
            <td><?php echo htmlspecialchars($ri['hsn']); ?></td>
            <td><?php echo $ri['qty']; ?></td>
            <td><?php echo $ri['amount']; ?></td>
            <td><?php echo $ri['discount_amount']; ?> (<?php echo $ri['discount_percentage']; ?>%)</td>
            <td>&#8377;<?php echo inr_format((float)$ri['subtotal'], 2); ?></td>
            <td><?php echo $ri['gstamount_total']; ?> (<?php echo $ri['gst_percentage']; ?>%)</td>
            <td><?php echo inr_format((float)$ri['total'], 2); ?></td>
            <?php if ($amount_received_fully == 0): ?>
            <td>
            <?php
            $cnt_ret2 = mysqli_num_rows(mysqli_query($db_conn, "SELECT * FROM user_return_stock_items WHERE invnumber='$Invoice_ID' AND prid='" . $ri['pr_id'] . "'"));
            if ($cnt_ret2 == 0) {
                echo "<a href='customer-del-inv-product.php?inv_id=$Invoice_ID_encode&&rowid=$ItemRowid&&userid=$CustomerID&&actionremove' onclick=\"return confirm('Delete?');\"><span class='badge bg-danger'>Remove</span></a>";
            } else {
                echo "<span id='cnlable'>-&nbsp;CN&nbsp;-</span>";
            }
            ?>
            </td>
            <?php endif; ?>
        </tr>
        <?php } ?>
        </tbody>
    </table>
</div></div>

<?php
if ($CountProducts == 0 && (float)($res_inv['sub_total'] ?? 0) > 0) {
    mysqli_query($db_conn, "UPDATE invoice SET sub_total='0',discount='0',total='0' WHERE inv_id='$Invoice_ID'");
}
$unround_value  = $TotalAMount123 + (float)($res_inv['courier_charges'] ?? 0);
$roundvalue     = round($unround_value);
$roundoff       = $roundvalue - $unround_value;
$already_received = (float)(mysqli_fetch_array(mysqli_query($db_conn, "SELECT COALESCE(SUM(received),0) FROM receipt WHERE inv_id='$Invoice_ID'"))[0]);
$balance_due    = max(0, (float)$roundvalue - $already_received);
$res_receipt    = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM receipt WHERE inv_id='$Invoice_ID' ORDER BY id ASC LIMIT 1"));
?>

<div class="card-footer"><div class="row invoice-summary">
<div class="col-lg-4"><div class="invoice-info">
    <p>Invoice Number:
    <?php if ($get_action == "edit"): ?>
    <a href="#" id="linkcaption" data-bs-toggle="modal" data-bs-target="#invNumModal">
    <span><?php echo htmlspecialchars($res_inv['inv_number'] ?? ''); ?></span></a>
    <?php else: ?>
    <span><?php echo htmlspecialchars($res_inv['inv_number'] ?? ''); ?></span>
    <?php endif; ?>
    <!-- Invoice number edit modal -->
    <div class="modal fade" id="invNumModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Invoice Number<br/><?php echo htmlspecialchars($res_inv['inv_number'] ?? ''); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" onsubmit="return confirm('Confirm update?');" action="update_invoice_action.php">
            <input type="hidden" name="invuser" value="customer">
            <input type="hidden" name="InvoiceID" value="<?php echo $_REQUEST['InvoiceID'] ?? ''; ?>">
            <input type="hidden" name="action" value="<?php echo $_REQUEST['action'] ?? ''; ?>">
            <input type="hidden" name="gid" value="<?php echo $_REQUEST['gid'] ?? ''; ?>">
            <input type="hidden" name="redirurl" value="customer-invoice-add">
            <input type="hidden" name="tblenme" value="2">
            <div class="example-content" style="padding:20px;">
                <div class="form-floating mb-3">
                    <input type="text" name="invnumber" placeholder="Invoice Number" class="form-control" required onkeypress="restrictSpecialChars(event)">
                    <label>Invoice Number</label>
                </div>
                <button type="submit" name="updateInvoiceNum" class="btn btn-primary"><i class="material-icons">update</i>Update</button>
            </div>
            </form>
        </div></div>
    </div>
    </p>
    <p>Invoice Date: <span><?php echo date("d/M/Y", strtotime($res_inv['date'] ?? '')); ?></span></p>
</div></div>
<div class="col-lg-5"></div>
<div class="col-lg-3"><div class="invoice-info">
<?php if ($amount_received_fully == 0): ?>
<form action="customer-invoice-submit.php" method="post" enctype="multipart/form-data" onsubmit="return confirm('Submit invoice?');">
<input type="hidden" name="invoice_id" value="<?php echo $Invoice_ID; ?>">
<input type="hidden" name="sub_total" value="<?php echo $TotalAMount123; ?>">
<input type="hidden" name="discount" value="0">
<input type="hidden" name="roundoff" value="<?php echo number_format($roundoff, 2, '.', ''); ?>">

<p><b>Subtotal</b><input type="number" class="form-control" value="<?php echo $TotalAMount123; ?>" id="subtotal" disabled></p>
<p><b>Round off</b><input type="number" step="any" value="<?php echo number_format($roundoff, 2, '.', ''); ?>" disabled class="form-control"></p>
<p><b>Courier Charges</b><input type="number" value="<?php echo $res_inv['courier_charges'] ?? 0; ?>" name="courier_charges" min="0" required onkeyup="totalamount()" id="cucharge" class="form-control"></p>
<p><b>Total</b><input type="number" class="form-control" step="any" value="<?php echo number_format((float)$roundvalue, 2, '.', ''); ?>" id="outputTotalamount" disabled></p>

<?php if ($already_received > 0): ?>
<p><b>Invoice Total</b><input type="number" step="any" class="form-control" style="width:100%;" value="<?php echo number_format((float)$roundvalue, 2, '.', ''); ?>" disabled></p>
<p><b>Already Received</b><input type="number" step="any" class="form-control" style="width:100%;background:#d1fae5;" value="<?php echo number_format($already_received, 2, '.', ''); ?>" disabled></p>
<p><b>Balance Due</b><input type="number" step="any" class="form-control" style="width:100%;background:#fee2e2;font-weight:bold;" value="<?php echo number_format($balance_due, 2, '.', ''); ?>" disabled></p>
<?php endif; ?>

<script>
function receiptamount() {
    var totalbillamount = parseFloat(document.getElementById('outputTotalamount').value) || 0;
    var alreadyreceived = <?php echo number_format($already_received, 2, '.', ''); ?>;
    var balancedue = totalbillamount - alreadyreceived;
    if (balancedue < 0) balancedue = 0;
    var receivedamount = parseFloat(document.getElementById('receivedamount').value) || 0;
    document.getElementById('receivableamount').value = (balancedue - receivedamount).toFixed(2);
    document.getElementById('receivedamount').setAttribute('max', balancedue.toFixed(2));
    document.getElementById('receivedamount').placeholder = 'Max: ' + balancedue.toFixed(2);
}
</script>

<p><b>Received Amount</b>
<input type="number" min="0" required step="any" max="<?php echo inr_format($balance_due, 2); ?>" id="receivedamount" class="form-control" style="width:100%;" onkeyup="receiptamount()" name="receivedamount" placeholder="Max: <?php echo inr_format($balance_due, 2); ?>">
</p>
<p><b>Receivable Amount</b>
<input type="number" min="0" id="receivableamount" class="form-control" readonly required style="width:100%;">
</p>

<div class="bold">Received Method
<select name="receipt_method" required class="form-control">
<?php if (empty($res_receipt['receipt_method'])): ?>
<option value="" hidden>Select</option>
<?php else: ?>
<option value="<?php echo $res_receipt['receipt_method']; ?>" hidden><?php echo $res_receipt['receipt_method']; ?></option>
<?php endif; ?>
<option>--None--</option>
<option>Cash</option>
<option>UPI</option>
<option>Bank Transfer</option>
<option>Deposit</option>
</select>
</div>
<div class="bold">Remarks
<textarea name="receipt_remarks" required class="form-control"><?php echo htmlspecialchars($res_receipt['receipt_remarks'] ?? ''); ?></textarea>
</div>

<div style="clear:both;"></div>
<div class="invoice-info-actions">
<?php if ($CountProducts > 0): ?>
<button class="btn btn-primary" type="submit" name="invoice-submit" style="width:100%;">Submit Invoice</button>
<?php endif; ?>
</div>
</form>
<?php else: ?>
<span class="badge badge-style-bordered badge-success">Not editable! Fully Paid Invoice</span>
<?php endif; ?>
</div></div>
</div></div>

<?php
} else {
    // ---- NEW INVOICE MODE ----
    function GeraHashCustTP($qtd) {
        $chars = '123456789';
        $len   = strlen($chars) - 1;
        $hash  = '';
        for ($x = 1; $x <= $qtd; $x++) { $hash .= substr($chars, rand(0, $len), 1); }
        return $hash;
    }
    $inv_randum_number = GeraHashCustTP(10);
    $randum_number     = GeraHashCustTP(3);
    $inv_id            = $inv_randum_number . $invidprefix . date("dmy") . date("gis");
?>

<!-- Add Customer Modal -->
<div class="modal fade" id="composeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
            <form action="customer-action.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="actionpage" value="invoiceadd">
            <div class="example-container"><div class="example-content">
            <label class="form-label">Customer Name*</label>
            <input type="text" required name="name" class="form-control" onkeypress="restrictSpecialChars(event)">
            <br/>
            <style>.form-group{display:flex;align-items:center;gap:5px;}.form-group .country-code{flex:0 0 20%;}.form-group .mobile-number{flex:1;}</style>
            <div class="form-group">
                <div class="country-code">
                    <label class="form-label">Country Code*</label>
                    <select name="country_code" required class="form-control">
                    <?php $fc = mysqli_query($db_conn, "SELECT * FROM country ORDER BY id ASC"); while ($rc2 = mysqli_fetch_array($fc)) { ?>
                    <option value="<?php echo $rc2['c_code']; ?>"><?php echo $rc2['c_name']; ?> (<?php echo $rc2['c_code']; ?>)</option>
                    <?php } ?>
                    </select>
                </div>
                <div class="mobile-number">
                    <label class="form-label">Mobile Number*</label>
                    <input type="text" required name="mobile" onkeypress="restrictnumber(event)" pattern="[1-9]{1}[0-9]{9}" class="form-control" maxlength="10">
                </div>
            </div>
            <br/>
            <label class="form-label">Email ID</label>
            <input type="email" name="email" class="form-control" placeholder="optional">
            <br/>
            <label class="form-label">GSTIN</label>
            <input type="text" name="gstin" class="form-control" placeholder="optional" maxlength="15">
            <br/>
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" placeholder="optional"></textarea>
            <br/>
            <input type="hidden" name="marketing_date" value="<?php echo date("Y-m-d"); ?>">
            <button type="submit" name="add-customer" class="btn btn-primary"><i class="material-icons">add</i>Add</button>
            </div></div>
            </form>
            </div>
        </div>
    </div>
</div>

<!-- New Invoice Form -->
<form action="customer-invoice-action.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="randum_number" value="<?php echo $randum_number; ?>">
<input type="hidden" name="inv_id" value="<?php echo $inv_id; ?>">

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
    xmlhttp.open("GET", "load_InvoiceNumber_customer.php?q=" + str, true);
    xmlhttp.send();
}
</script>

<label class="form-label">Invoice Number*</label>
<input type="text" onkeyup="showInvoiceDuplicate(this.value)" name="inv_number" autofocus required onkeypress="restrictSpecialChars(event)" class="form-control">
<br/>
<span id="txtHintInvoice"></span>

<table style="width:100%;margin-bottom:5px;">
<tr>
<td align="left"><label class="form-label"><?php echo $lablenamedisplay; ?>*</label></td>
<td align="right"><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal"><i class="material-icons">add</i>New</button></td>
</tr>
</table>

<select required name="customer_id" class="form-control" style="margin-bottom:5px;">
<option value="" hidden>Select Customer</option>
<?php
$res_custs = mysqli_query($db_conn, "SELECT * FROM $tablename WHERE user_id='$Login_user_IDvl' ORDER BY name ASC");
while ($rc = mysqli_fetch_array($res_custs)) {
?>
<option value="<?php echo $rc['id']; ?>"><?php echo ucwords($rc['name']); ?>, <?php echo $rc['mobile']; ?></option>
<?php } ?>
</select>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<label class="form-label">Invoice Date*</label>
<input type="date" id="bookingDate" name="date" value="<?php echo date("Y-m-d"); ?>" required class="form-control" style="margin-bottom:10px;">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>flatpickr("#bookingDate", { dateFormat: "Y-m-d", maxDate: "today" });</script>

<div class="item">
<select required name="pr_id" style="width:100%;" onchange="showPrice(this.value)" class="prinput">
<option value="" hidden>Select Product</option>
<?php
$res_prods = mysqli_query($db_conn, "SELECT p.id, p.productName FROM products p INNER JOIN territory_partner_stock tps ON tps.product_id = p.id AND tps.territory_partner_id = '$Login_user_IDvl' AND tps.closing_qty > 0 ORDER BY p.id ASC");
while ($rp = mysqli_fetch_array($res_prods)) {
?>
<option value="<?php echo $rp['id']; ?>"><?php echo $rp['productName']; ?></option>
<?php } ?>
</select>
<br/><br/>
<input type="number" min="0" name="qty" id="qty" onkeyup="totalkm()" required placeholder="Qty" class="numberinput">
<span id="txtHintPrice"><input type="number" min="0" name="amount" id="amount" onkeyup="totalkm()" required placeholder="Price"></span>
<input type="number" min="0" name="total" id="output" readonly required placeholder="Total" class="numberinput">
<input type="number" min="0" step="any" id="discountpercentae" name="discount_percentage" onkeyup="discamount()" required placeholder="Disc(%)" class="numberinput">
<input type="number" min="0" id="discountamount" name="discount_amount" step="any" required placeholder="Disc(Rs.)" class="numberinput">
<button type="submit" name="addInvoice" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add</button>
</div>

</div></div>
</form>

<?php } ?>

<?php } // end else (has stock) ?>

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
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
