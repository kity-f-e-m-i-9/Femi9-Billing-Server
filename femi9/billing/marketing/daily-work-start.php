<?php
// "Daily Work Start" — a DM reviews today's Firka->TP roster (from "Daily
// Assign District TPs") and, for each one still unresolved ('none'), asks
// ASM/Company to confirm it ("Ask ASM/Company") — only once ASM/Company
// actually resolves that request (work-start-requests.php +
// work-start-resolve-action.php) does tp_approval_status flip to 'accepted'
// and this DM's own portal unlocks.
//
// The DM's own "TP Approve" self-accept button (click it yourself, no one
// else involved) was removed 2026-11-30 per user request ("no tp approve
// click pannura optioney venam, ask ASM/Company nu mattum than irukanum,
// asm/Company yaravathu accept pannanum apo tha login work aganum") — ASM or
// Company must now explicitly accept every Firka before the DM can proceed,
// the DM can no longer self-clear it. Nothing ever sets tp_approval_status
// to 'requested'/'denied' any more either (see marketing/daily-work-
// start-action.php and territory-partner/checksession.php's own history) —
// those two display branches below are dead code left over from the earlier
// TP-side Accept/Deny flow, kept only so any pre-existing row still sitting
// in one of those states (from before this change) still renders sensibly
// instead of erroring.
include("checksession.php");
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/../shared/TpWorkStart.php';
error_reporting(0);

tpWorkStartEnsureColumns($db_conn);

$msId = (int)$Login_user_IDvl;
$scopeNodes = getMsAssignedTargetScopeNodes($db_conn, $msId);
$scopeIds = array_column($scopeNodes, 'id');

// Date filter — defaults to today. The live lock gate below always checks
// TODAY specifically (dmHasBlockingWorkStartToday() is hardcoded to
// CURDATE()), so switching this filter to a past date never bypasses
// anything — it only changes which day's roster this page displays.
// 2026-11-25, user-requested ("date filter vechu, ethana per waiting, ethana
// per accept nu therinjum").
$__dwsViewDate = date('Y-m-d');
if (!empty($_GET['date'])) {
    $__dwsParsed = DateTime::createFromFormat('Y-m-d', $_GET['date']);
    if ($__dwsParsed && $__dwsParsed->format('Y-m-d') === $_GET['date']) {
        $__dwsViewDate = $_GET['date'];
    }
}

$rows = fetchTodayWorkStartRows($db_conn, $scopeIds, $__dwsViewDate);

// Summary counts for the filtered date — "Waiting" = still escalated and
// unresolved; "Resolved/Working" = either the company resolved it or the TP
// itself is marked accepted (old pre-2026-11-30 flow, kept for historical
// rows); everything else ('none' with no escalation yet, or a Vacant Area
// never escalated) falls into "Not Yet Escalated".
$__dwsCountWaiting  = 0;
$__dwsCountResolved = 0;
$__dwsCountOther    = 0;
foreach ($rows as $__r) {
    if ($__r['company_request_status'] === 'pending') { $__dwsCountWaiting++; }
    elseif ($__r['company_request_status'] === 'resolved' || $__r['tp_approval_status'] === 'accepted') { $__dwsCountResolved++; }
    else { $__dwsCountOther++; }
}

// Whether THIS viewer can act as "ASM/Company" and resolve a pending
// escalation right here — same subordinate check work-start-resolve-
// action.php itself enforces (getMsSubtreeIds() always includes the caller,
// so >1 means at least one real subordinate). An ASM/SM who also happens to
// hold their own direct District/Division assignment sees their
// subordinates' escalated Firka rows on THIS page (same reason
// checksession.php's self-lock had to learn to skip them) but previously had
// no way to resolve one without a separate trip to work-start-requests.php —
// 2026-11-25, user-reported ("ASM um work start panna request approve
// pannanum").
require_once __DIR__ . '/include/TeamSubtree.php';
$__dwsSubtreeIds = getMsSubtreeIds($db_conn, $msId);
$__dwsCanResolve = count($__dwsSubtreeIds) > 1;

