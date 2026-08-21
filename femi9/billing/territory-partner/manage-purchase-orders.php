<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today     = date("Y-m-d");
$from_date = $_REQUEST['frdate'] ?? date("Y-m-d", strtotime("-6 days"));
$to_date   = $_REQUEST['todate'] ?? $today;

$statusFilter = $_REQUEST['status_filter'] ?? 'all';
$allowedStatusFilters = ['all', 'waiting', 'completed', 'cancelled'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) $statusFilter = 'all';

// Computes the same subtotal/discount/payment-status breakdown that used to
// live only on the separate "Purchased Bill Copy" page — merged in here so
// there's one page for both purchase-order requests and their resulting
// bills, instead of two.
function tpBillInfo($db_conn, array $invRow): array {
    $courier = (float)($invRow['courier_charges'] ?? 0);
    $discount = (float)($invRow['discount_amount'] ?? 0);
    $totalAmount = (float)$invRow['total_amount'];
    $netAmount = round($totalAmount - $courier, 2);

    $stmt = $db_conn->prepare("SELECT COALESCE(SUM(deducted_amount),0) AS paid FROM tp_invoice_advance_log WHERE tp_invoice_id=?");
    $stmt->bind_param('i', $invRow['id']);
    $stmt->execute();
    $paid = (float)($stmt->get_result()->fetch_assoc()['paid'] ?? 0);
    $stmt->close();

    if ($paid <= 0) {
        $paymentStatus = 'not_paid';
    } elseif ($netAmount > 0 && ($paid + 0.01) >= $netAmount) {
        $paymentStatus = 'fully_paid';
    } else {
        $paymentStatus = 'partially_paid';
    }

    return [
        'subtotal' => $netAmount,
        'discount' => $discount,
        'payment_status' => $paymentStatus,
        'invoice_number' => $invRow['invoice_number'],
        'total' => $totalAmount,
    ];
}

function tpInvoiceItemLines($db_conn, int $invoiceId): array {
    $stmt = $db_conn->prepare(
        "SELECT ii.quantity, ii.rate, ii.amount, p.productName
         FROM tp_invoice_items ii LEFT JOIN products p ON p.id = ii.product_id
         WHERE ii.tp_invoice_id = ? ORDER BY ii.id ASC"
    );
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $lines = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $lines[] = ['product' => $r['productName'], 'qty' => (int)$r['quantity'], 'price' => (float)$r['rate'], 'amount' => (float)$r['amount']];
    }
    $stmt->close();
    return $lines;
}

// 1) Purchase-order requests (the original "waiting → completed/cancelled"
// list). For ones still waiting/cancelled, the PO's own requested items are
// the only record. For completed ones, items/total get replaced below with
// the actual invoice — the company may have adjusted qty/price when billing.
$stmt = mysqli_prepare($db_conn,
    "SELECT o.id, o.order_date, o.status, o.tp_invoice_id, o.cancel_reason, o.approver_type, o.approver_ss_id, o.product_type,
            ss.name AS approver_ss_name,
            i.product_id, i.qty, i.price, i.amount, p.productName
     FROM tp_purchase_orders o
     LEFT JOIN tp_purchase_order_items i ON i.po_id = o.id
     LEFT JOIN products p ON p.id = i.product_id
     LEFT JOIN super_stockiest ss ON ss.id = o.approver_ss_id
     WHERE o.territory_partner_id=? AND o.order_date BETWEEN ? AND ?"
    . ($statusFilter !== 'all' ? " AND o.status = ?" : '')
    . "\n     ORDER BY o.order_date DESC, o.id DESC, i.id ASC"
);
if ($statusFilter !== 'all') {
    mysqli_stmt_bind_param($stmt, "isss", $Login_user_IDvl, $from_date, $to_date, $statusFilter);
} else {
    mysqli_stmt_bind_param($stmt, "iss", $Login_user_IDvl, $from_date, $to_date);
}
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$orders = [];
foreach ($rows as $r) {
    $key = 'po_' . $r['id'];
    if (!isset($orders[$key])) {
        $orders[$key] = [
            'kind'          => 'po',
            'po_id'         => (int)$r['id'],
            'display_date'  => $r['order_date'],
            'status'        => $r['status'],
            'tp_invoice_id' => $r['tp_invoice_id'],
            'cancel_reason' => $r['cancel_reason'],
            'approver_label' => $r['approver_type'] === 'ss' ? ($r['approver_ss_name'] ?? 'Super Stockist') : 'Company',
            'product_type'  => $r['product_type'] ?? 'napkin',
            'lines'         => [],
            'total'         => 0,
            'bill'          => null,
        ];
    }
    if ($r['product_id']) {
        $orders[$key]['lines'][] = ['product' => $r['productName'], 'qty' => (int)$r['qty'], 'price' => (float)$r['price'], 'amount' => (float)$r['amount']];
        $orders[$key]['total'] += (float)$r['amount'];
    }
}

