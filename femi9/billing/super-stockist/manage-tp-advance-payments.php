<?php
include("checksession.php");
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'super_stockiest') {
    header("Location: dashboard"); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$ss_id = $Login_user_IDvl;

$filter_from   = $_GET['from_date'] ?? date('Y-m-01');
$filter_to     = $_GET['to_date']   ?? date('Y-m-d');
$filter_tp     = (int)($_GET['tp_id'] ?? 0);
$filter_status = $_GET['status'] ?? '';

$allowed_statuses = ['active','partially_adjusted','fully_adjusted',''];
if (!in_array($filter_status, $allowed_statuses, true)) $filter_status = '';

// Only TPs belonging to this SS
$where  = ["tp.onboard_ss_id = ?", "tap.payment_date BETWEEN ? AND ?"];
$params = [$ss_id, $filter_from, $filter_to];
$types  = "sss";

if ($filter_tp > 0) {
    $where[]  = "tap.territory_partner_id = ?";
    $params[] = $filter_tp;
    $types   .= "i";
}
if ($filter_status !== '') {
    $where[]  = "tap.status = ?";
    $params[] = $filter_status;
    $types   .= "s";
}

$sql = "SELECT tap.*, tp.name AS tp_name, tp.tp_id AS tp_code
        FROM tp_advance_payments tap
        JOIN territory_partners tp ON tp.id = tap.territory_partner_id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY tap.payment_date DESC, tap.id DESC";

$stmt = $db_conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_count    = count($payments);
$total_amount   = array_sum(array_column($payments, 'amount'));
$total_balance  = array_sum(array_column($payments, 'balance_amount'));
$total_adjusted = array_sum(array_column($payments, 'adjusted_amount'));

// TPs under this SS for filter dropdown
$tp_stmt = $db_conn->prepare("SELECT id, tp_id, name FROM territory_partners WHERE is_active=1 AND onboard_ss_id=? ORDER BY name");
$tp_stmt->bind_param("s", $ss_id); $tp_stmt->execute();
$tps = $tp_stmt->get_result()->fetch_all(MYSQLI_ASSOC); $tp_stmt->close();

$i = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Advance Payments : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
    <style>
        .filter-card { background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-radius:10px; padding:20px; margin-bottom:20px; }
        .filter-card .form-label { color:#fff; font-weight:500; margin-bottom:5px; }
        .filter-card .form-control, .filter-card select { background:rgba(255,255,255,0.95); border:none; border-radius:6px; }
        .stats-card { background:#fff; border-radius:10px; padding:18px 20px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-left:4px solid #667eea; }
        .stats-card h3 { font-size:26px; font-weight:700; margin:0; color:#667eea; }
        .stats-card p  { margin:4px 0 0 0; color:#6b7280; font-size:13px; font-weight:500; }
        .status-active    { background:#d1fae5; color:#065f46; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        .status-partially { background:#fef3c7; color:#92400e; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        .status-fully     { background:#dbeafe; color:#1e40af; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
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
                                <h1>
                                    <table class="headertble"><tr>
                                        <td>TP Advance Payments</td>
                                        <td><a href="add-tp-advance-payment" title="Add Payment"><i class="material-icons">add_circle</i></a></td>
                                    </tr></table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <h3><?php echo $total_count; ?></h3>
                                <p>Total Payments</p>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <h3>₹<?php echo number_format($total_amount, 2); ?></h3>
                                <p>Total Amount</p>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <h3>₹<?php echo number_format($total_balance, 2); ?></h3>
                                <p>Total Balance</p>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <h3>₹<?php echo number_format($total_adjusted, 2); ?></h3>
                                <p>Adjusted Amount</p>
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
                                            <input type="date" name="from_date" class="form-control"
                                                   value="<?php echo htmlspecialchars($filter_from); ?>" max="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">To Date</label>
                                            <input type="date" name="to_date" class="form-control"
                                                   value="<?php echo htmlspecialchars($filter_to); ?>" max="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Territory Partner</label>
                                            <select name="tp_id" class="form-control">
                                                <option value="">All TPs</option>
                                                <?php foreach ($tps as $tp): ?>
                                                <option value="<?php echo $tp['id']; ?>" <?php echo $filter_tp == $tp['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($tp['name']); ?> (<?php echo htmlspecialchars($tp['tp_id']); ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-control">
                                                <option value="">All Status</option>
                                                <option value="active"             <?php echo $filter_status==='active'             ? 'selected' : ''; ?>>Active</option>
                                                <option value="partially_adjusted" <?php echo $filter_status==='partially_adjusted' ? 'selected' : ''; ?>>Partially Adjusted</option>
                                                <option value="fully_adjusted"     <?php echo $filter_status==='fully_adjusted'     ? 'selected' : ''; ?>>Fully Adjusted</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 d-flex gap-2">
                                            <button type="submit" class="btn btn-light font-weight-bold">
                                                <i class="material-icons" style="vertical-align:middle;font-size:17px;">filter_list</i> Filter
                                            </button>
                                            <a href="manage-tp-advance-payments" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.5);">
                                                <i class="material-icons" style="vertical-align:middle;font-size:17px;">refresh</i> Reset
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div style="overflow-x:auto;">
                                        <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>S.No</th>
                                                    <th>TP Name</th>
                                                    <th>TP ID</th>
                                                    <th>Date</th>
                                                    <th>Amount (₹)</th>
                                                    <th>Balance (₹)</th>
                                                    <th>Adjusted (₹)</th>
                                                    <th>Mode</th>
                                                    <th>Reference</th>
                                                    <th>Status</th>
                                                    <th>Recorded By</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($payments as $p): ?>
                                                <tr>
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo htmlspecialchars($p['tp_name']); ?></td>
                                                    <td><code style="font-size:12px;"><?php echo htmlspecialchars($p['tp_code']); ?></code></td>
                                                    <td><?php echo htmlspecialchars($p['payment_date']); ?></td>
                                                    <td class="text-right font-weight-bold"><?php echo number_format($p['amount'], 2); ?></td>
                                                    <td class="text-right" style="color:#10b981;font-weight:600;"><?php echo number_format($p['balance_amount'], 2); ?></td>
                                                    <td class="text-right"><?php echo number_format($p['adjusted_amount'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                                    <td><small class="text-muted"><?php echo htmlspecialchars($p['reference_number'] ?: '—'); ?></small></td>
                                                    <td>
                                                        <?php if ($p['status'] === 'active'): ?>
                                                            <span class="status-active">Active</span>
                                                        <?php elseif ($p['status'] === 'partially_adjusted'): ?>
                                                            <span class="status-partially">Partially Adjusted</span>
                                                        <?php else: ?>
                                                            <span class="status-fully">Fully Adjusted</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($p['created_by']); ?></td>
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
