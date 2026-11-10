<?php require_once __DIR__ . '/../shared/db-helpers.php';
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
// District-wise: the DM assigned to that district + the live (converted to
// invoice) value of the Get Orders they placed, next to every Territory
// Partner assigned anywhere under that district + the value they invoiced to
// shops — both for the same fixed period. DM conversion math mirrors
// asm-dm-order-conversion-export.php (ms_orders -> tp_orders.invoiced_inv_id
// -> user_invoice, live only). TP invoice value is from_user_type=
// 'territory_partner'/to_user_type='shop' on user_invoice, live only.
require_once __DIR__ . '/../marketing/include/MsDistrictScope.php';
error_reporting(0);

// Fixed period, per explicit request — no date picker.
$periodFrom = '2026-11-01';
$periodTo   = '2026-11-08';

$districtDepthRow = $db_conn->query(
    "SELECT depth FROM partner_location_layers WHERE LOWER(layer_name) LIKE 'district%' ORDER BY depth ASC LIMIT 1"
)->fetch_assoc();
$districtDepth = (int)($districtDepthRow['depth'] ?? 3);

$districts = [];
$res = $db_conn->query("SELECT id, name FROM partner_location_nodes WHERE depth = $districtDepth AND is_active = 1 ORDER BY name ASC");
while ($row = $res->fetch_assoc()) { $districts[] = ['id' => (int)$row['id'], 'name' => $row['name']]; }

