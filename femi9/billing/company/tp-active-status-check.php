<?php include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once __DIR__ . '/../shared/TpStatusHistory.php';
error_reporting(0);
include("config.php");
ensureTpStatusLogTable($db_conn);

// Verification tool for the Active-TPs history fix — lets staff pick a
// From/To date range and see exactly which TPs the system considers
// active/inactive on any date in it, instead of just trusting the single
// rolled-up count on Our Team Report. Company-wide (all TPs); the
// salesbdm version of this same page (salesbdm/tp-active-status-check.php)
// scopes it to just that BDM's own assigned TPs.
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate   = $_GET['to_date']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) $fromDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate))   $toDate   = date('Y-m-d');
if ($fromDate > $toDate) { [$fromDate, $toDate] = [$toDate, $fromDate]; }

// Capped so this doesn't run a day-by-day snapshot query for months at a
// time — 31 days covers "check this whole month" (the actual use case)
// without the page turning slow.
$rangeCapped = false;
$fromTs = strtotime($fromDate);
$toTs = strtotime($toDate);
if (($toTs - $fromTs) / 86400 > 31) {
    $toTs = strtotime('+31 days', $fromTs);
    $toDate = date('Y-m-d', $toTs);
    $rangeCapped = true;
}

$allTpIds = array_column(
    $db_conn->query("SELECT id FROM territory_partners")->fetch_all(MYSQLI_ASSOC),
    'id'
);
$allTpIds = array_map('intval', $allTpIds);

// Main summary + lists below — snapshot at the END of the selected range
// (the most recent date being checked).
$asOfDate = $toDate;
$rows = getTpStatusRowsAsOf($db_conn, $allTpIds, $asOfDate);
$activeRows = array_values(array_filter($rows, fn($r) => $r['status_as_of'] === 1));
$inactiveRows = array_values(array_filter($rows, fn($r) => $r['status_as_of'] === 0));

// "Total" card — a separate snapshot at the END OF THAT MONTH (whichever
// month the To-Date falls in) — e.g. bonus-calculator runs can flip a
// batch of TPs inactive mid-month and reactivate them again before month
// end, so the day-level check above doesn't reflect where things actually
// landed once the month closed out.
$monthEndDate = date('Y-m-t', $toTs);
$monthEndRows = getTpStatusRowsAsOf($db_conn, $allTpIds, $monthEndDate);
$monthEndActiveRows = array_values(array_filter($monthEndRows, fn($r) => $r['status_as_of'] === 1));
$monthEndInactiveRows = array_values(array_filter($monthEndRows, fn($r) => $r['status_as_of'] === 0));

