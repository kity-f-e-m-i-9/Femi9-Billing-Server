<?php
// Mandatory "start work today" confirmation — the TP is redirected here by
// checksession.php whenever a DM has sent a work-start request for one of
// today's Firkas they cover, and can't reach any other page until every
// pending request here is Accepted or Denied. No close/back option by
// design (2026-11-12, user request) -- this is a dedicated full page, not a
// dismissable modal, same as this portal's existing Agreement gate.
include("checksession.php");
require_once __DIR__ . '/../shared/TpWorkStart.php';
error_reporting(0);

tpWorkStartEnsureColumns($db_conn);
$tpId = (int)$Login_user_IDvl;
$pending = fetchTpPendingWorkStartRequests($db_conn, $tpId);

// Nothing pending (TP navigated here directly, or just resolved the last
// one) -- send them on to the dashboard instead of showing an empty gate.
if (empty($pending)) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm Today's Work : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        /* Theme colors matched to main.min.css: success #4bad48, danger #ff4857, warning #ff9500. */
        body { font-family:'Poppins',sans-serif; background:#f3f4f6; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .wsc-card { background:#fff; border-radius:14px; box-shadow:0 10px 40px rgba(0,0,0,0.1); max-width:480px; width:100%; padding:30px; }
        .wsc-title { font-size:18px; font-weight:700; color:#1f2937; margin-bottom:6px; }
        .wsc-sub { font-size:13px; color:#6b7280; margin-bottom:20px; }
        .wsc-item { border:1px solid #e5e7eb; border-radius:10px; padding:16px; margin-bottom:14px; }
        .wsc-firka { font-weight:700; font-size:15px; color:#1f2937; word-break:break-word; }
        .wsc-meta { font-size:12.5px; color:#6b7280; margin-top:2px; margin-bottom:12px; }
        .wsc-actions { display:flex; gap:10px; flex-wrap:wrap; }
        .btn-start { flex:1 1 140px; background:#4bad48; color:#fff; border:none; border-radius:8px; padding:10px; font-size:14px; font-weight:600; }
        .btn-cant { flex:1 1 140px; background:#fff; color:#ff4857; border:1px solid #ffd2d6; border-radius:8px; padding:10px; font-size:14px; font-weight:600; }
        .wsc-waiting { background:#fff8ef; border:1px solid #ffdbab; border-radius:7px; padding:10px 13px; font-size:12.5px; color:#b35e00; }
        .wsc-logout { display:block; text-align:center; margin-top:10px; font-size:12.5px; color:#9ca3af; text-decoration:underline; }
        @media (max-width: 400px) {
            .wsc-card { padding:22px 18px; }
        }
    </style>
</head>
<body>
<div class="wsc-card">
    <div class="wsc-title">Confirm today's work</div>
    <div class="wsc-sub">Your DM has requested you to start work today for the following area<?php echo count($pending) > 1 ? 's' : ''; ?>. Please respond before continuing.</div>
    <div id="wscList">
        <?php foreach ($pending as $p): ?>
        <div class="wsc-item" data-id="<?php echo (int)$p['id']; ?>">
            <div class="wsc-firka"><?php echo htmlspecialchars($p['firka_name']); ?></div>
            <div class="wsc-meta"><?php echo htmlspecialchars($p['district_name']); ?></div>
            <?php if ($p['tp_approval_status'] === 'denied'): ?>
            <div class="wsc-waiting">
                You said you can't work today for this area.
                <?php echo $p['company_request_status'] === 'pending' ? 'Your DM has informed ASM/Company — please wait for them to resolve it.' : 'Please inform your DM so they can notify ASM/Company.'; ?>
            </div>
            <?php else: ?>
            <div class="wsc-actions">
                <button type="button" class="btn-start" data-act="accept">I'm starting work</button>
                <button type="button" class="btn-cant" data-act="deny">Can't work today</button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <a href="logout.php" class="wsc-logout">Logout</a>
</div>

<!-- Deny reason modal -->
<div class="modal fade" id="wscDenyModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Why can't you work today?</h5>
            </div>
            <div class="modal-body">
                <textarea id="wscDenyReason" class="form-control" rows="3" placeholder="Reason (required)"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="wscDenySubmit">Submit</button>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script>
(function ($) {
    var $denyModal = new bootstrap.Modal(document.getElementById('wscDenyModal'));
    var $activeItem = null;

    function removeItem($item) {
        $item.fadeOut(200, function () {
            $item.remove();
            if ($('#wscList .wsc-item').length === 0) { location.href = 'dashboard.php'; }
        });
    }

    $('#wscList').on('click', '.btn-start', function () {
        var $item = $(this).closest('.wsc-item');
        var id = $item.data('id');
        $item.find('button').prop('disabled', true);
        $.post('work-start-confirm-action.php', { id: id, action: 'accept' }, function (resp) {
            if (resp && resp.success) { removeItem($item); }
            else { alert((resp && resp.error) || 'Failed.'); $item.find('button').prop('disabled', false); }
        }, 'json');
    });

    $('#wscList').on('click', '.btn-cant', function () {
        $activeItem = $(this).closest('.wsc-item');
        $('#wscDenyReason').val('');
        $denyModal.show();
    });

    $('#wscDenySubmit').on('click', function () {
        var reason = $.trim($('#wscDenyReason').val());
        if (!reason) { alert('Please enter a reason.'); return; }
        if (!$activeItem) return;
        var id = $activeItem.data('id');
        $.post('work-start-confirm-action.php', { id: id, action: 'deny', reason: reason }, function (resp) {
            if (resp && resp.success) { $denyModal.hide(); removeItem($activeItem); }
            else { alert((resp && resp.error) || 'Failed.'); }
        }, 'json');
    });
})(jQuery);
</script>
</body>
</html>
