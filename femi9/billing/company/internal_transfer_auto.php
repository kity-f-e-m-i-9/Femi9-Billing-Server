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
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
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
                                                            <button type="button" class="btn btn-sm btn-outline-secondary"
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
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
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
                    Uncheck an order to leave it out of today's transfer — it stays exactly as it
                    is (still waiting/draft), you're just choosing not to move its stock right now.
                </p>
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#bdTpPane" type="button">TP Purchase Orders</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#bdOtPane" type="button">OT Channel Orders</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#bdWaPane" type="button">WhatsApp Orders</button></li>
                </ul>
                <div class="tab-content" style="padding-top:10px;">
                    <div class="tab-pane fade show active" id="bdTpPane"><div id="bdTpList"></div></div>
                    <div class="tab-pane fade" id="bdOtPane"><div id="bdOtList"></div></div>
                    <div class="tab-pane fade" id="bdWaPane"><div id="bdWaList"></div></div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="applyBreakdown()">Apply</button>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
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
            html += '<label style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #f1f5f9;padding:8px 4px;cursor:pointer;margin:0;">' +
                '<span><input type="checkbox" class="bd-check" data-qty="' + it.qty + '" data-source-id="' + it.source_id + '" checked style="margin-right:8px;">' + escBd(it.label) + '</span>' +
                '<span style="font-weight:600;">' + it.qty + '</span>' +
            '</label>';
        });
        el.innerHTML = html;
    }

    function openBreakdown(pid, neksomoAvail, healthcareAvail) {
        currentBreakdownPid    = pid;
        currentNeksomoAvail    = neksomoAvail;
        currentHealthcareAvail = healthcareAvail;

        var loading = '<div class="text-muted small" style="padding:10px 4px;">Loading&hellip;</div>';
        document.getElementById('bdTpList').innerHTML = loading;
        document.getElementById('bdOtList').innerHTML = loading;
        document.getElementById('bdWaList').innerHTML = loading;

        $.getJSON('get-auto-transfer-breakdown.php', { product_id: pid }, function (data) {
            renderBreakdownTab('bdTpList', data.tp, 'No Territory Partner orders for this product today.');
            renderBreakdownTab('bdOtList', data.ot, 'No OT channel draft orders for this product today.');
            renderBreakdownTab('bdWaList', data.wa, 'No WhatsApp orders for this product today.');
        }).fail(function () {
            var failMsg = '<div class="text-danger small" style="padding:10px 4px;">Could not load breakdown.</div>';
            document.getElementById('bdTpList').innerHTML = failMsg;
            document.getElementById('bdOtList').innerHTML = failMsg;
            document.getElementById('bdWaList').innerHTML = failMsg;
        });

        var modal = new bootstrap.Modal(document.getElementById('breakdownModal'));
        modal.show();
    }

    function applyBreakdown() {
        if (currentBreakdownPid === null) return;
        var total = 0;
        document.querySelectorAll('.bd-check:checked').forEach(function (chk) {
            total += parseInt(chk.getAttribute('data-qty'), 10) || 0;
        });

        var pid = currentBreakdownPid;
        document.getElementById('req_' + pid).textContent = total;

        var capped = Math.min(total, currentNeksomoAvail + currentHealthcareAvail);
        if (capped < 0) capped = 0;
        document.getElementById('qty_' + pid).value = capped;

        var modalEl = document.getElementById('breakdownModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }
</script>
</body>
</html>
