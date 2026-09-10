<?php
// Sales BDM's own version of ms-team-shop-view.php — same per-person shop/
// order stats, but flat (no SM/ASM/DM org-tree) and filtered down to only
// the marketing staff whose OWN assigned district (marketing_staff_locations,
// resolved via marketing/include/AssignedLocations.php's getMsAssignedDistricts()
// — the same district-resolution helper the Add Shop picker uses) falls
// inside this BDM's own assigned districts (salesbdm_locations). Kept as a
// separate page rather than reworking the tree page in place — the tree's
// manager/subtree-sum recursion assumes every node in the chain is visible,
// which a partial per-district cut would break.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once("include/TeamLevelColors.php");
require_once __DIR__ . '/../marketing/include/AssignedLocations.php';
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'salesbdm') {
    header("Location: ms-team-shop-view.php");
    exit;
}
require_once __DIR__ . '/../salesbdm/include/BdmTpScope.php';
$bdmDistricts = array_map(fn($n) => mb_strtolower(trim($n)), getBdmAssignedDistrictNames($db_conn, (int)$salesBdmID));

$db_conn->query("CREATE TABLE IF NOT EXISTS marketing_team_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_rank INT NOT NULL,
    level_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_level_rank (level_rank)
)");
$levelColorMap = getTeamLevelColorMap($db_conn);

$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';
$fromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) ? $fromDate : '';
$toDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) ? $toDate : '';
$hasDateFilter = ($fromDate !== '' && $toDate !== '');
$dateParam = $hasDateFilter ? ('&from_date=' . urlencode($fromDate) . '&to_date=' . urlencode($toDate)) : '';
$ordersDateParam = $hasDateFilter ? ('&frdate=' . urlencode($fromDate) . '&todate=' . urlencode($toDate)) : '';

