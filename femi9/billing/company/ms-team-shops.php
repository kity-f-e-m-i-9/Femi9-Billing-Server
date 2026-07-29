<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
error_reporting(0);

$idsParam = $_GET['ms_ids'] ?? '';
$ids = array_filter(array_map('intval', explode(',', $idsParam)));

$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';
$fromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) ? $fromDate : '';
$toDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) ? $toDate : '';
$hasDateFilter = ($fromDate !== '' && $toDate !== '');
$dateParam = $hasDateFilter ? ('&from_date=' . urlencode($fromDate) . '&to_date=' . urlencode($toDate)) : '';

$shops = [];
$staffNames = [];
if (!empty($ids)) {
    $idList = implode(',', $ids);

    $resNames = $db_conn->query("SELECT id, ms_name FROM marketing_staff WHERE id IN ($idList)");
    if ($resNames) {
        while ($row = $resNames->fetch_assoc()) { $staffNames[] = $row['ms_name']; }
    }

    // Plain shop list first — no per-row subqueries (that was the slow part:
    // 2 correlated subqueries x every shop = thousands of extra queries for
    // a large team). Order counts are aggregated separately in one pass each
    // and merged in PHP instead.
    $shopDateWhere = $hasDateFilter ? " AND DATE(s.created_at) BETWEEN '$fromDate' AND '$toDate'" : '';
    $stmt = $db_conn->query("
        SELECT s.id, s.name AS shop_name, s.mobile_number, s.address, s.state_name, s.district_name, s.taluk_name, s.shop_cat,
               ms.ms_name AS owner_name
        FROM ms_shop s
        JOIN marketing_staff ms ON ms.id = s.ms_id
        WHERE s.ms_id IN ($idList)$shopDateWhere
        ORDER BY ms.ms_name ASC, s.name ASC
    ");
    $shops = $stmt ? $stmt->fetch_all(MYSQLI_ASSOC) : [];

    if (!empty($shops)) {
        $shopIds = array_column($shops, 'id');
        $shopIdList = implode(',', $shopIds);
        $orderDateWhere = $hasDateFilter ? " AND order_date BETWEEN '$fromDate' AND '$toDate'" : '';

        $gotMap = [];
        $resGot = $db_conn->query("SELECT shop_id, COUNT(DISTINCT order_id) AS cnt FROM ms_orders WHERE shop_id IN ($shopIdList) AND new_order='yes'$orderDateWhere GROUP BY shop_id");
        if ($resGot) { while ($r = $resGot->fetch_assoc()) { $gotMap[(int)$r['shop_id']] = (int)$r['cnt']; } }

        $noMap = [];
        $resNo = $db_conn->query("SELECT shop_id, COUNT(*) AS cnt FROM ms_orders WHERE shop_id IN ($shopIdList) AND new_order='no'$orderDateWhere GROUP BY shop_id");
        if ($resNo) { while ($r = $resNo->fetch_assoc()) { $noMap[(int)$r['shop_id']] = (int)$r['cnt']; } }

        foreach ($shops as &$shop) {
            $shop['got_order_count'] = $gotMap[(int)$shop['id']] ?? 0;
            $shop['no_order_count']  = $noMap[(int)$shop['id']] ?? 0;
        }
        unset($shop);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Team Shops : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
        .badge-got { background:#0ca30c; color:#fff; }
        .badge-no { background:#d03b3b; color:#fff; }
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
                                    <table class="headertble">
                                        <tr>
                                            <td>Team Shops<?php echo !empty($staffNames) ? ' — ' . htmlspecialchars(implode(', ', $staffNames)) : ''; ?></td>
                                            <td>
                                                <?php if (!empty($ids)): ?>
                                                    <a href="export-ms-team-shops.php?ms_ids=<?php echo htmlspecialchars($idsParam) . htmlspecialchars($dateParam); ?>" title="Export to Excel">&#128190;</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col">
                            <form method="get" class="d-flex flex-wrap align-items-end gap-2">
                                <input type="hidden" name="ms_ids" value="<?php echo htmlspecialchars($idsParam); ?>">
                                <div>
                                    <label class="form-label mb-0" style="font-size:11px;font-weight:600;color:#6b7280;">From Date</label>
                                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($fromDate); ?>">
                                </div>
                                <div>
                                    <label class="form-label mb-0" style="font-size:11px;font-weight:600;color:#6b7280;">To Date</label>
                                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($toDate); ?>">
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                                    <?php if ($hasDateFilter): ?>
                                        <a href="ms-team-shops.php?ms_ids=<?php echo htmlspecialchars($idsParam); ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col">
                            <?php if (!empty($ids)): ?>
                                <a href="export-ms-team-shops.php?ms_ids=<?php echo htmlspecialchars($idsParam) . htmlspecialchars($dateParam); ?>" class="btn btn-success btn-sm">
                                    <i class="material-icons-outlined" style="font-size:16px;vertical-align:middle;">download</i> Export to Excel
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div style="overflow-x:scroll;">
                                        <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Shop Name</th>
                                                    <th>Owner (Marketing Staff)</th>
                                                    <th>Mobile</th>
                                                    <th>Location</th>
                                                    <th>Get Order</th>
                                                    <th>No Order</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php $i = 0; foreach ($shops as $shop): $i++; ?>
                                                <tr>
                                                    <td><?php echo $i; ?></td>
                                                    <td><?php echo htmlspecialchars($shop['shop_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($shop['owner_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($shop['mobile_number']); ?></td>
                                                    <td><?php echo htmlspecialchars(trim($shop['taluk_name'] . ', ' . $shop['district_name'], ', ')); ?></td>
                                                    <td><span class="badge badge-got"><?php echo (int)$shop['got_order_count']; ?></span></td>
                                                    <td><span class="badge badge-no"><?php echo (int)$shop['no_order_count']; ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($shops)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted" style="padding:20px;">No shops found.</td>
                                                </tr>
                                            <?php endif; ?>
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
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/highlight/highlight.pack.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
</body>
</html>
