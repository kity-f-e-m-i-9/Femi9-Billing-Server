<?php
// "Pending Amount" — every Territory Partner assigned to this Sales BDM,
// with Target Amount, how much Napkin advance they've paid, the Balance
// (Target − Advance Paid) still pending, and Total Invoice Amount — same
// Target/Advance/Invoice logic as dashboard.php's own "Advance from
// Company — by TP" section (district-tree scoped Target, Napkin-only
// Advance/Invoice), just as its own dedicated page with a From/To filter.
include("checksession.php");
include("config.php");
require_once("include/BdmTpScope.php");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

tpEnsureAdvanceWalletColumns($db_conn);

$tpIds = getBdmAssignedTpIds($db_conn, (int)$salesBdmID);
$hasTps = !empty($tpIds);
$tpIdListAll = $hasTps ? implode(',', array_map('intval', $tpIds)) : '0';

$tpNameMap = [];
if ($hasTps) {
    $tpRows = $db_conn->query("SELECT id, name FROM territory_partners WHERE id IN ($tpIdListAll) ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
    foreach ($tpRows as $r) { $tpNameMap[(int)$r['id']] = $r['name']; }
}

// A specific TP picked from the filter dropdown narrows the table to just
// that one TP — 0 (or anything not actually assigned to this BDM) means
// "all of my TPs", same convention as dashboard.php's own filter.
$filter_tp_id = (int)($_GET['tp_id'] ?? 0);
if ($filter_tp_id > 0 && in_array($filter_tp_id, $tpIds, true)) {
    $tpIdList = (string)$filter_tp_id;
} else {
    $filter_tp_id = 0;
    $tpIdList = $tpIdListAll;
}

// ── Date range & presets — same UX as dashboard.php's own filter ───────────
$preset = $_GET['preset'] ?? 'month';
$today  = date('Y-m-d');
switch ($preset) {
    case 'today': $df = $today; $dt = $today; break;
    case 'week':  $df = date('Y-m-d', strtotime('monday this week')); $dt = date('Y-m-d', strtotime('sunday this week')); break;
    case 'year':  $df = date('Y-01-01'); $dt = date('Y-12-31'); break;
    default:      $df = date('Y-m-01'); $dt = date('Y-m-t');
}
$current_from_date = isset($_GET['from']) && $_GET['from'] ? date('Y-m-d', strtotime($_GET['from'])) : $df;
$current_to_date   = isset($_GET['to'])   && $_GET['to']   ? date('Y-m-d', strtotime($_GET['to']))   : $dt;
if (strtotime($current_from_date) > strtotime($current_to_date)) {
    [$current_from_date, $current_to_date] = [$current_to_date, $current_from_date];
}

$filter_district = trim($_GET['district'] ?? '');
$districtOptions = getBdmAssignedDistrictNames($db_conn, (int)$salesBdmID);
sort($districtOptions);

$rows = [];

if ($hasTps) {

    $districtDepthRow = $db_conn->query("SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1")->fetch_assoc();
    $districtDepth = (int)($districtDepthRow['depth'] ?? 0);
    $districtNames = getBdmAssignedDistrictNames($db_conn, (int)$salesBdmID);

    if ($districtDepth && !empty($districtNames)) {
        $dn = array_map(fn($n) => mb_strtolower(trim($n)), $districtNames);
        $ph = implode(',', array_fill(0, count($dn), '?'));
        $dtTypes = 'i' . str_repeat('s', count($dn));
        $dtParams = array_merge([$districtDepth], $dn);
        $districtTreeSql = "WITH RECURSIVE district_tree AS (
                    SELECT id FROM partner_location_nodes WHERE depth = ? AND LOWER(TRIM(name)) IN ($ph)
                    UNION ALL
                    SELECT n.id FROM partner_location_nodes n JOIN district_tree dt ON n.parent_id = dt.id
                 ) ";

        $stmt = $db_conn->prepare(
            $districtTreeSql .
            "SELECT tp.id, tp.tp_id, tp.name,
                    COALESCE(NULLIF(tp.assigned_district,''), tp.branch_district) AS district,
                    COALESCE(SUM(CASE WHEN pln.id IN (SELECT id FROM district_tree) THEN pln.target_amount END), 0) AS target
             FROM territory_partners tp
             LEFT JOIN territory_partner_locations tpl ON tpl.territory_partner_id = tp.id
             LEFT JOIN partner_location_nodes pln ON pln.id = tpl.location_id
             WHERE tp.id IN ($tpIdList) AND tp.deleted_at IS NULL
             GROUP BY tp.id
             ORDER BY tp.name ASC"
        );
        $stmt->bind_param($dtTypes, ...$dtParams);
        $stmt->execute();
        $tpBase = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $paidByTp = [];
        $stmtP = $db_conn->prepare("
            SELECT territory_partner_id, COALESCE(SUM(amount),0) AS paid
            FROM tp_advance_payments
            WHERE territory_partner_id IN ($tpIdList) AND product_type = 'napkin' AND deleted_at IS NULL
              AND payment_date BETWEEN ? AND ?
            GROUP BY territory_partner_id
        ");
        $stmtP->bind_param('ss', $current_from_date, $current_to_date);
        $stmtP->execute();
        foreach ($stmtP->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $paidByTp[(int)$r['territory_partner_id']] = (float)$r['paid'];
        }
        $stmtP->close();

        $invoiceByTp = [];
        $stmtI = $db_conn->prepare("
            SELECT ti.territory_partner_id AS tp_id, COALESCE(SUM(tii.amount),0) AS amt
            FROM tp_invoice_items tii JOIN tp_invoices ti ON ti.id = tii.tp_invoice_id
            JOIN products p ON p.id = tii.product_id
            WHERE ti.territory_partner_id IN ($tpIdList) AND ti.invoice_date BETWEEN ? AND ?
              AND COALESCE(p.category,'') != 'diaper'
            GROUP BY ti.territory_partner_id
        ");
        $stmtI->bind_param('ss', $current_from_date, $current_to_date);
        $stmtI->execute();
        foreach ($stmtI->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $invoiceByTp[(int)$r['tp_id']] = (float)$r['amt'];
        }
        $stmtI->close();

        foreach ($tpBase as $r) {
            $tid = (int)$r['id'];
            $target = (float)$r['target'];
            $paid = $paidByTp[$tid] ?? 0.0;
            $district = $r['district'] ?: '—';
            if ($filter_district !== '' && $district !== $filter_district) continue;
            $rows[] = [
                'tp_id' => $r['tp_id'], 'name' => $r['name'], 'district' => $district,
                'target' => $target, 'advance_paid' => $paid, 'balance' => $target - $paid,
                'invoice_amount' => $invoiceByTp[$tid] ?? 0.0,
            ];
        }
    }
}

$total_target = array_sum(array_column($rows, 'target'));
$total_advance = array_sum(array_column($rows, 'advance_paid'));
$total_balance = array_sum(array_column($rows, 'balance'));
$total_invoice = array_sum(array_column($rows, 'invoice_amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pending Amount : <?php echo $business_name; ?></title>
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
        :root {
            --surface-1: #ffffff; --page-plane: #f7f7f6; --text-primary: #0b0b0b;
            --text-secondary: #52514e; --text-muted: #898781; --gridline: #e1e0d9; --border: rgba(11,11,11,0.10);
            --blue: #2a78d6; --blue-tint: #eaf2fc; --good: #0ca30c; --good-tint: #e5f7e5;
            --critical: #d03b3b; --critical-tint: #fbe6e6;
        }
        body { background: var(--page-plane); }
        .pa-card { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); border:none; margin-bottom:20px; }
        .mis-filter { background: var(--surface-1); border: 1px solid var(--border); border-radius: 10px; padding: 14px 18px; margin-bottom: 14px; }
        .preset-btn { padding:4px 13px; border-radius:20px; border:1.5px solid var(--blue); color:var(--blue); background:var(--surface-1); font-size:12px; cursor:pointer; text-decoration:none; display:inline-block; }
        .preset-btn.active, .preset-btn:hover { background:var(--blue); color:#fff; border-color:var(--blue); }
        @media (max-width: 700px) {
            .mis-filter form { flex-direction: column; align-items: stretch !important; }
            .mis-filter form > div { width: 100% !important; }
            .mis-filter select, .mis-filter input { width: 100% !important; }
            .mis-filter form > div[style*="margin-left:auto"] { margin-left: 0 !important; justify-content: flex-start !important; }
        }
        table.dataTable thead th { background:#f8f9fa; font-weight:600; }
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
                                    <table class="headertble" style="width:100%">
                                        <tr>
                                            <td>Pending Amount — Your Territory Partners</td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($_companyBridgeView)): ?>
                        <div class="alert alert-info" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
                            <span><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">visibility</i> Viewing <b><?php echo htmlspecialchars($result_LoGuserDtails['bdm_name'] ?? ''); ?>'s</b> Pending Amount (read-only).</span>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($tpIds)): ?>
                        <div class="alert alert-info">No Territory Partners are assigned to your districts yet.</div>
                    <?php else: ?>

                    <div class="mis-filter">
                        <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">Territory Partner</label>
                                <select name="tp_id" class="form-control form-control-sm" style="width:200px;" onchange="this.form.submit()">
                                    <option value="0">All Territory Partners</option>
                                    <?php foreach ($tpNameMap as $tid => $tname): ?>
                                        <option value="<?php echo $tid; ?>" <?php echo $filter_tp_id===$tid?'selected':''; ?>><?php echo htmlspecialchars($tname); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">District</label>
                                <select name="district" class="form-control form-control-sm" style="width:170px;" onchange="this.form.submit()">
                                    <option value="">All Districts</option>
                                    <?php foreach ($districtOptions as $_dn): ?>
                                        <option value="<?php echo htmlspecialchars($_dn); ?>" <?php echo $filter_district===$_dn?'selected':''; ?>><?php echo htmlspecialchars($_dn); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">From</label>
                                <input type="date" name="from" value="<?php echo htmlspecialchars($current_from_date); ?>" class="form-control form-control-sm" style="width:145px;">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">To</label>
                                <input type="date" name="to" value="<?php echo htmlspecialchars($current_to_date); ?>" class="form-control form-control-sm" style="width:145px;">
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            </div>
                            <div style="margin-left:auto;display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap;">
                                <?php
                                $presetQs = $filter_tp_id > 0 ? "&tp_id={$filter_tp_id}" : '';
                                $presetQs .= $filter_district !== '' ? '&district=' . urlencode($filter_district) : '';
                                ?>
                                <a href="?preset=today<?php echo $presetQs; ?>" class="preset-btn <?php echo $preset==='today'?'active':''; ?>">Today</a>
                                <a href="?preset=week<?php echo $presetQs; ?>" class="preset-btn <?php echo $preset==='week'?'active':''; ?>">This Week</a>
                                <a href="?preset=month<?php echo $presetQs; ?>" class="preset-btn <?php echo $preset==='month'?'active':''; ?>">This Month</a>
                                <a href="?preset=year<?php echo $presetQs; ?>" class="preset-btn <?php echo $preset==='year'?'active':''; ?>">This Year</a>
                            </div>
                        </form>
                        <div style="font-size:12px;color:#888;margin-top:7px;">
                            <?php if ($filter_tp_id>0): ?>
                            Filtered by TP: <b><?php echo htmlspecialchars($tpNameMap[$filter_tp_id] ?? ''); ?></b> &nbsp;|&nbsp;
                            <?php endif; ?>
                            <?php if ($filter_district!==''): ?>
                            Filtered by District: <b><?php echo htmlspecialchars($filter_district); ?></b> &nbsp;|&nbsp;
                            <?php endif; ?>
                            Period: <b><?php echo date('d M Y', strtotime($current_from_date)); ?></b> to <b><?php echo date('d M Y', strtotime($current_to_date)); ?></b> &nbsp;|&nbsp;
                            <?php echo count($rows); ?> TP(s) shown
                        </div>
                    </div>

                    <div class="card pa-card">
                        <div class="card-body">
                            <?php if (empty($rows)): ?>
                                <div class="alert alert-info mb-0">No Territory Partner data found for this date range.</div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table id="paTable" class="table table-hover table-sm" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>S.No</th>
                                            <th>TP ID</th>
                                            <th>TP Name</th>
                                            <th>District</th>
                                            <th>Target Amount</th>
                                            <th>Advance Amount</th>
                                            <th>Balance Amount</th>
                                            <th>Total Invoice Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $sr = 1; foreach ($rows as $r): $bal = $r['balance']; ?>
                                        <tr>
                                            <td><?php echo $sr++; ?></td>
                                            <td><?php echo htmlspecialchars($r['tp_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($r['district'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo $r['target'] > 0 ? '₹' . inr_format($r['target'], 2) : '–'; ?></td>
                                            <td>₹<?php echo inr_format($r['advance_paid'], 2); ?></td>
                                            <td style="color:<?php echo $bal > 0 ? '#dc2626' : '#16a34a'; ?>;font-weight:600;">
                                                <?php echo $bal > 0 ? '−' : '+'; ?>₹<?php echo inr_format(abs($bal), 2); ?>
                                            </td>
                                            <td>₹<?php echo inr_format($r['invoice_amount'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr style="font-weight:700;border-top:2px solid var(--gridline);background:#f7f7f6;">
                                            <td colspan="4" style="text-align:right;">Total</td>
                                            <td>₹<?php echo inr_format($total_target, 2); ?></td>
                                            <td>₹<?php echo inr_format($total_advance, 2); ?></td>
                                            <td style="color:<?php echo $total_balance > 0 ? '#dc2626' : '#16a34a'; ?>;">
                                                <?php echo $total_balance > 0 ? '−' : '+'; ?>₹<?php echo inr_format(abs($total_balance), 2); ?>
                                            </td>
                                            <td>₹<?php echo inr_format($total_invoice, 2); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <?php endif; ?>
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
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="../../assets/js/main.min.js"></script>
<script>
$(function(){
    $('#paTable').DataTable({
        order: [],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100]
    });
});
</script>
</body>
</html>
