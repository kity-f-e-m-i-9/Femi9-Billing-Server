<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$uid   = (int)$Login_user_IDvl;
$utype = $Login_user_TYPEvl;

$shop_id = (int)base64_decode($_GET['shop'] ?? '');
$from    = isset($_GET['from']) && $_GET['from'] ? date('Y-m-d', strtotime($_GET['from'])) : date('Y-m-01');
$to      = isset($_GET['to'])   && $_GET['to']   ? date('Y-m-d', strtotime($_GET['to']))   : date('Y-m-t');

$shop = mysqli_fetch_assoc(mysqli_query($db_conn, "
    SELECT s.*, sc.catlable
    FROM shop s LEFT JOIN shop_category sc ON sc.id = s.shop_cat
    WHERE s.id='$shop_id' AND s.onboard_userID='$uid' AND s.onboard_userTYPE='$utype' LIMIT 1
"));

if (!$shop) {
    header("Location: shop-report.php");
    exit;
}

$temp_id = mysqli_real_escape_string($db_conn, $shop['temp_id']);

// ── Invoice list for this shop, with payment status ─────────────────────────
$invoices = [];
$res = mysqli_query($db_conn, "
    SELECT * FROM user_invoice
    WHERE to_user_id='$temp_id' AND to_user_type='shop'
      AND from_user_id='$uid' AND from_user_type='$utype'
      AND `date` BETWEEN '$from' AND '$to'
    ORDER BY `date` DESC, id DESC
");
while ($inv = mysqli_fetch_assoc($res)) {
    $received = (float)(mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT COALESCE(SUM(received),0) s FROM receipt WHERE inv_id='" . $inv['inv_id'] . "'"))['s']);
    $total = (float)$inv['total'];
    $isPendingInvNumber = ($inv['inv_number'] === $inv['inv_id']);

    if ($total <= 0 || (float)$inv['sub_total'] <= 0) {
        $status = 'incomplete';
    } elseif ($received <= 0) {
        $status = 'not_paid';
    } elseif (($received + 0.01) >= $total) {
        $status = 'fully_paid';
    } else {
        $status = 'partially_paid';
    }

    $inv['received_amt']     = $received;
    $inv['due_amt']          = max(0, $total - $received);
    $inv['status']           = $status;
    $inv['inv_number_disp']  = $isPendingInvNumber ? 'Pending' : $inv['inv_number'];
    $invoices[] = $inv;
}

$total_billed   = array_sum(array_map(fn($i) => (float)$i['sub_total'] > 0 ? (float)$i['total'] : 0, $invoices));
$total_received = array_sum(array_column($invoices, 'received_amt'));
$total_due      = max(0, $total_billed - $total_received);
$total_units    = (int)(mysqli_fetch_array(mysqli_query($db_conn, "
    SELECT COALESCE(SUM(qty),0) q FROM user_invoice_items
    WHERE to_user_id='$temp_id' AND to_user_type='shop'
      AND from_user_id='$uid' AND from_user_type='$utype'
      AND `date` BETWEEN '$from' AND '$to'
"))['q']);

// ── Payment / receipt history for this shop's invoices in range ─────────────
$inv_ids = array_map(fn($i) => $i['inv_id'], $invoices);
$receipts = [];
if (!empty($inv_ids)) {
    $inv_ids_esc = array_map(fn($v) => "'" . mysqli_real_escape_string($db_conn, $v) . "'", $inv_ids);
    $in_clause = implode(',', $inv_ids_esc);
    $rres = mysqli_query($db_conn, "SELECT * FROM receipt WHERE inv_id IN ($in_clause) ORDER BY date DESC, id DESC");
    while ($r = mysqli_fetch_assoc($rres)) { $receipts[] = $r; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shop Report — <?php echo htmlspecialchars($shop['name']); ?> : <?php echo $business_name; ?></title>
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
            --ink: #1a1d29; --ink-soft: #5c6072; --ink-faint: #9598a6;
            --line: #eceef4; --surface: #ffffff; --canvas: #f4f5fa;
            --indigo: #4338ca; --indigo-soft: #eef0fd;
            --teal: #0d9488; --teal-soft: #e6f7f5;
            --amber: #d97706; --amber-soft: #fef3e2;
            --rose: #dc2626; --rose-soft: #fdeaea;
            --green: #16a34a; --green-soft: #e8f8ed;
            --shadow-sm: 0 1px 2px rgba(20,20,43,.04), 0 1px 1px rgba(20,20,43,.03);
        }
        body { background: var(--canvas); }
        .container-fluid { max-width: 1440px; }
        .back-link { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; font-weight: 600;
            color: var(--ink-soft); text-decoration: none; margin-bottom: 12px; }
        .back-link:hover { color: var(--indigo); text-decoration: none; }
        .back-link .material-icons-outlined { font-size: 16px; }
        .shop-header { background: var(--surface); border: 1px solid var(--line); border-radius: 14px;
            padding: 20px 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm);
            display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .shop-avatar { width: 52px; height: 52px; border-radius: 14px; background: var(--indigo-soft); color: var(--indigo);
            display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 800; flex-shrink: 0; }
        .shop-name { font-size: 19px; font-weight: 800; color: var(--ink); }
        .shop-meta { font-size: 13px; color: var(--ink-faint); margin-top: 2px; }
        .shop-meta span { margin-right: 14px; }
        .kpi-card { border-radius: 14px; padding: 16px 18px; background: var(--surface);
            border: 1px solid var(--line); box-shadow: var(--shadow-sm); height: 100%; }
        .kpi-card .kpi-icon-chip { width: 34px; height: 34px; border-radius: 9px; display: inline-flex;
            align-items: center; justify-content: center; margin-bottom: 10px; }
        .kpi-card .kpi-icon-chip .material-icons-outlined { font-size: 18px; }
        .kpi-card .kpi-title { font-size: 11.5px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .4px; color: var(--ink-faint); }
        .kpi-card .kpi-value { font-size: 22px; font-weight: 800; margin-top: 3px; color: var(--ink); }
        .kpi-card .kpi-sub { font-size: 12px; margin-top: 6px; color: var(--ink-faint); }
        .chip-indigo { background: var(--indigo-soft); color: var(--indigo); }
        .chip-teal   { background: var(--teal-soft); color: var(--teal); }
        .chip-amber  { background: var(--amber-soft); color: var(--amber); }
        .chip-rose   { background: var(--rose-soft); color: var(--rose); }
        .chip-green  { background: var(--green-soft); color: var(--green); }
        .card { border-radius: 14px !important; border: 1px solid var(--line) !important; box-shadow: var(--shadow-sm) !important; }
        .card-header { background: transparent !important; border-bottom: 1px solid var(--line) !important;
            padding: 16px 20px !important; display: flex; align-items: center; gap: 10px; }
        .card-header .card-title { font-size: 14.5px !important; font-weight: 700 !important; color: var(--ink); margin: 0; }
        .card-header .hdr-icon { width: 28px; height: 28px; border-radius: 8px; display: inline-flex;
            align-items: center; justify-content: center; flex-shrink: 0; }
        .card-header .hdr-icon .material-icons-outlined { font-size: 16px; }
        .card-body { padding: 18px 20px !important; }
        .mis-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .mis-table th { background: var(--canvas); font-weight: 700; font-size: 11.5px; text-transform: uppercase;
            letter-spacing: .3px; color: var(--ink-soft); padding: 10px 14px; text-align: left; }
        .mis-table th:first-child { border-radius: 8px 0 0 8px; }
        .mis-table th:last-child { border-radius: 0 8px 8px 0; }
        .mis-table td { padding: 10px 14px; border-bottom: 1px solid var(--line); vertical-align: middle; color: var(--ink); }
        .mis-table tbody tr:last-child td { border-bottom: none; }
        .mis-table tbody tr:hover td { background: var(--indigo-soft); }
        .badge-rev { background: var(--green-soft); color: var(--green); padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .badge-due { background: var(--rose-soft); color: var(--rose); padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .status-badge { padding: 4px 11px; border-radius: 20px; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
        .badge-paid    { background: var(--green-soft); color: var(--green); }
        .badge-partial { background: var(--amber-soft); color: var(--amber); }
        .badge-unpaid  { background: var(--rose-soft); color: var(--rose); }
        .badge-none    { background: var(--canvas); color: var(--ink-faint); }
        .empty-state { text-align: center; padding: 36px 20px; color: var(--ink-faint); }
        .empty-state .material-icons-outlined { font-size: 32px; opacity: .4; display: block; margin: 0 auto 8px; }
        @media(max-width: 768px) { .kpi-card .kpi-value { font-size: 18px; } }
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

                    <a href="shop-report.php?from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>" class="back-link">
                        <i class="material-icons-outlined">arrow_back</i> Back to Shop Report
                    </a>

                    <!-- ── SHOP HEADER ──────────────────────────────────────── -->
                    <div class="shop-header">
                        <div class="shop-avatar"><?php echo strtoupper(substr($shop['name'], 0, 1)); ?></div>
                        <div>
                            <div class="shop-name"><?php echo htmlspecialchars(ucwords($shop['name'])); ?></div>
                            <div class="shop-meta">
                                <span><i class="material-icons-outlined" style="font-size:14px;vertical-align:-2px;">phone</i> <?php echo htmlspecialchars($shop['country_code']); ?> <?php echo htmlspecialchars($shop['mobile_number']); ?></span>
                                <span><i class="material-icons-outlined" style="font-size:14px;vertical-align:-2px;">badge</i> ID: <?php echo htmlspecialchars($shop['useridtext']); ?></span>
                                <?php if (!empty($shop['catlable'])): ?><span><i class="material-icons-outlined" style="font-size:14px;vertical-align:-2px;">category</i> <?php echo htmlspecialchars($shop['catlable']); ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div style="margin-left:auto;">
                            <form method="get" style="display:flex;gap:8px;align-items:flex-end;">
                                <input type="hidden" name="shop" value="<?php echo htmlspecialchars($_GET['shop'] ?? ''); ?>">
                                <div>
                                    <label style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--ink-faint);display:block;margin-bottom:3px;">From</label>
                                    <input type="date" name="from" class="form-control form-control-sm" value="<?php echo $from; ?>" style="width:145px;">
                                </div>
                                <div>
                                    <label style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--ink-faint);display:block;margin-bottom:3px;">To</label>
                                    <input type="date" name="to" class="form-control form-control-sm" value="<?php echo $to; ?>" style="width:145px;">
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm" style="border-radius:8px;">Apply</button>
                            </form>
                        </div>
                    </div>

                    <!-- ── KPI SUMMARY ──────────────────────────────────────── -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-indigo"><i class="material-icons-outlined">receipt_long</i></span>
                                <div class="kpi-title">Invoices</div>
                                <div class="kpi-value"><?php echo count($invoices); ?></div>
                                <div class="kpi-sub"><?php echo inr_format($total_units, 0); ?> units sold</div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-teal"><i class="material-icons-outlined">payments</i></span>
                                <div class="kpi-title">Total Billed</div>
                                <div class="kpi-value">&#x20B9;<?php echo inr_format($total_billed, 0); ?></div>
                                <div class="kpi-sub">in selected range</div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-green"><i class="material-icons-outlined">check_circle</i></span>
                                <div class="kpi-title">Received</div>
                                <div class="kpi-value">&#x20B9;<?php echo inr_format($total_received, 0); ?></div>
                                <div class="kpi-sub"><?php echo $total_billed > 0 ? round($total_received / $total_billed * 100, 1) : 0; ?>% collected</div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-rose"><i class="material-icons-outlined">hourglass_bottom</i></span>
                                <div class="kpi-title">Outstanding Due</div>
                                <div class="kpi-value">&#x20B9;<?php echo inr_format($total_due, 0); ?></div>
                                <div class="kpi-sub">balance to collect</div>
                            </div>
                        </div>
                    </div>

                    <!-- ── INVOICE LIST ─────────────────────────────────────── -->
                    <div class="row">
                        <div class="col-xl-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <span class="hdr-icon chip-indigo"><i class="material-icons-outlined">receipt_long</i></span>
                                    <h5 class="card-title">Invoices</h5>
                                </div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($invoices)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">receipt_long</i>No invoices for this shop in the selected range.</div>
                                    <?php else: ?>
                                    <table class="mis-table">
                                        <thead>
                                            <tr>
                                                <th>Invoice #</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Received</th>
                                                <th>Due</th>
                                                <th>Status</th>
                                                <th>Print</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($invoices as $inv): ?>
                                            <tr>
                                                <td><b><?php echo htmlspecialchars($inv['inv_number_disp']); ?></b></td>
                                                <td><?php echo date('d M Y', strtotime($inv['date'])); ?></td>
                                                <td>&#x20B9;<?php echo inr_format($inv['total'], 2); ?></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo inr_format($inv['received_amt'], 2); ?></span></td>
                                                <td><?php if ($inv['due_amt'] > 0): ?><span class="badge-due">&#x20B9;<?php echo inr_format($inv['due_amt'], 2); ?></span><?php else: ?>—<?php endif; ?></td>
                                                <td>
                                                    <?php if ($inv['status'] === 'fully_paid'): ?>
                                                        <span class="status-badge badge-paid">Fully Paid</span>
                                                    <?php elseif ($inv['status'] === 'partially_paid'): ?>
                                                        <span class="status-badge badge-partial">Partial</span>
                                                    <?php elseif ($inv['status'] === 'not_paid'): ?>
                                                        <span class="status-badge badge-unpaid">Not Paid</span>
                                                    <?php else: ?>
                                                        <span class="status-badge badge-none">Incomplete</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ((float)$inv['sub_total'] > 0): ?>
                                                    <a href="shop-invoice-print.php?invoiceid=<?php echo base64_encode($inv['inv_id']); ?>" title="Print"><img src="../../assets/images/print32.png" style="width:22px;"/></a>
                                                    <?php else: ?>—<?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── PAYMENT / RECEIPT HISTORY ────────────────────────── -->
                    <div class="row">
                        <div class="col-xl-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <span class="hdr-icon chip-green"><i class="material-icons-outlined">account_balance_wallet</i></span>
                                    <h5 class="card-title">Payment History</h5>
                                </div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($receipts)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">account_balance_wallet</i>No payments recorded for this shop in the selected range.</div>
                                    <?php else: ?>
                                    <table class="mis-table">
                                        <thead>
                                            <tr>
                                                <th>Receipt ID</th>
                                                <th>Invoice #</th>
                                                <th>Date</th>
                                                <th>Amount Received</th>
                                                <th>Method</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($receipts as $r): ?>
                                            <tr>
                                                <td><small><?php echo htmlspecialchars($r['receiptid']); ?></small></td>
                                                <td><?php echo htmlspecialchars($r['inv_id']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($r['date'])); ?></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo inr_format($r['received'], 2); ?></span></td>
                                                <td><?php echo htmlspecialchars($r['receipt_method'] ?? '—'); ?></td>
                                                <td><?php echo htmlspecialchars($r['receipt_remarks'] ?? '—'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
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
</body>
</html>
