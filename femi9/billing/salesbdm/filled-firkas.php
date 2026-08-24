<?php
include("checksession.php");
include("config.php");
require_once("include/TeamSubtree.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// Same "view as" resolution used by dashboard.php.
$viewBdmIdRaw = $_GET['view_bdm_id'] ?? '';
$viewingOther = false;
$viewBdmName = '';
if (!empty($_companyBridgeView)) {
    $viewingOther = true;
    $viewBdmName = $result_LoGuserDtails['bdm_name'] ?? '';
} elseif (!empty($viewBdmIdRaw)) {
    $requestedId = (int)$viewBdmIdRaw;
    if ($requestedId > 0 && $requestedId !== (int)$salesBdmID) {
        $mySubtree = getBdmSubtreeIds($db_conn, (int)$salesBdmID);
        if (in_array($requestedId, $mySubtree, true)) {
            $viewingOther = true;
            $nameRow = $db_conn->query("SELECT bdm_name FROM sales_bdm_staff WHERE id=" . $requestedId)->fetch_assoc();
            $viewBdmName = $nameRow['bdm_name'] ?? '';
        }
    }
}

// The month this page shows the weekly-target breakdown for — carried over
// from the dashboard's own date filter ("From") when linked from there,
// same as the modal used to do via ffMonth.
$ffMonth = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');

$dashboardUrl = 'dashboard.php' . ($viewBdmIdRaw !== '' ? '?view_bdm_id=' . urlencode($viewBdmIdRaw) : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Filled Firkas : <?php echo $business_name; ?></title>
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
        }
        body { background: var(--page-plane); }
        .card-panel { background: var(--surface-1); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 2px rgba(11,11,11,0.03); }
        .ff-status-btn { padding:4px 13px; border-radius:20px; border:1.5px solid #9ca3af; color:#4b5563; background:#fff; font-size:12px; cursor:pointer; }
        .ff-status-btn.active { background:#4b5563; color:#fff; border-color:#4b5563; }
        #ffTabs .nav-link { cursor: pointer; }
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
                            <div class="page-description" style="margin-left:-10px;">
                                <h1>
                                    <i class="material-icons-outlined" style="vertical-align:middle;margin-right:6px;color:#16a34a;">check_circle</i>
                                    Territory Partners in your Filled Firkas
                                </h1>
                                <a href="<?php echo htmlspecialchars($dashboardUrl); ?>" class="btn btn-sm" style="border:1px solid var(--border);color:var(--text-secondary);"><i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;">arrow_back</i> Back to Dashboard</a>
                            </div>
                        </div>
                    </div>

                    <?php if ($viewingOther): ?>
                        <div class="alert alert-info" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
                            <span><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">visibility</i> Viewing <b><?php echo htmlspecialchars($viewBdmName); ?>'s</b> Filled Firkas (read-only).</span>
                        </div>
                    <?php endif; ?>

                    <div class="card-panel">
                        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
                            <ul class="nav nav-tabs" id="ffTabs" role="tablist" style="margin-bottom:0;border-bottom:none;">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="ffActiveTabBtn" type="button" data-tab="active">Active TPs</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="ffInactiveTabBtn" type="button" data-tab="inactive">Inactive TPs</button>
                                </li>
                            </ul>
                            <span id="ffCountBadge" style="font-size:12px;font-weight:700;color:#374151;background:#f3f4f6;padding:4px 12px;border-radius:14px;margin-left:auto;white-space:nowrap;"></span>
                        </div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px;">
                            <div id="ffStatusFilters" style="display:flex;gap:6px;">
                                <button type="button" class="btn btn-sm ff-status-btn active" data-status="all" style="border-radius:14px;">All</button>
                                <button type="button" class="btn btn-sm ff-status-btn" data-status="on_track" style="border-radius:14px;">On Track</button>
                                <button type="button" class="btn btn-sm ff-status-btn" data-status="behind" style="border-radius:14px;">Behind</button>
                            </div>
                            <input type="text" id="ffSearchBox" class="form-control form-control-sm" placeholder="Search TP name / phone / TP ID..." style="max-width:240px;margin-left:auto;">
                        </div>
                        <div id="ffListBody">
                            <div style="color:#9ca3af;font-size:13px;padding:20px 0;text-align:center;">Loading&hellip;</div>
                        </div>
                        <div id="ffPagination" style="display:flex;justify-content:center;align-items:center;gap:10px;margin-top:12px;"></div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Javascripts -->
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
(function ($) {
    var viewBdmId = <?php echo json_encode($viewBdmIdRaw); ?>;
    var ffMonth = <?php echo json_encode($ffMonth); ?>;
    var ffCache = {};
    var ffCurrentTab = 'active';
    var ffCurrentStatus = 'all';
    var ffCurrentSearch = '';
    var ffCurrentPage = 1;
    var ffSearchTimer = null;

    function ffEsc(s) { return $('<div/>').text(s == null ? '' : s).html(); }
    function ffMoney(n) { return '&#8377;' + Number(n || 0).toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 0}); }

    var ffTierMeta = {
        top:    { label: 'Top',    bg: '#dcfce7', color: '#15803d' },
        medium: { label: 'Medium', bg: '#fef9c3', color: '#a16207' },
        late:   { label: 'Late',   bg: '#fee2e2', color: '#b91c1c' },
        none:   { label: 'No sale yet', bg: '#f3f4f6', color: '#6b7280' }
    };

    function ffRankCell(r, w) {
        if (w.is_future || w.no_target) return '<span style="color:#9ca3af;">&mdash;</span>';
        var tier = ffTierMeta[w.rank_tier] || ffTierMeta.none;
        var dayLine = (w.rank_day_offset === null || w.rank_day_offset === undefined)
            ? 'No Napkin advance payment this week'
            : (w.rank_day_offset <= 0 ? 'Paid on week start day' : 'Paid ' + w.rank_day_offset + ' day(s) into the week');
        return '<span style="font-weight:700;color:#374151;">#' + r.rank + '</span> ' +
            '<span style="background:' + tier.bg + ';color:' + tier.color + ';padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">' + tier.label + '</span>' +
            '<div style="font-size:10.5px;color:#9ca3af;margin-top:2px;">' + dayLine + '</div>';
    }

    function ffRenderRows(data) {
        if (!data || !data.rows || !data.rows.length) {
            return '<div style="color:#9ca3af;font-size:13px;padding:20px 0;text-align:center;">No Territory Partners found.</div>';
        }
        var serialStart = ((data.page || 1) - 1) * (data.per_page || 15);
        var html = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:13px;">' +
            '<thead><tr style="border-bottom:2px solid #e9ecef;text-align:left;color:#6b7280;">' +
            '<th style="padding:6px 8px;">S.No</th><th style="padding:6px 8px;">TP ID</th>' +
            '<th style="padding:6px 8px;">Name</th><th style="padding:6px 8px;">Phone</th>' +
            '<th style="padding:6px 8px;">District</th><th style="padding:6px 8px;">Firka(s)</th>' +
            '<th style="padding:6px 8px;text-align:right;">Target (Napkin)</th>' +
            '<th style="padding:6px 8px;">Weekly Status</th><th style="padding:6px 8px;">Rank (this week)</th></tr></thead><tbody>';
        $.each(data.rows, function (i, r) {
            var w = r.weekly || {};
            var rowId = 'ffwk-' + i;
            var weeklyCell;
            if (w.is_future) {
                weeklyCell = '<span style="background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">Upcoming</span>' +
                    '<div style="font-size:10.5px;color:#9ca3af;margin-top:2px;">' + ffEsc(w.month_label) + ' hasn\'t started yet.</div>';
            } else if (w.no_target) {
                weeklyCell = '<span style="background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">No Target</span>' +
                    '<div style="font-size:10.5px;color:#9ca3af;margin-top:2px;">No Firka assigned, or assigned Firka has no target set.</div>';
            } else {
                var badge = w.on_track
                    ? '<span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">On Track</span>'
                    : '<span style="background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">Behind</span>';
                weeklyCell = '<span class="ff-weekly-trigger" style="cursor:pointer;" data-target="' + rowId + '">' + badge + ' <i class="material-icons-outlined" style="font-size:14px;vertical-align:middle;">expand_more</i></span>' +
                    '<div style="font-size:10.5px;color:#9ca3af;margin-top:2px;">' + ffEsc(w.week_label) + ' &middot; ' + ffMoney(w.paid_so_far) + ' Napkin advance paid (' + (w.pct_of_target || 0) + '% of target)</div>';
            }
            html += '<tr style="border-bottom:1px solid #f3f4f6;">' +
                '<td style="padding:7px 8px;color:#9ca3af;">' + (serialStart + i + 1) + '</td>' +
                '<td style="padding:7px 8px;color:#6b7280;">' + ffEsc(r.tp_id) + '</td>' +
                '<td style="padding:7px 8px;font-weight:600;color:#1f2937;">' + ffEsc(r.name) + '</td>' +
                '<td style="padding:7px 8px;">' + ffEsc(r.mobile) + '</td>' +
                '<td style="padding:7px 8px;">' + ffEsc(r.district) + '</td>' +
                '<td style="padding:7px 8px;color:#6b7280;">' + ffEsc(r.firkas) + '</td>' +
                '<td style="padding:7px 8px;text-align:right;font-weight:600;">' + ffMoney(r.target_amount) + '</td>' +
                '<td style="padding:7px 8px;white-space:nowrap;">' + weeklyCell + '</td>' +
                '<td style="padding:7px 8px;white-space:nowrap;">' + ffRankCell(r, w) + '</td>' +
                '</tr>';
            if (!w.is_future && !w.no_target) {
                html += '<tr id="' + rowId + '" class="ff-week-detail-row" style="display:none;">' +
                    '<td colspan="9" style="padding:8px 10px 14px 30px;background:#f9fafb;">' + ffBuildWeeklyDetailHtml(w, r.db_id) + '</td>' +
                    '</tr>';
            }
        });
        html += '</tbody></table></div>';
        return html;
    }

    // Day 29 of the month immediately before a Y-m-01 (week 1's start) —
    // where that week's spillover range actually begins. Built from
    // Date(year, monthIndex, 29) directly rather than subtracting a fixed
    // day count, since the previous month's length varies (28-31 days).
    function ffSpilloverStart(week1StartYmd) {
        var parts = week1StartYmd.split('-').map(Number);
        // parts[1] is the target month (1-indexed); the JS Date month index
        // for the PREVIOUS month is (parts[1] - 1) - 1 = parts[1] - 2.
        var d = new Date(parts[0], parts[1] - 2, 29);
        var mm = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    function ffWeekPaymentLink(tpDbId, wk, includeSpillover) {
        if (!tpDbId) return '';
        var fromDate = includeSpillover ? ffSpilloverStart(wk.start) : wk.start;
        var url = 'tp-advance-payment-report.php?tp_id=' + tpDbId +
            '&from_date=' + fromDate + '&to_date=' + wk.end + '&type=napkin';
        return ' <a href="' + url + '" target="_blank" rel="noopener" ' +
            'style="font-size:10.5px;color:#4f46e5;text-decoration:none;white-space:nowrap;" ' +
            'title="View the actual advance payment entries for this week">View entries &#8599;</a>';
    }

    function ffBuildWeeklyDetailHtml(w, tpDbId) {
        if (!w || !w.weeks) return 'No data';
        var html = '<div style="font-weight:600;margin-bottom:6px;color:#374151;">' + ffEsc(w.month_label) + ' &mdash; Weekly Advance Payments</div>' +
            '<div style="font-size:11px;color:#9ca3af;margin-bottom:6px;">Each week needs its own Napkin advance payment of at least its "Required this week" amount — an earlier week\'s surplus does not carry forward, unless the full month\'s target has already been paid in full. "Napkin sold" is shown alongside for reference only.</div>' +
            '<table style="font-size:12px;border-collapse:collapse;width:100%;max-width:640px;">' +
            '<tr style="color:#6b7280;"><th style="text-align:left;padding:3px 8px;">Week</th>' +
            '<th style="text-align:right;padding:3px 8px;">Required this week</th>' +
            '<th style="text-align:right;padding:3px 8px;">Napkin sold this week</th>' +
            '<th style="text-align:right;padding:3px 8px;">Advance paid this week</th>' +
            '<th style="text-align:right;padding:3px 8px;">Total paid so far</th>' +
            '<th style="text-align:center;padding:3px 8px;">Status</th></tr>';
        $.each(['week1', 'week2', 'week3', 'week4'], function (_, key) {
            var wk = w.weeks[key];
            if (!wk) return;
            var status = !wk.has_started ? '<span style="color:#9ca3af;">&mdash;</span>'
                : (wk.pass ? '<span style="color:#15803d;font-weight:600;">Pass</span>' : '<span style="color:#b91c1c;font-weight:600;">Fail</span>');
            var rowStyle = wk.is_current ? ' style="background:#fffbeb;"' : '';
            var spilloverNote = (key === 'week1' && w.has_spillover)
                ? '<div style="font-size:10px;color:#0ea5e9;">incl. ' + ffMoney(w.spillover_amount) + ' paid late last month</div>' : '';
            var paidCell = wk.has_started
                ? ffMoney(wk.amount) + spilloverNote + ffWeekPaymentLink(tpDbId, wk, key === 'week1' && w.has_spillover)
                : ffMoney(wk.amount);
            html += '<tr' + rowStyle + '><td style="padding:4px 8px;">' + ffEsc(wk.label) + '</td>' +
                '<td style="text-align:right;padding:4px 8px;color:#6b7280;">' + ffMoney(wk.weekly_slice) + '</td>' +
                '<td style="text-align:right;padding:4px 8px;color:#6b7280;">' + ffMoney(wk.sold) + '</td>' +
                '<td style="text-align:right;padding:4px 8px;font-weight:600;">' + paidCell + '</td>' +
                '<td style="text-align:right;padding:4px 8px;">' + ffMoney(wk.cumulative) + '</td>' +
                '<td style="text-align:center;padding:4px 8px;">' + status + '</td></tr>';
        });
        html += '</table>' +
            '<div style="font-size:10.5px;color:#9ca3af;margin-top:6px;">A payment made on day 29-31 falls outside every week of that month &mdash; it counts toward next month\'s Week 1 instead.</div>';
        return html;
    }

    function ffRenderPagination(data) {
        var totalPages = Math.max(1, Math.ceil((data.total || 0) / (data.per_page || 15)));
        if (totalPages <= 1) return '';
        var html = '<button type="button" class="btn btn-sm btn-outline-secondary ff-page-btn" data-page="' + (data.page - 1) + '"' + (data.page <= 1 ? ' disabled' : '') + '>Prev</button>' +
            '<span style="font-size:12.5px;color:#6b7280;">Page ' + data.page + ' of ' + totalPages + ' &nbsp;(' + data.total + ' total)</span>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary ff-page-btn" data-page="' + (data.page + 1) + '"' + (data.page >= totalPages ? ' disabled' : '') + '>Next</button>';
        return html;
    }

    function ffStatusLabel(status) {
        return status === 'on_track' ? 'On Track' : (status === 'behind' ? 'Behind' : 'Total');
    }

    function ffUpdateCountBadge(data, status) {
        var count = data && typeof data.total !== 'undefined' ? data.total : 0;
        $('#ffCountBadge').text(count + ' ' + ffStatusLabel(status));
    }

    function ffLoad(tab, page, status, search) {
        var key = tab + '-' + status + '-' + page + '-' + search;
        $('#ffListBody').html('<div style="color:#9ca3af;font-size:13px;padding:20px 0;text-align:center;">Loading&hellip;</div>');
        $('#ffPagination').html('');
        if (ffCache[key]) {
            $('#ffListBody').html(ffRenderRows(ffCache[key]));
            $('#ffPagination').html(ffRenderPagination(ffCache[key]));
            ffUpdateCountBadge(ffCache[key], status);
            return;
        }
        var params = { tab: tab, page: page, month: ffMonth, status: status, q: search };
        if (viewBdmId) { params.view_bdm_id = viewBdmId; }
        $.getJSON('get-filled-firka-tps.php', params, function (data) {
            ffCache[key] = data;
            $('#ffListBody').html(ffRenderRows(data));
            $('#ffPagination').html(ffRenderPagination(data));
            ffUpdateCountBadge(data, status);
        }).fail(function () {
            $('#ffListBody').html('<div style="color:#b91c1c;font-size:13px;padding:20px 0;text-align:center;">Could not load data.</div>');
            $('#ffCountBadge').text('');
        });
    }

    $(document).on('click', '.ff-weekly-trigger', function () {
        $('#' + $(this).data('target')).toggle();
    });

    $('#ffSearchBox').on('input', function () {
        var val = $(this).val();
        clearTimeout(ffSearchTimer);
        ffSearchTimer = setTimeout(function () {
            ffCurrentSearch = val;
            ffCurrentPage = 1;
            ffLoad(ffCurrentTab, ffCurrentPage, ffCurrentStatus, ffCurrentSearch);
        }, 350);
    });

    $('#ffTabs button').on('click', function () {
        $('#ffTabs button').removeClass('active');
        $(this).addClass('active');
        ffCurrentTab = $(this).data('tab');
        ffCurrentPage = 1;
        ffLoad(ffCurrentTab, ffCurrentPage, ffCurrentStatus, ffCurrentSearch);
    });

    $('#ffStatusFilters .ff-status-btn').on('click', function () {
        $('#ffStatusFilters .ff-status-btn').removeClass('active');
        $(this).addClass('active');
        ffCurrentStatus = $(this).data('status');
        ffCurrentPage = 1;
        ffLoad(ffCurrentTab, ffCurrentPage, ffCurrentStatus, ffCurrentSearch);
    });

    $(document).on('click', '.ff-page-btn', function () {
        if ($(this).is(':disabled')) return;
        ffCurrentPage = parseInt($(this).data('page'), 10);
        ffLoad(ffCurrentTab, ffCurrentPage, ffCurrentStatus, ffCurrentSearch);
    });

    ffLoad(ffCurrentTab, ffCurrentPage, ffCurrentStatus, ffCurrentSearch);
})(jQuery);
</script>
</body>
</html>
