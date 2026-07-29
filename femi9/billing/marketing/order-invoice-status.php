<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$order_id = $_GET['order_id'] ?? '';

// Ownership check — a DM may only view their own orders.
$owns = false;
if ($order_id !== '') {
    $stmtOwn = $db_conn->prepare("SELECT COUNT(*) AS n FROM ms_orders WHERE order_id=? AND ms_id=?");
    $stmtOwn->bind_param('ss', $order_id, $markeingSTFID);
    $stmtOwn->execute();
    $owns = (int)($stmtOwn->get_result()->fetch_assoc()['n'] ?? 0) > 0;
    $stmtOwn->close();
}

$sentLines = [];
$invoiceLines = [];
$historyRows = [];
$invId = null;
$invMeta = null;

if ($owns) {
    $stmtSent = $db_conn->prepare(
        "SELECT mo.pr_id, mo.qty, p.productName
         FROM ms_orders mo
         LEFT JOIN products p ON p.id = mo.pr_id
         WHERE mo.order_id=? AND mo.new_order='yes'
         ORDER BY mo.id ASC"
    );
    $stmtSent->bind_param('s', $order_id);
    $stmtSent->execute();
    $sentLines = $stmtSent->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtSent->close();

    $stmtTp = $db_conn->prepare("SELECT invoiced_inv_id FROM tp_orders WHERE order_id=? LIMIT 1");
    $stmtTp->bind_param('s', $order_id);
    $stmtTp->execute();
    $invId = $stmtTp->get_result()->fetch_assoc()['invoiced_inv_id'] ?? null;
    $stmtTp->close();

    if (!empty($invId)) {
        $stmtInv = $db_conn->prepare("SELECT inv_number, status, total, date FROM user_invoice WHERE inv_id=? LIMIT 1");
        $stmtInv->bind_param('s', $invId);
        $stmtInv->execute();
        $invMeta = $stmtInv->get_result()->fetch_assoc();
        $stmtInv->close();

        // Same payment-status logic as the TP's own shop-manage-invoice.php.
        $stmtRcpt = $db_conn->prepare("SELECT SUM(received) AS total_received FROM receipt WHERE inv_id=?");
        $stmtRcpt->bind_param('s', $invId);
        $stmtRcpt->execute();
        $totalReceived = (float)($stmtRcpt->get_result()->fetch_assoc()['total_received'] ?? 0);
        $stmtRcpt->close();
        $invTotal = (float)($invMeta['total'] ?? 0);
        if ($totalReceived <= 0) {
            $paymentStatusHtml = "<span class='badge bg-danger'>Not Paid</span>";
        } elseif ($totalReceived >= $invTotal) {
            $paymentStatusHtml = "<span class='badge bg-success'>Fully Paid</span>";
        } else {
            $paymentStatusHtml = "<span class='badge bg-warning text-dark'>Partially Paid</span> (&#8377;" . inr_format($totalReceived, 2) . " paid, &#8377;" . inr_format($invTotal - $totalReceived, 2) . " pending)";
        }

        $stmtItems = $db_conn->prepare(
            "SELECT uii.pr_id, uii.qty, p.productName
             FROM user_invoice_items uii
             LEFT JOIN products p ON p.id = uii.pr_id
             WHERE uii.inv_id=?
             ORDER BY uii.id ASC"
        );
        $stmtItems->bind_param('s', $invId);
        $stmtItems->execute();
        $invoiceLines = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtItems->close();

        $stmtHist = $db_conn->prepare(
            "SELECT scl.*, p.productName
             FROM shop_invoice_change_log scl
             LEFT JOIN products p ON p.id = scl.pr_id
             WHERE scl.inv_id=?
             ORDER BY scl.created_at ASC"
        );
        $stmtHist->bind_param('s', $invId);
        $stmtHist->execute();
        $historyRows = $stmtHist->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtHist->close();
    }
}

