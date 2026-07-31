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
<?php if (isset($_SESSION['errorMessage'])): ?><div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['errorMessage']); unset($_SESSION['errorMessage']); ?></div><?php endif; ?>

<div class="page-title-modern">
    <h1><i class="material-icons">receipt_long</i><?php if ($get_action == "edit") { echo "Update "; } ?><?php echo $displaytitle; ?></h1>
    <a href="shop-manage-invoice.php" class="menu-link" title="Manage Invoices"><i class="material-icons">list</i></a>
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

.curstockvl { width:100%; border-collapse:collapse; }
.curstockvl th { font-weight:bold; padding:5px; font-size:16px; color:blue; }
.curstockvl td { font-weight:bold; padding:5px; }
select:focus, input[type=number]:focus { background:#fff; }
</style>

<?php if (isset($_REQUEST['InvoiceID'])) {

    $Invoice_ID_encode = $_REQUEST['InvoiceID'];
    $Invoice_ID = base64_decode($_REQUEST['InvoiceID']);

    $result_InvoieDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_invoice WHERE inv_id='$Invoice_ID'"));

    // Receipt check
    $totalamount = $result_InvoieDetails["total"];
    $Total_Receipt_amount = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(received) FROM receipt WHERE inv_id='$Invoice_ID'"))[0];
    $amount_received_fully = ($Total_Receipt_amount > 0 && $totalamount == $Total_Receipt_amount) ? "1" : "0";

    // order-to-invoice.php (the "Invoice" button on manage-orders.php) creates
    // invoices straight from a field visit with no manually-typed invoice
    // number yet. Until such an invoice is actually submitted (has a receipt —
    // same check manage-orders.php uses for "Continue" vs "Completed"), force
    // a blank, retype-and-duplicate-check Invoice Number field every time the
    // TP reopens it via "Continue Invoice", instead of remembering whatever
    // was typed on a previous visit.
    $stmtOrig = $db_conn->prepare("SELECT 1 FROM tp_orders WHERE invoiced_inv_id=? LIMIT 1");
    $stmtOrig->bind_param('s', $Invoice_ID);
    $stmtOrig->execute();
    $isFromFieldOrder = (bool)$stmtOrig->get_result()->fetch_assoc();
    $stmtOrig->close();

    // A DM-assigned order (not the TP's own field visit) gets a direct
    // qty/price/discount Edit on each line, instead of only Remove+re-Add —
    // the DM's suggested order is a starting point the TP should be able to
    // adjust in place.
    $stmtDm = $db_conn->prepare("SELECT 1 FROM tp_orders WHERE invoiced_inv_id=? AND assigned_by_ms_id IS NOT NULL LIMIT 1");
    $stmtDm->bind_param('s', $Invoice_ID);
    $stmtDm->execute();
    $isFromDmOrder = (bool)$stmtDm->get_result()->fetch_assoc();
    $stmtDm->close();

    $stmtRct = $db_conn->prepare("SELECT 1 FROM receipt WHERE inv_id=? LIMIT 1");
    $stmtRct->bind_param('s', $Invoice_ID);
    $stmtRct->execute();
    $hasReceipt = (bool)$stmtRct->get_result()->fetch_assoc();
    $stmtRct->close();

    $needs_inv_number = $isFromFieldOrder && !$hasReceipt;

    $CustomerID = $result_InvoieDetails['to_user_id'];
    $result_CUSTDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM $tablename WHERE temp_id='$CustomerID'"));
?>

<form action="shop-invoice-action2.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="inv_id" value="<?php echo $Invoice_ID; ?>">
<input type="hidden" name="invuser" value="<?php echo $getinvuser; ?>">

<div class="form-section">
<div class="section-header"><i class="material-icons">edit_document</i>Invoice Details</div>
<div class="row g-3">
<div class="col-md-6">
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
</div>
<div class="col-md-6">
<label class="form-label">Invoice Date*</label>
<input type="date" readonly name="date" value="<?php echo $result_InvoieDetails['date']; ?>" required class="form-control">
</div>
</div>

<?php if ($amount_received_fully == 0) { ?>
<div class="product-add-section">
<div class="section-header" style="border:none;padding-bottom:10px;margin-bottom:12px;"><i class="material-icons">add_shopping_cart</i>Add Product</div>
<div class="product-add-grid">
<div class="input-group-modern wide">
<label>Product</label>
<select required name="pr_id" class="prinput" style="width:100%;" autofocus onchange="showPrice(this.value)">
<option value="" hidden>Select Product</option>
<?php
$res_prods = mysqli_query($db_conn, "SELECT p.id, p.productName FROM products p INNER JOIN territory_partner_stock tps ON tps.product_id = p.id AND tps.territory_partner_id = '$Login_user_IDvl' AND tps.closing_qty > 0 WHERE (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL) ORDER BY p.id ASC");
while ($rp = mysqli_fetch_array($res_prods)) { ?>
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
<span id="txtHintPrice"><input type="number" min="0" name="amount" step="any" id="amount" onkeyup="totalkm()" required placeholder="Price"></span>
</div>
<div class="input-group-modern">
<label>MRP</label>
<input type="text" id="mrpDisplayField" placeholder="MRP" class="numberinput" onkeydown="return false;" onpaste="return false;">
</div>
<div class="input-group-modern">
<label>Total</label>
<input type="number" min="0" step="any" name="total" id="output" class="numberinput" required placeholder="Total">
</div>
<script>
function discamount() {
    var output = document.getElementById('output').value;
    var discountpercentae = document.getElementById('discountpercentae').value;
    document.getElementById('discountamount').value = (output * discountpercentae / 100).toFixed(2);
}
</script>
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
<?php } ?>

</div>
</form>

<!-- Items table -->
<div class="table-modern">
<div class="table-responsive">
<table class="table">
    <thead><tr>
        <th>#</th><th>Product Description</th><th>Qty</th><th>MRP</th><th>Shop Price</th>
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
    <tr id="itemrow_<?php echo $ri['id']; ?>">
        <th><?php echo ++$rd; ?></th>
        <td><?php echo $pr['productName']; ?></td>
        <?php $canEditLine = $isFromDmOrder && $amount_received_fully == 0; ?>
        <td>
            <?php if ($canEditLine) { ?>
            <input type="number" min="0" step="any" id="qty_<?php echo $ri['id']; ?>"
                   value="<?php echo $ri['qty']; ?>"
                   onchange="saveLineEdit(<?php echo $ri['id']; ?>, '<?php echo $Invoice_ID_encode; ?>', '<?php echo $ItemRowid; ?>', '<?php echo $getinvuser; ?>')"
                   class="form-control form-control-sm" style="width:70px;">
            <?php } else { ?>
            <?php echo $ri['qty']; ?>
            <?php } ?>
        </td>
        <td class="text-muted">&#8377;<?php echo inr_format($pr['mrp'] ?? 0, 2); ?></td>
        <td>
            <?php if ($canEditLine) { ?>
            <input type="number" min="0" step="any" id="price_<?php echo $ri['id']; ?>"
                   value="<?php echo $ri['amount']; ?>"
                   onchange="saveLineEdit(<?php echo $ri['id']; ?>, '<?php echo $Invoice_ID_encode; ?>', '<?php echo $ItemRowid; ?>', '<?php echo $getinvuser; ?>')"
                   class="form-control form-control-sm" style="width:80px;">
            <?php } else { ?>
            &#8377;<?php echo inr_format($ri['amount'], 2); ?>
            <?php } ?>
        </td>
        <td>
            <?php if ($canEditLine) { ?>
            <input type="number" min="0" step="any" id="discamt_<?php echo $ri['id']; ?>"
                   value="<?php echo $ri['discount_amount']; ?>" placeholder="Rs."
                   onchange="saveLineEdit(<?php echo $ri['id']; ?>, '<?php echo $Invoice_ID_encode; ?>', '<?php echo $ItemRowid; ?>', '<?php echo $getinvuser; ?>')"
                   class="form-control form-control-sm" style="width:80px;">
            <div style="font-size:11px;color:#6b7280;">(<?php echo $ri['discount_percentage']; ?>%)</div>
            <?php } else { ?>
            <?php echo $ri['discount_amount']; ?>(<?php echo $ri['discount_percentage']; ?>%)
            <?php } ?>
        </td>
        <td>&#8377;<?php echo inr_format($ri['subtotal'], 2); ?></td>
        <td>
            <?php if ($canEditLine) { ?>
            <input type="number" min="0" step="any" id="gstpct_<?php echo $ri['id']; ?>"
                   value="<?php echo $ri['gst_percentage']; ?>" placeholder="GST %"
                   onchange="saveLineEdit(<?php echo $ri['id']; ?>, '<?php echo $Invoice_ID_encode; ?>', '<?php echo $ItemRowid; ?>', '<?php echo $getinvuser; ?>')"
                   class="form-control form-control-sm" style="width:70px;">
            <?php } else { ?>
            <?php echo inr_format((float)$ri['gstamount_total'], 2); ?>(<?php echo $ri['gst_percentage']; ?>%)
            <?php } ?>
        </td>
        <td align="right"><?php echo inr_format($TotalAMount, 2); ?></td>
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

<?php if ($isFromDmOrder) { ?>
<form id="rowEditForm" method="post" action="shop-invoice-item-edit.php" style="display:none;">
    <input type="hidden" name="invid" id="rf_invid">
    <input type="hidden" name="rowid" id="rf_rowid">
    <input type="hidden" name="invuser" id="rf_invuser">
    <input type="hidden" name="qty" id="rf_qty">
    <input type="hidden" name="amount" id="rf_amount">
    <input type="hidden" name="discount_amount" id="rf_discamt">
    <input type="hidden" name="gst_percentage" id="rf_gstpct">
</form>
<script>
// Click straight into Qty / Shop Price / Discount(Rs.) / GST% on a
// DM-assigned order's invoice and change it — saves itself the moment you
// leave the field, no separate Edit/Save step. Discount is rupees-only;
// the percentage shown underneath is derived and read-only.
function saveLineEdit(id, invid, rowid, invuser) {
    var qty     = document.getElementById('qty_' + id).value;
    var price   = document.getElementById('price_' + id).value;
    var discamt = document.getElementById('discamt_' + id).value;
    var gstpct  = document.getElementById('gstpct_' + id).value;
    if (qty === '' || isNaN(qty)) { return; }
    if (price === '' || isNaN(price)) { return; }
    document.getElementById('rf_invid').value = invid;
    document.getElementById('rf_rowid').value = rowid;
    document.getElementById('rf_invuser').value = invuser;
    document.getElementById('rf_qty').value = qty;
    document.getElementById('rf_amount').value = price;
    document.getElementById('rf_discamt').value = discamt === '' ? 0 : discamt;
    document.getElementById('rf_gstpct').value = gstpct === '' ? 0 : gstpct;
    document.getElementById('rowEditForm').submit();
}
</script>
<?php } ?>

<?php if ($needs_inv_number): ?>
<script>
// Live duplicate check only — no separate save step and no page reload while
// typing. The number is just a normal field on the Submit Invoice form below;
// it gets validated and saved together with everything else on final submit.
function showInvoiceDuplicateReal(str) {
    var hint = document.getElementById('txtHintInvoiceReal');
    if (str == "") { hint.innerHTML = ""; return; }
    if (window.XMLHttpRequest) { xmlhttp = new XMLHttpRequest(); }
    else { xmlhttp = new ActiveXObject("Microsoft.XMLHTTP"); }
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            hint.innerHTML = xmlhttp.responseText;
        }
    };
    xmlhttp.open("GET", "loadInvoiceNumberUSER.php?q=" + str + "&exclude=<?php echo urlencode($Invoice_ID); ?>", true);
    xmlhttp.send();
}
</script>
<?php endif; ?>

<!-- Submit form -->
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

<div class="invoice-summary-card">
<div class="row g-4">
<div class="col-lg-5">
<div class="invoice-info">
<p style="margin:0 0 4px;color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.3px;">Invoice Number</p>
<?php if ($needs_inv_number): ?>
<input type="text" name="invnumber" id="realInvNumber" onkeyup="showInvoiceDuplicateReal(this.value)"
       onkeypress="restrictSpecialChars(event);"
       autofocus required class="form-control" style="background:#fffbea;"
       placeholder="Enter a unique invoice number">
<span id="txtHintInvoiceReal"></span>
<?php else: ?>
<p style="font-weight:700;color:#1e293b;font-size:16px;margin-bottom:14px;">
<?php if ($get_action == "edit") { ?>
<a href="#" id="linkcaption" data-bs-toggle="modal" data-bs-target="#exampleModalLive"><?php echo $result_InvoieDetails['inv_number']; ?></a>
<?php } else { ?>
<?php echo $result_InvoieDetails['inv_number']; ?>
<?php } ?>
</p>
<?php endif; ?>
<p style="margin:0 0 4px;color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.3px;">Invoice Date</p>
<p style="font-weight:700;color:#1e293b;font-size:15px;"><?php echo date("d/M/Y", strtotime($result_InvoieDetails['date'])); ?></p>
</div>
</div>

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

<div class="col-lg-7"><div class="invoice-info">

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
<input type="number" min="0" required step="any" max="<?php echo inr_format($balance_due, 2); ?>" id="receivedamount" class="form-control" style="width:100%;" onkeyup="receiptamount()" name="receivedamount" placeholder="Max: <?php echo inr_format($balance_due, 2); ?>">
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
<textarea name="receipt_remarks" class="form-control"><?php echo $show_remarks; ?></textarea>
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
    xmlhttp.open("GET", "loadInvoiceNumberUSER.php?q=" + str, true);
    xmlhttp.send();
}
</script>

