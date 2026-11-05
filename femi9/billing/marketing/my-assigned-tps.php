<?php require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
include("config.php");
require_once __DIR__ . '/include/AssignedLocations.php';
require_once __DIR__ . '/include/MsDistrictScope.php';
error_reporting(0);

$msId = (int)$Login_user_IDvl;

// Same scope resolution as dashboard.php's own district card — District OR
// Division depth nodes directly assigned to this person. Resolved by NODE ID
// (getMsNodeTpIds), not by name (getMsDistrictTpIds) — a Division-scoped
// assignment (e.g. "Chennai Central B") isn't itself a District-depth row,
// so a name lookup against the District table would silently return zero
// TPs for any DM assigned at Division level. Same id-based approach
// dashboard.php already uses for a viewer's own scope (its $scopeIds).
$scopeNodes    = getMsAssignedTargetScopeNodes($db_conn, $msId);
$districtNames = array_column($scopeNodes, 'name');

// Build one TP roster per assigned scope node — one lookup per node rather
// than one combined list, so a cross-district/division TP correctly shows
// up under each of this person's assigned scopes, not just one.
$rosterByDistrict = [];
$allTpIds = [];
foreach ($scopeNodes as $node) {
    // includeInactive=true — this roster shows an Active/Inactive status
    // badge per TP, so it needs to include inactive TPs, not silently drop
    // them (same includeInactive=true dashboard.php's own solo-DM roster
    // uses for $allTpIds).
    $tpIds = getMsNodeTpIds($db_conn, [$node['id']], true);
    $rosterByDistrict[$node['name']] = $tpIds;
    $allTpIds = array_merge($allTpIds, $tpIds);
}
$allTpIds = array_values(array_unique($allTpIds));

