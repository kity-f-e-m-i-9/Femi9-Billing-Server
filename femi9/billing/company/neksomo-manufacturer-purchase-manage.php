<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

$entries = $db_conn->query(
    "SELECT mp.id, mp.manufacturer_name, mp.purchase_date, mp.quantity_packs, mp.cost_per_piece, mp.total_cost, p.productName, p.pieces_per_pack
     FROM neksomo_manufacturer_purchases mp
     JOIN products p ON p.id = mp.product_id
     ORDER BY mp.purchase_date DESC, mp.id DESC"
)->fetch_all(MYSQLI_ASSOC);

$grand_total_packs = 0;
$grand_total_cost = 0.0;
foreach ($entries as $e) {
    $grand_total_packs += (int)$e['quantity_packs'];
    $grand_total_cost += (float)$e['total_cost'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Manufacturer Purchases : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/datatables/datatables.min.css" rel="stylesheet">

    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
    <style>
        .action-link { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;transition:all .15s;text-decoration:none;padding:0; }
        .action-link:hover { background:#f3f4f6;border-color:#d1d5db; }
        .action-link.delete:hover { background:#fef2f2;border-color:#fecaca; }
        .actions-group { display:inline-flex;align-items:center;gap:5px;white-space:nowrap; }
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
                                    <?php if (isset($_REQUEST['deletedDone'])) { ?><div class="alert alert-warning">Entry deleted and stock reversed.</div><?php } ?>
                                    <?php if (isset($_REQUEST['error'])) { ?><div class="alert alert-danger">Something went wrong. Please try again.</div><?php } ?>

                                    <h1>
                                        <table class="headertble">
                                        <tr>
                                        <td>Purchases from Manufacturer</td>
                                        <td><a href="neksomo-manufacturer-purchase.php" title="Add Purchase">&#10011;</a></td>
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
                                        <p class="text-muted" style="font-size:13px;">Each entry credited stock directly to Neksomo's on-hand balance when added. Deleting an entry reverses that stock credit.</p>
                                        <div style="overflow-x:scroll;">
                                        <table id="datatable1" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Product</th>
                                                    <th>Manufacturer</th>
                                                    <th>Qty (Packs)</th>
                                                    <th>Cost/Piece &#8377;</th>
                                                    <th>Total Cost &#8377;</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($entries as $e): $eid = base64_encode((string)$e['id']); ?>
                                                <tr>
                                                    <td><?php echo date('d M Y', strtotime($e['purchase_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($e['productName']); ?></td>
                                                    <td><?php echo htmlspecialchars($e['manufacturer_name']); ?></td>
                                                    <td><?php echo number_format((int)$e['quantity_packs']); ?></td>
                                                    <td>&#8377;<?php echo number_format((float)$e['cost_per_piece'], 2); ?></td>
                                                    <td>&#8377;<?php echo number_format((float)$e['total_cost'], 2); ?></td>
                                                    <td>
                                                        <div class="actions-group">
                                                            <a href="delete-neksomo-manufacturer-purchase.php?id=<?php echo $eid; ?>" class="action-link delete" title="Delete (reverses stock)" onclick="return confirm('Delete this purchase and reverse the stock it added?');"><i class="material-icons-outlined" style="font-size:17px;color:#ef4444;">delete_outline</i></a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($entries)): ?>
                                                <tr><td colspan="7" style="text-align:center;color:#898781;">No purchases recorded yet.</td></tr>
                                            <?php endif; ?>
                                            </tbody>
                                            <?php if (!empty($entries)): ?>
                                            <tfoot>
                                                <tr>
                                                    <th colspan="3">Grand Total</th>
                                                    <th><?php echo number_format($grand_total_packs); ?></th>
                                                    <th></th>
                                                    <th>&#8377;<?php echo number_format($grand_total_cost, 2); ?></th>
                                                    <th></th>
                                                </tr>
                                            </tfoot>
                                            <?php endif; ?>
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
