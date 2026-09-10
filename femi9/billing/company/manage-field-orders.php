<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today    = date("Y-m-d");
$from_date = $_REQUEST['frdate'] ?? date("Y-m-d", strtotime("-6 days"));
$to_date   = $_REQUEST['todate'] ?? $today;
$status_filter = $_REQUEST['status_filter'] ?? 'pending';
if (!in_array($status_filter, ['pending', 'incomplete', 'completed'], true)) { $status_filter = 'pending'; }
$tp_filter = (int)($_REQUEST['tp_filter'] ?? 0);

// A Sales BDM session only sees Field Orders for Territory Partners inside
// their own assigned districts — company staff see everyone. Mirrors
// manage-territory-partner.php's own BDM scoping.
$_isBdm = ($Login_user_TYPEvl ?? '') === 'salesbdm';
$_scopedTpIds = null;
if ($_isBdm) {
    require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';
    $_scopedTpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID, true);
}

// TP picker — scoped the same way.
$tpListWhere = $_scopedTpIds !== null
    ? 'WHERE id IN (' . (empty($_scopedTpIds) ? '0' : implode(',', array_map('intval', $_scopedTpIds))) . ')'
    : '';
$tpList = $db_conn->query("SELECT id, name, tp_id FROM territory_partners $tpListWhere ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

$tpWhereParts = [];
$params = [];
$types = '';
if ($_scopedTpIds !== null) {
    if (empty($_scopedTpIds)) { $_scopedTpIds = [0]; }
    $tpWhereParts[] = 'o.tp_id IN (' . implode(',', array_map('intval', $_scopedTpIds)) . ')';
}
if ($tp_filter > 0) {
    $tpWhereParts[] = 'o.tp_id = ?';
    $params[] = $tp_filter;
    $types .= 'i';
}
$tpWhereParts[] = 'o.order_date BETWEEN ? AND ?';
$params[] = $from_date; $params[] = $to_date;
$types .= 'ss';
$tpWhereSql = implode(' AND ', $tpWhereParts);

$stmt = mysqli_prepare($db_conn,
    "SELECT o.id, o.order_id, o.order_date, o.new_order, o.noorder_reason, o.tp_id,
            o.pr_id, o.qty, o.discount_percentage, o.discount_amount, o.invoiced_inv_id, o.assigned_by_ms_id, o.voided_at, o.void_reason,
            s.name shop_name, s.latitude shop_lat, s.longitude shop_lng, s.mobile_number shop_mobile,
            p.productName, p.gst AS p_gst, p.outlet_price, dm.ms_name, mo.dm_lat, mo.dm_lng, ui.inv_number, ui.total AS inv_total,
            tp.name tp_name, tp.tp_id tp_code
     FROM tp_orders o
     LEFT JOIN shop s ON s.id=o.shop_id
     LEFT JOIN products p ON p.id=o.pr_id
     LEFT JOIN marketing_staff dm ON dm.id=o.assigned_by_ms_id
     LEFT JOIN (SELECT order_id, MAX(latitude) dm_lat, MAX(longitude) dm_lng FROM ms_orders GROUP BY order_id) mo ON mo.order_id=o.order_id
     LEFT JOIN user_invoice ui ON ui.inv_id=o.invoiced_inv_id COLLATE utf8mb4_general_ci
     LEFT JOIN territory_partners tp ON tp.id=o.tp_id
     WHERE $tpWhereSql
     ORDER BY o.order_date DESC, o.id DESC"
);
mysqli_stmt_bind_param($stmt, $types, ...$params);
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
            'tp_id'      => (int)$r['tp_id'],
            'tp_name'    => $r['tp_name'],
            'tp_code'    => $r['tp_code'],
            'shop_name'  => $r['shop_name'],
            'shop_lat'   => $r['shop_lat'],
            'shop_lng'   => $r['shop_lng'],
            'new_order'  => $r['new_order'],
            'noorder_reason' => $r['noorder_reason'],
            'invoiced_inv_id' => $r['invoiced_inv_id'],
            'inv_total'  => $r['inv_total'],
            'assigned_by_ms_id' => $r['assigned_by_ms_id'],
            'voided_at'  => $r['voided_at'],
            'void_reason' => $r['void_reason'],
            'dm_lat'     => $r['dm_lat'],
            'dm_lng'     => $r['dm_lng'],
            'dm_name'    => $r['ms_name'],
            'shop_mobile' => $r['shop_mobile'],
            'inv_number' => $r['inv_number'],
            'lines'      => [],
            'est_amount' => 0.0,
        ];
    }
    if ($r['new_order'] === 'yes') {
        $visits[$oid]['lines'][] = ['product' => $r['productName'], 'qty' => $r['qty'], 'gst' => $r['p_gst']];
        // Estimated value only — the Get Order stage never records a price,
        // just qty per product, so this is qty x the shop-facing outlet price
        // (minus whatever discount was captured on the visit), not the exact
        // amount the TP will actually end up charging at invoicing time.
        $gross = (float)$r['qty'] * (float)($r['outlet_price'] ?? 0);
        $gross -= $gross * ((float)($r['discount_percentage'] ?? 0) / 100);
        $gross -= (float)($r['discount_amount'] ?? 0);
        $visits[$oid]['est_amount'] += max(0, $gross);
    }
}

