<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/CpPurchaseOrderBalance.php';
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$title = "Edit Purchase Order";

cpEnsurePurchaseOrderTables($db_conn);

$po_id = (int)($_GET['po_id'] ?? 0);
if ($po_id < 1) {
    header("Location: manage-purchase-orders.php");
    exit;
}

// Only a still-waiting order can be edited — same rule delete-purchase-order.php
// uses: nothing has moved yet, so there's no stock/transfer to reconcile.
$poStmt = mysqli_prepare($db_conn,
    "SELECT id, product_type, order_date, status, use_default_delivery_address,
            custom_delivery_line1, custom_delivery_line2, custom_delivery_city,
            custom_delivery_district, custom_delivery_state, custom_delivery_country, custom_delivery_pincode
     FROM channel_partner_purchase_orders WHERE id = ? AND channel_partner_id = ?"
);
mysqli_stmt_bind_param($poStmt, "ii", $po_id, $Login_user_IDvl);
mysqli_stmt_execute($poStmt);
$po = mysqli_stmt_get_result($poStmt)->fetch_assoc();
mysqli_stmt_close($poStmt);

if (!$po) {
    $_SESSION['errorMessage'] = 'Purchase order not found.';
    header("Location: manage-purchase-orders.php");
    exit;
}
if ($po['status'] !== 'waiting') {
    $_SESSION['errorMessage'] = 'Only a still-waiting purchase order can be edited.';
    header("Location: manage-purchase-orders.php");
    exit;
}

$productType = $po['product_type'];
$order_date  = $po['order_date'];

