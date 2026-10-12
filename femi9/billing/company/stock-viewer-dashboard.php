<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockViewerData.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['stockviewer', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

$summary   = get_stock_viewer_summary($db_conn);
$byProfile = get_stock_viewer_totals_by_profile($db_conn);
$byWarehouse = get_stock_viewer_totals_by_warehouse($db_conn);
$topProducts = get_stock_viewer_top_products($db_conn, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock Dashboard : <?php echo $business_name; ?></title>

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
    <?php include("include/stock-viewer-style.php"); ?>
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
                        <h1>Stock Dashboard</h1>
                        <span>Company-wide closing stock overview</span>
                    </div>

                    <div class="row">
                        <div class="col-xl col-md-6 mb-4" style="min-width:200px;">
                            <div class="card wh-card wh-kpi"><div class="card-body">
                                <div class="wh-kpi-icon" style="background:#e8eaf6;color:#3f51b5;"><i class="material-icons-outlined">stacked_bar_chart</i></div>
                                <div><p class="wh-kpi-label">Total Closing Qty</p><p class="wh-kpi-value"><?=number_format($summary['total_closing_qty']);?></p></div>
                            </div></div>
                        </div>
                        <div class="col-xl col-md-6 mb-4" style="min-width:200px;">
                            <div class="card wh-card wh-kpi"><div class="card-body">
                                <div class="wh-kpi-icon" style="background:#e8eaf6;color:#3f51b5;"><i class="material-icons-outlined">inventory_2</i></div>
                                <div><p class="wh-kpi-label">SKUs in Stock</p><p class="wh-kpi-value"><?=number_format($summary['sku_count']);?></p></div>
                            </div></div>
                        </div>
                        <div class="col-xl col-md-6 mb-4" style="min-width:200px;">
                            <div class="card wh-card wh-kpi"><div class="card-body">
                                <div class="wh-kpi-icon" style="background:#fef3c7;color:#d97706;"><i class="material-icons-outlined">store</i></div>
                                <div><p class="wh-kpi-label">Company Profiles</p><p class="wh-kpi-value"><?=number_format($summary['company_profile_count']);?></p></div>
                            </div></div>
                        </div>
                        <div class="col-xl col-md-6 mb-4" style="min-width:200px;">
                            <div class="card wh-card wh-kpi"><div class="card-body">
                                <div class="wh-kpi-icon" style="background:#e8eaf6;color:#5c6bc0;"><i class="material-icons-outlined">warehouse</i></div>
                                <div><p class="wh-kpi-label">Warehouses</p><p class="wh-kpi-value"><?=number_format($summary['warehouse_count']);?></p></div>
                            </div></div>
                        </div>
                        <div class="col-xl col-md-6 mb-4" style="min-width:200px;">
                            <div class="card wh-card wh-kpi"><div class="card-body">
                                <div class="wh-kpi-icon" style="background:#fee2e2;color:#dc2626;"><i class="material-icons-outlined">warning_amber</i></div>
                                <div><p class="wh-kpi-label">Low Stock (&lt;<?=STOCK_VIEWER_LOW_STOCK_THRESHOLD;?>)</p><p class="wh-kpi-value" style="<?=$summary['low_stock_count'] > 0 ? 'color:#dc2626;' : '';?>"><?=number_format($summary['low_stock_count']);?></p></div>
                            </div></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card wh-card"><div class="card-body">
                                <h5>Stock by Company Profile</h5>
                                <div id="chartByProfile"></div>
                            </div></div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card wh-card"><div class="card-body">
                                <h5>Stock by Warehouse</h5>
                                <div id="chartByWarehouse"></div>
                            </div></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="card wh-card"><div class="card-body">
                                <h5>Top 10 Products by Closing Qty</h5>
                                <div id="chartTopProducts"></div>
                            </div></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="card wh-card"><div class="card-body">
                                <h5>Explore Further</h5>
                                <a href="stock-viewer-by-profile.php" class="btn btn-outline-success me-2 mb-2">Stock by Company Profile</a>
                                <a href="stock-viewer-by-product.php" class="btn btn-outline-success me-2 mb-2">Stock by Product</a>
                                <a href="stock-viewer-by-warehouse.php" class="btn btn-outline-success mb-2">Stock by Warehouse</a>
                            </div></div>
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
<script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
var byProfile = <?php echo json_encode($byProfile); ?>;
var byWarehouse = <?php echo json_encode($byWarehouse); ?>;
var topProducts = <?php echo json_encode($topProducts); ?>;

function barChart(elId, categories, series, color) {
    new ApexCharts(document.querySelector(elId), {
        chart: { type: 'bar', height: 300, toolbar: { show: false } },
        series: [{ name: 'Closing Qty', data: series }],
        xaxis: { categories: categories, labels: { rotate: -30 } },
        colors: [color || '#3f51b5'],
        plotOptions: { bar: { borderRadius: 6, columnWidth: '40%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: '#eef2f6' }
    }).render();
}

barChart('#chartByProfile', Object.keys(byProfile), Object.values(byProfile), '#3f51b5');
barChart('#chartByWarehouse', Object.keys(byWarehouse), Object.values(byWarehouse), '#3f51b5');
barChart('#chartTopProducts', topProducts.map(function(p){return p.productName;}), topProducts.map(function(p){return p.total_qty;}), '#5c6bc0');
</script>
</body>
</html>
