<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support).
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$products = $db_conn->query("SELECT id, productName, pieces_per_pack FROM products ORDER BY productName ASC")->fetch_all(MYSQLI_ASSOC);
$vendors  = $db_conn->query("SELECT id, vendor_name FROM neksomo_vendors WHERE is_active = 1 ORDER BY vendor_name ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase from Manufacturer : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">

    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
        .form-section { background:white; border:2px solid #e5e7eb; border-radius:14px; padding:30px 35px; margin-bottom:22px; box-shadow:0 1px 4px rgba(0,0,0,0.06); }
        .section-header { color:#475569; font-size:13px; font-weight:600; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:24px; display:flex; align-items:center; gap:10px; padding-bottom:14px; border-bottom:2px solid #f1f5f9; }
        .section-header i { color:#2563eb; font-size:19px; }
        .field-hint { font-size:12px; color:#94a3b8; margin-top:7px; }
        .product-add-section { background:#f8fafc; border:2px dashed #cbd5e1; border-radius:12px; padding:20px; margin-top:20px; }
        .product-add-grid { display:grid; grid-template-columns:2.5fr 1fr 1fr auto; gap:14px; align-items:end; }
        .input-group-modern { display:flex; flex-direction:column; }
        .input-group-modern label { font-size:12px; color:#64748b; font-weight:600; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.4px; }
        .input-group-modern .form-control { border:2px solid #e5e7eb; border-radius:8px; padding:10px 12px; font-size:14px; }
        .btn-add-product { background:#10b981; color:white; border:none; padding:11px 20px; border-radius:8px; font-weight:500; display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-family:'Poppins',sans-serif; font-size:14px; white-space:nowrap; }
        .btn-add-product:hover { background:#059669; }
        .table-modern { background:white; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-top:20px; border:1px solid #f1f5f9; }
        .table-modern table { margin:0; width:100%; }
        .table-modern thead { background:#f8fafc; }
        .table-modern thead th { color:#475569; font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; padding:14px 16px; border-bottom:2px solid #e5e7eb; white-space:nowrap; }
        .table-modern tbody td { padding:13px 16px; vertical-align:middle; border-bottom:1px solid #f1f5f9; color:#1e293b; font-size:14px; }
        .table-modern tbody tr:last-child td { border-bottom:none; }
        .table-modern .empty-row td { text-align:center; padding:40px; color:#94a3b8; }
        .row-num { width:28px; height:28px; background:#f1f5f9; color:#64748b; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; }
        .badge-remove { background:#fee2e2; color:#991b1b; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:3px; }
        .badge-remove:hover { background:#fecaca; }
        .summary-card { background:white; border:2px solid #e5e7eb; border-radius:12px; padding:25px; margin-top:20px; }
        .summary-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9; font-size:14px; }
        .summary-row:last-child { border-bottom:none; }
        .summary-label { color:#64748b; font-weight:500; }
        .summary-value { font-weight:600; color:#1e293b; }
        .summary-total { font-size:20px; font-weight:700; color:#10b981; }
        @media (max-width:768px) { .product-add-grid { grid-template-columns:1fr 1fr; } .product-add-grid .input-group-modern:first-child { grid-column:1/-1; } .product-add-grid .input-group-modern:last-child { grid-column:1/-1; } }
        @keyframes spin { to { transform:rotate(360deg); } }
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
            <?php include("validate-scripts.php"); ?>
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble">
                                        <tr>
                                        <td>Purchase from Manufacturer</td>
                                        <td><a href="neksomo-manufacturer-purchase-manage" title="Manage Purchases">&#9776;</a></td>
                                        </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($_REQUEST['addesuccess'])) { ?><div class="alert alert-success">Purchase recorded and stock updated.</div><?php } ?>
                        <?php if (isset($_REQUEST['error'])): $err = $_REQUEST['error']; ?>
                            <div class="alert alert-danger">
                                <?php if ($err === 'duplicate'): ?>
                                    Invoice number <strong><?php echo htmlspecialchars($_REQUEST['inv'] ?? ''); ?></strong> already exists. Please enter a different invoice number.
                                <?php elseif ($err === 'missing'): ?>
                                    Please fill in all required fields.
                                <?php elseif ($err === 'noproducts'): ?>
                                    Please add at least one product with a valid quantity and cost.
                                <?php else: ?>
                                    Something went wrong. Please try again.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form action="neksomo-manufacturer-purchase-action.php" method="post" id="purchaseForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="add-record" value="1">
                        <div id="hiddenProductInputs"></div>

                        <div class="form-section">
                            <div class="section-header"><i class="material-icons">edit_document</i>Purchase Details</div>
                            <p class="text-muted" style="font-size:13px;">
                                This records a real purchase and adds directly to Neksomo's on-hand stock
                                (same effect as Add Input Stock) — quantity is in packs.
                            </p>
                            <div class="row g-4 align-items-start">

                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">Vendor <span class="required text-danger">*</span></label>
                                    <select required name="vendor_id" id="vendorSelect" class="form-control">
                                        <option value="" hidden>Select Vendor</option>
                                        <?php foreach ($vendors as $v): ?>
                                        <option value="<?php echo (int)$v['id']; ?>"><?php echo htmlspecialchars($v['vendor_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="field-hint"><a href="neksomo-vendor-add.php" target="_blank">+ Add New Vendor</a> (opens in a new tab)</div>
                                </div>

                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">Invoice Number <span class="required text-danger">*</span></label>
                                    <input type="text" name="inv_number" id="invNumberInput" class="form-control"
                                           autocomplete="off" required onkeypress="restrictSpecialChars(event)"
                                           value="<?php echo (isset($_GET['error']) && $_GET['error'] === 'duplicate') ? htmlspecialchars($_GET['inv'] ?? '') : ''; ?>"
                                           placeholder="Enter invoice number">
                                    <div id="invNumberHint" style="margin-top:6px;font-size:12.5px;"></div>
                                </div>

                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">Purchase Date <span class="required text-danger">*</span></label>
                                    <input type="date" required name="purchase_date" id="purchaseDate" class="form-control"
                                           value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                                    <div class="field-hint">Cannot be a future date</div>
                                </div>

                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header" style="border:none;padding-bottom:0;margin-bottom:15px;"><i class="material-icons">add_shopping_cart</i>Add Products</div>
                            <div class="product-add-section">
                                <div class="product-add-grid">
                                    <div class="input-group-modern">
                                        <label>Product <span style="color:#ef4444;">*</span></label>
                                        <select id="productSelect" class="form-control">
                                            <option value="">— Select Product —</option>
                                            <?php foreach ($products as $p): ?>
                                            <option value="<?php echo (int)$p['id']; ?>" data-pieces-per-pack="<?php echo (int)$p['pieces_per_pack']; ?>"><?php echo htmlspecialchars($p['productName']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="input-group-modern">
                                        <label>Qty (Pieces) <span style="color:#ef4444;">*</span></label>
                                        <input type="number" id="qtyInput" class="form-control" min="1" placeholder="0">
                                        <div class="field-hint" id="packSizeHint" style="margin-top:4px;"></div>
                                    </div>
                                    <div class="input-group-modern">
                                        <label>Cost/Piece (₹) <span style="color:#ef4444;">*</span></label>
                                        <input type="number" id="costInput" class="form-control" min="0" step="0.01" placeholder="0.00">
                                    </div>
                                    <div class="input-group-modern" style="align-items:flex-end;">
                                        <button type="button" class="btn-add-product" id="addProductBtn" onclick="addProduct()">
                                            <i class="material-icons">add</i> Add
                                        </button>
                                    </div>
                                </div>
                                <div id="addError" style="margin-top:10px;display:none;"></div>
                            </div>

                            <div class="table-modern">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>#</th><th>Product</th><th>Qty (Pieces)</th><th>Cost/Piece (₹)</th><th>Line Total (₹)</th><th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="productBody">
                                        <tr class="empty-row" id="emptyRow">
                                            <td colspan="6">
                                                <i class="material-icons" style="font-size:40px;display:block;margin-bottom:10px;color:#cbd5e1;">inventory_2</i>
                                                No products added yet
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="summary-card">
                                <div class="row">
                                    <div class="col-lg-5 ms-auto">
                                        <div class="summary-row">
                                            <span class="summary-label">Total Items</span>
                                            <span class="summary-value" id="summaryItems">0</span>
                                        </div>
                                        <div class="summary-row">
                                            <span class="summary-label">Total Quantity (Pieces)</span>
                                            <span class="summary-value" id="summaryQty">0</span>
                                        </div>
                                        <div class="summary-row" style="border-top:2px solid #e5e7eb;margin-top:4px;padding-top:12px;">
                                            <span class="summary-label" style="font-size:16px;font-weight:600;color:#1e293b;">Grand Total</span>
                                            <span class="summary-total" id="grandTotal">₹0.00</span>
                                        </div>
                                        <div style="margin-top:20px;">
                                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                                <i class="material-icons">check_circle</i> Submit Purchase
                                            </button>
                                            <a href="neksomo-manufacturer-purchase-manage" class="btn btn-secondary ms-2">Cancel</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script>
    (function ($) {
        var purchaseItems  = [];
        var invNumberOk    = false;
        var invNumberTimer = null;

        $('#invNumberInput').on('input', function () {
            var val = $(this).val().trim();
            invNumberOk = false;
            clearTimeout(invNumberTimer);
            if (!val) { $('#invNumberHint').html(''); updateSummary(); return; }
            $('#invNumberHint').html('<span style="color:#94a3b8;">Checking…</span>');
            invNumberTimer = setTimeout(function () {
                $.getJSON('neksomo-check-invoice-number.php', { q: val }, function (res) {
                    if (res.duplicate) {
                        invNumberOk = false;
                        $('#invNumberHint').html('<span style="color:#dc2626;"><i class="material-icons" style="font-size:14px;vertical-align:middle;">error</i> Invoice number already exists.</span>');
                    } else {
                        invNumberOk = true;
                        $('#invNumberHint').html('<span style="color:#059669;"><i class="material-icons" style="font-size:14px;vertical-align:middle;">check_circle</i> Available</span>');
                    }
                    updateSummary();
                }).fail(function () {
                    invNumberOk = true;
                    updateSummary();
                });
            }, 400);
        });
        if ($('#invNumberInput').val().trim()) { $('#invNumberInput').trigger('input'); }

        function fmtAmt(n) {
            return parseFloat(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        $('#productSelect').on('change', function () {
            var $opt = $(this).find('option:selected');
            var pph  = parseInt($opt.data('pieces-per-pack')) || 0;
            $('#packSizeHint').text(pph > 1 ? ('Pack size: ' + pph + ' pcs — any quantity is accepted; leftover pieces carry forward to the next purchase.') : '');
        });

        window.addProduct = function () {
            hideAddError();
            var $opt        = $('#productSelect').find('option:selected');
            var product_id  = parseInt($('#productSelect').val());
            var name        = $opt.text().trim();
            var qty         = parseInt($('#qtyInput').val()) || 0;
            var cost        = parseFloat($('#costInput').val());

            if (!product_id)        { showAddError('Please select a product.'); return; }
            if (qty < 1)             { showAddError('Quantity must be at least 1.'); return; }
            if (isNaN(cost) || cost < 0) { showAddError('Please enter a valid cost per piece.'); return; }
            if (purchaseItems.find(function (i) { return i.product_id === product_id; })) {
                showAddError('This product is already added.'); return;
            }

            var amount = parseFloat((qty * cost).toFixed(2));
            purchaseItems.push({ product_id: product_id, name: name, qty: qty, cost: cost, amount: amount });
            renderTable();
            resetAddForm();
        };

        window.removeProduct = function (idx) {
            purchaseItems.splice(idx, 1);
            renderTable();
        };

        function renderTable() {
            var $body = $('#productBody').empty();
            if (!purchaseItems.length) {
                $body.html('<tr class="empty-row"><td colspan="6"><i class="material-icons" style="font-size:40px;display:block;margin-bottom:10px;color:#cbd5e1;">inventory_2</i>No products added yet</td></tr>');
                updateSummary(); return;
            }
            $.each(purchaseItems, function (i, item) {
                $body.append(
                    '<tr>' +
                    '<td><span class="row-num">' + (i + 1) + '</span></td>' +
                    '<td><strong>' + escHtml(item.name) + '</strong></td>' +
                    '<td>' + item.qty + '</td>' +
                    '<td>₹' + item.cost.toFixed(2) + '</td>' +
                    '<td><strong>₹' + item.amount.toFixed(2) + '</strong></td>' +
                    '<td><button type="button" class="badge-remove" onclick="removeProduct(' + i + ')"><i class="material-icons" style="font-size:14px;vertical-align:middle;">delete</i> Remove</button></td>' +
                    '</tr>'
                );
            });
            updateSummary();
            buildHiddenInputs();
        }

        function updateSummary() {
            var total = 0, qty = 0;
            $.each(purchaseItems, function (_, item) { total += item.amount; qty += item.qty; });
            total = parseFloat(total.toFixed(2));

            $('#summaryItems').text(purchaseItems.length);
            $('#summaryQty').text(qty);
            $('#grandTotal').text('₹' + fmtAmt(total));

            var invNumberFilled = $('#invNumberInput').val().trim() !== '';
            $('#submitBtn').prop('disabled', purchaseItems.length === 0 || !invNumberFilled || !invNumberOk);
        }

        function buildHiddenInputs() {
            var html = '';
            $.each(purchaseItems, function (_, item) {
                html += '<input type="hidden" name="product_id[]"      value="' + item.product_id + '">';
                html += '<input type="hidden" name="quantity_pieces[]" value="' + item.qty + '">';
                html += '<input type="hidden" name="cost_per_piece[]"  value="' + item.cost + '">';
            });
            $('#hiddenProductInputs').html(html);
        }

        $('#purchaseForm').on('submit', function (e) {
            if (!purchaseItems.length) { e.preventDefault(); alert('Please add at least one product.'); return; }
            if (!$('#invNumberInput').val().trim()) { e.preventDefault(); alert('Please enter an invoice number.'); return; }
            if (!invNumberOk) { e.preventDefault(); alert('This invoice number already exists. Please enter a different one.'); return; }
            buildHiddenInputs();
            $('#submitBtn').prop('disabled', true).html('<i class="material-icons" style="animation:spin 1s linear infinite;font-size:18px;">refresh</i> Submitting…');
        });

        function resetAddForm() {
            $('#productSelect').val('');
            $('#qtyInput').val('');
            $('#costInput').val('');
            $('#packSizeHint').text('');
            hideAddError();
        }

        function showAddError(msg) {
            $('#addError').html('<div class="alert alert-danger mb-0" style="font-size:13px;padding:10px 14px;"><i class="material-icons" style="vertical-align:middle;font-size:16px;">error</i> ' + msg + '</div>').show();
        }
        function hideAddError() { $('#addError').hide().empty(); }
        function escHtml(str) { return $('<div>').text(str).html(); }

        updateSummary();
    }(jQuery));
    </script>
</body>

</html>