// ── DM(s) directly assigned to each district (District-layer assignment) ───
$dmsByDistrictId = []; // district_id => [ ['id'=>ms_id,'name'=>ms_name,'order'=>msl.id], ... ]
$resDm = $db_conn->query("
    SELECT d.id AS district_id, ms.id AS ms_id, ms.ms_name, msl.id AS assign_order
    FROM partner_location_nodes d
    JOIN marketing_staff_locations msl ON msl.location_id = d.id
    JOIN marketing_staff ms ON ms.id = msl.ms_id AND ms.deleted_at IS NULL
    JOIN marketing_team_levels tl ON tl.id = ms.team_level_id AND tl.level_name LIKE '%District%'
    WHERE d.depth = $districtDepth AND d.is_active = 1
");
while ($row = $resDm->fetch_assoc()) {
    $dmsByDistrictId[(int)$row['district_id']][] = ['id' => (int)$row['ms_id'], 'name' => $row['ms_name'], 'order' => (int)$row['assign_order']];
}

$allDmIds = [];
foreach ($dmsByDistrictId as $list) { foreach ($list as $dm) { $allDmIds[] = $dm['id']; } }
$allDmIds = array_values(array_unique($allDmIds));

// ── Re-order districts: grouped by DM (so every district a DM holds sits
//    together), districts within one DM's group kept in the order that DM
//    was actually assigned them (marketing_staff_locations.id ascending,
//    not alphabetical) — per explicit request. DMs are ordered by name;
//    districts with no DM are appended at the end, alphabetically. ────────
$dmNameById = [];
foreach ($dmsByDistrictId as $list) { foreach ($list as $dm) { $dmNameById[$dm['id']] = $dm['name']; } }
usort($districts, function ($a, $b) use ($dmsByDistrictId, $dmNameById) {
    $dmA = $dmsByDistrictId[$a['id']][0] ?? null;
    $dmB = $dmsByDistrictId[$b['id']][0] ?? null;
    if ($dmA === null && $dmB === null) { return strcmp($a['name'], $b['name']); }
    if ($dmA === null) { return 1; }
    if ($dmB === null) { return -1; }
    $nameCmp = strcmp($dmNameById[$dmA['id']], $dmNameById[$dmB['id']]);
    if ($nameCmp !== 0) { return $nameCmp; }
    return $dmA['order'] <=> $dmB['order'];
});

// ── DM Get Order -> converted (live invoice) value, same math as
//    asm-dm-order-conversion-export.php ──────────────────────────────────
$convertedAmtByDm = array_fill_keys($allDmIds, 0.0);
if (!empty($allDmIds)) {
    $dmIdListSql = implode(',', $allDmIds);
    $invIdsByDm = [];
    $res2 = $db_conn->query("
        SELECT DISTINCT o.ms_id, t.invoiced_inv_id
        FROM ms_orders o JOIN tp_orders t ON t.order_id = o.order_id
        WHERE o.ms_id IN ($dmIdListSql) AND o.new_order = 'yes'
          AND o.order_date BETWEEN '$periodFrom' AND '$periodTo'
          AND t.invoiced_inv_id IS NOT NULL AND t.invoiced_inv_id <> ''
    ");
    $invToDm = [];
    while ($row = $res2->fetch_assoc()) {
        $invToDm[$row['invoiced_inv_id']] = (int)$row['ms_id'];
    }
    if (!empty($invToDm)) {
        $placeholders = implode(',', array_fill(0, count($invToDm), '?'));
        $types = str_repeat('s', count($invToDm));
        $ids = array_keys($invToDm);
        $stmt = $db_conn->prepare("SELECT inv_id, total FROM user_invoice WHERE inv_id IN ($placeholders) AND deleted_at IS NULL AND voided_at IS NULL");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) {
            $dmId = $invToDm[$row['inv_id']] ?? null;
            if ($dmId === null) continue;
            $convertedAmtByDm[$dmId] += (float)$row['total'];
        }
        $stmt->close();
    }
}

// ── Territory Partners under each district (whole sub-tree: division/
//    taluk/firka included), same roster convention as
//    district-tp-stock-report.php / district-tp-reward-points-report.php ──
$tpIdsByDistrictId = [];
foreach ($districts as $d) {
    $tpIdsByDistrictId[$d['id']] = getMsNodeTpIds($db_conn, [$d['id']], true);
}
$allTpIds = [];
foreach ($tpIdsByDistrictId as $ids) { $allTpIds = array_merge($allTpIds, $ids); }
$allTpIds = array_values(array_unique($allTpIds));

$tpDetails = [];
if (!empty($allTpIds)) {
    $idList = implode(',', array_map('intval', $allTpIds));
    $res3 = $db_conn->query("SELECT id, tp_id, name FROM territory_partners WHERE id IN ($idList)");
    while ($row = $res3->fetch_assoc()) { $tpDetails[(int)$row['id']] = $row; }
}

// ── TP -> shop invoice value (live only) for the same period ───────────────
$invoiceAmtByTp = array_fill_keys($allTpIds, 0.0);
if (!empty($allTpIds)) {
    $idList = implode(',', array_map('intval', $allTpIds));
    $res4 = $db_conn->query("
        SELECT from_user_id, SUM(total) AS amt
        FROM user_invoice
        WHERE from_user_type = 'territory_partner' AND to_user_type = 'shop'
          AND from_user_id IN ($idList)
          AND `date` BETWEEN '$periodFrom' AND '$periodTo'
          AND deleted_at IS NULL AND voided_at IS NULL
        GROUP BY from_user_id
    ");
    while ($row = $res4->fetch_assoc()) {
        $invoiceAmtByTp[(int)$row['from_user_id']] = (float)$row['amt'];
    }
}

// ── Assemble per-district groups ────────────────────────────────────────────
$groups = [];
foreach ($districts as $d) {
    $dms = $dmsByDistrictId[$d['id']] ?? [];
    $tpIds = $tpIdsByDistrictId[$d['id']] ?? [];
    // Skip districts with neither a DM nor any TP — nothing to show.
    if (empty($dms) && empty($tpIds)) { continue; }
    $groups[] = [
        'id'      => $d['id'],
        'name'    => $d['name'],
        'dms'     => array_map(fn($dm) => ['name' => $dm['name'], 'amt' => $convertedAmtByDm[$dm['id']] ?? 0.0], $dms),
        'tps'     => array_values(array_filter(array_map(function ($tpId) use ($tpDetails, $invoiceAmtByTp) {
                        $tp = $tpDetails[$tpId] ?? null;
                        if (!$tp) return null;
                        return ['name' => $tp['name'], 'tp_code' => $tp['tp_id'], 'amt' => $invoiceAmtByTp[$tpId] ?? 0.0];
                     }, $tpIds))),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>District-wise DM / TP Order Conversion | <?php echo $safe_business_name ?? 'Femi9'; ?></title>
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
        .dtc-card { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.06); border:none; margin-bottom:20px; }
        .dtc-card .main-card-header-left { display:flex; align-items:center; gap:10px; }
        .header-icon-box { width:34px; height:34px; border-radius:8px; background:#eef1fd; color:#667eea; display:flex; align-items:center; justify-content:center; }
        .dtc-subhead { font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; color:#9ca3af; font-weight:700; padding:10px 18px 4px; }
        .dtc-table { width:100%; border-collapse:collapse; font-size:13px; }
        .dtc-table th { background:#f7f7f6; font-weight:600; color:#52514e; padding:9px 12px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11.5px; text-transform:uppercase; letter-spacing:.3px; }
        .dtc-table td { padding:9px 12px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
        .amt-dm { color:#2563eb; font-weight:700; }
        .amt-tp { color:#059669; font-weight:700; }
        .filter-bar { background:#fff; border-radius:10px; padding:18px 20px; box-shadow:0 1px 6px rgba(0,0,0,.07); margin-bottom:16px; }
        .district-filter-box { max-width:280px; }
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
                                        <tr><td>District-wise DM / TP Order Conversion</td></tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <div class="filter-bar">
                        <div class="row g-2 align-items-end">
                            <div class="col-auto">
                                <div style="font-size:12.5px;color:#6b7280;">
                                    Period: <b><?php echo date('d-m-Y', strtotime($periodFrom)); ?></b> to <b><?php echo date('d-m-Y', strtotime($periodTo)); ?></b>
                                    &middot; DM value = Get Orders placed in this period that are already converted to a live invoice
                                    &middot; TP value = live invoices that TP raised to shops in this period
                                </div>
                            </div>
                            <div class="col-auto ms-auto">
                                <label class="form-label mb-1">Search District / DM / TP</label>
                                <input type="text" id="districtSearchBox" class="form-control form-control-sm district-filter-box" placeholder="Search…">
                            </div>
                            <div class="col-auto">
                                <a href="export-district-dm-tp-conversion-xlsx.php" class="btn btn-success btn-sm" target="_blank">
                                    <i class="material-icons" style="font-size:16px;vertical-align:middle;">file_download</i> Export Excel
                                </a>
                            </div>
                        </div>
                    </div>

                    <div id="districtGroupsContainer">
                    <?php if (empty($groups)): ?>
                    <div class="main-card dtc-card">
                        <div style="padding:30px;text-align:center;color:#6b7280;">No districts with a DM or TP found.</div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($groups as $g):
                            $searchBlob = mb_strtolower($g['name']
                                . ' ' . implode(' ', array_column($g['dms'], 'name'))
                                . ' ' . implode(' ', array_column($g['tps'], 'name')));
                        ?>
                        <div class="main-card dtc-card district-row" data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES); ?>">
                            <div class="main-card-header">
                                <div class="main-card-header-left">
                                    <div class="header-icon-box"><i class="material-icons-outlined">map</i></div>
                                    <div>
                                        <p class="header-title" style="margin:0;font-weight:600;"><?php echo htmlspecialchars($g['name']); ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="dtc-subhead">District Manager — Get Order Converted Value</div>
                            <div class="table-responsive" style="padding:0 18px 10px;">
                                <table class="dtc-table">
                                    <thead><tr><th>District Manager</th><th style="text-align:right;">Converted Value</th></tr></thead>
                                    <tbody>
                                    <?php if (empty($g['dms'])): ?>
                                        <tr><td colspan="2" style="text-align:center;color:#dc2626;">No DM assigned</td></tr>
                                    <?php else: foreach ($g['dms'] as $dm): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dm['name']); ?></td>
                                            <td style="text-align:right;" class="amt-dm">&#8377;<?php echo number_format($dm['amt'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="dtc-subhead">Territory Partners — Invoice Value to Shops</div>
                            <div class="table-responsive" style="padding:0 18px 16px;">
                                <table class="dtc-table">
                                    <thead><tr><th>Territory Partner</th><th style="text-align:right;">Invoice Value</th></tr></thead>
                                    <tbody>
                                    <?php if (empty($g['tps'])): ?>
                                        <tr><td colspan="2" style="text-align:center;color:#6b7280;">No TP assigned in this district</td></tr>
                                    <?php else:
                                        $tpTotal = 0.0;
                                        foreach ($g['tps'] as $tp): $tpTotal += $tp['amt']; ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($tp['name']); ?> <span style="color:#9ca3af;font-size:11px;">(<?php echo htmlspecialchars($tp['tp_code']); ?>)</span></td>
                                            <td style="text-align:right;" class="amt-tp">&#8377;<?php echo number_format($tp['amt'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                        <tr style="font-weight:700;background:#f7f7f6;">
                                            <td>TOTAL</td>
                                            <td style="text-align:right;" class="amt-tp">&#8377;<?php echo number_format($tpTotal, 2); ?></td>
                                        </tr>
                                    <?php endif; ?>
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

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script>
// Pagination — 4 district groups per page. Search and pagination each hide
// rows via style.display, so they can't both read that same property to
// decide what's "in scope" — search marks matches with data-search-match,
// pagination reads that flag rather than the live display state.
(function () {
    var PAGE_SIZE = 4;
    var PAGE_WINDOW = 5;
    var pager = document.getElementById('districtGroupsPagination');
    var currentPage = 1;
    var allRows = Array.prototype.slice.call(document.querySelectorAll('.district-row'));

    function paginateGroups() {
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
    }

    document.getElementById('districtSearchBox').addEventListener('input', function (e) {
        var q = e.target.value.trim().toLowerCase();
        allRows.forEach(function (row) {
            row.dataset.searchMatch = row.getAttribute('data-search').indexOf(q) !== -1 ? '1' : '0';
        });
        currentPage = 1;
        paginateGroups();
    });

    paginateGroups();
})();
</script>
</body>
</html>
