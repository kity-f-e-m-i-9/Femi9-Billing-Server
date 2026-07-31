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
            o.pr_id, o.qty, o.invoiced_inv_id, o.assigned_by_ms_id, o.voided_at,
            s.name shop_name, s.latitude shop_lat, s.longitude shop_lng, p.productName, dm.ms_name
     FROM tp_orders o
     LEFT JOIN shop s ON s.id=o.shop_id
     LEFT JOIN products p ON p.id=o.pr_id
     LEFT JOIN marketing_staff dm ON dm.id=o.assigned_by_ms_id
     WHERE o.tp_id=? AND o.order_date BETWEEN ? AND ?
     ORDER BY o.order_date DESC, o.order_id DESC, o.id ASC"
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
            'dm_name'    => $r['ms_name'],
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

        .tp-location-link { font-size:11.5px; color:#6b7280; text-decoration:none; }
        .tp-location-link:hover { text-decoration:underline; }
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
                                                        <br/><a href="https://www.google.com/maps?q=<?=htmlspecialchars($v['shop_lat'])?>,<?=htmlspecialchars($v['shop_lng'])?>" target="_blank" class="tp-location-link">View Location</a>
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
                                                            <span class="tp-tag tp-tag-bad">Voided</span>
                                                            <?php elseif (!empty($v['invoiced_inv_id']) && isset($completedInvIds[$v['invoiced_inv_id']])): ?>
                                                            <a href="shop-invoice-print.php?invoiceid=<?=base64_encode($v['invoiced_inv_id'])?>" class="tp-action-link tp-action-success" target="_blank">Completed Invoice</a>
                                                            <?php elseif (!empty($v['invoiced_inv_id'])): ?>
                                                            <a href="shop-invoice-add.php?InvoiceID=<?=base64_encode($v['invoiced_inv_id'])?>&invuser=shop&action=edit" class="tp-action-link tp-action-primary">Continue Invoice</a>
                                                            <?php else: ?>
                                                            <a href="order-to-invoice.php?order_id=<?=urlencode($oid)?>" onclick="return confirm('Create an invoice for this order, using the shop and product/qty exactly as captured here?');" class="tp-action-link tp-action-primary">Invoice</a>
                                                            <a href="void-order.php?order_id=<?=urlencode($oid)?>" onclick="return confirm('Void this order? Use this when the shop no longer wants the product — the visit stays on record as Voided.');" class="tp-action-link tp-action-danger" style="margin-left:6px;">Void</a>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