// Existing lines, keyed in the same {pr_id, name, qty, price} shape the
// add-purchase-order.php JS cart already expects — re-skin add's own JS
// straight onto edit by just seeding poLines instead of starting empty.
$itemsStmt = mysqli_prepare($db_conn,
    "SELECT i.product_id, i.qty, i.price, p.productName
     FROM channel_partner_purchase_order_items i
     JOIN products p ON p.id = i.product_id
     WHERE i.po_id = ?"
);
mysqli_stmt_bind_param($itemsStmt, "i", $po_id);
mysqli_stmt_execute($itemsStmt);
$existingItems = mysqli_stmt_get_result($itemsStmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($itemsStmt);

$seedLines = array_map(fn($i) => [
    'pr_id' => (string)$i['product_id'],
    'name'  => $i['productName'],
    'qty'   => (int)$i['qty'],
    'price' => (float)$i['price'],
], $existingItems);

// Headroom excluding this PO's own current value — editing shouldn't count
// its own not-yet-changed amount against itself (see cpAvailableHeadroom()'s
// $excludePoId doc comment in shared/CpPurchaseOrderBalance.php).
$headroom = cpAvailableHeadroom($db_conn, (int)$Login_user_IDvl, $po_id);

$productList = [];
$resProd = mysqli_query($db_conn, "SELECT id, productName, mrp FROM products WHERE deleted_at IS NULL AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) AND " . tpProductTypeSqlFilter($productType, 'products') . " ORDER BY productName ASC");
if ($resProd) while ($p = mysqli_fetch_assoc($resProd)) $productList[] = $p;

$useDefaultDelivery = (bool)$po['use_default_delivery_address'];
$cpDeliveryStmt = mysqli_prepare($db_conn,
    "SELECT delivery_line1, delivery_line2, delivery_city, delivery_district, delivery_state, delivery_country, delivery_pincode
     FROM channel_partners WHERE id = ?"
);
mysqli_stmt_bind_param($cpDeliveryStmt, "i", $Login_user_IDvl);
mysqli_stmt_execute($cpDeliveryStmt);
$cpDeliveryAddress = mysqli_stmt_get_result($cpDeliveryStmt)->fetch_assoc() ?: [];
mysqli_stmt_close($cpDeliveryStmt);
$cpDeliveryAddressParts = array_filter([
    $cpDeliveryAddress['delivery_line1'] ?? '',
    $cpDeliveryAddress['delivery_line2'] ?? '',
    implode(', ', array_filter([$cpDeliveryAddress['delivery_city'] ?? '', $cpDeliveryAddress['delivery_district'] ?? ''])),
    implode(', ', array_filter([$cpDeliveryAddress['delivery_state'] ?? '', $cpDeliveryAddress['delivery_country'] ?? ''])),
    !empty($cpDeliveryAddress['delivery_pincode']) ? 'Pincode: ' . $cpDeliveryAddress['delivery_pincode'] : '',
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
        .apo-balance-card .explainer { font-size: 11.5px; color: #059669; margin-top: 2px; }

        .apo-delivery-default { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 16px; font-size: 13.5px; color: #374151; line-height: 1.6; }
        .apo-delivery-check { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; font-size: 14px; font-weight: 600; color: #374151; }
        .apo-delivery-check input { width: 16px; height: 16px; }
        .apo-delivery-fields { display: none; }
        .apo-delivery-fields.show { display: block; }

        .apo-add-panel { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 18px; margin-bottom: 6px; }
        .apo-add-panel .form-label { font-size: 11.5px; font-weight: 600; color: #6b7280; margin-bottom: 4px; }
        @media (max-width: 576px) {
            .apo-add-panel .row.g-2 > .col { flex: 0 0 50%; max-width: 50%; }
            .apo-add-panel .row.g-2 > .col-auto { flex: 0 0 100%; max-width: 100%; margin-top: 4px; }
            .apo-add-panel .row.g-2 > .col-auto #add { width: 100%; }
        }
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
        .apo-table-wrap tbody td { padding: 10px 14px !important; vertical-align: middle; font-size: 13.5px; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
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

        .btn-submit-po {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: #fff;
            font-weight: 600; padding: 11px 26px; border-radius: 10px; font-size: 14.5px; transition: all .2s;
        }
        .btn-submit-po:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(102,126,234,.4); color: #fff; }
    </style>
</head>
<body>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<?php if (isset($_SESSION['errorMessage'])) {
    $em = $_SESSION['errorMessage']; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>Swal.fire({ icon:'error', title:'Warning', text:'<?php echo $em; ?>', confirmButtonText:'OK' });</script>
<?php unset($_SESSION['errorMessage']); } ?>

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
                                    <h1 style="margin:0;"><?php echo $title;?>
                                        <?php [$badgeBg, $badgeFg] = tpProductTypeBadgeColors($productType); ?>
                                        <span style="font-size:13px;font-weight:700;padding:4px 12px;border-radius:20px;background:<?=$badgeBg?>;color:<?=$badgeFg?>;vertical-align:middle;"><?=htmlspecialchars(tpProductTypeLabel($productType))?></span>
                                    </h1>
                                </div>
                                <a href="manage-purchase-orders.php" class="btn-back-orders">
                                    <i class="material-icons" style="font-size:17px;">list_alt</i> My Purchase Orders
                                </a>
                            </div>
                        </div>
                        <br/>

                        <form action="edit-purchase-order-action.php" method="post" id="uploadForm" onsubmit="return validatePoLines();">
                            <input type="hidden" name="po_id" value="<?=(int)$po_id?>">

                            <div class="apo-balance-card">
                                <i class="material-icons-outlined">account_balance_wallet</i>
                                <div>
                                    <div class="label">Available Order Headroom</div>
                                    <div class="value">&#8377;<span id="headroomDisplay"><?=inr_format($headroom, 2)?></span></div>
                                    <div class="explainer">Excludes this order's own current amount — editing it doesn't count against itself.</div>
                                </div>
                            </div>

                            <div class="apo-card">
                                <div class="apo-card-title"><i class="material-icons-outlined">badge</i>Order Details</div>
                                <div class="apo-info-row">
                                    <div class="apo-info-chip">
                                        <label>Channel Partner</label>
                                        <div class="value"><?=htmlspecialchars($Login_user_name)?></div>
                                    </div>
                                    <div class="apo-info-chip">
                                        <label>Order Date</label>
                                        <div class="value"><?=date("d-m-Y", strtotime($order_date))?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="apo-card">
                                <div class="apo-card-title"><i class="material-icons-outlined">local_shipping</i>Delivery Address</div>

                                <label class="apo-delivery-check">
                                    <input type="checkbox" id="useDefaultDeliveryAddress" name="use_default_delivery_address" value="1" <?=$useDefaultDelivery ? 'checked' : ''?> onchange="toggleDeliveryFields()">
                                    Use existing delivery address
                                </label>

                                <div id="defaultDeliveryPreview" class="apo-delivery-default">
                                    <?php if (!empty($cpDeliveryAddressParts)): ?>
                                        <?=implode('<br/>', array_map('htmlspecialchars', $cpDeliveryAddressParts))?>
                                    <?php else: ?>
                                        <span class="text-muted">No delivery address on file. Uncheck above to enter one.</span>
                                    <?php endif; ?>
                                </div>

                                <div id="customDeliveryFields" class="apo-delivery-fields">
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Address Line 1</label>
                                            <input type="text" name="custom_delivery_line1" id="custom_delivery_line1" class="form-control" placeholder="Address Line 1" value="<?=htmlspecialchars($po['custom_delivery_line1'] ?? '')?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Address Line 2</label>
                                            <input type="text" name="custom_delivery_line2" class="form-control" placeholder="Address Line 2" value="<?=htmlspecialchars($po['custom_delivery_line2'] ?? '')?>">
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-3">
                                            <label class="form-label">City</label>
                                            <input type="text" name="custom_delivery_city" class="form-control" placeholder="City" value="<?=htmlspecialchars($po['custom_delivery_city'] ?? '')?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">District</label>
                                            <input type="text" name="custom_delivery_district" class="form-control" placeholder="District" value="<?=htmlspecialchars($po['custom_delivery_district'] ?? '')?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">State</label>
                                            <input type="text" name="custom_delivery_state" class="form-control" placeholder="State" value="<?=htmlspecialchars($po['custom_delivery_state'] ?? '')?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Pincode</label>
                                            <input type="text" name="custom_delivery_pincode" class="form-control" placeholder="Pincode" value="<?=htmlspecialchars($po['custom_delivery_pincode'] ?? '')?>">
                                        </div>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label">Country</label>
                                            <input type="text" name="custom_delivery_country" class="form-control" placeholder="Country" value="<?=htmlspecialchars($po['custom_delivery_country'] ?? 'India')?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (empty($productList)): ?>
                            <div class="alert alert-warning">No products available to order.</div>
                            <?php else: ?>
                            <div class="apo-card">
                                <div class="apo-card-title"><i class="material-icons-outlined">add_shopping_cart</i>Products</div>

                                <div class="apo-add-panel">
                                    <label class="form-label">Select Product</label>
                                    <select class="form-control mb-2" id="pr_select" onchange="showPoPrice(this.value)">
                                        <option value=""></option>
                                        <?php foreach ($productList as $p): ?>
                                        <option value="<?=$p['id']?>" data-price="<?=htmlspecialchars($p['mrp'])?>"><?=htmlspecialchars($p['productName'])?></option>
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
                                        <div class="col-auto">
                                            <button type="button" class="btn" id="add" onclick="addPoLine()"><i class="material-icons" style="font-size:16px;vertical-align:middle;">add</i> Add</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="apo-table-wrap">
                                <table class="table table-bordered mb-0">
                                    <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Price</th><th>Total</th><th></th></tr></thead>
                                    <tbody id="poItemsBody">
                                        <tr id="poItemsEmptyRow"><td colspan="6" class="text-center text-muted">No products added yet.</td></tr>
                                    </tbody>
                                </table>
                                </div>
                                <div id="hiddenInputsHolder"></div>

                                <div class="apo-summary-row">
                                    <span>Order Total: <span class="amt" id="poGrandTotal">&#8377;0.00</span></span>
                                    <span style="color:#d1d5db;">|</span>
                                    <span>Available Order Headroom: <span class="amt">&#8377;<span id="headroomDisplay2"><?=inr_format($headroom, 2)?></span></span></span>
                                </div>
                            </div>

                            <div id="poExcessWarning" class="apo-excess-card" style="display:none;">
                                <div class="apo-excess-title">Your order total exceeds your available headroom by &#8377;<span id="poExcessAmount">0.00</span>. Remove items or wait for your held stock to sell before saving.</div>
                            </div>

                            <button type="submit" name="submit_po" id="poSubmitBtn" onclick="return confirm('Save changes to this purchase order?');" class="btn-submit-po">
                                <i class="material-icons" style="vertical-align:middle;font-size:18px;">save</i> Save Changes
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

    <script>
    function toggleDeliveryFields() {
        var useDefault = document.getElementById('useDefaultDeliveryAddress').checked;
        document.getElementById('defaultDeliveryPreview').style.display = useDefault ? '' : 'none';
        document.getElementById('customDeliveryFields').classList.toggle('show', !useDefault);
    }

    // Seeded from the order's existing items — same cart model
    // add-purchase-order.php's JS uses, just pre-filled instead of empty.
    var poLines = <?=json_encode($seedLines)?>;

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
    }

    function addPoLine() {
        var sel   = document.getElementById('pr_select');
        var prId  = sel.value;
        var prName = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
        var qty   = parseInt(document.getElementById('po_qty').value) || 0;
        var price = parseFloat(document.getElementById('po_price').value) || 0;

        if (!prId) { alert('Select a product.'); return; }
        if (qty <= 0) { alert('Enter a valid qty.'); return; }
        for (var i = 0; i < poLines.length; i++) {
            if (poLines[i].pr_id === prId) { alert('That product is already added.'); return; }
        }

        poLines.push({ pr_id: prId, name: prName, qty: qty, price: price });
        renderPoLines();

        $(sel).val('').trigger('change');
        document.getElementById('po_qty').value = '';
        document.getElementById('po_price').value = '';
        document.getElementById('po_total').value = '';
    }

    function removePoLine(idx) {
        poLines.splice(idx, 1);
        renderPoLines();
    }

    // Qty is edited in place (the table row's own input), so only that row's
    // total + the hidden qty[] input + the grand total need refreshing —
    // re-rendering the whole table here would steal focus mid-keystroke.
    function updatePoLineQty(idx, newQty) {
        var qty = parseInt(newQty) || 0;
        if (qty < 1) qty = 1;
        poLines[idx].qty = qty;

        var totalEl = document.getElementById('poLineTotal' + idx);
        if (totalEl) totalEl.textContent = (qty * poLines[idx].price).toFixed(2);

        var hiddenQty = document.querySelectorAll('#hiddenInputsHolder input[name="qty[]"]')[idx];
        if (hiddenQty) hiddenQty.value = qty;

        updatePoSummary();
    }

    function renderPoLines() {
        var tbody = document.getElementById('poItemsBody');
        tbody.innerHTML = '';
        if (poLines.length === 0) {
            tbody.innerHTML = '<tr id="poItemsEmptyRow"><td colspan="6" class="text-center text-muted">No products added yet.</td></tr>';
        } else {
            poLines.forEach(function(l, idx) {
                var total = (l.qty * l.price).toFixed(2);
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<th>' + (idx + 1) + '</th>' +
                    '<td>' + l.name + '</td>' +
                    '<td><input type="number" min="1" class="form-control form-control-sm" style="width:80px;" value="' + l.qty + '" onchange="updatePoLineQty(' + idx + ', this.value)" oninput="updatePoLineQty(' + idx + ', this.value)"></td>' +
                    '<td>₹' + parseFloat(l.price).toFixed(2) + '</td>' +
                    '<td><strong>₹<span id="poLineTotal' + idx + '">' + total + '</span></strong></td>' +
                    '<td><button type="button" class="apo-remove-chip" onclick="removePoLine(' + idx + ')">Remove</button></td>';
                tbody.appendChild(tr);
            });
        }

        var holder = document.getElementById('hiddenInputsHolder');
        holder.innerHTML = '';
        poLines.forEach(function(l) {
            holder.innerHTML +=
                '<input type="hidden" name="pr_id[]" value="' + l.pr_id + '">' +
                '<input type="hidden" name="qty[]" value="' + l.qty + '">';
        });

        updatePoSummary();
    }

    function poGrandTotal() {
        var total = 0;
        poLines.forEach(function(l) { total += l.qty * l.price; });
        return total;
    }

    var headroom = <?=json_encode($headroom)?>;
    function updatePoSummary() {
        var total = poGrandTotal();
        document.getElementById('poGrandTotal').textContent = '₹' + total.toFixed(2);
        var warning = document.getElementById('poExcessWarning');
        var excess = total - headroom;
        if (excess > 0.001) {
            document.getElementById('poExcessAmount').textContent = excess.toFixed(2);
            warning.style.display = '';
        } else {
            warning.style.display = 'none';
        }
    }

    $(function() {
        if ($('#pr_select').length) {
            $('#pr_select').select2({ placeholder: 'Search product…', allowClear: true, width: '100%' });
        }
        toggleDeliveryFields();
        renderPoLines();
    });

    function validatePoLines() {
        if (poLines.length === 0) {
            alert('Add at least one product before saving.');
            return false;
        }
        if (!document.getElementById('useDefaultDeliveryAddress').checked &&
            !document.getElementById('custom_delivery_line1').value.trim()) {
            alert('Enter a delivery address, or check "Use existing delivery address".');
            return false;
        }
        var total = poGrandTotal();
        if (total - headroom > 0.001) {
            alert('Your order total exceeds your available headroom by ₹' + (total - headroom).toFixed(2) + '. Remove items or wait for stock to sell before saving.');
            return false;
        }
        return true;
    }
    </script>
</body>
</html>
