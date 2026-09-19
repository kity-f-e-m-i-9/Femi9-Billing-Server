<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/NeksomoStockBridge.php");
require_once("include/AutoTransferDemand.php");
include("config.php");
date_default_timezone_set("Asia/Kolkata");

$neksomoId    = get_neksomo_godown_id($db_conn);
$healthcareId = resolve_godown_id_by_gname($db_conn, 'FEMI HEALTH CARE');
$llpId        = resolve_godown_id_by_gname($db_conn, 'FEMI NAYAN LLP');

if (!$neksomoId || !$healthcareId || !$llpId) {
    die("Auto Transfer is unavailable: one or more required company profiles "
        . "(Neksomo / FEMI HEALTH CARE / FEMI NAYAN LLP) could not be found "
        . "in company_godown.");
}

$stockService = new StockService($db_conn);
$requirements = get_auto_transfer_requirements($db_conn, $llpId);
$defaultRates = get_auto_transfer_default_rates($db_conn);

$rows = [];
if (!empty($requirements)) {
    $productIds = array_keys($requirements);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = str_repeat('i', count($productIds));
    $stmt = $db_conn->prepare("SELECT id, productName FROM products WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$productIds);
    $stmt->execute();
    $productResult = $stmt->get_result();
    $productNames = [];
    while ($p = $productResult->fetch_assoc()) {
        $productNames[(int) $p['id']] = $p['productName'];
    }
    $stmt->close();

    foreach ($requirements as $pid => $required) {
        $neksomoAvail    = (int) ($stockService->getClosingQty($pid, $Login_user_TYPEvl, (string) $neksomoId) ?? 0);
        $healthcareAvail = (int) ($stockService->getClosingQty($pid, $Login_user_TYPEvl, (string) $healthcareId) ?? 0);
        $cappedQty       = cap_auto_transfer_qty($required, $neksomoAvail, $healthcareAvail);
        if ($cappedQty <= 0) continue;

        $rows[] = [
            'product_id'      => $pid,
            'product_name'    => $productNames[$pid] ?? "Product #$pid",
            'required'        => $required,
            'capped'          => $cappedQty,
            'neksomo_avail'   => $neksomoAvail,
            'healthcare_avail'=> $healthcareAvail,
            'rate_healthcare' => $defaultRates[$pid]['healthcare'] ?? null,
            'rate_llp'        => $defaultRates[$pid]['llp'] ?? null,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auto Transfer for Orders : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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

                    <?php
                    if (isset($_SESSION['sucMessage'])) {
                        $flashMsg = htmlspecialchars($_SESSION['sucMessage'], ENT_QUOTES, 'UTF-8');
                        unset($_SESSION['sucMessage']);
                        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
                           . '<script>Swal.fire({icon:"success",title:"Success",text:"' . $flashMsg . '",confirmButtonText:"OK"});</script>';
                    }
                    if (isset($_SESSION['errorMessage'])) {
                        $flashErr = htmlspecialchars($_SESSION['errorMessage'], ENT_QUOTES, 'UTF-8');
                        unset($_SESSION['errorMessage']);
                        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
                           . '<script>Swal.fire({icon:"error",title:"Error",text:"' . $flashErr . '",confirmButtonText:"OK"});</script>';
                    }
                    ?>

                    <div class="page-description">
                        <h1>
                            <table class="headertble">
                                <tr>
                                    <td>Auto Transfer for Orders</td>
                                    <td><a href="internal_transfer_manage" title="Manage Internal Stock Transfer">&#9776;</a></td>
                                </tr>
                            </table>
                        </h1>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <p class="text-muted">
                                        Quantities required for every still-waiting Territory Partner purchase
                                        order and drafted OT channel order (LLP) — regardless of when it was
                                        raised — auto-capped to available Neksomo + Healthcare stock. Adjust
                                        any row before transferring — Neksomo &rarr; Healthcare &rarr; LLP,
                                        both legs move in one click.
                                    </p>

                                    <?php if (!empty($rows)): ?>
                                    <button type="button" class="btn btn-sm" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);border:none;color:#fff;margin-bottom:14px;" onclick="openOrdersOverview()">
                                        <i class="material-icons" style="font-size:15px;vertical-align:middle;">list_alt</i> View All Orders
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm" style="background:linear-gradient(135deg, #0891b2 0%, #0e7490 100%);border:none;color:#fff;margin-bottom:14px;margin-left:8px;box-shadow:0 2px 6px rgba(8,145,178,.3);" onclick="openTransferHistory()">
                                        <i class="material-icons" style="font-size:15px;vertical-align:middle;">history</i> Transfer History
                                    </button>

                                    <?php if (empty($rows)): ?>
                                        <div class="alert alert-info">Nothing to transfer today.</div>
                                    <?php else: ?>
                                        <form method="post" action="internal_transfer_auto_action.php" id="autoTransferForm" onsubmit="return confirmAutoTransferSubmit(event);">
                                            <div style="overflow-x:auto;">
                                            <table class="table table-bordered" style="min-width:1000px;">
                                                <thead>
                                                    <tr>
                                                        <th>Product</th>
                                                        <th>Required Qty</th>
                                                        <th>Available (Neksomo)</th>
                                                        <th>Available (Healthcare)</th>
                                                        <th>Qty to Transfer</th>
                                                        <th>Rate to Health Care (Rs.)</th>
                                                        <th>Rate to LLP (Rs.)</th>
                                                        <th>Breakdown</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($rows as $row): ?>
                                                    <tr class="auto-transfer-row" data-product-id="<?php echo (int) $row['product_id']; ?>" data-product-name="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" data-neksomo-avail="<?php echo (int) $row['neksomo_avail']; ?>" data-healthcare-avail="<?php echo (int) $row['healthcare_avail']; ?>">
                                                        <td>
                                                            <?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                            <input type="hidden" name="product_id[]" value="<?php echo (int) $row['product_id']; ?>">
                                                        </td>
                                                        <td id="req_<?php echo (int) $row['product_id']; ?>"><?php echo (int) $row['required']; ?></td>
                                                        <td><?php echo (int) $row['neksomo_avail']; ?></td>
                                                        <td><?php echo (int) $row['healthcare_avail']; ?></td>
                                                        <td>
                                                            <input type="number" min="0" name="qty[]" id="qty_<?php echo (int) $row['product_id']; ?>"
                                                                   value="<?php echo (int) $row['capped']; ?>" class="form-control">
                                                        </td>
                                                        <td>
                                                            <input type="number" min="0" step="0.01" name="rate1[]" placeholder="Rate(Rs.)" class="form-control"
                                                                   value="<?php echo $row['rate_healthcare'] !== null ? htmlspecialchars((string) $row['rate_healthcare'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                                        </td>
                                                        <td>
                                                            <input type="number" min="0" step="0.01" name="rate2[]" placeholder="Rate(Rs.)" class="form-control"
                                                                   value="<?php echo $row['rate_llp'] !== null ? htmlspecialchars((string) $row['rate_llp'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm"
                                                                    style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);border:none;color:#fff;"
                                                                    onclick="openBreakdown(<?php echo (int) $row['product_id']; ?>, <?php echo (int) $row['neksomo_avail']; ?>, <?php echo (int) $row['healthcare_avail']; ?>)">
                                                                <i class="material-icons" style="font-size:14px;vertical-align:middle;">list_alt</i> View
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="material-icons" style="font-size:16px;vertical-align:middle;">sync_alt</i> Transfer Now
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Breakdown modal: which orders make up a product's Required Qty, with
     the option to exclude one specific order from today's transfer (its
     own status/record is never touched — only what this popup sums and
     submits changes). -->
<div class="modal fade" id="breakdownModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                <h6 class="modal-title" style="font-weight:600;color:#1f2937;">
                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#2563eb;">list_alt</i>
                    Order Breakdown
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:14px 20px;">
                <p class="text-muted small">
                    Uncheck an order + Apply to leave it out of just this view (comes back if you
                    reopen this page). The order itself stays exactly as it is
                    (still waiting/draft), only its stock movement is postponed.
                </p>
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#bdTpPane" type="button">TP Purchase Orders</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#bdOtPane" type="button">OT Channel Orders</button></li>
                </ul>
                <div class="tab-content" style="padding-top:10px;">
                    <div class="tab-pane fade show active" id="bdTpPane"><div id="bdTpList"></div></div>
                    <div class="tab-pane fade" id="bdOtPane"><div id="bdOtList"></div></div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="applyBreakdown()">Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- "View All Orders" — every order contributing to today's transfer, one
     row per PO/OT-invoice with ALL of its own products listed underneath
     (not scoped to a single product, unlike the modal above). Uncheck an
     order/product + Apply recomputes each affected product row's Required
     Qty / Qty to Transfer for this view only (same as the per-product
     breakdown modal's own checkbox+Apply) — nothing is persisted, so it
     resets on reload. -->
<div class="modal fade" id="ordersOverviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                <h6 class="modal-title" style="font-weight:600;color:#1f2937;">
                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#2563eb;">list_alt</i>
                    View All Orders
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:14px 20px;">
                <p class="text-muted small">
                    Every order behind today's transfer, with all of its own products. Uncheck one
                    product (e.g. if just that item has a stock problem) or the whole order's
                    checkbox, then Apply — this only recomputes the current view (nothing is saved),
                    so it resets if you close and reopen this page.
                </p>
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ovTpPane" type="button">TP Purchase Orders</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ovOtPane" type="button">OT Channel Orders</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ovExcludedPane" type="button" onclick="loadExcludedToday()">Already Transferred Today</button></li>
                </ul>
                <div class="tab-content" style="padding-top:10px;">
                    <div class="tab-pane fade show active" id="ovTpPane"><div id="ovTpList"></div></div>
                    <div class="tab-pane fade" id="ovOtPane"><div id="ovOtList"></div></div>
                    <div class="tab-pane fade" id="ovExcludedPane">
                        <p class="text-muted small">
                            Orders whose stock already moved earlier today via Transfer Now —
                            grouped by order, for reference only.
                        </p>
                        <div id="ovExcludedList"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="applyOrdersOverview()">Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- "Transfer History" — per-product before-stock (Neksomo/Healthcare/LLP)
     and qty transferred, for every auto-transfer run on a chosen date.
     Read entirely from stock_ledger's own qty_before/created_at — nothing
     new is tracked, this just surfaces what StockService already logged. -->
<div class="modal fade" id="transferHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                <h6 class="modal-title" style="font-weight:600;color:#1f2937;">
                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#6b7280;">history</i>
                    Auto Transfer History
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:14px 20px;">
                <div style="display:flex;align-items:end;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
                    <div>
                        <label class="form-label" style="font-size:12.5px;font-weight:600;color:#6b7280;">Date</label>
                        <input type="date" id="thDateInput" class="form-control form-control-sm" style="width:170px;">
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" onclick="loadTransferHistory()">Show</button>
                </div>
                <div id="thResult"></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
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
    var currentBreakdownPid    = null;
    var currentNeksomoAvail    = 0;
    var currentHealthcareAvail = 0;

    function escBd(str) { return $('<div>').text(str == null ? '' : str).html(); }

    // Warns before submitting if any product has zero stock at the first
    // leg's source (Neksomo) — the backend already silently caps/skips a
    // product with insufficient Neksomo stock (see
    // internal_transfer_auto_action.php's $writeLeg(), which computes
    // actualQty = min(requested, available) and continues past a product
    // whose first leg moves 0), so without this warning that skip happens
    // invisibly. This only flags products with NO stock at all
    // (neksomo_avail <= 0) — a partial shortfall still transfers what's
    // available and is left to the existing "Capped: ..." success message.
    function confirmAutoTransferSubmit(e) {
        var zeroStockNames = [];
        document.querySelectorAll('.auto-transfer-row').forEach(function (row) {
            var avail = parseInt(row.getAttribute('data-neksomo-avail'), 10) || 0;
            if (avail <= 0) zeroStockNames.push(row.getAttribute('data-product-name'));
        });

        if (zeroStockNames.length > 0) {
            var msg = 'These product(s) have no stock in Neksomo and will be skipped:\n\n'
                + zeroStockNames.map(function (n) { return '- ' + n; }).join('\n')
                + '\n\nProceed with transferring the remaining products?';
            if (!confirm(msg)) {
                e.preventDefault();
                return false;
            }
            return true;
        }

        return confirm('Transfer these quantities now?');
    }

    // Remembers each breakdown line's checked/qty state across reopens of
    // this modal (and across switching between products) within this page
    // load — same rationale/pattern as ovLineState for View All Orders.
    // Keyed by source_id itself ("tp:<po_id>:<product_id>" /
    // "ot:<tempid>:<product_id>"), which is already unique per order+product.
    var bdLineState = {};

    function renderBreakdownTab(containerId, items, emptyMsg) {
        var el = document.getElementById(containerId);
        if (!items || !items.length) {
            el.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">' + emptyMsg + '</div>';
            return;
        }
        var html = '';
        items.forEach(function (it) {
            var remembered = bdLineState[it.source_id];
            var isChecked = remembered ? remembered.checked : true;
            var qtyVal = remembered ? remembered.qty : it.qty;
            html += '<div class="bd-row" data-source-id="' + it.source_id + '" style="display:flex;justify-content:space-between;align-items:center;gap:10px;border-bottom:1px solid #f1f5f9;padding:8px 4px;flex-wrap:wrap;">' +
                '<label style="display:flex;align-items:center;flex:1;cursor:pointer;margin:0;min-width:160px;">' +
                    '<input type="checkbox" class="bd-check" data-source-id="' + it.source_id + '"' + (isChecked ? ' checked' : '') + ' style="margin-right:8px;flex-shrink:0;">' +
                    '<span style="overflow-wrap:anywhere;">' + escBd(it.label) + '</span>' +
                '</label>' +
                '<input type="number" min="0" max="' + it.qty + '" class="form-control form-control-sm bd-qty-input" data-source-id="' + it.source_id + '" ' +
                    'value="' + qtyVal + '" style="width:80px;flex-shrink:0;" title="Max ' + it.qty + ' — this order\'s own qty">' +
            '</div>';
        });
        el.innerHTML = html;
        // Persist every line's state as soon as it changes, not just on
        // Apply — so reopening the modal without ever clicking Apply
        // still shows what the user last set.
        el.querySelectorAll('.bd-row').forEach(function (rowEl) {
            var checkbox = rowEl.querySelector('.bd-check');
            var qtyInput = rowEl.querySelector('.bd-qty-input');
            checkbox.addEventListener('change', function () { bdSaveLineState(rowEl); });
            qtyInput.addEventListener('input', function () { bdSaveLineState(rowEl); });
        });
    }

    function bdSaveLineState(rowEl) {
        var sourceId = rowEl.getAttribute('data-source-id');
        var checkbox = rowEl.querySelector('.bd-check');
        var qtyInput = rowEl.querySelector('.bd-qty-input');
        var qty = parseInt(qtyInput.value, 10);
        if (isNaN(qty) || qty < 0) qty = 0;
        bdLineState[sourceId] = { checked: checkbox.checked, qty: qty };
    }

    // Recomputes the currently-open product's Required Qty + Qty to
    // Transfer from whatever's still checked across all three tabs —
    // shared by the plain "Apply" button.
    function recomputeCurrentRowFromCheckboxes() {
        if (currentBreakdownPid === null) return;
        var total = 0;
        document.querySelectorAll('.bd-check:checked').forEach(function (chk) {
            var sourceId = chk.getAttribute('data-source-id');
            var qtyInput = document.querySelector('.bd-qty-input[data-source-id="' + sourceId.replace(/"/g, '') + '"]');
            var maxQty = qtyInput ? parseInt(qtyInput.getAttribute('max'), 10) || 0 : 0;
            var val = qtyInput ? parseInt(qtyInput.value, 10) : 0;
            if (isNaN(val) || val < 0) val = 0;
            if (val > maxQty) val = maxQty; // never more than that order actually needs
            if (qtyInput) qtyInput.value = val;
            total += val;
        });

        var pid = currentBreakdownPid;
        document.getElementById('req_' + pid).textContent = total;

        var capped = Math.min(total, currentNeksomoAvail + currentHealthcareAvail);
        if (capped < 0) capped = 0;
        document.getElementById('qty_' + pid).value = capped;
    }

    function openBreakdown(pid, neksomoAvail, healthcareAvail) {
        currentBreakdownPid    = pid;
        currentNeksomoAvail    = neksomoAvail;
        currentHealthcareAvail = healthcareAvail;

        var loading = '<div class="text-muted small" style="padding:10px 4px;">Loading&hellip;</div>';
        document.getElementById('bdTpList').innerHTML = loading;
        document.getElementById('bdOtList').innerHTML = loading;

        $.getJSON('get-auto-transfer-breakdown.php', { product_id: pid }, function (data) {
            renderBreakdownTab('bdTpList', data.tp, 'No Territory Partner orders for this product today.');
            renderBreakdownTab('bdOtList', data.ot, 'No OT channel draft orders for this product today.');
        }).fail(function () {
            var failMsg = '<div class="text-danger small" style="padding:10px 4px;">Could not load breakdown.</div>';
            document.getElementById('bdTpList').innerHTML = failMsg;
            document.getElementById('bdOtList').innerHTML = failMsg;
        });

        var modal = new bootstrap.Modal(document.getElementById('breakdownModal'));
        modal.show();
    }

    function applyBreakdown() {
        recomputeCurrentRowFromCheckboxes();
        var modalEl = document.getElementById('breakdownModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }

    // ── "View All Orders" — order-level overview, all products per order.
    // Uncheck an order/product + Apply recomputes each affected product
    // row in the main table for this view only — nothing is persisted. ──
    // Remembers each product-in-order line's checked/qty state across
    // reopens of the modal within this page load — without this, every
    // openOrdersOverview() call re-fetches fresh data and rebuilds every
    // checkbox back to "checked" / every qty back to its max, silently
    // discarding whatever the user had unchecked/edited on a previous
    // Apply. Keyed by "<order_key>:<product_id>" since the same product
    // can appear in more than one order, each independently
    // checked/unchecked.
    var ovLineState = {};

    function ovRenderOrderList(containerId, orders, emptyMsg) {
        var el = document.getElementById(containerId);
        if (!orders || !orders.length) {
            el.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">' + emptyMsg + '</div>';
            return;
        }
        var html = '';
        orders.forEach(function (order) {
            var productsHtml = order.products.map(function (p) {
                var lineKey = order.order_key + ':' + p.product_id;
                var remembered = ovLineState[lineKey];
                var isChecked = remembered ? remembered.checked : true;
                var qtyVal = remembered ? remembered.qty : p.qty;
                return '<div class="ov-product-row" data-line-key="' + escBd(lineKey) + '" data-product-id="' + p.product_id + '" style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:5px 0;font-size:12.5px;color:#4b5563;">' +
                    '<label style="display:flex;align-items:center;flex:1;cursor:pointer;margin:0;min-width:0;">' +
                        '<input type="checkbox" class="ov-product-check"' + (isChecked ? ' checked' : '') + ' style="margin-right:8px;flex-shrink:0;">' +
                        '<span style="overflow-wrap:anywhere;">' + escBd(p.product_name) + '</span>' +
                    '</label>' +
                    '<input type="number" min="0" max="' + p.qty + '" class="form-control form-control-sm ov-product-qty" ' +
                        'value="' + qtyVal + '" style="width:75px;flex-shrink:0;" title="Max ' + p.qty + '">' +
                '</div>';
            }).join('');
            // Whole-order checkbox reflects the current state too — checked
            // only when every one of its own product lines is checked.
            var allChecked = order.products.every(function (p) {
                var remembered = ovLineState[order.order_key + ':' + p.product_id];
                return remembered ? remembered.checked : true;
            });
            html += '<div class="ov-order" style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:10px;">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">' +
                    '<label style="display:flex;align-items:center;flex:1;cursor:pointer;margin:0;min-width:160px;font-weight:600;">' +
                        '<input type="checkbox" class="ov-check"' + (allChecked ? ' checked' : '') + ' style="margin-right:8px;flex-shrink:0;" title="Select/deselect every product in this order">' +
                        '<span style="overflow-wrap:anywhere;">' + escBd(order.label) + '</span>' +
                    '</label>' +
                '</div>' +
                '<div style="margin-top:6px;padding-left:26px;border-top:1px solid #f1f5f9;padding-top:6px;">' + productsHtml + '</div>' +
            '</div>';
        });
        el.innerHTML = html;
        // Order checkbox is a select-all/deselect-all toggle for its own
        // product checkboxes — the real, individually-actionable state
        // lives on each .ov-product-check, not on this one.
        el.querySelectorAll('.ov-check').forEach(function (orderCheck) {
            orderCheck.addEventListener('change', function () {
                var rowEl = orderCheck.closest('.ov-order');
                rowEl.querySelectorAll('.ov-product-check').forEach(function (pc) {
                    pc.checked = orderCheck.checked;
                    ovSaveLineState(pc.closest('.ov-product-row'));
                });
            });
        });
        // Persist every product line's state as soon as it changes, not
        // just on Apply — so reopening the modal without ever clicking
        // Apply still shows what the user last set.
        el.querySelectorAll('.ov-product-row').forEach(function (rowEl) {
            var checkbox = rowEl.querySelector('.ov-product-check');
            var qtyInput = rowEl.querySelector('.ov-product-qty');
            checkbox.addEventListener('change', function () { ovSaveLineState(rowEl); });
            qtyInput.addEventListener('input', function () { ovSaveLineState(rowEl); });
        });
    }

    function ovSaveLineState(rowEl) {
        var lineKey = rowEl.getAttribute('data-line-key');
        var checkbox = rowEl.querySelector('.ov-product-check');
        var qtyInput = rowEl.querySelector('.ov-product-qty');
        var qty = parseInt(qtyInput.value, 10);
        if (isNaN(qty) || qty < 0) qty = 0;
        ovLineState[lineKey] = { checked: checkbox.checked, qty: qty };
    }

    // Recomputes every affected product row in the main table from
    // whatever's currently checked across both View All Orders tabs — a
    // product can be checked/unchecked from more than one order, so this
    // sums across every .ov-product-row for the same product_id rather
    // than acting on a single row at a time. View-only: nothing is sent
    // to the server, so this resets on reload just like the per-product
    // breakdown modal's own checkbox+Apply.
    function applyOrdersOverview() {
        // seenProductIds tracks every product that appears anywhere in the
        // overview, checked or not — totalsByProduct alone can't tell "this
        // product had 0 checked" apart from "this product never appeared
        // here at all," since an unchecked row contributes nothing to
        // either. Without this distinction, unchecking every row for a
        // product looked like a no-op instead of zeroing it out.
        var totalsByProduct = {};
        var seenProductIds = {};
        document.querySelectorAll('#ovTpList .ov-product-row, #ovOtList .ov-product-row').forEach(function (rowEl) {
            var pid = rowEl.getAttribute('data-product-id');
            seenProductIds[pid] = true;
            var checkbox = rowEl.querySelector('.ov-product-check');
            var qtyInput = rowEl.querySelector('.ov-product-qty');
            if (!checkbox.checked) { ovSaveLineState(rowEl); return; }
            var maxQty = parseInt(qtyInput.getAttribute('max'), 10) || 0;
            var val = parseInt(qtyInput.value, 10);
            if (isNaN(val) || val < 0) val = 0;
            if (val > maxQty) val = maxQty;
            qtyInput.value = val;
            ovSaveLineState(rowEl);
            totalsByProduct[pid] = (totalsByProduct[pid] || 0) + val;
        });

        document.querySelectorAll('.auto-transfer-row').forEach(function (row) {
            var pid = row.getAttribute('data-product-id');
            if (!(pid in seenProductIds)) return; // this product has no overview rows at all — leave untouched
            var total = totalsByProduct[pid] || 0; // 0 when every row for this product was unchecked
            var reqEl = document.getElementById('req_' + pid);
            if (reqEl) reqEl.textContent = total;
            var neksomoAvail = parseInt(row.getAttribute('data-neksomo-avail'), 10) || 0;
            var healthcareAvail = parseInt(row.getAttribute('data-healthcare-avail'), 10) || 0;
            var capped = Math.min(total, neksomoAvail + healthcareAvail);
            if (capped < 0) capped = 0;
            var qtyEl = document.getElementById('qty_' + pid);
            if (qtyEl) qtyEl.value = capped;
        });

        var modalEl = document.getElementById('ordersOverviewModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }

    function openOrdersOverview() {
        var loading = '<div class="text-muted small" style="padding:10px 4px;">Loading&hellip;</div>';
        document.getElementById('ovTpList').innerHTML = loading;
        document.getElementById('ovOtList').innerHTML = loading;

        $.getJSON('get-auto-transfer-orders-overview.php', {}, function (data) {
            ovRenderOrderList('ovTpList', data.tp, 'No Territory Partner orders contributing today.');
            ovRenderOrderList('ovOtList', data.ot, 'No OT channel draft orders contributing today.');
        }).fail(function () {
            var failMsg = '<div class="text-danger small" style="padding:10px 4px;">Could not load orders.</div>';
            document.getElementById('ovTpList').innerHTML = failMsg;
            document.getElementById('ovOtList').innerHTML = failMsg;
        });

        var modal = new bootstrap.Modal(document.getElementById('ordersOverviewModal'));
        modal.show();
    }

    // ── "Already Transferred Today" — read-only, grouped by order ──
    function loadExcludedToday() {
        var el = document.getElementById('ovExcludedList');
        el.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">Loading&hellip;</div>';

        $.getJSON('get-auto-transfer-skipped.php', {}, function (data) {
            var all = (data.tp || []).concat(data.ot || []);
            if (!all.length) {
                el.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">Nothing transferred yet today.</div>';
                return;
            }

            // Group by order: source_id is "tp:<orderKey>:<productId>" /
            // "ot:<orderKey>:<productId>" — group key is everything but the
            // trailing productId segment.
            var groups = [];
            var groupsByKey = {};
            all.forEach(function (item) {
                var lastColon = item.source_id.lastIndexOf(':');
                var groupKey = item.source_id.substring(0, lastColon);
                if (!groupsByKey[groupKey]) {
                    groupsByKey[groupKey] = { label: item.label, items: [] };
                    groups.push(groupsByKey[groupKey]);
                }
                groupsByKey[groupKey].items.push(item);
            });

            var html = '';
            groups.forEach(function (group) {
                html += '<div class="ov-excl-group" style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:10px;">' +
                    '<div style="font-weight:600;overflow-wrap:anywhere;">' + escBd(group.label) + '</div>';
                group.items.forEach(function (item) {
                    html += '<div style="display:flex;align-items:center;gap:8px;padding:5px 0 0 4px;">' +
                        '<span style="color:#9ca3af;">' + escBd(item.product_name) + '</span>' +
                        '<span class="badge" style="background:#d1fae5;color:#065f46;">Already transferred</span>' +
                    '</div>';
                });
                html += '</div>';
            });
            el.innerHTML = html;
        }).fail(function () {
            el.innerHTML = '<div class="text-danger small" style="padding:10px 4px;">Could not load excluded orders.</div>';
        });
    }

    // ── "Transfer History" ──
    function openTransferHistory() {
        var dateInput = document.getElementById('thDateInput');
        if (!dateInput.value) {
            dateInput.value = new Date().toISOString().slice(0, 10);
        }
        document.getElementById('thResult').innerHTML = '';
        var modal = new bootstrap.Modal(document.getElementById('transferHistoryModal'));
        modal.show();
        loadTransferHistory();
    }

    function thFmtStock(v) { return v === null ? '<span class="text-muted">&mdash;</span>' : v; }

    function thFmtDateTime(v) {
        if (!v) return '<span class="text-muted">&mdash;</span>';
        var d = new Date(v.replace(' ', 'T'));
        if (isNaN(d.getTime())) return escBd(v);
        return d.toLocaleDateString('en-IN') + ' ' + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
    }

    // Invoice-wise layout, matching Manage Internal Stock Transfer: one
    // row per Auto Transfer run (one "Transfer Now" click, identified by
    // its shared tempid pair), one column per product that appears in
    // ANY run on this date (union across runs, same convention the
    // manage page uses for its per-product columns) — 0/blank for a run
    // that didn't move that product.
    function loadTransferHistory() {
        var date = document.getElementById('thDateInput').value;
        if (!date) return;
        var resultEl = document.getElementById('thResult');
        resultEl.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">Loading&hellip;</div>';

        $.getJSON('get-auto-transfer-history.php', { date: date }, function (data) {
            if (data.error) {
                resultEl.innerHTML = '<div class="text-danger small" style="padding:10px 4px;">' + escBd(data.error) + '</div>';
                return;
            }
            if (!data.runs || !data.runs.length) {
                resultEl.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">No auto-transfers found on this date.</div>';
                return;
            }

            // Union of every product across every run, in first-seen order.
            var productColumns = [];
            var seenProductIds = {};
            data.runs.forEach(function (run) {
                run.products.forEach(function (p) {
                    if (!seenProductIds[p.product_id]) {
                        seenProductIds[p.product_id] = true;
                        productColumns.push({ id: p.product_id, name: p.product_name });
                    }
                });
            });

            var html = '<div style="overflow-x:auto;"><table class="table table-bordered table-sm" style="min-width:' + (700 + productColumns.length * 90) + 'px;">' +
                '<thead><tr style="background:#f8fafc;">' +
                    '<th>Date &amp; Time</th>' +
                    '<th>Invoice (Neksomo&rarr;Healthcare)</th>' +
                    '<th>Invoice (Healthcare&rarr;LLP)</th>';
            productColumns.forEach(function (col) { html += '<th>' + escBd(col.name) + '</th>'; });
            html += '<th>Undo</th></tr></thead><tbody>';

            data.runs.forEach(function (run) {
                var qtyByProductId = {};
                run.products.forEach(function (p) { qtyByProductId[p.product_id] = p.qty_transferred; });
                var runProductIds = run.products.map(function (p) { return p.product_id; });

                html += '<tr data-product-ids="' + escBd(JSON.stringify(runProductIds)) + '">' +
                    '<td>' + thFmtDateTime(run.transferred_at) + '</td>' +
                    '<td>' + (run.inv_number_leg1 ? escBd(run.inv_number_leg1) : '<span class="text-muted">&mdash;</span>') + '</td>' +
                    '<td>' + (run.inv_number_leg2 ? escBd(run.inv_number_leg2) : '<span class="text-muted">&mdash;</span>') + '</td>';
                productColumns.forEach(function (col) {
                    var qty = qtyByProductId[col.id];
                    html += '<td' + (qty ? ' style="font-weight:600;"' : '') + '>' + (qty || '<span class="text-muted">&mdash;</span>') + '</td>';
                });
                html += '<td>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger th-undo" ' +
                            'data-tempid="' + escBd(run.tempid) + '" ' +
                            'style="white-space:nowrap;font-size:11px;padding:2px 8px;">Undo</button>' +
                    '</td>' +
                '</tr>';
            });

            html += '</tbody></table></div>';
            resultEl.innerHTML = html;

            resultEl.querySelectorAll('.th-undo').forEach(function (btn) {
                btn.addEventListener('click', function () { undoAutoTransfer(this); });
            });
        }).fail(function () {
            resultEl.innerHTML = '<div class="text-danger small" style="padding:10px 4px;">Could not load history.</div>';
        });
    }

    // Reverses every product moved in one Auto Transfer run (both legs:
    // Neksomo -> Healthcare -> LLP, for every product in that run) via
    // undo-auto-transfer.php, then reloads the whole page so the main
    // table's Required Qty / Available (Neksomo/Healthcare) columns pick
    // up the restored stock. If any single product in the run can't be
    // undone (its stock already moved on further down the chain), the
    // ones that succeeded stay undone — reported so the user can
    // reconcile the rest manually rather than silently losing partial
    // progress.
    function undoAutoTransfer(btn) {
        var tempid = btn.getAttribute('data-tempid');
        var row = btn.closest('tr');
        var runProductIds = JSON.parse(row.getAttribute('data-product-ids') || '[]');

        if (!confirm('Undo this transfer run? This reverses both legs (Neksomo -> Healthcare -> LLP) for every product moved in this run (' + runProductIds.length + ' product(s)).')) return;
        btn.disabled = true;

        // Each call resolves to its parsed JSON response directly (never
        // jQuery's raw [data, textStatus, jqXHR] triple), avoiding
        // $.when's varargs quirk where a single deferred's .done() callback
        // receives different argument shapes than two or more.
        var calls = runProductIds.map(function (pid) {
            return $.post('undo-auto-transfer.php', { tempid: tempid, product_id: pid }, null, 'json')
                .then(function (res) { return res; }, function () { return { success: false, reason: 'request_failed' }; });
        });

        $.when.apply($, calls).done(function () {
            var results = Array.prototype.slice.call(arguments);
            var failures = results.filter(function (res) { return !res || !res.success; });
            if (failures.length > 0) {
                var insufficientMsgs = failures
                    .filter(function (f) { return f && f.reason === 'insufficient_stock_to_reverse'; })
                    .map(function (f) { return 'only ' + f.available + ' of ' + f.requested + ' still in stock'; });
                alert('Some products in this run could not be undone (already moved on further down the chain: '
                    + (insufficientMsgs.join('; ') || 'unknown reason') + '). Products that could be undone were reversed; reconcile the rest manually.');
            }
            window.location.reload();
        });
    }
</script>
</body>
</html>