$changeTypeLabels = [
    'initial'     => ['label' => 'Initial (from your order)', 'class' => 'badge bg-secondary'],
    'added'       => ['label' => 'Added',                      'class' => 'badge bg-success'],
    'removed'     => ['label' => 'Removed',                     'class' => 'badge bg-danger'],
    'qty_changed' => ['label' => 'Qty Changed',                 'class' => 'badge bg-warning text-dark'],
    'voided'      => ['label' => 'Invoice Voided',              'class' => 'badge bg-dark'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Invoice Status : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
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
                                <h1><table class="headertble"><tr><td>Order Invoice Status</td></tr></table></h1>
                            </div>
                        </div>
                    </div>

                    <?php if (!$owns): ?>
                        <div class="row">
                            <div class="col"><div class="alert alert-danger">Order not found.</div></div>
                        </div>
                    <?php else: ?>

                        <?php if ($invMeta): ?>
                        <div class="row mb-2">
                            <div class="col">
                                <div class="alert alert-info">
                                    Invoice #<?=htmlspecialchars($invMeta['inv_number'] ?: 'Pending')?>
                                    &mdash; Status: <b><?=htmlspecialchars(ucfirst($invMeta['status'] ?? 'draft'))?></b>
                                    &mdash; Total: <b>&#8377;<?=inr_format($invMeta['total'] ?? 0, 2)?></b>
                                    &mdash; Payment: <?=$paymentStatusHtml?>
                                </div>
                            </div>
                        </div>
                        <?php elseif (empty($invId)): ?>
                        <div class="row mb-2">
                            <div class="col"><div class="alert alert-warning">Not yet invoiced by the TP.</div></div>
                        </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5>What You Sent</h5>
                                        <table class="table table-bordered">
                                            <thead><tr><th>Product</th><th>Qty</th></tr></thead>
                                            <tbody>
                                            <?php if (empty($sentLines)): ?>
                                                <tr><td colspan="2" class="text-center">No items.</td></tr>
                                            <?php else: foreach ($sentLines as $ln): ?>
                                                <tr>
                                                    <td><?=htmlspecialchars($ln['productName'] ?? '-')?></td>
                                                    <td><?=(int)$ln['qty']?></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($invId)): ?>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5>Current Invoice</h5>
                                        <table class="table table-bordered">
                                            <thead><tr><th>Product</th><th>Qty</th></tr></thead>
                                            <tbody>
                                            <?php if (empty($invoiceLines)): ?>
                                                <tr><td colspan="2" class="text-center">No items.</td></tr>
                                            <?php else: foreach ($invoiceLines as $ln): ?>
                                                <tr>
                                                    <td><?=htmlspecialchars($ln['productName'] ?? '-')?></td>
                                                    <td><?=(int)$ln['qty']?></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($invId)): ?>
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body">
                                        <h5>Change History</h5>
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Date/Time</th>
                                                    <th>Change</th>
                                                    <th>Product</th>
                                                    <th>Qty Before</th>
                                                    <th>Qty After</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php if (empty($historyRows)): ?>
                                                <tr><td colspan="5" class="text-center">No history recorded.</td></tr>
                                            <?php else: foreach ($historyRows as $row):
                                                $ct = $changeTypeLabels[$row['change_type']] ?? ['label' => $row['change_type'], 'class' => 'badge bg-secondary'];
                                            ?>
                                                <tr>
                                                    <td><?=htmlspecialchars(date("d-m-Y h:i A", strtotime($row['created_at'])))?></td>
                                                    <td><span class="<?=$ct['class']?>"><?=htmlspecialchars($ct['label'])?></span></td>
                                                    <td><?=htmlspecialchars($row['productName'] ?? '-')?></td>
                                                    <td><?=$row['qty_before'] !== null ? (int)$row['qty_before'] : '-'?></td>
                                                    <td><?=$row['qty_after'] !== null ? (int)$row['qty_after'] : '-'?></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
