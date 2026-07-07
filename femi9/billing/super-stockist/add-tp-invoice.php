<?php
include("checksession.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Only TPs onboarded by this SS
$tp_stmt = $db_conn->prepare("SELECT id, tp_id, name, mobile FROM territory_partners WHERE is_active=1 AND onboard_ss_id=? ORDER BY name");
$tp_stmt->bind_param("s", $Login_user_IDvl);
$tp_stmt->execute();
$tps = $tp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$tp_stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add TP Invoice : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .alert { border-radius:10px; border:none; padding:15px 20px; margin-bottom:20px; }
        .alert-success { background:#d1fae5; color:#065f46; border-left:4px solid #10b981; }
        .alert-danger  { background:#fee2e2; color:#991b1b; border-left:4px solid #ef4444; }
        .alert-warning { background:#fef3c7; color:#92400e; border-left:4px solid #f59e0b; }
        .page-title-modern { background:white; border:2px solid #e5e7eb; border-radius:12px; padding:20px 25px; margin-bottom:25px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
        .page-title-modern h1 { color:#1e293b; font-size:22px; font-weight:700; margin:0; display:flex; align-items:center; gap:12px; }
        .page-title-modern h1 i { color:#2563eb; font-size:26px; }
        .page-title-modern .menu-link { background:#2563eb; color:white; width:40px; height:40px; border-radius:8px; display:flex; align-items:center; justify-content:center; text-decoration:none; font-size:20px; }
        .page-title-modern .menu-link:hover { background:#1d4ed8; color:white; }
        .form-section { background:white; border:2px solid #e5e7eb; border-radius:14px; padding:30px 35px; margin-bottom:22px; box-shadow:0 1px 4px rgba(0,0,0,0.06); }
        .section-header { color:#475569; font-size:13px; font-weight:600; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:24px; display:flex; align-items:center; gap:10px; padding-bottom:14px; border-bottom:2px solid #f1f5f9; }
        .section-header i { color:#2563eb; font-size:19px; }
        .form-label { font-weight:600; color:#374151; margin-bottom:10px; font-size:13.5px; display:block; }
        .form-label .required { color:#ef4444; margin-left:3px; }
        .form-control, .form-select { border:2px solid #e5e7eb; border-radius:9px; padding:11px 15px; font-size:14px; font-family:'Poppins',sans-serif; transition:all 0.3s ease; }
        .form-control:focus, .form-select:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.1); }
        .field-hint { font-size:12px; color:#94a3b8; margin-top:7px; }
        .balance-panel { display:flex; align-items:center; gap:14px; background:#f0fdf4; border:2px solid #bbf7d0; border-radius:10px; padding:14px 18px; margin-top:18px; }
        .balance-panel.warn { background:#fff7ed; border-color:#fed7aa; }
        .balance-panel.danger { background:#fef2f2; border-color:#fecaca; }
        .balance-panel.loading { background:#f8fafc; border-color:#e2e8f0; }
        .balance-panel-icon { font-size:28px; flex-shrink:0; }
        .balance-panel.ok .balance-panel-icon     { color:#10b981; }
        .balance-panel.warn .balance-panel-icon   { color:#f59e0b; }
        .balance-panel.danger .balance-panel-icon { color:#ef4444; }
        .balance-panel.loading .balance-panel-icon { color:#94a3b8; }
        .balance-panel-body { flex:1; }
        .balance-panel-label { font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px; }
        .balance-panel-amount { font-size:22px; font-weight:700; }
        .balance-panel.ok .balance-panel-amount     { color:#059669; }
        .balance-panel.warn .balance-panel-amount   { color:#d97706; }
        .balance-panel.danger .balance-panel-amount { color:#dc2626; }
        .balance-panel.loading .balance-panel-amount { color:#94a3b8; }
        .balance-panel-note { font-size:12.5px; color:#64748b; margin-top:3px; }
        .balance-panel-action { flex-shrink:0; }
        .balance-panel-action a { display:inline-flex; align-items:center; gap:5px; background:#2563eb; color:white; border-radius:7px; padding:7px 14px; font-size:13px; font-weight:500; text-decoration:none; }
        .balance-panel-action a:hover { background:#1d4ed8; color:white; }
        .product-add-section { background:#f8fafc; border:2px dashed #cbd5e1; border-radius:12px; padding:20px; margin-top:20px; }
        .product-add-grid { display:grid; grid-template-columns:2.5fr 1fr 1fr 1fr auto; gap:14px; align-items:end; }
        .input-group-modern { display:flex; flex-direction:column; }
        .input-group-modern label { font-size:12px; color:#64748b; font-weight:600; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.4px; }
        .input-group-modern .form-control { border:2px solid #e5e7eb; border-radius:8px; padding:10px 12px; font-size:14px; }
        .btn-primary { background:#2563eb; border:none; padding:10px 20px; border-radius:8px; font-weight:500; display:inline-flex; align-items:center; gap:6px; transition:all 0.3s ease; font-size:14px; font-family:'Poppins',sans-serif; }
        .btn-primary:hover { background:#1d4ed8; }
        .btn-primary:disabled { opacity:0.5; cursor:not-allowed; }
        .btn-add-product { background:#10b981; color:white; border:none; padding:11px 20px; border-radius:8px; font-weight:500; display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-family:'Poppins',sans-serif; font-size:14px; white-space:nowrap; }
        .btn-add-product:hover { background:#059669; }
        .btn-add-product:disabled { opacity:0.5; cursor:not-allowed; }
        .table-modern { background:white; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-top:20px; border:1px solid #f1f5f9; }
        .table-modern table { margin:0; width:100%; }
        .table-modern thead { background:#f8fafc; }
        .table-modern thead th { color:#475569; font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; padding:14px 16px; border-bottom:2px solid #e5e7eb; white-space:nowrap; }
        .table-modern tbody td { padding:13px 16px; vertical-align:middle; border-bottom:1px solid #f1f5f9; color:#1e293b; font-size:14px; }
        .table-modern tbody tr:last-child td { border-bottom:none; }
        .table-modern .empty-row td { text-align:center; padding:40px; color:#94a3b8; }
        .row-num { width:28px; height:28px; background:#f1f5f9; color:#64748b; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; }
        .avail-chip { display:inline-block; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:600; background:#d1fae5; color:#065f46; }
        .avail-chip.none { background:#f1f5f9; color:#94a3b8; }
        .badge-remove { background:#fee2e2; color:#991b1b; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:3px; }
        .badge-remove:hover { background:#fecaca; }
        .invoice-summary-card { background:white; border:2px solid #e5e7eb; border-radius:12px; padding:25px; margin-top:20px; }
        .summary-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9; font-size:14px; }
        .summary-row:last-child { border-bottom:none; }
        .summary-label { color:#64748b; font-weight:500; }
        .summary-value { font-weight:600; color:#1e293b; }
        .summary-total { font-size:20px; font-weight:700; color:#10b981; }
        .invoice-info-actions { margin-top:20px; padding-top:20px; border-top:2px solid #f1f5f9; }
        .select2-container--default .select2-selection--single { border:2px solid #e5e7eb; border-radius:9px; height:auto; padding:11px 15px; font-size:14px; font-family:'Poppins',sans-serif; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:1.5; padding:0; color:#1e293b; }
        .select2-container--default .select2-selection--single .select2-selection__placeholder { color:#94a3b8; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height:100%; top:0; right:10px; }
        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.1); outline:none; }
        .select2-dropdown { border:2px solid #e5e7eb; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.12); font-family:'Poppins',sans-serif; font-size:14px; }
        .select2-search--dropdown { padding:8px; }
        .select2-search--dropdown .select2-search__field { border:2px solid #e5e7eb; border-radius:6px; padding:8px 10px; font-family:'Poppins',sans-serif; }
        .select2-results__option { padding:9px 14px; }
        .select2-container { width:100% !important; }
        @media (max-width:992px) { .product-add-grid { grid-template-columns:1fr 1fr 1fr 1fr; } .product-add-grid .input-group-modern:first-child { grid-column:1/-1; } .product-add-grid .input-group-modern:last-child { grid-column:1/-1; } }
        @media (max-width:576px) { .product-add-grid { grid-template-columns:1fr; } }
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
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">

                    <?php if (isset($_GET['error'])): ?>
                    <?php $err = $_GET['error']; ?>
                    <div class="alert alert-danger">
                        <i class="material-icons" style="vertical-align:middle;margin-right:8px;">error</i>
                        <?php if ($err === 'insufficient'): ?>Insufficient stock for one or more products.
                        <?php elseif ($err === 'missing'): ?>Please fill in all required fields.
                        <?php elseif ($err === 'noproducts'): ?>Please add at least one product with a valid quantity.
                        <?php elseif ($err === 'nobalance'): ?>
                            Insufficient advance balance. Required: <strong>₹<?php echo htmlspecialchars($_GET['need'] ?? ''); ?></strong>, Available: <strong>₹<?php echo htmlspecialchars($_GET['have'] ?? '0.00'); ?></strong>.
                        <?php elseif ($err === 'duplicate'): ?>
                            Invoice number <strong><?php echo htmlspecialchars($_GET['inv'] ?? ''); ?></strong> already exists. Please enter a different invoice number.
                        <?php else: ?>An error occurred. Please try again.<?php if (!empty($_GET['msg'])): ?> <small>(<?php echo htmlspecialchars(substr($_GET['msg'],0,100)); ?>)</small><?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="page-title-modern">
                        <h1><i class="material-icons">receipt_long</i> Add TP Invoice</h1>
                        <a href="manage-tp-invoices" class="menu-link" title="Manage TP Invoices">
                            <i class="material-icons">list</i>
                        </a>
                    </div>

                    <form action="tp-invoice-action" method="post" id="invoiceForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="insert-tp-invoice">
                        <div id="hiddenProductInputs"></div>

                        <div class="form-section">
                            <div class="section-header"><i class="material-icons">edit_document</i>Invoice Details</div>
                            <div class="row g-4 align-items-start">

                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">Territory Partner <span class="required">*</span></label>
                                    <select name="tp_id" id="tpSelect" class="form-control" required>
                                        <option value=""></option>
                                        <?php foreach ($tps as $tp): ?>
                                            <option value="<?php echo $tp['id']; ?>"
                                                data-tpid="<?php echo htmlspecialchars($tp['tp_id']); ?>"
                                                data-mobile="<?php echo htmlspecialchars($tp['mobile'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($tp['name']); ?> (<?php echo htmlspecialchars($tp['tp_id']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="field-hint">Search by name, TP ID or mobile number</div>
                                    <?php if (empty($tps)): ?>
                                        <div class="alert alert-warning mt-2" style="font-size:13px;padding:10px 14px;">
                                            No active territory partners found. Please contact the company to assign a territory partner.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-lg-4 col-md-4">
                                    <label class="form-label">Invoice Date <span class="required">*</span></label>
                                    <input type="date" name="invoice_date" id="invoiceDate" class="form-control"
                                           value="<?php echo date('Y-m-d'); ?>"
                                           max="<?php echo date('Y-m-d'); ?>" required>
                                    <div class="field-hint">Cannot be a future date</div>
                                </div>

                            </div>

                            <!-- Advance Balance Panel -->
                            <div id="balancePanel" style="display:none;">
                                <div class="balance-panel loading" id="balancePanelInner">
                                    <i class="material-icons balance-panel-icon">account_balance_wallet</i>
                                    <div class="balance-panel-body">
                                        <div class="balance-panel-label">Advance Balance</div>
                                        <div class="balance-panel-amount" id="balancePanelAmount">—</div>
                                        <div class="balance-panel-note" id="balancePanelNote">Loading…</div>
                                    </div>
                                    <div class="balance-panel-action" id="balancePanelAction" style="display:none;">
                                        <a id="addPaymentLink" href="add-tp-advance-payment"><i class="material-icons" style="font-size:16px;">add</i> Add Payment</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Product section — shown after TP selected -->
                        <div id="productAddWrapper" style="display:none;">
                            <div class="product-add-section">
                                <div class="section-header" style="border:none;padding-bottom:15px;">
                                    <i class="material-icons">add_shopping_cart</i>
                                    Add Product to Invoice
                                </div>
                                <div class="product-add-grid">
                                    <div class="input-group-modern">
                                        <label>Product <span style="color:#ef4444;">*</span></label>
                                        <select id="productSelect" class="form-control">
                                            <option value="">— Select Product —</option>
                                        </select>
                                    </div>
                                    <div class="input-group-modern">
                                        <label>Available</label>
                                        <input type="number" id="availQty" class="form-control" readonly placeholder="—">
                                    </div>
                                    <div class="input-group-modern">
                                        <label>Qty <span style="color:#ef4444;">*</span></label>
                                        <input type="number" id="qtyInput" class="form-control" min="1" placeholder="0">
                                    </div>
                                    <div class="input-group-modern">
                                        <label>Rate (₹) <span style="color:#ef4444;">*</span></label>
                                        <input type="number" id="rateInput" class="form-control" min="0" step="0.01" placeholder="0.00">
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
                                            <th>#</th><th>Product</th><th>Available</th>
                                            <th>Qty</th><th>Rate (₹)</th><th>Amount (₹)</th><th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="productBody">
                                        <tr class="empty-row" id="emptyRow">
                                            <td colspan="7">
                                                <i class="material-icons" style="font-size:40px;display:block;margin-bottom:10px;color:#cbd5e1;">inventory_2</i>
                                                No products added yet
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="invoice-summary-card">
                                <div class="row">
                                    <div class="col-lg-5 ms-auto">
                                        <div class="summary-row">
                                            <span class="summary-label">Total Items</span>
                                            <span class="summary-value" id="summaryItems">0</span>
                                        </div>
                                        <div class="summary-row">
                                            <span class="summary-label">Total Quantity</span>
                                            <span class="summary-value" id="summaryQty">0</span>
                                        </div>
                                        <div class="summary-row">
                                            <span class="summary-label">Subtotal</span>
                                            <span class="summary-value" id="summarySubtotal">₹0.00</span>
                                        </div>
                                        <div class="summary-row">
                                            <span class="summary-label">
                                                <i class="material-icons" style="font-size:17px;color:#64748b;vertical-align:middle;">local_shipping</i>
                                                Courier Charges (₹)
                                                <span style="font-size:11px;color:#94a3b8;display:block;">Collected separately via receipt</span>
                                            </span>
                                            <span class="summary-value">
                                                <input type="number" id="courierInput" name="courier_charges" min="0" step="0.01" value="0"
                                                       style="width:130px;border:2px solid #e5e7eb;border-radius:7px;padding:7px 12px;text-align:right;font-size:14px;font-family:'Poppins',sans-serif;">
                                            </span>
                                        </div>
                                        <div class="summary-row">
                                            <span class="summary-label">
                                                <i class="material-icons" style="font-size:17px;color:#64748b;vertical-align:middle;">discount</i>
                                                Discount (₹)
                                            </span>
                                            <span class="summary-value">
                                                <input type="number" id="discountInput" name="discount_amount" min="0" step="0.01" value="0"
                                                       style="width:130px;border:2px solid #e5e7eb;border-radius:7px;padding:7px 12px;text-align:right;font-size:14px;font-family:'Poppins',sans-serif;">
                                            </span>
                                        </div>
                                        <div class="summary-row" style="border-top:2px solid #e5e7eb;margin-top:4px;padding-top:12px;">
                                            <span class="summary-label" style="font-size:16px;font-weight:600;color:#1e293b;">Grand Total</span>
                                            <span class="summary-total" id="grandTotal">₹0.00</span>
                                        </div>
                                        <div class="summary-row" id="summaryBalanceRow" style="display:none;">
                                            <span class="summary-label" style="color:#0369a1;">
                                                <i class="material-icons" style="font-size:15px;vertical-align:middle;margin-right:3px;">account_balance_wallet</i>
                                                Advance Balance
                                            </span>
                                            <span class="summary-value" id="summaryBalance" style="color:#0369a1;">—</span>
                                        </div>
                                        <div class="summary-row" id="summaryAfterRow" style="display:none;border-top:2px dashed #e5e7eb;margin-top:6px;padding-top:10px;">
                                            <span class="summary-label" id="summaryAfterLabel">Balance After Invoice</span>
                                            <span class="summary-value" id="summaryAfter">—</span>
                                        </div>
                                        <div class="invoice-info-actions">
                                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                                <i class="material-icons">check_circle</i> Submit Invoice
                                            </button>
                                            <a href="manage-tp-invoices" class="btn btn-secondary ms-2" style="border-radius:8px;padding:10px 18px;font-size:14px;">Cancel</a>
                                            <div id="submitHint" style="margin-top:10px;font-size:12.5px;color:#dc2626;display:none;">
                                                <i class="material-icons" style="font-size:14px;vertical-align:middle;">info</i>
                                                Advance balance is insufficient for this invoice.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /productAddWrapper -->

                        <div id="noTpPlaceholder" style="text-align:center;padding:40px;color:#94a3b8;">
                            <i class="material-icons" style="font-size:48px;display:block;margin-bottom:12px;color:#cbd5e1;">person_search</i>
                            <p style="font-size:14px;margin:0;">Select a Territory Partner to begin</p>
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
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script>
$(document).ready(function() {
    function tpMatcher(params, data) {
        if (!params.term || params.term.trim() === '') return data;
        var q = params.term.trim().toLowerCase();
        if ((data.text || '').toLowerCase().indexOf(q) > -1) return data;
        if (data.element) {
            var tpid   = (data.element.getAttribute('data-tpid')   || '').toLowerCase();
            var mobile = (data.element.getAttribute('data-mobile')  || '').toLowerCase();
            if (tpid.indexOf(q) > -1 || mobile.indexOf(q) > -1) return data;
        }
        return null;
    }
    $('#tpSelect').select2({ placeholder: 'Search by name, TP ID or mobile…', allowClear: true, matcher: tpMatcher });
});
</script>
<script>
(function ($) {
    var availableProducts = [];
    var invoiceItems      = [];
    var currentTpId       = null;
    var advanceBalance    = 0;

    function fetchBalance(tp_id) {
        $('#balancePanel').show();
        setBalancePanel('loading', '—', 'Fetching balance…', false);
        $.getJSON('get-tp-advance-balance.php?tp_id=' + tp_id, function (res) {
            advanceBalance = res.balance || 0;
            if (advanceBalance <= 0) {
                setBalancePanel('danger', '₹0.00', 'No advance balance. Please add a payment before creating an invoice.', true);
            } else {
                setBalancePanel('ok', '₹' + fmtAmt(advanceBalance), 'Available to deduct against new invoices.', false);
            }
            updateSummary();
        }).fail(function () {
            advanceBalance = 0;
            setBalancePanel('warn', '—', 'Could not load balance. Balance will be checked on submit.', false);
            updateSummary();
        });
    }

    function setBalancePanel(state, amount, note, showAction) {
        var $p = $('#balancePanelInner');
        $p.removeClass('loading ok warn danger').addClass(state);
        $('#balancePanelAmount').text(amount);
        $('#balancePanelNote').text(note);
        $('#balancePanelAction').toggle(showAction);
    }

    function fmtAmt(n) {
        return parseFloat(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    $('#tpSelect').on('change', function () {
        var tp_id = $(this).val();
        resetAll();
        if (!tp_id) {
            $('#balancePanel').hide();
            $('#noTpPlaceholder').show();
            return;
        }
        currentTpId = parseInt(tp_id);
        $('#noTpPlaceholder').hide();
        $('#addPaymentLink').attr('href', 'add-tp-advance-payment?tp_id=' + tp_id);
        fetchBalance(currentTpId);
        loadSsProducts();
    });

    function loadSsProducts() {
        $('#productSelect').html('<option value="">Loading…</option>').prop('disabled', true);
        $.getJSON('get-ss-tp-products.php?tp_id=' + currentTpId, function (data) {
            if (!data.length) {
                $('#productSelect').html('<option value="">No stock available</option>');
                showAddError('No products in stock for this Super Stockist.');
                return;
            }
            var opts = '<option value="">— Select Product —</option>';
            $.each(data, function (i, p) {
                opts += '<option value="' + p.product_id + '" data-avail="' + p.available_qty + '" data-rate="' + p.rate + '">'
                      + p.productName + (p.hsn ? ' [HSN: ' + p.hsn + ']' : '') + ' (Avail: ' + p.available_qty + ')'
                      + '</option>';
            });
            $('#productSelect').html(opts).prop('disabled', false);
        }).fail(function (jqXHR) {
            showAddError('Error loading products. Please refresh.');
        });
        $('#productAddWrapper').show();
    }

    $('#productSelect').on('change', function () {
        var $opt  = $(this).find('option:selected');
        var avail = parseInt($opt.data('avail')) || 0;
        var rate  = parseFloat($opt.data('rate')) || 0;
        $('#availQty').val(avail > 0 ? avail : '');
        $('#rateInput').val(rate > 0 ? rate.toFixed(2) : '0.00');
        $('#qtyInput').val('').attr('max', avail);
        hideAddError();
    });

    window.addProduct = function () {
        hideAddError();
        var $opt      = $('#productSelect').find('option:selected');
        var product_id = parseInt($('#productSelect').val());
        var name      = $opt.text().trim();
        var avail     = parseInt($('#availQty').val()) || 0;
        var qty       = parseInt($('#qtyInput').val()) || 0;
        var rate      = parseFloat($('#rateInput').val()) || 0;

        if (!product_id) { showAddError('Please select a product.'); return; }
        if (qty < 1)     { showAddError('Quantity must be at least 1.'); return; }
        if (rate <= 0)   { showAddError('Please enter a valid rate.'); return; }
        if (qty > avail) { showAddError('Quantity exceeds available stock (' + avail + ').'); return; }
        if (invoiceItems.find(function(i){ return i.product_id === product_id; })) {
            showAddError('This product is already added.'); return;
        }

        var amount = parseFloat((qty * rate).toFixed(2));
        invoiceItems.push({ product_id: product_id, name: name, avail: avail, qty: qty, rate: rate, amount: amount });
        renderTable();
        resetAddForm();
    };

    window.removeProduct = function (idx) {
        invoiceItems.splice(idx, 1);
        renderTable();
    };

    function renderTable() {
        var $body = $('#productBody').empty();
        if (!invoiceItems.length) {
            $body.html('<tr class="empty-row"><td colspan="7"><i class="material-icons" style="font-size:40px;display:block;margin-bottom:10px;color:#cbd5e1;">inventory_2</i>No products added yet</td></tr>');
            updateSummary(); return;
        }
        $.each(invoiceItems, function (i, item) {
            $body.append(
                '<tr>' +
                '<td><span class="row-num">' + (i+1) + '</span></td>' +
                '<td><strong>' + escHtml(item.name) + '</strong></td>' +
                '<td><span class="avail-chip' + (item.avail>0?'':' none') + '">' + item.avail + '</span></td>' +
                '<td>' + item.qty + '</td>' +
                '<td>₹' + item.rate.toFixed(2) + '</td>' +
                '<td><strong>₹' + item.amount.toFixed(2) + '</strong></td>' +
                '<td><button type="button" class="badge-remove" onclick="removeProduct(' + i + ')"><i class="material-icons" style="font-size:14px;vertical-align:middle;">delete</i> Remove</button></td>' +
                '</tr>'
            );
        });
        updateSummary();
        buildHiddenInputs();
    }

    $('#courierInput, #discountInput').on('input change', function () { updateSummary(); });

    function updateSummary() {
        var subtotal = 0, qty = 0;
        $.each(invoiceItems, function (_, item) { subtotal += item.amount; qty += item.qty; });
        subtotal = parseFloat(subtotal.toFixed(2));
        var discount  = Math.max(0, parseFloat($('#discountInput').val()) || 0);
        discount      = parseFloat(Math.min(discount, subtotal).toFixed(2));
        var courier   = Math.max(0, parseFloat($('#courierInput').val()) || 0);
        var netAmount = parseFloat((subtotal - discount).toFixed(2));
        var total     = parseFloat((netAmount + courier).toFixed(2));

        $('#summaryItems').text(invoiceItems.length);
        $('#summaryQty').text(qty);
        $('#summarySubtotal').text('₹' + fmtAmt(subtotal));
        $('#grandTotal').text('₹' + fmtAmt(total));

        if (currentTpId && advanceBalance > 0) {
            var remaining = parseFloat((advanceBalance - netAmount).toFixed(2));
            $('#summaryBalance').text('₹' + fmtAmt(advanceBalance));
            $('#summaryBalanceRow').show();
            $('#summaryAfterRow').show();
            if (remaining >= 0) {
                $('#summaryAfter').text('₹' + fmtAmt(remaining)).css('color', remaining > 0 ? '#059669' : '#64748b');
                $('#summaryAfterLabel').text('Balance After Invoice');
            } else {
                $('#summaryAfter').text('−₹' + fmtAmt(Math.abs(remaining))).css('color', '#dc2626');
                $('#summaryAfterLabel').text('Shortfall');
            }
            if (netAmount > 0 && advanceBalance >= netAmount) {
                setBalancePanel('ok', '₹' + fmtAmt(advanceBalance), '₹' + fmtAmt(netAmount) + ' will be deducted from advance.', false);
            } else if (netAmount > 0 && advanceBalance < netAmount) {
                setBalancePanel('danger', '₹' + fmtAmt(advanceBalance), 'Insufficient — need ₹' + fmtAmt(netAmount) + '.', true);
            }
        }

        $('#submitBtn').prop('disabled', invoiceItems.length === 0);
        var insufficient = advanceBalance > 0 && currentTpId && (advanceBalance < (subtotal - discount));
        $('#submitHint').toggle(insufficient && invoiceItems.length > 0);
    }

    function buildHiddenInputs() {
        var html = '';
        $.each(invoiceItems, function (_, item) {
            html += '<input type="hidden" name="product_id[]" value="' + item.product_id + '">';
            html += '<input type="hidden" name="qty[]"        value="' + item.qty + '">';
            html += '<input type="hidden" name="rate[]"       value="' + item.rate + '">';
        });
        $('#hiddenProductInputs').html(html);
    }

    $('#invoiceForm').on('submit', function (e) {
        if (!invoiceItems.length) { e.preventDefault(); alert('Please add at least one product.'); return; }
        buildHiddenInputs();
        $('#submitBtn').prop('disabled', true).html('<i class="material-icons" style="animation:spin 1s linear infinite;font-size:18px;">refresh</i> Submitting…');
    });

    function resetAll() {
        currentTpId    = null;
        advanceBalance = 0;
        availableProducts = [];
        invoiceItems   = [];
        $('#balancePanel').hide();
        $('#productAddWrapper').hide();
        $('#productBody').empty();
        $('#hiddenProductInputs').empty();
        updateSummary();
    }

    function resetAddForm() {
        $('#productSelect').val('').trigger('change.select2');
        $('#availQty').val('');
        $('#qtyInput').val('');
        $('#rateInput').val('');
        hideAddError();
    }

    function showAddError(msg) {
        $('#addError').html('<div class="alert alert-danger mb-0" style="font-size:13px;padding:10px 14px;"><i class="material-icons" style="vertical-align:middle;font-size:16px;">error</i> ' + msg + '</div>').show();
    }
    function hideAddError() { $('#addError').hide().empty(); }
    function escHtml(str) { return $('<div>').text(str).html(); }

}(jQuery));
</script>
</body>
</html>
