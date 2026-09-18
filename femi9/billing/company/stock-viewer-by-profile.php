<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockViewerData.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['stockviewer', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

$profiles   = get_stock_viewer_company_profiles($db_conn);
$warehouses = get_stock_viewer_warehouses($db_conn);
$rows       = get_stock_viewer_rows($db_conn);

// Bucket rows by [company_godown_id][warehouse_id or ''] => rows, so each
// card renders one (profile, warehouse) combination — same shape as
// overall-stock.php's per-warehouse cards, but spanning every profile.
$buckets = [];
foreach ($rows as $row) {
    $profileKey = (int) $row['company_godown_id'];
    $whKey      = $row['warehouse_id'] !== null ? (string) $row['warehouse_id'] : '';
    $buckets[$profileKey][$whKey]['rows'][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock by Company Profile : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
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
                        <h1>Stock by Company Profile</h1>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-4">
                                            <label class="form-label">Filter by Product</label>
                                            <input type="text" id="productFilterInput" class="form-control" placeholder="Type a product name…">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Filter by Company Profile</label>
                                            <div>
                                                <?php foreach ($profiles as $p): ?>
                                                <label style="font-weight:normal;display:inline-flex;align-items:center;gap:4px;margin-right:14px;">
                                                    <input type="checkbox" class="profile-filter-check" value="cp-<?=(int)$p['id'];?>" checked> <?=htmlspecialchars($p['gname'], ENT_QUOTES, 'UTF-8');?>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Filter by Warehouse</label>
                                            <div>
                                                <label style="font-weight:normal;display:inline-flex;align-items:center;gap:4px;margin-right:14px;">
                                                    <input type="checkbox" class="warehouse-filter-check" value="unassigned" checked> Unassigned
                                                </label>
                                                <?php foreach ($warehouses as $wh): ?>
                                                <label style="font-weight:normal;display:inline-flex;align-items:center;gap:4px;margin-right:14px;">
                                                    <input type="checkbox" class="warehouse-filter-check" value="wh-<?=(int)$wh['id'];?>" checked> <?=htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8');?>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

<?php foreach ($profiles as $profile):
    $profileId = (int) $profile['id'];
    if (empty($buckets[$profileId])) continue;
    foreach ($buckets[$profileId] as $whKey => $bucket):
        $whLabel = 'Unassigned';
        $cardFilterWh = 'unassigned';
        if ($whKey !== '') {
            $whRow = null;
            foreach ($warehouses as $wh) { if ((string)$wh['id'] === $whKey) { $whRow = $wh; break; } }
            $whLabel = $whRow ? ($whRow['code'] . ($whRow['name'] ? ' - ' . $whRow['name'] : '')) : "Godown #$whKey";
            $cardFilterWh = 'wh-' . $whKey;
        }
?>
                    <div class="row wh-card" data-profile="cp-<?=$profileId;?>" data-warehouse="<?=$cardFilterWh;?>">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <h1><?=htmlspecialchars($profile['gname'], ENT_QUOTES, 'UTF-8');?> &mdash; <?=htmlspecialchars($whLabel, ENT_QUOTES, 'UTF-8');?></h1>
                                    <div style="background:#fff;overflow:scroll;width:100%;">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Product Name</th>
                                                <th style="text-align:right;">Closing Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
<?php foreach ($bucket['rows'] as $row): ?>
                                            <tr class="product-row" data-product-name="<?=htmlspecialchars(strtolower($row['productName']), ENT_QUOTES, 'UTF-8');?>">
                                                <td><?=htmlspecialchars($row['productName'], ENT_QUOTES, 'UTF-8');?></td>
                                                <td align="right"><b><?=(int)$row['closing_qty'];?></b></td>
                                            </tr>
<?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
<?php
    endforeach;
endforeach;
?>

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
function applyStockFilters() {
    var productQuery = document.getElementById('productFilterInput').value.trim().toLowerCase();
    var checkedProfiles = Array.prototype.slice.call(document.querySelectorAll('.profile-filter-check:checked')).map(function (cb) { return cb.value; });
    var checkedWarehouses = Array.prototype.slice.call(document.querySelectorAll('.warehouse-filter-check:checked')).map(function (cb) { return cb.value; });

    document.querySelectorAll('.wh-card').forEach(function (card) {
        var profileVisible = checkedProfiles.indexOf(card.getAttribute('data-profile')) !== -1;
        var warehouseVisible = checkedWarehouses.indexOf(card.getAttribute('data-warehouse')) !== -1;
        if (!profileVisible || !warehouseVisible) {
            card.style.display = 'none';
            return;
        }
        var anyRowVisible = false;
        card.querySelectorAll('.product-row').forEach(function (row) {
            var matches = !productQuery || row.getAttribute('data-product-name').indexOf(productQuery) !== -1;
            row.style.display = matches ? '' : 'none';
            if (matches) anyRowVisible = true;
        });
        card.style.display = (productQuery && !anyRowVisible) ? 'none' : '';
    });
}
document.getElementById('productFilterInput').addEventListener('input', applyStockFilters);
document.querySelectorAll('.profile-filter-check, .warehouse-filter-check').forEach(function (cb) {
    cb.addEventListener('change', applyStockFilters);
});
</script>
</body>
</html>
