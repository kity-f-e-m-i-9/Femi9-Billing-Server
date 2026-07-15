<?php
include("checksession.php");
include("config.php");
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today     = date("Y-m-d");
$from_date = $_REQUEST['frdate'] ?? date("Y-m-d", strtotime("-6 days"));
$to_date   = $_REQUEST['todate'] ?? $today;

$stmt = mysqli_prepare($db_conn,
    "SELECT o.id, o.order_date, o.status, o.tp_invoice_id,
            i.product_id, i.qty, i.price, i.amount, p.productName
     FROM tp_purchase_orders o
     LEFT JOIN tp_purchase_order_items i ON i.po_id = o.id
     LEFT JOIN products p ON p.id = i.product_id
     WHERE o.territory_partner_id=? AND o.order_date BETWEEN ? AND ?
     ORDER BY o.order_date DESC, o.id DESC, i.id ASC"
);
mysqli_stmt_bind_param($stmt, "iss", $Login_user_IDvl, $from_date, $to_date);
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$orders = [];
foreach ($rows as $r) {
    $oid = $r['id'];
    if (!isset($orders[$oid])) {
        $orders[$oid] = [
            'order_date'    => $r['order_date'],
            'status'        => $r['status'],
            'tp_invoice_id' => $r['tp_invoice_id'],
            'lines'         => [],
            'total'         => 0,
        ];
    }
    if ($r['product_id']) {
        $orders[$oid]['lines'][] = ['product' => $r['productName'], 'qty' => $r['qty'], 'price' => $r['price'], 'amount' => $r['amount']];
        $orders[$oid]['total'] += (float)$r['amount'];
    }
}

$invNumbers = [];
$invIds = array_values(array_unique(array_filter(array_column($orders, 'tp_invoice_id'))));
if (!empty($invIds)) {
    $placeholders = implode(',', array_fill(0, count($invIds), '?'));
    $types = str_repeat('i', count($invIds));
    $stmtI = $db_conn->prepare("SELECT id, invoice_number FROM tp_invoices WHERE id IN ($placeholders)");
    $stmtI->bind_param($types, ...$invIds);
    $stmtI->execute();
    $resI = $stmtI->get_result();
    while ($ir = $resI->fetch_assoc()) { $invNumbers[$ir['id']] = $ir['invoice_number']; }
    $stmtI->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Purchase Orders : <?php echo $business_name;?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                                                <td>My Purchase Orders</td>
                                                <td><a href="add-purchase-order.php" title="New Purchase Order"><i class="material-icons">add</i></a></td>
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
                                                    <th>Invoice No.</th>
                                                    <th>Products</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($orders)): ?>
                                                <tr><td colspan="5" class="text-center">No purchase orders in this date range.</td></tr>
                                                <?php else: foreach ($orders as $oid => $o): ?>
                                                <tr>
                                                    <td><?=htmlspecialchars(date("d-m-Y", strtotime($o['order_date'])))?></td>
                                                    <td>
                                                        <?php if ($o['status'] === 'completed' && $o['tp_invoice_id'] && isset($invNumbers[$o['tp_invoice_id']])): ?>
                                                        <a href="purchased-bill-print.php?id=<?=urlencode(base64_encode($o['tp_invoice_id']))?>" target="_blank" title="Open Invoice">
                                                            <?=htmlspecialchars($invNumbers[$o['tp_invoice_id']])?>
                                                        </a>
                                                        <?php else: ?>
                                                        -
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php foreach ($o['lines'] as $ln): ?>
                                                        <?=htmlspecialchars($ln['product'] ?? '-')?>: <b><?=htmlspecialchars($ln['qty'])?></b><br/>
                                                        <?php endforeach; ?>
                                                    </td>
                                                    <td>₹<?=number_format($o['total'], 2)?></td>
                                                    <td>
                                                        <?php if ($o['status'] === 'completed'): ?>
                                                        <span class="badge bg-success">Completed</span>
                                                        <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Waiting</span>
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
