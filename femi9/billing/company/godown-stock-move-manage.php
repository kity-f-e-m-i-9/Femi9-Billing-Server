<?php include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/GodownStockMove.php");
include("config.php");

// Finance-only, same as the rest of the Internal Stock Transfer area —
// but this is a SEPARATE concept from Internal Transfer/Auto Transfer.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header("Location: dashboard.php");
    exit;
}

$moves = get_godown_stock_moves($db_conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Godown Stock Move History : <?php echo $business_name; ?></title>

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
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="page-description">
                        <h1>
                            <table class="headertble">
                            <tr>
                            <td>Godown Stock Move History</td>
                            <td><a href="godown-stock-move.php" title="Move Stock">&#10011;</a></td>
                            </tr>
                            </table>
                        </h1>
                        <p class="text-muted mb-0">Direct company-profile-to-company-profile stock moves — no invoice involved. Separate from Internal Stock Transfer / Auto Transfer.</p>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <div style="overflow-x:auto;">
                                    <table id="datatable1" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Product</th>
                                                <th>From</th>
                                                <th>To</th>
                                                <th>Qty</th>
                                                <th>Note</th>
                                                <th>By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($moves as $m): ?>
                                            <tr>
                                                <td><?php echo date('d M Y, h:i A', strtotime($m['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($m['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($m['from_gname'], ENT_QUOTES, 'UTF-8'); ?><?php echo $m['from_warehouse_code'] ? ' (' . htmlspecialchars($m['from_warehouse_code'], ENT_QUOTES, 'UTF-8') . ')' : ''; ?></td>
                                                <td><?php echo htmlspecialchars($m['to_gname'], ENT_QUOTES, 'UTF-8'); ?><?php echo $m['to_warehouse_code'] ? ' (' . htmlspecialchars($m['to_warehouse_code'], ENT_QUOTES, 'UTF-8') . ')' : ''; ?></td>
                                                <td><?php echo number_format($m['qty']); ?></td>
                                                <td><?php echo $m['note'] ? htmlspecialchars($m['note'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>'; ?></td>
                                                <td><?php echo htmlspecialchars($m['created_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($moves)): ?>
                                            <tr><td colspan="7" style="text-align:center;color:#898781;">No stock moves recorded yet.</td></tr>
                                        <?php endif; ?>
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
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/plugins/datatables/datatables.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script src="../../assets/js/pages/datatables.js"></script>
</body>
</html>
