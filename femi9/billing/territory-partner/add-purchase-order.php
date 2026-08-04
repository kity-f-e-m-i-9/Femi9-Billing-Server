<?php
include("checksession.php");
include("config.php");
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$order_date = date("Y-m-d");
$title      = "Purchase Order";

// Full company product catalog — this is a stock replenishment request to the
// company, not limited to what the TP already holds (unlike Field Order).
$productList = [];
$resProd = mysqli_query($db_conn, "SELECT id, productName, stockist_price FROM products WHERE deleted_at IS NULL AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY productName ASC");
if ($resProd) while ($p = mysqli_fetch_assoc($resProd)) $productList[] = $p;

// Available advance balance
$balStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(balance_amount), 0) AS bal
     FROM tp_advance_payments WHERE territory_partner_id = ? AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL"
);
mysqli_stmt_bind_param($balStmt, "i", $Login_user_IDvl);
mysqli_stmt_execute($balStmt);
$advBalance = (float)(mysqli_stmt_get_result($balStmt)->fetch_assoc()['bal'] ?? 0);
mysqli_stmt_close($balStmt);

// Orders still "waiting" (not yet invoiced/fulfilled, not cancelled) already
// have their advance-covered portion (order total minus any excess covered
// by uploaded payment proof) implicitly earmarked, even though
// tp_advance_payments.balance_amount is only actually decremented once the
// order is fulfilled. Without subtracting this, a TP could place several
// pending orders that each look "within budget" individually while
// cumulatively over-committing the real balance.
$reservedStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(poi.total - po.excess_amount), 0) AS reserved
     FROM tp_purchase_orders po
     JOIN (SELECT po_id, SUM(amount) AS total FROM tp_purchase_order_items GROUP BY po_id) poi
       ON poi.po_id = po.id
     WHERE po.territory_partner_id = ? AND po.status = 'waiting'"
);
mysqli_stmt_bind_param($reservedStmt, "i", $Login_user_IDvl);
mysqli_stmt_execute($reservedStmt);
$reservedAmount = (float)(mysqli_stmt_get_result($reservedStmt)->fetch_assoc()['reserved'] ?? 0);
mysqli_stmt_close($reservedStmt);

$advBalance = max(0, round($advBalance - $reservedAmount, 2));

// Screenshots uploaded for this TP's in-progress order (not yet linked to a
// submitted PO) — resumable if the TP reloads the page mid-upload.
$existingScreenshots = [];
$acceptedScreenshotTotal = 0.0;
$scrStmt = mysqli_prepare($db_conn,
    "SELECT id, status, detected_amount, reference_number, rejection_reason, file_path
     FROM tp_purchase_order_screenshots WHERE territory_partner_id = ? AND po_id IS NULL ORDER BY id ASC"
);
mysqli_stmt_bind_param($scrStmt, "i", $Login_user_IDvl);
mysqli_stmt_execute($scrStmt);
$scrRes = mysqli_stmt_get_result($scrStmt);
while ($s = mysqli_fetch_assoc($scrRes)) {
    $existingScreenshots[] = $s;
    if ($s['status'] === 'accepted') $acceptedScreenshotTotal += (float)$s['detected_amount'];
}
mysqli_stmt_close($scrStmt);

