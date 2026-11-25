<?php
// Company-only — "which 2+-district TP's invoice amount isn't cleanly
// attributing to any of their current districts" — 2026-11-30, user-
// requested follow-up to the "Your TPs' Invoice Amount" undercount fix.
// TP Name, each of their CURRENT districts' own invoiced amount (+ that
// district's DM), and a Mismatch Amount for whatever didn't resolve to any
// of their current districts at all. See
// company/include/MultiDistrictTpMismatch.php for the resolution rule.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms_edit');
require_once __DIR__ . '/include/MultiDistrictTpMismatch.php';
error_reporting(0);

function validateDate_mdtp(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}
$from_date = validateDate_mdtp($_GET['frdate'] ?? null, date('Y-m-01'));
$to_date   = validateDate_mdtp($_GET['todate'] ?? null, date('Y-m-d'));
if (strtotime($from_date) > strtotime($to_date)) { [$from_date, $to_date] = [$to_date, $from_date]; }

$rows = getMultiDistrictTpInvoiceBreakdown($db_conn, $from_date, $to_date);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Multi-District TP Invoice Mismatch : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        .mdtp-card { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.06); border:none; margin-bottom:20px; }
        .filter-bar { background:#fff; border-radius:10px; padding:18px 20px; box-shadow:0 1px 6px rgba(0,0,0,.07); margin-bottom:16px; }
        .table th { background:#f7f7f6; font-weight:600; color:#52514e; font-size:12px; text-transform:uppercase; letter-spacing:.3px; text-align:center; vertical-align:middle; }
        .table td { font-size:13px; vertical-align:middle; text-align:center; }
        .mdtp-tp-cell { text-align:left !important; }
        .mdtp-dist-amt { color:#166534; font-weight:600; }
        .mdtp-dist-amt.zero { color:#9ca3af; font-weight:400; }
        .mdtp-mismatch { color:#dc2626; font-weight:700; }
        .mdtp-mismatch.zero { color:#9ca3af; font-weight:400; }
        .mdtp-total { font-weight:700; color:#1f2937; }
        .mdtp-empty { color:#9ca3af; font-size:14px; padding:30px; text-align:center; }
        .dm-tag { font-size:10.5px; color:#6b7280; display:block; }
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
                                <h1>Multi-District TP Invoice Mismatch</h1>
                                <p style="color:#6b7280;font-size:13px;margin-top:-6px;">
                                    Territory Partners assigned to 2 or more districts — Napkin invoice amount split by
                                    which of their current districts it actually resolved to, same rule "Your TPs'
                                    Invoice Amount" uses on the Marketing Dashboard. Mismatch Amount = sales that
                                    didn't cleanly attribute to any of this TP's current districts (broken shop data,
                                    or sold under a district they've since been reassigned away from).
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="filter-bar">
                        <form method="GET">
                            <div class="row g-2 align-items-end">
                                <div class="col-auto">
                                    <label class="form-label mb-1">From Date</label>
                                    <input type="date" name="frdate" class="form-control form-control-sm" value="<?php echo htmlspecialchars($from_date, ENT_QUOTES); ?>">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1">To Date</label>
                                    <input type="date" name="todate" class="form-control form-control-sm" value="<?php echo htmlspecialchars($to_date, ENT_QUOTES); ?>">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="material-icons" style="vertical-align:middle;font-size:16px">search</i> Search</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="main-card mdtp-card">
                        <div class="table-responsive" style="padding:0 2px 10px;">
                            <table class="table table-bordered table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th class="mdtp-tp-cell">Territory Partner</th>
                                        <th>Districts (Current)</th>
                                        <th>Mismatch Amount</th>
                                        <th>Total Invoice Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="4" class="mdtp-empty">No multi-district Territory Partners found.</td></tr>
                                <?php else: foreach ($rows as $r): ?>
                                    <tr>
                                        <td class="mdtp-tp-cell">
                                            <b><?php echo htmlspecialchars($r['tp_name']); ?></b>
                                            <span style="color:#9ca3af;font-size:11px;"> (<?php echo htmlspecialchars($r['tp_code']); ?>)</span><br>
                                            <span style="color:#9ca3af;font-size:11.5px;"><?php echo htmlspecialchars($r['mobile'] ?? ''); ?></span>
                                        </td>
                                        <td style="text-align:left;">
                                            <?php foreach ($r['districts'] as $d): ?>
                                            <div style="padding:2px 0;border-bottom:1px dashed #eee;">
                                                <b><?php echo htmlspecialchars($d['district_name']); ?></b>
                                                <span class="mdtp-dist-amt<?php echo ((float)$d['amount'] === 0.0) ? ' zero' : ''; ?>" style="float:right;">
                                                    &#8377;<?php echo number_format((float)$d['amount'], 2); ?>
                                                </span>
                                                <?php if (!empty($d['dm_names'])): ?>
                                                <span class="dm-tag">DM: <?php echo htmlspecialchars(implode(', ', $d['dm_names'])); ?></span>
                                                <?php else: ?>
                                                <span class="dm-tag" style="color:#dc2626;">No DM assigned</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <span class="mdtp-mismatch<?php echo ((float)$r['mismatch_amount'] === 0.0) ? ' zero' : ''; ?>">
                                                &#8377;<?php echo number_format((float)$r['mismatch_amount'], 2); ?>
                                            </span>
                                        </td>
                                        <td class="mdtp-total">&#8377;<?php echo number_format((float)$r['total_amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
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
<script src="../../assets/js/main.min.js"></script>
</body>
</html>
