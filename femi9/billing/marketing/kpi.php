<?php
// Personal/team KPI scorecard page — a free From/To date range (not tied to
// a calendar month or week) drives a 5-KPI breakdown per person, each with
// its own Plan/Achieved/Ach%/Score, rolled up into an overall KPI Score.
// Works for whoever is logged in: an SM sees their ASMs and under each ASM
// their DMs; a lone DM with no team just sees their own row. Reuses the
// exact Target/Achieved numbers and rollup rule (zero a manager's own target
// once they have a team, roll up the children's sum instead) that
// my-team.php already established.
include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/KpiDataPrep.php';
require_once __DIR__ . '/../shared/TpStaffFeedback.php';

// Layer 2 — "Your Total Score", the cumulative average of every month-end TP
// feedback rating this person has received as a DM (see
// shared/TpStaffFeedback.php), scoped to whichever months fall inside this
// page's own $fromDate/$toDate range — same range every other KPI figure on
// this page already respects, so picking a past month's range shows that
// past month's own mark instead of always every month ever. 2026-11-24,
// user-requested ("pona month filter pottalaum athu pona month potta mark
// than kattanum"). Only a District-depth person is ever a 'dm' rating
// target (getTpAssignedDmRows() only matches District-level staff), so this
// stays hidden for an ASM/SM with no District level of their own.
// Per-TP breakdown is included here but only ever RENDERED when viewed via
// the Company "login as this DM" bridge — kept anonymous on the DM's own
// direct login, by design (2026-11-24, user-requested: "dm login la direct
// ahh partha antha tp yaaru yaaru evvalvau score pottu irukaganu katta
// kudathu... company login la irunthu avnga login ku pona kattanum").
$myLayer2Score = null;
if (!empty($selfRow) && stripos($selfRow['level_name'] ?? '', 'District') !== false) {
    $myLayer2Score = getCumulativeTpFeedbackScore($db_conn, 'dm', (int)$selfRow['id'], $fromDate, $toDate);
}
$myLayer2CanSeeBreakdown = !empty($_SESSION['LOGGED_IN_VIA_COMPANY']) || !empty($_companyBridgeView);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KPI : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
        .tp-tag { font-size:11px; padding:2px 8px; border-radius:6px; font-weight:600; white-space:nowrap; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th {
            background:#f7f7f6; font-weight:600; color:#52514e; padding:9px 12px; text-align:left;
            border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px;
        }
        .mt td { padding:9px 12px; border-bottom:1px solid #e1e0d9; vertical-align:middle; white-space:nowrap; }
        .mt th.kpi-group { text-align:center; background:#eef2ff; border-left:1px solid #d7dafc; }
        .kpi-hover-cell { cursor:pointer; border-bottom:1px dotted #6366f1; }
        #kpiTipBox {
            position:fixed; z-index:9999; background:#111827; color:#fff; padding:8px 10px;
            border-radius:6px; font-size:12px; line-height:1.5; max-width:280px; display:none;
            font-family:arial,"DejaVu Sans"; box-shadow:0 4px 12px rgba(0,0,0,.25);
        }
        /* Solo-view KPI cards — one card per KPI component instead of one
           40+-column table row, for a DM with no subordinates. 2026-11-21. */
        .kpi-card-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:14px; }
        .kpi-card { background:#fff; border:1px solid #e1e0d9; border-radius:10px; padding:16px 18px; }
        .kpi-card-head { display:flex; align-items:center; gap:8px; font-weight:600; font-size:13.5px; color:#1f2937; margin-bottom:8px; }
        .kpi-card-head i { color:#6366f1; font-size:19px; }
        .kpi-card-desc { font-size:12px; color:#6b7280; margin-bottom:12px; min-height:32px; }
        .kpi-card-stats { display:flex; gap:14px; border-top:1px solid #f1f5f9; padding-top:10px; margin-bottom:8px; }
        .kpi-card-stats > div { display:flex; flex-direction:column; flex:1; }
        .kpi-card-label { font-size:10.5px; color:#9ca3af; text-transform:uppercase; letter-spacing:.3px; margin-bottom:2px; }
        .kpi-card-val { font-size:13.5px; font-weight:600; color:#1f2937; }
        .kpi-card-score { font-size:15px; font-weight:700; text-align:right; }
        .kpi-card-total { margin-top:16px; text-align:right; font-size:15px; color:#374151; }
        @media (max-width:576px) { .kpi-card-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body<?php echo $embed ? ' class="ms-embed"' : ''; ?>>
<div class="app align-content-stretch d-flex flex-wrap">

    <?php if (!$embed): ?>
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <?php endif; ?>

    <div class="app-container">
        <?php if (!$embed) include("app-header.php"); ?>

        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">

                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble">
                                        <tr><td>KPI</td></tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($_companyBridgeView) && !$embed): ?>
                        <div class="row">
                            <div class="col">
                                <div class="alert alert-info" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
                                    <span><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">visibility</i> Viewing <b><?php echo htmlspecialchars($result_LoGuserDtails['ms_name'] ?? ''); ?>'s</b> KPI (read-only).</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!$selfRow || !$selfRow['team_level_id']): ?>
                        <div class="row">
                            <div class="col">
                                <div class="alert alert-info">No team level has been assigned to you yet.</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row mb-2">
                            <div class="col">
                                <p class="text-muted" style="font-size:12px;margin-bottom:6px;">
                                    KPI Scorecard for <?php echo date('d M Y', strtotime($fromDate)); ?> - <?php echo date('d M Y', strtotime($toDate)); ?>
                                </p>
                                <p class="text-muted" style="font-size:11px;margin-bottom:0;">
                                    Layer 1 &mdash; Business Impact, scored here out of 60 marks. Per the official KPI Structure document this layer is actually 65 marks, but Monthly Promotional Activities (5%) has no DB signal anywhere in the system, so it's scored manually, outside this page, and left off this scorecard entirely. Layer 2 (Professional Excellence) and Innovation &amp; Value Creation are not included here &mdash; those are manual, multi-reviewer scores.
                                </p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <form method="get" class="d-flex flex-wrap align-items-end gap-2">
                                    <div>
                                        <label class="form-label mb-0" style="font-size:11px;font-weight:600;color:#6b7280;">From Date</label>
                                        <input type="date" name="from_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($fromDate); ?>">
                                    </div>
                                    <div>
                                        <label class="form-label mb-0" style="font-size:11px;font-weight:600;color:#6b7280;">To Date</label>
                                        <input type="date" name="to_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($toDate); ?>">
                                    </div>
                                    <div>
                                        <button type="submit" class="btn btn-primary btn-sm">Show</button>
                                        <?php if ($fromDate !== date('Y-m-01') || $toDate !== date('Y-m-d')): ?>
                                            <a href="kpi" class="btn btn-outline-secondary btn-sm">This Month</a>
                                        <?php endif; ?>
                                        <button type="button" id="kpiWeeklyToggleBtn" class="btn btn-outline-primary btn-sm">Weekly</button>
                                        <a href="export-kpi-xlsx?from_date=<?php echo urlencode($fromDate); ?>&to_date=<?php echo urlencode($toDate); ?>" class="btn btn-outline-success btn-sm"><i class="material-icons-outlined" style="font-size:15px;vertical-align:middle;">download</i> Export Excel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php $isSoloView = empty($byManager[(int)$selfRow['id']] ?? []); ?>
                        <?php // Personal scorecard — own numbers only (byManager=[] forces
                        // computeKpiRowValues to treat this person as having no subtree, so
                        // an ASM/SM sees their OWN 9 cards here, same as a solo DM always
                        // has, instead of their team's rolled-up totals). 2026-11-30,
                        // user-requested ("asm ku dm mathiri avnagalodathu 9 card ahh
                        // kattanum") — previously an ASM/SM saw only the team table below,
                        // never their own personal scorecard. ?>
                        <div class="row" id="kpiMainViewRow">
                            <div class="col">
                                <?php if (!$isSoloView): ?><h6 class="mb-2" style="font-weight:600;">Your Own Scorecard</h6><?php endif; ?>
                                <?php echo renderKpiCards(computeKpiRowValues($selfRow, [], $rawStats, $dailyByMsId, $levelColorMap, 0, $fromDate, $daysInRange, $newShopCountByMsId, $marketWorkDatesByMsId, $workingDaysPlan, $kx)); ?>
                            </div>
                        </div>
                        <?php if (!$isSoloView): ?>
                        <div class="row mt-3" id="kpiTeamViewRow">
                            <div class="col">
                                <h6 class="mb-2" style="font-weight:600;">Team</h6>
                                <div class="card">
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <input type="text" id="kpiSearchBox" class="form-control form-control-sm" style="max-width:320px;" placeholder="Search by Name...">
                                        </div>
                                        <div style="overflow-x:auto;">
                                        <table class="mt">
                                            <thead>
                                                <tr>
                                                    <th rowspan="2">Name</th>
                                                    <th rowspan="2">Level</th>
                                                    <th colspan="4" class="kpi-group">1. Napkin Sales Target Ach. (15%)</th>
                                                    <th colspan="4" class="kpi-group">2. District Sales Overachievement (10%)</th>
                                                    <th colspan="4" class="kpi-group">3. Weekly Sales Target Ach. (5%)</th>
                                                    <th colspan="4" class="kpi-group">4. Early Month Target Ach. (5%)</th>
                                                    <th colspan="4" class="kpi-group">5. Daily Market Visit &amp; Order (10%)</th>
                                                    <th colspan="4" class="kpi-group">6. New Shop Onboarding (5%)</th>
                                                    <th colspan="4" class="kpi-group">7. New Category Outlets (5%)</th>
                                                    <th colspan="4" class="kpi-group">8. Retail Shop Merchandising (5%)</th>
                                                    <th colspan="3" class="kpi-group">9. Sales Return Control (deduct 10)</th>
                                                    <th rowspan="2">KPI Score<br>(/ 60)</th>
                                                </tr>
                                                <tr>
                                                    <th>Plan</th><th>Ach</th><th>Ach%</th><th>Score</th>
                                                    <th>Eligibility</th><th>Ach%</th><th>vs Top</th><th>Score</th>
                                                    <th>Plan</th><th>Ach</th><th>Ach%</th><th>Score</th>
                                                    <th>Plan</th><th>Ach</th><th>Ach%</th><th>Score</th>
                                                    <th>Plan</th><th>Ach</th><th>Ach%</th><th>Score</th>
                                                    <th>Min</th><th>Ach</th><th>Ach%</th><th>Score</th>
                                                    <th>Min</th><th>Ach</th><th>Ach%</th><th>Score</th>
                                                    <th>Shops</th><th>Compliant</th><th>%</th><th>Score</th>
                                                    <th>Return%</th><th>Status</th><th>Penalty</th>
                                                </tr>
                                            </thead>
                                            <tbody id="kpiMainTableBody">
                                            <?php echo renderKpiRow($selfRow, $byManager, $rawStats, $dailyByMsId, $levelColorMap, 0, $fromDate, $daysInRange, $newShopCountByMsId, $marketWorkDatesByMsId, $workingDaysPlan, $newShopPlan, $kx); ?>
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($myLayer2Score !== null): ?>
                        <div class="row mt-3">
                            <div class="col">
                                <p class="text-muted" style="font-size:11px;margin-bottom:6px;">
                                    Layer 2 &mdash; Professional Excellence. Scored here from your own TPs' month-end feedback (5 questions, 1-5 stars each = out of 25 per TP per month) &mdash; the cumulative average of every rating received within the From/To range selected above.
                                </p>
                                <div class="kpi-card-grid">
                                    <div class="kpi-card">
                                        <div class="kpi-card-head"><i class="material-icons-outlined">star_rate</i> 1. Your Total Score</div>
                                        <div class="kpi-card-desc">Average TP feedback score for the selected date range (out of 25 per rating).</div>
                                        <div class="kpi-card-stats">
                                            <div>
                                                <span class="kpi-card-label">Average Score</span>
                                                <span class="kpi-card-val"><?php echo $myLayer2Score['count'] > 0 ? number_format($myLayer2Score['avg_score_25'], 1) . ' / 25' : 'No ratings yet'; ?></span>
                                            </div>
                                            <div>
                                                <span class="kpi-card-label">Ratings Received</span>
                                                <span class="kpi-card-val"><?php echo (int)$myLayer2Score['count']; ?></span>
                                            </div>
                                        </div>
                                        <?php if ($myLayer2CanSeeBreakdown && $myLayer2Score['count'] > 0): ?>
                                        <div style="margin-top:10px;border-top:1px solid #f1f5f9;padding-top:10px;">
                                            <p style="font-size:11px;color:#9ca3af;margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px;">Per-TP breakdown (visible to Company only)</p>
                                            <div style="max-height:180px;overflow-y:auto;">
                                                <?php foreach ($myLayer2Score['rows'] as $_r2): ?>
                                                <div style="display:flex;justify-content:space-between;font-size:12.5px;padding:3px 0;border-bottom:1px dotted #f1f5f9;">
                                                    <span><?php echo htmlspecialchars($_r2['tp_name'] ?? '-'); ?> (<?php echo htmlspecialchars($_r2['tp_code'] ?? '-'); ?>) &mdash; <?php echo htmlspecialchars($_r2['period_month']); ?></span>
                                                    <span style="font-weight:600;"><?php echo (int)$_r2['score_25']; ?> / 25</span>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row" id="kpiWeeklyViewRow" style="display:none;">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <p class="text-muted" style="font-size:12px;margin-bottom:10px;">
                                            Week-wise Target / Achieved (TP Invoice) / Balance for <?php echo date('d M', strtotime($fromDate)) . ' - ' . date('d M Y', strtotime($toDate)); ?> &mdash; each week's Target is this person's selected-range target prorated to that week's days, so the weekly totals add up to the Plan/Achieved shown above.
                                        </p>
                                        <div style="overflow-x:auto;">
                                        <table class="mt">
                                            <thead>
                                                <tr>
                                                    <th rowspan="2">Name</th>
                                                    <th rowspan="2">Level</th>
                                                    <?php foreach ($kpiWeeks as $week): ?>
                                                        <th colspan="3" class="kpi-group"><?php echo htmlspecialchars($week['label']); ?><br><span style="font-weight:400;"><?php echo date('d M', strtotime($week['start'])) . ' - ' . date('d M', strtotime($week['end'])); ?></span></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                                <tr>
                                                    <?php foreach ($kpiWeeks as $week): ?>
                                                        <th>Target</th><th>Achieved</th><th>Balance</th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php echo renderWeeklyKpiRow($selfRow, $byManager, $monthlyRawStats, $monthlyDailyByMsId, $levelColorMap, 0, $kpiWeeks, $wkDaysInRange); ?>
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<div id="kpiTipBox"></div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
(function () {
    var tipBox = document.getElementById('kpiTipBox');
    if (!tipBox) return;
    document.querySelectorAll('.kpi-hover-cell').forEach(function (cell) {
        cell.addEventListener('mouseenter', function () {
            tipBox.innerHTML = cell.getAttribute('data-kpi-tip') || '';
            tipBox.style.display = 'block';
        });
        cell.addEventListener('mousemove', function (e) {
            tipBox.style.left = (e.clientX + 14) + 'px';
            tipBox.style.top = (e.clientY + 14) + 'px';
        });
        cell.addEventListener('mouseleave', function () {
            tipBox.style.display = 'none';
        });
    });
})();

(function () {
    var searchBox = document.getElementById('kpiSearchBox');
    if (!searchBox) return;
    var rows = Array.prototype.slice.call(document.querySelectorAll('#kpiMainTableBody tr'));
    var rowById = {};
    rows.forEach(function (tr) { rowById[tr.getAttribute('data-id')] = tr; });

    function showWithAncestors(tr) {
        var cur = tr;
        while (cur) {
            cur.style.display = '';
            var parentId = cur.getAttribute('data-parent');
            cur = (parentId && parentId !== '0') ? rowById[parentId] : null;
        }
    }

    searchBox.addEventListener('input', function () {
        var q = searchBox.value.trim().toLowerCase();
        if (q === '') {
            rows.forEach(function (tr) { tr.style.display = ''; });
            return;
        }
        rows.forEach(function (tr) { tr.style.display = 'none'; });
        rows.forEach(function (tr) {
            var nameCell = tr.querySelector('.kpi-name');
            var nameText = nameCell ? nameCell.textContent.toLowerCase() : '';
            if (nameText.indexOf(q) !== -1) {
                showWithAncestors(tr);
            }
        });
    });
})();

(function () {
    var btn = document.getElementById('kpiWeeklyToggleBtn');
    var mainRow = document.getElementById('kpiMainViewRow');
    var weeklyRow = document.getElementById('kpiWeeklyViewRow');
    if (!btn || !mainRow || !weeklyRow) return;
    btn.addEventListener('click', function () {
        var showingWeekly = weeklyRow.style.display !== 'none';
        weeklyRow.style.display = showingWeekly ? 'none' : '';
        mainRow.style.display = showingWeekly ? '' : 'none';
        btn.textContent = showingWeekly ? 'Weekly' : 'Scorecard';
    });
})();
</script>
</body>
</html>