// 2) Attach real bill info (and swap in the actual billed items) for every
// completed PO-linked order.
$invIds = array_values(array_unique(array_filter(array_column($orders, 'tp_invoice_id'))));
$invNumbers = [];
if (!empty($invIds)) {
    $placeholders = implode(',', array_fill(0, count($invIds), '?'));
    $types = str_repeat('i', count($invIds));
    $stmtI = $db_conn->prepare("SELECT * FROM tp_invoices WHERE id IN ($placeholders)");
    $stmtI->bind_param($types, ...$invIds);
    $stmtI->execute();
    $invRows = $stmtI->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtI->close();

    $invById = [];
    foreach ($invRows as $ir) { $invById[$ir['id']] = $ir; $invNumbers[$ir['id']] = $ir['invoice_number']; }

    foreach ($orders as $key => $o) {
        if ($o['tp_invoice_id'] && isset($invById[$o['tp_invoice_id']])) {
            $orders[$key]['bill'] = tpBillInfo($db_conn, $invById[$o['tp_invoice_id']]);
            $orders[$key]['lines'] = tpInvoiceItemLines($db_conn, (int)$o['tp_invoice_id']);
            $orders[$key]['total'] = $orders[$key]['bill']['total'];
        }
    }
}

// 3) Invoices the company created directly, with no originating purchase
// order — these used to only be visible on "Purchased Bill Copy". Only
// relevant when the status filter isn't narrowed to waiting/cancelled.
if ($statusFilter === 'all' || $statusFilter === 'completed') {
    $stmtD = mysqli_prepare($db_conn,
        "SELECT * FROM tp_invoices
         WHERE territory_partner_id = ? AND invoice_date BETWEEN ? AND ?
           AND id NOT IN (SELECT tp_invoice_id FROM tp_purchase_orders WHERE tp_invoice_id IS NOT NULL)
         ORDER BY invoice_date DESC, id DESC"
    );
    mysqli_stmt_bind_param($stmtD, "iss", $Login_user_IDvl, $from_date, $to_date);
    mysqli_stmt_execute($stmtD);
    $directInvoices = mysqli_stmt_get_result($stmtD)->fetch_all(MYSQLI_ASSOC);
    mysqli_stmt_close($stmtD);

    foreach ($directInvoices as $inv) {
        $key = 'inv_' . $inv['id'];
        $orders[$key] = [
            'kind'          => 'invoice',
            'po_id'         => null,
            'display_date'  => $inv['invoice_date'],
            'status'        => 'completed',
            'tp_invoice_id' => $inv['id'],
            'cancel_reason' => null,
            // Direct invoices (no originating PO) are created either by
            // Company or, for TPs assigned to an SS, by that SS directly
            // from their own stock (super-stockist/tp-invoice-action.php) —
            // both always draw from the company-approved pool today (see
            // that file's own $invoiceApprover), so labeling by creator here
            // reflects who billed it, not a routing choice the TP made.
            'approver_label' => ($inv['created_by_user_type'] ?? '') === 'super_stockiest' ? 'Super Stockist' : 'Company',
            // No originating PO to carry a type from — Phase 2 adds
            // product_type directly to tp_invoices; until then this is left
            // null (badge omitted) rather than guessed from line items here.
            'product_type'  => $inv['product_type'] ?? null,
            'lines'         => tpInvoiceItemLines($db_conn, (int)$inv['id']),
            'total'         => (float)$inv['total_amount'],
            'bill'          => tpBillInfo($db_conn, $inv),
        ];
        $invNumbers[$inv['id']] = $inv['invoice_number'];
    }
}

