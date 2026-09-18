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
                    </div>

                    <div class="row">
                        <div class="col-md-2-4 col-sm-6 mb-4" style="flex:1;min-width:180px;">
                            <div class="card"><div class="card-body text-center">
                                <div class="text-muted">Total Closing Qty</div>
                                <h2><?=number_format($summary['total_closing_qty']);?></h2>
                            </div></div>
                        </div>
                        <div class="col-md-2-4 col-sm-6 mb-4" style="flex:1;min-width:180px;">
                            <div class="card"><div class="card-body text-center">
                                <div class="text-muted">SKUs in Stock</div>
                                <h2><?=number_format($summary['sku_count']);?></h2>
                            </div></div>
                        </div>
                        <div class="col-md-2-4 col-sm-6 mb-4" style="flex:1;min-width:180px;">
                            <div class="card"><div class="card-body text-center">
                                <div class="text-muted">Company Profiles</div>
                                <h2><?=number_format($summary['company_profile_count']);?></h2>
                            </div></div>
                        </div>
                        <div class="col-md-2-4 col-sm-6 mb-4" style="flex:1;min-width:180px;">
                            <div class="card"><div class="card-body text-center">
                                <div class="text-muted">Warehouses</div>
                                <h2><?=number_format($summary['warehouse_count']);?></h2>
                            </div></div>
                        </div>
                        <div class="col-md-2-4 col-sm-6 mb-4" style="flex:1;min-width:180px;">
                            <div class="card" style="<?=$summary['low_stock_count'] > 0 ? 'border-color:#dc3545;' : '';?>">
                                <div class="card-body text-center">
                                <div class="text-muted">Low Stock Products (&lt;<?=STOCK_VIEWER_LOW_STOCK_THRESHOLD;?>)</div>
                                <h2 style="<?=$summary['low_stock_count'] > 0 ? 'color:#dc3545;' : '';?>"><?=number_format($summary['low_stock_count']);?></h2>
                            </div></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card"><div class="card-body">
                                <h5>Stock by Company Profile</h5>
                                <div id="chartByProfile"></div>
                            </div></div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card"><div class="card-body">
                                <h5>Stock by Warehouse</h5>
                                <div id="chartByWarehouse"></div>
                            </div></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="card"><div class="card-body">
                                <h5>Top 10 Products by Closing Qty</h5>
                                <div id="chartTopProducts"></div>
                            </div></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="card"><div class="card-body">
                                <h5>Explore Further</h5>
                                <a href="stock-viewer-by-profile.php" class="btn btn-outline-primary me-2 mb-2">Stock by Company Profile</a>
                                <a href="stock-viewer-by-product.php" class="btn btn-outline-primary me-2 mb-2">Stock by Product</a>
                                <a href="stock-viewer-by-warehouse.php" class="btn btn-outline-primary mb-2">Stock by Warehouse</a>
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
        colors: [color || '#3699FF'],
        dataLabels: { enabled: false }
    }).render();
}

barChart('#chartByProfile', Object.keys(byProfile), Object.values(byProfile), '#3699FF');
barChart('#chartByWarehouse', Object.keys(byWarehouse), Object.values(byWarehouse), '#1BC5BD');
barChart('#chartTopProducts', topProducts.map(function(p){return p.productName;}), topProducts.map(function(p){return p.total_qty;}), '#F64E60');
</script>
</body>
</html>
