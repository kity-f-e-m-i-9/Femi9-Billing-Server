<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

$id = (int) base64_decode($_REQUEST['id'] ?? '');

$stmt = $db_conn->prepare(
    "SELECT mp.id, mp.invoice_number, mp.purchase_date, mp.total_amount, mp.created_by, mp.created_at,
            v.vendor_name, v.address, v.gstin, v.phone, v.email
     FROM neksomo_manufacturer_purchases mp
     JOIN neksomo_vendors v ON v.id = mp.vendor_id
     WHERE mp.id = ?"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$purchase = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$purchase) {
    header("Location: neksomo-manufacturer-purchase-manage.php?error");
    exit;
}

$itemStmt = $db_conn->prepare(
    "SELECT npi.product_id, npi.quantity_pieces, npi.cost_per_piece, npi.total_cost, p.productName
     FROM neksomo_purchase_items npi
     JOIN products p ON p.id = npi.product_id
     WHERE npi.purchase_id = ?
     ORDER BY npi.id ASC"
);
$itemStmt->bind_param('i', $id);
$itemStmt->execute();
$items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase <?php echo htmlspecialchars($purchase['invoice_number']); ?> : <?php echo $business_name; ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        .detail-card { background:white; border:2px solid #e5e7eb; border-radius:14px; padding:25px 30px; margin-bottom:22px; }
        .detail-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f1f5f9; font-size:14px; }
        .detail-row:last-child { border-bottom:none; }
        .detail-label { color:#64748b; font-weight:500; }
        .detail-value { font-weight:600; color:#1e293b; }
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
                                        <td>Purchase <?php echo htmlspecialchars($purchase['invoice_number']); ?></td>
                                        <td><a href="neksomo-manufacturer-purchase-print.php?id=<?php echo urlencode($_GET['id']); ?>" title="Print" style="margin-right:10px;">&#128438;</a><a href="neksomo-manufacturer-purchase-manage" title="Manage Purchases">&#9776;</a></td>
                                        </tr>
                                        </table>
                                    </h1>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-5">
                                <div class="detail-card">
                                    <div class="detail-row"><span class="detail-label">Invoice Number</span><span class="detail-value"><?php echo htmlspecialchars($purchase['invoice_number']); ?></span></div>
                                    <div class="detail-row"><span class="detail-label">Purchase Date</span><span class="detail-value"><?php echo date('d M Y', strtotime($purchase['purchase_date'])); ?></span></div>
                                    <div class="detail-row"><span class="detail-label">Vendor</span><span class="detail-value"><?php echo htmlspecialchars($purchase['vendor_name']); ?></span></div>
                                    <?php if (!empty($purchase['address'])): ?>
                                    <div class="detail-row"><span class="detail-label">Address</span><span class="detail-value"><?php echo nl2br(htmlspecialchars($purchase['address'])); ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($purchase['gstin'])): ?>
                                    <div class="detail-row"><span class="detail-label">GSTIN</span><span class="detail-value"><?php echo htmlspecialchars($purchase['gstin']); ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($purchase['phone'])): ?>
                                    <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value"><?php echo htmlspecialchars($purchase['phone']); ?></span></div>
                                    <?php endif; ?>
                                    <div class="detail-row"><span class="detail-label">Total Amount</span><span class="detail-value">&#8377;<?php echo number_format((float)$purchase['total_amount'], 2); ?></span></div>
                                    <div class="detail-row"><span class="detail-label">Recorded By</span><span class="detail-value"><?php echo htmlspecialchars($purchase['created_by'] ?? ''); ?></span></div>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="card">
                                    <div class="card-body">
                                        <div style="overflow-x:scroll;">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Qty (Pieces)</th>
                                                    <th>Cost/Piece &#8377;</th>
                                                    <th>Line Total &#8377;</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($items as $it): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($it['productName']); ?></td>
                                                    <td><?php echo number_format((int)$it['quantity_pieces']); ?></td>
                                                    <td>&#8377;<?php echo number_format((float)$it['cost_per_piece'], 2); ?></td>
                                                    <td>&#8377;<?php echo number_format((float)$it['total_cost'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
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
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>

</html>
