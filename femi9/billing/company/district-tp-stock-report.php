<?php require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
include("config.php");
// Company-wide twin of marketing/my-assigned-tps.php: every district (not
// just one DM/ASM's own assignment), who the district's DM(s) — or, for a
// district split into Divisions (e.g. Chennai), each Division's own DM — is,
// and that scope's TP roster with current total stock qty.
require_once __DIR__ . '/../marketing/include/MsDistrictScope.php';
error_reporting(0);

$districtDepthRow = $db_conn->query(
    "SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1"
)->fetch_assoc();
$districtDepth = (int)($districtDepthRow['depth'] ?? 0);
$divisionDepthRow = $db_conn->query(
    "SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'division%' ORDER BY depth ASC LIMIT 1"
)->fetch_assoc();
$divisionDepth = (int)($divisionDepthRow['depth'] ?? 0);

$districts = [];
if ($districtDepth) {
    $res = $db_conn->query("SELECT id, name FROM partner_location_nodes WHERE depth = $districtDepth AND is_active = 1 ORDER BY name ASC");
    while ($row = $res->fetch_assoc()) { $districts[] = ['id' => (int)$row['id'], 'name' => $row['name']]; }
}
$districtNames = array_column($districts, 'name');

// Divisions, grouped by parent district id — only districts with at least
// one Division actually holding a DM assignment get split into per-Division
// groups below; a district with Divisions defined but no DM ever assigned to
// any of them stays a single district-level group, same as before.
$divisionsByDistrictId = [];
$divisionIdsWithDm = [];
if ($divisionDepth) {
    $res = $db_conn->query("SELECT id, name, parent_id FROM partner_location_nodes WHERE depth = $divisionDepth AND is_active = 1 ORDER BY name ASC");
    while ($row = $res->fetch_assoc()) {
        $divisionsByDistrictId[(int)$row['parent_id']][] = ['id' => (int)$row['id'], 'name' => $row['name']];
    }
    $resDm = $db_conn->query("
        SELECT DISTINCT n.id
        FROM marketing_staff_locations msl
        JOIN partner_location_nodes n ON n.id = msl.location_id AND n.depth = $divisionDepth
        JOIN marketing_staff ms ON ms.id = msl.ms_id AND ms.deleted_at IS NULL
    ");
    while ($row = $resDm->fetch_assoc()) { $divisionIdsWithDm[(int)$row['id']] = true; }
}

$dmNamesByDistrict = getDistrictManagerNames($db_conn, $districtNames);

function getDivisionManagerNames($db_conn, int $divisionLocId): array {
    $stmt = $db_conn->prepare("
        SELECT ms.ms_name
        FROM marketing_staff_locations msl
        JOIN marketing_staff ms ON ms.id = msl.ms_id AND ms.deleted_at IS NULL
        WHERE msl.location_id = ?
    ");
    $stmt->bind_param('i', $divisionLocId);
    $stmt->execute();
    $names = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'ms_name');
    $stmt->close();
    return $names;
}

// Build the flat list of groups to render — one per district, OR one per
// Division when that district has any DM assigned at Division level.
// includeInactive=true throughout — company should see every TP on the
// roster (including inactive ones), same as the dashboard's own admin-facing
// roster queries (e.g. dashboard.php's $teamTpIds).
$groups = [];
foreach ($districts as $d) {
    $districtDivisions = $divisionsByDistrictId[$d['id']] ?? [];
    $hasDmSplitDivisions = false;
    foreach ($districtDivisions as $div) {
        if (isset($divisionIdsWithDm[$div['id']])) { $hasDmSplitDivisions = true; break; }
    }

    if ($hasDmSplitDivisions) {
        foreach ($districtDivisions as $div) {
            $groups[] = [
                'label'    => $d['name'] . ' / ' . $div['name'],
                'dm_names' => getDivisionManagerNames($db_conn, $div['id']),
                'tp_ids'   => getMsNodeTpIds($db_conn, [$div['id']], true),
            ];
        }
    } else {
        $groups[] = [
            'label'    => $d['name'],
            'dm_names' => $dmNamesByDistrict[$d['name']] ?? [],
            'tp_ids'   => getMsDistrictTpIds($db_conn, [$d['name']], true),
        ];
    }
}

$allTpIds = [];
foreach ($groups as $g) { $allTpIds = array_merge($allTpIds, $g['tp_ids']); }
$allTpIds = array_values(array_unique($allTpIds));

$tpDetails = [];
if (!empty($allTpIds)) {
    $idList = implode(',', array_map('intval', $allTpIds));
    $res = $db_conn->query("SELECT id, tp_id, name, mobile, is_active FROM territory_partners WHERE id IN ($idList)");
    while ($row = $res->fetch_assoc()) {
        $tpDetails[(int)$row['id']] = $row;
    }

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
    <title>District-wise TP Stock | <?php echo $safe_business_name ?? 'Femi9'; ?></title>
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
        .district-filter-box { max-width:260px; margin-bottom:16px; }
        .mis-pagination { display:flex; align-items:center; justify-content:flex-end; gap:6px; padding:4px 0 20px; flex-wrap:wrap; }
        .mis-pagination button { padding:4px 11px; border-radius:6px; border:1px solid #e5e7eb; background:#fff; color:#374151; font-size:12.5px; font-weight:600; cursor:pointer; }
        .mis-pagination button.active { background:#667eea; border-color:#667eea; color:#fff; }
        .mis-pagination button:disabled { opacity:.4; cursor:default; }
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
                                        <tr><td>District-wise TP Stock</td></tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <input type="text" id="districtSearchBox" class="form-control district-filter-box" placeholder="Search district…">

                    <div id="districtGroupsContainer">
                    <?php if (empty($groups)): ?>
                    <div class="main-card district-tp-card">
                        <div style="padding:30px;text-align:center;color:#6b7280;">No districts found.</div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($groups as $g): ?>
                        <div class="main-card district-tp-card district-row" data-district="<?php echo htmlspecialchars(mb_strtolower($g['label']), ENT_QUOTES); ?>">
                            <div class="main-card-header">
                                <div class="main-card-header-left">
                                    <div class="header-icon-box"><i class="material-icons-outlined">map</i></div>
                                    <div>
                                        <p class="header-title" style="margin:0;font-weight:600;"><?php echo htmlspecialchars($g['label']); ?></p>
                                        <p class="header-sub" style="margin:0;color:#6b7280;font-size:12px;">
                                            <?php echo count($g['tp_ids']); ?> Territory Partner(s)
                                            <?php if (!empty($g['dm_names'])): ?>
                                                &middot; DM: <?php echo htmlspecialchars(implode(', ', $g['dm_names'])); ?>
                                            <?php else: ?>
                                                &middot; <span style="color:#dc2626;">No DM assigned</span>
                                            <?php endif; ?>
                                        </p>
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
                                    <?php if (empty($g['tp_ids'])): ?>
                                        <tr><td colspan="5" style="text-align:center;color:#6b7280;padding:20px;">No TP assigned in this scope.</td></tr>
                                    <?php else: $i = 0; foreach ($g['tp_ids'] as $tpId):
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

                    <div id="districtGroupsPagination" class="mis-pagination"></div>

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

// Pagination — 4 district/Division groups per page, re-applied whenever the
// search box filters the visible rows. Search and pagination each hide rows
// via style.display, so they can't both read that same property to decide
// what's "in scope" (each run would shrink what the other already hid) —
// search instead marks matches with data-search-match, and pagination reads
// that flag rather than the live display state.
(function () {
    var PAGE_SIZE = 4;
    var PAGE_WINDOW = 5; // at most 5 page-number buttons shown at once
    var pager = document.getElementById('districtGroupsPagination');
    var currentPage = 1;
    var allRows = Array.prototype.slice.call(document.querySelectorAll('.district-row'));

    window.paginateGroups = function () {
        var matchedRows = allRows.filter(function (row) { return row.dataset.searchMatch !== '0'; });
        var totalPages = Math.max(1, Math.ceil(matchedRows.length / PAGE_SIZE));
        if (currentPage > totalPages) { currentPage = totalPages; }

        allRows.forEach(function (row) { row.style.display = 'none'; });
        matchedRows.forEach(function (row, idx) {
            var page = Math.floor(idx / PAGE_SIZE) + 1;
            if (page === currentPage) { row.style.display = ''; }
        });

        if (totalPages <= 1) { pager.innerHTML = ''; return; }

        var windowStart = Math.max(1, currentPage - Math.floor(PAGE_WINDOW / 2));
        var windowEnd = Math.min(totalPages, windowStart + PAGE_WINDOW - 1);
        windowStart = Math.max(1, windowEnd - PAGE_WINDOW + 1);

        var html = '';
        html += '<button type="button" id="dgPrev"' + (currentPage === 1 ? ' disabled' : '') + '>Prev</button>';
        if (windowStart > 1) { html += '<button type="button" class="dg-page-btn" data-page="1">1</button>'; if (windowStart > 2) { html += '<span style="padding:0 4px;color:#9ca3af;">…</span>'; } }
        for (var p = windowStart; p <= windowEnd; p++) {
            html += '<button type="button" class="dg-page-btn ' + (p === currentPage ? 'active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        if (windowEnd < totalPages) { if (windowEnd < totalPages - 1) { html += '<span style="padding:0 4px;color:#9ca3af;">…</span>'; } html += '<button type="button" class="dg-page-btn" data-page="' + totalPages + '">' + totalPages + '</button>'; }
        html += '<button type="button" id="dgNext"' + (currentPage === totalPages ? ' disabled' : '') + '>Next</button>';
        pager.innerHTML = html;

        var prevBtn = document.getElementById('dgPrev');
        if (prevBtn) prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; paginateGroups(); } });
        var nextBtn = document.getElementById('dgNext');
        if (nextBtn) nextBtn.addEventListener('click', function () { if (currentPage < totalPages) { currentPage++; paginateGroups(); } });
        pager.querySelectorAll('.dg-page-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { currentPage = parseInt(this.getAttribute('data-page'), 10); paginateGroups(); });
        });
    };

    document.getElementById('districtSearchBox').addEventListener('input', function (e) {
        var q = e.target.value.trim().toLowerCase();
        allRows.forEach(function (row) {
            row.dataset.searchMatch = row.getAttribute('data-district').indexOf(q) !== -1 ? '1' : '0';
        });
        currentPage = 1;
        paginateGroups();
    });

    paginateGroups();
})();
</script>
</body>
</html>
