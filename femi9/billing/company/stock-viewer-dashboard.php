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
    <style>
        :root {
            --ata-tp-1: #667eea;
            --ata-tp-2: #764ba2;
        }
        .ata-page-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
        .ata-page-head h1 { font-size:20px; font-weight:600; color:#1f2937; margin:0; display:flex; align-items:center; }
        .ata-intro { color:#6b7280; font-size:13.5px; line-height:1.55; margin-bottom:16px; }

        .ata-nav-tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
        .ata-nav-tab { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:9px; font-size:13.5px; font-weight:500; text-decoration:none; transition:filter .15s, background .15s; }
        .ata-nav-tab i { font-size:17px; }
        .ata-nav-tab.active { background:linear-gradient(135deg, var(--ata-tp-1) 0%, var(--ata-tp-2) 100%); color:#fff; box-shadow:0 2px 8px rgba(102,126,234,.3); }
        .ata-nav-tab:not(.active) { background:#f3f4f6; color:#4b5563; }
        .ata-nav-tab:not(.active):hover { background:#e5e7eb; color:#1f2937; }

        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 18px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            border-left: 4px solid transparent;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
        }
        .stat-card.purple { border-color: #667eea; }
        .stat-card.orange { border-color: #f59e0b; }
        .stat-card.blue   { border-color: #3b82f6; }
        .stat-card.green  { border-color: #10b981; }
        .stat-card.red    { border-color: #dc3545; }
        .stat-card h2 { font-size: 26px; font-weight: 700; margin: 0 0 2px 0; color: #1f2937; }
        .stat-card p  { margin: 0; font-size: 11.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
        .stat-icon { font-size: 36px; opacity: .15; }
        .stat-card.purple .stat-icon { color: #667eea; }
        .stat-card.orange .stat-icon { color: #f59e0b; }
        .stat-card.blue   .stat-icon { color: #3b82f6; }
        .stat-card.green  .stat-icon { color: #10b981; }
        .stat-card.red    .stat-icon { color: #dc3545; }

        .chart-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            border: none;
        }
        .chart-card .card-body h5 {
            font-weight: 600;
            font-size: 15px;
            color: #1f2937;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chart-card .card-body h5 i { color: #667eea; font-size: 20px; }

        .chart-card-purple { background: #f1f2fd; }
        .chart-card-purple .card-body h5 i { color: #667eea; }
        .chart-card-green  { background: #e9fbf4; }
        .chart-card-green  .card-body h5 i { color: #10b981; }
        .chart-card-orange { background: #fef6e9; }
        .chart-card-orange .card-body h5 i { color: #f59e0b; }

        .explore-card .btn-outline-primary {
            border-radius: 8px;
            font-weight: 500;
            padding: 10px 18px;
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
                    <div class="ata-page-head">
                        <h1><i class="material-icons-outlined" style="font-size:22px;vertical-align:middle;margin-right:8px;color:var(--ata-tp-1);">dashboard</i>Stock Dashboard</h1>
                    </div>
                    <p class="ata-intro">A snapshot of closing stock across every company profile and warehouse.</p>

                    <div class="ata-nav-tabs">
                        <a href="stock-viewer-dashboard.php" class="ata-nav-tab active"><i class="material-icons-outlined">dashboard</i> Dashboard</a>
                        <a href="stock-viewer-by-profile.php" class="ata-nav-tab"><i class="material-icons-outlined">store</i> Stock Viewer</a>
                    </div>

                    <div class="row">
                        <div class="col-lg col-md-4 col-sm-6 mb-4">
                            <div class="stat-card purple">
                                <div>
                                    <h2><?=number_format($summary['total_closing_qty']);?></h2>
                                    <p>Total Closing Qty</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">inventory_2</i>
                            </div>
                        </div>
                        <div class="col-lg col-md-4 col-sm-6 mb-4">
                            <div class="stat-card blue">
                                <div>
                                    <h2><?=number_format($summary['sku_count']);?></h2>
                                    <p>SKUs in Stock</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">qr_code_2</i>
                            </div>
                        </div>
                        <div class="col-lg col-md-4 col-sm-6 mb-4">
                            <div class="stat-card orange">
                                <div>
                                    <h2><?=number_format($summary['company_profile_count']);?></h2>
                                    <p>Company Profiles</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">business</i>
                            </div>
                        </div>
                        <div class="col-lg col-md-4 col-sm-6 mb-4">
                            <div class="stat-card green">
                                <div>
                                    <h2><?=number_format($summary['warehouse_count']);?></h2>
                                    <p>Warehouses</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">warehouse</i>
                            </div>
                        </div>
                        <div class="col-lg col-md-4 col-sm-6 mb-4">
                            <div class="stat-card <?=$summary['low_stock_count'] > 0 ? 'red' : 'green';?>">
                                <div>
                                    <h2 style="<?=$summary['low_stock_count'] > 0 ? 'color:#dc3545;' : '';?>"><?=number_format($summary['low_stock_count']);?></h2>
                                    <p>Low Stock (&lt;<?=STOCK_VIEWER_LOW_STOCK_THRESHOLD;?>)</p>
                                </div>
                                <i class="material-icons-outlined stat-icon">report_problem</i>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card chart-card chart-card-purple"><div class="card-body">
                                <h5><i class="material-icons-outlined">donut_small</i>Stock by Company Profile</h5>
                                <div id="chartByProfile"></div>
                            </div></div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card chart-card chart-card-green"><div class="card-body">
                                <h5><i class="material-icons-outlined">store</i>Stock by Warehouse</h5>
                                <div id="chartByWarehouse"></div>
                            </div></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="card chart-card chart-card-orange"><div class="card-body">
                                <h5><i class="material-icons-outlined">leaderboard</i>Top 10 Products by Closing Qty</h5>
                                <div id="chartTopProducts"></div>
                            </div></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="card chart-card explore-card"><div class="card-body">
                                <h5><i class="material-icons-outlined">explore</i>Explore Further</h5>
                                <a href="stock-viewer-by-profile.php" class="btn btn-outline-primary mb-2">Open Stock Viewer (filter by Product / Company Profile / Warehouse)</a>
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
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        series: [{ name: 'Closing Qty', data: series }],
        plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
        xaxis: {
            categories: categories,
            labels: {
                rotate: 0,
                trim: true,
                hideOverlappingLabels: false,
                style: { fontSize: '11px' }
            }
        },
        grid: { padding: { bottom: 10 } },
        colors: [color || '#3699FF'],
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: function (val) { return val.toLocaleString('en-IN'); } } }
    }).render();
}

barChart('#chartByProfile', Object.keys(byProfile), Object.values(byProfile), '#667eea');
barChart('#chartByWarehouse', Object.keys(byWarehouse), Object.values(byWarehouse), '#10b981');
barChart('#chartTopProducts', topProducts.map(function(p){return p.productName;}), topProducts.map(function(p){return p.total_qty;}), '#f59e0b');
</script>
</body>
</html>
