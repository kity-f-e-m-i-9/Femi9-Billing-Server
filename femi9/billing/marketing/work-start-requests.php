<?php
// ASM-side twin of company/work-start-requests.php — scoped to the logged-in
// ASM/SM's own subtree (self + every subordinate's assigned districts), same
// scoping rule tp-assign-firka-action.php's save path uses. Approving here
// unlocks the escalating DM's own portal — never the TP's (that separate
// TP-side lock was removed 2026-11-24, so the button below was renamed from
// "...Unlock TP..." 2026-11-30, user-reported stale wording).
include("checksession.php");
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/TeamSubtree.php';
require_once __DIR__ . '/../shared/TpWorkStart.php';
error_reporting(0);

tpWorkStartEnsureColumns($db_conn);

$msId = (int)$Login_user_IDvl;
$subtreeIds = getMsSubtreeIds($db_conn, $msId);
$allowedDistrictIds = [];
foreach ($subtreeIds as $sid) {
    foreach (getMsAssignedDistricts($db_conn, $sid) as $d) {
        $allowedDistrictIds[(int)$d['id']] = true;
    }
}
$scopeIds = array_keys($allowedDistrictIds);

$pendingRows = fetchPendingCompanyWorkStartRequests($db_conn, $scopeIds);

$allTps = [];
$res = $db_conn->query("SELECT id, name, tp_id FROM territory_partners WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name ASC");
if ($res) { while ($r = $res->fetch_assoc()) { $allTps[] = $r; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Work Start Requests : <?php echo $business_name; ?></title>
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
        body { font-family: 'Poppins', sans-serif; }
        .card { border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border:none; margin-bottom:20px; }
        .wsr-empty { color:#9ca3af; font-size:14px; padding:30px; text-align:center; }
        .wsr-row { padding:16px 20px; border-bottom:1px solid #f0f0f0; }
        .wsr-row:last-child { border-bottom:none; }
        .wsr-firka { font-weight:700; font-size:14.5px; color:#1f2937; }
        .wsr-meta { font-size:12.5px; color:#6b7280; margin-top:2px; }
        .wsr-note { background:#fff8ef; border:1px solid #ffdbab; border-radius:7px; padding:9px 13px; margin-top:10px; font-size:13px; color:#b35e00; }
        .wsr-resolve { display:flex; gap:8px; align-items:center; margin-top:12px; flex-wrap:wrap; }
        .wsr-resolve select { max-width:260px; flex:1 1 200px; }
        @media (max-width: 575px) {
            .wsr-resolve { flex-direction:column; align-items:stretch; }
            .wsr-resolve select { max-width:none; }
            .wsr-resolve .btn-resolve { width:100%; }
        }
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
                                <h1>Work Start Requests</h1>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body" style="padding:0;">
                            <div id="wsrList">
                                <?php if (empty($pendingRows)): ?>
                                <div class="wsr-empty">No pending work-start requests right now.</div>
                                <?php else: foreach ($pendingRows as $r): ?>
                                <div class="wsr-row" data-id="<?php echo (int)$r['id']; ?>">
                                    <div class="wsr-firka"><?php echo htmlspecialchars($r['firka_name']); ?></div>
                                    <div class="wsr-meta">
                                        <?php echo htmlspecialchars($r['district_name']); ?> &middot;
                                        <?php echo date('d M Y', strtotime($r['assign_date'])); ?>
                                        <?php if ($r['territory_partner_id']): ?>
                                        &middot; Currently assigned: <b><?php echo htmlspecialchars($r['tp_name']); ?> (<?php echo htmlspecialchars($r['tp_code']); ?>)</b>
                                        <?php endif; ?>
                                        <?php if (!empty($r['company_requested_at'])): ?>
                                        &middot; Requested: <?php echo htmlspecialchars(date('d-m-Y h:i A', strtotime($r['company_requested_at']))); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="wsr-note"><?php echo htmlspecialchars($r['company_request_note'] ?? ''); ?></div>
                                    <div class="wsr-resolve">
                                        <select class="form-control form-control-sm wsr-tp-select">
                                            <option value="">-- Vacant Area --</option>
                                            <?php foreach ($allTps as $tp): ?>
                                            <option value="<?php echo (int)$tp['id']; ?>" <?php echo ((int)$tp['id'] === (int)$r['territory_partner_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tp['name']); ?> (<?php echo htmlspecialchars($tp['tp_id']); ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-sm btn-primary btn-resolve">Approve &amp; Resolve for Today</button>
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
    $('#wsrList').on('click', '.btn-resolve', function () {
        var $row = $(this).closest('.wsr-row');
        var id = $row.data('id');
        var tpId = $row.find('.wsr-tp-select').val();
        $.post('work-start-resolve-action.php', { id: id, tp_id: tpId }, function (resp) {
            if (resp && resp.success) {
                $row.fadeOut(200, function () {
                    $row.remove();
                    if ($('#wsrList .wsr-row').length === 0) {
                        $('#wsrList').html('<div class="wsr-empty">No pending work-start requests right now.</div>');
                    }
                });
            } else {
                alert((resp && resp.error) || 'Failed to resolve.');
            }
        }, 'json');
    });
})(jQuery);
</script>
</body>
</html>