// An invoice only gets a `receipt` row once "Submit Invoice" has actually been
// clicked — see territory-partner/manage-orders.php for the same logic this
// mirrors (this page is the company/BDM cross-TP view of the same data).
$invIds = array_values(array_unique(array_filter(array_column($visits, 'invoiced_inv_id'))));
$completedInvIds = [];
if (!empty($invIds)) {
    $placeholders = implode(',', array_fill(0, count($invIds), '?'));
    $typesR = str_repeat('s', count($invIds));
    $stmtR = mysqli_prepare($db_conn, "SELECT DISTINCT inv_id FROM receipt WHERE inv_id IN ($placeholders)");
    mysqli_stmt_bind_param($stmtR, $typesR, ...$invIds);
    mysqli_stmt_execute($stmtR);
    $resR = mysqli_stmt_get_result($stmtR);
    while ($rr = mysqli_fetch_assoc($resR)) { $completedInvIds[$rr['inv_id']] = true; }
    mysqli_stmt_close($stmtR);
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Field Orders : <?php echo $business_name;?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
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
        .tp-action-success {
            color:#374151; background:#f3f4f6; border:1px solid #d1d5db;
        }
        .tp-action-success:hover { color:#111827; background:#e9eaec; }

        .tp-location-link {
            font-size:11px; font-weight:600; text-decoration:none; padding:4px 10px;
            border-radius:14px; display:inline-block; margin-top:4px;
            color:#2a78d6; background:#eaf2fc; border:1px solid #cfe1f7;
        }
        .tp-location-link:hover { color:#1a5eb0; background:#dcebfb; }
        .tp-line-item { font-size:12.5px; color:#3a3a38; }
        .tp-line-item + .tp-line-item { margin-top:2px; }

        .select2-container--default .select2-selection--single {
            border-radius: 4px; border: 1px solid #ced4da; height: auto; padding: 6px 10px; font-size: 0.875rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 1.5; padding: 0; color: #495057; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 100%; top: 0; right: 6px; }
        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single { border-color: #86b7fe; box-shadow: 0 0 0 .2rem rgba(13,110,253,.25); }
        .select2-dropdown { border: 1px solid #ced4da; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,.1); font-size: .875rem; }
        .select2-search--dropdown .select2-search__field { border: 1px solid #ced4da; border-radius: 4px; padding: 5px 8px; }
        .select2-container { width: 100% !important; }
    </style>
</head>
<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include((($Login_user_TYPEvl ?? '') === 'salesbdm') ? '../salesbdm/logo.php' : 'logo.php'); ?>
            <?php include((($Login_user_TYPEvl ?? '') === 'salesbdm') ? '../salesbdm/femi_menu.php' : 'femi_menu.php'); ?>
        </div>
        <div class="app-container">
            <?php include((($Login_user_TYPEvl ?? '') === 'salesbdm') ? '../salesbdm/app-header.php' : 'app-header.php'); ?>
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">

                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>Field Orders <?php if ($_isBdm): ?><small class="text-muted" style="font-size:13px;">(your districts' TPs)</small><?php endif; ?></h1>
                                </div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <a href="field-orders-summary" class="tp-action-link tp-action-success"><i class="material-icons" style="font-size:14px;vertical-align:middle;">bar_chart</i> View Per-TP Summary</a>
                        </div>

                        <form method="get" class="row g-2 align-items-end mb-3">
                            <div class="col-auto" style="min-width:260px;">
                                <label class="form-label">Territory Partner</label>
                                <select name="tp_filter" id="tp_filter_select" class="form-control">
                                    <option value="0">— All —</option>
                                    <?php foreach ($tpList as $tp): ?>
                                    <option value="<?=$tp['id']?>" <?=$tp_filter===(int)$tp['id']?'selected':''?>><?=htmlspecialchars($tp['name'])?> (<?=htmlspecialchars($tp['tp_id'])?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
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
                                                    <th>TP</th>
                                                    <th>Shop</th>
                                                    <th>Status</th>
                                                    <th>Details</th>
                                                    <th>Invoice</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($visits)): ?>
                                                <tr><td colspan="6" class="text-center text-muted">No field order entries in this date range.</td></tr>
                                                <?php else: foreach ($visits as $oid => $v): ?>
                                                <tr>
                                                    <td><?=htmlspecialchars(date("d-m-Y", strtotime($v['order_date'])))?></td>
                                                    <td>
                                                        <?=htmlspecialchars($v['tp_name'] ?? '-')?>
                                                        <?php if (!empty($v['tp_code'])): ?><br/><span class="text-muted" style="font-size:11px;"><?=htmlspecialchars($v['tp_code'])?></span><?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?=htmlspecialchars($v['shop_name'] ?? '-')?>
                                                        <?php if (!empty($v['shop_lat']) && !empty($v['shop_lng'])): ?>
                                                        <br/><a href="https://www.google.com/maps?q=<?=htmlspecialchars($v['shop_lat'])?>,<?=htmlspecialchars($v['shop_lng'])?>" target="_blank" class="tp-location-link">Shop Location</a>
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
                                                            <div class="tp-line-item"><?=htmlspecialchars($ln['product'] ?? '-')?>: <b><?=htmlspecialchars($ln['qty'])?></b> <span class="text-muted" style="font-size:11px;">(GST <?=htmlspecialchars($ln['gst'] !== null ? $ln['gst'] . '%' : '-')?>)</span></div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Reason: <?=htmlspecialchars($v['noorder_reason'])?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($v['new_order'] === 'yes'): ?>
                                                            <?php if (!empty($v['voided_at'])): ?>
                                                            <span class="tp-tag tp-tag-bad" <?php if (!empty($v['void_reason'])): ?>title="<?=htmlspecialchars($v['void_reason'])?>"<?php endif; ?>>Cancelled</span>
                                                            <?php elseif (!empty($v['invoiced_inv_id']) && isset($completedInvIds[$v['invoiced_inv_id']])): ?>
                                                            <span class="tp-tag tp-tag-good">Completed<?php if (!empty($v['inv_number'])): ?> — <?=htmlspecialchars($v['inv_number'])?><?php endif; ?></span>
                                                            <?php elseif (!empty($v['invoiced_inv_id'])): ?>
                                                            <span class="tp-tag tp-tag-info">Invoice started, not submitted yet</span>
                                                            <?php else: ?>
                                                            <span class="tp-tag tp-tag-neutral">Not invoiced yet</span>
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

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script>
    $(function () {
        $('#tp_filter_select').select2({
            placeholder: '— All —',
            width: '100%'
        }).on('change', function () {
            $(this).closest('form').submit();
        });
    });
    </script>
</body>
</html>
