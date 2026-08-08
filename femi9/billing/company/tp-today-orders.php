<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("include/GodownAccess.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

date_default_timezone_set("Asia/Kolkata");
$today = date("Y-m-d");

// The date range only applies once the Filter button is actually submitted
// (any of these three fields present in the query string). A fresh, un-
// filtered page load instead shows every still-waiting order regardless of
// date — that's the actionable "things to process" queue.
$filterSubmitted = isset($_GET['from_date']) || isset($_GET['to_date']) || isset($_GET['status_filter']);

if ($filterSubmitted) {
    $from_date = $_GET['from_date'] ?? $today;
    $to_date   = $_GET['to_date']   ?? $today;
    if (strtotime($from_date) > strtotime($to_date)) { [$from_date, $to_date] = [$to_date, $from_date]; }

    // Default view (once filtering) hides cancelled orders so they don't
    // clutter the daily work queue; the status filter lets company
    // specifically pull them up.
    $statusFilter = $_GET['status_filter'] ?? 'active';
    $allowedStatusFilters = ['active', 'waiting', 'completed', 'cancelled', 'all'];
    if (!in_array($statusFilter, $allowedStatusFilters, true)) $statusFilter = 'active';
} else {
    $from_date = '';
    $to_date = '';
    $statusFilter = 'waiting';
}

$whereSql = 'WHERE 1=1';
$bindTypes = '';
$bindValues = [];
if ($filterSubmitted) {
    $whereSql .= ' AND o.order_date BETWEEN ? AND ?';
    $bindTypes .= 'ss';
    $bindValues[] = $from_date;
    $bindValues[] = $to_date;
}
if ($statusFilter === 'active') {
    $whereSql .= " AND o.status != 'cancelled'";
} elseif (in_array($statusFilter, ['waiting', 'completed', 'cancelled'], true)) {
    $whereSql .= ' AND o.status = ?';
    $bindTypes .= 's';
    $bindValues[] = $statusFilter;
}

$stmt = $db_conn->prepare(
    "SELECT o.id, o.order_date, o.status, o.tp_invoice_id, o.excess_amount,
            o.cancelled_at, o.cancelled_by, o.cancel_reason,
            tp.id AS tp_db_id, tp.name AS tp_name, tp.tp_id AS tp_code,
            i.product_id, i.qty, i.price, i.amount, p.productName
     FROM tp_purchase_orders o
     JOIN territory_partners tp ON tp.id = o.territory_partner_id
     LEFT JOIN tp_purchase_order_items i ON i.po_id = o.id
     LEFT JOIN products p ON p.id = i.product_id
     $whereSql
     ORDER BY o.id DESC, i.id ASC"
);
if ($bindTypes !== '') {
    $stmt->bind_param($bindTypes, ...$bindValues);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$orders = [];
$productTotals = []; // productName => qty
foreach ($rows as $r) {
    $oid = $r['id'];
    if (!isset($orders[$oid])) {
        $orders[$oid] = [
            'tp_db_id'      => $r['tp_db_id'],
            'tp_name'       => $r['tp_name'],
            'tp_code'       => $r['tp_code'],
            'status'        => $r['status'],
            'tp_invoice_id' => $r['tp_invoice_id'],
            'excess_amount' => (float)$r['excess_amount'],
            'cancelled_at'  => $r['cancelled_at'],
            'cancelled_by'  => $r['cancelled_by'],
            'cancel_reason' => $r['cancel_reason'],
            'lines'         => [],
            'total'         => 0,
            'screenshots'   => [],
        ];
    }
    if ($r['product_id']) {
        $orders[$oid]['lines'][] = ['pid' => $r['product_id'], 'product' => $r['productName'], 'qty' => $r['qty'], 'price' => (float)$r['price'], 'amount' => (float)$r['amount']];
        $orders[$oid]['total'] += (float)$r['amount'];
        $pname = $r['productName'] ?? 'Unknown';
        $productTotals[$pname] = ($productTotals[$pname] ?? 0) + (int)$r['qty'];
    }
}

$totalOrders    = count($orders);
$totalAmount    = array_sum(array_column($orders, 'total'));
$totalProductQty = array_sum($productTotals);
$totalProductCount = count($productTotals);
$completedCount = count(array_filter($orders, fn($o) => $o['status'] === 'completed'));

arsort($productTotals);

$invNumbers = [];
$invIds = array_values(array_unique(array_filter(array_column($orders, 'tp_invoice_id'))));
if (!empty($invIds)) {
    $placeholders = implode(',', array_fill(0, count($invIds), '?'));
    $types = str_repeat('i', count($invIds));
    $stmtI = $db_conn->prepare("SELECT id, invoice_number FROM tp_invoices WHERE id IN ($placeholders)");
    $stmtI->bind_param($types, ...$invIds);
    $stmtI->execute();
    $resI = $stmtI->get_result();
    while ($ir = $resI->fetch_assoc()) { $invNumbers[$ir['id']] = $ir['invoice_number']; }
    $stmtI->close();
}

// Payment-proof screenshots linked to the orders in view — only orders with
// excess_amount > 0 ever required these, but it's simplest to just fetch by
// order id set and let ones with none come back empty.
$orderIds = array_keys($orders);
if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $types = str_repeat('i', count($orderIds));
    $stmtS = $db_conn->prepare(
        "SELECT id, po_id, file_path, detected_amount, reference_number, status, rejection_reason, advance_payment_id
         FROM tp_purchase_order_screenshots WHERE po_id IN ($placeholders) ORDER BY id ASC"
    );
    $stmtS->bind_param($types, ...$orderIds);
    $stmtS->execute();
    $resS = $stmtS->get_result();
    while ($sr = $resS->fetch_assoc()) {
        $orders[$sr['po_id']]['screenshots'][] = $sr;
    }
    $stmtS->close();
}

// Each TP's current advance balance, batched for every TP appearing in this
// result set — same three-condition filter used everywhere else this figure
// is shown (add-purchase-order.php, dashboard.php, mis-report.php).
$tpBalances = [];
$tpPendingSubmissions = []; // territory_partner_id => count of pending_review/accepted submissions
$tpDbIds = array_values(array_unique(array_column($orders, 'tp_db_id')));
if (!empty($tpDbIds)) {
    $placeholders = implode(',', array_fill(0, count($tpDbIds), '?'));
    $types = str_repeat('i', count($tpDbIds));

    $stmtB = $db_conn->prepare(
        "SELECT territory_partner_id, COALESCE(SUM(balance_amount), 0) AS bal
         FROM tp_advance_payments
         WHERE territory_partner_id IN ($placeholders) AND balance_amount > 0 AND status != 'fully_adjusted' AND deleted_at IS NULL
         GROUP BY territory_partner_id"
    );
    $stmtB->bind_param($types, ...$tpDbIds);
    $stmtB->execute();
    $resB = $stmtB->get_result();
    while ($br = $resB->fetch_assoc()) { $tpBalances[(int)$br['territory_partner_id']] = (float)$br['bal']; }
    $stmtB->close();

    // Whether the TP has a live advance-payment submission at all (any
    // status other than draft) — shown as a nudge to check for it, since
    // there's no direct FK from an order to a specific submission.
    $stmtP = $db_conn->prepare(
        "SELECT territory_partner_id, COUNT(*) AS cnt
         FROM tp_advance_payment_submissions
         WHERE territory_partner_id IN ($placeholders) AND status IN ('pending_review', 'accepted')
         GROUP BY territory_partner_id"
    );
    $stmtP->bind_param($types, ...$tpDbIds);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    while ($pr = $resP->fetch_assoc()) { $tpPendingSubmissions[(int)$pr['territory_partner_id']] = (int)$pr['cnt']; }
    $stmtP->close();
}

// Company profiles for the "Add to TP Payment Entry" form — same source/filter
// as add-tp-advance-payment.php's Company Profile dropdown.
$companyProfiles = $db_conn->query(
    "SELECT id, gname FROM company_godown WHERE gname LIKE '%Femi%' AND " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Today Order : <?php echo $business_name; ?></title>
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
        body { font-family: 'Poppins', sans-serif; }

        .filter-card { background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-radius:10px; padding:20px; margin-bottom:20px; }
        .filter-card .form-label { color:#fff; font-weight:500; margin-bottom:5px; }
        .filter-card .form-control { background:rgba(255,255,255,0.95); border:none; border-radius:6px; }

        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 18px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            border-left: 4px solid;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }
        .stat-card.purple { border-color: #667eea; }
        .stat-card.orange { border-color: #f59e0b; }
        .stat-card.blue   { border-color: #3b82f6; }
        .stat-card.green  { border-color: #10b981; }
        .stat-card h3 { font-size: 26px; font-weight: 700; margin: 0 0 2px 0; color: #1f2937; }
        .stat-card p  { margin: 0; font-size: 11.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
        .stat-card .stat-sub { font-size: 12.5px; color: #9ca3af; font-weight: 500; }
        .stat-icon { font-size: 36px; opacity: .12; }
        .stat-card.purple .stat-icon { color: #667eea; }
        .stat-card.orange .stat-icon { color: #f59e0b; }
        .stat-card.blue   .stat-icon { color: #3b82f6; }
        .stat-card.green  .stat-icon { color: #10b981; }

        .stat-card.hoverable { cursor: help; }
        .stat-tooltip {
            display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 20;
            background: #fff; border-radius: 10px; padding: 12px 16px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15); margin-top: 6px; max-height: 260px; overflow-y: auto;
        }
        .stat-card.hoverable:hover .stat-tooltip { display: block; }
        .stat-tooltip-row { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; padding: 5px 0; border-bottom: 1px dotted #f3f4f6; color: #374151; }
        .stat-tooltip-row:last-child { border-bottom: none; }
        .stat-tooltip-row strong { color: #1f2937; }

        .card { border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); border: none; margin-bottom: 20px; }
        .card-header {
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
            border-radius: 10px 10px 0 0 !important;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-header-title { font-size: 14px; font-weight: 600; color: #2c3e50; margin: 0; display: flex; align-items: center; gap: 8px; }
        .card-header-title i { font-size: 18px; color: #667eea; }

        table#datatable1 thead th { font-size: 11.5px !important; font-weight: 600 !important; color: #6b7280 !important; text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
        table#datatable1 tbody td { font-size: 13.5px; vertical-align: middle; }

        .badge-waiting   { background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-completed { background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .btn-cancel-order {
            border: none; cursor: pointer; background: #fee2e2; color: #991b1b;
            font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; white-space: nowrap; margin-left: 6px;
        }
        .btn-cancel-order:hover { background: #fecaca; }

        .btn-invoice {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; border-radius: 7px; font-size: 12.5px; font-weight: 500;
            border: none; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff; transition: all .2s; text-decoration: none;
        }
        .btn-invoice:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,.4); color: #fff; }

        .items-view-trigger {
            border: none; cursor: pointer; background: #667eea; color: #fff;
            font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; white-space: nowrap;
        }

        /* Payment Proof modal */
        #proofViewModal .modal-header { padding: 20px 24px; }
        #proofViewModal .modal-body { padding: 22px 24px; background: #f8f9fb; }
        #proofViewModal .modal-footer { padding: 14px 24px; }
        #proofViewModalLabel { font-size: 15px; }
        #proofViewModalSubtitle { font-size: 12.5px; color: #6b7280; margin-top: 2px; }

        .proof-card {
            background: #fff; border: 1px solid #edeef1; border-radius: 14px;
            padding: 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(16,24,40,.04);
        }
        .proof-card:last-child { margin-bottom: 0; }
        .proof-card-body { display: flex; gap: 22px; align-items: flex-start; }
        .proof-media { flex: 0 0 150px; }
        .proof-media img {
            width: 150px; height: 150px; object-fit: cover; border-radius: 10px;
            border: 1px solid #e5e7eb; display: block; transition: box-shadow .15s;
        }
        .proof-media a:hover img { box-shadow: 0 4px 14px rgba(16,24,40,.12); }
        .proof-main { flex: 1; min-width: 0; }
        .proof-card-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
        .proof-badge {
            font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 20px;
            letter-spacing: .3px; text-transform: uppercase;
        }
        .proof-badge.is-accepted { background: #d1fae5; color: #065f46; }
        .proof-badge.is-rejected { background: #fee2e2; color: #991b1b; }
        .proof-badge.is-pending  { background: #fef3c7; color: #92400e; }

        .proof-info-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 14px 24px; }
        .proof-info-label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; margin-bottom: 3px; }
        .proof-info-value { font-size: 14.5px; font-weight: 600; color: #1f2937; }
        .proof-info-value.muted { font-weight: 500; color: #9ca3af; font-style: italic; }
        .proof-reason { grid-column: 1 / -1; font-size: 12.5px; color: #92400e; background: #fffbeb; border-radius: 8px; padding: 8px 12px; margin-top: 2px; }

        .proof-action-panel { margin-top: 16px; padding-top: 16px; border-top: 1px dashed #e5e7eb; }
        .proof-field-row { display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap; }
        .proof-field { display: flex; flex-direction: column; gap: 4px; }
        .proof-field label { font-size: 11px; font-weight: 600; color: #6b7280; margin: 0; }
        .proof-field .form-control, .proof-field .form-select { font-size: 13px; }
        .proof-confirmed-badge {
            display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600;
            color: #065f46; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 8px 14px;
        }
        .proof-empty { text-align: center; padding: 48px 20px; color: #9ca3af; }
        .proof-empty .material-icons-outlined { font-size: 40px; opacity: .4; display: block; margin-bottom: 8px; }
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

                    <!-- Page Header -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble"><tr>
                                        <td>Purchase Order</td>
                                    </tr></table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="row">
                        <div class="col-12">
                            <div class="filter-card">
                                <form method="GET" action="">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label">From Date</label>
                                            <input type="date" name="from_date" class="form-control" value="<?=htmlspecialchars($from_date)?>" max="<?=date('Y-m-d')?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">To Date</label>
                                            <input type="date" name="to_date" class="form-control" value="<?=htmlspecialchars($to_date)?>" max="<?=date('Y-m-d')?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Status</label>
                                            <select name="status_filter" class="form-control">
                                                <option value="active" <?=$statusFilter === 'active' ? 'selected' : ''?>>All (except cancelled)</option>
                                                <option value="waiting" <?=$statusFilter === 'waiting' ? 'selected' : ''?>>Waiting</option>
                                                <option value="completed" <?=$statusFilter === 'completed' ? 'selected' : ''?>>Completed</option>
                                                <option value="cancelled" <?=$statusFilter === 'cancelled' ? 'selected' : ''?>>Cancelled</option>
                                                <option value="all" <?=$statusFilter === 'all' ? 'selected' : ''?>>All (including cancelled)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 d-flex gap-2">
                                            <button type="submit" class="btn btn-light font-weight-bold">
                                                <i class="material-icons" style="vertical-align:middle;font-size:17px;">filter_list</i> Filter
                                            </button>
                                            <a href="tp-today-orders" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.5);">
                                                <i class="material-icons" style="vertical-align:middle;font-size:17px;">refresh</i> Reset
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="row">
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card purple">
                                <div>
                                    <h3><?php echo $totalOrders; ?></h3>
                                    <p><?=$filterSubmitted ? 'Orders in Range' : 'Orders Waiting'?></p>
                                </div>
                                <i class="material-icons-outlined stat-icon">receipt_long</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card orange">
                                <div>
                                    <h3 style="font-size:20px;">₹<?php echo number_format($totalAmount, 2); ?></h3>
                                    <p>Total Amount</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">currency_rupee</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card blue hoverable">
                                <div>
                                    <h3><?php echo $totalProductQty; ?> <span class="stat-sub">(<?php echo $totalProductCount; ?> items)</span></h3>
                                    <p>Total Products</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">inventory_2</i>
                                <div class="stat-tooltip">
                                    <?php if (empty($productTotals)): ?>
                                    <div class="stat-tooltip-row">No products in this view.</div>
                                    <?php else: foreach ($productTotals as $pname => $pqty): ?>
                                    <div class="stat-tooltip-row"><span><?=htmlspecialchars($pname)?></span><strong><?=$pqty?></strong></div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card green">
                                <div>
                                    <h3><?php echo $completedCount; ?></h3>
                                    <p>Completed</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">check_circle</i>
                            </div>
                        </div>
                    </div>

                    <!-- Table Card -->
                    <div class="card">
                        <div class="card-header">
                            <span class="card-header-title">
                                <i class="material-icons-outlined">list_alt</i>
                                Territory Partner Orders
                            </span>
                        </div>
                        <div class="card-body">
                            <div style="overflow-x:auto;">
                                <table id="datatable1" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>TP ID</th>
                                            <th>TP Name</th>
                                            <th>Invoice</th>
                                            <th>Products</th>
                                            <th>Total</th>
                                            <th>Payment Proof</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 0; foreach ($orders as $oid => $o):
                                            $items_json = htmlspecialchars(json_encode($o['lines'], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                                        ?>
                                        <tr>
                                            <td style="color:#9ca3af;font-size:13px;"><?php echo ++$i; ?></td>
                                            <td><code style="font-size:12px;background:#f3f4f6;padding:2px 7px;border-radius:4px;"><?=htmlspecialchars($o['tp_code'])?></code></td>
                                            <td><span style="font-weight:600;font-size:13.5px;color:#1f2937;"><?=htmlspecialchars($o['tp_name'])?></span></td>
                                            <td>
                                                <?php if ($o['status'] === 'completed'): ?>
                                                    <?php if ($o['tp_invoice_id'] && isset($invNumbers[$o['tp_invoice_id']])): ?>
                                                    <a href="tp-invoice-print.php?id=<?=urlencode(base64_encode($o['tp_invoice_id']))?>" target="_blank" title="View Invoice">
                                                        <code style="font-size:12px;background:#f3f4f6;padding:2px 7px;border-radius:4px;color:#667eea;"><?=htmlspecialchars($invNumbers[$o['tp_invoice_id']])?></code>
                                                    </a>
                                                    <?php else: ?>
                                                    Completed
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                <a href="add-tp-invoice.php?po_id=<?=urlencode($oid)?>" class="btn-invoice">
                                                    <i class="material-icons" style="font-size:15px;">receipt_long</i> Invoice
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="items-view-trigger"
                                                        data-partner="<?php echo htmlspecialchars($o['tp_name'], ENT_QUOTES); ?>"
                                                        data-items="<?php echo $items_json; ?>">
                                                    <?php echo count($o['lines']); ?> item<?php echo count($o['lines']) !== 1 ? 's' : ''; ?>
                                                </button>
                                            </td>
                                            <td><span style="font-weight:700;color:#10b981;font-size:13.5px;">₹<?=number_format($o['total'], 2)?></span></td>
                                            <td style="min-width:190px;">
                                                <?php
                                                $tpBal = $tpBalances[$o['tp_db_id']] ?? 0.0;
                                                $coveredByBalance = min($o['total'], max(0, $o['total'] - $o['excess_amount']));
                                                $pendingSubCount = $tpPendingSubmissions[$o['tp_db_id']] ?? 0;
                                                ?>
                                                <div style="font-size:11px;color:#6b7280;line-height:1.6;margin-bottom:4px;">
                                                    From balance: <b style="color:#1f2937;">₹<?=number_format($coveredByBalance, 2)?></b>
                                                    <span title="This TP's current available advance balance (all orders/purposes, not reserved for this one specifically).">
                                                        <i class="material-icons-outlined" style="font-size:12px;vertical-align:-2px;color:#9ca3af;">info</i>
                                                    </span>
                                                    <br>Balance now: ₹<?=number_format($tpBal, 2)?>
                                                </div>
                                                <?php if ($o['excess_amount'] <= 0): ?>
                                                <span style="background:#d1fae5;color:#065f46;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;">Fully within balance</span>
                                                <?php else: ?>
                                                <div style="font-size:11px;color:#b45309;font-weight:600;margin-bottom:6px;">
                                                    Excess needing proof: ₹<?=number_format($o['excess_amount'], 2)?>
                                                </div>
                                                <?php if (!empty($o['screenshots'])):
                                                    $pendingCount = count(array_filter($o['screenshots'], fn($s) => $s['status'] === 'pending_review'));
                                                    $proof_json = htmlspecialchars(json_encode($o['screenshots'], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                                                ?>
                                                <button type="button" class="proof-view-trigger"
                                                        style="border:none;cursor:pointer;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;white-space:nowrap;margin-bottom:4px;<?= $pendingCount > 0 ? 'background:#fef3c7;color:#92400e;' : 'background:#d1fae5;color:#065f46;' ?>"
                                                        data-partner="<?php echo htmlspecialchars($o['tp_name'], ENT_QUOTES); ?>"
                                                        data-tp-db-id="<?php echo (int)$o['tp_db_id']; ?>"
                                                        data-excess="<?php echo (float)$o['excess_amount']; ?>"
                                                        data-screenshots="<?php echo $proof_json; ?>">
                                                    <?php if ($pendingCount > 0): ?>
                                                    Screenshot: Pending Review (<?=$pendingCount?>)
                                                    <?php else: ?>
                                                    Screenshot: Verified ₹<?=number_format($o['excess_amount'], 2)?>
                                                    <?php endif; ?>
                                                </button>
                                                <br>
                                                <?php else: ?>
                                                <div style="font-size:10.5px;color:#9ca3af;margin-bottom:4px;">No screenshot on file for this order.</div>
                                                <?php endif; ?>
                                                <a href="manage-tp-advance-submissions.php?tp_id=<?php echo (int)$o['tp_db_id']; ?>&from=po"
                                                   style="display:inline-block;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;text-decoration:none;<?= $pendingSubCount > 0 ? 'background:#dbeafe;color:#1e40af;' : 'background:#f3f4f6;color:#6b7280;' ?>">
                                                    <?php if ($pendingSubCount > 0): ?>
                                                    <?=$pendingSubCount?> Advance Submission<?=$pendingSubCount !== 1 ? 's' : ''?> →
                                                    <?php else: ?>
                                                    View Advance Submissions →
                                                    <?php endif; ?>
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($o['status'] === 'completed'): ?>
                                                <span class="badge-completed">Completed</span>
                                                <?php elseif ($o['status'] === 'cancelled'): ?>
                                                <span class="badge-cancelled" <?=$o['cancel_reason'] ? 'title="' . htmlspecialchars($o['cancel_reason'], ENT_QUOTES) . '"' : ''?>>Cancelled</span>
                                                <?php else: ?>
                                                <span class="badge-waiting">Waiting</span>
                                                <button type="button" class="btn-cancel-order cancel-order-trigger" data-po-id="<?=(int)$oid?>">Cancel</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
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

<!-- Order Items Modal -->
<div class="modal fade" id="itemsViewModal" tabindex="-1" aria-labelledby="itemsViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-md">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                <h6 class="modal-title" id="itemsViewModalLabel" style="font-weight:600;color:#1f2937;">
                    <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#667eea;">inventory_2</i>
                    Order Items
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="itemsViewModalBody" style="padding:16px 20px;">
            </div>
            <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Proof Modal -->
<div class="modal fade" id="proofViewModal" tabindex="-1" aria-labelledby="proofViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden;">
            <div class="modal-header" style="border-bottom:1px solid #e9ecef;">
                <div>
                    <h6 class="modal-title mb-0" id="proofViewModalLabel" style="font-weight:700;color:#1f2937;">
                        <i class="material-icons-outlined" style="font-size:19px;vertical-align:middle;margin-right:6px;color:#667eea;">receipt_long</i>
                        Payment Proof
                    </h6>
                    <div id="proofViewModalSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="proofViewModalBody">
            </div>
            <div class="modal-footer" style="border-top:1px solid #e9ecef;">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
<script>
var CSRF_TOKEN = <?=json_encode($_SESSION['csrf_token'])?>;
var COMPANY_PROFILES = <?=json_encode($companyProfiles, JSON_UNESCAPED_UNICODE)?>;

$(document).on('click', '.cancel-order-trigger', function () {
    var $btn = $(this);
    var poId = $btn.data('po-id');
    if (!confirm('Cancel this purchase order? This cannot be undone.')) return;
    var reason = prompt('Reason for cancellation (optional):', '') || '';

    $btn.prop('disabled', true).text('Cancelling…');

    $.post('cancel-tp-purchase-order.php', { csrf_token: CSRF_TOKEN, po_id: poId, reason: reason })
        .done(function (data) {
            if (!data.success) {
                alert(data.message || 'Could not cancel this order.');
                $btn.prop('disabled', false).text('Cancel');
                return;
            }
            location.reload();
        })
        .fail(function () {
            alert('Could not reach the server. Please try again.');
            $btn.prop('disabled', false).text('Cancel');
        });
});

$(document).on('click', '.items-view-trigger', function () {
    var partner = $(this).data('partner');
    var items   = $(this).data('items');
    $('#itemsViewModalLabel').html(
        '<i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#667eea;">inventory_2</i>' +
        $('<span>').text(partner).html()
    );
    var html = '';
    var grandTotal = 0;
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
    $('#itemsViewModalBody').html(html || '<div style="color:#9ca3af;">No items.</div>');
    $('#itemsViewModal').modal('show');
});

var currentProofButton = null;
var currentProofScreenshots = null;

function renderProofModal() {
    if (!currentProofScreenshots.length) {
        $('#proofViewModalBody').html(
            '<div class="proof-empty">' +
            '<i class="material-icons-outlined">image_not_supported</i>' +
            'No screenshots have been uploaded for this order.' +
            '</div>'
        );
        return;
    }

    var html = '';
    currentProofScreenshots.forEach(function (s) {
        var badgeClass = s.status === 'accepted' ? 'is-accepted' : (s.status === 'rejected' ? 'is-rejected' : 'is-pending');
        var badgeText = s.status === 'accepted' ? 'Accepted' : (s.status === 'rejected' ? 'Rejected' : 'Pending Review');

        var isHeic = /\.hei[cf]$/i.test(s.file_path);
        var fileUrl = '../territory-partner/po_screenshots/' + encodeURIComponent(s.file_path);
        var mediaHtml = isHeic
            ? '<div class="proof-media d-flex align-items-center justify-content-center" style="height:150px;background:#f3f4f6;border-radius:10px;">' +
              '<a href="' + fileUrl + '" target="_blank" class="btn btn-sm btn-outline-secondary">Download HEIC</a></div>'
            : '<div class="proof-media"><a href="' + fileUrl + '" target="_blank"><img src="' + fileUrl + '" alt="Payment screenshot"></a></div>';

        var amountValue = s.detected_amount !== null
            ? '<span class="proof-info-value">₹' + parseFloat(s.detected_amount).toFixed(2) + '</span>'
            : '<span class="proof-info-value muted">Not detected</span>';
        var refValue = s.reference_number
            ? '<span class="proof-info-value">' + $('<div>').text(s.reference_number).html() + '</span>'
            : '<span class="proof-info-value muted">Not detected</span>';

        var infoGrid = '<div class="proof-info-grid">' +
            '<div><div class="proof-info-label">Amount</div>' + amountValue + '</div>' +
            '<div><div class="proof-info-label">Reference Number</div>' + refValue + '</div>' +
            (s.rejection_reason ? '<div class="proof-reason">' + $('<div>').text(s.rejection_reason).html() + '</div>' : '') +
            '</div>';

        var actionsHtml = '';
        if (s.status === 'pending_review') {
            actionsHtml = '<div class="proof-action-panel" data-screenshot-id="' + s.id + '">' +
                '<div class="proof-field-row">' +
                    '<div class="proof-field"><label>Confirmed Amount</label>' +
                    '<input type="number" step="0.01" min="0" class="form-control proof-amount-input" style="width:120px;" value="' + (s.detected_amount !== null ? s.detected_amount : '') + '"></div>' +
                    '<div class="proof-field"><label>Confirmed Reference</label>' +
                    '<input type="text" class="form-control proof-ref-input" style="width:180px;" value="' + (s.reference_number ? $('<div>').text(s.reference_number).html() : '') + '"></div>' +
                    '<button type="button" class="btn btn-success proof-approve-btn">Approve</button>' +
                    '<button type="button" class="btn btn-outline-danger proof-reject-btn">Reject</button>' +
                '</div>' +
            '</div>';
        } else if (s.status === 'accepted') {
            if (s.advance_payment_id) {
                actionsHtml = '<div class="proof-action-panel">' +
                    '<span class="proof-confirmed-badge"><i class="material-icons-outlined" style="font-size:16px;">check_circle</i>Added as Advance Payment #' + s.advance_payment_id + '</span>' +
                    '</div>';
            } else {
                var companyOptions = '<option value="">Select company profile…</option>';
                COMPANY_PROFILES.forEach(function (cp) {
                    companyOptions += '<option value="' + cp.id + '">' + $('<div>').text(cp.gname).html() + '</option>';
                });
                var today = new Date().toISOString().slice(0, 10);
                actionsHtml = '<div class="proof-action-panel" data-screenshot-id="' + s.id + '">' +
                    '<button type="button" class="btn btn-outline-primary add-advance-toggle-btn">' +
                    '<i class="material-icons-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px;">add_card</i>Add to TP Payment Entry</button>' +
                    '<div class="advance-form proof-field-row mt-3" style="display:none;">' +
                        '<div class="proof-field"><label>Company Profile</label>' +
                        '<select class="form-select advance-company-select" style="width:180px;">' + companyOptions + '</select></div>' +
                        '<div class="proof-field"><label>Amount</label>' +
                        '<input type="number" step="0.01" min="0" class="form-control advance-amount-input" style="width:110px;" value="' + (s.detected_amount !== null ? s.detected_amount : '') + '"></div>' +
                        '<div class="proof-field"><label>Reference</label>' +
                        '<input type="text" class="form-control advance-ref-input" style="width:170px;" value="' + (s.reference_number ? $('<div>').text(s.reference_number).html() : '') + '"></div>' +
                        '<div class="proof-field"><label>Payment Mode</label>' +
                        '<select class="form-select advance-mode-select" style="width:120px;">' +
                            ['UPI', 'NEFT', 'RTGS', 'IMPS', 'Bank Transfer', 'Cash', 'Cheque', 'Demand Draft', 'Other'].map(function (m) {
                                return '<option value="' + m + '">' + m + '</option>';
                            }).join('') +
                        '</select></div>' +
                        '<div class="proof-field"><label>Date</label>' +
                        '<input type="date" class="form-control advance-date-input" style="width:140px;" max="' + today + '" value="' + today + '"></div>' +
                        '<button type="button" class="btn btn-primary advance-save-btn">Save</button>' +
                    '</div>' +
                '</div>';
            }
        }

        html += '<div class="proof-card">' +
                '<div class="proof-card-top"><span class="proof-badge ' + badgeClass + '">' + badgeText + '</span></div>' +
                '<div class="proof-card-body">' + mediaHtml +
                '<div class="proof-main">' + infoGrid + actionsHtml + '</div>' +
                '</div>' +
                '</div>';
    });
    $('#proofViewModalBody').html(html);
}

function updateProofButton() {
    if (!currentProofButton) return;
    var pendingCount = currentProofScreenshots.filter(function (s) { return s.status === 'pending_review'; }).length;
    var excess = parseFloat(currentProofButton.data('excess')) || 0;
    currentProofButton.attr('data-screenshots', JSON.stringify(currentProofScreenshots));
    if (pendingCount > 0) {
        currentProofButton.css({ background: '#fef3c7', color: '#92400e' }).text('Pending Review (' + pendingCount + ')');
    } else {
        currentProofButton.css({ background: '#d1fae5', color: '#065f46' }).text('Verified ₹' + excess.toFixed(2));
    }
}

function updateProofSubtitle() {
    if (!currentProofButton) return;
    var excess = parseFloat(currentProofButton.data('excess')) || 0;
    var acceptedTotal = currentProofScreenshots
        .filter(function (s) { return s.status === 'accepted'; })
        .reduce(function (sum, s) { return sum + (parseFloat(s.detected_amount) || 0); }, 0);
    var count = currentProofScreenshots.length;
    $('#proofViewModalSubtitle').text(
        count + ' screenshot' + (count !== 1 ? 's' : '') + ' · Excess required ₹' + excess.toFixed(2) +
        ' · Verified so far ₹' + acceptedTotal.toFixed(2)
    );
}

$(document).on('click', '.proof-view-trigger', function () {
    currentProofButton = $(this);
    currentProofScreenshots = currentProofButton.data('screenshots') || [];

    $('#proofViewModalLabel').html(
        '<i class="material-icons-outlined" style="font-size:19px;vertical-align:middle;margin-right:6px;color:#667eea;">receipt_long</i>' +
        $('<span>').text(currentProofButton.data('partner')).html()
    );
    updateProofSubtitle();
    renderProofModal();
    $('#proofViewModal').modal('show');
});

$(document).on('click', '.proof-approve-btn, .proof-reject-btn', function () {
    var $btn = $(this);
    var $row = $btn.closest('[data-screenshot-id]');
    var screenshotId = parseInt($row.data('screenshot-id'), 10);
    var isApprove = $btn.hasClass('proof-approve-btn');
    var payload = {
        screenshot_id: screenshotId,
        action: isApprove ? 'approve' : 'reject'
    };
    if (isApprove) {
        payload.confirmed_amount = $row.find('.proof-amount-input').val();
        payload.confirmed_reference = $row.find('.proof-ref-input').val();
    }
    $row.find('button').prop('disabled', true);

    $.post('tp-po-screenshot-review-action-ajax.php', payload)
        .done(function (data) {
            if (!data.success) {
                alert(data.message || 'Action failed.');
                $row.find('button').prop('disabled', false);
                return;
            }
            var screenshot = currentProofScreenshots.find(function (s) { return parseInt(s.id, 10) === screenshotId; });
            if (screenshot) {
                screenshot.status = isApprove ? 'accepted' : 'rejected';
                if (isApprove) {
                    screenshot.detected_amount = parseFloat(payload.confirmed_amount);
                    screenshot.reference_number = payload.confirmed_reference;
                    screenshot.rejection_reason = null;
                }
            }
            renderProofModal();
            updateProofButton();
            updateProofSubtitle();
        })
        .fail(function () {
            alert('Could not reach the server. Please try again.');
            $row.find('button').prop('disabled', false);
        });
});

$(document).on('click', '.add-advance-toggle-btn', function () {
    $(this).hide().siblings('.advance-form').show();
});

$(document).on('click', '.advance-save-btn', function () {
    var $btn = $(this);
    var $row = $btn.closest('[data-screenshot-id]');
    var screenshotId = parseInt($row.data('screenshot-id'), 10);
    var companyId = $row.find('.advance-company-select').val();

    if (!companyId) { alert('Please select a company profile.'); return; }

    var payload = {
        csrf_token: CSRF_TOKEN,
        screenshot_id: screenshotId,
        company_id: companyId,
        amount: $row.find('.advance-amount-input').val(),
        reference_number: $row.find('.advance-ref-input').val(),
        payment_mode: $row.find('.advance-mode-select').val(),
        payment_date: $row.find('.advance-date-input').val()
    };
    $row.find('button, select, input').prop('disabled', true);

    $.post('tp-screenshot-to-advance-payment.php', payload)
        .done(function (data) {
            if (!data.success) {
                alert(data.message || 'Could not add this as a payment entry.');
                $row.find('button, select, input').prop('disabled', false);
                return;
            }
            var screenshot = currentProofScreenshots.find(function (s) { return parseInt(s.id, 10) === screenshotId; });
            if (screenshot) screenshot.advance_payment_id = data.advance_payment_id;
            if (currentProofButton) currentProofButton.attr('data-screenshots', JSON.stringify(currentProofScreenshots));
            renderProofModal();

            // Land the reviewer directly on the new advance payment entry
            // instead of leaving them to search the full list for it.
            var tpDbId = currentProofButton ? currentProofButton.data('tp-db-id') : '';
            alert('Added to TP advance payments. Taking you to the entry now…');
            window.location.href = 'manage-tp-advance-payments.php?highlight=' + encodeURIComponent(data.advance_payment_id)
                + (tpDbId ? '&tp_id=' + encodeURIComponent(tpDbId) : '');
        })
        .fail(function () {
            alert('Could not reach the server. Please try again.');
            $row.find('button, select, input').prop('disabled', false);
        });
});
</script>
</body>
</html>
