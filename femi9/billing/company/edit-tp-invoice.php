<?php
include("checksession.php");
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: manage-tp-invoices?error=unauthorized"); exit;
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$enc    = $_GET['id'] ?? '';
$inv_id = (int)base64_decode($enc);
if (!$inv_id) { header("Location: manage-tp-invoices?error=invalid"); exit; }

// Load invoice with TP and location info
$s = $db_conn->prepare("
    SELECT tpi.*,
           tp.name AS tp_name, tp.tp_id AS tp_code,
           COALESCE(cp_src.name, gd.gname, pln.name) AS location_name,
           COALESCE(cp_src.name, cp_old.name) AS cp_name,
           COALESCE(cp_src.cp_id, cp_old.cp_id) AS cp_code
    FROM tp_invoices tpi
    JOIN territory_partners tp                   ON tp.id = tpi.territory_partner_id
    LEFT JOIN partner_location_nodes pln         ON pln.id = tpi.source_location_id
    LEFT JOIN channel_partner_locations cpl      ON cpl.location_id = tpi.source_location_id
    LEFT JOIN channel_partners cp_old            ON cp_old.id = cpl.channel_partner_id
    LEFT JOIN channel_partners cp_src            ON cp_src.id = tpi.source_cp_id
    LEFT JOIN company_godown gd                  ON gd.id = tpi.source_godown_id
    WHERE tpi.id = ? LIMIT 1
");
$s->bind_param("i", $inv_id); $s->execute();
$inv = $s->get_result()->fetch_assoc(); $s->close();
if (!$inv) { header("Location: manage-tp-invoices?error=notfound"); exit; }

// Load existing items with product names
$s2 = $db_conn->prepare("
    SELECT tii.product_id, tii.quantity, tii.rate, tii.amount, p.productName
    FROM tp_invoice_items tii
    JOIN products p ON p.id = tii.product_id
    WHERE tii.tp_invoice_id = ?
    ORDER BY tii.id
");
$s2->bind_param("i", $inv_id); $s2->execute();
$existing_items = $s2->get_result()->fetch_all(MYSQLI_ASSOC); $s2->close();

// Get current available qty for each existing item's product (to show in UI)
$_src_cp_id     = (int)($inv['source_cp_id'] ?? 0);
$_src_godown_id = (int)($inv['source_godown_id'] ?? 0);
$_src_loc_id    = (int)($inv['source_location_id'] ?? 0);
$_use_cp        = $_src_cp_id > 0;
$_use_godown    = ($_src_godown_id > 0 && !$_src_cp_id);

$avail_map = [];
foreach ($existing_items as $it) {
    $pid = (int)$it['product_id'];
    if ($_use_cp) {
        $sq = $db_conn->prepare("SELECT closing_qty FROM channel_partner_stock WHERE channel_partner_id=? AND product_id=?");
        $sq->bind_param("ii", $_src_cp_id, $pid);
    } elseif ($_use_godown) {
        $uid = (string)$_src_godown_id;
        $sq = $db_conn->prepare("SELECT closing_qty FROM stock WHERE user_type='company' AND user_id=? AND product_id=?");
        $sq->bind_param("si", $uid, $pid);
    } else {
        $sq = $db_conn->prepare("SELECT closing_qty FROM partner_location_stock WHERE partner_location_id=? AND product_id=?");
        $sq->bind_param("ii", $_src_loc_id, $pid);
    }
    $sq->execute();
    $qr = $sq->get_result()->fetch_assoc(); $sq->close();
    // Available = current_closing + already allocated to this invoice (will be restored on edit)
    $avail_map[$pid] = ($qr ? (int)$qr['closing_qty'] : 0) + (int)$it['quantity'];
}

$courier   = (float)$inv['courier_charges'];
$subtotal  = round((float)$inv['total_amount'] - $courier, 2);

// Build JS-safe items array
$js_items = [];
foreach ($existing_items as $it) {
    $pid = (int)$it['product_id'];
    $js_items[] = [
        'product_id' => $pid,
        'name'       => $it['productName'],
        'avail'      => $avail_map[$pid] ?? 0,
        'qty'        => (int)$it['quantity'],
        'rate'       => (float)$it['rate'],
        'amount'     => (float)$it['amount'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit <?php echo htmlspecialchars($inv['invoice_number']); ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
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
        .page-title-modern .menu-link { background:#2563eb; color:white; width:40px; height:40px; border-radius:8px; display:flex; align-items:center; justify-content:center; text-decoration:none; font-size:20px; transition:all .3s; }
        .page-title-modern .menu-link:hover { background:#1d4ed8; }

        .inv-badge { display:inline-block; background:#ede9fe; color:#5b21b6; border-radius:6px; padding:3px 12px; font-family:monospace; font-size:15px; font-weight:700; margin-left:10px; }

        .form-section { background:white; border:2px solid #e5e7eb; border-radius:14px; padding:30px 35px; margin-bottom:22px; box-shadow:0 1px 4px rgba(0,0,0,0.06); }
        .form-section.readonly-section { background:#f8fafc; border-color:#e2e8f0; }
        .section-header { color:#475569; font-size:13px; font-weight:600; text-transform:uppercase; letter-spacing:.6px; margin-bottom:24px; display:flex; align-items:center; gap:10px; padding-bottom:14px; border-bottom:2px solid #f1f5f9; }
        .section-header i { color:#2563eb; font-size:19px; }

        .form-label { font-weight:600; color:#374151; margin-bottom:10px; font-size:13.5px; display:block; }
        .form-label .required { color:#ef4444; margin-left:3px; }
        .form-control { border:2px solid #e5e7eb; border-radius:9px; padding:11px 15px; font-size:14px; font-family:'Poppins',sans-serif; transition:all .3s; }
        .form-control:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.1); }
        .form-control[readonly] { background:#f8fafc; color:#64748b; cursor:not-allowed; }

        .info-block { background:#f8fafc; border:2px solid #e2e8f0; border-radius:8px; padding:12px 16px; font-size:14px; }
        .info-block .label { font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
        .info-block .value { font-weight:600; color:#1e293b; font-size:14px; }
        .info-block .sub { font-size:12px; color:#64748b; margin-top:2px; }

        .balance-panel { display:flex; align-items:center; gap:14px; background:#f0fdf4; border:2px solid #bbf7d0; border-radius:10px; padding:14px 18px; margin-top:18px; transition:all .3s; }
        .balance-panel.warn   { background:#fff7ed; border-color:#fed7aa; }
        .balance-panel.danger { background:#fef2f2; border-color:#fecaca; }
        .balance-panel.loading { background:#f8fafc; border-color:#e2e8f0; }
        .balance-panel-icon { font-size:28px; flex-shrink:0; }
        .balance-panel.ok     .balance-panel-icon { color:#10b981; }
        .balance-panel.warn   .balance-panel-icon { color:#f59e0b; }
        .balance-panel.danger .balance-panel-icon { color:#ef4444; }
        .balance-panel.loading .balance-panel-icon { color:#94a3b8; }
        .balance-panel-body { flex:1; }
        .balance-panel-label { font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.5px; margin-bottom:3px; }
        .balance-panel-amount { font-size:22px; font-weight:700; }
        .balance-panel.ok     .balance-panel-amount { color:#059669; }
        .balance-panel.warn   .balance-panel-amount { color:#d97706; }
        .balance-panel.danger .balance-panel-amount { color:#dc2626; }
        .balance-panel.loading .balance-panel-amount { color:#94a3b8; }
        .balance-panel-note { font-size:12.5px; color:#64748b; margin-top:3px; }
        .balance-panel-action { flex-shrink:0; }
        .balance-panel-action a { display:inline-flex; align-items:center; gap:5px; background:#2563eb; color:white; border-radius:7px; padding:7px 14px; font-size:13px; font-weight:500; text-decoration:none; }

        .product-add-section { background:#f8fafc; border:2px dashed #cbd5e1; border-radius:12px; padding:20px; margin-top:20px; }
        .product-add-grid { display:grid; grid-template-columns:2.5fr 1fr 1fr 1fr auto; gap:14px; align-items:end; }
        .input-group-modern { display:flex; flex-direction:column; }
        .input-group-modern label { font-size:12px; color:#64748b; font-weight:600; margin-bottom:6px; text-transform:uppercase; letter-spacing:.4px; }
        .input-group-modern .form-control { border:2px solid #e5e7eb; border-radius:8px; padding:10px 12px; font-size:14px; }
        .input-group-modern .form-control:focus { border-color:#2563eb; outline:none; box-shadow:0 0 0 3px rgba(37,99,235,0.1); }

        .btn-primary { background:#2563eb; border:none; padding:10px 20px; border-radius:8px; font-weight:500; display:inline-flex; align-items:center; gap:6px; transition:all .3s; font-size:14px; font-family:'Poppins',sans-serif; }
        .btn-primary:hover { background:#1d4ed8; transform:translateY(-1px); box-shadow:0 4px 12px rgba(37,99,235,.3); }
        .btn-primary:disabled { opacity:.5; transform:none; box-shadow:none; cursor:not-allowed; }
        .btn-add { background:#10b981; color:white; border:none; padding:11px 20px; border-radius:8px; font-weight:500; display:inline-flex; align-items:center; gap:6px; transition:all .3s; cursor:pointer; font-family:'Poppins',sans-serif; font-size:14px; white-space:nowrap; }
        .btn-add:hover { background:#059669; }

        .table-modern { background:white; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-top:20px; border:1px solid #f1f5f9; }
        .table-modern table { margin:0; width:100%; }
        .table-modern thead { background:#f8fafc; }
        .table-modern thead th { color:#475569; font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.5px; padding:14px 16px; border-bottom:2px solid #e5e7eb; white-space:nowrap; }
        .table-modern tbody td { padding:13px 16px; vertical-align:middle; border-bottom:1px solid #f1f5f9; color:#1e293b; font-size:14px; }
        .table-modern tbody tr:last-child td { border-bottom:none; }
        .table-modern tbody tr:hover { background:#f8fafc; }
        .table-modern .empty-row td { text-align:center; padding:40px; color:#94a3b8; }
        .row-num { width:28px; height:28px; background:#f1f5f9; color:#64748b; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; }
        .avail-chip { display:inline-block; padding:2px 10px; border-radius:20px; font-size:12px; font-weight:600; background:#d1fae5; color:#065f46; }
        .avail-chip.none { background:#f1f5f9; color:#94a3b8; }
        .badge-remove { background:#fee2e2; color:#991b1b; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:600; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:3px; transition:all .2s; }
        .badge-remove:hover { background:#fecaca; }

        .invoice-summary-card { background:white; border:2px solid #e5e7eb; border-radius:12px; padding:25px; margin-top:20px; }
        .summary-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9; font-size:14px; }
        .summary-row:last-child { border-bottom:none; }
        .summary-label { color:#64748b; font-weight:500; }
        .summary-value { font-weight:600; color:#1e293b; }
        .summary-total { font-size:20px; font-weight:700; color:#10b981; }
        .invoice-info-actions { margin-top:20px; padding-top:20px; border-top:2px solid #f1f5f9; }

        @keyframes spin { to { transform:rotate(360deg); } }
        @media (max-width:992px) { .product-add-grid { grid-template-columns:1fr 1fr 1fr 1fr; } .product-add-grid .input-group-modern:first-child { grid-column:1/-1; } .product-add-grid .input-group-modern:last-child { grid-column:1/-1; } }
        @media (max-width:576px)  { .product-add-grid { grid-template-columns:1fr; } }
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

                    <?php if (isset($_GET['error'])): $err = $_GET['error']; ?>
                    <div class="alert alert-danger">
                        <i class="material-icons" style="vertical-align:middle;margin-right:8px;">error</i>
                        <?php if ($err === 'insufficient'): ?>Insufficient stock at the source location for one or more products.
                        <?php elseif ($err === 'noproducts'): ?>Please add at least one product.
                        <?php elseif ($err === 'nobalance'): ?>Insufficient advance balance. <a href="add-tp-advance-payment" style="color:inherit;font-weight:700;text-decoration:underline;">Add advance payment →</a>
                        <?php else: ?>An error occurred. <?php if (!empty($_GET['msg'])): ?><small style="opacity:.75;">(<?php echo htmlspecialchars(substr($_GET['msg'],0,120)); ?>)</small><?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Page Title -->
                    <div class="page-title-modern">
                        <h1>
                            <i class="material-icons">edit</i>
                            Edit Invoice
                            <span class="inv-badge"><?php echo htmlspecialchars($inv['invoice_number']); ?></span>
                        </h1>
                        <a href="manage-tp-invoices" class="menu-link" title="Back to Invoices">
                            <i class="material-icons">list</i>
                        </a>
                    </div>

                    <form action="edit-tp-invoice-action" method="post" id="invoiceForm">
                        <input type="hidden" name="csrf_token"   value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action"       value="update-tp-invoice">
                        <input type="hidden" name="invoice_enc"  value="<?php echo htmlspecialchars($enc); ?>">
                        <div id="hiddenProductInputs"></div>

                        <!-- ── Invoice Info (read-only) ── -->
                        <div class="form-section readonly-section">
                            <div class="section-header">
                                <i class="material-icons">info</i>
                                Invoice Details
                            </div>
                            <div class="row g-4 align-items-start">
                                <div class="col-lg-4 col-md-6">
                                    <div class="info-block">
                                        <div class="label">Territory Partner</div>
                                        <div class="value"><?php echo htmlspecialchars($inv['tp_name']); ?></div>
                                        <div class="sub"><?php echo htmlspecialchars($inv['tp_code']); ?></div>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <div class="info-block">
                                        <div class="label">Source Location</div>
                                        <div class="value"><?php echo htmlspecialchars($inv['location_name']); ?></div>
                                        <?php if ($inv['cp_name']): ?>
                                        <div class="sub"><?php echo htmlspecialchars($inv['cp_code']); ?> · <?php echo htmlspecialchars($inv['cp_name']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">Invoice Date <span class="required">*</span></label>
                                    <input type="date" name="invoice_date" class="form-control"
                                           value="<?php echo htmlspecialchars($inv['invoice_date']); ?>"
                                           max="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>

                            <!-- Advance Balance Panel -->
                            <div class="balance-panel loading" id="balancePanelInner" style="margin-top:18px;">
                                <i class="material-icons balance-panel-icon">account_balance_wallet</i>
                                <div class="balance-panel-body">
                                    <div class="balance-panel-label">Advance Balance (current — will include restored amount on save)</div>
                                    <div class="balance-panel-amount" id="balancePanelAmount">Loading…</div>
                                    <div class="balance-panel-note" id="balancePanelNote">Fetching…</div>
                                </div>
                                <div class="balance-panel-action" id="balancePanelAction" style="display:none;">
                                    <a href="add-tp-advance-payment"><i class="material-icons" style="font-size:16px;">add</i> Add Payment</a>
                                </div>
                            </div>
                        </div>

                        <!-- ── Add Product ── -->
                        <div class="form-section">
                            <div class="section-header">
                                <i class="material-icons">inventory_2</i>
                                Products
                            </div>

                            <div class="product-add-section">
                                <div class="section-header" style="border:none;padding-bottom:15px;">
                                    <i class="material-icons">add_shopping_cart</i>
                                    Add Product
                                </div>
                                <div class="product-add-grid">
                                    <div class="input-group-modern">
                                        <label>Product <span style="color:#ef4444;">*</span></label>
                                        <select id="productSelect" class="form-control">
                                            <option value="">Loading…</option>
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
                                        <button type="button" class="btn-add" onclick="addProduct()">
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
                                            <td colspan="7"><i class="material-icons" style="font-size:40px;display:block;margin-bottom:10px;color:#cbd5e1;">inventory_2</i>No products added yet</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Summary -->
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
                                                       min="0" step="0.01"
                                                       value="<?php echo number_format($courier, 2, '.', ''); ?>"
                                                       style="width:130px;border:2px solid #e5e7eb;border-radius:7px;padding:7px 12px;text-align:right;font-size:14px;font-family:'Poppins',sans-serif;font-weight:500;"
                                                       onfocus="this.style.borderColor='#2563eb'"
                                                       onblur="this.style.borderColor='#e5e7eb'">
                                            </span>
                                        </div>
                                        <div class="summary-row" style="border-top:2px solid #e5e7eb;margin-top:4px;padding-top:12px;">
                                            <span class="summary-label" style="font-size:16px;font-weight:600;color:#1e293b;">Grand Total</span>
                                            <span class="summary-total" id="grandTotal">₹0.00</span>
                                        </div>
                                        <div class="summary-row" id="summaryBalanceRow" style="display:none;">
                                            <span class="summary-label" style="color:#0369a1;">
                                                <i class="material-icons" style="font-size:15px;vertical-align:middle;margin-right:3px;">account_balance_wallet</i>
                                                Advance Balance (after restore)
                                            </span>
                                            <span class="summary-value" id="summaryBalance" style="color:#0369a1;">—</span>
                                        </div>
                                        <div class="invoice-info-actions">
                                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                                <i class="material-icons">save</i>
                                                Save Changes
                                            </button>
                                            <a href="manage-tp-invoices" class="btn btn-secondary ms-2" style="border-radius:8px;padding:10px 18px;font-size:14px;">Cancel</a>
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

    /* ── Pre-loaded state from PHP ── */
    var sourceCpId     = <?php echo $_src_cp_id; ?>;
    var sourceGodownId = <?php echo $_src_godown_id; ?>;
    var locationId     = <?php echo (int)$inv['source_location_id']; ?>;
    var tpId           = <?php echo (int)$inv['territory_partner_id']; ?>;
    var oldSubtotal    = <?php echo $subtotal; ?>;
    var advanceBalance = 0;
    var invoiceItems   = <?php echo json_encode($js_items); ?>;
    var availableProducts = [];

    function fmtAmt(n) {
        return parseFloat(n).toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 });
    }

    /* ── Fetch balance ── */
    function fetchBalance() {
        $.getJSON('get-tp-advance-balance.php?tp_id=' + tpId, function (res) {
            // Effective balance = current_balance + old_subtotal (will be restored on save)
            var current = res.balance || 0;
            var effective = parseFloat((current + oldSubtotal).toFixed(2));
            advanceBalance = effective;
            if (effective <= 0) {
                setBalancePanel('danger', '₹' + fmtAmt(effective), 'No available balance after restoring current invoice amount.', true);
            } else {
                setBalancePanel('ok', '₹' + fmtAmt(effective), 'Effective balance = current ₹' + fmtAmt(current) + ' + this invoice ₹' + fmtAmt(oldSubtotal) + ' (to be restored on save).', false);
            }
            updateSummary();
        }).fail(function () {
            advanceBalance = 0;
            setBalancePanel('warn', '—', 'Could not load balance. Balance will be checked on save.', false);
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

    /* ── Load products at source ── */
    function loadProducts() {
        $('#productSelect').html('<option value="">Loading…</option>').prop('disabled', true);
        var url = sourceCpId
            ? 'get-tp-location-products.php?cp_id=' + sourceCpId
            : (sourceGodownId ? 'get-godown-tp-products.php?godown_id=' + sourceGodownId
                              : 'get-tp-location-products.php?location_id=' + locationId);
        $.getJSON(url, function (data) {
            availableProducts = data;
            var loadedPids = {};
            var opts = '<option value="">— Select Product —</option>';
            $.each(data, function (_, p) {
                loadedPids[p.product_id] = true;
                var alreadyAdded = invoiceItems.find(function(it){ return it.product_id === p.product_id; });
                // Effective available = location current + what we already allocated in existing items
                var effAvail = p.available_qty + (alreadyAdded ? alreadyAdded.qty : 0);
                opts += '<option value="' + p.product_id + '" data-avail="' + effAvail + '" data-rate="' + p.rate + '">' + escHtml(p.productName) + '</option>';
            });
            // Also include existing invoice products that are at 0 stock (fully consumed by this invoice)
            $.each(invoiceItems, function (_, item) {
                if (!loadedPids[item.product_id]) {
                    opts += '<option value="' + item.product_id + '" data-avail="' + item.qty + '" data-rate="' + item.rate + '">' + escHtml(item.name) + '</option>';
                }
            });
            $('#productSelect').html(opts).prop('disabled', false);
        }).fail(function () {
            $('#productSelect').html('<option value="">Error loading</option>');
        });
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
        var $opt       = $('#productSelect').find('option:selected');
        var product_id = parseInt($('#productSelect').val());
        var name       = $opt.text().trim();
        var avail      = parseInt($('#availQty').val()) || 0;
        var qty        = parseInt($('#qtyInput').val()) || 0;
        var rate       = parseFloat($('#rateInput').val()) || 0;

        if (!product_id) { showAddError('Please select a product.'); return; }
        if (qty < 1)     { showAddError('Quantity must be at least 1.'); return; }
        if (rate <= 0)   { showAddError('Please enter a valid rate.'); return; }
        if (qty > avail) { showAddError('Quantity exceeds available stock (' + avail + ').'); return; }
        if (invoiceItems.find(function(i){ return i.product_id === product_id; })) {
            showAddError('Already added. Remove it first to change quantity.'); return;
        }

        var amount = parseFloat((qty * rate).toFixed(2));
        invoiceItems.push({ product_id:product_id, name:name, avail:avail, qty:qty, rate:rate, amount:amount });
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
            $body.append($('<tr></tr>').html(
                '<td><span class="row-num">' + (i+1) + '</span></td>' +
                '<td><strong>' + escHtml(item.name) + '</strong></td>' +
                '<td><span class="avail-chip' + (item.avail > 0 ? '' : ' none') + '">' + item.avail + '</span></td>' +
                '<td>' + item.qty + '</td>' +
                '<td>₹' + item.rate.toFixed(2) + '</td>' +
                '<td><strong>₹' + item.amount.toFixed(2) + '</strong></td>' +
                '<td><button type="button" class="badge-remove" onclick="removeProduct(' + i + ')"><i class="material-icons" style="font-size:14px;vertical-align:middle;">delete</i> Remove</button></td>'
            ));
        });
        updateSummary();
        buildHiddenInputs();
    }

    /* ── Courier recalc ── */
    $('#courierInput').on('input change', function () { updateSummary(); });

    /* ── Summary ── */
    function updateSummary() {
        var subtotal = 0, qty = 0;
        $.each(invoiceItems, function (_, item) { subtotal += item.amount; qty += item.qty; });
        subtotal = parseFloat(subtotal.toFixed(2));
        var courier = Math.max(0, parseFloat($('#courierInput').val()) || 0);
        var total   = parseFloat((subtotal + courier).toFixed(2));

        $('#summaryItems').text(invoiceItems.length);
        $('#summaryQty').text(qty);
        $('#summarySubtotal').text('₹' + fmtAmt(subtotal));
        $('#grandTotal').text('₹' + fmtAmt(total));

        // Balance after save = effective_balance - new_subtotal
        if (advanceBalance > 0) {
            var remaining = parseFloat((advanceBalance - subtotal).toFixed(2));
            $('#summaryBalance').text((remaining >= 0 ? '₹' : '−₹') + fmtAmt(Math.abs(remaining)))
                                .css('color', remaining >= 0 ? '#059669' : '#dc2626');
            $('#summaryBalanceRow').show();
        }

        $('#submitBtn').prop('disabled', false);
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

    /* ── Form submit ── */
    $('#invoiceForm').on('submit', function (e) {
        buildHiddenInputs();
        $('#submitBtn').prop('disabled', true).html('<i class="material-icons" style="animation:spin 1s linear infinite;font-size:18px;">refresh</i> Saving…');
    });

    /* ── Helpers ── */
    function resetAddForm() {
        $('#productSelect').val('');
        $('#availQty').val('');
        $('#qtyInput').val('');
        $('#rateInput').val('');
        hideAddError();
    }
    function showAddError(msg) { $('#addError').html('<div class="alert alert-danger mb-0" style="font-size:13px;padding:10px 14px;">' + msg + '</div>').show(); }
    function hideAddError() { $('#addError').hide().empty(); }
    function escHtml(str) { return $('<div>').text(str).html(); }

    /* ── Init ── */
    $(document).ready(function () {
        fetchBalance();
        loadProducts();
        renderTable();
    });

}(jQuery));
</script>
</body>
</html>