<div class="row g-3">
<div class="col-md-4">
<label class="form-label">Invoice Number *</label>
<input type="text" onkeyup="showInvoiceDuplicate(this.value)" name="inv_number" autofocus required onkeypress="restrictSpecialChars(event)" class="form-control">
<span id="txtHintInvoice"></span>
</div>
<div class="col-md-4">
<label class="form-label"><?php echo $lablenamedisplay; ?>*</label>
<select required name="customer_id" class="form-control" autofocus>
<option value="" hidden>Select</option>
<?php
$res_shops = mysqli_query($db_conn, "SELECT * FROM $tablename WHERE onboard_userTYPE='$Login_user_TYPEvl' AND onboard_userID='$Login_user_IDvl' ORDER BY name ASC");
while ($r = mysqli_fetch_array($res_shops)) { ?>
<option value="<?php echo $r['temp_id']; ?>"><?php echo ucwords($r['name']); ?>, <?php echo $r['mobile_number']; ?>, <?php echo ucwords($r['address']); ?></option>
<?php } ?>
</select>
</div>
<div class="col-md-4">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<label class="form-label">Invoice Date*</label>
<input id="bookingDate" type="date" name="date" value="<?php echo date("Y-m-d"); ?>" required class="form-control">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>flatpickr("#bookingDate", { dateFormat: "Y-m-d", maxDate: "today" });</script>
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
$res_prods = mysqli_query($db_conn, "SELECT p.id, p.productName FROM products p INNER JOIN territory_partner_stock tps ON tps.product_id = p.id AND tps.territory_partner_id = '$Login_user_IDvl' AND tps.closing_qty > 0 WHERE (p.temp_id NOT LIKE 'NKS-%' OR p.temp_id IS NULL) ORDER BY p.id ASC");
while ($rp = mysqli_fetch_array($res_prods)) { ?>
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
<span id="txtHintPrice"><input type="number" min="0" name="amount" step="any" id="amount" onkeyup="totalkm()" required placeholder="Price"></span>
</div>
<div class="input-group-modern">
<label>MRP</label>
<input type="text" id="mrpDisplayField" placeholder="MRP" class="numberinput" onkeydown="return false;" onpaste="return false;">
</div>
<div class="input-group-modern">
<label>Total</label>
<input type="number" min="0" step="any" name="total" id="output" required placeholder="Total" class="numberinput">
</div>
<script>
function discamount() {
    var output = document.getElementById('output').value;
    var discountpercentae = document.getElementById('discountpercentae').value;
    document.getElementById('discountamount').value = (output * discountpercentae / 100).toFixed(2);
}
</script>
<div class="input-group-modern">
<label>Disc (%)</label>
<input type="number" min="0" step="any" id="discountpercentae" name="discount_percentage" onkeyup="discamount()" required placeholder="Disc(%)" class="numberinput">
</div>
<div class="input-group-modern">
<label>Disc (₹)</label>
<input type="number" min="0" id="discountamount" name="discount_amount" step="any" required placeholder="Disc(Rs.)" class="numberinput">
</div>
<div class="input-group-modern">
<span id="txtHintstock">
<button type="submit" name="addInvoice" class="btn-add" id="add"><i class="material-icons" style="font-size:18px;">add</i>Add</button>
</span>
</div>
</div>
</div>

</div>
</form>

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
