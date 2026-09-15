<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
require_once("include/GodownAccess.php");
error_reporting(0);

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── Filters ──────────────────────────────────────────────────────────────
$filter_cp_id     = (int)($_GET['cp_id'] ?? 0);
$filter_date_from = trim($_GET['date_from'] ?? '');
$filter_date_to   = trim($_GET['date_to']   ?? '');
$filter_type      = $_GET['type_filter'] ?? '';
if (!in_array($filter_type, ['napkin', 'diaper'], true)) $filter_type = '';

// CPs with at least one invoice, for the filter dropdown
$cps_res = $db_conn->query("
    SELECT DISTINCT cp.id, cp.name, cp.cp_id AS cp_code
    FROM cp_invoices cpi
    JOIN channel_partners cp ON cp.id = cpi.channel_partner_id
    ORDER BY cp.name
");
$cps = $cps_res ? $cps_res->fetch_all(MYSQLI_ASSOC) : [];

// ── Build main query with filters ───────────────────────────────────────
$where  = [];
$params = [];
$types  = '';

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

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT cpi.id, cpi.invoice_number, cpi.invoice_date, cpi.total_amount, cpi.product_type,
           cp.name AS cp_name, cp.cp_id AS cp_code,
           gd.gname AS godown_name
    FROM cp_invoices cpi
    JOIN channel_partners cp ON cp.id = cpi.channel_partner_id
    LEFT JOIN company_godown gd ON gd.id = cpi.source_godown_id AND (" . godown_finance_filter_sql($db_conn, 'gd') . ")
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
        .stat-card h3 { font-size:22px; font-weight:700; margin:0 0 2px 0; color:#1f2937; }
        .stat-card p  { margin:0; font-size:11.5px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }
        .stat-icon { font-size:36px; opacity:.12; }
        .stat-card.purple .stat-icon { color:#667eea; }
        .stat-card.green  .stat-icon { color:#10b981; }
        .stat-card.blue   .stat-icon { color:#3b82f6; }
        .card { border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border:none; margin-bottom:20px; }
        .card-header { background:#fff; border-bottom:1px solid #f0f0f0; border-radius:10px 10px 0 0 !important; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; }
        .card-header-title { font-size:14px; font-weight:600; color:#2c3e50; margin:0; display:flex; align-items:center; gap:8px; }
        .card-header-title i { font-size:18px; color:#667eea; }
        .alert { border-radius:8px; border:none; font-size:13.5px; padding:12px 16px; }
        .alert-success { background:#f0fdf4; color:#166534; border-left:4px solid #22c55e; }
        .alert-danger  { background:#fef2f2; color:#991b1b; border-left:4px solid #ef4444; }
        .action-btn { display:inline-block; padding:4px 8px; border-radius:5px; margin-right:4px; text-decoration:none; }
        .action-btn.view  { background:#eff6ff; color:#3b82f6; }
        .action-btn.print { background:#f0fdf4; color:#10b981; }
        .action-btn.delete { background:#fef2f2; color:#ef4444; border:none; cursor:pointer; }
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

                <div class="row">
                    <div class="col">
                        <div class="page-description"><h1>CP Invoices</h1></div>
                    </div>
                </div>

                <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success">Invoice <?=htmlspecialchars($_GET['inv'] ?? '')?> deleted.</div>
                <?php elseif (isset($_GET['error'])): ?>
                <div class="alert alert-danger">Could not complete that action (<?=htmlspecialchars($_GET['error'])?>).</div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4">
                        <div class="stat-card purple">
                            <div><h3><?=$total_count?></h3><p>Total Invoices</p></div>
                            <i class="material-icons stat-icon">receipt_long</i>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card green">
                            <div><h3>₹<?=number_format($total_amount, 2)?></h3><p>Total Amount</p></div>
                            <i class="material-icons stat-icon">payments</i>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card blue">
                            <div><h3><?=$this_month?> (₹<?=number_format($month_amount, 2)?>)</h3><p>This Month</p></div>
                            <i class="material-icons stat-icon">calendar_month</i>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h6 class="card-header-title"><i class="material-icons">filter_alt</i>Filters</h6></div>
                    <div class="card-header" style="border-top:0;">
                        <form method="GET" action="" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;">
                            <select name="cp_id" class="form-control" style="width:200px;">
                                <option value="">All Channel Partners</option>
                                <?php foreach ($cps as $cp): ?>
                                <option value="<?=(int)$cp['id']?>" <?=$filter_cp_id === (int)$cp['id'] ? 'selected' : ''?>><?=htmlspecialchars($cp['name'])?> (<?=htmlspecialchars($cp['cp_code'])?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="date_from" class="form-control" style="width:160px;" value="<?=htmlspecialchars($filter_date_from)?>" placeholder="From">
                            <input type="date" name="date_to" class="form-control" style="width:160px;" value="<?=htmlspecialchars($filter_date_to)?>" placeholder="To">
                            <select name="type_filter" class="form-control" style="width:140px;">
                                <option value="">All Types</option>
                                <option value="napkin" <?=$filter_type === 'napkin' ? 'selected' : ''?>>Napkin</option>
                                <option value="diaper" <?=$filter_type === 'diaper' ? 'selected' : ''?>>Diaper</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="manage-cp-invoices.php" class="btn btn-secondary">Reset</a>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h6 class="card-header-title"><i class="material-icons">table_chart</i>Invoices</h6></div>
                    <div class="card-header" style="border-top:0;overflow-x:auto;">
                        <table id="datatable1" style="width:100%;">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Channel Partner</th>
                                    <th>Type</th>
                                    <th>Godown</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv): $enc = base64_encode((string)$inv['id']); ?>
                                <tr>
                                    <td><?=htmlspecialchars($inv['invoice_number'])?></td>
                                    <td><?=htmlspecialchars(date('d-M-Y', strtotime($inv['invoice_date']) ?: 0))?></td>
                                    <td><?=htmlspecialchars($inv['cp_name'])?> (<?=htmlspecialchars($inv['cp_code'])?>)</td>
                                    <td><?=htmlspecialchars(ucfirst($inv['product_type']))?></td>
                                    <td><?=htmlspecialchars($inv['godown_name'] ?? '-')?></td>
                                    <td>₹<?=number_format((float)$inv['total_amount'], 2)?></td>
                                    <td>
                                        <a href="cp-invoice-print.php?id=<?=htmlspecialchars($enc)?>" target="_blank" class="action-btn view" title="View / Print">
                                            <i class="material-icons" style="font-size:16px;vertical-align:middle;">visibility</i>
                                        </a>
                                        <form method="POST" action="delete-cp-invoice.php" style="display:inline" onsubmit="return confirm('Delete invoice <?=htmlspecialchars($inv['invoice_number'], ENT_QUOTES)?>? This only removes the invoice record — stock is not affected.');">
                                            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                                            <input type="hidden" name="invoice_enc" value="<?=htmlspecialchars($enc)?>">
                                            <button type="submit" class="action-btn delete" title="Delete Invoice">
                                                <i class="material-icons" style="font-size:16px;vertical-align:middle;">delete</i>
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

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/datatables/datatables.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script>
        $(document).ready(function () {
            $('#datatable1').DataTable({ order: [] });
        });
    </script>
</body>
</html>
