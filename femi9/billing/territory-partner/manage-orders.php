<?php
include("checksession.php");
include("config.php");
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today    = date("Y-m-d");
$from_date = $_REQUEST['frdate'] ?? date("Y-m-d", strtotime("-6 days"));
$to_date   = $_REQUEST['todate'] ?? $today;

$stmt = mysqli_prepare($db_conn,
    "SELECT o.id, o.order_id, o.order_date, o.new_order, o.noorder_reason, o.marketing_tool,
            o.pr_id, o.qty, o.invoiced_inv_id, o.assigned_by_ms_id, o.voided_at, o.void_reason,
            s.name shop_name, s.latitude shop_lat, s.longitude shop_lng, s.mobile_number shop_mobile,
            p.productName, dm.ms_name, mo.dm_lat, mo.dm_lng, ui.inv_number
     FROM tp_orders o
     LEFT JOIN shop s ON s.id=o.shop_id
     LEFT JOIN products p ON p.id=o.pr_id
     LEFT JOIN marketing_staff dm ON dm.id=o.assigned_by_ms_id
     LEFT JOIN (SELECT order_id, MAX(latitude) dm_lat, MAX(longitude) dm_lng FROM ms_orders GROUP BY order_id) mo ON mo.order_id=o.order_id
     LEFT JOIN user_invoice ui ON ui.inv_id=o.invoiced_inv_id
     WHERE o.tp_id=? AND o.order_date BETWEEN ? AND ?
     ORDER BY o.order_date DESC, o.id DESC"
);
mysqli_stmt_bind_param($stmt, "iss", $Login_user_IDvl, $from_date, $to_date);
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Group got-order lines under one order_id so each visit is a single row/card.
$visits = [];
foreach ($rows as $r) {
    $oid = $r['order_id'];
    if (!isset($visits[$oid])) {
        $visits[$oid] = [
            'order_date' => $r['order_date'],
            'shop_name'  => $r['shop_name'],
            'shop_lat'   => $r['shop_lat'],
            'shop_lng'   => $r['shop_lng'],
            'new_order'  => $r['new_order'],
            'noorder_reason' => $r['noorder_reason'],
            'invoiced_inv_id' => $r['invoiced_inv_id'],
            'assigned_by_ms_id' => $r['assigned_by_ms_id'],
            'voided_at'  => $r['voided_at'],
            'void_reason' => $r['void_reason'],
            'dm_lat'     => $r['dm_lat'],
            'dm_lng'     => $r['dm_lng'],
            'dm_name'    => $r['ms_name'],
            'shop_mobile' => $r['shop_mobile'],
            'inv_number' => $r['inv_number'],
            'lines'      => [],
        ];
    }
    if ($r['new_order'] === 'yes') {
        $visits[$oid]['lines'][] = ['product' => $r['productName'], 'qty' => $r['qty']];
    }
}

