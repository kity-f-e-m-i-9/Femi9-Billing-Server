<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
error_reporting(0);

$tpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID);
$hasTps = !empty($tpIds);
$tpIdList = $hasTps ? implode(',', array_map('intval', $tpIds)) : '0';

// ── Filters ──────────────────────────────────────────────────────────────
$filter_tp_id     = (int)($_GET['tp_id'] ?? 0);
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to']   ?? '');
$filter_status    = $_GET['status'] ?? '';
if ($filter_tp_id > 0 && !in_array($filter_tp_id, $tpIds, true)) { $filter_tp_id = 0; }
if (!in_array($filter_status, ['', 'waiting', 'completed', 'cancelled'], true)) { $filter_status = ''; }

$tps = [];
$orders = [];
$grand_total = 0;
$filters_active = $filter_tp_id || $filter_date_from || $filter_date_to || $filter_status;
$po_waiting = 0; $po_completed = 0; $po_cancelled = 0;

if ($hasTps) {
    // TPs with at least one PO request (for the filter dropdown)
    $stmtTp = $db_conn->query("
        SELECT DISTINCT tp.id, tp.name, tp.tp_id AS tp_code
        FROM tp_purchase_orders po
        JOIN territory_partners tp ON tp.id = po.territory_partner_id
        WHERE po.territory_partner_id IN ($tpIdList)
        ORDER BY tp.name
    ");
    $tps = $stmtTp ? $stmtTp->fetch_all(MYSQLI_ASSOC) : [];

    // Overall status counts — unaffected by the filters below, so the 3 stat
    // cards always reflect the true totals regardless of what's filtered.
    $poStatusRows = $db_conn->query("
        SELECT status, COUNT(*) cnt FROM tp_purchase_orders
        WHERE territory_partner_id IN ($tpIdList)
        GROUP BY status
    ");
    if ($poStatusRows) {
        while ($r = $poStatusRows->fetch_assoc()) {
            if ($r['status'] === 'waiting') $po_waiting = (int)$r['cnt'];
            elseif ($r['status'] === 'completed') $po_completed = (int)$r['cnt'];
            elseif ($r['status'] === 'cancelled') $po_cancelled = (int)$r['cnt'];
        }
    }

    // ── Unified list: one row per purchase order REQUEST (tp_purchase_orders),
    // left-joined to the actual bill (tp_invoices) once it's been converted —
    // View/Print only make sense (and only appear) once status='completed'
    // and a real invoice exists.
    $where  = ["po.territory_partner_id IN ($tpIdList)"];
    $params = [];
    $types  = '';

    if ($filter_tp_id > 0) {
        $where[]  = "po.territory_partner_id = ?";
        $params[] = $filter_tp_id;
        $types   .= 'i';
    }
    if ($filter_date_from !== '') {
        $where[]  = "po.order_date >= ?";
        $params[] = $filter_date_from;
        $types   .= 's';
    }
    if ($filter_date_to !== '') {
        $where[]  = "po.order_date <= ?";
        $params[] = $filter_date_to;
        $types   .= 's';
    }
    if ($filter_status !== '') {
        $where[]  = "po.status = ?";
        $params[] = $filter_status;
        $types   .= 's';
    }
    $where_sql = 'WHERE ' . implode(' AND ', $where);

    $sql = "
        SELECT po.id AS po_id, po.order_date, po.status, po.cancel_reason, po.tp_invoice_id,
               tp.name AS tp_name, tp.tp_id AS tp_code,
               ti.invoice_number, ti.total_amount AS invoice_total,
               (SELECT COUNT(*) FROM tp_invoice_items tii WHERE tii.tp_invoice_id = ti.id) AS invoice_item_count,
               COALESCE(poi.item_count, 0) AS po_item_count,
               COALESCE(poi.item_total, 0) AS po_item_total
        FROM tp_purchase_orders po
        JOIN territory_partners tp ON tp.id = po.territory_partner_id
        LEFT JOIN tp_invoices ti ON ti.id = po.tp_invoice_id
        LEFT JOIN (
            SELECT po_id, COUNT(*) item_count, SUM(amount) item_total
            FROM tp_purchase_order_items GROUP BY po_id
        ) poi ON poi.po_id = po.id
        $where_sql
        ORDER BY po.order_date DESC, po.id DESC
        LIMIT 200
    ";
    if ($types) {
        $stmt = $db_conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $res = $db_conn->query($sql);
        $orders = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    $grand_total = array_sum(array_map(fn($o) => $o['status']==='completed' ? (float)$o['invoice_total'] : (float)$o['po_item_total'], $orders));

    // ── Item breakdown per row, for the "Items" click modal ────────────────
    // Waiting/cancelled rows read their products from tp_purchase_order_items
    // (the TP's own request); completed rows read from tp_invoice_items (the
    // actual billed invoice) instead, matching which figure drives $amount above.
    $itemsByPo = []; $itemsByInvoice = [];
    $poIds = array_map(fn($o) => (int)$o['po_id'], $orders);
    $invIds = array_values(array_filter(array_map(fn($o) => $o['status']==='completed' ? (int)$o['tp_invoice_id'] : 0, $orders)));
    if (!empty($poIds)) {
        $poIdList = implode(',', $poIds);
        $res = $db_conn->query("
            SELECT poi.po_id, p.productName, poi.qty, poi.amount
            FROM tp_purchase_order_items poi JOIN products p ON p.id = poi.product_id
            WHERE poi.po_id IN ($poIdList) ORDER BY poi.po_id, p.productName
        ");
        if ($res) while ($r = $res->fetch_assoc()) { $itemsByPo[(int)$r['po_id']][] = $r; }
    }
    if (!empty($invIds)) {
        $invIdList = implode(',', $invIds);
        $res = $db_conn->query("
            SELECT tii.tp_invoice_id, p.productName, tii.quantity AS qty, tii.amount
            FROM tp_invoice_items tii JOIN products p ON p.id = tii.product_id
            WHERE tii.tp_invoice_id IN ($invIdList) ORDER BY tii.tp_invoice_id, p.productName
        ");
        if ($res) while ($r = $res->fetch_assoc()) { $itemsByInvoice[(int)$r['tp_invoice_id']][] = $r; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Purchase Order : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .filter-card { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-radius:10px; padding:18px 20px; margin-bottom:20px; }
        .filter-card .form-label { color:#fff; font-weight:500; margin-bottom:4px; font-size:12.5px; }
        .filter-card .form-control, .filter-card select.form-control { background:rgba(255,255,255,0.95); border:none; border-radius:6px; font-size:13px; height:36px; padding:4px 10px; }
        .filter-card .btn-filter { background:#fff; color:#667eea; border:none; border-radius:6px; padding:7px 18px; font-size:13px; font-weight:600; height:36px; line-height:1; cursor:pointer; }
        .filter-card .btn-filter:hover { background:#f0f0ff; }
        .filter-card .btn-clear { background:rgba(255,255,255,0.18); color:#fff; border:1px solid rgba(255,255,255,0.4); border-radius:6px; padding:7px 14px; font-size:13px; height:36px; line-height:1; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; }
        .filter-card .btn-clear:hover { background:rgba(255,255,255,0.28); color:#fff; }
        .filter-active-badge { display:inline-block; background:#fbbf24; color:#78350f; border-radius:5px; padding:2px 8px; font-size:11px; font-weight:700; margin-left:8px; vertical-align:middle; }
        #searchBox { max-width:320px; }
        tr.no-match { display:none !important; }
        .stat-card { background:#fff; border-radius:10px; padding:18px 20px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border-left:4px solid; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; }
        .stat-card.warning { border-color:#f59e0b; }
        .stat-card.red     { border-color:#ef4444; }
        .stat-card.green   { border-color:#10b981; }
        .stat-card h3 { font-size:26px; font-weight:700; margin:0 0 2px 0; color:#1f2937; }
        .stat-card p  { margin:0; font-size:11.5px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
        .stat-icon { font-size:32px; opacity:.15; }
        .stat-card.warning .stat-icon { color:#f59e0b; }
        .stat-card.red     .stat-icon { color:#ef4444; }
        .stat-card.green   .stat-icon { color:#10b981; }
        .po-status-pill { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; letter-spacing:.3px; text-transform:uppercase; display:inline-block; }
        .po-status-pill.waiting   { background:#fef3c7; color:#92400e; }
        .po-status-pill.completed { background:#d1fae5; color:#065f46; }
        .po-status-pill.cancelled { background:#fee2e2; color:#991b1b; }
        .items-view-trigger {
            border: none; cursor: pointer; background: #667eea; color: #fff;
            font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; white-space: nowrap;
        }

        @media (max-width: 768px) {
            .stat-card { margin-bottom: 12px; }
            #searchBox { max-width: 100%; }
            .filter-card .col-lg-2, .filter-card .col-lg-3 { margin-bottom: 10px; }
        }
        @media (max-width: 480px) {
            .page-description h1 { font-size: 20px; }
            .stat-card h3 { font-size: 20px; }
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
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1><table class="headertble"><tr><td>TP Purchase Order</td></tr></table></h1>
                            </div>
                        </div>
                    </div>

                    <?php if (!$hasTps): ?>
                        <div class="alert alert-info">No Territory Partners are assigned to your districts yet.</div>
                    <?php else: ?>

                    <h3 style="font-size:15px;font-weight:700;margin:0 0 10px;">Purchase Order Requests</h3>
                    <div class="row">
                        <div class="col-lg-4 col-sm-6">
                            <div class="stat-card warning"><div><h3><?php echo $po_waiting; ?></h3><p>Waiting</p></div><i class="material-icons-outlined stat-icon">hourglass_top</i></div>
                        </div>
                        <div class="col-lg-4 col-sm-6">
                            <div class="stat-card red"><div><h3><?php echo $po_cancelled; ?></h3><p>Rejected / Cancelled</p></div><i class="material-icons-outlined stat-icon">cancel</i></div>
                        </div>
                        <div class="col-lg-4 col-sm-6">
                            <div class="stat-card green"><div><h3><?php echo $po_completed; ?></h3><p>Completed</p></div><i class="material-icons-outlined stat-icon">check_circle</i></div>
                        </div>
                    </div>

                    <div class="filter-card">
                        <form method="GET" action="">
                            <div class="row align-items-end g-2">
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">person</i>
                                        Territory Partner
                                    </label>
                                    <select name="tp_id" class="form-control">
                                        <option value="">All TPs</option>
                                        <?php foreach ($tps as $tp): ?>
                                        <option value="<?php echo $tp['id']; ?>" <?php echo $filter_tp_id == $tp['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tp['name']); ?> (<?php echo htmlspecialchars($tp['tp_code']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-2 col-sm-6">
                                    <label class="form-label">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">flag</i>
                                        Status
                                    </label>
                                    <select name="status" class="form-control">
                                        <option value="">All</option>
                                        <option value="waiting" <?php echo $filter_status==='waiting'?'selected':''; ?>>Waiting</option>
                                        <option value="completed" <?php echo $filter_status==='completed'?'selected':''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $filter_status==='cancelled'?'selected':''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">event</i>
                                        From Date
                                    </label>
                                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                                </div>
                                <div class="col-lg-2 col-sm-6">
                                    <label class="form-label">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">event</i>
                                        To Date
                                    </label>
                                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                                </div>
                                <div class="col-lg-2 col-sm-6">
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <button type="submit" class="btn-filter">
                                            <i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;">filter_list</i>
                                            Filter
                                        </button>
                                        <?php if ($filters_active): ?>
                                        <a href="tp-purchase-order" class="btn-clear">
                                            <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;margin-right:3px;">close</i>
                                            Clear
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                        <span style="font-weight:600;font-size:14px;">
                                            All Requests
                                            <?php if ($filters_active): ?><span class="filter-active-badge">Filtered</span><?php endif; ?>
                                        </span>
                                        <div class="input-group" id="searchBox">
                                            <span class="input-group-text"><i class="material-icons-outlined" style="font-size:16px;">search</i></span>
                                            <input type="text" id="searchInput" class="form-control" placeholder="Search invoice no. or TP name...">
                                        </div>
                                    </div>
                                    <div style="background:#fff;overflow:scroll;width:100%;">
                                        <table class="table" id="invoiceTable">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Date</th>
                                                    <th>Territory Partner</th>
                                                    <th class="text-right">Items</th>
                                                    <th class="text-right">Amount (&#8377;)</th>
                                                    <th>Status</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php if (empty($orders)): ?>
                                                <tr><td colspan="7" class="text-center text-muted">No purchase order requests in this period.</td></tr>
                                            <?php else: foreach ($orders as $o):
                                                $isCompleted = $o['status'] === 'completed' && $o['tp_invoice_id'];
                                                $amount = $isCompleted ? (float)$o['invoice_total'] : (float)$o['po_item_total'];
                                                $itemCount = $isCompleted ? (int)$o['invoice_item_count'] : (int)$o['po_item_count'];
                                                $searchKey = strtolower(($o['invoice_number'] ?? '') . ' ' . $o['tp_name'] . ' ' . $o['tp_code']);
                                                $rowItems = $isCompleted ? ($itemsByInvoice[(int)$o['tp_invoice_id']] ?? []) : ($itemsByPo[(int)$o['po_id']] ?? []);
                                                $itemsForJs = array_map(fn($it) => ['product' => $it['productName'], 'qty' => (int)$it['qty'], 'amount' => (float)$it['amount']], $rowItems);
                                                $items_json = htmlspecialchars(json_encode($itemsForJs, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                                            ?>
                                                <tr data-search="<?php echo htmlspecialchars($searchKey); ?>">
                                                    <td><?php echo $o['invoice_number'] ? '<code>'.htmlspecialchars($o['invoice_number']).'</code>' : '#'.(int)$o['po_id']; ?></td>
                                                    <td><?php echo htmlspecialchars($o['order_date']); ?></td>
                                                    <td><?php echo htmlspecialchars($o['tp_name']); ?> <small class="text-muted">(<?php echo htmlspecialchars($o['tp_code']); ?>)</small></td>
                                                    <td class="text-right">
                                                        <button type="button" class="items-view-trigger"
                                                                data-partner="<?php echo htmlspecialchars($o['tp_name'], ENT_QUOTES); ?>"
                                                                data-items="<?php echo $items_json; ?>">
                                                            <?php echo $itemCount; ?> item<?php echo $itemCount !== 1 ? 's' : ''; ?>
                                                        </button>
                                                    </td>
                                                    <td class="text-right"><b>&#8377;<?php echo inr_format($amount, 2); ?></b></td>
                                                    <td>
                                                        <span class="po-status-pill <?php echo htmlspecialchars($o['status']); ?>" <?php echo $o['status']==='cancelled' && $o['cancel_reason'] ? 'title="'.htmlspecialchars($o['cancel_reason'], ENT_QUOTES).'"' : ''; ?>><?php echo ucfirst($o['status']); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($isCompleted): ?>
                                                        <a href="view-tp-invoice.php?id=<?php echo base64_encode($o['tp_invoice_id']); ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="material-icons" style="font-size:14px;vertical-align:middle;">visibility</i> View
                                                        </a>
                                                        <a href="tp-invoice-print.php?id=<?php echo base64_encode($o['tp_invoice_id']); ?>" class="btn btn-sm btn-outline-secondary">
                                                            <i class="material-icons" style="font-size:14px;vertical-align:middle;">print</i> Print
                                                        </a>
                                                        <?php endif; ?>
                                                        <?php if (!empty($rowItems)): ?>
                                                        <a href="dispatch-slip-print.php?po_id=<?php echo (int)$o['po_id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                                                            <i class="material-icons" style="font-size:14px;vertical-align:middle;">local_shipping</i> Dispatch Slip
                                                        </a>
                                                        <?php elseif (!$isCompleted): ?>
                                                        <span class="text-muted">&mdash;</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                            </tbody>
                                            <?php if (!empty($orders)): ?>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="4" class="text-right">Grand Total</td>
                                                    <td class="text-right"><b>&#8377;<?php echo inr_format($grand_total, 2); ?></b></td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php endif; ?>
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
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
document.getElementById('searchInput').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    document.querySelectorAll('#invoiceTable tbody tr').forEach(function (row) {
        var hay = row.getAttribute('data-search');
        if (hay === null) return;
        row.classList.toggle('no-match', q !== '' && hay.indexOf(q) === -1);
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
    $.each(items, function (_, item) {
        html += '<div style="padding:7px 0;font-size:13.5px;color:#1f2937;border-bottom:1px dotted #f3f4f6;display:flex;justify-content:space-between;align-items:center;gap:10px;">' +
                '<span>' + $('<div>').text(item.product || '-').html() + '</span>' +
                '<span style="display:flex;align-items:center;gap:14px;white-space:nowrap;">' +
                '<span style="color:#6b7280;">x' + $('<div>').text(item.qty).html() + '</span>' +
                '<strong>&#8377;' + Number(item.amount || 0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</strong>' +
                '</span></div>';
    });
    $('#itemsViewModalBody').html(html || '<div style="color:#9ca3af;">No items.</div>');
    $('#itemsViewModal').modal('show');
});
</script>
</body>
</html>