// ── Every marketing staff member, then keep only those whose own assigned
// district falls inside this BDM's districts. ──────────────────────────────
$staffRows = $db_conn->query("
    SELECT ms.id, ms.ms_name, ms.team_level_id, tl.level_name
    FROM marketing_staff ms
    LEFT JOIN marketing_team_levels tl ON tl.id = ms.team_level_id
    ORDER BY tl.level_rank ASC, ms.ms_name ASC
")->fetch_all(MYSQLI_ASSOC);

$scopedStaff = [];
foreach ($staffRows as $row) {
    $msId = (int)$row['id'];
    $districts = getMsAssignedDistricts($db_conn, $msId);
    $matches = false;
    foreach ($districts as $d) {
        if (in_array(mb_strtolower(trim($d['name'])), $bdmDistricts, true)) { $matches = true; break; }
    }
    if ($matches) {
        $row['districts'] = implode(', ', array_column($districts, 'name'));
        $scopedStaff[] = $row;
    }
}
$allIds = array_map(fn($r) => (int)$r['id'], $scopedStaff);

// ── Same per-person stats as ms-team-shop-view.php, just for this smaller set. ──
$rawStats = [];
foreach ($allIds as $id) { $rawStats[$id] = ['shops' => 0, 'oldshops' => 0, 'got' => 0, 'no' => 0]; }
if (!empty($allIds)) {
    $idList = implode(',', $allIds);
    $shopDateWhere = $hasDateFilter ? " AND DATE(created_at) BETWEEN '$fromDate' AND '$toDate'" : '';
    $orderDateWhere = $hasDateFilter ? " AND order_date BETWEEN '$fromDate' AND '$toDate'" : '';
    $_rs = $db_conn->query("SELECT ms_id, COUNT(*) AS cnt FROM ms_shop WHERE ms_id IN ($idList)$shopDateWhere GROUP BY ms_id");
    if ($_rs) { while ($r = $_rs->fetch_assoc()) { $rawStats[(int)$r['ms_id']]['shops'] = (int)$r['cnt']; } }
    if ($hasDateFilter) {
        $_rs = $db_conn->query(
            "SELECT o.ms_id, COUNT(DISTINCT o.shop_id) AS cnt
             FROM ms_orders o JOIN ms_shop s ON s.id = o.shop_id
             WHERE o.ms_id IN ($idList) AND o.new_order='yes'
                   AND o.order_date BETWEEN '$fromDate' AND '$toDate'
                   AND DATE(s.created_at) < '$fromDate'
             GROUP BY o.ms_id"
        );
        if ($_rs) { while ($r = $_rs->fetch_assoc()) { $rawStats[(int)$r['ms_id']]['oldshops'] = (int)$r['cnt']; } }
    }
    $_rs = $db_conn->query("SELECT ms_id, COUNT(DISTINCT order_id) AS cnt FROM ms_orders WHERE ms_id IN ($idList) AND new_order='yes'$orderDateWhere GROUP BY ms_id");
    if ($_rs) { while ($r = $_rs->fetch_assoc()) { $rawStats[(int)$r['ms_id']]['got'] = (int)$r['cnt']; } }
    $_rs = $db_conn->query("SELECT ms_id, COUNT(*) AS cnt FROM ms_orders WHERE ms_id IN ($idList) AND new_order='no'$orderDateWhere GROUP BY ms_id");
    if ($_rs) { while ($r = $_rs->fetch_assoc()) { $rawStats[(int)$r['ms_id']]['no'] = (int)$r['cnt']; } }
}

$kpiShops = array_sum(array_column($rawStats, 'shops'));
$kpiOldShops = array_sum(array_column($rawStats, 'oldshops'));
$kpiGot = array_sum(array_column($rawStats, 'got'));
$kpiNo = array_sum(array_column($rawStats, 'no'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>District Shop View : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        .kpi-card {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
            padding: 18px 20px; position: relative; overflow: hidden;
            height: 100%; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .kpi-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background: var(--kpi-accent, #667eea); }
        .kpi-card .kpi-t { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; font-weight:600; color: #6b7280; }
        .kpi-card .kpi-v { font-size: 24px; font-weight: 700; margin-top: 6px; color: #111827; }
        .tp-tag { font-size:11px; padding:2px 8px; border-radius:6px; font-weight:600; white-space:nowrap; }
        .stat-pill { font-size:13px; font-weight:600; color:#374151; }
        .btn-view-shop-list {
            font-size:12px; font-weight:600; color:#fff; white-space:nowrap;
            background: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            padding:5px 12px; border-radius:6px; text-decoration:none; display:inline-block;
        }
        .btn-view-shop-list:hover { color:#fff; opacity:.9; }
        .actions-cell { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
        .mt { width:100%; border-collapse:collapse; font-size:13px; }
        .mt th { background:#f7f7f6; font-weight:600; color:#52514e; padding:10px 12px; text-align:left; border-bottom:1px solid #e1e0d9; white-space:nowrap; font-size:11px; text-transform:uppercase; letter-spacing:.3px; }
        .mt td { padding:10px 12px; border-bottom:1px solid #e1e0d9; vertical-align:middle; }
    </style>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("../salesbdm/logo.php"); ?>
        <?php include("../salesbdm/femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("../salesbdm/app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">

                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>District Shop View <small class="text-muted" style="font-size:13px;">(marketing staff assigned to your districts)</small></h1>
                            </div>
                        </div>
                    </div>

                    <form method="get" class="row g-2 align-items-end mb-3">
                        <div class="col-auto">
                            <label class="form-label">From Date</label>
                            <input type="date" name="from_date" value="<?=htmlspecialchars($fromDate)?>" class="form-control">
                        </div>
                        <div class="col-auto">
                            <label class="form-label">To Date</label>
                            <input type="date" name="to_date" value="<?=htmlspecialchars($toDate)?>" class="form-control">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary"><i class="material-icons">search</i> Filter</button>
                        </div>
                        <?php if ($hasDateFilter): ?>
                        <div class="col-auto">
                            <a href="bdm-ms-shop-view.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                        <?php endif; ?>
                    </form>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="kpi-card" style="--kpi-accent:#667eea;">
                                <div class="kpi-t">Total Shops</div>
                                <div class="kpi-v"><?=$kpiShops?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="kpi-card" style="--kpi-accent:#0ca30c;">
                                <div class="kpi-t">Got Order</div>
                                <div class="kpi-v"><?=$kpiGot?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="kpi-card" style="--kpi-accent:#d03b3b;">
                                <div class="kpi-t">No Order</div>
                                <div class="kpi-v"><?=$kpiNo?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="kpi-card" style="--kpi-accent:#2a78d6;">
                                <div class="kpi-t">Staff in your districts</div>
                                <div class="kpi-v"><?=count($scopedStaff)?></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div style="overflow-x:auto;">
                                    <table class="mt">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Name</th>
                                                <th>Level</th>
                                                <th>District</th>
                                                <th>Shops</th>
                                                <th>Old Shops</th>
                                                <th>Got Order</th>
                                                <th>No Order</th>
                                                <th>Total</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($scopedStaff)): ?>
                                        <tr><td colspan="10" class="text-center text-muted">No marketing staff assigned to your districts yet.</td></tr>
                                        <?php else: $rank = 0; foreach ($scopedStaff as $row):
                                            $id = (int)$row['id'];
                                            $sum = $rawStats[$id];
                                            $color = $levelColorMap[(int)$row['team_level_id']] ?? '#999999';
                                            $rank++;
                                        ?>
                                        <tr>
                                            <td><?=$rank?></td>
                                            <td><b><?=htmlspecialchars($row['ms_name'])?></b></td>
                                            <td><span class="tp-tag" style="color:<?=$color?>;background:<?=$color?>1a;"><?=htmlspecialchars($row['level_name'] ?: '-')?></span></td>
                                            <td><?=htmlspecialchars($row['districts'])?></td>
                                            <td><span class="stat-pill"><?=$sum['shops']?></span></td>
                                            <td><span class="stat-pill"><?=$sum['oldshops']?></span></td>
                                            <td><span class="stat-pill"><?=$sum['got']?></span></td>
                                            <td><span class="stat-pill"><?=$sum['no']?></span></td>
                                            <td><span class="stat-pill"><?=($sum['got'] + $sum['no'])?></span></td>
                                            <td>
                                                <div class="actions-cell">
                                                <?php if ($sum['shops'] > 0): ?>
                                                <a class="btn-view-shop-list" target="_blank" rel="noopener" href="ms-team-shops.php?ms_ids=<?=$id?><?=htmlspecialchars($dateParam)?>">Shop List</a>
                                                <?php else: ?>
                                                <span class="text-muted" style="font-size:11px;">&mdash;</span>
                                                <?php endif; ?>
                                                <a class="btn-view-shop-list" target="_blank" rel="noopener" href="ms_prorders.php?se_msid=<?=$id?><?=htmlspecialchars($ordersDateParam)?>">Orders</a>
                                                </div>
                                            </td>
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
</div>

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
