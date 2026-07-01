<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$cp_id = (int)$Login_user_IDvl;

// Get this CP's text ID (e.g. CP-0001) — used as referral_id in territory_partners
$cpRow = $db_conn->prepare("SELECT cp_id FROM channel_partners WHERE id = ? LIMIT 1");
$cpRow->bind_param('i', $cp_id);
$cpRow->execute();
$cp_text_id = $cpRow->get_result()->fetch_assoc()['cp_id'] ?? '';
$cpRow->close();

// All TPs formally assigned under this CP (referral_type = 'CP', referral_id = cp_id text)
// Invoice stats are included where available
$stmt = $db_conn->prepare("
    SELECT
        tp.id,
        tp.tp_id                            AS tp_code,
        tp.name,
        tp.mobile,
        tp.is_active,
        COUNT(tpi.id)                       AS invoice_count,
        COALESCE(SUM(tpi.total_amount), 0)  AS total_sales,
        MAX(tpi.invoice_date)               AS last_invoice_date
    FROM territory_partners tp
    LEFT JOIN tp_invoices tpi
           ON tpi.territory_partner_id = tp.id
          AND tpi.source_cp_id = ?
    WHERE tp.referral_type = 'CP'
      AND tp.referral_id   = ?
    GROUP BY tp.id
    ORDER BY tp.is_active DESC, tp.name ASC
");
$stmt->bind_param('is', $cp_id, $cp_text_id);
$stmt->execute();
$tps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_tps     = count($tps);
$active_tps    = count(array_filter($tps, fn($r) => $r['is_active']));
$total_invoices = array_sum(array_column($tps, 'invoice_count'));
$total_sales   = array_sum(array_column($tps, 'total_sales'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Territory Partners : <?php echo htmlspecialchars($business_name); ?></title>
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
        body { font-family: 'Poppins', sans-serif; }

        /* ── Stat Cards ──────────────────────────────────── */
        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 22px 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            border-left: 5px solid;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform .15s, box-shadow .15s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.10); }
        .stat-card.purple { border-color: #667eea; }
        .stat-card.green  { border-color: #10b981; }
        .stat-card.blue   { border-color: #3b82f6; }
        .stat-card.orange { border-color: #f59e0b; }
        .stat-card h3 { font-size: 28px; font-weight: 700; margin: 0 0 3px 0; color: #1f2937; line-height: 1; }
        .stat-card p  { margin: 0; font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: .7px; }
        .stat-icon-wrap {
            width: 52px; height: 52px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .stat-icon-wrap i { font-size: 26px; }
        .stat-card.purple .stat-icon-wrap { background: #ede9fe; color: #667eea; }
        .stat-card.green  .stat-icon-wrap { background: #d1fae5; color: #10b981; }
        .stat-card.blue   .stat-icon-wrap { background: #dbeafe; color: #3b82f6; }
        .stat-card.orange .stat-icon-wrap { background: #fef3c7; color: #f59e0b; }

        /* ── Main Card ───────────────────────────────────── */
        .main-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,.07);
            border: none;
            overflow: hidden;
        }
        .main-card-header {
            padding: 20px 28px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
        }
        .main-card-header-left { display: flex; align-items: center; gap: 12px; }
        .header-icon-box {
            width: 42px; height: 42px; border-radius: 11px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex; align-items: center; justify-content: center;
        }
        .header-icon-box i { font-size: 20px; color: #fff; }
        .header-title { font-size: 15px; font-weight: 700; color: #1f2937; margin: 0; line-height: 1.2; }
        .header-sub   { font-size: 12px; color: #9ca3af; margin: 0; }
        .tp-count-pill {
            background: #ede9fe; color: #6d28d9;
            font-size: 12px; font-weight: 700;
            padding: 5px 14px; border-radius: 20px;
        }

        /* ── Table ───────────────────────────────────────── */
        .tp-table { width: 100%; border-collapse: separate; border-spacing: 0; }

        .tp-table thead tr {
            background: #f8fafc;
        }
        .tp-table thead th {
            padding: 14px 20px;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #94a3b8;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .tp-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background .12s;
        }
        .tp-table tbody tr:last-child { border-bottom: none; }
        .tp-table tbody tr:hover { background: #fafbff; }

        .tp-table tbody td {
            padding: 18px 20px;
            vertical-align: middle;
        }

        /* ── Avatar ──────────────────────────────────────── */
        .tp-avatar {
            width: 42px; height: 42px; border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700; color: #fff;
            flex-shrink: 0;
            text-transform: uppercase;
        }
        .tp-info { display: flex; align-items: center; gap: 14px; }
        .tp-info-text { display: flex; flex-direction: column; gap: 3px; }
        .tp-name { font-size: 14px; font-weight: 600; color: #1e293b; line-height: 1.2; }
        .tp-code-pill {
            display: inline-block;
            font-size: 10.5px; font-weight: 700; color: #7c3aed;
            background: #ede9fe; padding: 2px 8px; border-radius: 5px;
            letter-spacing: .3px;
        }

        /* ── Mobile cell ─────────────────────────────────── */
        .mobile-cell { display: flex; align-items: center; gap: 7px; }
        .mobile-cell i { font-size: 15px; color: #cbd5e1; }
        .mobile-text { font-size: 13px; color: #475569; font-weight: 500; }

        /* ── Invoice badge ───────────────────────────────── */
        .inv-badge {
            display: inline-flex; align-items: center; justify-content: center;
            background: #eff6ff; color: #2563eb;
            font-size: 13px; font-weight: 700;
            width: 38px; height: 28px; border-radius: 8px;
        }

        /* ── Sales amount ────────────────────────────────── */
        .sales-amount { font-size: 14px; font-weight: 700; color: #1e293b; }
        .sales-currency { font-size: 11px; color: #94a3b8; font-weight: 600; margin-right: 1px; }

        /* ── Date cell ───────────────────────────────────── */
        .date-cell { display: flex; align-items: center; gap: 7px; }
        .date-cell i { font-size: 15px; color: #cbd5e1; }
        .date-text { font-size: 13px; color: #64748b; }

        /* ── Status badge ────────────────────────────────── */
        .status-dot {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; font-weight: 600;
            padding: 5px 12px; border-radius: 20px;
        }
        .status-dot::before {
            content: ''; width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
        }
        .status-active   { background: #ecfdf5; color: #059669; }
        .status-active::before   { background: #10b981; }
        .status-inactive { background: #fef2f2; color: #dc2626; }
        .status-inactive::before { background: #ef4444; }

        /* ── Serial number ───────────────────────────────── */
        .serial-num { font-size: 12px; color: #cbd5e1; font-weight: 600; }

        /* ── Empty state ─────────────────────────────────── */
        .empty-state { text-align: center; padding: 72px 20px; color: #9ca3af; }
        .empty-icon-wrap {
            width: 72px; height: 72px; border-radius: 20px;
            background: #f1f5f9;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .empty-icon-wrap i { font-size: 36px; color: #cbd5e1; }
        .empty-state h6 { font-size: 15px; font-weight: 600; color: #64748b; margin-bottom: 6px; }
        .empty-state p  { font-size: 13px; color: #9ca3af; margin: 0; }

        /* ── Search box override ─────────────────────────── */
        #tpSearch {
            border: 1.5px solid #e2e8f0; border-radius: 8px;
            padding: 7px 14px 7px 36px; font-size: 13px; color: #374151;
            outline: none; width: 220px;
            background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%239ca3af' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.099zm-5.242 1.656a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z'/%3E%3C/svg%3E") no-repeat 12px center;
            transition: border-color .15s;
        }
        #tpSearch:focus { border-color: #667eea; background-color: #fff; }
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

                    <div class="row mb-2">
                        <div class="col">
                            <h4 style="font-weight:700;color:#1f2937;margin-bottom:4px;">
                                <i class="material-icons-two-tone" style="vertical-align:middle;font-size:22px;">people</i>
                                My Territory Partners
                            </h4>
                            <p style="color:#6b7280;font-size:13px;margin:0;">TPs who have received stock invoices from your account</p>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6">
                            <div class="stat-card purple">
                                <div>
                                    <h3><?php echo $total_tps; ?></h3>
                                    <p>Total TPs</p>
                                </div>
                                <div class="stat-icon-wrap">
                                    <i class="material-icons-outlined">people</i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="stat-card green">
                                <div>
                                    <h3><?php echo $active_tps; ?></h3>
                                    <p>Active TPs</p>
                                </div>
                                <div class="stat-icon-wrap">
                                    <i class="material-icons-outlined">how_to_reg</i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="stat-card blue">
                                <div>
                                    <h3><?php echo number_format($total_invoices); ?></h3>
                                    <p>Total Invoices</p>
                                </div>
                                <div class="stat-icon-wrap">
                                    <i class="material-icons-outlined">receipt_long</i>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="stat-card orange">
                                <div>
                                    <h3>&#8377;<?php echo number_format($total_sales, 0); ?></h3>
                                    <p>Total Sales</p>
                                </div>
                                <div class="stat-icon-wrap">
                                    <i class="material-icons-outlined">payments</i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TP Table -->
                    <div class="row">
                        <div class="col-12">
                            <div class="main-card">

                                <div class="main-card-header">
                                    <div class="main-card-header-left">
                                        <div class="header-icon-box">
                                            <i class="material-icons-outlined">people_alt</i>
                                        </div>
                                        <div>
                                            <p class="header-title">Territory Partners Under You</p>
                                            <p class="header-sub">Stock invoices raised from your account</p>
                                        </div>
                                    </div>
                                    <?php if (!empty($tps)): ?>
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <div style="position:relative;">
                                            <input type="text" id="tpSearch" placeholder="Search TPs…">
                                        </div>
                                        <span class="tp-count-pill"><?php echo $total_tps; ?> TPs</span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (empty($tps)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon-wrap">
                                        <i class="material-icons-outlined">person_search</i>
                                    </div>
                                    <h6>No Territory Partners Assigned</h6>
                                    <p>TPs referred under your CP ID will appear here.</p>
                                </div>

                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="tp-table" id="tpTable">
                                        <thead>
                                            <tr>
                                                <th style="width:48px;text-align:center;">#</th>
                                                <th>Territory Partner</th>
                                                <th>Mobile</th>
                                                <th style="text-align:center;">Invoices</th>
                                                <th style="text-align:right;">Total Sales</th>
                                                <th>Last Invoice</th>
                                                <th style="text-align:center;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tpTableBody">
                                        <?php $n = 1; foreach ($tps as $tp):
                                            $initials = strtoupper(substr(trim($tp['name']), 0, 1));
                                            $words    = explode(' ', trim($tp['name']));
                                            if (count($words) > 1) $initials = strtoupper($words[0][0] . end($words)[0]);
                                        ?>
                                            <tr>
                                                <td style="text-align:center;">
                                                    <span class="serial-num"><?php echo $n++; ?></span>
                                                </td>
                                                <td>
                                                    <div class="tp-info">
                                                        <div class="tp-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                                        <div class="tp-info-text">
                                                            <span class="tp-name"><?php echo htmlspecialchars(ucwords(strtolower($tp['name']))); ?></span>
                                                            <span class="tp-code-pill"><?php echo htmlspecialchars($tp['tp_code']); ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="mobile-cell">
                                                        <i class="material-icons-outlined">phone_iphone</i>
                                                        <span class="mobile-text"><?php echo htmlspecialchars($tp['mobile']); ?></span>
                                                    </div>
                                                </td>
                                                <td style="text-align:center;">
                                                    <span class="inv-badge"><?php echo number_format((int)$tp['invoice_count']); ?></span>
                                                </td>
                                                <td style="text-align:right;">
                                                    <span class="sales-amount">
                                                        <span class="sales-currency">&#8377;</span><?php echo number_format((float)$tp['total_sales'], 2); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="date-cell">
                                                        <i class="material-icons-outlined">calendar_today</i>
                                                        <span class="date-text">
                                                            <?php echo $tp['last_invoice_date'] ? date('d M Y', strtotime($tp['last_invoice_date'])) : '—'; ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td style="text-align:center;">
                                                    <?php if ($tp['is_active']): ?>
                                                    <span class="status-dot status-active">Active</span>
                                                    <?php else: ?>
                                                    <span class="status-dot status-inactive">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>

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
$(function () {
    $('#tpSearch').on('input', function () {
        var q = $(this).val().toLowerCase().trim();
        $('#tpTableBody tr').each(function () {
            var text = $(this).text().toLowerCase();
            $(this).toggle(q === '' || text.indexOf(q) > -1);
        });
    });
});
</script>
</body>
</html>