// An invoice only gets a `receipt` row once "Submit Invoice" has actually been
// clicked on shop-invoice-add.php (see shop-invoice-submit.php) — items can
// exist on a draft invoice with no receipt yet, so this is what distinguishes
// "Continue Invoice" (still mid-way) from "Completed Invoice" (submitted).
$invIds = array_values(array_unique(array_filter(array_column($visits, 'invoiced_inv_id'))));
$completedInvIds = [];
if (!empty($invIds)) {
    $placeholders = implode(',', array_fill(0, count($invIds), '?'));
    $types = str_repeat('s', count($invIds));
    $stmtR = mysqli_prepare($db_conn, "SELECT DISTINCT inv_id FROM receipt WHERE inv_id IN ($placeholders)");
    mysqli_stmt_bind_param($stmtR, $types, ...$invIds);
    mysqli_stmt_execute($stmtR);
    $resR = mysqli_stmt_get_result($stmtR);
    while ($rr = mysqli_fetch_assoc($resR)) { $completedInvIds[$rr['inv_id']] = true; }
    mysqli_stmt_close($stmtR);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Field Orders : <?php echo $business_name;?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th {
            background:#f7f7f6; font-weight:600; color:#52514e; padding:10px 12px; text-align:left;
            border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px;
        }
        .mt td { padding:10px 12px; border-bottom:1px solid #e1e0d9; vertical-align:top; }

        .tp-tag { font-size:11px; padding:3px 9px; border-radius:6px; font-weight:600; white-space:nowrap; display:inline-block; }
        .tp-tag-good     { background:#e5f7e5; color:#0ca30c; }
        .tp-tag-bad      { background:#fbe6e6; color:#d03b3b; }
        .tp-tag-info     { background:#eaf2fc; color:#2a78d6; }
        .tp-tag-neutral  { background:#f0f1f2; color:#52525b; }

        .tp-action-link {
            font-size:12px; font-weight:600; text-decoration:none; padding:6px 13px;
            border-radius:6px; display:inline-block;
        }
        .tp-action-primary {
            color:#fff; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
        }
        .tp-action-primary:hover { color:#fff; opacity:.9; }
        .tp-action-success {
            color:#374151; background:#f3f4f6; border:1px solid #d1d5db;
        }
        .tp-action-success:hover { color:#111827; background:#e9eaec; }
        .tp-action-danger {
            color:#d03b3b; background:#fff; border:1px solid #f3c6c6;
        }
        .tp-action-danger:hover { color:#b32e2e; background:#fdf1f1; }

        .tp-location-link {
            font-size:11px; font-weight:600; text-decoration:none; padding:4px 10px;
            border-radius:14px; display:inline-block; margin-top:4px;
            color:#2a78d6; background:#eaf2fc; border:1px solid #cfe1f7;
        }
        .tp-location-link:hover { color:#1a5eb0; background:#dcebfb; }
        .tp-line-item { font-size:12.5px; color:#3a3a38; }
        .tp-line-item + .tp-line-item { margin-top:2px; }
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
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td>Manage Field Orders</td>
                                                <td><a href="add-order.php" title="Add Order"><i class="material-icons">add</i></a></td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($_SESSION['successMessage'])): ?>
                        <div class="alert alert-success"><?=htmlspecialchars($_SESSION['successMessage']); unset($_SESSION['successMessage']);?></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['errorMessage'])): ?>
                        <div class="alert alert-danger"><?=htmlspecialchars($_SESSION['errorMessage']); unset($_SESSION['errorMessage']);?></div>
                        <?php endif; ?>

                        <form method="get" class="row g-2 align-items-end mb-3">
                            <div class="col-auto">
                                <label class="form-label">From Date</label>
                                <input type="date" name="frdate" value="<?=htmlspecialchars($from_date)?>" class="form-control">
                            </div>
                            <div class="col-auto">
                                <label class="form-label">To Date</label>
                                <input type="date" name="todate" value="<?=htmlspecialchars($to_date)?>" class="form-control">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary"><i class="material-icons">search</i> Search</button>
                            </div>
                        </form>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div style="overflow-x:auto;">
                                        <table class="mt">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Shop</th>
                                                    <th>Status</th>
                                                    <th>Details</th>
                                                    <th>Invoice</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($visits)): ?>
                                                <tr><td colspan="5" class="text-center text-muted">No field order entries in this date range.</td></tr>
                                                <?php else: foreach ($visits as $oid => $v): ?>
                                                <tr>
                                                    <td><?=htmlspecialchars(date("d-m-Y", strtotime($v['order_date'])))?></td>
                                                    <td>
                                                        <?=htmlspecialchars($v['shop_name'] ?? '-')?>
                                                        <?php if (!empty($v['shop_lat']) && !empty($v['shop_lng'])): ?>
                                                        <br/><a href="https://www.google.com/maps?q=<?=htmlspecialchars($v['shop_lat'])?>,<?=htmlspecialchars($v['shop_lng'])?>" target="_blank" class="tp-location-link">Shop Location</a>
                                                        <?php endif; ?>
                                                        <?php if (!empty($v['dm_lat']) && !empty($v['dm_lng'])): ?>
                                                        <br/><a href="https://www.google.com/maps?q=<?=htmlspecialchars($v['dm_lat'])?>,<?=htmlspecialchars($v['dm_lng'])?>" target="_blank" class="tp-location-link">Order Location (DM)</a>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($v['new_order'] === 'yes'): ?>
                                                        <span class="tp-tag tp-tag-good">Get Order</span>
                                                        <?php else: ?>
                                                        <span class="tp-tag tp-tag-bad">No Order</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($v['assigned_by_ms_id'])): ?>
                                                        <div style="margin-top:5px;"><span class="tp-tag tp-tag-info">From DM: <?=htmlspecialchars($v['dm_name'] ?? '-')?></span></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($v['new_order'] === 'yes'): ?>
                                                            <?php foreach ($v['lines'] as $ln): ?>
                                                            <div class="tp-line-item"><?=htmlspecialchars($ln['product'] ?? '-')?>: <b><?=htmlspecialchars($ln['qty'])?></b></div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Reason: <?=htmlspecialchars($v['noorder_reason'])?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($v['new_order'] === 'yes'): ?>
                                                            <?php if (!empty($v['voided_at'])): ?>
                                                            <span class="tp-tag tp-tag-bad" <?php if (!empty($v['void_reason'])): ?>title="<?=htmlspecialchars($v['void_reason'])?>"<?php endif; ?>>Cancelled</span>
                                                            <?php if (!empty($v['void_reason'])): ?>
                                                            <div class="text-muted" style="font-size:11px;margin-top:3px;">Reason: <?=htmlspecialchars($v['void_reason'])?></div>
                                                            <?php endif; ?>
                                                            <?php elseif (!empty($v['invoiced_inv_id']) && isset($completedInvIds[$v['invoiced_inv_id']])): ?>
                                                            <a href="shop-invoice-print.php?invoiceid=<?=base64_encode($v['invoiced_inv_id'])?>" class="tp-action-link tp-action-success" target="_blank">Completed Invoice</a>
                                                            <button type="button" title="Share to WhatsApp" style="background:none;border:none;padding:0;cursor:pointer;vertical-align:middle;margin-left:6px;"
                                                                data-id="<?=base64_encode($v['invoiced_inv_id'])?>"
                                                                data-mobile="<?=htmlspecialchars($v['shop_mobile'] ?? '')?>"
                                                                data-invoice="<?=htmlspecialchars($v['inv_number'] ?? '')?>"
                                                                onclick="shareShopInvoiceDirect(this)"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#25D366"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.29-1.39c1.44.79 3.06 1.2 4.71 1.2h.01c5.46 0 9.9-4.45 9.9-9.9C21.91 6.45 17.5 2 12.04 2zm5.8 14.03c-.24.68-1.4 1.32-1.93 1.4-.5.08-1.13.11-1.82-.11-.42-.13-.96-.31-1.65-.61-2.9-1.25-4.79-4.17-4.94-4.36-.14-.19-1.18-1.57-1.18-3 0-1.42.75-2.12 1.02-2.41.27-.29.58-.36.78-.36.19 0 .39 0 .56.01.18.01.42-.07.66.5.24.58.83 2 .9 2.15.07.15.12.32.02.51-.1.19-.15.31-.29.48-.15.17-.31.38-.44.51-.15.15-.3.31-.13.6.17.29.76 1.25 1.63 2.02 1.12 1 2.06 1.31 2.35 1.46.29.15.46.13.63-.08.17-.21.72-.84.92-1.13.19-.29.38-.24.64-.14.26.1 1.65.78 1.94.92.29.15.48.22.55.34.07.13.07.72-.17 1.4z"/></svg></button>
                                                            <?php elseif (!empty($v['invoiced_inv_id'])): ?>
                                                            <a href="shop-invoice-add.php?InvoiceID=<?=base64_encode($v['invoiced_inv_id'])?>&invuser=shop&action=edit" class="tp-action-link tp-action-primary">Continue Invoice</a>
                                                            <?php else: ?>
                                                            <a href="order-to-invoice.php?order_id=<?=urlencode($oid)?>" onclick="return confirm('Create an invoice for this order, using the shop and product/qty exactly as captured here?');" class="tp-action-link tp-action-primary">Invoice</a>
                                                            <a href="javascript:void(0);" onclick="openCancelOrderModal('<?=htmlspecialchars($oid, ENT_QUOTES)?>');" class="tp-action-link tp-action-danger" style="margin-left:6px;">Cancel</a>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">&mdash;</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; endif; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Order modal — a reason is required before the order is voided -->
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form method="post" action="void-order.php" id="cancelOrderForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Cancel Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="cancelOrderIdField" value="">
                        <label class="form-label">Reason for cancelling the order <span style="color:red;">*</span></label>
                        <textarea name="void_reason" id="cancelOrderReasonField" class="form-control" rows="3" required maxlength="255" placeholder="e.g. Shop no longer wants this product"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Confirm Cancel</button>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
    <script src="../../assets/js/whatsapp-invoice-share.js"></script>
    <script>
    function openCancelOrderModal(orderId) {
        document.getElementById('cancelOrderIdField').value = orderId;
        document.getElementById('cancelOrderReasonField').value = '';
        var modalEl = document.getElementById('cancelOrderModal');
        var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }

    // Shares a completed shop invoice straight to WhatsApp — no detour through
    // the print page. This click is a real user gesture, so the whole async
    // chain below (fetch -> PDF -> alert -> WhatsApp) keeps that gesture's
    // permission to open a window, same as the print page's own button.
    function shareShopInvoiceDirect(btn) {
        var id             = btn.getAttribute('data-id');
        var mobile         = btn.getAttribute('data-mobile');
        var invoiceNumber  = btn.getAttribute('data-invoice');
        var originalHtml   = btn.innerHTML;

        btn.disabled  = true;
        btn.innerHTML = '&hellip;';

        fetch('shop-invoice-print.php?invoiceid=' + encodeURIComponent(id))
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var parsed   = new DOMParser().parseFromString(html, 'text/html');
                var printDiv = parsed.getElementById('divToPrint');
                if (!printDiv) throw new Error('Invoice content not found.');

                var iframe = document.createElement('iframe');
                iframe.style.cssText = 'position:fixed;left:-9999px;top:0;width:900px;height:600px;border:0;';
                iframe.srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' + printDiv.outerHTML + '</body></html>';
                iframe.onload = function () {
                    var idoc = iframe.contentDocument;

                    btn.disabled  = false;
                    btn.innerHTML = originalHtml;

                    shareInvoiceToWhatsApp({
                        elementId:     'divToPrint',
                        doc:           idoc,
                        mobile:        mobile,
                        invoiceNumber: invoiceNumber,
                        fileName:      'Invoice_' + invoiceNumber,
                        businessName:  <?php echo json_encode($business_name ?? ''); ?>,
                        button:        btn
                    });

                    setTimeout(function () { iframe.remove(); }, 15000);
                };
                document.body.appendChild(iframe);
            })
            .catch(function (err) {
                console.error(err);
                btn.disabled  = false;
                btn.innerHTML = originalHtml;
                alert('Could not prepare the invoice. Please try again.');
            });
    }
    </script>
</body>
</html>