uasort($orders, function ($a, $b) {
    $cmp = strtotime($b['display_date']) <=> strtotime($a['display_date']);
    if ($cmp !== 0) return $cmp;
    return ($b['tp_invoice_id'] ?? $b['po_id']) <=> ($a['tp_invoice_id'] ?? $a['po_id']);
});

$totalOrders     = count($orders);
$totalAmount     = array_sum(array_column($orders, 'total'));
$waitingCount    = count(array_filter($orders, fn($o) => $o['status'] === 'waiting'));
$completedCount  = count(array_filter($orders, fn($o) => $o['status'] === 'completed'));
$cancelledCount  = count(array_filter($orders, fn($o) => $o['status'] === 'cancelled'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Purchase Orders : <?php echo $business_name;?></title>
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

        .btn-new-order {
            display: inline-flex; align-items: center; gap: 6px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff;
            padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 13.5px;
            text-decoration: none; transition: all .2s;
        }
        .btn-new-order:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,.35); color: #fff; }

        .po-stat-card {
            background: #fff; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06); border-left: 4px solid;
            display: flex; align-items: center; justify-content: space-between;
        }
        .po-stat-card h3 { font-size: 22px; font-weight: 700; margin: 0; color: #1f2937; }
        .po-stat-card p { margin: 2px 0 0; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; }
        .po-stat-card .material-icons-outlined { font-size: 32px; opacity: .15; }
        .po-stat-card.purple { border-color: #667eea; } .po-stat-card.purple .material-icons-outlined { color: #667eea; }
        .po-stat-card.orange { border-color: #f59e0b; } .po-stat-card.orange .material-icons-outlined { color: #f59e0b; }
        .po-stat-card.amber  { border-color: #f59e0b; } .po-stat-card.amber  .material-icons-outlined { color: #f59e0b; }
        .po-stat-card.green  { border-color: #10b981; } .po-stat-card.green  .material-icons-outlined { color: #10b981; }
        .po-stat-card.red    { border-color: #ef4444; } .po-stat-card.red    .material-icons-outlined { color: #ef4444; }

        .po-filter-card { background: #fff; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .po-filter-card .form-label { font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 4px; }

        .po-table-card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .po-table { width: 100%; margin: 0; }
        .po-table thead th {
            background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .4px; padding: 12px 16px;
            border-bottom: 2px solid #e5e7eb; white-space: nowrap;
        }
        .po-table tbody td { padding: 13px 16px; vertical-align: middle; font-size: 13.5px; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
        .po-table tbody tr:last-child td { border-bottom: none; }
        .po-table tbody tr:hover { background: #f8fafc; }

        .po-status-pill { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; display: inline-block; }
        .po-status-pill.waiting   { background: #fef3c7; color: #92400e; }
        .po-status-pill.completed { background: #d1fae5; color: #065f46; }
        .po-status-pill.cancelled { background: #fee2e2; color: #991b1b; }

        .po-items-trigger {
            border: none; cursor: pointer; background: #667eea; color: #fff;
            font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 20px; white-space: nowrap;
        }
        .po-invoice-chip { font-size: 12px; background: #f3f4f6; padding: 3px 9px; border-radius: 5px; color: #667eea; font-weight: 600; text-decoration: none; }

        .po-delete-btn {
            border: none; cursor: pointer; background: #fee2e2; color: #991b1b;
            font-size: 11.5px; font-weight: 600; padding: 5px 12px; border-radius: 7px;
            display: inline-flex; align-items: center; gap: 4px; transition: all .15s;
        }
        .po-delete-btn:hover { background: #fecaca; }

        .po-actions { display: flex; flex-wrap: wrap; gap: 6px; }
        .po-view-btn, .po-print-btn {
            border: none; cursor: pointer; font-size: 11.5px; font-weight: 600;
            padding: 5px 12px; border-radius: 7px; display: inline-flex; align-items: center;
            gap: 4px; transition: all .15s; text-decoration: none; white-space: nowrap;
        }
        .po-view-btn { background: #ede9fe; color: #5b21b6; }
        .po-view-btn:hover { background: #ddd6fe; color: #5b21b6; }
        .po-print-btn { background: #d1fae5; color: #065f46; }
        .po-print-btn:hover { background: #a7f3d0; color: #065f46; }

        .po-empty { text-align: center; padding: 50px 20px; color: #9ca3af; }
        .po-empty .material-icons-outlined { font-size: 40px; display: block; margin: 0 auto 10px; opacity: .4; }
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
                            <div class="col d-flex align-items-center justify-content-between flex-wrap" style="gap:10px;">
                                <div class="page-description">
                                    <h1 style="margin:0;">My Purchase Orders</h1>
                                </div>
                                <a href="add-purchase-order.php" class="btn-new-order">
                                    <i class="material-icons" style="font-size:17px;">add</i> New Purchase Order
                                </a>
                            </div>
                        </div>
                        <br/>

                        <?php
                            $_poJustSubmitted = isset($_SESSION['successMessage']) && $_SESSION['successMessage'] === 'Purchase order submitted successfully.';
                        ?>
                        <?php if (isset($_SESSION['successMessage'])): ?>
                        <div class="alert alert-success"><?=htmlspecialchars($_SESSION['successMessage']); unset($_SESSION['successMessage']);?></div>
                        <?php endif; ?>
                        <?php if ($_poJustSubmitted): ?>
                        <div class="po-eta-banner">
                            <div class="po-eta-banner-track">
                                <span><i class="material-icons-outlined">local_shipping</i> Your purchase order will reach you within 3 to 5 working days.</span>
                            </div>
                        </div>
                        <?php include __DIR__ . '/include/po-eta-banner-style.php'; ?>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['errorMessage'])): ?>
                        <div class="alert alert-danger"><?=htmlspecialchars($_SESSION['errorMessage']); unset($_SESSION['errorMessage']);?></div>
                        <?php endif; ?>

                        <!-- Stats -->
                        <div class="row">
                            <div class="col-lg-3 col-sm-6">
                                <div class="po-stat-card purple">
                                    <div><h3><?=$totalOrders?></h3><p>Total Orders</p></div>
                                    <i class="material-icons-outlined">receipt_long</i>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="po-stat-card orange">
                                    <div><h3 style="font-size:18px;">₹<?=number_format($totalAmount, 2)?></h3><p>Total Amount</p></div>
                                    <i class="material-icons-outlined">currency_rupee</i>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="po-stat-card amber">
                                    <div><h3><?=$waitingCount?></h3><p>Waiting</p></div>
                                    <i class="material-icons-outlined">hourglass_top</i>
                                </div>
                            </div>
                            <div class="col-lg-3 col-sm-6">
                                <div class="po-stat-card green">
                                    <div><h3><?=$completedCount?></h3><p>Completed</p></div>
                                    <i class="material-icons-outlined">check_circle</i>
                                </div>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="po-filter-card">
                            <form method="get" class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">From Date</label>
                                    <input type="date" name="frdate" value="<?=htmlspecialchars($from_date)?>" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">To Date</label>
                                    <input type="date" name="todate" value="<?=htmlspecialchars($to_date)?>" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status_filter" class="form-control">
                                        <option value="all" <?=$statusFilter === 'all' ? 'selected' : ''?>>All</option>
                                        <option value="waiting" <?=$statusFilter === 'waiting' ? 'selected' : ''?>>Waiting</option>
                                        <option value="completed" <?=$statusFilter === 'completed' ? 'selected' : ''?>>Completed</option>
                                        <option value="cancelled" <?=$statusFilter === 'cancelled' ? 'selected' : ''?>>Cancelled<?=$cancelledCount ? ' (' . $cancelledCount . ')' : ''?></option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary"><i class="material-icons" style="vertical-align:middle;font-size:17px;">filter_list</i> Filter</button>
                                    <a href="manage-purchase-orders.php" class="btn btn-outline-secondary"><i class="material-icons" style="vertical-align:middle;font-size:17px;">refresh</i> Reset</a>
                                </div>
                            </form>
                        </div>

                        <!-- Table -->
                        <div class="po-table-card">
                            <div style="overflow-x:auto;">
                                <table class="po-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Invoice No.</th>
                                            <th>Approver</th>
                                            <th>Type</th>
                                            <th>Products</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($orders)): ?>
                                        <tr><td colspan="8">
                                            <div class="po-empty">
                                                <i class="material-icons-outlined">inbox</i>
                                                No purchase orders in this date range.
                                            </div>
                                        </td></tr>
                                        <?php else: foreach ($orders as $o):
                                            $items_json = htmlspecialchars(json_encode($o['lines'], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                                            $bill_json  = htmlspecialchars(json_encode($o['bill'], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                                        ?>
                                        <tr>
                                            <td><?=htmlspecialchars(date("d-m-Y", strtotime($o['display_date'])))?></td>
                                            <td>
                                                <?php if ($o['status'] === 'completed' && $o['tp_invoice_id'] && isset($invNumbers[$o['tp_invoice_id']])): ?>
                                                <a href="purchased-bill-print.php?id=<?=urlencode(base64_encode($o['tp_invoice_id']))?>" target="_blank" title="Open Invoice" class="po-invoice-chip">
                                                    <?=htmlspecialchars($invNumbers[$o['tp_invoice_id']])?>
                                                </a>
                                                <?php if ($o['bill']): ?>
                                                <div class="mt-1">
                                                    <?php if ($o['bill']['payment_status'] === 'fully_paid'): ?>
                                                    <span class="po-status-pill completed" style="font-size:9.5px;">Fully Paid</span>
                                                    <?php elseif ($o['bill']['payment_status'] === 'partially_paid'): ?>
                                                    <span class="po-status-pill waiting" style="font-size:9.5px;">Partially Paid</span>
                                                    <?php else: ?>
                                                    <span class="po-status-pill cancelled" style="font-size:9.5px;">Not Paid</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                                <?php else: ?>
                                                <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="text-muted" style="font-size:12.5px;"><?=htmlspecialchars($o['approver_label'])?></span></td>
                                            <td>
                                                <?php if ($o['product_type']): [$tBg, $tFg] = tpProductTypeBadgeColors($o['product_type']); ?>
                                                <span style="font-size:10.5px;font-weight:700;padding:3px 9px;border-radius:12px;background:<?=$tBg?>;color:<?=$tFg?>;"><?=htmlspecialchars(tpProductTypeLabel($o['product_type']))?></span>
                                                <?php else: ?>
                                                <span class="text-muted" style="font-size:11px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="po-items-trigger items-view-trigger" data-items="<?=$items_json?>" data-bill="<?=$bill_json?>">
                                                    <?=count($o['lines'])?> item<?=count($o['lines']) !== 1 ? 's' : ''?>
                                                </button>
                                            </td>
                                            <td><strong>₹<?=number_format($o['total'], 2)?></strong></td>
                                            <td>
                                                <?php if ($o['status'] === 'completed'): ?>
                                                <span class="po-status-pill completed">Completed</span>
                                                <?php elseif ($o['status'] === 'cancelled'): ?>
                                                <span class="po-status-pill cancelled" <?=$o['cancel_reason'] ? 'title="' . htmlspecialchars($o['cancel_reason'], ENT_QUOTES) . '"' : ''?>>Cancelled</span>
                                                <?php if ($o['cancel_reason']): ?>
                                                <div class="text-muted" style="font-size:11.5px;margin-top:3px;"><?=htmlspecialchars($o['cancel_reason'])?></div>
                                                <?php endif; ?>
                                                <?php else: ?>
                                                <span class="po-status-pill waiting">Waiting</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="po-actions">
                                                    <button type="button" class="po-view-btn items-view-trigger" data-items="<?=$items_json?>" data-bill="<?=$bill_json?>" title="View order in detail">
                                                        <i class="material-icons" style="font-size:14px;">visibility</i> View
                                                    </button>
                                                    <?php if ($o['status'] === 'completed' && $o['tp_invoice_id'] && isset($invNumbers[$o['tp_invoice_id']])): ?>
                                                    <a href="purchased-bill-print.php?id=<?=urlencode(base64_encode($o['tp_invoice_id']))?>" target="_blank" class="po-print-btn" title="Print purchased bill copy">
                                                        <i class="material-icons" style="font-size:14px;">print</i> Print
                                                    </a>
                                                    <button type="button" class="po-print-btn" title="Share purchased bill to WhatsApp"
                                                        data-id="<?=base64_encode($o['tp_invoice_id'])?>"
                                                        data-invoice="<?=htmlspecialchars($invNumbers[$o['tp_invoice_id']])?>"
                                                        onclick="sharePODirect(this)">
                                                        <i class="material-icons" style="font-size:14px;">share</i> Share
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php if ($o['kind'] === 'po' && $o['status'] === 'waiting'): ?>
                                                    <form method="post" action="delete-purchase-order.php" onsubmit="return confirm('Delete this purchase order? This cannot be undone.');">
                                                        <input type="hidden" name="po_id" value="<?=(int)$o['po_id']?>">
                                                        <button type="submit" class="po-delete-btn"><i class="material-icons" style="font-size:14px;">delete</i> Delete</button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
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

    <!-- Order Items Modal -->
    <div class="modal fade" id="itemsViewModal" tabindex="-1" aria-labelledby="itemsViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-md">
            <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
                <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                    <h6 class="modal-title" id="itemsViewModalLabel" style="font-weight:700;color:#1f2937;">
                        <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#667eea;">inventory_2</i>
                        Order Items
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="itemsViewModalBody" style="padding:18px 22px;">
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
    $(document).on('click', '.items-view-trigger', function () {
        var items = $(this).data('items');
        var bill = $(this).data('bill');
        var html = '';
        var grandTotal = 0;

        $('#itemsViewModalLabel').html(
            '<i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#667eea;">' +
            (bill ? 'receipt_long' : 'inventory_2') + '</i>' + (bill ? 'Bill Copy' : 'Order Items')
        );

        if (items.length) {
            html += '<table style="width:100%;font-size:13.5px;border-collapse:collapse;">' +
                    '<thead><tr style="color:#6b7280;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;">' +
                    '<th style="text-align:left;padding:6px 12px 6px 0;">Product</th>' +
                    '<th style="text-align:right;padding:6px 12px;width:50px;">Qty</th>' +
                    '<th style="text-align:right;padding:6px 12px;width:90px;">Price</th>' +
                    '<th style="text-align:right;padding:6px 0;width:100px;">Total</th>' +
                    '</tr></thead><tbody>';
            $.each(items, function (_, item) {
                var price = parseFloat(item.price) || 0;
                var amount = parseFloat(item.amount) || 0;
                grandTotal += amount;
                html += '<tr style="border-bottom:1px dotted #f3f4f6;color:#1f2937;">' +
                        '<td style="padding:8px 12px 8px 0;">' + $('<div>').text(item.product || '-').html() + '</td>' +
                        '<td style="text-align:right;padding:8px 12px;">' + $('<div>').text(item.qty).html() + '</td>' +
                        '<td style="text-align:right;padding:8px 12px;white-space:nowrap;">₹' + price.toFixed(2) + '</td>' +
                        '<td style="text-align:right;padding:8px 0;white-space:nowrap;"><strong>₹' + amount.toFixed(2) + '</strong></td>' +
                        '</tr>';
            });
            html += '</tbody><tfoot><tr>' +
                    '<td colspan="3" style="text-align:right;padding:12px 12px 0 0;font-weight:700;color:#374151;">Grand Total</td>' +
                    '<td style="text-align:right;padding:12px 0 0;font-weight:700;color:#10b981;white-space:nowrap;">₹' + grandTotal.toFixed(2) + '</td>' +
                    '</tr></tfoot></table>';
        }

        if (bill) {
            var statusLabel = bill.payment_status === 'fully_paid' ? 'Fully Paid'
                : (bill.payment_status === 'partially_paid' ? 'Partially Paid' : 'Not Paid');
            var statusColor = bill.payment_status === 'fully_paid' ? '#065f46'
                : (bill.payment_status === 'partially_paid' ? '#92400e' : '#991b1b');
            var statusBg = bill.payment_status === 'fully_paid' ? '#d1fae5'
                : (bill.payment_status === 'partially_paid' ? '#fef3c7' : '#fee2e2');

            html = '<div style="background:#f8fafc;border-radius:10px;padding:14px 16px;margin-bottom:16px;font-size:13.5px;">' +
                '<div class="d-flex justify-content-between mb-1"><span style="color:#6b7280;">Sub Total</span><strong>₹' + parseFloat(bill.subtotal).toFixed(2) + '</strong></div>' +
                '<div class="d-flex justify-content-between mb-1"><span style="color:#6b7280;">Discount</span><strong>₹' + parseFloat(bill.discount).toFixed(2) + '</strong></div>' +
                '<div class="d-flex justify-content-between mb-1"><span style="color:#6b7280;">Total</span><strong>₹' + parseFloat(bill.total).toFixed(2) + '</strong></div>' +
                '<div class="d-flex justify-content-between align-items-center"><span style="color:#6b7280;">Payment Status</span>' +
                '<span style="background:' + statusBg + ';color:' + statusColor + ';padding:3px 10px;border-radius:20px;font-size:10.5px;font-weight:700;text-transform:uppercase;">' + statusLabel + '</span></div>' +
                '</div>' + html;
        }

        $('#itemsViewModalBody').html(html || '<div style="color:#9ca3af;">No items.</div>');
        $('#itemsViewModal').modal('show');
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
    <script src="../../assets/js/whatsapp-invoice-share.js?v=6"></script>
    <script>
    // Shares a purchased bill straight to WhatsApp from this list — no
    // detour through the print page. This click is a real user gesture, so
    // the whole async chain below (fetch -> PDF -> alert -> WhatsApp) keeps
    // that gesture's permission to open a window, same as the print page's
    // own button. Note: this page doesn't have the TP's own mobile number in
    // scope, so mobile is left blank here — the shared helper still works
    // fine (mobile is only used to prefill the desktop wa.me fallback link).
    function sharePODirect(btn) {
        var id             = btn.getAttribute('data-id');
        var invoiceNumber  = btn.getAttribute('data-invoice');
        var originalHtml   = btn.innerHTML;

        btn.disabled  = true;
        btn.innerHTML = '&hellip;';

        var iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:fixed;left:-9999px;top:0;width:900px;height:600px;border:0;';
        iframe.onload = function () {
            var idoc     = iframe.contentDocument;
            var printDiv = idoc && idoc.getElementById('divToPrint');

            if (!printDiv) {
                btn.disabled  = false;
                btn.innerHTML = originalHtml;
                iframe.remove();
                alert('Could not prepare the bill. Please try again.');
                return;
            }

            btn.disabled  = false;
            btn.innerHTML = originalHtml;

            shareInvoiceToWhatsApp({
                elementId:     'divToPrint',
                doc:           idoc,
                mobile:        '',
                invoiceNumber: invoiceNumber,
                fileName:      'PurchaseBill_' + invoiceNumber,
                businessName:  <?php echo json_encode($business_name ?? ''); ?>,
                button:        btn
            });

            setTimeout(function () { iframe.remove(); }, 15000);
        };
        iframe.src = 'purchased-bill-print.php?id=' + encodeURIComponent(id);
        document.body.appendChild(iframe);
    }
    </script>
</body>
</html>