// While checksession.php's gate is actively forcing the DM here (something
// today is still unresolved), hide the sidebar entirely so they can't click
// away to another page and bounce right back — once everything clears, a
// voluntary visit to this page shows the normal sidebar again. This only
// ever applies to a plain DM (checksession.php's self-lock itself now skips
// anyone with subordinates, see 2026-11-25 note there) — an ASM/SM landing
// here to resolve a subordinate's escalation was never actually locked out
// of the rest of the portal, so hiding their sidebar too was just a
// confusing leftover of the same gate query being reused here without that
// same exemption. 2026-11-25, user-reported ("sidebar enga, idhu ipadi
// iruka koodathu").
$__dwsGateLocked = !$__dwsCanResolve && dmHasBlockingWorkStartToday($db_conn, $scopeIds);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily Work Start : <?php echo $business_name; ?></title>
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
        /* Theme colors matched to main.min.css: primary #2269f5, success #4bad48,
           danger #ff4857, warning #ff9500 — same palette every other page uses. */
        body { font-family: 'Poppins', sans-serif; }
        .card { border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border:none; margin-bottom:20px; }
        .dws-empty { color:#9ca3af; font-size:14px; padding:30px; text-align:center; }
        .dws-row { padding:14px 18px; border-bottom:1px solid #f0f0f0; display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap; }
        .dws-row:last-child { border-bottom:none; }
        .dws-main { min-width:0; flex:1 1 220px; }
        .dws-side { flex:0 0 auto; display:flex; flex-direction:column; align-items:flex-end; gap:6px; }
        .dws-firka { font-weight:700; font-size:14.5px; color:#1f2937; word-break:break-word; }
        .dws-meta { font-size:12.5px; color:#6b7280; margin-top:2px; }
        .dws-tp { font-size:13.5px; font-weight:600; margin-top:4px; }
        .dws-tp.vacant { color:#ff9500; }
        .dws-tp.filled { color:#4bad48; }
        .dws-reason { background:#fff5f5; border:1px solid #ffd2d6; border-radius:7px; padding:9px 13px; font-size:12.5px; color:#ff4857; max-width:100%; }
        .dws-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .dws-filter-bar { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
        .dws-summary { display:flex; gap:10px; flex-wrap:wrap; }
        .dws-summary .badge { font-size:12.5px; padding:7px 13px; }
        .dws-resolved-meta { font-size:11.5px; color:#4bad48; margin-top:2px; text-align:right; }
        @media (max-width: 575px) {
            .dws-row { flex-direction:column; align-items:stretch; }
            .dws-side { align-items:stretch; }
            .dws-actions { justify-content:stretch; }
            .dws-actions .btn { flex:1 1 auto; }
        }
    </style>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <?php if (!$__dwsGateLocked): ?>
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <?php endif; ?>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>Daily Work Start — <?php echo date('d M Y', strtotime($__dwsViewDate)); ?></h1>
                            </div>
                        </div>
                    </div>

                    <?php if ($__dwsGateLocked): ?>
                    <div class="alert alert-warning" style="border-radius:10px;">
                        Complete today's work-start confirmation for every Firka below to continue using the portal — other pages are locked until each one is Resolved by ASM/Company.
                    </div>
                    <?php else: ?>
                    <div class="dws-filter-bar">
                        <form method="get" action="daily-work-start.php" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;">
                            <div>
                                <label class="form-label" style="margin-bottom:4px;">Date</label>
                                <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($__dwsViewDate); ?>" onchange="this.form.submit();">
                            </div>
                        </form>
                        <div class="dws-summary">
                            <span class="badge badge-style-bordered badge-primary"><?php echo $__dwsCountWaiting; ?> Waiting for ASM/Company</span>
                            <span class="badge badge-style-bordered badge-success"><?php echo $__dwsCountResolved; ?> Resolved / Working</span>
                            <?php if ($__dwsCountOther > 0): ?>
                            <span class="badge badge-style-bordered badge-secondary"><?php echo $__dwsCountOther; ?> Not Yet Escalated</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-body" style="padding:0;">
                            <div id="dwsList">
                                <?php if (empty($rows)): ?>
                                <div class="dws-empty">No Firka assigned for <?php echo htmlspecialchars(date('d M Y', strtotime($__dwsViewDate))); ?> in your area.</div>
                                <?php else: foreach ($rows as $r): ?>
                                <div class="dws-row" data-id="<?php echo (int)$r['id']; ?>" data-has-tp="<?php echo $r['territory_partner_id'] ? '1' : '0'; ?>" data-tp-id="<?php echo (int)($r['territory_partner_id'] ?? 0); ?>" data-tp-name="<?php echo htmlspecialchars($r['tp_name'] ?? '', ENT_QUOTES); ?>" data-tp-code="<?php echo htmlspecialchars($r['tp_code'] ?? '', ENT_QUOTES); ?>">
                                    <div class="dws-main">
                                        <div class="dws-firka"><?php echo htmlspecialchars($r['firka_name']); ?></div>
                                        <div class="dws-meta"><?php echo htmlspecialchars($r['district_name']); ?></div>
                                        <div class="dws-tp <?php echo $r['territory_partner_id'] ? 'filled' : 'vacant'; ?>">
                                            <?php echo $r['territory_partner_id'] ? htmlspecialchars($r['tp_name']) . ' (' . htmlspecialchars($r['tp_code']) . ')' : 'Vacant Area'; ?>
                                        </div>
                                        <?php if ($r['tp_approval_status'] === 'denied'): ?>
                                        <div class="dws-reason" style="margin-top:8px;"><b>TP denied:</b> <?php echo nl2br(htmlspecialchars($r['tp_denial_reason'] ?? '')); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="dws-side">
                                    <?php if ($r['company_request_status'] === 'pending'): ?>
                                        <span class="badge badge-style-bordered badge-primary">Waiting for ASM/Company</span>
                                        <?php if (!empty($r['company_requested_at'])): ?>
                                        <div class="dws-meta" style="margin-top:2px;">Requested: <?php echo htmlspecialchars(date('d-m-Y h:i A', strtotime($r['company_requested_at']))); ?></div>
                                        <?php endif; ?>
                                        <?php if ($__dwsCanResolve): ?>
                                        <div class="dws-actions">
                                            <button type="button" class="btn btn-sm btn-success" data-act="resolve">Resolve (Confirm TP)</button>
                                        </div>
                                        <?php endif; ?>
                                    <?php elseif ($r['company_request_status'] === 'resolved'): ?>
                                        <span class="badge badge-style-bordered badge-success">Resolved by Company — Working today</span>
                                        <?php if (!empty($r['company_resolved_at'])): ?>
                                        <div class="dws-resolved-meta">Accepted: <?php echo htmlspecialchars(date('d-m-Y h:i A', strtotime($r['company_resolved_at']))); ?><?php if (!empty($r['company_resolved_by'])): ?> by <?php echo htmlspecialchars($r['company_resolved_by']); ?><?php endif; ?></div>
                                        <?php endif; ?>
                                    <?php elseif ($r['tp_approval_status'] === 'accepted'): ?>
                                        <span class="badge badge-style-bordered badge-success">Working today</span>
                                        <?php if (!empty($r['tp_responded_at'])): ?>
                                        <div class="dws-resolved-meta">Accepted: <?php echo htmlspecialchars(date('d-m-Y h:i A', strtotime($r['tp_responded_at']))); ?></div>
                                        <?php endif; ?>
                                    <?php elseif ($r['tp_approval_status'] === 'requested'): ?>
                                        <span class="badge badge-style-bordered badge-warning">Waiting for TP to respond</span>
                                    <?php elseif ($r['tp_approval_status'] === 'denied'): ?>
                                        <div class="dws-actions">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-act="ask_company">Ask ASM/Company</button>
                                        </div>
                                    <?php else: /* 'none' */ ?>
                                        <div class="dws-actions">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-act="ask_company">Ask ASM/Company</button>
                                        </div>
                                    <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
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
<script src="../../assets/js/custom.js"></script>
<script>
(function ($) {
    $('#dwsList').on('click', '[data-act]', function () {
        var $btn = $(this);
        var $row = $btn.closest('.dws-row');
        var id = $row.data('id');
        var act = $btn.data('act');
        $row.find('button').prop('disabled', true);

        // "resolve" (ASM/SM confirming a subordinate's escalated Firka right
        // here) hits the same endpoint work-start-requests.php itself posts
        // to — keeps the TP already on the row instead of clearing it.
        var url = (act === 'resolve') ? 'work-start-resolve-action.php' : 'daily-work-start-action.php';
        var payload = (act === 'resolve') ? { id: id, tp_id: $row.data('tp-id') || '' } : { id: id, action: act };

        $.post(url, payload, function (resp) {
            if (resp && resp.success) {
                location.reload();
            } else {
                alert((resp && resp.error) || 'Failed.');
                $row.find('button').prop('disabled', false);
            }
        }, 'json').fail(function () {
            alert('Failed. Please try again.');
            $row.find('button').prop('disabled', false);
        });
    });
})(jQuery);
</script>
</body>
</html>