// TP's own registered delivery address — shown as the default when the
// "use existing delivery address" checkbox is left checked.
$tpDeliveryStmt = mysqli_prepare($db_conn,
    "SELECT delivery_line1, delivery_line2, delivery_city, delivery_district, delivery_state, delivery_country, delivery_pincode
     FROM territory_partners WHERE id = ?"
);
mysqli_stmt_bind_param($tpDeliveryStmt, "i", $Login_user_IDvl);
mysqli_stmt_execute($tpDeliveryStmt);
$tpDeliveryAddress = mysqli_stmt_get_result($tpDeliveryStmt)->fetch_assoc() ?: [];
mysqli_stmt_close($tpDeliveryStmt);
$tpDeliveryAddressParts = array_filter([
    $tpDeliveryAddress['delivery_line1'] ?? '',
    $tpDeliveryAddress['delivery_line2'] ?? '',
    implode(', ', array_filter([$tpDeliveryAddress['delivery_city'] ?? '', $tpDeliveryAddress['delivery_district'] ?? ''])),
    implode(', ', array_filter([$tpDeliveryAddress['delivery_state'] ?? '', $tpDeliveryAddress['delivery_country'] ?? ''])),
    !empty($tpDeliveryAddress['delivery_pincode']) ? 'Pincode: ' . $tpDeliveryAddress['delivery_pincode'] : '',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title;?> : <?php echo $business_name;?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        body { font-family: 'Poppins', sans-serif; }

        .btn-back-orders {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fff; color: #667eea; border: 1px solid #e5e7eb;
            padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 13.5px;
            text-decoration: none; transition: all .2s;
        }
        .btn-back-orders:hover { background: #f8fafc; color: #667eea; border-color: #667eea; }

        .apo-card { background: #fff; border-radius: 12px; padding: 22px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .apo-card-title {
            font-size: 12.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #6b7280;
            margin-bottom: 16px; display: flex; align-items: center; gap: 6px;
        }
        .apo-card-title .material-icons-outlined { font-size: 17px; color: #667eea; }

        .apo-info-row { display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 4px; }
        .apo-info-chip { flex: 1; min-width: 180px; }
        .apo-info-chip label { display: block; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: #9ca3af; margin-bottom: 3px; }
        .apo-info-chip .value { font-size: 14.5px; font-weight: 600; color: #1f2937; }

        .apo-balance-card {
            background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%); border: 1px solid #a7f3d0;
            border-radius: 12px; padding: 16px 20px; margin-bottom: 20px;
            display: flex; align-items: center; gap: 12px;
        }
        .apo-balance-card .material-icons-outlined { font-size: 30px; color: #10b981; }
        .apo-balance-card .value { font-size: 20px; font-weight: 700; color: #065f46; }
        .apo-balance-card .label { font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: #059669; }

        .apo-delivery-default { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 16px; font-size: 13.5px; color: #374151; line-height: 1.6; }
        .apo-delivery-check { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; font-size: 14px; font-weight: 600; color: #374151; }
        .apo-delivery-check input { width: 16px; height: 16px; }
        .apo-delivery-fields { display: none; }
        .apo-delivery-fields.show { display: block; }

        .apo-add-panel { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px; margin-bottom: 6px; }
        .apo-add-panel .form-label { font-size: 11.5px; font-weight: 600; color: #6b7280; margin-bottom: 4px; }
        #add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: #fff;
            font-weight: 600; border-radius: 8px; transition: all .2s;
        }
        #add:hover, #add:focus { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,.35); color: #fff; }

        .apo-table-wrap { border: 1px solid #f1f5f9; border-radius: 10px; overflow: hidden; margin-top: 18px; }
        .apo-table-wrap table { width: 100%; margin: 0; }
        .apo-table-wrap thead th {
            background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .4px; padding: 10px 14px; border-bottom: 2px solid #e5e7eb;
        }
        .apo-table-wrap tbody td { padding: 5px !important; padding: 10px 14px !important; vertical-align: middle; font-size: 13.5px; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
        .apo-table-wrap tbody tr:last-child td { border-bottom: none; }
        .apo-remove-chip {
            border: none; cursor: pointer; background: #fee2e2; color: #991b1b;
            font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px;
        }
        .apo-remove-chip:hover { background: #fecaca; }

        .apo-summary-row {
            display: flex; align-items: center; gap: 18px; flex-wrap: wrap;
            background: #f8fafc; border-radius: 10px; padding: 14px 18px; margin: 18px 0;
            font-size: 14px; color: #374151;
        }
        .apo-summary-row .amt { font-weight: 700; color: #1f2937; }

        .apo-excess-card {
            background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px;
        }
        .apo-excess-card .apo-excess-title { font-weight: 700; color: #92400e; font-size: 14.5px; margin-bottom: 8px; }
        .apo-excess-card .apo-excess-desc { font-size: 12.5px; color: #78716c; margin-bottom: 10px; }
        .apo-progress-track { background: #fde68a; border-radius: 20px; height: 8px; overflow: hidden; margin: 8px 0 14px; }
        .apo-progress-fill { background: #10b981; height: 100%; border-radius: 20px; transition: width .3s; }

        .apo-screenshot-card {
            background: #fff; border: 1px solid #f1f5f9; border-radius: 10px; padding: 12px 14px;
            display: flex; align-items: center; gap: 12px; margin-bottom: 10px;
        }
        .apo-screenshot-card .thumb { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; border: 1px solid #e5e7eb; flex-shrink: 0; }
        .apo-screenshot-card .thumb-placeholder {
            width: 48px; height: 48px; border-radius: 8px; background: #f3f4f6; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; color: #9ca3af;
        }
        .apo-screenshot-card .details { flex: 1; min-width: 0; font-size: 12.5px; color: #4b5563; }
        .apo-status-pill { padding: 3px 10px; border-radius: 20px; font-size: 10.5px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; }
        .apo-status-pill.accepted { background: #d1fae5; color: #065f46; }
        .apo-status-pill.rejected { background: #fee2e2; color: #991b1b; }
        .apo-status-pill.pending  { background: #fef3c7; color: #92400e; }

        .apo-upload-row { background: #fff; border: 1px dashed #d1d5db; border-radius: 10px; padding: 14px; }

        .btn-submit-po {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: #fff;
            font-weight: 600; padding: 11px 26px; border-radius: 10px; font-size: 14.5px; transition: all .2s;
        }
        .btn-submit-po:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(102,126,234,.4); color: #fff; }
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
                            <div class="col d-flex align-items-center justify-content-between flex-wrap" style="gap:10px;">
                                <div class="page-description">
                                    <h1 style="margin:0;"><?php echo $title;?></h1>
                                </div>
                                <a href="manage-purchase-orders.php" class="btn-back-orders">
                                    <i class="material-icons" style="font-size:17px;">list_alt</i> My Purchase Orders
                                </a>
                            </div>
                        </div>
                        <br/>

                        <form action="purchase-order-action.php" method="post" id="uploadForm" onsubmit="return validatePoLines();">
                            <input type="hidden" id="advBalanceVal" value="<?=$advBalance?>">

                            <div class="apo-balance-card">
                                <i class="material-icons-outlined">account_balance_wallet</i>
                                <div>
                                    <div class="label">Available Advance Balance</div>
                                    <div class="value">&#8377;<?=inr_format($advBalance, 2)?></div>
                                </div>
                            </div>

                            <div class="apo-card">
                                <div class="apo-card-title"><i class="material-icons-outlined">badge</i>Order Details</div>
                                <div class="apo-info-row">
                                    <div class="apo-info-chip">
                                        <label>Territory Partner</label>
                                        <div class="value"><?=htmlspecialchars($Login_user_name)?></div>
                                    </div>
                                    <div class="apo-info-chip">
                                        <label>Invoice Date</label>
                                        <div class="value"><?=date("d-m-Y", strtotime($order_date))?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="apo-card">
                                <div class="apo-card-title"><i class="material-icons-outlined">local_shipping</i>Delivery Address</div>

                                <label class="apo-delivery-check">
                                    <input type="checkbox" id="useDefaultDeliveryAddress" name="use_default_delivery_address" value="1" checked onchange="toggleDeliveryFields()">
                                    Use existing delivery address
                                </label>

                                <div id="defaultDeliveryPreview" class="apo-delivery-default">
                                    <?php if (!empty($tpDeliveryAddressParts)): ?>
                                        <?=implode('<br/>', array_map('htmlspecialchars', $tpDeliveryAddressParts))?>
                                    <?php else: ?>
                                        <span class="text-muted">No delivery address on file. Uncheck above to enter one.</span>
                                    <?php endif; ?>
                                </div>

                                <div id="customDeliveryFields" class="apo-delivery-fields">
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Address Line 1</label>
                                            <input type="text" name="custom_delivery_line1" id="custom_delivery_line1" class="form-control" placeholder="Address Line 1">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Address Line 2</label>
                                            <input type="text" name="custom_delivery_line2" class="form-control" placeholder="Address Line 2">
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-3">
                                            <label class="form-label">City</label>
                                            <input type="text" name="custom_delivery_city" class="form-control" placeholder="City">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">District</label>
                                            <input type="text" name="custom_delivery_district" class="form-control" placeholder="District">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">State</label>
                                            <input type="text" name="custom_delivery_state" class="form-control" placeholder="State">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Pincode</label>
                                            <input type="text" name="custom_delivery_pincode" class="form-control" placeholder="Pincode">
                                        </div>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label">Country</label>
                                            <input type="text" name="custom_delivery_country" class="form-control" placeholder="Country" value="India">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (empty($productList)): ?>
                            <div class="alert alert-warning">No products available to order.</div>
                            <?php else: ?>
                            <div class="apo-card">
                                <div class="apo-card-title"><i class="material-icons-outlined">add_shopping_cart</i>Add Product</div>

                                <div class="apo-add-panel">
                                    <label class="form-label">Select Product</label>
                                    <select class="form-control mb-2" id="pr_select" onchange="showPoPrice(this.value)">
                                        <option value=""></option>
                                        <?php foreach ($productList as $p): ?>
                                        <option value="<?=$p['id']?>" data-price="<?=htmlspecialchars($p['stockist_price'])?>"><?=htmlspecialchars($p['productName'])?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <div class="row g-2 align-items-end">
                                        <div class="col">
                                            <label class="form-label">Qty</label>
                                            <input type="number" min="1" id="po_qty" onkeyup="poTotal()" placeholder="Qty" class="form-control">
                                        </div>
                                        <div class="col">
                                            <label class="form-label">Price</label>
                                            <input type="number" min="0" step="any" id="po_price" placeholder="Price" class="form-control" disabled>
                                        </div>
                                        <div class="col">
                                            <label class="form-label">Total</label>
                                            <input type="number" min="0" step="any" id="po_total" placeholder="Total" class="form-control" readonly>
                                        </div>
                                        <div class="col">
                                            <label class="form-label">Disc(%)</label>
                                            <input type="number" min="0" step="any" id="po_disc_pct" onkeyup="poDiscAmount()" placeholder="Disc(%)" class="form-control">
                                        </div>
                                        <div class="col">
                                            <label class="form-label">Disc(Rs.)</label>
                                            <input type="number" min="0" step="any" id="po_disc_amt" placeholder="Disc(Rs.)" class="form-control">
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" class="btn" id="add" onclick="addPoLine()"><i class="material-icons" style="font-size:16px;vertical-align:middle;">add</i> Add</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="apo-table-wrap">
                                <table class="table table-bordered mb-0">
                                    <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Price</th><th>Disc</th><th>Total</th><th></th></tr></thead>
                                    <tbody id="poItemsBody">
                                        <tr id="poItemsEmptyRow"><td colspan="7" class="text-center text-muted">No products added yet.</td></tr>
                                    </tbody>
                                </table>
                                </div>
                                <div id="hiddenInputsHolder"></div>

                                <div class="apo-summary-row">
                                    <span>Order Total: <span class="amt" id="poGrandTotal">&#8377;0.00</span></span>
                                    <span style="color:#d1d5db;">|</span>
                                    <span>Available Advance Balance: <span class="amt">&#8377;<?=inr_format($advBalance, 2)?></span></span>
                                </div>
                            </div>

                            <div id="poExcessWarning" class="apo-excess-card" style="display:none;">
                                <div class="apo-excess-title">Your order total exceeds your available advance balance by &#8377;<span id="poExcessAmount">0.00</span></div>
                                <div class="apo-excess-desc">
                                    Upload proof of payment (UPI/NEFT/RTGS/net banking) for the excess amount — up to 5 screenshots.
                                    Each is checked automatically for a readable amount and UTR/transaction number; unclear ones are
                                    sent for manual review instead of being rejected outright.
                                </div>

                                <div style="font-size:13px;color:#78716c;">Uploaded towards excess (accepted + pending review): <strong id="poAcceptedTotal" style="color:#065f46;">0.00</strong> / &#8377;<span id="poExcessAmount2">0.00</span></div>
                                <div class="apo-progress-track"><div class="apo-progress-fill" id="poProgressFill" style="width:0%;"></div></div>
                                <div id="poReadyNote" style="display:none;font-size:12.5px;color:#065f46;margin:-8px 0 12px;">
                                    <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">check_circle</i>
                                    A screenshot has been uploaded and is awaiting/passed verification — you can submit; company will review the amount before invoicing.
                                </div>

                                <div id="poScreenshotList"></div>

                                <div class="apo-upload-row" id="poScreenshotUploadRow">
                                    <input type="file" id="po_screenshot_file" accept="image/*,.heic,.heif" class="form-control d-inline-block mb-2" style="max-width:320px;">
                                    <button type="button" class="btn btn-secondary btn-sm" id="poScreenshotUploadBtn" onclick="uploadPoScreenshot()">Upload</button>
                                    <small class="text-muted d-block mt-1">Max file size: 10 MB per image, up to 5 images.</small>
                                    <div id="poScreenshotStatus" class="mt-1" style="font-size:12.5px;"></div>
                                </div>
                            </div>

                            <button type="submit" name="submit_po" id="poSubmitBtn" onclick="return confirm('Submit this purchase order?');" class="btn-submit-po">
                                <i class="material-icons" style="vertical-align:middle;font-size:18px;">add</i> Submit Purchase Order
                            </button>
                            <?php endif; ?>
                        </form>

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
    <script src="https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js"></script>

    <script>
    $(document).ready(function() {
        $('#pr_select').select2({ width: '100%', placeholder: 'Select Product' });
    });

    function toggleDeliveryFields() {
        var useDefault = document.getElementById('useDefaultDeliveryAddress').checked;
        document.getElementById('defaultDeliveryPreview').style.display = useDefault ? '' : 'none';
        document.getElementById('customDeliveryFields').classList.toggle('show', !useDefault);
    }

    var poLines = [];

    function showPoPrice(str) {
        var sel = document.getElementById('pr_select');
        var opt = sel.options[sel.selectedIndex];
        document.getElementById('po_price').value = str ? (opt.getAttribute('data-price') || '') : '';
        poTotal();
    }

    function poTotal() {
        var qty   = parseFloat(document.getElementById('po_qty').value) || 0;
        var price = parseFloat(document.getElementById('po_price').value) || 0;
        document.getElementById('po_total').value = (qty * price).toFixed(2);
        poDiscAmount();
    }

    function poDiscAmount() {
        var total = parseFloat(document.getElementById('po_total').value) || 0;
        var pct   = parseFloat(document.getElementById('po_disc_pct').value) || 0;
        document.getElementById('po_disc_amt').value = (total * pct / 100).toFixed(2);
    }

    function addPoLine() {
        var sel   = document.getElementById('pr_select');
        var prId  = sel.value;
        var prName = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
        var qty   = parseInt(document.getElementById('po_qty').value) || 0;
        var price = parseFloat(document.getElementById('po_price').value) || 0;
        var discPct = parseFloat(document.getElementById('po_disc_pct').value) || 0;
        var discAmt = parseFloat(document.getElementById('po_disc_amt').value) || 0;

        if (!prId) { alert('Select a product.'); return; }
        if (qty <= 0) { alert('Enter a valid qty.'); return; }
        for (var i = 0; i < poLines.length; i++) {
            if (poLines[i].pr_id === prId) { alert('That product is already added.'); return; }
        }

        poLines.push({ pr_id: prId, name: prName, qty: qty, price: price, discPct: discPct, discAmt: discAmt });
        renderPoLines();

        $(sel).val('').trigger('change');
        document.getElementById('po_qty').value = '';
        document.getElementById('po_price').value = '';
        document.getElementById('po_total').value = '';
        document.getElementById('po_disc_pct').value = '';
        document.getElementById('po_disc_amt').value = '';
    }

    function removePoLine(idx) {
        poLines.splice(idx, 1);
        renderPoLines();
    }

    function renderPoLines() {
        var tbody = document.getElementById('poItemsBody');
        tbody.innerHTML = '';
        if (poLines.length === 0) {
            tbody.innerHTML = '<tr id="poItemsEmptyRow"><td colspan="7" class="text-center text-muted">No products added yet.</td></tr>';
        } else {
            poLines.forEach(function(l, idx) {
                var grossTotal = l.qty * l.price;
                var netTotal = (grossTotal - l.discAmt).toFixed(2);
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<th>' + (idx + 1) + '</th>' +
                    '<td>' + l.name + '</td>' +
                    '<td>' + l.qty + '</td>' +
                    '<td>₹' + l.price.toFixed(2) + '</td>' +
                    '<td>₹' + l.discAmt.toFixed(2) + '(' + l.discPct.toFixed(2) + '%)</td>' +
                    '<td><strong>₹' + netTotal + '</strong></td>' +
                    '<td><button type="button" class="apo-remove-chip" onclick="removePoLine(' + idx + ')">Remove</button></td>';
                tbody.appendChild(tr);
            });
        }

        var holder = document.getElementById('hiddenInputsHolder');
        holder.innerHTML = '';
        poLines.forEach(function(l) {
            holder.innerHTML +=
                '<input type="hidden" name="pr_id[]" value="' + l.pr_id + '">' +
                '<input type="hidden" name="qty[]" value="' + l.qty + '">' +
                '<input type="hidden" name="price[]" value="' + l.price + '">' +
                '<input type="hidden" name="discount_percentage[]" value="' + l.discPct + '">' +
                '<input type="hidden" name="discount_amount[]" value="' + l.discAmt + '">';
        });

        updatePoSummary();
    }

    var MAX_SCREENSHOT_BYTES = 10 * 1024 * 1024; // 10 MB
    var MAX_SCREENSHOTS = 5;
    var poScreenshots = <?=json_encode(array_map(function ($s) {
        return [
            'id' => (int)$s['id'],
            'status' => $s['status'],
            'detected_amount' => $s['detected_amount'] !== null ? (float)$s['detected_amount'] : null,
            'reference_number' => $s['reference_number'],
            'reason' => $s['rejection_reason'],
            'file_url' => $s['file_path'] ? ('po_screenshots/' . $s['file_path']) : null,
        ];
    }, $existingScreenshots))?>;
    var poAcceptedTotal = <?=json_encode($acceptedScreenshotTotal)?>;

    // A screenshot still "Pending Review" hasn't been rejected — it's just
    // waiting on a human to confirm what OCR couldn't read confidently. That
    // shouldn't block the TP from submitting; company can already see
    // pending items on their end (tp-today-orders.php) before invoicing, so
    // this is a checkpoint, not a hard requirement at submission time.
    function poEligibleTotal() {
        return poScreenshots
            .filter(function(s) { return (s.status === 'accepted' || s.status === 'pending_review') && s.detected_amount !== null; })
            .reduce(function(sum, s) { return sum + (parseFloat(s.detected_amount) || 0); }, 0);
    }

    // Some pending_review screenshots have no detected_amount at all (OCR
    // couldn't confidently read a single amount — e.g. "found more than one
    // possible amount"), so poEligibleTotal() undercounts them as ₹0 even
    // though a real upload attempt exists. Submission shouldn't hinge on
    // OCR having successfully parsed a number — only on the TP actually
    // having uploaded something that isn't rejected. Company still reviews
    // and can act on anything pending before ever invoicing.
    function poHasEligibleProof() {
        return poScreenshots.some(function(s) { return s.status === 'accepted' || s.status === 'pending_review'; });
    }

    function poGrandTotal() {
        var total = 0;
        poLines.forEach(function(l) {
            total += (l.qty * l.price) - l.discAmt;
        });
        return total;
    }

    function updatePoSummary() {
        var advBalance = parseFloat(document.getElementById('advBalanceVal').value) || 0;
        var total = poGrandTotal();
        var excess = total - advBalance;
        var eligible = poEligibleTotal();

        document.getElementById('poGrandTotal').textContent = '₹' + total.toFixed(2);

        var warning = document.getElementById('poExcessWarning');
        if (excess > 0.001 || poScreenshots.length > 0) {
            var excessAmt = Math.max(excess, 0);
            document.getElementById('poExcessAmount').textContent = excessAmt.toFixed(2);
            document.getElementById('poExcessAmount2').textContent = excessAmt.toFixed(2);
            warning.style.display = '';
            var pct = excessAmt > 0 ? Math.min(100, (eligible / excessAmt) * 100) : 100;
            document.getElementById('poProgressFill').style.width = pct + '%';
        } else {
            warning.style.display = 'none';
        }

        document.getElementById('poAcceptedTotal').textContent = eligible.toFixed(2);
        document.getElementById('poReadyNote').style.display = (excess > 0.001 && poHasEligibleProof()) ? '' : 'none';

        var uploadRow = document.getElementById('poScreenshotUploadRow');
        uploadRow.style.display = poScreenshots.length >= MAX_SCREENSHOTS ? 'none' : '';
    }

    function statusBadge(status) {
        if (status === 'accepted') return '<span class="apo-status-pill accepted">Accepted</span>';
        if (status === 'rejected') return '<span class="apo-status-pill rejected">Rejected</span>';
        return '<span class="apo-status-pill pending">Pending Review</span>';
    }

    function renderPoScreenshotList() {
        var list = document.getElementById('poScreenshotList');
        list.innerHTML = '';
        poScreenshots.forEach(function(s) {
            var detail = '';
            if (s.status === 'accepted') {
                detail = 'Amount: ₹' + s.detected_amount.toFixed(2) + ', Ref: ' + s.reference_number;
            } else if (s.reason) {
                detail = s.reason;
            }
            var isHeic = s.file_url && /\.hei[cf]$/i.test(s.file_url);
            var thumbHtml = '<div class="thumb-placeholder"><i class="material-icons-outlined" style="font-size:20px;">image</i></div>';
            if (s.file_url && !isHeic) {
                thumbHtml = '<a href="' + s.file_url + '" target="_blank"><img class="thumb" src="' + s.file_url + '"></a>';
            } else if (s.file_url && isHeic) {
                thumbHtml = '<a href="' + s.file_url + '" target="_blank" class="thumb-placeholder" title="Download HEIC"><i class="material-icons-outlined" style="font-size:20px;">description</i></a>';
            }

            var card = document.createElement('div');
            card.className = 'apo-screenshot-card';
            card.innerHTML =
                thumbHtml +
                '<div class="details">' + statusBadge(s.status) + '<div class="mt-1">' + detail + '</div></div>' +
                '<button type="button" class="apo-remove-chip" onclick="removePoScreenshot(' + s.id + ')">Remove</button>';
            list.appendChild(card);
        });
        updatePoSummary();
    }

    function isHeicFile(file) {
        var name = (file.name || '').toLowerCase();
        var type = (file.type || '').toLowerCase();
        return type === 'image/heic' || type === 'image/heif' ||
            name.endsWith('.heic') || name.endsWith('.heif');
    }

    // Safari/iOS decode HEIC natively at the OS level — createImageBitmap()
    // exposes that directly and handles camera profiles the pure-JS/WASM
    // libheif port (heic2any) sometimes can't (e.g. "ERR_LIBHEIF format not
    // supported" on some newer iPhones). Try this first; it's a no-op
    // rejection on browsers without native HEIC support (most non-Safari).
    function nativeHeicToJpeg(file) {
        if (typeof createImageBitmap !== 'function') return Promise.reject(new Error('createImageBitmap unsupported'));
        return createImageBitmap(file).then(function(bitmap) {
            var canvas = document.createElement('canvas');
            canvas.width = bitmap.width;
            canvas.height = bitmap.height;
            canvas.getContext('2d').drawImage(bitmap, 0, 0);
            return new Promise(function(resolve, reject) {
                canvas.toBlob(function(blob) {
                    if (blob) resolve(blob); else reject(new Error('canvas export failed'));
                }, 'image/jpeg', 0.9);
            });
        });
    }

    function uploadPoScreenshot() {
        var input = document.getElementById('po_screenshot_file');
        var statusEl = document.getElementById('poScreenshotStatus');
        statusEl.textContent = '';

        if (poScreenshots.length >= MAX_SCREENSHOTS) {
            alert('You can upload a maximum of ' + MAX_SCREENSHOTS + ' screenshots.');
            return;
        }
        var file = input.files[0];
        if (!file) { alert('Choose an image first.'); return; }
        var heic = isHeicFile(file);
        if (!heic && !file.type.startsWith('image/')) { alert('Please select an image file.'); return; }
        if (file.size > MAX_SCREENSHOT_BYTES) { alert('Image is too large. Maximum allowed size is 10 MB.'); return; }

        if (heic) {
            statusEl.textContent = 'Converting HEIC image…';
            var jpegName = file.name.replace(/\.(heic|heif)$/i, '.jpg');

            nativeHeicToJpeg(file)
                .catch(function(nativeErr) {
                    console.warn('Native HEIC decode unavailable, trying heic2any:', nativeErr);
                    return heic2any({ blob: file, toType: 'image/jpeg', quality: 0.9 })
                        .then(function(converted) {
                            return Array.isArray(converted) ? converted[0] : converted;
                        });
                })
                .then(function(jpegBlob) {
                    sendPoScreenshot(jpegBlob, jpegName);
                })
                .catch(function(err) {
                    console.error('HEIC conversion failed:', err);
                    var reason = (err && err.message) ? err.message : String(err);
                    if (confirm('Could not auto-convert this HEIC image (' + reason + ').\n\n' +
                        'Upload the original file anyway for manual review by the company? ' +
                        '(Or press Cancel to try a JPG/PNG instead.)')) {
                        sendPoScreenshot(file, file.name);
                    } else {
                        statusEl.textContent = 'Please try a JPG/PNG instead.';
                    }
                });
            return;
        }

        sendPoScreenshot(file, file.name);
    }

    function sendPoScreenshot(fileOrBlob, filename) {
        var input = document.getElementById('po_screenshot_file');
        var statusEl = document.getElementById('poScreenshotStatus');
        var btn = document.getElementById('poScreenshotUploadBtn');
        btn.disabled = true;
        statusEl.textContent = 'Uploading and verifying…';

        var formData = new FormData();
        formData.append('screenshot', fileOrBlob, filename);

        fetch('upload-po-screenshot.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                if (!data.success) {
                    statusEl.textContent = data.message || 'Upload failed.';
                    return;
                }
                poScreenshots.push(data.screenshot);
                poAcceptedTotal = parseFloat(data.accepted_total) || 0;
                input.value = '';
                statusEl.textContent = '';
                renderPoScreenshotList();
            })
            .catch(function() {
                btn.disabled = false;
                statusEl.textContent = 'Upload failed — please check your connection and try again.';
            });
    }

    function removePoScreenshot(id) {
        fetch('remove-po-screenshot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'screenshot_id=' + encodeURIComponent(id)
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) { alert(data.message || 'Could not remove screenshot.'); return; }
                poScreenshots = poScreenshots.filter(function(s) { return s.id !== id; });
                poAcceptedTotal = parseFloat(data.accepted_total) || 0;
                renderPoScreenshotList();
            })
            .catch(function() { alert('Could not remove screenshot — please check your connection.'); });
    }

    $(function() {
        if ($('#pr_select').length) {
            $('#pr_select').select2({ placeholder: 'Search product…', allowClear: true, width: '100%' });
        }
        renderPoScreenshotList();
        toggleDeliveryFields();
    });

    function validatePoLines() {
        if (poLines.length === 0) {
            alert('Add at least one product before submitting.');
            return false;
        }

        if (!document.getElementById('useDefaultDeliveryAddress').checked &&
            !document.getElementById('custom_delivery_line1').value.trim()) {
            alert('Enter a delivery address, or check "Use existing delivery address".');
            return false;
        }

        var advBalance = parseFloat(document.getElementById('advBalanceVal').value) || 0;
        var total = poGrandTotal();
        var excess = total - advBalance;

        if (excess > 0.001 && !poHasEligibleProof()) {
            alert('Your order total exceeds your available advance balance by ₹' + excess.toFixed(2) +
                '. Please upload at least one payment screenshot for the excess amount before submitting.');
            return false;
        }
        return true;
    }
    </script>
</body>
</html>
