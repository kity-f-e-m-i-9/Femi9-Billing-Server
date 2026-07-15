<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today = date("Y-m-d");

$from_date = $_GET['from_date'] ?? $today;
$to_date   = $_GET['to_date']   ?? $today;
if (strtotime($from_date) > strtotime($to_date)) { [$from_date, $to_date] = [$to_date, $from_date]; }

$stmt = $db_conn->prepare(
    "SELECT o.id, o.order_date, o.status, o.tp_invoice_id,
            tp.id AS tp_db_id, tp.name AS tp_name, tp.tp_id AS tp_code,
            i.product_id, i.qty, i.amount, p.productName
     FROM tp_purchase_orders o
     JOIN territory_partners tp ON tp.id = o.territory_partner_id
     LEFT JOIN tp_purchase_order_items i ON i.po_id = o.id
     LEFT JOIN products p ON p.id = i.product_id
     WHERE o.order_date BETWEEN ? AND ?
     ORDER BY o.id DESC, i.id ASC"
);
$stmt->bind_param("ss", $from_date, $to_date);
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
            'lines'         => [],
            'total'         => 0,
        ];
    }
    if ($r['product_id']) {
        $orders[$oid]['lines'][] = ['pid' => $r['product_id'], 'product' => $r['productName'], 'qty' => $r['qty']];
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
                                    <p>Orders Today</p>
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
                                    <div class="stat-tooltip-row">No products ordered today.</div>
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
                                    <p>Completed Today</p>
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
                                            <td>
                                                <?php if ($o['status'] === 'completed'): ?>
                                                <span class="badge-completed">Completed</span>
                                                <?php else: ?>
                                                <span class="badge-waiting">Waiting</span>
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
$(document).on('click', '.items-view-trigger', function () {
    var partner = $(this).data('partner');
    var items   = $(this).data('items');
    $('#itemsViewModalLabel').html(
        '<i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;margin-right:5px;color:#667eea;">inventory_2</i>' +
        $('<span>').text(partner).html()
    );
    var html = '';
    $.each(items, function (_, item) {
        html += '<div style="padding:7px 0;font-size:13.5px;color:#1f2937;border-bottom:1px dotted #f3f4f6;display:flex;justify-content:space-between;align-items:center;">' +
                '<span>' + $('<div>').text(item.product || '-').html() + '</span>' +
                '<strong>' + $('<div>').text(item.qty).html() + '</strong>' +
                '</div>';
    });
    $('#itemsViewModalBody').html(html || '<div style="color:#9ca3af;">No items.</div>');
    $('#itemsViewModal').modal('show');
});
</script>
</body>
</html>
