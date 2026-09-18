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
                                        Quantities required today for waiting Territory Partner purchase
                                        orders and drafted OT channel orders (LLP), auto-capped to
                                        available Neksomo + Healthcare stock. Adjust any row before
                                        transferring — Neksomo &rarr; Healthcare &rarr; LLP, both legs
                                        move in one click.
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
                                        <form method="post" action="internal_transfer_auto_action.php" onsubmit="return confirm('Transfer these quantities now?');">
                                            <div style="overflow-x:auto;">
                                            <table class="table table-bordered" style="min-width:760px;">
                                                <thead>
                                                    <tr>
                                                        <th>Product</th>
                                                        <th>Required Qty</th>
                                                        <th>Available (Neksomo)</th>
                                                        <th>Available (Healthcare)</th>
                                                        <th>Qty to Transfer</th>
                                                        <th>Breakdown</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($rows as $row): ?>
                                                    <tr>
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
                    reopen this page). Click <strong>Not Today</strong> to exclude it for the rest
                    of today instead — either way the order itself stays exactly as it is
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
     (not scoped to a single product, unlike the modal above). Unchecking
     an order + Apply excludes every one of that order's product lines at
     once — since one order can carry several different products, this can
     reduce several rows in the main table in a single action, unlike the
     per-product breakdown modal's checkbox. -->
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
                    checkbox, then Apply — the order/its other products are untouched, only what's
                    unchecked is left out of today's transfer. The order itself stays exactly as it
                    is (still waiting/draft); this page reloads to reflect the change across every
                    affected product row.
                </p>
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ovTpPane" type="button">TP Purchase Orders</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ovOtPane" type="button">OT Channel Orders</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ovExcludedPane" type="button" onclick="loadExcludedToday()">Excluded Today</button></li>
                </ul>
                <div class="tab-content" style="padding-top:10px;">
                    <div class="tab-pane fade show active" id="ovTpPane"><div id="ovTpList"></div></div>
                    <div class="tab-pane fade" id="ovOtPane"><div id="ovOtList"></div></div>
                    <div class="tab-pane fade" id="ovExcludedPane">
                        <p class="text-muted small">
                            Everything currently left out of today's transfer, grouped by order —
                            either you unchecked it, or it was already transferred earlier today
                            (that stock already moved, so it can't be brought back). Click an order's
                            name to select/deselect all of its lines at once, or tick just the
                            products you want, then Include Selected.
                        </p>
                        <div id="ovExcludedList"></div>
                        <div style="text-align:right;margin-top:10px;">
                            <button type="button" id="ovExcludedIncludeBtn" class="btn btn-sm btn-primary" onclick="ovIncludeSelected()">Include Selected</button>
                        </div>
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

    function renderBreakdownTab(containerId, items, emptyMsg) {
        var el = document.getElementById(containerId);
        if (!items || !items.length) {
            el.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">' + emptyMsg + '</div>';
            return;
        }
        var html = '';
        items.forEach(function (it) {
            html += '<div class="bd-row" data-source-id="' + it.source_id + '" style="display:flex;justify-content:space-between;align-items:center;gap:10px;border-bottom:1px solid #f1f5f9;padding:8px 4px;flex-wrap:wrap;">' +
                '<label style="display:flex;align-items:center;flex:1;cursor:pointer;margin:0;min-width:160px;">' +
                    '<input type="checkbox" class="bd-check" data-source-id="' + it.source_id + '" checked style="margin-right:8px;flex-shrink:0;">' +
                    '<span style="overflow-wrap:anywhere;">' + escBd(it.label) + '</span>' +
                '</label>' +
                '<input type="number" min="0" max="' + it.qty + '" class="form-control form-control-sm bd-qty-input" data-source-id="' + it.source_id + '" ' +
                    'value="' + it.qty + '" style="width:80px;flex-shrink:0;" title="Max ' + it.qty + ' — this order\'s own qty">' +
                '<button type="button" class="btn btn-sm btn-outline-danger bd-not-today" data-source-id="' + it.source_id + '" style="white-space:nowrap;font-size:11px;padding:2px 8px;flex-shrink:0;">Not Today</button>' +
            '</div>';
        });
        el.innerHTML = html;

        el.querySelectorAll('.bd-not-today').forEach(function (btn) {
            btn.addEventListener('click', function () { skipOrderNotToday(this); });
        });
    }

    // Recomputes the currently-open product's Required Qty + Qty to
    // Transfer from whatever's still checked across all three tabs —
    // shared by the plain "Apply" button and by skipOrderNotToday() once
    // an order's row is removed, so both paths end up consistent.
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

    // "Not Today" — unlike the checkbox (a this-view-only recompute lost on
    // reload), this persists: the order is recorded as skipped for today
    // (its own PO/draft status is never touched) so it stays out of
    // Required Qty even after closing and reopening this page.
    function skipOrderNotToday(btn) {
        var sourceId = btn.getAttribute('data-source-id');
        var colonIdx = sourceId.indexOf(':');
        var sourceType = sourceId.substring(0, colonIdx);
        var sourceRef  = sourceId.substring(colonIdx + 1);

        btn.disabled = true;
        $.post('mark-auto-transfer-skip.php', { source_type: sourceType, source_ref: sourceRef }, function (res) {
            if (!res || !res.success) {
                alert('Could not exclude this order. Please try again.');
                btn.disabled = false;
                return;
            }
            var row = document.querySelector('.bd-row[data-source-id="' + sourceId.replace(/"/g, '') + '"]');
            if (row) row.remove();
            recomputeCurrentRowFromCheckboxes();
        }, 'json').fail(function () {
            alert('Request failed. Please try again.');
            btn.disabled = false;
        });
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

    // ── "View All Orders" — order-level overview, all products per order ──
    function ovRenderOrderList(containerId, orders, emptyMsg) {
        var el = document.getElementById(containerId);
        if (!orders || !orders.length) {
            el.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">' + emptyMsg + '</div>';
            return;
        }
        var html = '';
        orders.forEach(function (order) {
            var productsHtml = order.products.map(function (p) {
                return '<div class="ov-product-row" data-product-id="' + p.product_id + '" style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:5px 0;font-size:12.5px;color:#4b5563;">' +
                    '<label style="display:flex;align-items:center;flex:1;cursor:pointer;margin:0;min-width:0;">' +
                        '<input type="checkbox" class="ov-product-check" checked style="margin-right:8px;flex-shrink:0;">' +
                        '<span style="overflow-wrap:anywhere;">' + escBd(p.product_name) + '</span>' +
                    '</label>' +
                    '<input type="number" min="0" max="' + p.qty + '" class="form-control form-control-sm ov-product-qty" ' +
                        'value="' + p.qty + '" style="width:75px;flex-shrink:0;" title="Max ' + p.qty + '">' +
                '</div>';
            }).join('');
            html += '<div class="ov-order" data-order-key="' + escBd(order.order_key) + '" style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:10px;">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">' +
                    '<label style="display:flex;align-items:center;flex:1;cursor:pointer;margin:0;min-width:160px;font-weight:600;">' +
                        '<input type="checkbox" class="ov-check" checked style="margin-right:8px;flex-shrink:0;" title="Select/deselect every product in this order">' +
                        '<span style="overflow-wrap:anywhere;">' + escBd(order.label) + '</span>' +
                    '</label>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger ov-not-today" style="white-space:nowrap;font-size:11px;padding:2px 8px;">Not Today (whole order)</button>' +
                '</div>' +
                '<div style="margin-top:6px;padding-left:26px;border-top:1px solid #f1f5f9;padding-top:6px;">' + productsHtml + '</div>' +
            '</div>';
        });
        el.innerHTML = html;
        // Store the raw data on each row so Apply/Not Today can read the
        // per-product qtys back out without re-parsing the DOM.
        el.querySelectorAll('.ov-order').forEach(function (rowEl, idx) {
            rowEl._ovOrder = orders[idx];
            rowEl._ovSourceType = containerId === 'ovTpList' ? 'tp' : 'ot';
        });
        // Order checkbox is a select-all/deselect-all toggle for its own
        // product checkboxes — the real, individually-actionable state
        // lives on each .ov-product-check, not on this one.
        el.querySelectorAll('.ov-check').forEach(function (orderCheck) {
            orderCheck.addEventListener('change', function () {
                var rowEl = orderCheck.closest('.ov-order');
                rowEl.querySelectorAll('.ov-product-check').forEach(function (pc) { pc.checked = orderCheck.checked; });
            });
        });
        el.querySelectorAll('.ov-not-today').forEach(function (btn) {
            btn.addEventListener('click', function () { ovSkipOrderNotToday(this); });
        });
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

    // Marks every product line of one order skipped for today, then
    // removes that order's row from the list — same underlying
    // mark-auto-transfer-skip.php endpoint as the per-product breakdown
    // modal's "Not Today", just called once per product line here.
    function ovSkipOrderNotToday(btn) {
        var rowEl = btn.closest('.ov-order');
        var order = rowEl._ovOrder;
        var sourceType = rowEl._ovSourceType;
        btn.disabled = true;

        var calls = order.products.map(function (p) {
            var sourceRef = order.order_key + ':' + p.product_id;
            return $.post('mark-auto-transfer-skip.php', { source_type: sourceType, source_ref: sourceRef });
        });
        $.when.apply($, calls).done(function () {
            rowEl.remove();
        }).fail(function () {
            alert('Could not exclude this order. Please try again.');
            btn.disabled = false;
        });
    }

    // Acts per PRODUCT LINE, not per order — a single PO/OT invoice can
    // carry several products, and one of them alone might have a stock
    // problem that shouldn't hold back the rest of the same order. Any
    // unchecked product line gets marked skipped (its own qty box's
    // edited value isn't sent anywhere — this is a full include/exclude
    // per line here, not a partial-qty split); then reloads the page so
    // every affected product row's Required Qty / Qty to Transfer
    // recomputes from the server, rather than trying to patch several
    // rows client-side.
    function applyOrdersOverview() {
        var calls = [];
        document.querySelectorAll('.ov-order').forEach(function (rowEl) {
            var order = rowEl._ovOrder;
            var sourceType = rowEl._ovSourceType;
            rowEl.querySelectorAll('.ov-product-row').forEach(function (prodRowEl) {
                var checkbox = prodRowEl.querySelector('.ov-product-check');
                if (checkbox.checked) return; // left in — nothing to do
                var productId = prodRowEl.getAttribute('data-product-id');
                var sourceRef = order.order_key + ':' + productId;
                calls.push($.post('mark-auto-transfer-skip.php', { source_type: sourceType, source_ref: sourceRef }));
            });
        });

        if (calls.length === 0) {
            var modalEl = document.getElementById('ordersOverviewModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            return;
        }

        $.when.apply($, calls).done(function () {
            window.location.reload();
        }).fail(function () {
            alert('Could not apply your changes. Please try again.');
        });
    }

    // ── "Excluded Today" — undo any Not Today / already-transferred skip ──
    // Grouped by order: clicking the order's name toggles every checkbox
    // under it, but each product line keeps its own checkbox so a few
    // specific products can be left out while the rest of the order is
    // included again. Lines already transferred today have no checkbox —
    // that stock already moved, so there's nothing to "include" back.
    function loadExcludedToday() {
        var el = document.getElementById('ovExcludedList');
        el.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">Loading&hellip;</div>';

        $.getJSON('get-auto-transfer-skipped.php', {}, function (data) {
            var all = (data.tp || []).map(function (r) { return Object.assign({ source_type: 'tp' }, r); })
                .concat((data.ot || []).map(function (r) { return Object.assign({ source_type: 'ot' }, r); }));
            if (!all.length) {
                el.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">Nothing excluded today.</div>';
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
            groups.forEach(function (group, gIdx) {
                html += '<div class="ov-excl-group" style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:10px;">' +
                    '<div class="ov-excl-group-label" data-group-idx="' + gIdx + '" style="font-weight:600;cursor:pointer;color:#2563eb;overflow-wrap:anywhere;">' +
                        escBd(group.label) +
                        ' <span class="text-muted" style="font-weight:400;font-size:11px;">(click to select/deselect all)</span>' +
                    '</div>';
                group.items.forEach(function (item) {
                    html += '<div class="ov-excl-line" data-group-idx="' + gIdx + '" style="display:flex;align-items:center;gap:8px;padding:5px 0 0 4px;">';
                    if (item.reason === 'transferred') {
                        html += '<input type="checkbox" disabled style="visibility:hidden;">' +
                            '<span style="color:#9ca3af;">' + escBd(item.product_name) + '</span>' +
                            '<span class="badge" style="background:#d1fae5;color:#065f46;">Already transferred</span>';
                    } else {
                        html += '<input type="checkbox" class="ov-excl-check" data-source-id="' + escBd(item.source_id) + '">' +
                            '<span>' + escBd(item.product_name) + '</span>' +
                            '<span class="badge" style="background:#fef3c7;color:#92400e;">Excluded</span>';
                    }
                    html += '</div>';
                });
                html += '</div>';
            });
            el.innerHTML = html;

            el.querySelectorAll('.ov-excl-group-label').forEach(function (labelEl) {
                labelEl.addEventListener('click', function () {
                    var gIdx = labelEl.getAttribute('data-group-idx');
                    var boxes = el.querySelectorAll('.ov-excl-check[data-source-id]');
                    var groupBoxes = Array.prototype.filter.call(boxes, function (b) {
                        return b.closest('.ov-excl-line').getAttribute('data-group-idx') === gIdx;
                    });
                    if (!groupBoxes.length) return;
                    var allChecked = groupBoxes.every(function (b) { return b.checked; });
                    groupBoxes.forEach(function (b) { b.checked = !allChecked; });
                });
            });
        }).fail(function () {
            el.innerHTML = '<div class="text-danger small" style="padding:10px 4px;">Could not load excluded orders.</div>';
        });
    }

    function ovIncludeSelected() {
        var checked = document.querySelectorAll('#ovExcludedList .ov-excl-check:checked');
        if (!checked.length) {
            alert('Tick at least one product to include it back.');
            return;
        }
        var btn = document.getElementById('ovExcludedIncludeBtn');
        btn.disabled = true;

        var calls = Array.prototype.map.call(checked, function (box) {
            var sourceId = box.getAttribute('data-source-id');
            var colonIdx = sourceId.indexOf(':');
            var sourceType = sourceId.substring(0, colonIdx);
            var sourceRef = sourceId.substring(colonIdx + 1);
            return $.post('unmark-auto-transfer-skip.php', { source_type: sourceType, source_ref: sourceRef }, null, 'json');
        });

        $.when.apply($, calls).done(function () {
            window.location.reload();
        }).fail(function () {
            alert('Some items could not be included back. Please try again.');
            btn.disabled = false;
        });
    }

    // ── "Transfer History" ──
    function openTransferHistory() {
        var dateInput = document.getElementById('thDateInput');
        if (!dateInput.value) {
            var yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            dateInput.value = yesterday.toISOString().slice(0, 10);
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
            if (!data.rows || !data.rows.length) {
                resultEl.innerHTML = '<div class="text-muted small" style="padding:10px 4px;">No auto-transfers found on this date.</div>';
                return;
            }
            var html = '<div style="overflow-x:auto;"><table class="table table-bordered table-sm" style="min-width:1080px;">' +
                '<thead><tr style="background:#f8fafc;">' +
                    '<th>Product</th>' +
                    '<th>Neksomo Before</th><th>Neksomo After</th>' +
                    '<th>Healthcare Before</th><th>Healthcare After</th>' +
                    '<th>LLP Before</th><th>LLP After</th>' +
                    '<th>Qty Transferred</th><th>Date &amp; Time</th>' +
                '</tr></thead><tbody>';
            data.rows.forEach(function (r) {
                html += '<tr>' +
                    '<td>' + escBd(r.product_name) + '</td>' +
                    '<td>' + thFmtStock(r.neksomo_before) + '</td>' +
                    '<td>' + thFmtStock(r.neksomo_after) + '</td>' +
                    '<td>' + thFmtStock(r.healthcare_before) + '</td>' +
                    '<td>' + thFmtStock(r.healthcare_after) + '</td>' +
                    '<td>' + thFmtStock(r.llp_before) + '</td>' +
                    '<td>' + thFmtStock(r.llp_after) + '</td>' +
                    '<td style="font-weight:600;">' + r.qty_transferred + '</td>' +
                    '<td>' + thFmtDateTime(r.transferred_at) + '</td>' +
                '</tr>';
            });
            html += '</tbody></table></div>';
            resultEl.innerHTML = html;
        }).fail(function () {
            resultEl.innerHTML = '<div class="text-danger small" style="padding:10px 4px;">Could not load history.</div>';
        });
    }
</script>
</body>
</html>
