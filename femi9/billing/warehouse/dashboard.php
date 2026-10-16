<?php
include("checksession.php");
include("config.php");

$warehouses = [];
$w = mysqli_query($db_conn, "SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
while ($row = mysqli_fetch_assoc($w)) {
    $warehouses[] = $row;
}

$selectedWarehouseId = isset($_GET['warehouse_id']) && $_GET['warehouse_id'] !== ''
    ? (int)$_GET['warehouse_id']
    : null;

$totalProducts = 0;
$totalStock    = 0;
$stockList     = [];
$lowThreshold  = 10;

try {

if ($selectedWarehouseId !== null) {
    // Single godown: stock physically tagged to it, any company entity.
    $s = mysqli_prepare($db_conn,
        "SELECT p.productName, SUM(st.closing_qty) AS closing_qty
         FROM stock st
         JOIN products p ON p.id = st.product_id
         WHERE st.warehouse_id = ?
         GROUP BY st.product_id, p.productName
         HAVING SUM(st.closing_qty) <> 0
         ORDER BY closing_qty DESC"
    );
    mysqli_stmt_bind_param($s, "i", $selectedWarehouseId);
} else {
    // All godowns combined, per product.
    $s = mysqli_prepare($db_conn,
        "SELECT p.productName, SUM(st.closing_qty) AS closing_qty
         FROM stock st
         JOIN products p ON p.id = st.product_id
         WHERE st.warehouse_id IS NOT NULL
         GROUP BY st.product_id, p.productName
         HAVING SUM(st.closing_qty) <> 0
         ORDER BY closing_qty DESC"
    );
}
mysqli_stmt_execute($s);
$res = mysqli_stmt_get_result($s);
while ($row = mysqli_fetch_assoc($res)) {
    $stockList[] = $row;
    $totalStock += (int)$row['closing_qty'];
}
$totalProducts = count($stockList);
mysqli_stmt_close($s);

// Per-godown totals (always all godowns) for the breakdown chart + cards.
$godownTotals = [];
$g = mysqli_query($db_conn,
    "SELECT w.id, w.code, w.name, COALESCE(SUM(st.closing_qty),0) AS qty, COUNT(DISTINCT CASE WHEN st.closing_qty <> 0 THEN st.product_id END) AS products
     FROM warehouses w
     LEFT JOIN stock st ON st.warehouse_id = w.id
     WHERE w.is_active = 1
     GROUP BY w.id, w.code, w.name
     ORDER BY w.code ASC");
while ($g && ($row = mysqli_fetch_assoc($g))) {
    $godownTotals[] = $row;
}

} catch (\Throwable $_e) {
    error_log('[warehouse dashboard] Query error: ' . $_e->getMessage());
}

$lowStockCount = 0;
foreach ($stockList as $_i) { if ((int)$_i['closing_qty'] < $lowThreshold) $lowStockCount++; }
$maxQty = 0;
foreach ($stockList as $_i) { $maxQty = max($maxQty, (int)$_i['closing_qty']); }
$selectedLabel = 'All Godowns';
foreach ($warehouses as $wh) {
    if ($selectedWarehouseId === (int)$wh['id']) {
        $selectedLabel = $wh['code'] . ($wh['name'] ? ' - ' . $wh['name'] : '');
    }
}
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
                            <h1>Stock Dashboard</h1>
                            <span>Viewing: <b><?php echo htmlspecialchars($selectedLabel); ?></b></span>
                        </div>

                        <!-- KPI cards -->
                        <div class="row">
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card wh-card wh-kpi"><div class="card-body">
                                    <div class="wh-kpi-icon" style="background:#e8eaf6;color:#3f51b5;"><i class="material-icons-outlined">inventory_2</i></div>
                                    <div><p class="wh-kpi-label">Products in Stock</p><p class="wh-kpi-value"><?php echo inr_format($totalProducts, 0); ?></p></div>
                                </div></div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card wh-card wh-kpi"><div class="card-body">
                                    <div class="wh-kpi-icon" style="background:#e8eaf6;color:#3f51b5;"><i class="material-icons-outlined">stacked_bar_chart</i></div>
                                    <div><p class="wh-kpi-label">Total Stock Units</p><p class="wh-kpi-value"><?php echo inr_format($totalStock, 0); ?></p></div>
                                </div></div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card wh-card wh-kpi"><div class="card-body">
                                    <div class="wh-kpi-icon" style="background:#e8eaf6;color:#5c6bc0;"><i class="material-icons-outlined">warehouse</i></div>
                                    <div><p class="wh-kpi-label">Active Godowns</p><p class="wh-kpi-value"><?php echo count($warehouses); ?></p></div>
                                </div></div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card wh-card wh-kpi"><div class="card-body">
                                    <div class="wh-kpi-icon" style="background:#fee2e2;color:#dc2626;"><i class="material-icons-outlined">warning_amber</i></div>
                                    <div><p class="wh-kpi-label">Low Stock (&lt;<?php echo $lowThreshold; ?>)</p><p class="wh-kpi-value" style="<?php echo $lowStockCount > 0 ? 'color:#dc2626;' : ''; ?>"><?php echo inr_format($lowStockCount, 0); ?></p></div>
                                </div></div>
                            </div>
                        </div>

                        <!-- Godown breakdown -->
                        <?php if (!empty($godownTotals)): ?>
                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="card wh-card"><div class="card-body">
                                    <h5 class="mb-1">Stock by Godown</h5>
                                    <div id="chartByGodown"></div>
                                </div></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Filter + table -->
                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="card wh-card">
                                    <div class="card-body">
                                        <div class="wh-toolbar">
                                            <div style="flex:1;min-width:220px;max-width:360px;">
                                                <label class="form-label" for="whSearch">Search product</label>
                                                <input type="text" id="whSearch" class="form-control" placeholder="Type a product name…" autocomplete="off">
                                            </div>
                                            <form method="GET" style="min-width:220px;">
                                                <label class="form-label" for="whSelect">Godown</label>
                                                <select id="whSelect" name="warehouse_id" class="form-control" onchange="this.form.submit()">
                                                    <option value="">All Godowns</option>
                                                    <?php foreach ($warehouses as $wh): ?>
                                                        <option value="<?php echo (int)$wh['id']; ?>" <?php echo ($selectedWarehouseId === (int)$wh['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($wh['code']); ?><?php echo $wh['name'] ? ' - ' . htmlspecialchars($wh['name']) : ''; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </div>

                                        <div class="wh-pills">
                                            <a class="wh-pill <?php echo $selectedWarehouseId === null ? 'active' : ''; ?>" href="dashboard.php">All</a>
                                            <?php foreach ($godownTotals as $gt): ?>
                                                <a class="wh-pill <?php echo $selectedWarehouseId === (int)$gt['id'] ? 'active' : ''; ?>" href="dashboard.php?warehouse_id=<?php echo (int)$gt['id']; ?>">
                                                    <?php echo htmlspecialchars($gt['code']); ?><small><?php echo inr_format((int)$gt['qty'], 0); ?></small>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>

                                        <?php if (empty($stockList)): ?>
                                            <div class="wh-empty">
                                                <i class="material-icons-outlined">inventory_2</i>
                                                No stock found for this selection.
                                            </div>
                                        <?php else: ?>
                                        <div class="table-responsive" style="margin-top:18px;">
                                            <table class="table table-hover wh-table" id="whTable">
                                                <thead>
                                                    <tr>
                                                        <th style="width:50px;">#</th>
                                                        <th>Product</th>
                                                        <th class="text-end" style="width:140px;">Closing Stock</th>
                                                        <th style="width:180px;">Level</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php $n = 1; foreach ($stockList as $item):
                                                    $q = (int)$item['closing_qty'];
                                                    $isLow = $q < $lowThreshold;
                                                    $pct = $maxQty > 0 ? max(2, min(100, round($q / $maxQty * 100))) : 0;
                                                ?>
                                                    <tr data-name="<?php echo htmlspecialchars(strtolower($item['productName'])); ?>">
                                                        <td class="text-muted"><?php echo $n++; ?></td>
                                                        <td><b><?php echo htmlspecialchars($item['productName']); ?></b>
                                                            <?php if ($isLow): ?> <span class="wh-badge-low">Low</span><?php endif; ?></td>
                                                        <td class="text-end"><b><?php echo inr_format($q, 0); ?></b></td>
                                                        <td><div class="wh-bar <?php echo $isLow ? 'low' : ''; ?>"><span style="width:<?php echo $pct; ?>%"></span></div></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <div class="wh-empty" id="whNoMatch" style="display:none;padding:28px;">No products match your search.</div>
                                        </div>
                                        <?php endif; ?>
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
    <script src="../../assets/plugins/apexcharts/apexcharts.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script>
    (function () {
        var input = document.getElementById('whSearch');
        var rows = document.querySelectorAll('#whTable tbody tr');
        var none = document.getElementById('whNoMatch');
        if (input) {
            input.addEventListener('input', function () {
                var q = input.value.trim().toLowerCase(), shown = 0;
                rows.forEach(function (r) {
                    var ok = r.getAttribute('data-name').indexOf(q) !== -1;
                    r.style.display = ok ? '' : 'none';
                    if (ok) shown++;
                });
                if (none) none.style.display = shown ? 'none' : 'block';
            });
        }
        var gt = <?php echo json_encode(array_map(function ($r) { return ['label' => $r['code'], 'qty' => (int)$r['qty']]; }, $godownTotals)); ?>;
        var el = document.querySelector('#chartByGodown');
        if (el && gt.length) {
            new ApexCharts(el, {
                chart: { type: 'bar', height: 260, toolbar: { show: false } },
                series: [{ name: 'Closing Qty', data: gt.map(function (g) { return g.qty; }) }],
                xaxis: { categories: gt.map(function (g) { return g.label; }) },
                colors: ['#3f51b5'],
                plotOptions: { bar: { borderRadius: 6, columnWidth: '38%' } },
                dataLabels: { enabled: false },
                grid: { borderColor: '#eef2f6' }
            }).render();
        }
    })();
    </script>
</body>
</html>