// Day-by-day breakdown across the whole selected range — the trend table
// below, and the source for each date row's click-to-expand detail modal.
$dailySnapshots = [];
for ($cursorTs = $fromTs; $cursorTs <= $toTs; $cursorTs = strtotime('+1 day', $cursorTs)) {
    $d = date('Y-m-d', $cursorTs);
    $dayRows = getTpStatusRowsAsOf($db_conn, $allTpIds, $d);
    $dayActive = array_values(array_filter($dayRows, fn($r) => $r['status_as_of'] === 1));
    $dayInactive = array_values(array_filter($dayRows, fn($r) => $r['status_as_of'] === 0));
    $dailySnapshots[$d] = [
        'active_count' => count($dayActive),
        'inactive_count' => count($dayInactive),
        'active' => array_map(fn($r) => ['name' => $r['name'], 'tp_id' => $r['tp_id'], 'district' => $r['district']], $dayActive),
        'inactive' => array_map(fn($r) => ['name' => $r['name'], 'tp_id' => $r['tp_id'], 'district' => $r['district']], $dayInactive),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Active TP Status Check : <?php echo $business_name; ?></title>
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
        .stat-card { background:#fff; border-radius:10px; padding:16px 18px; box-shadow:0 1px 4px rgba(0,0,0,0.06); text-align:center; }
        .stat-card .num { font-size:26px; font-weight:700; }
        .stat-card.active .num { color:#10b981; }
        .stat-card.inactive .num { color:#d03b3b; }
        .tp-balance-clickable { cursor:pointer; transition:box-shadow .15s, transform .15s; }
        .tp-balance-clickable:hover { box-shadow:0 4px 14px rgba(0,0,0,0.10); transform:translateY(-1px); }
        .tp-count-badge { display:inline-block; background:#eef0ff; color:#667eea; font-size:10px; font-weight:700; padding:1px 6px; border-radius:8px; margin-left:4px; vertical-align:middle; }
        .status-check-card-header { margin:0; font-weight:700; font-size:14px; color:#1f2937; }
        .status-check-card-header.active-heading { color:#0d9488; }
        .status-check-card-header.inactive-heading { color:#b91c1c; }
        .tp-check-table { width:100%; font-size:13px; }
        .tp-check-table th { background:#f7f7f6; font-weight:600; color:#52514e; padding:7px 10px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11px; text-transform:uppercase; }
        .tp-check-table td { padding:6px 10px; border-bottom:1px solid #eee; }
        .asof-note { background:#eaf2fc; border:1px solid #bcd6f5; border-radius:8px; padding:10px 14px; font-size:13px; color:#2a4d7a; margin-bottom:16px; }
        .daily-row:hover { background:#f7f9ff; }
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

                        <div class="page-description">
                            <h1>Active TP Status Check</h1>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                                    <div>
                                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">From Date</label>
                                        <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" class="form-control form-control-sm">
                                    </div>
                                    <div>
                                        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px;">To Date</label>
                                        <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" class="form-control form-control-sm">
                                    </div>
                                    <div><button type="submit" class="btn btn-primary btn-sm">Check</button></div>
                                </form>
                            </div>
                        </div>

                        <?php if ($rangeCapped): ?>
                        <div class="asof-note" style="background:#fef3c7;border-color:#fde68a;color:#92400e;">
                            Range picked was more than 31 days — capped to <b><?php echo htmlspecialchars($toDate); ?></b> so the day-by-day check below stays fast.
                        </div>
                        <?php endif; ?>

                        <div class="asof-note">
                            Cards + lists below show status <b>as of <?php echo htmlspecialchars($asOfDate); ?></b> (the To Date) — a TP added after this date is left out entirely; one soft-deleted on/before this date is also left out. Status changes made before this check tool existed can't be reconstructed exactly and fall back to the TP's current status.
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="stat-card active">
                                    <div class="num"><?php echo count($activeRows); ?></div>
                                    <div class="text-muted small">Active TPs</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card inactive">
                                    <div class="num"><?php echo count($inactiveRows); ?></div>
                                    <div class="text-muted small">Inactive TPs</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card tp-balance-clickable" onclick="openMonthEndModal()">
                                    <div class="num"><?php echo count($rows); ?></div>
                                    <div class="text-muted small">Total <span class="tp-count-badge">Month-end status</span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Total card's modal — a separate snapshot at the To-Date's own month's
                             last day, since a batch process (e.g. TP Bonus Calculator) can flip a
                             lot of TPs inactive mid-month and reactivate them again before the
                             month closes, which the day-level check above wouldn't reflect. -->
                        <div class="modal fade" id="monthEndModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-scrollable modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h6 class="modal-title" style="font-weight:700;">Status as of month end — <?php echo htmlspecialchars(date('d M Y', strtotime($monthEndDate))); ?></h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <div class="stat-card active">
                                                    <div class="num"><?php echo count($monthEndActiveRows); ?></div>
                                                    <div class="text-muted small">Active TPs</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="stat-card inactive">
                                                    <div class="num"><?php echo count($monthEndInactiveRows); ?></div>
                                                    <div class="text-muted small">Inactive TPs</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="status-check-card-header active-heading mb-2">Active (<?php echo count($monthEndActiveRows); ?>)</h6>
                                                <div style="max-height:380px;overflow-y:auto;">
                                                    <table class="tp-check-table">
                                                        <thead><tr><th>Name</th><th>TP ID</th><th>District</th></tr></thead>
                                                        <tbody>
                                                        <?php if (empty($monthEndActiveRows)): ?>
                                                            <tr><td colspan="3" class="text-muted">None.</td></tr>
                                                        <?php else: foreach ($monthEndActiveRows as $r): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($r['name']); ?></td>
                                                                <td><code><?php echo htmlspecialchars($r['tp_id']); ?></code></td>
                                                                <td><?php echo htmlspecialchars($r['district'] ?? '-'); ?></td>
                                                            </tr>
                                                        <?php endforeach; endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="status-check-card-header inactive-heading mb-2">Inactive (<?php echo count($monthEndInactiveRows); ?>)</h6>
                                                <div style="max-height:380px;overflow-y:auto;">
                                                    <table class="tp-check-table">
                                                        <thead><tr><th>Name</th><th>TP ID</th><th>District</th></tr></thead>
                                                        <tbody>
                                                        <?php if (empty($monthEndInactiveRows)): ?>
                                                            <tr><td colspan="3" class="text-muted">None.</td></tr>
                                                        <?php else: foreach ($monthEndInactiveRows as $r): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($r['name']); ?></td>
                                                                <td><code><?php echo htmlspecialchars($r['tp_id']); ?></code></td>
                                                                <td><?php echo htmlspecialchars($r['district'] ?? '-'); ?></td>
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

                        <!-- Date-wise trend — one row per date in the selected range; click a row
                             to see that exact date's Active/Inactive TP lists in a modal. -->
                        <div class="card mb-3">
                            <div class="card-header"><h6 class="status-check-card-header">Date-wise (<?php echo htmlspecialchars($fromDate); ?> to <?php echo htmlspecialchars($toDate); ?>)</h6></div>
                            <div class="card-body" style="max-height:360px;overflow-y:auto;padding:0;">
                                <table class="tp-check-table">
                                    <thead><tr><th>Date</th><th>Active</th><th>Inactive</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($dailySnapshots as $d => $snap): ?>
                                    <tr class="daily-row" data-date="<?php echo htmlspecialchars($d); ?>" style="cursor:pointer;">
                                        <td><?php echo htmlspecialchars(date('d M Y (D)', strtotime($d))); ?></td>
                                        <td style="color:#10b981;font-weight:600;"><?php echo (int)$snap['active_count']; ?></td>
                                        <td style="color:#d03b3b;font-weight:600;"><?php echo (int)$snap['inactive_count']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Shared modal for a clicked date-wise row — populated client-side from
                             the dailySnapshots JSON below, no re-fetch needed. -->
                        <div class="modal fade" id="dailyDetailModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-scrollable modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h6 class="modal-title" style="font-weight:700;" id="dailyDetailTitle">Status as of —</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <div class="stat-card active">
                                                    <div class="num" id="dailyActiveCount">0</div>
                                                    <div class="text-muted small">Active TPs</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="stat-card inactive">
                                                    <div class="num" id="dailyInactiveCount">0</div>
                                                    <div class="text-muted small">Inactive TPs</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="status-check-card-header active-heading mb-2">Active</h6>
                                                <div style="max-height:380px;overflow-y:auto;">
                                                    <table class="tp-check-table">
                                                        <thead><tr><th>Name</th><th>TP ID</th><th>District</th></tr></thead>
                                                        <tbody id="dailyActiveBody"></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="status-check-card-header inactive-heading mb-2">Inactive</h6>
                                                <div style="max-height:380px;overflow-y:auto;">
                                                    <table class="tp-check-table">
                                                        <thead><tr><th>Name</th><th>TP ID</th><th>District</th></tr></thead>
                                                        <tbody id="dailyInactiveBody"></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header"><h6 class="status-check-card-header active-heading">Active (<?php echo count($activeRows); ?>)</h6></div>
                                    <div class="card-body" style="max-height:520px;overflow-y:auto;">
                                        <table class="tp-check-table">
                                            <thead><tr><th>Name</th><th>TP ID</th><th>District</th></tr></thead>
                                            <tbody>
                                            <?php if (empty($activeRows)): ?>
                                                <tr><td colspan="3" class="text-muted">None.</td></tr>
                                            <?php else: foreach ($activeRows as $r): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($r['name']); ?></td>
                                                    <td><code><?php echo htmlspecialchars($r['tp_id']); ?></code></td>
                                                    <td><?php echo htmlspecialchars($r['district'] ?? '-'); ?></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header"><h6 class="status-check-card-header inactive-heading">Inactive (<?php echo count($inactiveRows); ?>)</h6></div>
                                    <div class="card-body" style="max-height:520px;overflow-y:auto;">
                                        <table class="tp-check-table">
                                            <thead><tr><th>Name</th><th>TP ID</th><th>District</th></tr></thead>
                                            <tbody>
                                            <?php if (empty($inactiveRows)): ?>
                                                <tr><td colspan="3" class="text-muted">None.</td></tr>
                                            <?php else: foreach ($inactiveRows as $r): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($r['name']); ?></td>
                                                    <td><code><?php echo htmlspecialchars($r['tp_id']); ?></code></td>
                                                    <td><?php echo htmlspecialchars($r['district'] ?? '-'); ?></td>
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
    function openMonthEndModal() {
        new bootstrap.Modal(document.getElementById('monthEndModal')).show();
    }

    var dailySnapshots = <?php echo json_encode($dailySnapshots, JSON_HEX_TAG | JSON_HEX_APOS); ?>;

    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s === null || s === undefined ? '' : s;
        return div.innerHTML;
    }

    function renderDailyList(tbodyId, list) {
        var tbody = document.getElementById(tbodyId);
        if (!list || !list.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-muted">None.</td></tr>';
            return;
        }
        var html = '';
        list.forEach(function (r) {
            html += '<tr><td>' + escapeHtml(r.name) + '</td><td><code>' + escapeHtml(r.tp_id) + '</code></td><td>' + escapeHtml(r.district || '-') + '</td></tr>';
        });
        tbody.innerHTML = html;
    }

    document.querySelectorAll('.daily-row').forEach(function (row) {
        row.addEventListener('click', function () {
            var d = row.getAttribute('data-date');
            var snap = dailySnapshots[d];
            if (!snap) return;
            document.getElementById('dailyDetailTitle').textContent = 'Status as of ' + d;
            document.getElementById('dailyActiveCount').textContent = snap.active_count;
            document.getElementById('dailyInactiveCount').textContent = snap.inactive_count;
            renderDailyList('dailyActiveBody', snap.active);
            renderDailyList('dailyInactiveBody', snap.inactive);
            new bootstrap.Modal(document.getElementById('dailyDetailModal')).show();
        });
    });
    </script>
</body>
</html>
