<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockViewerData.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['stockviewer', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

$products = get_stock_viewer_product_options($db_conn);
$selectedProductId = filter_var($_GET['product_id'] ?? '', FILTER_VALIDATE_INT) ?: null;

$rows = $selectedProductId ? get_stock_viewer_rows($db_conn, ['product_id' => $selectedProductId]) : [];

$byProfileChart = [];
foreach ($rows as $row) {
    $name = $row['company_godown_name'];
    $byProfileChart[$name] = ($byProfileChart[$name] ?? 0) + (int) $row['closing_qty'];
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
                        <h1>Stock by Product</h1>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <form method="get" class="row g-3 align-items-end">
                                        <div class="col-md-6">
                                            <label class="form-label">Product</label>
                                            <select name="product_id" class="form-control" onchange="this.form.submit()">
                                                <option value="" hidden>Select a product…</option>
                                                <?php foreach ($products as $p): ?>
                                                <option value="<?=(int)$p['id'];?>" <?=$selectedProductId === (int)$p['id'] ? 'selected' : '';?>><?=htmlspecialchars($p['productName'], ENT_QUOTES, 'UTF-8');?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

<?php if ($selectedProductId && !empty($rows)): ?>
                    <div class="row">
                        <div class="col-md-12 mb-4">
                            <div class="card"><div class="card-body">
                                <h5>Closing Qty by Company Profile</h5>
                                <div id="chartByProfile"></div>
                            </div></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div style="background:#fff;overflow:scroll;width:100%;">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Company Profile</th>
                                                <th>Warehouse</th>
                                                <th style="text-align:right;">Closing Qty</th>
                                                <th style="text-align:right;">Extra Pieces</th>
                                            </tr>
                                        </thead>
                                        <tbody>
<?php foreach ($rows as $row): ?>
                                            <tr>
                                                <td><?=htmlspecialchars($row['company_godown_name'], ENT_QUOTES, 'UTF-8');?></td>
                                                <td><?=$row['warehouse_id'] !== null ? htmlspecialchars($row['warehouse_code'] . ($row['warehouse_name'] ? ' - ' . $row['warehouse_name'] : ''), ENT_QUOTES, 'UTF-8') : 'Unassigned';?></td>
                                                <td align="right"><b><?=(int)$row['closing_qty'];?></b></td>
                                                <td align="right"><?=(int)$row['extra_pieces'];?></td>
                                            </tr>
<?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
<?php elseif ($selectedProductId): ?>
                    <div class="row"><div class="col"><div class="alert alert-info">No stock recorded for this product yet.</div></div></div>
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
var byProfile = <?php echo json_encode($byProfileChart); ?>;
new ApexCharts(document.querySelector('#chartByProfile'), {
    chart: { type: 'bar', height: 280, toolbar: { show: false } },
    series: [{ name: 'Closing Qty', data: Object.values(byProfile) }],
    xaxis: { categories: Object.keys(byProfile) },
    colors: ['#3699FF'],
    dataLabels: { enabled: false }
}).render();
</script>
<?php endif; ?>
</body>
</html>
