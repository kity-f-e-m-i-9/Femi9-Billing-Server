<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_cp_id     = (int)($_GET['cp_id'] ?? 0);
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to']   ?? '');
$filter_type      = $_GET['type_filter'] ?? '';
if (!in_array($filter_type, ['napkin', 'diaper'], true)) $filter_type = '';

// Channel Partners that actually have invoices — for the filter dropdown.
$cps_res = $db_conn->query("
    SELECT DISTINCT cp.id, cp.name, cp.cp_id AS cp_code
    FROM cp_invoices cpi
    JOIN channel_partners cp ON cp.id = cpi.channel_partner_id
    ORDER BY cp.name
");
$cps = $cps_res ? $cps_res->fetch_all(MYSQLI_ASSOC) : [];

$where = ['1=1'];
$params = []; $types = '';

if ($filter_cp_id > 0) {
    $where[]  = "cpi.channel_partner_id = ?";
    $params[] = $filter_cp_id;
    $types   .= 'i';
}
if ($filter_date_from !== '') {
    $where[]  = "cpi.invoice_date >= ?";
    $params[] = $filter_date_from;
    $types   .= 's';
}
if ($filter_date_to !== '') {
    $where[]  = "cpi.invoice_date <= ?";
    $params[] = $filter_date_to;
    $types   .= 's';
}
if ($filter_type !== '') {
    $where[]  = "cpi.product_type = ?";
    $params[] = $filter_type;
    $types   .= 's';
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT cpi.id, cpi.invoice_number, cpi.invoice_date, cpi.total_amount, cpi.product_type,
           cpi.created_by, cpi.created_by_user_type, cpi.created_at,
           cp.name AS cp_name, cp.cp_id AS cp_code,
           COALESCE(NULLIF(cp.delivery_district,''), cp.branch_district) AS cp_district,
           gd.gname AS godown_name,
           po.id AS po_id
    FROM cp_invoices cpi
    JOIN channel_partners cp       ON cp.id = cpi.channel_partner_id
    LEFT JOIN company_godown gd    ON gd.id = cpi.source_godown_id
    LEFT JOIN channel_partner_purchase_orders po ON po.cp_invoice_id = cpi.id
    $where_sql
    ORDER BY cpi.created_at DESC
";
if ($params) {
    $stmt_main = $db_conn->prepare($sql);
    $stmt_main->bind_param($types, ...$params);
    $stmt_main->execute();
    $invoices = $stmt_main->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_main->close();
} else {
    $result   = $db_conn->query($sql);
    $invoices = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

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
    <title>CP Invoices : <?php echo $business_name; ?></title>
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

        .stat-card { background:#fff; border-radius:10px; padding:18px 20px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border-left:4px solid; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; }
        .stat-card.purple { border-color:#667eea; }
        .stat-card.green  { border-color:#10b981; }
        .stat-card.blue   { border-color:#3b82f6; }
        .stat-card.amber  { border-color:#f59e0b; }
        .stat-card h3 { font-size:22px; font-weight:700; margin:0 0 2px 0; color:#1f2937; }
        .stat-card p  { margin:0; font-size:11.5px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
        .stat-icon { font-size:36px; opacity:.12; }
        .stat-card.purple .stat-icon { color:#667eea; }
        .stat-card.green  .stat-icon { color:#10b981; }
        .stat-card.blue   .stat-icon { color:#3b82f6; }
        .stat-card.amber  .stat-icon { color:#f59e0b; }

        .card { border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border:none; margin-bottom:20px; }
        .card-header { background:#fff; border-bottom:1px solid #f0f0f0; border-radius:10px 10px 0 0 !important; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; }
        .card-header-title { font-size:14px; font-weight:600; color:#2c3e50; margin:0; display:flex; align-items:center; gap:8px; }
        .card-header-title i { font-size:18px; color:#667eea; }

        table#datatable1 thead th { font-size:11.5px !important; font-weight:600 !important; color:#6b7280 !important; text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }

        .action-btn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:6px; transition:background .15s; text-decoration:none; }
        .action-btn.print  { color:#0369a1; } .action-btn.print:hover  { background:#e0f2fe; }
        .action-btn.ship   { color:#92400e; } .action-btn.ship:hover   { background:#fef3c7; }

        .filter-card { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-radius:10px; padding:18px 20px; margin-bottom:20px; }
        .filter-card .form-label { color:#fff; font-weight:500; margin-bottom:4px; font-size:12.5px; }
        .filter-card .form-control, .filter-card select.form-control { background:rgba(255,255,255,0.95); border:none; border-radius:6px; font-size:13px; height:36px; padding:4px 10px; }
        .filter-card .btn-filter { background:#fff; color:#667eea; border:none; border-radius:6px; padding:7px 18px; font-size:13px; font-weight:600; height:36px; line-height:1; cursor:pointer; transition:all .15s; }
        .filter-card .btn-filter:hover { background:#f0f0ff; }
        .filter-card .btn-clear { background:rgba(255,255,255,0.18); color:#fff; border:1px solid rgba(255,255,255,0.4); border-radius:6px; padding:7px 14px; font-size:13px; height:36px; line-height:1; cursor:pointer; transition:all .15s; text-decoration:none; display:inline-flex; align-items:center; }
        .filter-card .btn-clear:hover { background:rgba(255,255,255,0.28); color:#fff; }
        .filter-active-badge { display:inline-block; background:#fbbf24; color:#78350f; border-radius:5px; padding:2px 8px; font-size:11px; font-weight:700; margin-left:8px; vertical-align:middle; }
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
                                        <td>CP Invoices</td>
                                    </tr></table>
                                </h1>
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
                                        Channel Partner
                                    </label>
                                    <select name="cp_id" class="form-control">
                                        <option value="">All CPs</option>
                                        <?php foreach ($cps as $cp): ?>
                                        <option value="<?php echo (int)$cp['id']; ?>" <?php echo $filter_cp_id == $cp['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cp['name']); ?> (<?php echo htmlspecialchars($cp['cp_code']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">event</i>
                                        Date From
                                    </label>
                                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">event</i>
                                        Date To
                                    </label>
                                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label">
                                        <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">category</i>
                                        Type
                                    </label>
                                    <select name="type_filter" class="form-control">
                                        <option value="">Napkin + Diaper</option>
                                        <option value="napkin" <?php echo $filter_type === 'napkin' ? 'selected' : ''; ?>>Napkin only</option>
                                        <option value="diaper" <?php echo $filter_type === 'diaper' ? 'selected' : ''; ?>>Lumi Diaper only</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 col-sm-6">
                                    <label class="form-label" style="visibility:hidden;">.</label>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <button type="submit" class="btn-filter">
                                            <i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;">filter_list</i>
                                            Filter
                                        </button>
                                        <?php if ($filter_cp_id || $filter_date_from || $filter_date_to || $filter_type): ?>
                                        <a href="manage-cp-invoices" class="btn-clear">
                                            <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;margin-right:3px;">close</i>
                                            Clear
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Stats -->
                    <div class="row">
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card purple">
                                <div><h3><?php echo $total_count; ?></h3><p>Total Invoices</p></div>
                                <i class="material-icons-outlined stat-icon">receipt_long</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card green">
                                <div><h3>₹<?php echo inr_format($total_amount, 0); ?></h3><p>Total Value</p></div>
                                <i class="material-icons-outlined stat-icon">currency_rupee</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card blue">
                                <div><h3><?php echo $this_month; ?></h3><p>This Month</p></div>
                                <i class="material-icons-outlined stat-icon">calendar_month</i>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="stat-card amber">
                                <div><h3>₹<?php echo inr_format($month_amount, 0); ?></h3><p>Month Value</p></div>
                                <i class="material-icons-outlined stat-icon">trending_up</i>
                            </div>
                        </div>
                    </div>

                    <!-- Table Card -->
                    <div class="card">
                        <div class="card-header">
                            <span class="card-header-title">
                                <i class="material-icons-outlined">receipt_long</i>
                                All CP Invoices
                                <?php if ($filter_cp_id || $filter_date_from || $filter_date_to || $filter_type): ?>
                                <span class="filter-active-badge">Filtered</span>
                                <?php endif; ?>
                            </span>
                            <a href="export-cp-invoices-xlsx.php?<?php echo http_build_query([
                                    'cp_id'       => $filter_cp_id ?: '',
                                    'date_from'   => $filter_date_from,
                                    'date_to'     => $filter_date_to,
                                    'type_filter' => $filter_type,
                                ]); ?>" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;background:#10b981;color:#fff;border-radius:6px;padding:7px 16px;font-size:13px;font-weight:600;">
                                <i class="material-icons-outlined" style="font-size:16px;">download</i>
                                Export Excel
                            </a>
                        </div>
                        <div class="card-body">
                            <div style="overflow-x:auto;">
                                <table id="datatable1" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Invoice #</th>
                                            <th>Channel Partner</th>
                                            <th>District</th>
                                            <th>Source</th>
                                            <th>Date</th>
                                            <th>Amount (₹)</th>
                                            <th>Created By</th>
                                            <th>Invoiced Time</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($invoices as $inv):
                                        $enc = base64_encode($inv['id']);
                                        $_invType = tpResolveProductType($inv['product_type'] ?? null);
                                        [$_tBg, $_tFg] = tpProductTypeBadgeColors($_invType);
                                    ?>
                                        <tr>
                                            <td style="color:#9ca3af;font-size:13px;"><?php echo ++$i; ?></td>
                                            <td>
                                                <code style="font-size:12px;background:#f3f4f6;padding:2px 7px;border-radius:4px;"><?php echo htmlspecialchars($inv['invoice_number']); ?></code>
                                                <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:9px;background:<?php echo $_tBg; ?>;color:<?php echo $_tFg; ?>;"><?php echo htmlspecialchars(tpProductTypeLabel($_invType)); ?></span>
                                            </td>
                                            <td>
                                                <span style="font-weight:600;font-size:13.5px;"><?php echo htmlspecialchars($inv['cp_name']); ?></span><br>
                                                <small style="color:#9ca3af;font-size:11px;"><?php echo htmlspecialchars($inv['cp_code']); ?></small>
                                            </td>
                                            <td style="font-size:12.5px;color:#374151;"><?php echo htmlspecialchars($inv['cp_district'] ?: '—'); ?></td>
                                            <td style="font-size:13.5px;"><?php echo htmlspecialchars($inv['godown_name'] ?: '—'); ?></td>
                                            <td style="font-size:13px;color:#6b7280;"><?php echo date('d M Y', strtotime($inv['invoice_date'])); ?></td>
                                            <td><span style="font-weight:700;color:#10b981;font-size:13.5px;">₹<?php echo inr_format((float)$inv['total_amount'], 2); ?></span></td>
                                            <td style="font-size:13px;color:#6b7280;"><?php echo htmlspecialchars($inv['created_by']); ?></td>
                                            <td style="white-space:nowrap;font-size:12.5px;color:#4b5563;">
                                                <?php if (!empty($inv['created_at'])): ?>
                                                <?php echo date('d M Y', strtotime($inv['created_at'])); ?><br>
                                                <span style="color:#9ca3af;"><?php echo date('h:i A', strtotime($inv['created_at'])); ?></span>
                                                <?php else: ?>
                                                —
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space:nowrap;">
                                                <a href="cp-invoice-print.php?id=<?php echo $enc; ?>" target="_blank" class="action-btn print" title="View / Print Invoice">
                                                    <i class="material-icons-outlined" style="font-size:19px;">print</i>
                                                </a>
                                                <?php if (!empty($inv['po_id'])): ?>
                                                <a href="cp-shipping-label-print.php?po_id=<?php echo (int)$inv['po_id']; ?>" target="_blank" class="action-btn ship" title="Shipping Labels">
                                                    <i class="material-icons-outlined" style="font-size:19px;">label</i>
                                                </a>
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
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
</body>
</html>
