<?php
include("checksession.php");
error_reporting(0);

$enc_id = $_GET['id'] ?? '';
$inv_id = (int)base64_decode($enc_id);
if (!$inv_id) { header("Location: manage-tp-invoices"); exit; }

// Fetch invoice with ownership check via TP
$stmt = $db_conn->prepare("
    SELECT tpi.*, tp.name AS tp_name, tp.tp_id AS tp_code, tp.mobile AS tp_mobile
    FROM tp_invoices tpi
    JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
    WHERE tpi.id = ? AND tp.onboard_ss_id = ?
");
$stmt->bind_param("is", $inv_id, $Login_user_IDvl);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$inv) { header("Location: manage-tp-invoices"); exit; }

// Line items
$stmt2 = $db_conn->prepare("
    SELECT tpii.*, p.productName, p.hsn
    FROM tp_invoice_items tpii
    JOIN products p ON p.id = tpii.product_id
    WHERE tpii.tp_invoice_id = ?
    ORDER BY tpii.id
");
$stmt2->bind_param("i", $inv_id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Invoice <?php echo htmlspecialchars($inv['invoice_number']); ?> : <?php echo $business_name; ?></title>
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
    .inv-meta td { padding:4px 12px 4px 0; font-size:13px; }
    .inv-meta td:first-child { color:#888; white-space:nowrap; }
    .inv-meta td:last-child { font-weight:500; }
    .inv-block { background:#f8f9fa; border-radius:6px; padding:14px 18px; }
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
                                <h1><table class="headertble"><tr>
                                    <td>TP Invoice</td>
                                    <td><a href="manage-tp-invoices" title="All TP Invoices">&#9776;</a></td>
                                </tr></table></h1>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body inv-block">
                                    <div style="font-size:11px;color:#aaa;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;">Invoice</div>
                                    <div style="font-size:22px;font-weight:700;color:#1a237e;"><?php echo htmlspecialchars($inv['invoice_number']); ?></div>
                                    <div style="font-size:13px;color:#555;margin-top:4px;"><?php echo htmlspecialchars($inv['invoice_date']); ?></div>
                                    <hr style="margin:10px 0;">
                                    <table class="inv-meta">
                                        <tr><td>Created by</td><td><?php echo htmlspecialchars($inv['created_by']); ?></td></tr>
                                        <tr><td>Created at</td><td><?php echo htmlspecialchars($inv['created_at']); ?></td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body inv-block">
                                    <div style="font-size:11px;color:#aaa;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;">Territory Partner</div>
                                    <div style="font-size:16px;font-weight:700;"><?php echo htmlspecialchars($inv['tp_name']); ?></div>
                                    <hr style="margin:10px 0;">
                                    <table class="inv-meta">
                                        <tr><td>TP ID</td><td><code><?php echo htmlspecialchars($inv['tp_code']); ?></code></td></tr>
                                        <tr><td>Mobile</td><td><?php echo htmlspecialchars($inv['tp_mobile']); ?></td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body inv-block">
                                    <div style="font-size:11px;color:#aaa;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;">Stock Source</div>
                                    <div style="font-size:16px;font-weight:700;">SS Stock</div>
                                    <div style="font-size:12px;color:#888;">Super Stockist Inventory</div>
                                    <hr style="margin:10px 0;">
                                    <table class="inv-meta">
                                        <tr><td>SS ID</td><td><code><?php echo htmlspecialchars($Login_user_IDvl); ?></code></td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <table class="table table-sm" style="font-size:13px;">
                                        <thead>
                                            <tr>
                                                <th>#</th><th>Product</th><th>HSN</th>
                                                <th class="text-right">Qty</th>
                                                <th class="text-right">Rate (₹)</th>
                                                <th class="text-right">Amount (₹)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($items as $idx => $item): ?>
                                            <tr>
                                                <td><?php echo $idx + 1; ?></td>
                                                <td><strong><?php echo htmlspecialchars($item['productName']); ?></strong></td>
                                                <td class="text-muted"><?php echo htmlspecialchars($item['hsn'] ?? ''); ?></td>
                                                <td class="text-right"><?php echo (int)$item['quantity']; ?></td>
                                                <td class="text-right">₹<?php echo number_format((float)$item['rate'], 2); ?></td>
                                                <td class="text-right"><strong>₹<?php echo number_format((float)$item['amount'], 2); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <?php
                                            $subtotal = array_sum(array_column($items, 'amount'));
                                            $courier  = (float)($inv['courier_charges'] ?? 0);
                                            $discount = (float)($inv['discount_amount'] ?? 0);
                                            ?>
                                            <?php if ($discount > 0): ?>
                                            <tr><td colspan="5" class="text-right" style="color:#64748b;">Subtotal</td><td class="text-right" style="color:#64748b;">₹<?php echo number_format($subtotal, 2); ?></td></tr>
                                            <tr><td colspan="5" class="text-right" style="color:#64748b;">Discount</td><td class="text-right" style="color:#10b981;">−₹<?php echo number_format($discount, 2); ?></td></tr>
                                            <?php endif; ?>
                                            <?php if ($courier > 0): ?>
                                            <tr>
                                                <td colspan="5" class="text-right" style="color:#64748b;"><i class="material-icons" style="font-size:14px;vertical-align:middle;">local_shipping</i> Courier Charges</td>
                                                <td class="text-right" style="color:#64748b;">₹<?php echo number_format($courier, 2); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr style="border-top:2px solid #e5e7eb;">
                                                <td colspan="5" class="text-right" style="font-weight:700;font-size:14px;">Grand Total</td>
                                                <td class="text-right" style="font-weight:700;font-size:16px;color:#1a237e;">₹<?php echo number_format((float)$inv['total_amount'], 2); ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <a href="manage-tp-invoices" class="btn btn-secondary"><i class="material-icons" style="font-size:16px;vertical-align:middle;">arrow_back</i> Back to List</a>
                            <a href="tp-invoice-print?id=<?php echo $enc_id; ?>" class="btn btn-outline-primary ms-2" target="_blank"><i class="material-icons" style="font-size:16px;vertical-align:middle;">print</i> Print</a>
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
