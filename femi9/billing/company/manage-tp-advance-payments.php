<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("include/GodownAccess.php");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);
tpEnsureAdvanceWalletColumns($db_conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Self-migrating: ensure deleted_at column exists for TP advance payment soft-delete.
$_tapDelCol = $db_conn->query("SHOW COLUMNS FROM tp_advance_payments LIKE 'deleted_at'");
if ($_tapDelCol && $_tapDelCol->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_advance_payments ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER status");
}

// Company profiles (receivers of the advance payment)
$company_profiles = $db_conn->query("SELECT id, gname FROM company_godown WHERE gname LIKE '%Femi%' AND " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$default_company_id = $company_profiles[0]['id'] ?? 0;

// Filter params
$filter_from = $_GET['from_date'] ?? date('Y-m-01');
$filter_to   = $_GET['to_date']   ?? date('Y-m-d');
$filter_tp   = (int)($_GET['tp_id'] ?? 0);
$filter_company = isset($_GET['company_id']) ? (int)$_GET['company_id'] : $default_company_id;
$filter_status = $_GET['status'] ?? '';
$filter_type   = $_GET['type_filter'] ?? '';

$allowed_statuses = ['active','partially_adjusted','fully_adjusted',''];
if (!in_array($filter_status, $allowed_statuses, true)) $filter_status = '';
if (!in_array($filter_type, ['napkin','diaper'], true)) $filter_type = '';

// Build query
$where = ["tap.deleted_at IS NULL", "tap.payment_date BETWEEN ? AND ?"];
$params = [$filter_from, $filter_to];
$types = "ss";

if ($filter_tp > 0) {
    $where[] = "tap.territory_partner_id = ?";
    $params[] = $filter_tp;
    $types .= "i";
}
if ($filter_company > 0) {
    $where[] = "tap.company_id = ?";
    $params[] = $filter_company;
    $types .= "i";
}
if ($filter_status !== '') {
    $where[] = "tap.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($filter_type !== '') {
    $where[] = "tap.product_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

$where[] = "(tap.company_id IS NULL OR " . godown_finance_filter_sql($db_conn, 'cg') . ")";

$sql = "SELECT tap.*, tp.name AS tp_name, tp.tp_id AS tp_code, cg.gname AS receiver_name
        FROM tp_advance_payments tap
        JOIN territory_partners tp ON tp.id = tap.territory_partner_id
        LEFT JOIN company_godown cg ON cg.id = tap.company_id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY tap.payment_date DESC, tap.id DESC";

$stmt = $db_conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// TP's monthly target = sum of target_amount across the Firka/location(s) assigned to them,
// batched once for every TP appearing in this filtered result set (not per-row queries).
$tpTargets = [];
$tpIdsInResult = array_unique(array_column($payments, 'territory_partner_id'));
if (!empty($tpIdsInResult)) {
    $idList = implode(',', array_map('intval', $tpIdsInResult));
    $resTgt = $db_conn->query("
        SELECT tpl.territory_partner_id, SUM(pln.target_amount) AS total_target, COUNT(pln.target_amount) AS set_count
        FROM territory_partner_locations tpl
        JOIN partner_location_nodes pln ON pln.id = tpl.location_id
        WHERE tpl.territory_partner_id IN ($idList)
        GROUP BY tpl.territory_partner_id
    ");
    while ($tr = $resTgt->fetch_assoc()) {
        $tpTargets[$tr['territory_partner_id']] = (int)$tr['set_count'] > 0 ? (float)$tr['total_target'] : null;
    }
}

// Stats
$total_count = count($payments);
$total_amount = array_sum(array_column($payments, 'amount'));
$total_balance = array_sum(array_column($payments, 'balance_amount'));
$total_adjusted = array_sum(array_column($payments, 'adjusted_amount'));

// Same four totals, split by wallet — the combined figure above hides
// which type each amount actually belongs to.
$stats_by_type = ['napkin' => ['count' => 0, 'amount' => 0.0, 'balance' => 0.0, 'adjusted' => 0.0], 'diaper' => ['count' => 0, 'amount' => 0.0, 'balance' => 0.0, 'adjusted' => 0.0]];
foreach ($payments as $p) {
    $t = ($p['product_type'] ?? 'napkin') === 'diaper' ? 'diaper' : 'napkin';
    $stats_by_type[$t]['count']++;
    $stats_by_type[$t]['amount'] += (float)$p['amount'];
    $stats_by_type[$t]['balance'] += (float)$p['balance_amount'];
    $stats_by_type[$t]['adjusted'] += (float)$p['adjusted_amount'];
}

// TPs for filter dropdown (payers)
$tps = $db_conn->query("SELECT id, tp_id, name FROM territory_partners ORDER BY name")->fetch_all(MYSQLI_ASSOC);

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
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
        .filter-card { background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border-radius:10px; padding:20px; margin-bottom:20px; }
        .filter-card .form-label { color:#fff; font-weight:500; margin-bottom:5px; }
        .filter-card .form-control, .filter-card select { background:rgba(255,255,255,0.95); border:none; border-radius:6px; }
        /* Select2 replaces the plain <select> with its own markup, which
           doesn't inherit the .form-control sizing above — left alone it
           renders shorter than its siblings (Receiver Name/Type/Status),
           throwing off the row's align-items:end alignment. Match the same
           height/background/radius here instead of guessing at a fixed
           top-margin nudge. */
        .filter-card .select2-container--default .select2-selection--single {
            background:rgba(255,255,255,0.95); border:none; border-radius:6px;
            height:38px; display:flex; align-items:center;
        }
        .filter-card .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height:normal; padding-left:12px; color:#374151;
        }
        .filter-card .select2-container--default .select2-selection--single .select2-selection__arrow {
            height:36px;
        }
        .stats-card { background:#fff; border-radius:10px; padding:18px 20px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-left:4px solid #667eea; }
        .stats-card h3 { font-size:26px; font-weight:700; margin:0; color:#667eea; }
        .stats-card p { margin:4px 0 0 0; color:#6b7280; font-size:13px; font-weight:500; }
        .status-active { background:#d1fae5; color:#065f46; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        .status-partially { background:#fef3c7; color:#92400e; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        .status-fully { background:#dbeafe; color:#1e40af; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        @keyframes advPayHighlightFlash {
            0%, 100% { background-color: #fef9c3; }
            50% { background-color: #fde68a; }
        }
        tr.adv-pay-highlight td { animation: advPayHighlightFlash 1.2s ease-in-out 2; background-color: #fef9c3; }
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
                                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
                                    <a href="review-mixed-advance-payments.php" style="display:inline-flex;align-items:center;gap:6px;background:#ede9fe;color:#6d28d9;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
                                        <i class="material-icons-outlined" style="font-size:17px;">fact_check</i>
                                        Review Mixed Napkin/Diaper Payments
                                    </a>
                                    <a href="tp-wallet-transfer.php" style="display:inline-flex;align-items:center;gap:6px;background:#dbeafe;color:#1e40af;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
                                        <i class="material-icons-outlined" style="font-size:17px;">swap_horiz</i>
                                        Transfer Between Napkin/Diaper Wallets
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <h3><?php echo $total_count; ?></h3>
                                <p>Total Payments</p>
                                <p style="font-size:11.5px;color:#9ca3af;margin-top:4px;">
                                    <?php echo $stats_by_type['napkin']['count']; ?> Napkin &middot; <?php echo $stats_by_type['diaper']['count']; ?> Diaper
                                </p>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <h3>₹<?php echo inr_format($total_amount, 2); ?></h3>
                                <p>Total Amount</p>
                                <p style="font-size:11.5px;color:#9ca3af;margin-top:4px;">
                                    ₹<?php echo inr_format($stats_by_type['napkin']['amount'], 2); ?> Napkin &middot; ₹<?php echo inr_format($stats_by_type['diaper']['amount'], 2); ?> Diaper
                                </p>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <h3>₹<?php echo inr_format($total_balance, 2); ?></h3>
                                <p>Total Balance</p>
                                <p style="font-size:11.5px;color:#9ca3af;margin-top:4px;">
                                    ₹<?php echo inr_format($stats_by_type['napkin']['balance'], 2); ?> Napkin &middot; ₹<?php echo inr_format($stats_by_type['diaper']['balance'], 2); ?> Diaper
                                </p>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card">
                                <h3>₹<?php echo inr_format($total_adjusted, 2); ?></h3>
                                <p>Adjusted Amount</p>
                                <p style="font-size:11.5px;color:#9ca3af;margin-top:4px;">
                                    ₹<?php echo inr_format($stats_by_type['napkin']['adjusted'], 2); ?> Napkin &middot; ₹<?php echo inr_format($stats_by_type['diaper']['adjusted'], 2); ?> Diaper
                                </p>
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
                                            <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($filter_from); ?>" max="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">To Date</label>
                                            <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($filter_to); ?>" max="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Payer Name</label>
                                            <select name="tp_id" id="tpPayerSelect" class="form-control">
                                                <option value="">All Payers</option>
                                                <?php foreach ($tps as $tp): ?>
                                                <option value="<?php echo $tp['id']; ?>" <?php echo $filter_tp == $tp['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($tp['name']); ?> (<?php echo htmlspecialchars($tp['tp_id']); ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Receiver Name</label>
                                            <select name="company_id" class="form-control">
                                                <option value="">All Receivers</option>
                                                <?php foreach ($company_profiles as $cp): ?>
                                                <option value="<?php echo $cp['id']; ?>" <?php echo $filter_company == $cp['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cp['gname']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Type</label>
                                            <select name="type_filter" class="form-control">
                                                <option value="">Napkin + Diaper</option>
                                                <option value="napkin" <?php echo $filter_type==='napkin' ? 'selected' : ''; ?>>Napkin only</option>
                                                <option value="diaper" <?php echo $filter_type==='diaper' ? 'selected' : ''; ?>>Lumi Diaper only</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-control">
                                                <option value="">All Status</option>
                                                <option value="active" <?php echo $filter_status==='active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="partially_adjusted" <?php echo $filter_status==='partially_adjusted' ? 'selected' : ''; ?>>Partially Adjusted</option>
                                                <option value="fully_adjusted" <?php echo $filter_status==='fully_adjusted' ? 'selected' : ''; ?>>Fully Adjusted</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 d-flex gap-2">
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
                                                    <th>TP Target (₹)</th>
                                                    <th>Type</th>
                                                    <th>Receiver Name</th>
                                                    <th>Date</th>
                                                    <th>Amount (₹)</th>
                                                    <th>Balance (₹)</th>
                                                    <th>Adjusted (₹)</th>
                                                    <th>Mode</th>
                                                    <th>Reference</th>
                                                    <th>Status</th>
                                                    <th>Recorded By</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($payments as $p): ?>
                                                <tr id="advPayRow<?php echo (int)$p['id']; ?>">
                                                    <td><?php echo ++$i; ?></td>
                                                    <td><?php echo htmlspecialchars($p['tp_name']); ?></td>
                                                    <td><code style="font-size:12px;"><?php echo htmlspecialchars($p['tp_code']); ?></code></td>
                                                    <td>
                                                        <?php $tgt = $tpTargets[$p['territory_partner_id']] ?? null; ?>
                                                        <?php if ($tgt !== null && $tgt > 0): ?>
                                                            <?php echo inr_format($tgt, 2); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Not set</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php $ptype = tpResolveProductType($p['product_type'] ?? null); [$ptBg, $ptFg] = tpProductTypeBadgeColors($ptype); ?>
                                                        <span style="background:<?=$ptBg?>;color:<?=$ptFg?>;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;"><?=tpProductTypeLabel($ptype)?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($p['receiver_name'] ?: '—'); ?></td>
                                                    <td><?php echo htmlspecialchars($p['payment_date']); ?></td>
                                                    <td class="text-right font-weight-bold"><?php echo inr_format($p['amount'], 2); ?></td>
                                                    <td class="text-right" style="color:#10b981;font-weight:600;"><?php echo inr_format($p['balance_amount'], 2); ?></td>
                                                    <td class="text-right"><?php echo inr_format($p['adjusted_amount'], 2); ?></td>
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
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-warning btn-action edit-btn me-1" data-id="<?php echo $p['id']; ?>" title="Edit Payment">
                                                            <i class="material-icons" style="font-size:16px;vertical-align:middle;">edit</i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger btn-action delete-btn" data-id="<?php echo $p['id']; ?>" title="Delete Payment">
                                                            <i class="material-icons" style="font-size:16px;vertical-align:middle;">delete</i>
                                                        </button>
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
    </div>
</div>

<!-- Edit Payment Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit TP Advance Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPaymentForm" method="POST" action="edit-tp-advance-payment-action.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="modal-body" id="editModalContent">
                    <div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="update_advance_payment">
                        <i class="material-icons" style="vertical-align:middle">save</i> Update Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
$(document).ready(function () {
    // Plain <select> made the ~300+ TP list impossible to find a name in
    // without scrolling the whole thing by eye — select2 adds a type-to-
    // filter search box on top of the same options/values, no server change.
    $('#tpPayerSelect').select2({ placeholder: 'All Payers', allowClear: true, width: '100%' });

    var advPayTable = $('#datatable1').DataTable({
        dom: '<"row"<"col-sm-6"l><"col-sm-6"f>><"row"<"col-sm-12"B>><"row"<"col-sm-12"tr>><"row"<"col-sm-5"i><"col-sm-7"p>>',
        buttons: [
            { extend: 'excel', text: '<i class="material-icons" style="vertical-align:middle">download</i> Excel', className: 'btn btn-success' },
            { extend: 'print', text: '<i class="material-icons" style="vertical-align:middle">print</i> Print', className: 'btn btn-info' }
        ]
    });

    // Deep-link support: ?highlight=<tp_advance_payments.id> jumps straight
    // to that row (paging to whichever DataTables page it landed on) and
    // flashes it — used when a screenshot/submission is just converted into
    // an advance payment, so the reviewer lands exactly on the new entry
    // instead of having to search for it in the full list.
    var highlightId = new URLSearchParams(window.location.search).get('highlight');
    if (highlightId) {
        var $row = $('#advPayRow' + highlightId);
        if ($row.length) {
            var rowNode = advPayTable.row($row).node();
            if (rowNode) {
                var pageInfo = advPayTable.page.info();
                var rowIndexInFullList = advPayTable.rows().indexes().toArray().findIndex(function (idx) {
                    return advPayTable.row(idx).node() === rowNode;
                });
                if (rowIndexInFullList > -1) {
                    advPayTable.page(Math.floor(rowIndexInFullList / pageInfo.length)).draw(false);
                }
            }
            setTimeout(function () {
                var target = $('#advPayRow' + highlightId);
                if (target.length) {
                    target.addClass('adv-pay-highlight');
                    target[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(function () { target.removeClass('adv-pay-highlight'); }, 3000);
                }
            }, 150);
        }
    }

    $('#datatable1').on('click', '.edit-btn', function () {
        const id = $(this).data('id');
        $('#editModalContent').html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        $('#editModal').modal('show');

        $.post('get-tp-advance-payment-edit-form.php', {
            id: id,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
        .done(function (response) {
            $('#editModalContent').html(response);
        })
        .fail(function () {
            $('#editModalContent').html('<div class="alert alert-danger">Error loading edit form. Please try again.</div>');
        });
    });

    $('#editPaymentForm').on('submit', function (e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to update this payment entry?')) {
            return;
        }

        const btn = $(this).find('[type="submit"]');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Updating...');

        $.post('edit-tp-advance-payment-action.php', $(this).serialize())
            .done(function (response) {
                btn.prop('disabled', false).html('<i class="material-icons" style="vertical-align:middle">save</i> Update Payment');

                if (response.success) {
                    alert(response.message);
                    window.location.reload();
                } else {
                    alert('Error: ' + (response.message || 'Failed to update payment'));
                }
            })
            .fail(function () {
                btn.prop('disabled', false).html('<i class="material-icons" style="vertical-align:middle">save</i> Update Payment');
                alert('Error updating payment. Please try again.');
            });
    });

    $('#datatable1').on('click', '.delete-btn', function () {
        const id = $(this).data('id');
        const btn = $(this);

        if (!confirm('Are you sure you want to delete this payment entry? This action can be undone later.')) {
            return;
        }

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.post('delete-tp-advance-payment-action.php', {
            id: id,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
        .done(function (response) {
            if (response.success) {
                alert(response.message || 'Payment deleted successfully');
                window.location.reload();
            } else {
                alert('Error: ' + (response.message || 'Failed to delete payment'));
                btn.prop('disabled', false).html('<i class="material-icons" style="font-size:16px;vertical-align:middle;">delete</i>');
            }
        })
        .fail(function () {
            alert('Error deleting payment. Please try again.');
            btn.prop('disabled', false).html('<i class="material-icons" style="font-size:16px;vertical-align:middle;">delete</i>');
        });
    });
});
</script>
</body>
</html>
