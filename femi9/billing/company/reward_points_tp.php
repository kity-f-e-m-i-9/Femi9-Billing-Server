<?php
/**
 * Reward Points - Territory Partners (Company View)
 */

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

require_once("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('reward_points');
require_once("config.php");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set("Asia/Kolkata");

function validateDate_rtp(?string $date, string $default): string {
    if (empty($date)) return $default;
    $ts = strtotime($date);
    return ($ts === false) ? $default : date('Y-m-d', $ts);
}

$daysInMonth     = (int) date('t');
$currentMonth    = date('m');
$defaultFromDate = date("Y-{$currentMonth}-01");
$defaultToDate   = date("Y-{$currentMonth}-{$daysInMonth}");

$current_from_date = validateDate_rtp($_POST['frdate'] ?? $_GET['frdate'] ?? null, $defaultFromDate);
$current_to_date   = validateDate_rtp($_POST['todate'] ?? $_GET['todate'] ?? null, $defaultToDate);

if (strtotime($current_from_date) > strtotime($current_to_date)) {
    [$current_from_date, $current_to_date] = [$current_to_date, $current_from_date];
}

$safe_business_name = htmlspecialchars($business_name ?? 'Femi9', ENT_QUOTES, 'UTF-8');

require_once("include/TpRewardPointsData.php");

$target_ranges = getTpTargetAmountRanges();
$current_target_range = $_POST['target_range'] ?? $_GET['target_range'] ?? '';
if (!array_key_exists($current_target_range, $target_ranges)) $current_target_range = '';

$combined_users = [];
try {
    $combined_users = getTpRewardPointsData($current_from_date, $current_to_date, $current_target_range);
} catch (PDOException $e) {
    error_log("reward_points_tp error: " . $e->getMessage());
    $combined_users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reward Points - Territory Partners | <?php echo $safe_business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png" />
    <style>
        .rp-card { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.08); border:none; margin-bottom:20px; }
        .filter-bar { background:#fff; border-radius:10px; padding:18px 20px; box-shadow:0 1px 6px rgba(0,0,0,.07); margin-bottom:20px; }
        .badge-total { background:#4f46e5; color:#fff; font-size:13px; padding:5px 12px; border-radius:20px; font-weight:600; }
        .badge-purchase { background:#10b981; color:#fff; font-size:12px; padding:4px 10px; border-radius:12px; }
        .badge-sales { background:#0ea5e9; color:#fff; font-size:12px; padding:4px 10px; border-radius:12px; border:1px dashed rgba(255,255,255,.6); }
        .badge-daily { background:#3b82f6; color:#fff; font-size:12px; padding:4px 10px; border-radius:12px; }
        .badge-team { background:#8b5cf6; color:#fff; font-size:12px; padding:4px 10px; border-radius:12px; }
        .badge-advance { background:#f59e0b; color:#fff; font-size:12px; padding:4px 10px; border-radius:12px; }
        .badge-return { background:#ef4444; color:#fff; font-size:12px; padding:4px 10px; border-radius:12px; }
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
                                            <td>Reward Points — Territory Partners</td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Date Filter -->
                    <div class="filter-bar">
                        <form method="POST">
                            <div class="row g-2 align-items-end">
                                <div class="col-auto">
                                    <label class="form-label mb-1">From Date</label>
                                    <input type="date" name="frdate" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($current_from_date, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1">To Date</label>
                                    <input type="date" name="todate" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($current_to_date, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1">Monthly Target</label>
                                    <select name="target_range" class="form-control form-control-sm" style="min-width:190px;">
                                        <?php foreach ($target_ranges as $key => $label): ?>
                                        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $key === $current_target_range ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="material-icons" style="vertical-align:middle;font-size:16px">search</i> Filter
                                    </button>
                                    <a href="reward_points_tp" class="btn btn-secondary btn-sm ms-1">Reset</a>
                                    <a href="reward_points_tp_export_xlsx.php?frdate=<?php echo urlencode($current_from_date); ?>&todate=<?php echo urlencode($current_to_date); ?>&target_range=<?php echo urlencode($current_target_range); ?>" class="btn btn-success btn-sm ms-1">
                                        <i class="material-icons" style="vertical-align:middle;font-size:16px">file_download</i> Export
                                    </a>
                                </div>
                                <div class="col-auto ms-auto">
                                    <small class="text-muted">
                                        <?php echo date('d M Y', strtotime($current_from_date)); ?> –
                                        <?php echo date('d M Y', strtotime($current_to_date)); ?>
                                    </small>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Table -->
                    <div class="card rp-card">
                        <div class="card-body">
                            <?php if (empty($combined_users)): ?>
                                <div class="alert alert-info mb-0">No reward points data found for this date range.</div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table id="rpTable" class="table table-hover table-sm" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>TP ID</th>
                                            <th>Name</th>
                                            <th>Mobile</th>
                                            <th>District</th>
                                            <th>Monthly Target</th>
                                            <th>Purchase Pts</th>
                                            <th>Login Pts</th>
                                            <th>Team Pts</th>
                                            <th>Advance Bonus Pts</th>
                                            <th>Returns (–)</th>
                                            <th>Total Points</th>
                                            <th>Sales Pts <small class="text-muted">(not in total)</small></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $sr = 1; foreach ($combined_users as $u): ?>
                                        <?php $d = $u['details']; ?>
                                        <tr>
                                            <td><?php echo $sr++; ?></td>
                                            <td><?php echo htmlspecialchars($d['tp_id'] ?? '–', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($d['name'] ?? '–', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($d['mobile'] ?? '–', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($u['district_names'] ?? '–', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo $u['target_amount'] > 0 ? '₹' . inr_format($u['target_amount'], 2) : '–'; ?></td>
                                            <td><span class="badge-purchase"><?php echo inr_format($u['purchase_points'], 2); ?></span></td>
                                            <td><span class="badge-daily"><?php echo inr_format($u['daily_points'], 2); ?></span></td>
                                            <td><?php if ($u['team_points'] > 0): ?><button type="button" class="badge-team team-points-trigger" style="border:none;cursor:pointer;" data-referrer-id="<?php echo (int)$u['user_id']; ?>" data-referrer-name="<?php echo htmlspecialchars($d['name'] ?? '–', ENT_QUOTES, 'UTF-8'); ?>" title="Click to see the breakdown"><?php echo inr_format($u['team_points'], 2); ?></button><?php else: ?>–<?php endif; ?></td>
                                            <td><?php if ($u['advance_points'] > 0): ?><span class="badge-advance"><?php echo inr_format($u['advance_points'], 2); ?></span><?php else: ?>–<?php endif; ?></td>
                                            <td><?php if ($u['return_points'] > 0): ?><span class="badge-return"><?php echo inr_format($u['return_points'], 2); ?></span><?php else: ?>–<?php endif; ?></td>
                                            <td><span class="badge-total"><?php echo inr_format($u['total_points'], 2); ?></span></td>
                                            <td><?php if ($u['sales_points'] > 0): ?><span class="badge-sales"><?php echo inr_format($u['sales_points'], 2); ?></span><?php else: ?>–<?php endif; ?></td>
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

<!-- Team Points Breakdown Modal -->
<div class="modal fade" id="teamPointsModal" tabindex="-1" aria-labelledby="teamPointsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="teamPointsModalLabel" style="font-weight:700;">
                    <i class="material-icons" style="vertical-align:middle;font-size:19px;color:#8b5cf6;">groups</i>
                    Team Points Breakdown
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="teamPointsModalBody" style="min-height:120px;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
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
    $('#rpTable').DataTable({
        order: [[11, 'desc']],
        pageLength: 25,
        language: { emptyTable: 'No data found' }
    });
});

var TP_RP_FROM_DATE = <?php echo json_encode($current_from_date); ?>;
var TP_RP_TO_DATE   = <?php echo json_encode($current_to_date); ?>;

$(document).on('click', '.team-points-trigger', function () {
    var referrerId   = $(this).data('referrer-id');
    var referrerName = $(this).data('referrer-name');

    $('#teamPointsModalLabel').html(
        '<i class="material-icons" style="vertical-align:middle;font-size:19px;color:#8b5cf6;">groups</i> ' +
        'Team Points Breakdown — ' + $('<span>').text(referrerName).html()
    );
    $('#teamPointsModalBody').html('<div style="text-align:center;padding:30px;color:#9ca3af;">Loading…</div>');
    $('#teamPointsModal').modal('show');

    $.get('get-tp-team-points-breakdown.php', {
        referrer_id: referrerId,
        frdate: TP_RP_FROM_DATE,
        todate: TP_RP_TO_DATE
    })
        .done(function (data) {
            if (!data.success) {
                $('#teamPointsModalBody').html('<div style="color:#dc2626;padding:20px;">' + (data.message || 'Could not load breakdown.') + '</div>');
                return;
            }
            if (!data.members.length) {
                $('#teamPointsModalBody').html('<div style="text-align:center;padding:30px;color:#9ca3af;">No team member purchases found for this range.</div>');
                return;
            }

            var totalPoints = 0;
            var html = '<div style="font-size:12.5px;color:#6b7280;margin-bottom:14px;">' +
                'Team members whose purchases (0% GST products, reward-points-enabled invoices) contributed to this Team Points total, ' +
                data.from_date + ' to ' + data.to_date + '.</div>';
            html += '<table style="width:100%;font-size:13.5px;border-collapse:collapse;">' +
                    '<thead><tr style="color:#6b7280;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:2px solid #f3f4f6;">' +
                    '<th style="text-align:left;padding:8px 10px 8px 0;">Territory Partner</th>' +
                    '<th style="text-align:center;padding:8px 10px;">%</th>' +
                    '<th style="text-align:center;padding:8px 10px;">Invoices</th>' +
                    '<th style="text-align:right;padding:8px 10px;">0% GST Amount</th>' +
                    '<th style="text-align:right;padding:8px 0;">Points Contributed</th>' +
                    '</tr></thead><tbody>';
            $.each(data.members, function (_, m) {
                totalPoints += parseFloat(m.points) || 0;
                var pct = parseFloat(m.referral_percentage) || 0;
                html += '<tr style="border-bottom:1px dotted #f3f4f6;">' +
                        '<td style="padding:10px 10px 10px 0;">' +
                            '<div style="font-weight:600;color:#1f2937;">' + $('<span>').text(m.name || '–').html() + '</div>' +
                            '<div style="font-size:11px;color:#8b5cf6;font-weight:700;">' + $('<span>').text(m.tp_code || '').html() + '</div>' +
                            '<div style="font-size:11.5px;color:#9ca3af;">' + $('<span>').text(m.mobile || '').html() + '</div>' +
                        '</td>' +
                        '<td style="text-align:center;padding:10px;">' +
                            '<span style="display:inline-block;white-space:nowrap;font-size:11px;font-weight:700;color:#059669;background:#d1fae5;border-radius:10px;padding:2px 9px;line-height:1.4;">' + pct + '%</span>' +
                        '</td>' +
                        '<td style="text-align:center;padding:10px;color:#374151;">' + m.invoice_count + '</td>' +
                        '<td style="text-align:right;padding:10px;color:#374151;">₹' + (parseFloat(m.amount) || 0).toFixed(2) + '</td>' +
                        '<td style="text-align:right;padding:10px 0;"><span class="badge-team">' + (parseFloat(m.points) || 0).toFixed(2) + '</span></td>' +
                        '</tr>';
            });
            html += '</tbody><tfoot><tr>' +
                    '<td colspan="4" style="text-align:right;padding:14px 10px 0 0;font-weight:700;color:#374151;">Total Team Points</td>' +
                    '<td style="text-align:right;padding:14px 0 0;font-weight:700;color:#8b5cf6;font-size:16px;">' + totalPoints.toFixed(2) + '</td>' +
                    '</tr></tfoot></table>';

            $('#teamPointsModalBody').html(html);
        })
        .fail(function () {
            $('#teamPointsModalBody').html('<div style="color:#dc2626;padding:20px;">Could not reach the server. Please try again.</div>');
        });
});
</script>
</body>
</html>
