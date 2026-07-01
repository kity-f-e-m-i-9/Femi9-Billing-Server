<?php
include("checksession.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$tp_result = $db_conn->query("SELECT id, tp_id, name, mobile FROM territory_partners WHERE is_active=1 ORDER BY name");
$tps = $tp_result ? $tp_result->fetch_all(MYSQLI_ASSOC) : [];

$gd_result = $db_conn->query("SELECT id, gname FROM company_godown WHERE gname LIKE '%Femi%' ORDER BY gname");
$godowns_list = $gd_result ? $gd_result->fetch_all(MYSQLI_ASSOC) : [];
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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
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

        /* ── Alerts ── */
        .alert { border-radius: 10px; border: none; padding: 15px 20px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger  { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
        .alert-info    { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; }

        /* ── Page Title ── */
        .page-title-modern {
            background: white; border: 2px solid #e5e7eb; border-radius: 12px;
            padding: 20px 25px; margin-bottom: 25px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .page-title-modern h1 { color: #1e293b; font-size: 22px; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 12px; }
        .page-title-modern h1 i { color: #2563eb; font-size: 26px; }
        .page-title-modern .menu-link {
            background: #2563eb; color: white; width: 40px; height: 40px;
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            text-decoration: none; font-size: 20px; transition: all 0.3s ease;
        }
        .page-title-modern .menu-link:hover { background: #1d4ed8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37,99,235,0.3); }

        /* ── Form Sections ── */
        .form-section {
            background: white; border: 2px solid #e5e7eb; border-radius: 14px;
            padding: 30px 35px; margin-bottom: 22px; box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            transition: border-color 0.2s;
        }
        .form-section:hover { border-color: #bfdbfe; }

        .section-header {
            color: #475569; font-size: 13px; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.6px; margin-bottom: 24px; display: flex; align-items: center;
            gap: 10px; padding-bottom: 14px; border-bottom: 2px solid #f1f5f9;
        }
        .section-header i { color: #2563eb; font-size: 19px; }

        /* ── Form controls ── */
        .form-label { font-weight: 600; color: #374151; margin-bottom: 10px; font-size: 13.5px; display: block; letter-spacing: 0.1px; }
        .form-label .required { color: #ef4444; margin-left: 3px; }
        .form-control, .form-select {
            border: 2px solid #e5e7eb; border-radius: 9px; padding: 11px 15px;
            font-size: 14px; font-family: 'Poppins', sans-serif; transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .form-control:hover, .form-select:hover { border-color: #bfdbfe; }
        .form-control[readonly] { background: #f8fafc; color: #64748b; }
        .field-hint { font-size: 12px; color: #94a3b8; margin-top: 7px; display: flex; align-items: center; gap: 4px; }
        .field-hint::before { content: ''; display: inline-block; width: 4px; height: 4px; border-radius: 50%; background: #cbd5e1; flex-shrink: 0; }

        /* ── Advance balance panel ── */
        .balance-panel {
            display: flex; align-items: center; gap: 14px;
            background: #f0fdf4; border: 2px solid #bbf7d0; border-radius: 10px;
            padding: 14px 18px; margin-top: 18px; transition: all 0.3s ease;
        }
        .balance-panel.warn { background: #fff7ed; border-color: #fed7aa; }
        .balance-panel.danger { background: #fef2f2; border-color: #fecaca; }
        .balance-panel.loading { background: #f8fafc; border-color: #e2e8f0; }
        .balance-panel-icon { font-size: 28px; flex-shrink: 0; }
        .balance-panel.ok .balance-panel-icon     { color: #10b981; }
        .balance-panel.warn .balance-panel-icon   { color: #f59e0b; }
        .balance-panel.danger .balance-panel-icon { color: #ef4444; }
        .balance-panel.loading .balance-panel-icon { color: #94a3b8; }
        .balance-panel-body { flex: 1; }
        .balance-panel-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
        .balance-panel-amount { font-size: 22px; font-weight: 700; }
        .balance-panel.ok .balance-panel-amount     { color: #059669; }
        .balance-panel.warn .balance-panel-amount   { color: #d97706; }
        .balance-panel.danger .balance-panel-amount { color: #dc2626; }
        .balance-panel.loading .balance-panel-amount { color: #94a3b8; }
        .balance-panel-note { font-size: 12.5px; color: #64748b; margin-top: 3px; }
        .balance-panel-action { flex-shrink: 0; }
        .balance-panel-action a {
            display: inline-flex; align-items: center; gap: 5px;
            background: #2563eb; color: white; border-radius: 7px;
            padding: 7px 14px; font-size: 13px; font-weight: 500; text-decoration: none;
            transition: all 0.2s;
        }
        .balance-panel-action a:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(37,99,235,0.3); color: white; }

        /* ── Source location box ── */
        .source-info {
            background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px;
            padding: 12px 16px; font-size: 14px;
        }
        .source-info .loc-name { font-weight: 600; color: #1e293b; display: block; margin-bottom: 4px; }
        .source-info .cp-badge { display: inline-block; background: #e8eaf6; color: #5c6bc0; border-radius: 4px; padding: 2px 8px; font-size: 11.5px; font-weight: 600; }

        /* ── Product Add Section ── */
        .product-add-section {
            background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px;
            padding: 20px; margin-top: 20px;
        }
        .product-add-grid {
            display: grid;
            grid-template-columns: 2.5fr 1fr 1fr 1fr auto;
            gap: 14px;
            align-items: end;
        }
        .input-group-modern { display: flex; flex-direction: column; }
        .input-group-modern label { font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.4px; }
        .input-group-modern .form-control { border: 2px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; font-size: 14px; }
        .input-group-modern .form-control:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

        /* ── Buttons ── */
        .btn-primary {
            background: #2563eb; border: none; padding: 10px 20px;
            border-radius: 8px; font-weight: 500; display: inline-flex; align-items: center;
            gap: 6px; transition: all 0.3s ease; font-size: 14px; font-family: 'Poppins', sans-serif;
        }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
        .btn-primary i { font-size: 18px; }
        .btn-primary:disabled { opacity: 0.5; transform: none; box-shadow: none; cursor: not-allowed; }

        .btn-add {
            background: #10b981; color: white; border: none; padding: 11px 20px;
            border-radius: 8px; font-weight: 500; display: inline-flex; align-items: center;
            gap: 6px; transition: all 0.3s ease; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 14px;
            white-space: nowrap;
        }
        .btn-add:hover { background: #059669; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
        .btn-add:disabled { opacity: 0.5; transform: none; cursor: not-allowed; }

        /* ── Products Table ── */
        .table-modern { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 20px; border: 1px solid #f1f5f9; }
        .table-modern table { margin: 0; width: 100%; }
        .table-modern thead { background: #f8fafc; }
        .table-modern thead th { color: #475569; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding: 14px 16px; border-bottom: 2px solid #e5e7eb; white-space: nowrap; }
        .table-modern tbody td { padding: 13px 16px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-size: 14px; }
        .table-modern tbody tr:last-child td { border-bottom: none; }
        .table-modern tbody tr:hover { background: #f8fafc; }
        .table-modern .empty-row td { text-align: center; padding: 40px; color: #94a3b8; }

        .row-num { width: 28px; height: 28px; background: #f1f5f9; color: #64748b; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; }
        .avail-chip { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #d1fae5; color: #065f46; }
        .avail-chip.none { background: #f1f5f9; color: #94a3b8; }

        .badge-remove {
            background: #fee2e2; color: #991b1b; padding: 5px 10px; border-radius: 6px;
            font-size: 12px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 3px; transition: all 0.2s;
        }
        .badge-remove:hover { background: #fecaca; transform: scale(1.05); }

        /* ── Summary Card ── */
        .invoice-summary-card {
            background: white; border: 2px solid #e5e7eb; border-radius: 12px;
            padding: 25px; margin-top: 20px;
        }
        .summary-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px;
        }
        .summary-row:last-child { border-bottom: none; }
        .summary-label { color: #64748b; font-weight: 500; }
        .summary-value { font-weight: 600; color: #1e293b; }
        .summary-total { font-size: 20px; font-weight: 700; color: #10b981; }

        .invoice-info-actions { margin-top: 20px; padding-top: 20px; border-top: 2px solid #f1f5f9; }

        /* ── Select2 ── */
        .select2-container--default .select2-selection--single { border: 2px solid #e5e7eb; border-radius: 9px; height: auto; padding: 11px 15px; font-size: 14px; font-family: 'Poppins', sans-serif; transition: border-color 0.3s; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 1.5; padding: 0; color: #1e293b; }
        .select2-container--default .select2-selection--single .select2-selection__placeholder { color: #94a3b8; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 100%; top: 0; right: 10px; }
        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); outline: none; }
        .select2-container--default .select2-results__option--highlighted[aria-selected] { background: #2563eb; }
        .select2-dropdown { border: 2px solid #e5e7eb; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); font-family: 'Poppins', sans-serif; font-size: 14px; }
        .select2-search--dropdown { padding: 8px; }
        .select2-search--dropdown .select2-search__field { border: 2px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; font-family: 'Poppins', sans-serif; }
        .select2-results__option { padding: 9px 14px; }
        .select2-container { width: 100% !important; }
        .select2-container--default .select2-selection--single .select2-selection__clear { margin-right: 24px; font-size: 16px; color: #94a3b8; }

        /* ── Responsive ── */
        @media (max-width: 992px) {
            .product-add-grid { grid-template-columns: 1fr 1fr 1fr 1fr; }
            .product-add-grid .input-group-modern:first-child { grid-column: 1 / -1; }
            .product-add-grid .input-group-modern:last-child { grid-column: 1 / -1; }
        }
        @media (max-width: 576px) {
            .product-add-grid { grid-template-columns: 1fr; }
        }
        @keyframes spin { to { transform: rotate(360deg); } }
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
                        <?php if ($err === 'insufficient'): ?>Insufficient stock at the source location for one or more products.
                        <?php elseif ($err === 'no_input_stock'): ?>
                            Input stock has not been set for this Territory Partner. Please <a href="add-tp-input-stock" style="color:inherit;font-weight:700;text-decoration:underline;">add input stock</a> before creating a TP invoice.
                        <?php elseif ($err === 'missing'): ?>Please fill in all required fields.
                        <?php elseif ($err === 'noproducts'): ?>Please add at least one product with a valid quantity.
                        <?php elseif ($err === 'nobalance'): ?>
                            Insufficient advance balance. Required: <strong>₹<?php echo htmlspecialchars($_GET['need'] ?? ''); ?></strong>, Available: <strong>₹<?php echo htmlspecialchars($_GET['have'] ?? '0.00'); ?></strong>.
                            <a href="add-tp-advance-payment" style="color:inherit;font-weight:700;text-decoration:underline;">Add advance payment →</a>
                        <?php else: ?>An error occurred. Please try again.<?php if (!empty($_GET['msg'])): ?> <small style="opacity:0.75;">(<?php echo htmlspecialchars(substr($_GET['msg'],0,100)); ?>)</small><?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Page Title -->
                    <div class="page-title-modern">
                        <h1>
                            <i class="material-icons">receipt_long</i>
                            Add TP Invoice
                        </h1>
                        <a href="manage-tp-invoices" class="menu-link" title="Manage TP Invoices">
                            <i class="material-icons">list</i>
                        </a>
                    </div>

                    <form action="tp-invoice-action" method="post" id="invoiceForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="insert-tp-invoice">
                        <input type="hidden" name="source_location_id" id="sourceLocationId">
                        <input type="hidden" name="source_cp_id" id="sourceCpId">
                        <input type="hidden" name="source_godown_id" id="sourceGodownId">
                        <!-- product arrays injected by JS before submit -->
                        <div id="hiddenProductInputs"></div>

                        <!-- ── Invoice Details ── -->
                        <div class="form-section">
                            <div class="section-header">
                                <i class="material-icons">edit_document</i>
                                Invoice Details
                            </div>
                            <div class="row g-4 align-items-start">

                                <div class="col-lg-5 col-md-6">
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
                                </div>

                                <div class="col-lg-4 col-md-6" id="sourceSection" style="display:none;">
                                    <label class="form-label" id="sourceLabel">Channel Partner</label>
                                    <div id="sourceContent"></div>
                                    <div class="field-hint" id="sourceHint">Auto-resolved from territory assignment</div>
                                </div>

                                <div class="col-lg-3 col-md-4">
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
                                        <a href="add-tp-advance-payment">
                                            <i class="material-icons" style="font-size:16px;">add</i> Add Payment
                                        </a>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- ── Add Product ── -->
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
                                        <label>Rate (₹) <span style="color:#ef4444;">*</span> <span style="font-size:10px;color:#94a3b8;font-weight:400;text-transform:none;letter-spacing:0;">stockist price</span></label>
                                        <input type="number" id="rateInput" class="form-control" min="0" step="0.01" placeholder="0.00">
                                    </div>
                                    <div class="input-group-modern" style="align-items:flex-end;">
                                        <button type="button" class="btn-add" id="addProductBtn" onclick="addProduct()">
                                            <i class="material-icons">add</i> Add
                                        </button>
                                    </div>
                                </div>
                                <div id="addError" style="margin-top:10px;display:none;"></div>
                            </div>

                            <!-- ── Products Table ── -->
                            <div class="table-modern">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Product</th>
                                            <th>Available</th>
                                            <th>Qty</th>
                                            <th>Rate (₹)</th>
                                            <th>Amount (₹)</th>
                                            <th></th>
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

                            <!-- ── Summary ── -->
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
                                            <span class="summary-label" style="display:flex;flex-direction:column;gap:3px;">
                                                <span style="display:flex;align-items:center;gap:6px;">
                                                    <i class="material-icons" style="font-size:17px;color:#64748b;">local_shipping</i>
                                                    Courier Charges (₹)
                                                </span>
                                                <span style="font-size:11px;color:#94a3b8;font-weight:400;">Collected separately via receipt</span>
                                            </span>
                                            <span class="summary-value">
                                                <input type="number" id="courierInput" name="courier_charges"
                                                       min="0" step="0.01" value="0"
                                                       style="width:130px;border:2px solid #e5e7eb;border-radius:7px;padding:7px 12px;text-align:right;font-size:14px;font-family:'Poppins',sans-serif;font-weight:500;transition:border-color .2s;"
                                                       onfocus="this.style.borderColor='#2563eb';this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.1)'"
                                                       onblur="this.style.borderColor='#e5e7eb';this.style.boxShadow='none'">
                                            </span>
                                        </div>
                                        <div class="summary-row">
                                            <span class="summary-label" style="display:flex;align-items:center;gap:6px;">
                                                <i class="material-icons" style="font-size:17px;color:#64748b;">discount</i>
                                                Discount (₹)
                                            </span>
                                            <span class="summary-value">
                                                <input type="number" id="discountInput" name="discount_amount"
                                                       min="0" step="0.01" value="0"
                                                       style="width:130px;border:2px solid #e5e7eb;border-radius:7px;padding:7px 12px;text-align:right;font-size:14px;font-family:'Poppins',sans-serif;font-weight:500;transition:border-color .2s;"
                                                       onfocus="this.style.borderColor='#2563eb';this.style.boxShadow='0 0 0 3px rgba(37,99,235,0.1)'"
                                                       onblur="this.style.borderColor='#e5e7eb';this.style.boxShadow='none'">
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
                                                <i class="material-icons">check_circle</i>
                                                Submit Invoice
                                            </button>
                                            <a href="manage-tp-invoices" class="btn btn-secondary ms-2" style="border-radius:8px;padding:10px 18px;font-size:14px;">Cancel</a>
                                            <div id="submitHint" style="margin-top:10px;font-size:12.5px;color:#dc2626;display:none;">
                                                <i class="material-icons" style="font-size:14px;vertical-align:middle;">info</i>
                                                Advance balance is insufficient. <a href="add-tp-advance-payment" style="color:#dc2626;font-weight:600;text-decoration:underline;">Add advance payment →</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div><!-- /productAddWrapper -->

                        <!-- warning: TP has no input stock -->
                        <div id="noStockWarning" style="display:none;margin-top:16px;">
                            <div style="background:#fff7ed;border:2px solid #fed7aa;border-radius:10px;padding:18px 22px;display:flex;align-items:center;gap:14px;">
                                <i class="material-icons" style="font-size:30px;color:#f59e0b;flex-shrink:0;">inventory_2</i>
                                <div>
                                    <div style="font-weight:600;color:#92400e;font-size:14px;margin-bottom:4px;">Input stock not set for this Territory Partner</div>
                                    <div style="font-size:13px;color:#78350f;">You must add input stock to this TP before creating an invoice.
                                        <a href="add-tp-input-stock" style="color:#92400e;font-weight:700;text-decoration:underline;margin-left:4px;">Add Input Stock →</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- placeholder when no TP selected -->
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
    // Select2 for TP with multi-field search
    function tpMatcher(params, data) {
        if (!params.term || params.term.trim() === '') return data;
        var q    = params.term.trim().toLowerCase();
        var text = (data.text || '').toLowerCase();
        if (text.indexOf(q) > -1) return data;
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

    /* ── State ── */
    var availableProducts = [];
    var invoiceItems      = [];
    var currentLocationId = null;
    var currentCpId       = null;
    var currentGodownId   = null;
    var currentTpId       = null;
    var advanceBalance    = 0;

    /* ── Godown list (pre-loaded from PHP) ── */
    var godownsList = <?php echo json_encode($godowns_list); ?>;

    /* ── Balance helpers ── */
    function fetchBalance(tp_id, godown_id) {
        var url = 'get-tp-advance-balance.php?tp_id=' + tp_id;
        if (godown_id) url += '&godown_id=' + godown_id;
        var note = godown_id ? 'Available advance balance for this godown.' : 'Available to deduct against new invoices.';
        $('#balancePanel').show();
        setBalancePanel('loading', '—', 'Fetching balance…', false);
        $.getJSON(url, function (res) {
            advanceBalance = res.balance || 0;
            if (advanceBalance <= 0) {
                setBalancePanel('danger', '₹0.00', 'No advance balance' + (godown_id ? ' for this godown' : '') + '. Please add a payment before creating an invoice.', true);
            } else {
                setBalancePanel('ok', '₹' + fmtAmt(advanceBalance), note, false);
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

    /* ── Stock check helper ── */
    function checkTpStock(tp_id, onReady) {
        $.getJSON('get-tp-stock-check.php?tp_id=' + tp_id, function (res) {
            if (res.has_stock) {
                $('#noStockWarning').hide();
                onReady();
            } else {
                $('#noStockWarning').show();
                $('#sourceSection').hide();
                $('#balancePanel').hide();
                $('#productAddWrapper').hide();
            }
        }).fail(function () {
            onReady();
        });
    }

    /* ── TP Select change ── */
    $('#tpSelect').on('change', function () {
        var tp_id = $(this).val();
        resetAll();
        $('#noStockWarning').hide();
        if (!tp_id) {
            $('#sourceSection').hide();
            $('#balancePanel').hide();
            $('#noTpPlaceholder').show();
            return;
        }
        currentTpId = parseInt(tp_id);
        $('#noTpPlaceholder').hide();

        checkTpStock(currentTpId, function () {
            $('#sourceSection').show();
            $('#sourceContent').html('<span class="field-hint">Resolving channel partner…</span>');
            fetchBalance(currentTpId);

        $.getJSON('get-tp-source-locations.php?tp_id=' + tp_id, function (res) {
            if (res.status === 'ok' && res.sources.length) {
                $('#sourceLabel').text('Channel Partner');
                $('#sourceHint').text('Auto-resolved from territory assignment');
                if (res.sources.length === 1) {
                    useSource(res.sources[0]);
                } else {
                    renderSourceDropdown(res.sources);
                }
            } else {
                // No CP found — fall back to godown selection
                $('#sourceLabel').text('Source Godown');
                $('#sourceHint').text('No CP assigned — select a company godown');
                renderGodownDropdown();
            }
        }).fail(function () {
            $('#sourceContent').html('<div class="alert alert-danger mb-0" style="font-size:13px;">Failed to resolve source. Please try again.</div>');
        });
        }); // end checkTpStock callback
    });

    function useSource(src) {
        currentLocationId = src.location_id;
        currentCpId       = src.cp_db_id;
        $('#sourceLocationId').val(src.location_id);
        $('#sourceCpId').val(src.cp_db_id);
        $('#sourceContent').html(
            '<div class="source-info">' +
            '<span class="loc-name">' + escHtml(src.cp_name) + '</span>' +
            '<span class="cp-badge">' + escHtml(src.cp_code) + ' · ' + escHtml(src.location_name) + '</span>' +
            '</div>'
        );
        loadProducts(src.cp_db_id);
    }

    function renderSourceDropdown(sources) {
        var $sel = $('<select class="form-control" id="sourceDrop"></select>');
        $sel.append('<option value=""></option>');
        $.each(sources, function (_, src) {
            $sel.append(
                $('<option>').val(src.location_id)
                    .attr('data-loc', src.cp_code + ' · ' + src.location_name)
                    .attr('data-cpdbid', src.cp_db_id)
                    .text(src.cp_name)
            );
        });
        $('#sourceContent').html($sel);

        $sel.select2({
            placeholder: '— Select channel partner —',
            allowClear: false,
            templateResult: function (data) {
                if (!data.id) return data.text;
                var loc = $(data.element).data('loc') || '';
                return $('<span>' + escHtml(data.text) + (loc ? ' <small style="color:#94a3b8;font-size:12px;">(' + escHtml(loc) + ')</small>' : '') + '</span>');
            },
            templateSelection: function (data) {
                if (!data.id) return data.text;
                var loc = $(data.element).data('loc') || '';
                return $('<span>' + escHtml(data.text) + (loc ? ' <small style="color:#94a3b8;font-size:12px;">(' + escHtml(loc) + ')</small>' : '') + '</span>');
            }
        });

        $sel.on('change', function () {
            var loc_id = parseInt($(this).val());
            resetProducts();
            if (!loc_id) { currentLocationId = null; currentCpId = null; $('#sourceLocationId').val(''); $('#sourceCpId').val(''); $('#productAddWrapper').hide(); return; }
            var $opt  = $sel.find('option[value="' + loc_id + '"]');
            var cp_id = parseInt($opt.data('cpdbid')) || 0;
            currentLocationId = loc_id;
            currentCpId       = cp_id;
            $('#sourceLocationId').val(loc_id);
            $('#sourceCpId').val(cp_id);
            loadProducts(cp_id);
        });
    }

    /* ── Godown fallback: render dropdown ── */
    function renderGodownDropdown() {
        // Hide balance panel until a specific godown is selected
        $('#balancePanel').hide();
        advanceBalance = 0;

        var $sel = $('<select class="form-control" id="godownDrop"></select>');
        $sel.append('<option value=""></option>');
        $.each(godownsList, function (_, gd) {
            $sel.append($('<option>').val(gd.id).text(gd.gname));
        });
        $('#sourceContent').html($sel);

        $sel.select2({ placeholder: '— Select godown —', allowClear: false });

        $sel.on('change', function () {
            var gd_id = parseInt($(this).val());
            resetProducts();
            if (!gd_id) {
                currentGodownId = null;
                $('#sourceGodownId').val('');
                $('#balancePanel').hide();
                advanceBalance = 0;
                $('#productAddWrapper').hide();
                return;
            }
            currentGodownId = gd_id;
            $('#sourceGodownId').val(gd_id);
            fetchBalance(currentTpId, gd_id);
            loadGodownProducts(gd_id);
        });
    }

    /* ── Load products from godown stock ── */
    function loadGodownProducts(godown_id) {
        $('#productSelect').html('<option value="">Loading…</option>').prop('disabled', true);
        $.getJSON('get-godown-tp-products.php?godown_id=' + godown_id, function (data) {
            availableProducts = data;
            var opts = '<option value="">— Select Product —</option>';
            $.each(data, function (_, p) {
                opts += '<option value="' + p.product_id + '" data-avail="' + p.available_qty + '" data-rate="' + p.rate + '">' + escHtml(p.productName) + '</option>';
            });
            $('#productSelect').html(opts).prop('disabled', false);
            if (!data.length) {
                $('#productSelect').html('<option value="">No stock available</option>');
                showAddError('No stock available in this godown.');
            } else {
                hideAddError();
                $('#productAddWrapper').show();
            }
        }).fail(function () {
            $('#productSelect').html('<option value="">Error loading products</option>');
        });
    }

    /* ── Load products for CP ── */
    function loadProducts(cp_id) {
        $('#productSelect').html('<option value="">Loading…</option>').prop('disabled', true);
        $.getJSON('get-tp-location-products.php?cp_id=' + cp_id, function (data) {
            availableProducts = data;
            var opts = '<option value="">— Select Product —</option>';
            $.each(data, function (_, p) {
                opts += '<option value="' + p.product_id + '" data-avail="' + p.available_qty + '" data-rate="' + p.rate + '">' + escHtml(p.productName) + '</option>';
            });
            $('#productSelect').html(opts).prop('disabled', false);
            if (!data.length) {
                $('#productSelect').html('<option value="">No stock available</option>');
                showAddError('No stock available at this location.');
            } else {
                hideAddError();
                $('#productAddWrapper').show();
            }
        }).fail(function () {
            $('#productSelect').html('<option value="">Error loading</option>');
            showAddError('Failed to load products.');
        });
        $('#productAddWrapper').show();
    }

    /* ── Product select auto-fill ── */
    $('#productSelect').on('change', function () {
        var $opt  = $(this).find('option:selected');
        var avail = parseInt($opt.data('avail')) || 0;
        var rate  = parseFloat($opt.data('rate')) || 0;
        $('#availQty').val(avail > 0 ? avail : '');
        $('#rateInput').val(rate > 0 ? rate.toFixed(2) : '0.00');
        $('#qtyInput').val('').attr('max', avail);
        hideAddError();
    });

    /* ── Add product ── */
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

        // Check duplicate
        if (invoiceItems.find(function(i){ return i.product_id === product_id; })) {
            showAddError('This product is already added. Remove it first to change quantity.'); return;
        }

        var amount = parseFloat((qty * rate).toFixed(2));
        invoiceItems.push({ product_id: product_id, name: name, avail: avail, qty: qty, rate: rate, amount: amount });

        renderTable();
        resetAddForm();
    };

    /* ── Remove product ── */
    window.removeProduct = function (idx) {
        invoiceItems.splice(idx, 1);
        renderTable();
    };

    /* ── Render table ── */
    function renderTable() {
        var $body = $('#productBody');
        $body.empty();

        if (!invoiceItems.length) {
            $body.html('<tr class="empty-row"><td colspan="7"><i class="material-icons" style="font-size:40px;display:block;margin-bottom:10px;color:#cbd5e1;">inventory_2</i>No products added yet</td></tr>');
            updateSummary();
            return;
        }

        $.each(invoiceItems, function (i, item) {
            var $tr = $('<tr></tr>');
            $tr.html(
                '<td><span class="row-num">' + (i + 1) + '</span></td>' +
                '<td><strong>' + escHtml(item.name) + '</strong></td>' +
                '<td><span class="avail-chip' + (item.avail > 0 ? '' : ' none') + '">' + item.avail + '</span></td>' +
                '<td>' + item.qty + '</td>' +
                '<td>₹' + item.rate.toFixed(2) + '</td>' +
                '<td><strong>₹' + item.amount.toFixed(2) + '</strong></td>' +
                '<td><button type="button" class="badge-remove" onclick="removeProduct(' + i + ')"><i class="material-icons" style="font-size:14px;vertical-align:middle;">delete</i> Remove</button></td>'
            );
            $body.append($tr);
        });

        updateSummary();
        buildHiddenInputs();
    }

    /* ── Courier / Discount inputs trigger recalc ── */
    $('#courierInput, #discountInput').on('input change', function () { updateSummary(); });

    /* ── Summary & hidden inputs ── */
    function updateSummary() {
        var subtotal = 0, qty = 0;
        $.each(invoiceItems, function (_, item) { subtotal += item.amount; qty += item.qty; });
        subtotal = parseFloat(subtotal.toFixed(2));

        var discount = Math.max(0, parseFloat($('#discountInput').val()) || 0);
        discount     = parseFloat(Math.min(discount, subtotal).toFixed(2));
        var courier  = Math.max(0, parseFloat($('#courierInput').val()) || 0);
        courier      = parseFloat(courier.toFixed(2));
        var netAmount = parseFloat((subtotal - discount).toFixed(2));
        var total     = parseFloat((netAmount + courier).toFixed(2));

        $('#summaryItems').text(invoiceItems.length);
        $('#summaryQty').text(qty);
        $('#summarySubtotal').text('₹' + fmtAmt(subtotal));
        $('#grandTotal').text('₹' + fmtAmt(total));

        // Balance rows — courier is NOT deducted from advance (collected separately)
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
            // Update balance panel note live
            if (netAmount > 0 && advanceBalance >= netAmount) {
                var note = '₹' + fmtAmt(netAmount) + ' will be deducted from advance.';
                if (discount > 0) note += ' Discount ₹' + fmtAmt(discount) + ' applied.';
                if (courier > 0) note += ' Courier ₹' + fmtAmt(courier) + ' collected separately.';
                setBalancePanel('ok', '₹' + fmtAmt(advanceBalance), note, false);
            } else if (netAmount > 0 && advanceBalance < netAmount) {
                setBalancePanel('danger', '₹' + fmtAmt(advanceBalance), 'Insufficient — need ₹' + fmtAmt(netAmount) + ' for products.', true);
            }
        } else if (currentTpId) {
            $('#summaryBalanceRow').hide();
            $('#summaryAfterRow').hide();
        }

        // Enable submit as long as products are added — server validates balance
        $('#submitBtn').prop('disabled', invoiceItems.length === 0);

        // Advisory warning (does NOT block submit — server will catch it)
        var insufficient = advanceBalance > 0 && currentTpId && (advanceBalance < netAmount);
        if (insufficient && invoiceItems.length > 0) {
            $('#submitHint').show();
        } else {
            $('#submitHint').hide();
        }
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

    /* ── Form submit validation ── */
    $('#invoiceForm').on('submit', function (e) {
        if (!$('#sourceCpId').val() && !$('#sourceGodownId').val()) { e.preventDefault(); alert('Please select a channel partner or godown.'); return; }
        if (!invoiceItems.length)          { e.preventDefault(); alert('Please add at least one product.'); return; }
        buildHiddenInputs();
        $('#submitBtn').prop('disabled', true).html('<i class="material-icons" style="animation:spin 1s linear infinite;font-size:18px;">refresh</i> Submitting…');
    });

    /* ── Helpers ── */
    function resetAll() {
        currentLocationId = null;
        currentCpId       = null;
        currentGodownId   = null;
        currentTpId       = null;
        advanceBalance    = 0;
        $('#sourceLocationId').val('');
        $('#sourceCpId').val('');
        $('#sourceGodownId').val('');
        availableProducts = [];
        // destroy any Select2 on the source dropdown before clearing it
        if ($('#sourceDrop').length)   { try { $('#sourceDrop').select2('destroy');   } catch(e){} }
        if ($('#godownDrop').length)   { try { $('#godownDrop').select2('destroy');   } catch(e){} }
        $('#sourceContent').empty();
        $('#sourceLabel').text('Channel Partner');
        $('#sourceHint').text('Auto-resolved from territory assignment');
        $('#balancePanel').hide();
        resetProducts();
        $('#productAddWrapper').hide();
        $('#noTpPlaceholder').hide();
    }

    function resetProducts() {
        invoiceItems = [];
        renderTable();
        resetAddForm();
        $('#hiddenProductInputs').empty();
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
