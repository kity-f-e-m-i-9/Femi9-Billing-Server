<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$uid    = (int)$Login_user_IDvl;
$utype  = $Login_user_TYPEvl;
$my_tpid = $Login_user_tp_id;

// ── Date range filter (defaults to this month, like mis-report.php) ────────
$preset = $_GET['preset'] ?? 'month';
$today  = date('Y-m-d');
switch ($preset) {
    case 'today': $default_from = $today; $default_to = $today; break;
    case 'week':
        $default_from = date('Y-m-d', strtotime('monday this week'));
        $default_to   = date('Y-m-d', strtotime('sunday this week')); break;
    case 'year':  $default_from = date('Y-01-01'); $default_to = date('Y-12-31'); break;
    case 'all':   $default_from = '2000-01-01'; $default_to = $today; break;
    default:      $default_from = date('Y-m-01'); $default_to = date('Y-m-t');
}
$from = isset($_GET['from']) && $_GET['from'] ? date('Y-m-d', strtotime($_GET['from'])) : $default_from;
$to   = isset($_GET['to'])   && $_GET['to']   ? date('Y-m-d', strtotime($_GET['to']))   : $default_to;

// ═══════════════════════════════════════════════════════════════════════════
// 1. MY DOWNLINE — TPs who chose this TP as their referrer
// ═══════════════════════════════════════════════════════════════════════════
$my_tpid_esc = mysqli_real_escape_string($db_conn, $my_tpid);
$referrals = [];
$res = mysqli_query($db_conn, "
    SELECT m.id, m.tp_id, m.name, m.mobile, m.is_active, m.referral_percentage, m.created_at,
           COUNT(tpi.id)                        AS invoice_count,
           COALESCE(SUM(tpi.total_amount),0)     AS total_purchase,
           MAX(tpi.invoice_date)                 AS last_invoice_date
    FROM territory_partners m
    LEFT JOIN tp_invoices tpi
           ON tpi.territory_partner_id = m.id
          AND tpi.invoice_date BETWEEN '" . mysqli_real_escape_string($db_conn, $from) . "' AND '" . mysqli_real_escape_string($db_conn, $to) . "'
    WHERE m.referral_type = 'TP' AND m.referral_id = '$my_tpid_esc'
    GROUP BY m.id
    ORDER BY total_purchase DESC, m.name ASC
");
while ($row = mysqli_fetch_assoc($res)) {
    // Location target for this downline TP, to show whether they're on track
    $tgt = (float)(mysqli_fetch_array(mysqli_query($db_conn, "
        SELECT COALESCE(SUM(n.target_amount),0) FROM territory_partner_locations tpl
        JOIN partner_location_nodes n ON n.id = tpl.location_id
        WHERE tpl.territory_partner_id='" . (int)$row['id'] . "'"))[0] ?? 0);
    $row['target'] = $tgt;
    $row['target_pct'] = $tgt > 0 ? min(round((float)$row['total_purchase'] / $tgt * 100, 1), 999) : 0;
    $referrals[] = $row;
}
$total_referrals   = count($referrals);
$active_referrals  = count(array_filter($referrals, fn($r) => (int)$r['is_active'] === 1));
$total_downline_purchase = array_sum(array_column($referrals, 'total_purchase'));
$total_downline_invoices = array_sum(array_column($referrals, 'invoice_count'));

// ═══════════════════════════════════════════════════════════════════════════
// 2. EARNINGS — commission actually credited to this TP's wallet from referrals
// ═══════════════════════════════════════════════════════════════════════════
$uid_esc = mysqli_real_escape_string($db_conn, (string)$uid);
$utype_esc = mysqli_real_escape_string($db_conn, $utype);
$from_esc = mysqli_real_escape_string($db_conn, $from);
$to_esc   = mysqli_real_escape_string($db_conn, $to);

$earnings = [];
$eres = mysqli_query($db_conn, "
    SELECT * FROM wallet_monthly_sls_report
    WHERE refer_by_usertype='$utype_esc' AND refer_by_userid='$uid_esc'
      AND commission_type='Refferral'
      AND from_date BETWEEN '$from_esc' AND '$to_esc'
    ORDER BY from_date DESC, id DESC
");
while ($e = mysqli_fetch_assoc($eres)) {
    // Resolve which downline TP this credit came from (territory_partners.id)
    $tpRow = mysqli_fetch_assoc(mysqli_query($db_conn,
        "SELECT tp_id, name, mobile FROM territory_partners WHERE id='" . (int)$e['user_id'] . "' LIMIT 1"));
    $e['tp_name']   = $tpRow['name']   ?? 'Unknown TP';
    $e['tp_code']   = $tpRow['tp_id']  ?? '—';
    $e['tp_mobile'] = $tpRow['mobile'] ?? '';
    $earnings[] = $e;
}
$total_earnings      = array_sum(array_column($earnings, 'commission_amount'));
$earnings_count      = count($earnings);

// All-time earnings (for the KPI, independent of the date filter above)
$all_time_earnings = (float)(mysqli_fetch_array(mysqli_query($db_conn,
    "SELECT COALESCE(SUM(commission_amount),0) FROM wallet_monthly_sls_report
     WHERE refer_by_usertype='$utype_esc' AND refer_by_userid='$uid_esc' AND commission_type='Refferral'"))[0] ?? 0);

// Per-downline-TP earnings totals (all-time), attached to each referral row below
$leader_res = mysqli_query($db_conn, "
    SELECT user_id, COALESCE(SUM(commission_amount),0) AS earned, COUNT(*) AS credit_count
    FROM wallet_monthly_sls_report
    WHERE refer_by_usertype='$utype_esc' AND refer_by_userid='$uid_esc' AND commission_type='Refferral'
    GROUP BY user_id
");
$earnings_by_tp_id = [];
while ($lr = mysqli_fetch_assoc($leader_res)) {
    $earnings_by_tp_id[(int)$lr['user_id']] = ['earned' => (float)$lr['earned'], 'credit_count' => (int)$lr['credit_count']];
}

foreach ($referrals as &$rf) {
    $rf['lifetime_earned']  = $earnings_by_tp_id[(int)$rf['id']]['earned'] ?? 0;
    $rf['lifetime_credits'] = $earnings_by_tp_id[(int)$rf['id']]['credit_count'] ?? 0;
}
unset($rf);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Referrals : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
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
            --violet: #7c3aed; --violet-soft: #f3ecfe;
            --green: #16a34a; --green-soft: #e8f8ed;
            --shadow-sm: 0 1px 2px rgba(20,20,43,.04), 0 1px 1px rgba(20,20,43,.03);
            --shadow-hover: 0 10px 28px rgba(24,24,60,.10), 0 2px 6px rgba(24,24,60,.05);
        }
        body { background: var(--canvas); }
        .container-fluid { max-width: 1440px; }
        .mis-page-title { font-size: 22px; font-weight: 800; color: var(--ink); letter-spacing: -.3px;
            display: flex; align-items: center; gap: 10px; margin-bottom: 2px; }
        .mis-page-title .icon-chip { width: 38px; height: 38px; border-radius: 11px; display: inline-flex;
            align-items: center; justify-content: center; background: var(--violet-soft); color: var(--violet); flex-shrink: 0; }
        .mis-page-sub { font-size: 13px; color: var(--ink-faint); margin: 0 0 20px 48px; }
        .mis-filter-bar { background: var(--surface); border: 1px solid var(--line); border-radius: 14px;
            padding: 16px 20px; margin-bottom: 24px; box-shadow: var(--shadow-sm); }
        .mis-filter-bar label { font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .4px; color: var(--ink-faint); display: block; margin-bottom: 4px; }
        .mis-filter-bar .form-control-sm { border-radius: 8px; border: 1px solid #dfe1eb; font-size: 13px; }
        .mis-filter-bar .btn-primary { background: var(--indigo); border-color: var(--indigo); border-radius: 8px;
            font-size: 13px; font-weight: 600; padding: 6px 16px; }
        .preset-btn { padding: 6px 15px; border-radius: 20px; border: 1px solid #e2e4ee;
            color: var(--ink-soft); background: var(--surface); font-size: 12.5px; font-weight: 600;
            cursor: pointer; text-decoration: none; }
        .preset-btn:hover { border-color: var(--indigo); color: var(--indigo); text-decoration: none; }
        .preset-btn.active { background: var(--indigo); color: #fff; border-color: var(--indigo); }
        .kpi-card { border-radius: 14px; padding: 16px 18px; background: var(--surface);
            border: 1px solid var(--line); box-shadow: var(--shadow-sm); position: relative; height: 100%;
            transition: transform .15s ease, box-shadow .15s ease; }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); }
        .kpi-card .kpi-icon-chip { width: 34px; height: 34px; border-radius: 9px; display: inline-flex;
            align-items: center; justify-content: center; margin-bottom: 10px; }
        .kpi-card .kpi-icon-chip .material-icons-outlined { font-size: 18px; }
        .kpi-card .kpi-title { font-size: 11.5px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .4px; color: var(--ink-faint); }
        .kpi-card .kpi-value { font-size: 24px; font-weight: 800; margin-top: 3px; color: var(--ink); }
        .kpi-card .kpi-sub { font-size: 12px; margin-top: 7px; color: var(--ink-faint); font-weight: 500; }
        .chip-indigo { background: var(--indigo-soft); color: var(--indigo); }
        .chip-teal   { background: var(--teal-soft); color: var(--teal); }
        .chip-amber  { background: var(--amber-soft); color: var(--amber); }
        .chip-rose   { background: var(--rose-soft); color: var(--rose); }
        .chip-violet { background: var(--violet-soft); color: var(--violet); }
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
        .badge-qty { background: var(--indigo-soft); color: var(--indigo); padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .status-badge { padding: 4px 11px; border-radius: 20px; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
        .badge-paid    { background: var(--green-soft); color: var(--green); }
        .badge-partial { background: var(--amber-soft); color: var(--amber); }
        .badge-unpaid  { background: var(--rose-soft); color: var(--rose); }
        .badge-none    { background: var(--canvas); color: var(--ink-faint); }
        .progress-bar-mis { height: 7px; border-radius: 6px; background: var(--canvas); overflow: hidden; min-width: 70px; }
        .progress-fill { height: 100%; border-radius: 6px; }
        .empty-state { text-align: center; padding: 36px 20px; color: var(--ink-faint); }
        .empty-state .material-icons-outlined { font-size: 32px; opacity: .4; display: block; margin: 0 auto 8px; }
        .tp-avatar { width: 38px; height: 38px; border-radius: 11px; background: linear-gradient(135deg, var(--violet), #a78bfa);
            display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: #fff; flex-shrink: 0; text-transform: uppercase; }
        .tp-info { display: flex; align-items: center; gap: 12px; }
        .tp-code-pill { display: inline-block; font-size: 10.5px; font-weight: 700; color: var(--violet);
            background: var(--violet-soft); padding: 2px 8px; border-radius: 5px; margin-top:2px; }
        .rate-pill { font-size: 11px; font-weight: 700; color: var(--teal); background: var(--teal-soft); padding: 2px 8px; border-radius: 12px; }
        @media(max-width: 768px) { .kpi-card .kpi-value { font-size: 19px; } .mis-page-title { font-size: 18px; } }
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

                    <div class="mis-page-title">
                        <span class="icon-chip"><i class="material-icons-outlined">groups</i></span>
                        My Referrals &amp; Earnings
                    </div>
                    <div class="mis-page-sub">Territory Partners you referred, their performance, and the commission you've earned from them.</div>

                    <!-- ── FILTER BAR ──────────────────────────────────────── -->
                    <div class="mis-filter-bar">
                        <form method="get" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                            <div>
                                <label>From</label>
                                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo $from; ?>" style="width:150px;">
                            </div>
                            <div>
                                <label>To</label>
                                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo $to; ?>" style="width:150px;">
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="material-icons-outlined" style="font-size:14px;vertical-align:-2px;">search</i> Apply
                                </button>
                            </div>
                            <div style="margin-left:auto;align-self:flex-end;display:flex;gap:6px;flex-wrap:wrap;">
                                <a href="?preset=today" class="preset-btn <?php echo $preset==='today'?'active':''; ?>">Today</a>
                                <a href="?preset=week"  class="preset-btn <?php echo $preset==='week'?'active':''; ?>">This Week</a>
                                <a href="?preset=month" class="preset-btn <?php echo $preset==='month'?'active':''; ?>">This Month</a>
                                <a href="?preset=year"  class="preset-btn <?php echo $preset==='year'?'active':''; ?>">This Year</a>
                                <a href="?preset=all"   class="preset-btn <?php echo $preset==='all'?'active':''; ?>">All Time</a>
                            </div>
                        </form>
                    </div>

                    <!-- ── SUMMARY KPIs ─────────────────────────────────────── -->
                    <div class="row mb-4">
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-violet"><i class="material-icons-outlined">groups</i></span>
                                <div class="kpi-title">My Referrals</div>
                                <div class="kpi-value"><?php echo $total_referrals; ?></div>
                                <div class="kpi-sub"><?php echo $active_referrals; ?> active</div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-teal"><i class="material-icons-outlined">receipt_long</i></span>
                                <div class="kpi-title">Their Invoices</div>
                                <div class="kpi-value"><?php echo inr_format($total_downline_invoices, 0); ?></div>
                                <div class="kpi-sub">in selected range</div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-indigo"><i class="material-icons-outlined">shopping_cart</i></span>
                                <div class="kpi-title">Their Purchases</div>
                                <div class="kpi-value">&#x20B9;<?php echo inr_format($total_downline_purchase, 0); ?></div>
                                <div class="kpi-sub">total stock bought</div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-amber"><i class="material-icons-outlined">confirmation_number</i></span>
                                <div class="kpi-title">Credits This Range</div>
                                <div class="kpi-value"><?php echo $earnings_count; ?></div>
                                <div class="kpi-sub">commission entries</div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-green"><i class="material-icons-outlined">payments</i></span>
                                <div class="kpi-title">Earned (Range)</div>
                                <div class="kpi-value">&#x20B9;<?php echo inr_format($total_earnings, 0); ?></div>
                                <div class="kpi-sub">referral commission</div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-4 col-6 mb-3">
                            <div class="kpi-card">
                                <span class="kpi-icon-chip chip-green"><i class="material-icons-outlined">account_balance_wallet</i></span>
                                <div class="kpi-title">Earned (All-Time)</div>
                                <div class="kpi-value">&#x20B9;<?php echo inr_format($all_time_earnings, 0); ?></div>
                                <div class="kpi-sub"><a href="wallet-history.php" style="color:var(--indigo);font-weight:600;">View Wallet →</a></div>
                            </div>
                        </div>
                    </div>

                    <!-- ── MY DOWNLINE TABLE ────────────────────────────────── -->
                    <div class="row">
                        <div class="col-xl-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <span class="hdr-icon chip-violet"><i class="material-icons-outlined">groups</i></span>
                                    <h5 class="card-title">My Referred Territory Partners</h5>
                                </div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($referrals)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">person_search</i>No Territory Partners have referred you as their sponsor yet.</div>
                                    <?php else: ?>
                                    <table id="referralTable" class="mis-table" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Territory Partner</th>
                                                <th>Mobile</th>
                                                <th>Commission Rate</th>
                                                <th>Invoices</th>
                                                <th>Purchases</th>
                                                <th>Target Progress</th>
                                                <th>Last Purchase</th>
                                                <th>Lifetime Earned From Them</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($referrals as $r):
                                            $words = explode(' ', trim($r['name']));
                                            $initials = count($words) > 1 ? strtoupper($words[0][0] . end($words)[0]) : strtoupper(substr(trim($r['name']), 0, 1));
                                            $pct = (float)$r['target_pct'];
                                            $bar_c = $pct >= 100 ? 'var(--green)' : ($pct >= 50 ? 'var(--amber)' : 'var(--rose)');
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="tp-info">
                                                        <div class="tp-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                                        <div>
                                                            <div><b><?php echo htmlspecialchars(ucwords(strtolower($r['name']))); ?></b></div>
                                                            <span class="tp-code-pill"><?php echo htmlspecialchars($r['tp_id']); ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($r['mobile']); ?></td>
                                                <td><span class="rate-pill"><?php echo htmlspecialchars($r['referral_percentage']); ?>%</span></td>
                                                <td><span class="badge-qty"><?php echo (int)$r['invoice_count']; ?></span></td>
                                                <td>&#x20B9;<?php echo inr_format($r['total_purchase'], 2); ?></td>
                                                <td>
                                                    <?php if ($r['target'] > 0): ?>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress-bar-mis" style="width:80px"><div class="progress-fill" style="width:<?php echo min($pct,100); ?>%;background:<?php echo $bar_c; ?>"></div></div>
                                                        <span style="font-size:12px;font-weight:700;color:<?php echo $bar_c; ?>"><?php echo $pct; ?>%</span>
                                                    </div>
                                                    <div style="font-size:11px;color:var(--ink-faint);">of &#x20B9;<?php echo inr_format($r['target'], 0); ?></div>
                                                    <?php else: ?>
                                                    <span style="color:var(--ink-faint);font-size:12px;">No target set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $r['last_invoice_date'] ? date('d M Y', strtotime($r['last_invoice_date'])) : '—'; ?></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo inr_format($r['lifetime_earned'], 2); ?></span>
                                                    <div style="font-size:11px;color:var(--ink-faint);"><?php echo (int)$r['lifetime_credits']; ?> credit<?php echo (int)$r['lifetime_credits'] === 1 ? '' : 's'; ?></div>
                                                </td>
                                                <td>
                                                    <?php if ((int)$r['is_active'] === 1): ?>
                                                        <span class="status-badge badge-paid">Active</span>
                                                    <?php else: ?>
                                                        <span class="status-badge badge-unpaid">Inactive</span>
                                                    <?php endif; ?>
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

                    <!-- ── EARNINGS HISTORY ─────────────────────────────────── -->
                    <div class="row">
                        <div class="col-xl-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <span class="hdr-icon chip-green"><i class="material-icons-outlined">payments</i></span>
                                    <h5 class="card-title">Referral Commission History <small class="text-muted">(selected range)</small></h5>
                                </div>
                                <div class="card-body" style="overflow-x:auto">
                                    <?php if (empty($earnings)): ?>
                                        <div class="empty-state"><i class="material-icons-outlined">payments</i>No referral commission credited in this range.</div>
                                    <?php else: ?>
                                    <table class="mis-table">
                                        <thead>
                                            <tr>
                                                <th>Period</th>
                                                <th>Referred TP</th>
                                                <th>Their Net Sales</th>
                                                <th>Their Target</th>
                                                <th>Target Reached</th>
                                                <th>Commission %</th>
                                                <th>Commission Earned</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($earnings as $e): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($e['month'] . ' ' . $e['year']); ?>
                                                    <div style="font-size:11px;color:var(--ink-faint);"><?php echo date('d M', strtotime($e['from_date'])); ?> – <?php echo date('d M Y', strtotime($e['to_date'])); ?></div>
                                                </td>
                                                <td><b><?php echo htmlspecialchars(ucwords(strtolower($e['tp_name']))); ?></b>
                                                    <div class="tp-code-pill"><?php echo htmlspecialchars($e['tp_code']); ?></div>
                                                </td>
                                                <td>&#x20B9;<?php echo inr_format($e['total_sls_amount'], 2); ?></td>
                                                <td>&#x20B9;<?php echo inr_format($e['target_sls_amount'], 2); ?></td>
                                                <td>
                                                    <?php if (($e['target_reached'] ?? '') === 'yes'): ?>
                                                        <span class="status-badge badge-paid">Yes</span>
                                                    <?php else: ?>
                                                        <span class="status-badge badge-unpaid">No</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="rate-pill"><?php echo htmlspecialchars($e['commission_percentage']); ?>%</span></td>
                                                <td><span class="badge-rev">&#x20B9;<?php echo inr_format($e['commission_amount'], 2); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="6" style="text-align:right;font-weight:700;">Total Earned (Selected Range)</td>
                                                <td><span class="badge-rev" style="font-size:13px;">&#x20B9;<?php echo inr_format($total_earnings, 2); ?></span></td>
                                            </tr>
                                        </tfoot>
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
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
$(function(){
    $('#referralTable').DataTable({
        order: [[4, 'desc']],
        pageLength: 25,
        language: { emptyTable: 'No data found' }
    });
});
</script>
</body>
</html>
