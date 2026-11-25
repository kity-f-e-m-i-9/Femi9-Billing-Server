<?php
include("checksession.php");
include("config.php");
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today    = date("Y-m-d");
$from_date = $_REQUEST['frdate'] ?? date("Y-m-d", strtotime("-6 days"));
$to_date   = $_REQUEST['todate'] ?? $today;
$status_filter = $_REQUEST['status_filter'] ?? 'pending';
if (!in_array($status_filter, ['pending', 'incomplete', 'completed'], true)) { $status_filter = 'pending'; }
$shop_search = trim($_REQUEST['shop_search'] ?? '');
$mt_filter = $_REQUEST['mt_filter'] ?? '';
if (!in_array($mt_filter, ['', 'available', 'not_available'], true)) { $mt_filter = ''; }

// marketing_tool is stored as "Available: Tool1, Tool2" / "Not Available:
// Tool1, Tool2" (add_order.php's Available/Not Available toggle + tool
// checkboxes, 2026-11-30) — parsed back into a status + tool list for the
// badge/tooltip and the mt_filter dropdown below. A pre-2026-11-30 order's
// plain free-text value matches neither prefix — shown as-is with no status
// badge (status null), and never matches an Available/Not Available filter.
function parseMarketingTool(?string $raw): array {
    $raw = trim((string)$raw);
    if ($raw === '') { return ['status' => null, 'tools' => [], 'raw' => '']; }
    foreach (['Available', 'Not Available'] as $prefix) {
        if (stripos($raw, $prefix . ':') === 0) {
            $toolsPart = substr($raw, strlen($prefix) + 1);
            $tools = array_values(array_filter(array_map('trim', explode(',', $toolsPart)), fn($t) => $t !== ''));
            return ['status' => $prefix, 'tools' => $tools, 'raw' => $raw];
        }
    }
    return ['status' => null, 'tools' => [], 'raw' => $raw];
}

// "Convert" (pick an already-raised invoice to link to) was replaced
// 2026-12-01 by a direct "Invoice" button (order-confirm-shop.php ->
// order-to-invoice-action.php) that creates the real invoice right away,
// same concept as the original order-to-invoice.php "Invoice" button this
// Convert step itself had replaced on 2026-11-24 — user asked to go back to
// that, with an added shop-confirm step. invoiced_inv_id/invoiced_at are
// still the same two columns that drive Pending/Converted status here, just
// now written by the new action file instead of convert-order-invoice.php.
//
// invoiced_at — when the order actually got linked to an invoice (separate
// from the invoice's own created_at, which is when that invoice itself was
// first raised). Self-migrating, same pattern as every other ad-hoc column
// added this session.
$_chkInvoicedAt = $db_conn->query("SHOW COLUMNS FROM tp_orders LIKE 'invoiced_at'");
if ($_chkInvoicedAt && $_chkInvoicedAt->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_orders ADD COLUMN invoiced_at DATETIME NULL DEFAULT NULL AFTER invoiced_inv_id");
}

