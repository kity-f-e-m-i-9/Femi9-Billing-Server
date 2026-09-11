<?php
include("checksession.php");
include("config.php");
error_reporting(0);
mysqli_set_charset($db_conn, 'utf8mb4');

// Every product this SS's own Territory Partners could have stock of.
$products = [];
$prod_res = mysqli_query($db_conn, "SELECT id, productName FROM products WHERE (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY id ASC");
if ($prod_res) while ($pr = mysqli_fetch_assoc($prod_res)) $products[$pr['id']] = $pr['productName'];

// This SS's own Territory Partners (onboard_ss_id links a TP back to the SS
// that onboarded them — same link the dashboard's "Territory Partner wise"
// card totals against).
$tp_rows = [];
$tp_res = mysqli_query($db_conn, "SELECT id, tp_id, name, mobile FROM territory_partners WHERE onboard_ss_id='$Login_user_IDvl' ORDER BY name ASC");
if ($tp_res) while ($row = mysqli_fetch_assoc($tp_res)) $tp_rows[] = $row;

// Stock quantities for these TPs, one query for all of them.
$stock_data = [];
if (!empty($tp_rows)) {
    $tp_db_ids = implode(',', array_map(fn($r) => (int)$r['id'], $tp_rows));
    $stk_res = mysqli_query($db_conn, "
        SELECT territory_partner_id, product_id, input_qty, closing_qty
        FROM territory_partner_stock
        WHERE territory_partner_id IN ($tp_db_ids)
    ");
    if ($stk_res) while ($row = mysqli_fetch_assoc($stk_res))
        $stock_data[$row['territory_partner_id']][$row['product_id']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Territory Partner Stock : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">

    <style>
        #overflowon { width: 100%; overflow-x: auto; }
        .table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 600; white-space: nowrap; font-size: 14px;
            padding: 12px 8px; border-color: #dee2e6; color: #495057;
        }
        .table td {
            white-space: nowrap; font-size: 13px; padding: 10px 8px;
            vertical-align: middle; border-color: #dee2e6;
        }
        .product-col { background: linear-gradient(135deg, #e3f2fd 0%, #f0f8ff 100%); text-align: center; min-width: 80px; font-weight: 500; }
        .stock-positive { color: #28a745; font-weight: 600; }
        .stock-zero     { color: #dc3545; font-weight: 600; }
        .stock-low      { color: #ffc107; font-weight: 600; }
        .table-bordered { border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; }
        .table-hover tbody tr:hover { background-color: rgba(0,123,255,0.05); }
        .card { border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-radius: 12px; }
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
                                            <td>Territory Partner Stock</td>
                                            <td><a href="dashboard">&#8592;&nbsp;Dashboard</a></td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <?php if (!empty($tp_rows)): ?>
                                <div class="card-header py-3">
                                    <span class="text-muted small"><?= count($tp_rows); ?> Territory Partner(s) under you</span>
                                </div>
                                <div class="card-body px-3 pt-0 pb-2">
                                    <div id="overflowon">
                                        <table class="table table-bordered table-hover table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th rowspan="2">#</th>
                                                    <th rowspan="2">Territory Partner</th>
                                                    <th colspan="<?= count($products); ?>" style="text-align:center; background:#e3f2fd;">Product Stock (Closing Qty)</th>
                                                    <th rowspan="2">Total</th>
                                                </tr>
                                                <tr>
                                                    <?php foreach ($products as $pr_id => $pr_name): ?>
                                                    <th class="product-col" title="<?= htmlspecialchars($pr_name); ?>">
                                                        <?= htmlspecialchars(strlen($pr_name) > 18 ? substr($pr_name, 0, 15) . '...' : $pr_name); ?>
                                                    </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            $serial = 1;
                                            $product_totals = array_fill_keys(array_keys($products), 0);
                                            $grand_total = 0;
                                            foreach ($tp_rows as $tp):
                                                $tp_stock = $stock_data[$tp['id']] ?? [];
                                                $row_total = 0;
                                            ?>
                                            <tr>
                                                <td class="text-muted"><?= $serial++; ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($tp['name']); ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($tp['tp_id']); ?> &middot; <?= htmlspecialchars($tp['mobile']); ?></small>
                                                </td>
                                                <?php foreach ($products as $pr_id => $pr_name):
                                                    $stk = $tp_stock[$pr_id] ?? null;
                                                    $closing = $stk ? (int)$stk['closing_qty'] : 0;
                                                    $product_totals[$pr_id] += $closing;
                                                    $row_total += $closing;
                                                    $cls = 'stock-zero';
                                                    if ($closing > 10) $cls = 'stock-positive';
                                                    elseif ($closing > 0) $cls = 'stock-low';
                                                ?>
                                                <td align="center" class="product-col" title="Input: <?= $stk ? $stk['input_qty'] : 0; ?>">
                                                    <?php if ($closing > 0): ?>
                                                        <strong class="<?= $cls; ?>"><?= $closing; ?></strong>
                                                    <?php else: ?>
                                                        <span style="color:#ccc;">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endforeach; ?>
                                                <td align="center">
                                                    <strong class="<?= $row_total > 0 ? 'stock-positive' : 'stock-zero'; ?>"><?= $row_total; ?></strong>
                                                </td>
                                            </tr>
                                            <?php
                                                $grand_total += $row_total;
                                            endforeach;
                                            ?>
                                            </tbody>
                                            <tfoot>
                                                <tr style="background:#e9ecef; font-weight:bold;">
                                                    <th colspan="2" align="right">Total:</th>
                                                    <?php foreach ($product_totals as $total): ?>
                                                    <th align="center" class="product-col"><?= $total; ?></th>
                                                    <?php endforeach; ?>
                                                    <th align="center"><?= $grand_total; ?></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="card-body">
                                    <div class="text-center py-4 text-muted">
                                        <i class="material-icons" style="font-size:40px;color:#ced4da;display:block;margin-bottom:8px;">inventory_2</i>
                                        No Territory Partners onboarded under you yet.
                                    </div>
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

<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
</body>
</html>
