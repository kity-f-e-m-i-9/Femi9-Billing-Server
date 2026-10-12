<?php
include("checksession.php");
include("config.php");
require_once(__DIR__ . "/../company/include/StockViewerData.php");

$products = get_stock_viewer_product_options($db_conn);
$selectedProductId = filter_var($_GET['product_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$rows = $selectedProductId ? get_stock_viewer_rows($db_conn, ['product_id' => $selectedProductId]) : [];

$byProfile = []; $byGodown = []; $total = 0; $productName = '';
foreach ($rows as $r) {
    $q = (int)$r['closing_qty'];
    $total += $q;
    $productName = $r['productName'];
    $byProfile[$r['company_godown_name']] = ($byProfile[$r['company_godown_name']] ?? 0) + $q;
    $gl = $r['warehouse_id'] !== null ? $r['warehouse_code'] : 'Unassigned';
    $byGodown[$gl] = ($byGodown[$gl] ?? 0) + $q;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock by Product : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <?php include("include/stock-style.php"); ?>
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
                            <h1>Stock by Product</h1>
                            <span>Pick a product to see where its stock sits</span>
                        </div>

                        <div class="card wh-card mb-4"><div class="card-body">
                            <form method="get" class="wh-toolbar">
                                <div style="flex:1;min-width:260px;max-width:480px;">
                                    <label class="form-label" for="prodSel">Product</label>
                                    <select id="prodSel" name="product_id" class="form-control" onchange="this.form.submit()">
                                        <option value="" hidden>Select a product…</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?php echo (int)$p['id']; ?>" <?php echo $selectedProductId === (int)$p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['productName']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div></div>

                        <?php if (!$selectedProductId): ?>
                            <div class="card wh-card"><div class="card-body"><div class="wh-empty"><i class="material-icons-outlined">search</i>Select a product above to view its stock.</div></div></div>
                        <?php elseif (empty($rows)): ?>
                            <div class="card wh-card"><div class="card-body"><div class="wh-empty"><i class="material-icons-outlined">inventory_2</i>No stock recorded for this product yet.</div></div></div>
                        <?php else: ?>
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="card wh-card wh-kpi"><div class="card-body">
                                    <div class="wh-kpi-icon" style="background:#e8eaf6;color:#3f51b5;"><i class="material-icons-outlined">stacked_bar_chart</i></div>
                                    <div><p class="wh-kpi-label"><?php echo htmlspecialchars($productName); ?> — total</p><p class="wh-kpi-value"><?php echo inr_format($total, 0); ?></p></div>
                                </div></div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card wh-card wh-kpi"><div class="card-body">
                                    <div class="wh-kpi-icon" style="background:#e8eaf6;color:#3f51b5;"><i class="material-icons-outlined">store</i></div>
                                    <div><p class="wh-kpi-label">Company Profiles</p><p class="wh-kpi-value"><?php echo count($byProfile); ?></p></div>
                                </div></div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card wh-card wh-kpi"><div class="card-body">
                                    <div class="wh-kpi-icon" style="background:#e8eaf6;color:#5c6bc0;"><i class="material-icons-outlined">warehouse</i></div>
                                    <div><p class="wh-kpi-label">Godowns Holding It</p><p class="wh-kpi-value"><?php echo count($byGodown); ?></p></div>
                                </div></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-6 mb-4"><div class="card wh-card"><div class="card-body"><h5>By Company Profile</h5><div id="chProfile"></div></div></div></div>
                            <div class="col-lg-6 mb-4"><div class="card wh-card"><div class="card-body"><h5>By Godown</h5><div id="chGodown"></div></div></div></div>
                        </div>

                        <div class="card wh-card mb-4"><div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover wh-table">
                                    <thead><tr><th>Company Profile</th><th>Godown</th><th class="text-end">Closing Qty</th><th class="text-end">Extra Pieces</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($rows as $r): ?>
                                        <tr>
                                            <td><b><?php echo htmlspecialchars($r['company_godown_name']); ?></b></td>
                                            <td><?php echo $r['warehouse_id'] !== null ? htmlspecialchars($r['warehouse_code'] . ($r['warehouse_name'] ? ' - ' . $r['warehouse_name'] : '')) : '<span class="text-muted">Unassigned</span>'; ?></td>
                                            <td class="text-end"><b><?php echo inr_format((int)$r['closing_qty'], 0); ?></b></td>
                                            <td class="text-end"><?php echo (int)$r['extra_pieces']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div></div>
                        <?php endif; ?>
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
    <?php if ($selectedProductId && !empty($rows)): ?>
    <script>
    function whBar(sel, data, color) {
        new ApexCharts(document.querySelector(sel), {
            chart: { type: 'bar', height: 260, toolbar: { show: false } },
            series: [{ name: 'Closing Qty', data: Object.values(data) }],
            xaxis: { categories: Object.keys(data) },
            colors: [color],
            plotOptions: { bar: { borderRadius: 6, columnWidth: '40%' } },
            dataLabels: { enabled: false },
            grid: { borderColor: '#eef2f6' }
        }).render();
    }
    whBar('#chProfile', <?php echo json_encode($byProfile); ?>, '#3f51b5');
    whBar('#chGodown', <?php echo json_encode($byGodown); ?>, '#3f51b5');
    </script>
    <?php endif; ?>
</body>
</html>
