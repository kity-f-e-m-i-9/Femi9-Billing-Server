<?php
include("checksession.php");
include("config.php");

$totalProducts = 0;
$totalStock    = 0;
$stockList     = [];

try {

// Total distinct products in stock
$s = mysqli_prepare($db_conn,
    "SELECT COUNT(*) AS cnt FROM channel_partner_stock WHERE channel_partner_id = ?"
);
mysqli_stmt_bind_param($s, "i", $Login_user_IDvl);
mysqli_stmt_execute($s);
$totalProducts = (int)(mysqli_stmt_get_result($s)->fetch_assoc()['cnt'] ?? 0);
mysqli_stmt_close($s);

// Total closing stock units
$s = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(closing_qty), 0) AS total FROM channel_partner_stock WHERE channel_partner_id = ?"
);
mysqli_stmt_bind_param($s, "i", $Login_user_IDvl);
mysqli_stmt_execute($s);
$totalStock = (int)(mysqli_stmt_get_result($s)->fetch_assoc()['total'] ?? 0);
mysqli_stmt_close($s);

// Stock list sorted by closing qty
$s = mysqli_prepare($db_conn,
    "SELECT p.productName, cps.input_qty, cps.closing_qty
     FROM channel_partner_stock cps
     JOIN products p ON p.id = cps.product_id
     WHERE cps.channel_partner_id = ?
     ORDER BY cps.closing_qty DESC"
);
mysqli_stmt_bind_param($s, "i", $Login_user_IDvl);
mysqli_stmt_execute($s);
$res = mysqli_stmt_get_result($s);
while ($row = mysqli_fetch_assoc($res)) {
    $stockList[] = $row;
}
mysqli_stmt_close($s);

} catch (\Throwable $_e) {
    error_log('[CP dashboard] Query error: ' . $_e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard : <?php echo $business_name; ?></title>

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Femi9 Channel Partner">
    <meta name="theme-color" content="#f5b400">
    <link rel="apple-touch-icon" href="../../assets/images/pwa-icon-apple-touch.png">
    <script>
    if ("serviceWorker" in navigator) {
        window.addEventListener("load", function () {
            navigator.serviceWorker.register("service-worker.js");
        });
    }
    </script>
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
                    <div class="container">

                        <div class="row">
                            <div class="col-xl-12">
                                <div class="card widget widget-stats">
                                    <div class="card-body">
                                        <h1>Dashboard</h1>
                                        <h2>Welcome to <?php echo htmlspecialchars($business_name); ?></h2>

                                        <div class="row" style="margin-top:20px;">

                                            <div class="col-xl-6 col-md-6">
                                                <div class="card widget widget-stats">
                                                    <div class="card-body">
                                                        <div class="widget-stats-container d-flex">
                                                            <div class="widget-stats-icon widget-stats-icon-warning">
                                                                <i class="material-icons-outlined">inventory_2</i>
                                                            </div>
                                                            <div class="widget-stats-content flex-fill">
                                                                <span class="widget-stats-title">Products in Stock</span>
                                                                <span class="widget-stats-amount"><?php echo $totalProducts; ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-xl-6 col-md-6">
                                                <div class="card widget widget-stats">
                                                    <div class="card-body">
                                                        <div class="widget-stats-container d-flex">
                                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                                <i class="material-icons-outlined">stacked_bar_chart</i>
                                                            </div>
                                                            <div class="widget-stats-content flex-fill">
                                                                <span class="widget-stats-title">Total Stock Units</span>
                                                                <span class="widget-stats-amount"><?php echo inr_format($totalStock, 0); ?></span>
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

                        <!-- Product Stock Table -->
                        <div class="row">
                            <div class="col-xl-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">
                                            <i class="material-icons-outlined" style="vertical-align:middle;margin-right:6px;">inventory_2</i>
                                            Product Stock
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($stockList)): ?>
                                            <p class="text-muted">No stock assigned yet.</p>
                                        <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Product Name</th>
                                                        <th class="text-center">Input Qty</th>
                                                        <th class="text-center">Closing Stock</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php $n = 1; foreach ($stockList as $item): ?>
                                                    <tr>
                                                        <td><?php echo $n++; ?></td>
                                                        <td><b><?php echo htmlspecialchars($item['productName']); ?></b></td>
                                                        <td class="text-center"><?php echo inr_format((int)$item['input_qty'], 0); ?></td>
                                                        <td class="text-center"><?php echo inr_format((int)$item['closing_qty'], 0); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- End Product Table -->

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
