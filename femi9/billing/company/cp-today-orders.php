<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
require_once("include/GodownAccess.php");
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/CpPurchaseOrderBalance.php';
error_reporting(0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

date_default_timezone_set("Asia/Kolkata");
$today = date("Y-m-d");

// The date range only applies once the Filter button is actually submitted
// (any of these three fields present in the query string). A fresh,
// unfiltered page load instead shows every still-waiting order regardless
// of date — that's the actionable "things to process" queue.
$filterSubmitted = isset($_GET['from_date']) || isset($_GET['to_date']) || isset($_GET['status_filter']);
if ($filterSubmitted) {
    $from_date = $_GET['from_date'] ?? $today;
    $to_date   = $_GET['to_date']   ?? $today;
    if (strtotime($from_date) > strtotime($to_date)) { [$from_date, $to_date] = [$to_date, $from_date]; }
    $statusFilter = $_GET['status_filter'] ?? 'active';
    $allowedStatusFilters = ['active', 'waiting', 'completed', 'cancelled', 'all'];
    if (!in_array($statusFilter, $allowedStatusFilters, true)) $statusFilter = 'active';
} else {
    $from_date = ''; $to_date = ''; $statusFilter = 'waiting';
}

// Napkin/Diaper filter — independent of status/date, applies either way.
$typeFilter = $_GET['type_filter'] ?? '';
if (!in_array($typeFilter, ['napkin', 'diaper'], true)) $typeFilter = '';

cpEnsurePurchaseOrderTables($db_conn);

$whereSql = "WHERE 1=1";
$bindTypes = ''; $bindValues = [];
if ($filterSubmitted) {
    $whereSql .= ' AND o.order_date BETWEEN ? AND ?';
    $bindTypes .= 'ss'; $bindValues[] = $from_date; $bindValues[] = $to_date;
}
if ($statusFilter === 'active') {
    $whereSql .= " AND o.status != 'cancelled'";
} elseif (in_array($statusFilter, ['waiting', 'completed', 'cancelled'], true)) {
    $whereSql .= ' AND o.status = ?'; $bindTypes .= 's'; $bindValues[] = $statusFilter;
}
if ($typeFilter !== '') {
    $whereSql .= ' AND o.product_type = ?'; $bindTypes .= 's'; $bindValues[] = $typeFilter;
}

$stmt = $db_conn->prepare(
    "SELECT o.id, o.order_date, o.status, o.transfer_id, o.cp_invoice_id, o.cancel_reason, o.product_type, o.channel_partner_id,
            cp.name AS cp_name, cp.cp_id AS cp_code,
            i.product_id, i.qty, i.price, i.amount, p.productName
     FROM channel_partner_purchase_orders o
     JOIN channel_partners cp ON cp.id = o.channel_partner_id
     LEFT JOIN channel_partner_purchase_order_items i ON i.po_id = o.id
     LEFT JOIN products p ON p.id = i.product_id
     $whereSql
     ORDER BY o.order_date DESC, o.id DESC, i.id ASC"
);
if ($bindTypes !== '') $stmt->bind_param($bindTypes, ...$bindValues);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$orders = [];
foreach ($rows as $r) {
    $key = (int)$r['id'];
    if (!isset($orders[$key])) {
        $orders[$key] = [
            'po_id'              => (int)$r['id'],
            'display_date'       => $r['order_date'],
            'status'             => $r['status'],
            'transfer_id'        => $r['transfer_id'],
            'cp_invoice_id'      => $r['cp_invoice_id'],
            'cancel_reason'      => $r['cancel_reason'],
            'product_type'       => $r['product_type'] ?? 'napkin',
            'cp_id'              => (int)$r['channel_partner_id'],
            'cp_name'            => $r['cp_name'],
            'cp_code'            => $r['cp_code'],
            'lines'              => [],
            'total'              => 0,
            'headroom'           => null, // computed lazily below, only for waiting orders
        ];
    }
    if ($r['product_id']) {
        $orders[$key]['lines'][] = ['product' => $r['productName'], 'qty' => (int)$r['qty'], 'price' => (float)$r['price'], 'amount' => (float)$r['amount']];
        $orders[$key]['total'] += (float)$r['amount'];
    }
}

// Current headroom per CP — only needed for orders still awaiting a
// decision, and computed once per distinct CP rather than once per order.
$headroomByCp = [];
foreach ($orders as $key => $o) {
    if ($o['status'] !== 'waiting') continue;
    if (!isset($headroomByCp[$o['cp_id']])) {
        $headroomByCp[$o['cp_id']] = cpAvailableHeadroom($db_conn, $o['cp_id'], $o['po_id']);
    }
    $orders[$key]['headroom'] = $headroomByCp[$o['cp_id']];
}

$godowns = $db_conn->query("SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY gname")->fetch_all(MYSQLI_ASSOC);
$wh_result = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
$warehouses_list = $wh_result ? $wh_result->fetch_all(MYSQLI_ASSOC) : [];

uasort($orders, fn($a, $b) => strtotime($b['display_date']) <=> strtotime($a['display_date']) ?: $b['po_id'] <=> $a['po_id']);
$waitingCount   = count(array_filter($orders, fn($o) => $o['status'] === 'waiting'));
$completedCount = count(array_filter($orders, fn($o) => $o['status'] === 'completed'));
$cancelledCount = count(array_filter($orders, fn($o) => $o['status'] === 'cancelled'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CP Purchase Orders : <?php echo $business_name; ?></title>
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
        }
        .stat-card.orange { border-color: #f59e0b; }
        .stat-card.green  { border-color: #10b981; }
        .stat-card.red    { border-color: #ef4444; }
        .stat-card h3 { font-size: 26px; font-weight: 700; margin: 0 0 2px 0; color: #1f2937; }
        .stat-card p  { margin: 0; font-size: 11.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
        .stat-icon { font-size: 36px; opacity: .12; }
        .stat-card.orange .stat-icon { color: #f59e0b; }
        .stat-card.green  .stat-icon { color: #10b981; }
        .stat-card.red    .stat-icon { color: #ef4444; }

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

        .items-view-trigger {
            border: none; cursor: pointer; background: #667eea; color: #fff;
            font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; white-space: nowrap;
        }
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
                                        <td>CP Purchase Orders</td>
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
                                        <div class="col-md-2">
                                            <label class="form-label">From Date</label>
                                            <input type="date" name="from_date" class="form-control" value="<?=htmlspecialchars($from_date)?>" max="<?=date('Y-m-d')?>">
                                        </div>
                                        <div class="col-md-2">
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
                                        <div class="col-md-2">
                                            <label class="form-label">Type</label>
                                            <select name="type_filter" class="form-control">
                                                <option value="" <?=$typeFilter === '' ? 'selected' : ''?>>Napkin + Diaper</option>
                                                <option value="napkin" <?=$typeFilter === 'napkin' ? 'selected' : ''?>>Napkin only</option>
                                                <option value="diaper" <?=$typeFilter === 'diaper' ? 'selected' : ''?>>Lumi Diaper only</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 d-flex gap-2">
                                            <button type="submit" class="btn btn-light font-weight-bold">
                                                <i class="material-icons" style="vertical-align:middle;font-size:17px;">filter_list</i> Filter
                                            </button>
                                            <a href="cp-today-orders" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.5);">
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
                        <div class="col-lg-4 col-sm-6">
                            <div class="stat-card orange">
                                <div>
                                    <h3><?php echo $waitingCount; ?></h3>
                                    <p>Waiting</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">hourglass_top</i>
                            </div>
                        </div>
                        <div class="col-lg-4 col-sm-6">
                            <div class="stat-card green">
                                <div>
                                    <h3><?php echo $completedCount; ?></h3>
                                    <p>Completed</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">check_circle</i>
                            </div>
                        </div>
                        <div class="col-lg-4 col-sm-6">
                            <div class="stat-card red">
                                <div>
                                    <h3><?php echo $cancelledCount; ?></h3>
                                    <p>Cancelled</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">cancel</i>
                            </div>
                        </div>
                    </div>

                    <!-- Table Card -->
                    <div class="card">
                        <div class="card-header">
                            <span class="card-header-title">
                                <i class="material-icons-outlined">list_alt</i>
                                Channel Partner Orders
                            </span>
                        </div>
                        <div class="card-body">
                            <div style="overflow-x:auto;">
                                <table id="datatable1" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>CP</th>
                                            <th>Type</th>
                                            <th>Products</th>
                                            <th>Total</th>
                                            <th>Headroom</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 0; foreach ($orders as $o):
                                            $items_json = htmlspecialchars(json_encode($o['lines'], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                                            $poType = tpResolveProductType($o['product_type'] ?? null);
                                            [$tBg, $tFg] = tpProductTypeBadgeColors($poType);
                                        ?>
                                        <tr>
                                            <td style="color:#9ca3af;font-size:13px;"><?php echo ++$i; ?></td>
                                            <td style="white-space:nowrap;font-size:12.5px;color:#4b5563;"><?=date('d M Y', strtotime($o['display_date']))?></td>
                                            <td>
                                                <code style="font-size:12px;background:#f3f4f6;padding:2px 7px;border-radius:4px;"><?=htmlspecialchars($o['cp_code'])?></code><br>
                                                <span style="font-weight:600;font-size:13.5px;color:#1f2937;"><?=htmlspecialchars($o['cp_name'])?></span>
                                            </td>
                                            <td>
                                                <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:9px;background:<?=$tBg?>;color:<?=$tFg?>;"><?=htmlspecialchars(tpProductTypeLabel($poType))?></span>
                                            </td>
                                            <td>
                                                <button type="button" class="items-view-trigger"
                                                        data-partner="<?php echo htmlspecialchars($o['cp_name'], ENT_QUOTES); ?>"
                                                        data-items="<?php echo $items_json; ?>">
                                                    <?php echo count($o['lines']); ?> item<?php echo count($o['lines']) !== 1 ? 's' : ''; ?>
                                                </button>
                                            </td>
                                            <td><span style="font-weight:700;color:#10b981;font-size:13.5px;">₹<?=number_format($o['total'], 2)?></span></td>
                                            <td>
                                                <?php if ($o['status'] === 'waiting'): ?>
                                                <span style="font-weight:700;font-size:13px;color:<?= $o['headroom'] < $o['total'] ? '#ef4444' : '#10b981' ?>;">₹<?=number_format($o['headroom'], 2)?></span>
                                                <?php else: ?>
                                                <span style="color:#9ca3af;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($o['status'] === 'completed'): ?>
                                                <span class="badge-completed">Completed</span>
                                                <?php if (!empty($o['cp_invoice_id'])): ?>
                                                <br><a href="cp-invoice-print.php?id=<?=base64_encode((string)$o['cp_invoice_id'])?>" target="_blank" style="font-size:11px;">View Invoice</a>
                                                <?php endif; ?>
                                                <?php elseif ($o['status'] === 'cancelled'): ?>
                                                <span class="badge-cancelled" <?=$o['cancel_reason'] ? 'title="' . htmlspecialchars($o['cancel_reason'], ENT_QUOTES) . '"' : ''?>>Cancelled</span>
                                                <?php else: ?>
                                                <span class="badge-waiting">Waiting</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($o['status'] === 'waiting'): ?>
                                                <div style="display:flex;flex-direction:column;gap:6px;">
                                                    <form method="post" action="cp-po-action.php" style="display:inline-flex;gap:6px;align-items:center;">
                                                        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                                                        <input type="hidden" name="po_id" value="<?=(int)$o['po_id']?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <select name="godown_id" class="form-control form-control-sm" required style="width:140px;">
                                                            <option value="">Company Profile…</option>
                                                            <?php foreach ($godowns as $g): ?>
                                                            <option value="<?=$g['id']?>"><?=htmlspecialchars($g['gname'])?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <select name="warehouse_id" class="form-control form-control-sm" required style="width:110px;">
                                                            <option value="">Warehouse…</option>
                                                            <?php foreach ($warehouses_list as $wh): ?>
                                                            <option value="<?=(int)$wh['id']?>"><?=htmlspecialchars($wh['code'])?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this order and transfer stock now?');">Approve</button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="rejectCpPo(<?=(int)$o['po_id']?>)">Reject</button>
                                                </div>
                                                <?php else: ?>
                                                <span style="color:#9ca3af;">—</span>
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

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
<script>
function rejectCpPo(poId) {
    var reason = prompt('Reason for rejecting this order:');
    if (reason === null) return;
    var f = document.createElement('form');
    f.method = 'post'; f.action = 'cp-po-action.php';
    f.innerHTML = '<input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">' +
        '<input type="hidden" name="po_id" value="' + poId + '">' +
        '<input type="hidden" name="action" value="reject">' +
        '<input type="hidden" name="reason" value="' + reason.replace(/"/g, '&quot;') + '">';
    document.body.appendChild(f);
    f.submit();
}

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
</script>
</body>
</html>