$stmt = mysqli_prepare($db_conn,
    "SELECT o.id, o.order_id, o.order_date, o.new_order, o.noorder_reason, o.marketing_tool,
            o.pr_id, o.qty, o.invoiced_inv_id, o.invoiced_at, o.assigned_by_ms_id, o.voided_at, o.void_reason,
            s.name shop_name, s.latitude shop_lat, s.longitude shop_lng, s.mobile_number shop_mobile, s.temp_id shop_temp_id,
            p.productName, p.gst AS p_gst, dm.ms_name, mo.dm_lat, mo.dm_lng, ui.inv_number, ui.created_at AS inv_created_at
     FROM tp_orders o
     LEFT JOIN shop s ON s.id=o.shop_id
     LEFT JOIN products p ON p.id=o.pr_id
     LEFT JOIN marketing_staff dm ON dm.id=o.assigned_by_ms_id
     LEFT JOIN (SELECT order_id, MAX(latitude) dm_lat, MAX(longitude) dm_lng FROM ms_orders GROUP BY order_id) mo ON mo.order_id=o.order_id
     LEFT JOIN user_invoice ui ON ui.inv_id=o.invoiced_inv_id COLLATE utf8mb4_general_ci
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
            'shop_temp_id' => $r['shop_temp_id'],
            'new_order'  => $r['new_order'],
            'noorder_reason' => $r['noorder_reason'],
            'marketing_tool' => $r['marketing_tool'],
            'invoiced_inv_id' => $r['invoiced_inv_id'],
            'invoiced_at' => $r['invoiced_at'],
            'assigned_by_ms_id' => $r['assigned_by_ms_id'],
            'voided_at'  => $r['voided_at'],
            'void_reason' => $r['void_reason'],
            'dm_lat'     => $r['dm_lat'],
            'dm_lng'     => $r['dm_lng'],
            'dm_name'    => $r['ms_name'],
            'shop_mobile' => $r['shop_mobile'],
            'inv_number' => $r['inv_number'],
            'inv_created_at' => $r['inv_created_at'],
            'lines'      => [],
        ];
    }
    if ($r['new_order'] === 'yes') {
        $visits[$oid]['lines'][] = ['product' => $r['productName'], 'qty' => $r['qty'], 'gst' => $r['p_gst']];
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

// Invoice status per "Get Order" visit — matches the same 3 outcomes shown
// in the Invoice column below. "No Order" visits and cancelled ones have no
// invoice status at all, so they're left out of this filter entirely.
foreach ($visits as $oid => &$v) {
    if ($v['new_order'] !== 'yes' || !empty($v['voided_at'])) {
        $v['invoice_status'] = null;
    } elseif (empty($v['invoiced_inv_id'])) {
        $v['invoice_status'] = 'pending';
    } elseif (isset($completedInvIds[$v['invoiced_inv_id']])) {
        $v['invoice_status'] = 'completed';
    } else {
        $v['invoice_status'] = 'incomplete';
    }
}
unset($v);

$visits = array_filter($visits, fn($v) => $v['invoice_status'] === $status_filter);

if ($shop_search !== '') {
    $visits = array_filter($visits, fn($v) => stripos($v['shop_name'] ?? '', $shop_search) !== false);
}

foreach ($visits as $oid => &$v) {
    $v['marketing_tool_parsed'] = parseMarketingTool($v['marketing_tool'] ?? '');
}
unset($v);

if ($mt_filter === 'available') {
    $visits = array_filter($visits, fn($v) => $v['marketing_tool_parsed']['status'] === 'Available');
} elseif ($mt_filter === 'not_available') {
    $visits = array_filter($visits, fn($v) => $v['marketing_tool_parsed']['status'] === 'Not Available');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage DM Orders : <?php echo $business_name;?></title>
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
            border-radius:6px; display:inline-block; text-align:center;
        }
        .tp-invoice-actions { display:flex; flex-direction:column; align-items:stretch; gap:6px; max-width:150px; }
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

        /* Marketing Tool badge hover — click/tap or hover shows which tools
           the DM actually noted (Available: what's there / Not Available:
           what's needed), same popover pattern as the dashboard's own
           hoverable stat cards. 2026-11-30, user-requested. */
        .tp-stat-hoverable { position:relative; cursor:default; }
        .tp-stat-tooltip {
            display:none; position:absolute; top:100%; left:0; margin-top:6px;
            z-index:50; background:#fff; border:1px solid #e5e7eb; border-radius:10px;
            box-shadow:0 8px 24px rgba(16,24,40,.14); padding:10px 12px;
            min-width:180px; max-width:260px; max-height:240px; overflow-y:auto; text-align:left;
            font-size:12.5px; color:#374151;
        }
        .tp-stat-hoverable:hover .tp-stat-tooltip,
        .tp-stat-hoverable:focus-within .tp-stat-tooltip,
        .tp-stat-hoverable:focus .tp-stat-tooltip { display:block; }
        .tp-stat-tooltip-row { padding:3px 0; border-bottom:1px solid #f8fafc; }
        .tp-stat-tooltip-row:last-child { border-bottom:none; }
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
                            <div class="col d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="page-description">
                                    <h1>Manage DM Orders</h1>
                                </div>
                                <a href="export-field-orders-xlsx.php?frdate=<?=urlencode($from_date)?>&todate=<?=urlencode($to_date)?>&status_filter=<?=urlencode($status_filter)?>&shop_search=<?=urlencode($shop_search)?>&mt_filter=<?=urlencode($mt_filter)?>" class="btn btn-sm btn-outline-success"><i class="material-icons" style="font-size:15px;vertical-align:middle;">download</i> Export Excel</a>
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
                                <label class="form-label">Status</label>
                                <select name="status_filter" class="form-control">
                                    <option value="pending" <?=$status_filter==='pending'?'selected':''?>>Pending</option>
                                    <option value="incomplete" <?=$status_filter==='incomplete'?'selected':''?>>Incomplete</option>
                                    <option value="completed" <?=$status_filter==='completed'?'selected':''?>>Completed</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <label class="form-label">Marketing Tool</label>
                                <select name="mt_filter" class="form-control">
                                    <option value="" <?=$mt_filter===''?'selected':''?>>All</option>
                                    <option value="available" <?=$mt_filter==='available'?'selected':''?>>Available</option>
                                    <option value="not_available" <?=$mt_filter==='not_available'?'selected':''?>>Not Available</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <label class="form-label">Shop Name</label>
                                <input type="text" name="shop_search" value="<?=htmlspecialchars($shop_search)?>" placeholder="Search shop..." class="form-control">
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
                                                    <th>Marketing Tool</th>
                                                    <th>Location</th>
                                                    <th>Invoice</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($visits)): ?>
                                                <tr><td colspan="7" class="text-center text-muted">No field order entries in this date range.</td></tr>
                                                <?php else: foreach ($visits as $oid => $v): ?>
                                                <tr>
                                                    <td><?=htmlspecialchars(date("d-m-Y", strtotime($v['order_date'])))?></td>
                                                    <td>
                                                        <?=htmlspecialchars($v['shop_name'] ?? '-')?>
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
                                                            <div class="tp-line-item"><?=htmlspecialchars($ln['product'] ?? '-')?>: <b><?=htmlspecialchars($ln['qty'])?></b> <span class="text-muted" style="font-size:11px;">(GST <?=htmlspecialchars($ln['gst'] !== null ? $ln['gst'] . '%' : '-')?>)</span></div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Reason: <?=htmlspecialchars($v['noorder_reason'])?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php $mtp = $v['marketing_tool_parsed']; ?>
                                                        <?php if ($mtp['status'] === 'Available'): ?>
                                                        <span class="tp-tag tp-tag-good tp-stat-hoverable" tabindex="0">Available
                                                            <div class="tp-stat-tooltip">
                                                                <?php if (empty($mtp['tools'])): ?>
                                                                <div class="tp-stat-tooltip-row text-muted">None listed.</div>
                                                                <?php else: foreach ($mtp['tools'] as $tool): ?>
                                                                <div class="tp-stat-tooltip-row"><?=htmlspecialchars($tool)?></div>
                                                                <?php endforeach; endif; ?>
                                                            </div>
                                                        </span>
                                                        <?php elseif ($mtp['status'] === 'Not Available'): ?>
                                                        <span class="tp-tag tp-tag-bad tp-stat-hoverable" tabindex="0">Not Available
                                                            <div class="tp-stat-tooltip">
                                                                <?php if (empty($mtp['tools'])): ?>
                                                                <div class="tp-stat-tooltip-row text-muted">None listed.</div>
                                                                <?php else: foreach ($mtp['tools'] as $tool): ?>
                                                                <div class="tp-stat-tooltip-row"><?=htmlspecialchars($tool)?></div>
                                                                <?php endforeach; endif; ?>
                                                            </div>
                                                        </span>
                                                        <?php elseif ($mtp['raw'] !== ''): ?>
                                                        <span class="tp-tag tp-tag-neutral tp-stat-hoverable" tabindex="0">Noted
                                                            <div class="tp-stat-tooltip"><div class="tp-stat-tooltip-row"><?=htmlspecialchars($mtp['raw'])?></div></div>
                                                        </span>
                                                        <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($v['dm_lat']) && !empty($v['dm_lng'])): ?>
                                                        <div style="margin-bottom:6px;">
                                                            <span class="text-muted" style="font-size:11px;display:block;">DM Order Location</span>
                                                            <a href="javascript:void(0);" onclick="copyLatLng(this,'<?=htmlspecialchars($v['dm_lat'], ENT_QUOTES)?>','<?=htmlspecialchars($v['dm_lng'], ENT_QUOTES)?>');" class="tp-location-link">Copy</a>
                                                        </div>
                                                        <?php else: ?>
                                                        <div style="margin-bottom:6px;"><span class="text-muted" style="font-size:11px;display:block;">DM Order Location</span><span class="text-muted">&mdash;</span></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($v['shop_lat']) && !empty($v['shop_lng'])): ?>
                                                        <div>
                                                            <span class="text-muted" style="font-size:11px;display:block;">Shop Location</span>
                                                            <a href="javascript:void(0);" onclick="copyLatLng(this,'<?=htmlspecialchars($v['shop_lat'], ENT_QUOTES)?>','<?=htmlspecialchars($v['shop_lng'], ENT_QUOTES)?>');" class="tp-location-link">Copy</a>
                                                        </div>
                                                        <?php else: ?>
                                                        <div><span class="text-muted" style="font-size:11px;display:block;">Shop Location</span><span class="text-muted">&mdash;</span></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($v['new_order'] === 'yes'): ?>
                                                            <?php if (!empty($v['voided_at'])): ?>
                                                            <span class="tp-tag tp-tag-bad" <?php if (!empty($v['void_reason'])): ?>title="<?=htmlspecialchars($v['void_reason'])?>"<?php endif; ?>>Not Converted</span>
                                                            <?php if (!empty($v['void_reason'])): ?>
                                                            <div class="text-muted" style="font-size:11px;margin-top:3px;">Reason: <?=htmlspecialchars($v['void_reason'])?></div>
                                                            <?php endif; ?>
                                                            <?php elseif (!empty($v['invoiced_inv_id']) && isset($completedInvIds[$v['invoiced_inv_id']])): ?>
                                                            <a href="shop-invoice-print.php?invoiceid=<?=base64_encode($v['invoiced_inv_id'])?>" class="tp-action-link tp-action-success" target="_blank">Completed Invoice</a>
                                                            <?php if (!empty($v['inv_created_at'])): ?>
                                                            <div class="text-muted" style="font-size:11px;margin-top:3px;">Invoice placed: <?=htmlspecialchars(date('d-m-Y h:i A', strtotime($v['inv_created_at'])))?></div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($v['invoiced_at'])): ?>
                                                            <div class="text-muted" style="font-size:11px;">Converted: <?=htmlspecialchars(date('d-m-Y h:i A', strtotime($v['invoiced_at'])))?></div>
                                                            <?php endif; ?>
                                                            <?php elseif (!empty($v['invoiced_inv_id'])): ?>
                                                            <a href="shop-invoice-add.php?InvoiceID=<?=base64_encode($v['invoiced_inv_id'])?>&invuser=shop&action=edit" class="tp-action-link tp-action-primary">Continue Invoice</a>
                                                            <?php if (!empty($v['inv_created_at'])): ?>
                                                            <div class="text-muted" style="font-size:11px;margin-top:3px;">Invoice placed: <?=htmlspecialchars(date('d-m-Y h:i A', strtotime($v['inv_created_at'])))?></div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($v['invoiced_at'])): ?>
                                                            <div class="text-muted" style="font-size:11px;">Converted: <?=htmlspecialchars(date('d-m-Y h:i A', strtotime($v['invoiced_at'])))?></div>
                                                            <?php endif; ?>
                                                            <?php else: ?>
                                                            <div class="tp-invoice-actions">
                                                                <a href="order-confirm-shop.php?order_id=<?=urlencode($oid)?>" class="tp-action-link tp-action-primary">Invoice</a>
                                                                <a href="javascript:void(0);" onclick="openCancelOrderModal('<?=htmlspecialchars($oid, ENT_QUOTES)?>');" class="tp-action-link tp-action-danger">Not Convert</a>
                                                            </div>
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

    <!-- Not Convert modal — a reason is required before the order is marked Not Converted -->
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form method="post" action="void-order.php" id="cancelOrderForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Not Convert</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="cancelOrderIdField" value="">
                        <label class="form-label">Reason for not converting this order <span style="color:red;">*</span></label>
                        <textarea name="void_reason" id="cancelOrderReasonField" class="form-control" rows="3" required maxlength="255" placeholder="e.g. Shop no longer wants this product"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Confirm</button>
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
    <script>
    function openCancelOrderModal(orderId) {
        document.getElementById('cancelOrderIdField').value = orderId;
        document.getElementById('cancelOrderReasonField').value = '';
        var modalEl = document.getElementById('cancelOrderModal');
        var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }

    function copyLatLng(el, lat, lng) {
        var text = lat + ',' + lng;
        var done = function () {
            var orig = el.textContent;
            el.textContent = 'Copied!';
            setTimeout(function () { el.textContent = orig; }, 1200);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () { window.prompt('Copy this location:', text); });
        } else {
            window.prompt('Copy this location:', text);
        }
    }
    </script>
</body>
</html>