$tpDetails = [];
if (!empty($allTpIds)) {
    $idList = implode(',', array_map('intval', $allTpIds));
    $res = $db_conn->query("SELECT id, tp_id, name, mobile, is_active FROM territory_partners WHERE id IN ($idList)");
    while ($row = $res->fetch_assoc()) {
        $tpDetails[(int)$row['id']] = $row;
    }

    // Total current stock (all products combined) per TP, from the TP's own
    // stock ledger table — same table shop-invoice-add.php reads from when
    // letting a TP sell out of what they actually hold.
    $resStock = $db_conn->query("
        SELECT territory_partner_id, SUM(closing_qty) AS total_qty
        FROM territory_partner_stock
        WHERE territory_partner_id IN ($idList)
        GROUP BY territory_partner_id
    ");
    while ($row = $resStock->fetch_assoc()) {
        $tpId = (int)$row['territory_partner_id'];
        if (isset($tpDetails[$tpId])) { $tpDetails[$tpId]['total_stock'] = (int)$row['total_qty']; }
    }
}
foreach ($tpDetails as $tpId => $d) {
    if (!isset($tpDetails[$tpId]['total_stock'])) { $tpDetails[$tpId]['total_stock'] = 0; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Assigned TPs : <?php echo $business_name; ?></title>
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
        .district-tp-card { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.06); border:none; margin-bottom:20px; }
        .district-tp-card .main-card-header-left { display:flex; align-items:center; gap:10px; }
        .header-icon-box { width:34px; height:34px; border-radius:8px; background:#eef1fd; color:#667eea; display:flex; align-items:center; justify-content:center; }
        .tp-roster-plain { width:100%; border-collapse:collapse; font-size:13px; }
        .tp-roster-plain th { background:#f7f7f6; font-weight:600; color:#52514e; padding:9px 12px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .tp-roster-plain td { padding:9px 12px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .status-badge { font-size:11px; padding:2px 10px; border-radius:12px; font-weight:600; }
        .status-badge.active { background:#dcfce7; color:#059669; }
        .status-badge.inactive { background:#fee2e2; color:#dc2626; }
        .stock-qty-btn { font-weight:700; color:#2563eb; background:none; border:none; padding:0; cursor:pointer; text-decoration:underline dotted; }
        .stock-qty-btn:hover { color:#1d4ed8; }
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
                                        <tr><td>Your Assigned TPs</td></tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($districtNames)): ?>
                    <div class="main-card district-tp-card">
                        <div style="padding:30px;text-align:center;color:#6b7280;">
                            No district is assigned to you yet.
                        </div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($districtNames as $dn): ?>
                        <div class="main-card district-tp-card">
                            <div class="main-card-header">
                                <div class="main-card-header-left">
                                    <div class="header-icon-box"><i class="material-icons-outlined">map</i></div>
                                    <div>
                                        <p class="header-title" style="margin:0;font-weight:600;"><?php echo htmlspecialchars($dn); ?></p>
                                        <p class="header-sub" style="margin:0;color:#6b7280;font-size:12px;"><?php echo count($rosterByDistrict[$dn]); ?> Territory Partner(s)</p>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive" style="padding:0 18px 16px;">
                                <table class="tp-roster-plain">
                                    <thead>
                                        <tr>
                                            <th style="width:48px;text-align:center;">#</th>
                                            <th>Territory Partner</th>
                                            <th>Mobile</th>
                                            <th style="text-align:right;">Stock Qty</th>
                                            <th style="text-align:center;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($rosterByDistrict[$dn])): ?>
                                        <tr><td colspan="5" style="text-align:center;color:#6b7280;padding:20px;">No TP assigned in this district.</td></tr>
                                    <?php else: $i = 0; foreach ($rosterByDistrict[$dn] as $tpId):
                                        $tp = $tpDetails[$tpId] ?? null;
                                        if (!$tp) continue;
                                        $i++;
                                    ?>
                                        <tr>
                                            <td style="text-align:center;color:#9ca3af;"><?php echo $i; ?></td>
                                            <td><b><?php echo htmlspecialchars($tp['name']); ?></b> <span style="color:#9ca3af;font-size:11px;">(<?php echo htmlspecialchars($tp['tp_id']); ?>)</span></td>
                                            <td><?php echo htmlspecialchars($tp['mobile'] ?? ''); ?></td>
                                            <td style="text-align:right;">
                                                <button type="button" class="stock-qty-btn" onclick="showStockBreakdown(<?php echo $tpId; ?>, '<?php echo htmlspecialchars(addslashes($tp['name']), ENT_QUOTES); ?>')"><?php echo number_format((int)$tp['total_stock']); ?></button>
                                            </td>
                                            <td style="text-align:center;">
                                                <?php if ((int)$tp['is_active'] === 1): ?>
                                                <span class="status-badge active">Active</span>
                                                <?php else: ?>
                                                <span class="status-badge inactive">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Product-wise Stock Breakdown modal -->
<div class="modal fade" id="stockBreakdownModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" style="font-weight:700;" id="stockBreakdownTitle">Stock Breakdown</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:0;">
                <table class="tp-roster-plain" style="margin:0;">
                    <thead>
                        <tr><th>Product</th><th style="text-align:right;">Qty</th></tr>
                    </thead>
                    <tbody id="stockBreakdownBody">
                        <tr><td colspan="2" style="text-align:center;color:#6b7280;padding:20px;">Loading…</td></tr>
                    </tbody>
                </table>
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
function showStockBreakdown(tpId, tpName) {
    document.getElementById('stockBreakdownTitle').textContent = 'Stock Breakdown — ' + tpName;
    var body = document.getElementById('stockBreakdownBody');
    body.innerHTML = '<tr><td colspan="2" style="text-align:center;color:#6b7280;padding:20px;">Loading…</td></tr>';
    var modal = new bootstrap.Modal(document.getElementById('stockBreakdownModal'));
    modal.show();

    fetch('get-tp-stock-breakdown.php?tp_id=' + encodeURIComponent(tpId))
        .then(function (r) { return r.json(); })
        .then(function (rows) {
            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="2" style="text-align:center;color:#6b7280;padding:20px;">No stock.</td></tr>';
                return;
            }
            var html = '';
            rows.forEach(function (r) {
                html += '<tr><td>' + r.productName + '</td><td style="text-align:right;font-weight:600;">' + Number(r.closing_qty).toLocaleString() + '</td></tr>';
            });
            body.innerHTML = html;
        })
        .catch(function () {
            body.innerHTML = '<tr><td colspan="2" style="text-align:center;color:#dc2626;padding:20px;">Failed to load.</td></tr>';
        });
}
</script>
</body>
</html>
