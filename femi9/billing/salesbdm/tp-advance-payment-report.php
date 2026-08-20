<?php
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

tpEnsureAdvanceWalletColumns($db_conn);

$tpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID);
$hasTps = !empty($tpIds);
$tpIdList = $hasTps ? implode(',', array_map('intval', $tpIds)) : '0';

$filter_from = $_GET['from_date'] ?? date('Y-m-01');
$filter_to   = $_GET['to_date']   ?? date('Y-m-d');
$filter_tp   = (int)($_GET['tp_id'] ?? 0);
if ($filter_tp > 0 && !in_array($filter_tp, $tpIds, true)) { $filter_tp = 0; }
$filter_status = $_GET['status'] ?? '';
$allowed_statuses = ['active','partially_adjusted','fully_adjusted',''];
if (!in_array($filter_status, $allowed_statuses, true)) $filter_status = '';
$filter_type = $_GET['type'] ?? '';
if (!in_array($filter_type, ['', 'napkin', 'diaper'], true)) $filter_type = '';

$payments = [];
$tpTargets = [];
$tpInvoiced = [];
$tps = [];
$total_count = 0; $total_amount = 0; $total_balance = 0; $total_adjusted = 0;

if ($hasTps) {
    $tps = call_rows_local($db_conn, "SELECT id, tp_id, name FROM territory_partners WHERE id IN ($tpIdList) ORDER BY name ASC", '', []);

    $where = ["tap.deleted_at IS NULL", "tap.payment_date BETWEEN ? AND ?", "tap.territory_partner_id IN ($tpIdList)"];
    $params = [$filter_from, $filter_to];
    $types = "ss";
    if ($filter_tp > 0) {
        $where[] = "tap.territory_partner_id = ?";
        $params[] = $filter_tp;
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

    $sql = "SELECT tap.*, tp.name AS tp_name, tp.tp_id AS tp_code, cg.gname AS receiver_name
            FROM tp_advance_payments tap
            JOIN territory_partners tp ON tp.id = tap.territory_partner_id
            LEFT JOIN company_godown cg ON cg.id = tap.company_id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY tap.payment_date DESC, tap.id DESC";
    $payments = call_rows_local($db_conn, $sql, $types, $params);

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

        // Invoiced amount per TP, always Napkin-only — target_amount (the
        // column this gets shown next to) has no Diaper equivalent, so
        // comparing it against a combined or Diaper-only invoiced figure
        // would silently misreport a TP's progress toward their real
        // (Napkin) target. This is deliberately independent of $filter_type,
        // which only controls which advance-payment rows are listed below.
        // Napkin match uses COALESCE since existing Napkin products carry a
        // NULL category, not the literal string 'napkin' (Diaper is the only
        // explicitly-tagged one).
        $typeWhere = " AND COALESCE(p.category,'') != 'diaper'";
        $resInv = call_rows_local($db_conn, "
            SELECT ti.territory_partner_id, COALESCE(SUM(tii.amount),0) AS inv_amt
            FROM tp_invoice_items tii
            JOIN tp_invoices ti ON ti.id = tii.tp_invoice_id
            JOIN products p ON p.id = tii.product_id
            WHERE ti.territory_partner_id IN ($idList) AND ti.invoice_date BETWEEN ? AND ?$typeWhere
            GROUP BY ti.territory_partner_id
        ", 'ss', [$filter_from, $filter_to]);
        foreach ($resInv as $ir) { $tpInvoiced[(int)$ir['territory_partner_id']] = (float)$ir['inv_amt']; }
    }

    $total_count = count($payments);
    $total_amount = array_sum(array_column($payments, 'amount'));
    $total_balance = array_sum(array_column($payments, 'balance_amount'));
    $total_adjusted = array_sum(array_column($payments, 'adjusted_amount'));
}

function call_rows_local($db, $sql, $types, $params) {
    if (!$types) {
        $r = $db->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }
    $s = $db->prepare($sql);
    if (!$s) return [];
    $s->bind_param($types, ...$params);
    $s->execute();
    $r = $s->get_result();
    $s->close();
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
$i = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Advance Payment Report : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/select2/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .mis-filter { background:#fff; border:1px solid rgba(11,11,11,0.10); border-radius:10px; padding:14px 18px; margin-bottom:20px; }
        .stats-card { background:#fff; border-radius:10px; padding:18px 20px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-left:4px solid #667eea; }
        .stats-card h3 { font-size:26px; font-weight:700; margin:0; color:#667eea; }
        .stats-card p { margin:4px 0 0 0; color:#6b7280; font-size:13px; font-weight:500; }
        .status-active { background:#d1fae5; color:#065f46; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        .status-partially { background:#fef3c7; color:#92400e; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        .status-fully { background:#dbeafe; color:#1e40af; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:8px 11px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:7px 11px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }

        @media (max-width: 768px) {
            .mis-filter form { flex-direction: column; align-items: stretch !important; }
            .mis-filter form > div { width: 100% !important; }
            .mis-filter select, .mis-filter input { width: 100% !important; }
            .stats-card { margin-bottom: 12px; }
            .mt { font-size: 12px; }
        }
        @media (max-width: 480px) {
            .page-description h1 { font-size: 20px; }
            .stats-card h3 { font-size: 20px; }
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
                                <h1>
                                    <table class="headertble">
                                        <tr>
                                            <td>TP Advance Payment Report</td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <?php if (!$hasTps): ?>
                        <div class="alert alert-info">No Territory Partners are assigned to your districts yet.</div>
                    <?php else: ?>

                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card"><h3><?php echo $total_count; ?></h3><p>Total Payments</p></div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card"><h3>&#8377;<?php echo inr_format($total_amount, 2); ?></h3><p>Total Amount</p></div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card"><h3>&#8377;<?php echo inr_format($total_balance, 2); ?></h3><p>Total Balance</p></div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="stats-card"><h3>&#8377;<?php echo inr_format($total_adjusted, 2); ?></h3><p>Adjusted Amount</p></div>
                        </div>
                    </div>

                    <div class="mis-filter">
                        <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Territory Partner</label>
                                <select name="tp_id" id="tpSelect" class="form-control form-control-sm" style="width:220px;">
                                    <option value="0">All Territory Partners</option>
                                    <?php foreach ($tps as $t): ?>
                                        <option value="<?php echo (int)$t['id']; ?>" <?php echo $filter_tp===(int)$t['id']?'selected':''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">From</label>
                                <input type="date" name="from_date" value="<?php echo htmlspecialchars($filter_from); ?>" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">To</label>
                                <input type="date" name="to_date" value="<?php echo htmlspecialchars($filter_to); ?>" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Status</label>
                                <select name="status" class="form-control form-control-sm">
                                    <option value="">All</option>
                                    <option value="active" <?php echo $filter_status==='active'?'selected':''; ?>>Active</option>
                                    <option value="partially_adjusted" <?php echo $filter_status==='partially_adjusted'?'selected':''; ?>>Partially Adjusted</option>
                                    <option value="fully_adjusted" <?php echo $filter_status==='fully_adjusted'?'selected':''; ?>>Fully Adjusted</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Type</label>
                                <select name="type" class="form-control form-control-sm">
                                    <option value="">All Products</option>
                                    <option value="napkin" <?php echo $filter_type==='napkin'?'selected':''; ?>>Napkin</option>
                                    <option value="diaper" <?php echo $filter_type==='diaper'?'selected':''; ?>>Lumi Diaper</option>
                                </select>
                            </div>
                            <div><button type="submit" class="btn btn-primary btn-sm">Apply</button></div>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-body" style="overflow-x:auto;">
                            <table class="mt">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>TP Name</th>
                                        <th>TP ID</th>
                                        <th>Type</th>
                                        <th>TP Target (&#8377;)</th>
                                        <th>Invoiced &mdash; Napkin (&#8377;)</th>
                                        <th>Receiver Name</th>
                                        <th>Date</th>
                                        <th>Amount (&#8377;)</th>
                                        <th>Balance (&#8377;)</th>
                                        <th>Adjusted (&#8377;)</th>
                                        <th>Mode</th>
                                        <th>Reference</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr><td colspan="14" class="text-muted">No advance payments in this period.</td></tr>
                                <?php else: foreach ($payments as $p): ?>
                                    <tr>
                                        <td><?php echo ++$i; ?></td>
                                        <td><?php echo htmlspecialchars($p['tp_name']); ?></td>
                                        <td><code style="font-size:12px;"><?php echo htmlspecialchars($p['tp_code']); ?></code></td>
                                        <td>
                                            <?php $_payType = tpResolveProductType($p['product_type'] ?? null); [$_tBg, $_tFg] = tpProductTypeBadgeColors($_payType); ?>
                                            <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:9px;background:<?php echo $_tBg; ?>;color:<?php echo $_tFg; ?>;"><?php echo htmlspecialchars(tpProductTypeLabel($_payType)); ?></span>
                                        </td>
                                        <td>
                                            <?php $tgt = $tpTargets[$p['territory_partner_id']] ?? null; ?>
                                            <?php if ($tgt !== null && $tgt > 0): ?>
                                                <?php echo inr_format($tgt, 2); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>&#8377;<?php echo inr_format($tpInvoiced[$p['territory_partner_id']] ?? 0, 2); ?></td>
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
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php endif; ?>
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
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/plugins/select2/js/select2.full.min.js"></script>
<script>
$('#tpSelect').select2({ width: '220px', placeholder: 'All Territory Partners' });
</script>
</body>
</html>
