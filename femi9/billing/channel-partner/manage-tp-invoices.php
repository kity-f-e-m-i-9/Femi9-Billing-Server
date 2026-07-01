<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$cp_id = (int) $Login_user_IDvl;

// ── Filters ──────────────────────────────────────────────────────────────
$filter_tp_id   = (int)($_GET['tp_id']    ?? 0);
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to']   ?? '');

// TPs this CP has actually invoiced (for the filter dropdown)
$stmtTp = $db_conn->prepare("
    SELECT DISTINCT tp.id, tp.name, tp.tp_id AS tp_code
    FROM tp_invoices tpi
    JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
    WHERE tpi.source_cp_id = ?
    ORDER BY tp.name
");
$stmtTp->bind_param('i', $cp_id);
$stmtTp->execute();
$tps = $stmtTp->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtTp->close();

$where  = ["tpi.source_cp_id = ?"];
$params = [$cp_id];
$types  = 'i';

if ($filter_tp_id > 0) {
    $where[]  = "tpi.territory_partner_id = ?";
    $params[] = $filter_tp_id;
    $types   .= 'i';
}
if ($filter_date_from !== '') {
    $where[]  = "tpi.invoice_date >= ?";
    $params[] = $filter_date_from;
    $types   .= 's';
}
if ($filter_date_to !== '') {
    $where[]  = "tpi.invoice_date <= ?";
    $params[] = $filter_date_to;
    $types   .= 's';
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT tpi.id, tpi.invoice_number, tpi.invoice_date, tpi.courier_charges, tpi.discount_amount, tpi.total_amount,
           tp.name AS tp_name, tp.tp_id AS tp_code,
           (SELECT COUNT(*) FROM tp_invoice_items tii WHERE tii.tp_invoice_id = tpi.id) AS item_count
    FROM tp_invoices tpi
    JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
    $where_sql
    ORDER BY tpi.invoice_date DESC, tpi.id DESC
";
$stmt = $db_conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$grand_total  = array_sum(array_column($invoices, 'total_amount'));
$filters_active = $filter_tp_id || $filter_date_from || $filter_date_to;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales / Invoices : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
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
                                <h1><table class="headertble"><tr><td>Sales / Invoices</td></tr></table></h1>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
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
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">event</i>
                                        From Date
                                    </label>
                                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">event</i>
                                        To Date
                                    </label>
                                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label" style="visibility:hidden;">.</label>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <button type="submit" class="btn-filter">
                                            <i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;">filter_list</i>
                                            Filter
                                        </button>
                                        <?php if ($filters_active): ?>
                                        <a href="manage-tp-invoices.php" class="btn-clear">
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
                                            All Invoices
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
                                                    <th>Invoice No.</th>
                                                    <th>Date</th>
                                                    <th>Territory Partner</th>
                                                    <th>TP ID</th>
                                                    <th class="text-right">Items</th>
                                                    <th class="text-right">Total (₹)</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php if (empty($invoices)): ?>
                                                <tr><td colspan="7" class="text-center text-muted">No invoices sourced from your stock yet.</td></tr>
                                            <?php else: foreach ($invoices as $inv): ?>
                                                <tr data-search="<?php echo htmlspecialchars(strtolower($inv['invoice_number'] . ' ' . $inv['tp_name'] . ' ' . $inv['tp_code'])); ?>">
                                                    <td><code><?php echo htmlspecialchars($inv['invoice_number']); ?></code></td>
                                                    <td><?php echo htmlspecialchars($inv['invoice_date']); ?></td>
                                                    <td><?php echo htmlspecialchars($inv['tp_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($inv['tp_code']); ?></td>
                                                    <td class="text-right"><?php echo (int)$inv['item_count']; ?></td>
                                                    <td class="text-right"><b>₹<?php echo number_format((float)$inv['total_amount'], 2); ?></b></td>
                                                    <td>
                                                        <a href="view-tp-invoice.php?id=<?php echo base64_encode($inv['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="material-icons" style="font-size:14px;vertical-align:middle;">visibility</i> View
                                                        </a>
                                                        <a href="tp-invoice-print.php?id=<?php echo base64_encode($inv['id']); ?>" class="btn btn-sm btn-outline-secondary">
                                                            <i class="material-icons" style="font-size:14px;vertical-align:middle;">print</i> Print
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                            </tbody>
                                            <?php if (!empty($invoices)): ?>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="5" class="text-right">Grand Total</td>
                                                    <td class="text-right"><b>₹<?php echo number_format($grand_total, 2); ?></b></td>
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
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
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
</script>
</body>
</html>
