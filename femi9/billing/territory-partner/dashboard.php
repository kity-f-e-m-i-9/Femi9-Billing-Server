<?php
include("checksession.php");
include("config.php");
include("insert_wallet_referral.php");

// ── Safe defaults (used if try block below fails for any reason) ─────────────
$locCount    = 0; $invCount    = 0; $advBalance = 0; $totalTarget = 0;
$productsSold = 0; $paidRevenue = 0; $stockList  = [];

try {

// Forced password change check
$checkReset = mysqli_prepare($db_conn,
    "SELECT id FROM forgotpassword
     WHERE usertype = 'territory_partner' AND mobilenumber = ? AND must_change_password = 1
     ORDER BY reset_at DESC LIMIT 1"
);
if ($checkReset) {
    mysqli_stmt_bind_param($checkReset, "s", $Login_user_mobile);
    mysqli_stmt_execute($checkReset);
    $resetData = mysqli_stmt_get_result($checkReset)->fetch_assoc();
    mysqli_stmt_close($checkReset);
    if ($resetData) {
        echo "<script>alert('For security reasons, you must change your password before continuing.');
              window.location='change-password.php?forced=1';</script>";
        exit;
    }
}

// Assigned locations count
$locStmt = mysqli_prepare($db_conn,
    "SELECT COUNT(*) AS cnt FROM territory_partner_locations WHERE territory_partner_id = ?"
);
mysqli_stmt_bind_param($locStmt, "i", $Login_user_IDvl);
mysqli_stmt_execute($locStmt);
$locCount = (int)(mysqli_stmt_get_result($locStmt)->fetch_assoc()['cnt'] ?? 0);
mysqli_stmt_close($locStmt);

// Total invoices created by this TP (customer + shop)
$custInvStmt = mysqli_prepare($db_conn,
    "SELECT COUNT(*) AS cnt FROM invoice WHERE user_id = ? AND user_type = 'territory_partner'"
);
mysqli_stmt_bind_param($custInvStmt, "s", $Login_user_IDvl);
mysqli_stmt_execute($custInvStmt);
$custInvCount = (int)(mysqli_stmt_get_result($custInvStmt)->fetch_assoc()['cnt'] ?? 0);
mysqli_stmt_close($custInvStmt);

$shopInvStmt = mysqli_prepare($db_conn,
    "SELECT COUNT(*) AS cnt FROM user_invoice WHERE from_user_id = ? AND from_user_type = 'territory_partner'"
);
mysqli_stmt_bind_param($shopInvStmt, "s", $Login_user_IDvl);
mysqli_stmt_execute($shopInvStmt);
$shopInvCount = (int)(mysqli_stmt_get_result($shopInvStmt)->fetch_assoc()['cnt'] ?? 0);
mysqli_stmt_close($shopInvStmt);

$invCount = $custInvCount + $shopInvCount;

// Advance balance
$balStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(balance_amount), 0) AS bal
     FROM tp_advance_payments WHERE territory_partner_id = ? AND status = 'active'"
);
mysqli_stmt_bind_param($balStmt, "i", $Login_user_IDvl);
mysqli_stmt_execute($balStmt);
$advBalance = (float)(mysqli_stmt_get_result($balStmt)->fetch_assoc()['bal'] ?? 0);
mysqli_stmt_close($balStmt);

// Total target
$targetStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(n.target_amount), 0) AS total
     FROM territory_partner_locations tpl
     JOIN partner_location_nodes n ON n.id = tpl.location_id
     WHERE tpl.territory_partner_id = ?"
);
mysqli_stmt_bind_param($targetStmt, "i", $Login_user_IDvl);
mysqli_stmt_execute($targetStmt);
$totalTarget = (float)(mysqli_stmt_get_result($targetStmt)->fetch_assoc()['total'] ?? 0);
mysqli_stmt_close($targetStmt);

// Total products sold (units) from TP-created invoices (customer + shop)
$custProdStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(ii.qty), 0) AS total
     FROM invoice_items ii
     JOIN invoice i ON i.inv_id = ii.inv_id
     WHERE i.user_id = ? AND i.user_type = 'territory_partner'"
);
mysqli_stmt_bind_param($custProdStmt, "s", $Login_user_IDvl);
mysqli_stmt_execute($custProdStmt);
$custProdSold = (int)(mysqli_stmt_get_result($custProdStmt)->fetch_assoc()['total'] ?? 0);
mysqli_stmt_close($custProdStmt);

$shopProdStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(uii.qty), 0) AS total
     FROM user_invoice_items uii
     JOIN user_invoice ui ON ui.inv_id = uii.inv_id
     WHERE ui.from_user_id = ? AND ui.from_user_type = 'territory_partner'"
);
mysqli_stmt_bind_param($shopProdStmt, "s", $Login_user_IDvl);
mysqli_stmt_execute($shopProdStmt);
$shopProdSold = (int)(mysqli_stmt_get_result($shopProdStmt)->fetch_assoc()['total'] ?? 0);
mysqli_stmt_close($shopProdStmt);

$productsSold = $custProdSold + $shopProdSold;

// Paid revenue from TP-created invoices (customer + shop)
$custRevStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(r.received), 0) AS total
     FROM receipt r
     JOIN invoice i ON i.inv_id = r.inv_id
     WHERE i.user_id = ? AND i.user_type = 'territory_partner'"
);
mysqli_stmt_bind_param($custRevStmt, "s", $Login_user_IDvl);
mysqli_stmt_execute($custRevStmt);
$custRevenue = (float)(mysqli_stmt_get_result($custRevStmt)->fetch_assoc()['total'] ?? 0);
mysqli_stmt_close($custRevStmt);

$shopRevStmt = mysqli_prepare($db_conn,
    "SELECT COALESCE(SUM(r.received), 0) AS total
     FROM receipt r
     JOIN user_invoice ui ON ui.inv_id = r.inv_id
     WHERE ui.from_user_id = ? AND ui.from_user_type = 'territory_partner'"
);
mysqli_stmt_bind_param($shopRevStmt, "s", $Login_user_IDvl);
mysqli_stmt_execute($shopRevStmt);
$shopRevenue = (float)(mysqli_stmt_get_result($shopRevStmt)->fetch_assoc()['total'] ?? 0);
mysqli_stmt_close($shopRevStmt);

$paidRevenue = $custRevenue + $shopRevenue;

