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

<div class="page-title-modern">
    <h1><i class="material-icons">receipt_long</i><?php if ($get_action == "edit") echo "Update "; echo $displaytitle; ?></h1>
    <a href="customer-manage-invoice.php" class="menu-link" title="Manage Invoice"><i class="material-icons">list</i></a>
</div>

<?php { ?>

<script type="text/javascript">
function showPrice(str) {
    if (str == "") { return; }
    if (window.XMLHttpRequest) { xmlhttp = new XMLHttpRequest(); }
    else { xmlhttp = new ActiveXObject("Microsoft.XMLHTTP"); }
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            document.getElementById("txtHintPrice").innerHTML = xmlhttp.responseText;
            var mrpVal = document.getElementById("amount").getAttribute("data-mrp");
            var mrpField = document.getElementById("mrpDisplayField");
            if (mrpField) { mrpField.value = mrpVal ? ('MRP: ₹' + mrpVal) : ''; }
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
body { font-family: 'Poppins', sans-serif; }

/* ── Alerts ── */
.alert { border-radius: 10px; border: none; padding: 15px 20px; margin-bottom: 20px; }
.alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
.alert-danger  { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }

/* ── Page Title ── */
.page-title-modern {
    background: white; border: 2px solid #e5e7eb; border-radius: 12px;
    padding: 18px 24px; margin-bottom: 22px;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.page-title-modern h1 { color: #1e293b; font-size: 21px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 10px; }
.page-title-modern h1 i { color: #2563eb; font-size: 24px; }
.page-title-modern .menu-link {
    background: #2563eb; color: white; width: 38px; height: 38px;
    border-radius: 8px; display: flex; align-items: center; justify-content: center;
    text-decoration: none; font-size: 18px; transition: all 0.2s ease;
}
.page-title-modern .menu-link:hover { background: #1d4ed8; color: white; }

/* ── Form Sections ── */
.form-section {
    background: white; border: 2px solid #e5e7eb; border-radius: 14px;
    padding: 24px 28px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.section-header {
    color: #475569; font-size: 12.5px; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.5px; margin-bottom: 18px; display: flex; align-items: center;
    gap: 8px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9;
}
.section-header i { color: #2563eb; font-size: 18px; }
.form-label { font-weight: 600; color: #374151; margin-bottom: 8px; font-size: 13.5px; display: block; }
.form-control, .form-select, select.prinput, input.numberinput,
.product-add-grid input, .product-add-grid select {
    border: 2px solid #e5e7eb !important; border-radius: 9px !important; padding: 10px 14px !important;
    font-size: 14px !important; font-family: 'Poppins', sans-serif; transition: all 0.2s ease;
    box-sizing: border-box !important;
}
.form-control:focus, select.prinput:focus, input.numberinput:focus,
.product-add-grid input:focus, .product-add-grid select:focus {
    border-color: #2563eb !important; box-shadow: 0 0 0 3px rgba(37,99,235,0.1) !important; background: #fff !important;
    outline: none !important;
}

/* ── Product Add Section ── */
.product-add-section {
    background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px;
    padding: 18px; margin-top: 16px;
}
.product-add-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px; align-items: end;
}
.product-add-grid .wide { grid-column: span 2; }
@media (max-width: 992px) { .product-add-grid .wide { grid-column: 1 / -1; } }
.input-group-modern { display: flex; flex-direction: column; }
.input-group-modern label {
    font-size: 11.5px; color: #64748b; font-weight: 600; margin-bottom: 5px;
    text-transform: uppercase; letter-spacing: 0.4px;
}
/* Element-type selectors (not just .form-control) — loadPrice.php's AJAX
   response swaps #amount's markup for a bare, class-less <input>, so the
   styling hook has to key off being inside .product-add-grid, not a class
   that only exists on the initial page load. */
.product-add-grid .input-group-modern, .product-add-grid span {
    margin: 0 !important; float: none !important; width: 100% !important; display: flex; flex-direction: column;
}
.product-add-grid input, .product-add-grid select {
    margin: 0 !important; float: none !important; width: 100% !important;
}

.btn-add, #add {
    background: #10b981 !important; color: white !important; border: none !important; padding: 10px 18px;
    border-radius: 8px; font-weight: 500; display: inline-flex; align-items: center;
    gap: 6px; white-space: nowrap; font-family: 'Poppins', sans-serif; font-size: 14px;
}
.btn-add:hover, #add:hover, #add:focus { background: #059669 !important; color: white !important; }

/* ── Products Table ── */
.table-modern {
    background: white; border-radius: 12px; overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 18px; border: 1px solid #f1f5f9;
}
.table-modern table { margin: 0; width: 100%; }
.table-modern thead { background: #f8fafc; }
.table-modern thead th {
    color: #475569; font-weight: 600; font-size: 11.5px; text-transform: uppercase;
    letter-spacing: 0.4px; padding: 12px 14px; border-bottom: 2px solid #e5e7eb; white-space: nowrap;
}
.table-modern tbody td, .table-modern tbody th {
    padding: 11px 14px; vertical-align: middle; border-bottom: 1px solid #f1f5f9;
    color: #1e293b; font-size: 13.5px;
}
.table-modern tbody tr:last-child td { border-bottom: none; }
.table-modern tbody tr:hover { background: #f8fafc; }

/* ── Summary Card ── */
.invoice-summary-card {
    background: white; border: 2px solid #e5e7eb; border-radius: 12px;
    padding: 22px; margin-top: 18px;
}
.invoice-summary-card p { margin-bottom: 12px; }
.invoice-summary-card p b { display: block; font-size: 12.5px; color: #64748b; font-weight: 600; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.3px; }
.invoice-info-actions .btn-primary {
    background: #2563eb; border: none; padding: 11px 20px; border-radius: 8px; font-weight: 500;
}
.invoice-info-actions .btn-primary:hover { background: #1d4ed8; }

select:focus, input[type=number]:focus { background:#fff; }
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

<div class="form-section">
<div class="section-header"><i class="material-icons">edit_document</i>Invoice Details</div>
<div class="row g-3">
<div class="col-md-6">
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
</div>
<div class="col-md-6">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<label class="form-label">Invoice Date*</label>
<?php
// A draft continued here can have an empty date column (never set on
// first creation) — falling back to today keeps this consistent with the
// brand-new-invoice flow below, which always defaults to today too.
$editInvoiceDateVal = $res_inv['date'] ?: date("Y-m-d");
?>
<input id="editInvoiceDate" type="date" name="date" value="<?php echo $editInvoiceDateVal; ?>" required class="form-control">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Same today-plus-4-days-back restriction as the brand-new-invoice date
// field further down this page — this used to be a plain readonly input
// instead, which left the date permanently stuck blank whenever a
// continued draft's date column was empty, since readonly blocks the
// native picker from ever committing a value into it.
var editInvoiceMinDate = new Date();
editInvoiceMinDate.setHours(0, 0, 0, 0);
editInvoiceMinDate.setDate(editInvoiceMinDate.getDate() - 4);
flatpickr("#editInvoiceDate", {
    dateFormat: "Y-m-d", altFormat: "d-m-Y", altInput: true,
    maxDate: "today", minDate: editInvoiceMinDate
});
</script>
<style>.flatpickr-alt-input { margin-bottom: 10px; }</style>
</div>
</div>

<?php if ($amount_received_fully == 0): ?>
<div class="product-add-section">
<div class="section-header" style="border:none;padding-bottom:10px;margin-bottom:12px;"><i class="material-icons">add_shopping_cart</i>Add Product</div>
<div class="product-add-grid">
<div class="input-group-modern wide">
<label>Product</label>
<select required name="pr_id" style="width:100%;" class="prinput" autofocus onchange="showPrice(this.value)">
<option value="" hidden>Select Product</option>
<?php
$res_prods = mysqli_query($db_conn, "SELECT p.id, p.productName FROM products p INNER JOIN territory_partner_stock tps ON tps.product_id = p.id AND tps.territory_partner_id = '$Login_user_IDvl' AND tps.closing_qty > 0 WHERE p.deleted_at IS NULL AND (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL) ORDER BY p.id ASC");
while ($rp = mysqli_fetch_array($res_prods)) {
?>
<option value="<?php echo $rp['id']; ?>"><?php echo $rp['productName']; ?></option>
<?php } ?>
</select>
</div>
<div class="input-group-modern">
<label>Qty</label>
<input type="number" min="0" name="qty" id="qty" onkeyup="totalkm()" required placeholder="Qty" class="numberinput">
</div>
<div class="input-group-modern">
<label>Price</label>
<span id="txtHintPrice"><input type="number" min="0" step="any" name="amount" id="amount" onkeyup="totalkm()" required placeholder="Customer Price"></span>
</div>
<div class="input-group-modern">
<label>MRP</label>
<input type="text" id="mrpDisplayField" placeholder="MRP" class="numberinput" onkeydown="return false;" onpaste="return false;">
</div>
<div class="input-group-modern">
<label>Total</label>
<input type="number" min="0" step="any" name="total" id="output" class="numberinput" required placeholder="Total" readonly>
</div>
<div class="input-group-modern">
<label>Disc (%)</label>
<input type="number" min="0" step="any" id="discountpercentae" name="discount_percentage" onkeyup="discamount()" required placeholder="Disc(%)" class="numberinput">
</div>
<div class="input-group-modern">
<label>Disc (₹)</label>
<input type="number" min="0" id="discountamount" name="discount_amount" step="any" required placeholder="Disc(Rs.)" class="numberinput">
</div>
<div class="input-group-modern">
<button type="submit" name="addInvoice2" class="btn-add" id="add"><i class="material-icons" style="font-size:18px;">add</i>Add</button>
</div>
</div>
</div>
<?php endif; ?>

</div>
</form>

<?php
$TotalAMount123 = 0;
$res_items = mysqli_query($db_conn, "SELECT ii.*, p.productName, p.hsn FROM invoice_items ii LEFT JOIN products p ON ii.pr_id=p.id WHERE ii.inv_id='$Invoice_ID' ORDER BY ii.id DESC");
$CountProducts = mysqli_num_rows($res_items);
$rd = 0;
?>
<div class="table-modern"><div class="table-responsive">
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
            <td><?php echo inr_format((float)$ri['gstamount_total'], 2); ?> (<?php echo $ri['gst_percentage']; ?>%)</td>
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

<div class="invoice-summary-card">
<div class="row g-4">
<div class="col-lg-5"><div class="invoice-info">
    <p style="margin:0 0 4px;color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.3px;">Invoice Number</p>
    <p style="font-weight:700;color:#1e293b;font-size:16px;margin-bottom:14px;">
    <?php if ($get_action == "edit"): ?>
    <a href="#" id="linkcaption" data-bs-toggle="modal" data-bs-target="#invNumModal"><?php echo htmlspecialchars($res_inv['inv_number'] ?? ''); ?></a>
    <?php else: ?>
    <?php echo htmlspecialchars($res_inv['inv_number'] ?? ''); ?>
    <?php endif; ?>
    </p>
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
    <p style="margin:0 0 4px;color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.3px;">Invoice Date</p>
    <p style="font-weight:700;color:#1e293b;font-size:15px;"><?php echo date("d/M/Y", strtotime($res_inv['date'] ?? '')); ?></p>
</div></div>
<div class="col-lg-7"><div class="invoice-info">
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
<textarea name="receipt_remarks" class="form-control"><?php echo htmlspecialchars($res_receipt['receipt_remarks'] ?? ''); ?></textarea>
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

<div class="form-section">
<div class="section-header"><i class="material-icons">edit_document</i>Invoice Details</div>

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

<div class="row g-3">
<div class="col-md-4">
<label class="form-label">Invoice Number*</label>
<input type="text" onkeyup="showInvoiceDuplicate(this.value)" name="inv_number" autofocus required onkeypress="restrictSpecialChars(event)" class="form-control">
<span id="txtHintInvoice"></span>
</div>
<div class="col-md-4">
<label class="form-label" style="display:flex;align-items:center;justify-content:space-between;">
<?php echo $lablenamedisplay; ?>*
<a href="#" data-bs-toggle="modal" data-bs-target="#composeModal" style="font-size:12px;font-weight:600;text-decoration:none;"><i class="material-icons" style="font-size:14px;vertical-align:middle;">add</i>New</a>
</label>
<select required name="customer_id" class="form-control">
<option value="" hidden>Select Customer</option>
<?php
$res_custs = mysqli_query($db_conn, "SELECT * FROM $tablename WHERE user_id='$Login_user_IDvl' ORDER BY name ASC");
while ($rc = mysqli_fetch_array($res_custs)) {
?>
<option value="<?php echo $rc['id']; ?>"><?php echo ucwords($rc['name']); ?>, <?php echo $rc['mobile']; ?></option>
<?php } ?>
</select>
</div>
<div class="col-md-4">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<label class="form-label">Invoice Date*</label>
<input type="date" id="bookingDate" name="date" value="<?php echo date("Y-m-d"); ?>" required class="form-control">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Invoice date can only be today or up to 4 days back — no older
// backdating allowed. altInput swaps in a flatpickr-controlled text field in
// place of the native type="date" widget — without it, the browser's own
// calendar icon still opens its native picker (which ignores minDate/
// maxDate entirely) alongside flatpickr's, letting any date through.
var invoiceMinDate = new Date();
invoiceMinDate.setHours(0, 0, 0, 0);
invoiceMinDate.setDate(invoiceMinDate.getDate() - 4);
var invoiceDateOpts = {
    dateFormat: "Y-m-d", altFormat: "d-m-Y", altInput: true,
    maxDate: "today", minDate: invoiceMinDate
};
flatpickr("#bookingDate", invoiceDateOpts);
</script>
<style>.flatpickr-alt-input { margin-bottom: 10px; }</style>
</div>
</div>

<div class="product-add-section">
<div class="section-header" style="border:none;padding-bottom:10px;margin-bottom:12px;"><i class="material-icons">add_shopping_cart</i>Add Product</div>
<div class="product-add-grid">
<div class="input-group-modern wide">
<label>Product</label>
<select required name="pr_id" style="width:100%;" onchange="showPrice(this.value)" class="prinput">
<option value="" hidden>Select Product</option>
<?php
$res_prods = mysqli_query($db_conn, "SELECT p.id, p.productName FROM products p INNER JOIN territory_partner_stock tps ON tps.product_id = p.id AND tps.territory_partner_id = '$Login_user_IDvl' AND tps.closing_qty > 0 WHERE p.deleted_at IS NULL AND (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL) ORDER BY p.id ASC");
while ($rp = mysqli_fetch_array($res_prods)) {
?>
<option value="<?php echo $rp['id']; ?>"><?php echo $rp['productName']; ?></option>
<?php } ?>
</select>
</div>
<div class="input-group-modern">
<label>Qty</label>
<input type="number" min="0" name="qty" id="qty" onkeyup="totalkm()" required placeholder="Qty" class="numberinput">
</div>
<div class="input-group-modern">
<label>Price</label>
<span id="txtHintPrice"><input type="number" min="0" name="amount" id="amount" onkeyup="totalkm()" required placeholder="Customer Price"></span>
</div>
<div class="input-group-modern">
<label>MRP</label>
<input type="text" id="mrpDisplayField" placeholder="MRP" class="numberinput" onkeydown="return false;" onpaste="return false;">
</div>
<div class="input-group-modern">
<label>Total</label>
<input type="number" min="0" name="total" id="output" readonly required placeholder="Total" class="numberinput">
</div>
<div class="input-group-modern">
<label>Disc (%)</label>
<input type="number" min="0" step="any" id="discountpercentae" name="discount_percentage" onkeyup="discamount()" required placeholder="Disc(%)" class="numberinput">
</div>
<div class="input-group-modern">
<label>Disc (₹)</label>
<input type="number" min="0" id="discountamount" name="discount_amount" step="any" required placeholder="Disc(Rs.)" class="numberinput">
</div>
<div class="input-group-modern">
<button type="submit" name="addInvoice" class="btn-add" id="add"><i class="material-icons" style="font-size:18px;">add</i>Add</button>
</div>
</div>
</div>

</div>
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
<script>
$(document).ready(function() {
    $('select[name="customer_id"]').select2({ width: '100%', placeholder: 'Select', allowClear: true });
    $('select[name="pr_id"]').select2({ width: '100%', placeholder: 'Select Product' });
});
</script>
</body>
</html>
