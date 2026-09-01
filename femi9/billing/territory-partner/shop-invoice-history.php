<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$invid_enc = $_REQUEST['invid'] ?? '';
$inv_id    = base64_decode($invid_enc);
$tp_id     = (int)$Login_user_IDvl;
$tp_id_str = (string)$tp_id;

$invRows = [];
if ($inv_id !== '') {
    // Only show history for an invoice that actually belongs to this TP.
    $stmtChk = $db_conn->prepare("SELECT COUNT(*) AS n FROM user_invoice WHERE inv_id=? AND from_user_type=? AND from_user_id=?");
    $stmtChk->bind_param('sss', $inv_id, $Login_user_TYPEvl, $tp_id_str);
    $stmtChk->execute();
    $owns = (int)($stmtChk->get_result()->fetch_assoc()['n'] ?? 0) > 0;
    $stmtChk->close();

    if ($owns) {
        $stmt = $db_conn->prepare(
            "SELECT scl.*, p.productName
             FROM shop_invoice_change_log scl
             LEFT JOIN products p ON p.id = scl.pr_id
             WHERE scl.inv_id = ?
             ORDER BY scl.created_at ASC"
        );
        $stmt->bind_param('s', $inv_id);
        $stmt->execute();
        $invRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$changeTypeLabels = [
    'initial'     => ['label' => 'Initial (from field order)', 'class' => 'badge bg-secondary'],
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
    <title>Invoice History : <?php echo $business_name; ?></title>
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
                                <h1>
                                    <table class="headertble" style="width:100%">
                                        <tr>
                                            <td><a href="shop-manage-invoice.php" title="Back to Manage Invoice" style="color:inherit;text-decoration:none;"><i class="material-icons" style="vertical-align:middle;">arrow_back</i></a> Invoice History</td>
                                        </tr>
                                    </table>
                                </h1>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                    <table class="table table-bordered" style="min-width:640px;">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>Change</th>
                                                <th>Product</th>
                                                <th>Qty Before</th>
                                                <th>Qty After</th>
                                                <th>Changed By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($invRows)): ?>
                                            <tr><td colspan="6" class="text-center">No history recorded for this invoice.</td></tr>
                                        <?php else: foreach ($invRows as $row):
                                            $ct = $changeTypeLabels[$row['change_type']] ?? ['label' => $row['change_type'], 'class' => 'badge bg-secondary'];
                                        ?>
                                            <tr>
                                                <td><?=htmlspecialchars(date("d-m-Y h:i A", strtotime($row['created_at'])))?></td>
                                                <td><span class="<?=$ct['class']?>"><?=htmlspecialchars($ct['label'])?></span></td>
                                                <td><?=htmlspecialchars($row['productName'] ?? '-')?></td>
                                                <td><?=$row['qty_before'] !== null ? (int)$row['qty_before'] : '-'?></td>
                                                <td><?=$row['qty_after'] !== null ? (int)$row['qty_after'] : '-'?></td>
                                                <td><?=htmlspecialchars($row['changed_by_user_type'])?> #<?=htmlspecialchars($row['changed_by_user_id'])?></td>
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
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