// All stock products sorted by qty sold from TP-created invoices
$stockListStmt = mysqli_prepare($db_conn,
    "SELECT
         p.productName,
         tps.closing_qty,
         COALESCE(cust_sales.sold_qty, 0) + COALESCE(shop_sales.sold_qty, 0) AS sold_qty
     FROM territory_partner_stock tps
     JOIN products p ON p.id = tps.product_id
     LEFT JOIN (
         SELECT ii.pr_id, SUM(ii.qty) AS sold_qty
         FROM invoice_items ii
         JOIN invoice i ON i.inv_id = ii.inv_id
         WHERE i.user_id = ? AND i.user_type = 'territory_partner'
         GROUP BY ii.pr_id
     ) AS cust_sales ON cust_sales.pr_id = tps.product_id
     LEFT JOIN (
         SELECT uii.pr_id, SUM(uii.qty) AS sold_qty
         FROM user_invoice_items uii
         JOIN user_invoice ui ON ui.inv_id = uii.inv_id
         WHERE ui.from_user_id = ? AND ui.from_user_type = 'territory_partner'
         GROUP BY uii.pr_id
     ) AS shop_sales ON shop_sales.pr_id = tps.product_id
     WHERE tps.territory_partner_id = ?
     ORDER BY sold_qty DESC"
);
mysqli_stmt_bind_param($stockListStmt, "ssi", $Login_user_IDvl, $Login_user_IDvl, $Login_user_IDvl);
mysqli_stmt_execute($stockListStmt);
$stockListResult = mysqli_stmt_get_result($stockListStmt);
$stockList = [];
while ($row = mysqli_fetch_assoc($stockListResult)) {
    $stockList[] = $row;
}
mysqli_stmt_close($stockListStmt);

} catch (\Throwable $_dash_e) {
    error_log('[TP dashboard] Query error: ' . $_dash_e->getMessage());
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

                                        <!-- Row 1: Locations / Invoices / Advance Balance / Total Target -->
                                        <div class="row" style="margin-top:20px;">

                                            <div class="col-xl-3 col-md-6">
                                                <div class="card widget widget-stats">
                                                    <div class="card-body">
                                                        <div class="widget-stats-container d-flex">
                                                            <div class="widget-stats-icon widget-stats-icon-warning">
                                                                <i class="material-icons-outlined">place</i>
                                                            </div>
                                                            <div class="widget-stats-content flex-fill">
                                                                <span class="widget-stats-title">Locations</span>
                                                                <span class="widget-stats-amount"><?php echo $locCount; ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-xl-3 col-md-6">
                                                <div class="card widget widget-stats">
                                                    <div class="card-body">
                                                        <div class="widget-stats-container d-flex">
                                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                                <i class="material-icons-outlined">receipt_long</i>
                                                            </div>
                                                            <div class="widget-stats-content flex-fill">
                                                                <span class="widget-stats-title">Total Invoices</span>
                                                                <span class="widget-stats-amount"><?php echo $invCount; ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-xl-3 col-md-6">
                                                <div class="card widget widget-stats">
                                                    <div class="card-body">
                                                        <div class="widget-stats-container d-flex">
                                                            <div class="widget-stats-icon widget-stats-icon-primary">
                                                                <i class="material-icons-outlined">account_balance_wallet</i>
                                                            </div>
                                                            <div class="widget-stats-content flex-fill">
                                                                <span class="widget-stats-title">Advance Balance</span>
                                                                <span class="widget-stats-amount">₹<?php echo number_format($advBalance, 2); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-xl-3 col-md-6">
                                                <div class="card widget widget-stats">
                                                    <div class="card-body">
                                                        <div class="widget-stats-container d-flex">
                                                            <div class="widget-stats-icon widget-stats-icon-danger">
                                                                <i class="material-icons-outlined">flag</i>
                                                            </div>
                                                            <div class="widget-stats-content flex-fill">
                                                                <span class="widget-stats-title">Total Target</span>
                                                                <span class="widget-stats-amount">₹<?php echo number_format($totalTarget, 2); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                        <!-- End Row 1 -->

                                        <!-- Row 2: Products Sold / Paid Revenue -->
                                        <div class="row">

                                            <div class="col-xl-6 col-md-6">
                                                <div class="card widget widget-stats">
                                                    <div class="card-body">
                                                        <div class="widget-stats-container d-flex">
                                                            <div class="widget-stats-icon widget-stats-icon-warning">
                                                                <i class="material-icons-outlined">inventory_2</i>
                                                            </div>
                                                            <div class="widget-stats-content flex-fill">
                                                                <span class="widget-stats-title">Products Sold (Units)</span>
                                                                <span class="widget-stats-amount"><?php echo number_format($productsSold); ?></span>
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
                                                                <i class="material-icons-outlined">payments</i>
                                                            </div>
                                                            <div class="widget-stats-content flex-fill">
                                                                <span class="widget-stats-title">Paid Invoice Revenue</span>
                                                                <span class="widget-stats-amount">₹<?php echo number_format($paidRevenue, 2); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                        <!-- End Row 2 -->

                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Product Stock & Sales Table -->
                        <div class="row">
                            <div class="col-xl-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">
                                            <i class="material-icons-outlined" style="vertical-align:middle;margin-right:6px;">star</i>
                                            Product Stock &amp; Sales
                                            <span class="badge badge-success badge-style-light">Sorted by Top Selling</span>
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
                                                        <th class="text-center">Qty Sold</th>
                                                        <th class="text-center">Closing Stock</th>
                                                        <th class="text-center">Sales Rank</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                $rank = 1;
                                                $maxSold = (int)($stockList[0]['sold_qty'] ?? 1) ?: 1;
                                                foreach ($stockList as $item):
                                                    $pct = $maxSold > 0 ? round(($item['sold_qty'] / $maxSold) * 100) : 0;
                                                    $badgeClass = $rank === 1 ? 'badge-warning' : ($rank === 2 ? 'badge-secondary' : ($rank === 3 ? 'badge-danger' : 'badge-light'));
                                                ?>
                                                    <tr>
                                                        <td><?php echo $rank; ?></td>
                                                        <td><b><?php echo htmlspecialchars($item['productName']); ?></b></td>
                                                        <td class="text-center">
                                                            <span class="badge <?php echo $badgeClass; ?> badge-style-light" style="font-size:13px;padding:4px 10px;">
                                                                <?php echo number_format((int)$item['sold_qty']); ?> units
                                                            </span>
                                                        </td>
                                                        <td class="text-center"><?php echo number_format((int)$item['closing_qty']); ?></td>
                                                        <td style="min-width:140px;">
                                                            <div class="progress" style="height:8px;">
                                                                <div class="progress-bar <?php echo $rank===1 ? 'bg-warning' : ''; ?>"
                                                                     role="progressbar"
                                                                     style="width:<?php echo $pct; ?>%"
                                                                     aria-valuenow="<?php echo $pct; ?>"
                                                                     aria-valuemin="0" aria-valuemax="100">
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php $rank++; endforeach; ?>
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
