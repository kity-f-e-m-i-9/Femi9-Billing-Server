<?php
include("checksession.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Migration
$col = $db_conn->query("SHOW COLUMNS FROM tp_invoices LIKE 'courier_charges'");
if ($col && $col->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN courier_charges DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER invoice_date");
}

$filter_tp_id = (int)($_GET['tp_id'] ?? 0);

// TPs belonging to this SS that have invoices
$tp_filter_res = $db_conn->prepare("
    SELECT DISTINCT tp.id, tp.name, tp.tp_id AS tp_code
    FROM tp_invoices tpi
    JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
    WHERE tp.onboard_ss_id = ?
    ORDER BY tp.name
");
$tp_filter_res->bind_param("s", $Login_user_IDvl);
$tp_filter_res->execute();
$tp_filter_list = $tp_filter_res->get_result()->fetch_all(MYSQLI_ASSOC);
$tp_filter_res->close();

// Main query — scoped to SS's TPs
$where  = ["tp.onboard_ss_id = ?"];
$params = [$Login_user_IDvl];
$types  = 's';

if ($filter_tp_id > 0) {
    $where[]  = "tpi.territory_partner_id = ?";
    $params[] = $filter_tp_id;
    $types   .= 'i';
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT tpi.id, tpi.invoice_number, tpi.invoice_date, tpi.total_amount,
           COALESCE(tpi.courier_charges, 0) AS courier_charges,
           tpi.created_by, tpi.created_at,
           tp.name AS tp_name, tp.tp_id AS tp_code
    FROM tp_invoices tpi
    JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
    $where_sql
    ORDER BY tpi.created_at DESC
";

$stmt_main = $db_conn->prepare($sql);
$stmt_main->bind_param($types, ...$params);
$stmt_main->execute();
$invoices = $stmt_main->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_main->close();

$total_count  = count($invoices);
$total_amount = array_sum(array_column($invoices, 'total_amount'));
$this_month   = 0; $month_amount = 0;
$cur_ym = date('Y-m');
foreach ($invoices as $inv) {
    if (substr($inv['invoice_date'], 0, 7) === $cur_ym) { $this_month++; $month_amount += $inv['total_amount']; }
}
$i = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Invoices : <?php echo $business_name; ?></title>
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
        body { font-family:'Poppins',sans-serif; }
        .stat-card { background:#fff; border-radius:10px; padding:18px 20px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border-left:4px solid; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; }
        .stat-card.purple { border-color:#667eea; } .stat-card.green { border-color:#10b981; } .stat-card.blue { border-color:#3b82f6; } .stat-card.amber { border-color:#f59e0b; }
        .stat-card h3 { font-size:22px; font-weight:700; margin:0 0 2px 0; color:#1f2937; }
        .stat-card p  { margin:0; font-size:11.5px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
        .stat-icon { font-size:36px; opacity:.12; }
        .stat-card.purple .stat-icon { color:#667eea; } .stat-card.green .stat-icon { color:#10b981; } .stat-card.blue .stat-icon { color:#3b82f6; } .stat-card.amber .stat-icon { color:#f59e0b; }
        .card { border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border:none; margin-bottom:20px; }
        .card-header { background:#fff; border-bottom:1px solid #f0f0f0; border-radius:10px 10px 0 0 !important; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; }
        .card-header-title { font-size:14px; font-weight:600; color:#2c3e50; margin:0; display:flex; align-items:center; gap:8px; }
        .card-header-title i { font-size:18px; color:#667eea; }
        .alert { border-radius:8px; border:none; font-size:13.5px; padding:12px 16px; }
        .alert-success { background:#f0fdf4; color:#166534; border-left:4px solid #22c55e; }
        .alert-danger  { background:#fef2f2; color:#991b1b; border-left:4px solid #ef4444; }
        .alert-warning { background:#fffbeb; color:#92400e; border-left:4px solid #f59e0b; }
        .btn-add { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border-radius:7px; font-size:13px; font-weight:500; border:none; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; transition:all .2s; text-decoration:none; }
        .btn-add:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(102,126,234,.4); color:#fff; }
        table#datatable1 thead th { font-size:11.5px !important; font-weight:600 !important; color:#6b7280 !important; text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }
        .action-btn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:6px; transition:background .15s; text-decoration:none; }
        .action-btn.view   { color:#667eea; } .action-btn.view:hover   { background:#ede9fe; }
        .action-btn.edit   { color:#d97706; } .action-btn.edit:hover   { background:#fef3c7; }
        .action-btn.print  { color:#0369a1; } .action-btn.print:hover  { background:#e0f2fe; }
        .action-btn.delete { color:#dc2626; } .action-btn.delete:hover { background:#fee2e2; }
        .courier-chip { display:inline-block; background:#fef3c7; color:#92400e; border-radius:5px; padding:2px 8px; font-size:11px; font-weight:600; }
        .filter-card { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-radius:10px; padding:18px 20px; margin-bottom:20px; }
        .filter-card .form-label { color:#fff; font-weight:500; margin-bottom:4px; font-size:12.5px; }
        .filter-card .form-control { background:rgba(255,255,255,0.95); border:none; border-radius:6px; font-size:13px; height:36px; padding:4px 10px; }
        .filter-card .btn-filter { background:#fff; color:#667eea; border:none; border-radius:6px; padding:7px 18px; font-size:13px; font-weight:600; height:36px; line-height:1; cursor:pointer; }
        .filter-card .btn-clear { background:rgba(255,255,255,0.18); color:#fff; border:1px solid rgba(255,255,255,0.4); border-radius:6px; padding:7px 14px; font-size:13px; height:36px; line-height:1; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; }
        .filter-card .btn-clear:hover { background:rgba(255,255,255,0.28); color:#fff; }
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
                                <h1><table class="headertble"><tr>
                                    <td>TP Invoices</td>
                                    <td><a href="add-tp-invoice" title="Create TP Invoice">&#10011;</a></td>
                                </tr></table></h1>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success mb-3"><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">check_circle</i> Invoice <strong><?php echo htmlspecialchars($_GET['inv'] ?? ''); ?></strong> created successfully.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['deleted'])): ?>
                        <div class="alert alert-warning mb-3"><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">delete_outline</i> Invoice <strong><?php echo htmlspecialchars($_GET['inv'] ?? ''); ?></strong> deleted. Stock and advance balance restored.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger mb-3"><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">error_outline</i> An error occurred. Please try again.</div>
                    <?php endif; ?>

                    <!-- Filter -->
                    <div class="filter-card">
                        <form method="GET" action="">
                            <div class="row align-items-end g-2">
                                <div class="col-lg-4 col-sm-6">
                                    <label class="form-label">Territory Partner</label>
                                    <select name="tp_id" class="form-control">
                                        <option value="">All TPs</option>
                                        <?php foreach ($tp_filter_list as $tp): ?>
                                        <option value="<?php echo $tp['id']; ?>" <?php echo $filter_tp_id == $tp['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tp['name']); ?> (<?php echo htmlspecialchars($tp['tp_code']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label" style="visibility:hidden;">.</label>
                                    <div style="display:flex;gap:8px;">
                                        <button type="submit" class="btn-filter">Filter</button>
                                        <?php if ($filter_tp_id): ?>
                                            <a href="manage-tp-invoices" class="btn-clear">Clear</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Stats -->
                    <div class="row">
                        <div class="col-lg-3 col-sm-6"><div class="stat-card purple"><div><h3><?php echo $total_count; ?></h3><p>Total Invoices</p></div><i class="material-icons-outlined stat-icon">receipt_long</i></div></div>
                        <div class="col-lg-3 col-sm-6"><div class="stat-card green"><div><h3>₹<?php echo inr_format($total_amount, 0); ?></h3><p>Total Value</p></div><i class="material-icons-outlined stat-icon">currency_rupee</i></div></div>
                        <div class="col-lg-3 col-sm-6"><div class="stat-card blue"><div><h3><?php echo $this_month; ?></h3><p>This Month</p></div><i class="material-icons-outlined stat-icon">calendar_month</i></div></div>
                        <div class="col-lg-3 col-sm-6"><div class="stat-card amber"><div><h3>₹<?php echo inr_format($month_amount, 0); ?></h3><p>Month Value</p></div><i class="material-icons-outlined stat-icon">trending_up</i></div></div>
                    </div>

                    <!-- Table Card -->
                    <div class="card">
                        <div class="card-header">
                            <span class="card-header-title"><i class="material-icons-outlined">receipt_long</i> TP Invoices</span>
                            <a href="add-tp-invoice" class="btn-add"><i class="material-icons" style="font-size:16px;">add</i> New Invoice</a>
                        </div>
                        <div class="card-body">
                            <div style="overflow-x:auto;">
                                <table id="datatable1" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>#</th><th>Invoice #</th><th>Territory Partner</th>
                                            <th>Date</th><th>Amount (₹)</th><th>Courier</th>
                                            <th>Created By</th><th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($invoices as $inv):
                                        $enc     = base64_encode($inv['id']);
                                        $courier = (float)$inv['courier_charges'];
                                        $subtotal = round((float)$inv['total_amount'] - $courier, 2);
                                    ?>
                                        <tr>
                                            <td style="color:#9ca3af;font-size:13px;"><?php echo ++$i; ?></td>
                                            <td><code style="font-size:12px;background:#f3f4f6;padding:2px 7px;border-radius:4px;"><?php echo htmlspecialchars($inv['invoice_number']); ?></code></td>
                                            <td>
                                                <span style="font-weight:600;font-size:13.5px;"><?php echo htmlspecialchars($inv['tp_name']); ?></span><br>
                                                <small style="color:#9ca3af;font-size:11px;"><?php echo htmlspecialchars($inv['tp_code']); ?></small>
                                            </td>
                                            <td style="font-size:13px;color:#6b7280;"><?php echo date('d M Y', strtotime($inv['invoice_date'])); ?></td>
                                            <td>
                                                <span style="font-weight:700;color:#10b981;font-size:13.5px;">₹<?php echo inr_format($subtotal, 2); ?></span>
                                                <?php if ($courier > 0): ?>
                                                    <br><small style="color:#94a3b8;font-size:11px;">+₹<?php echo inr_format($courier, 2); ?> courier</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($courier > 0): ?>
                                                    <span class="courier-chip">₹<?php echo inr_format($courier, 2); ?></span>
                                                <?php else: ?>
                                                    <span style="color:#d1d5db;font-size:12px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:13px;color:#6b7280;"><?php echo htmlspecialchars($inv['created_by']); ?></td>
                                            <td style="white-space:nowrap;">
                                                <a href="view-tp-invoice?id=<?php echo $enc; ?>" class="action-btn view" title="View">
                                                    <i class="material-icons-outlined" style="font-size:19px;">visibility</i>
                                                </a>
                                                <a href="edit-tp-invoice?id=<?php echo $enc; ?>" class="action-btn edit" title="Edit">
                                                    <i class="material-icons-outlined" style="font-size:19px;">edit</i>
                                                </a>
                                                <a href="tp-invoice-print?id=<?php echo $enc; ?>" class="action-btn print" title="Print" target="_blank">
                                                    <i class="material-icons-outlined" style="font-size:19px;">print</i>
                                                </a>
                                                <form method="POST" action="delete-tp-invoice" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="invoice_enc" value="<?php echo $enc; ?>">
                                                    <button type="button" class="action-btn delete btn-delete-invoice" title="Delete"
                                                            data-inv="<?php echo htmlspecialchars($inv['invoice_number']); ?>">
                                                        <i class="material-icons-outlined" style="font-size:19px;">delete_outline</i>
                                                    </button>
                                                </form>
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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-delete-invoice').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var inv  = this.dataset.inv;
            var form = this.closest('form');
            Swal.fire({
                title: 'Delete ' + inv + '?',
                html: 'This will <strong>reverse all stock movements</strong> and restore the advance balance.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) form.submit();
            });
        });
    });
});
</script>
</body>
</html>
