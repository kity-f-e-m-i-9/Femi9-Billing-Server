<?php
include("checksession.php");
include("config.php");
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today    = date("Y-m-d");
$from_date = $_REQUEST['frdate'] ?? date("Y-m-d", strtotime("-6 days"));
$to_date   = $_REQUEST['todate'] ?? $today;

$stmt = mysqli_prepare($db_conn,
    "SELECT o.id, o.order_id, o.order_date, o.new_order, o.noorder_reason, o.marketing_tool,
            o.pr_id, o.qty, o.invoiced_inv_id, s.name shop_name, p.productName
     FROM tp_orders o
     LEFT JOIN shop s ON s.id=o.shop_id
     LEFT JOIN products p ON p.id=o.pr_id
     WHERE o.tp_id=? AND o.order_date BETWEEN ? AND ?
     ORDER BY o.order_date DESC, o.order_id DESC, o.id ASC"
);
mysqli_stmt_bind_param($stmt, "iss", $Login_user_IDvl, $from_date, $to_date);
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Group got-order lines under one order_id so each visit is a single row/card.
$visits = [];
foreach ($rows as $r) {
    $oid = $r['order_id'];
    if (!isset($visits[$oid])) {
        $visits[$oid] = [
            'order_date' => $r['order_date'],
            'shop_name'  => $r['shop_name'],
            'new_order'  => $r['new_order'],
            'noorder_reason' => $r['noorder_reason'],
            'invoiced_inv_id' => $r['invoiced_inv_id'],
            'lines'      => [],
        ];
    }
    if ($r['new_order'] === 'yes') {
        $visits[$oid]['lines'][] = ['product' => $r['productName'], 'qty' => $r['qty']];
    }
}

// An invoice only gets a `receipt` row once "Submit Invoice" has actually been
// clicked on shop-invoice-add.php (see shop-invoice-submit.php) — items can
// exist on a draft invoice with no receipt yet, so this is what distinguishes
// "Continue Invoice" (still mid-way) from "Completed Invoice" (submitted).
$invIds = array_values(array_unique(array_filter(array_column($visits, 'invoiced_inv_id'))));
$completedInvIds = [];
if (!empty($invIds)) {
    $placeholders = implode(',', array_fill(0, count($invIds), '?'));
    $types = str_repeat('s', count($invIds));
    $stmtR = mysqli_prepare($db_conn, "SELECT DISTINCT inv_id FROM receipt WHERE inv_id IN ($placeholders)");
    mysqli_stmt_bind_param($stmtR, $types, ...$invIds);
    mysqli_stmt_execute($stmtR);
    $resR = mysqli_stmt_get_result($stmtR);
    while ($rr = mysqli_fetch_assoc($resR)) { $completedInvIds[$rr['inv_id']] = true; }
    mysqli_stmt_close($stmtR);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Field Orders : <?php echo $business_name;?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
</head>
<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
        <div class="app-container">
            <?php include("app-header.php");?>
            <div class="app-content">
                <div class="content-wrapper">
                    <div class="container-fluid">

                        <div class="row">
                            <div class="col">
                                <div class="page-description">
                                    <h1>
                                        <table class="headertble">
                                            <tr>
                                                <td>Manage Field Orders</td>
                                                <td><a href="add-order.php" title="Add Order"><i class="material-icons">add</i></a></td>
                                            </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($_SESSION['successMessage'])): ?>
                        <div class="alert alert-success"><?=htmlspecialchars($_SESSION['successMessage']); unset($_SESSION['successMessage']);?></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['errorMessage'])): ?>
                        <div class="alert alert-danger"><?=htmlspecialchars($_SESSION['errorMessage']); unset($_SESSION['errorMessage']);?></div>
                        <?php endif; ?>

                        <form method="get" class="row g-2 align-items-end mb-3">
                            <div class="col-auto">
                                <label class="form-label">From Date</label>
                                <input type="date" name="frdate" value="<?=htmlspecialchars($from_date)?>" class="form-control">
                            </div>
                            <div class="col-auto">
                                <label class="form-label">To Date</label>
                                <input type="date" name="todate" value="<?=htmlspecialchars($to_date)?>" class="form-control">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary"><i class="material-icons">search</i> Search</button>
                            </div>
                        </form>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Shop</th>
                                                    <th>Status</th>
                                                    <th>Details</th>
                                                    <th>Invoice</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($visits)): ?>
                                                <tr><td colspan="5" class="text-center">No field order entries in this date range.</td></tr>
                                                <?php else: foreach ($visits as $oid => $v): ?>
                                                <tr>
                                                    <td><?=htmlspecialchars(date("d-m-Y", strtotime($v['order_date'])))?></td>
                                                    <td><?=htmlspecialchars($v['shop_name'] ?? '-')?></td>
                                                    <td>
                                                        <?php if ($v['new_order'] === 'yes'): ?>
                                                        <span class="badge bg-success">Get Order</span>
                                                        <?php else: ?>
                                                        <span class="badge bg-danger">No Order</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($v['new_order'] === 'yes'): ?>
                                                            <?php foreach ($v['lines'] as $ln): ?>
                                                            <?=htmlspecialchars($ln['product'] ?? '-')?>: <b><?=htmlspecialchars($ln['qty'])?></b><br/>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            Reason: <?=htmlspecialchars($v['noorder_reason'])?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($v['new_order'] === 'yes'): ?>
                                                            <?php if (!empty($v['invoiced_inv_id']) && isset($completedInvIds[$v['invoiced_inv_id']])): ?>
                                                            <a href="shop-invoice-print.php?invoiceid=<?=base64_encode($v['invoiced_inv_id'])?>" class="btn btn-success btn-sm" target="_blank">Completed Invoice</a>
                                                            <?php elseif (!empty($v['invoiced_inv_id'])): ?>
                                                            <a href="shop-invoice-add.php?InvoiceID=<?=base64_encode($v['invoiced_inv_id'])?>&invuser=shop&action=edit" class="btn btn-outline-primary btn-sm">Continue Invoice</a>
                                                            <?php else: ?>
                                                            <a href="order-to-invoice.php?order_id=<?=urlencode($oid)?>" onclick="return confirm('Create an invoice for this order, using the shop and product/qty exactly as captured here?');" class="btn btn-primary btn-sm">Invoice</a>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; endif; ?>
                                            </tbody>
                                        </table>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
