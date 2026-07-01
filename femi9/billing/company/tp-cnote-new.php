<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: manage-tp-invoices"); exit;
}

$inv_id    = (int)($_REQUEST['inv_id'] ?? 0);
$returnid  = trim(base64_decode($_REQUEST['returnid'] ?? ''));
$created_by = $_SESSION['LOGIN_USER'] ?? 'system';

// Resolve: if returnid given, get inv from return master
if ($returnid) {
    $s = $db_conn->prepare("SELECT urs.*, tpi.id AS tpi_id, tpi.territory_partner_id, tpi.source_godown_id, tpi.source_cp_id, tp.name AS tp_name, tp.tp_id AS tp_code FROM user_return_stock urs JOIN tp_invoices tpi ON tpi.invoice_number = urs.invnumber COLLATE utf8mb4_unicode_ci JOIN territory_partners tp ON tp.id = tpi.territory_partner_id WHERE urs.returnid=? AND urs.from_usertype='territory_partner' AND urs.to_usertype='company' LIMIT 1");
    $s->bind_param('s', $returnid);
    $s->execute();
    $returnMaster = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$returnMaster) { header("Location: tp-cnote-manage"); exit; }
    $inv_id = (int)$returnMaster['tpi_id'];
}

if (!$inv_id) { header("Location: manage-tp-invoices"); exit; }

// Load TP invoice
$s = $db_conn->prepare("SELECT tpi.*, tp.name AS tp_name, tp.tp_id AS tp_code, tp.id AS tp_db_id FROM tp_invoices tpi JOIN territory_partners tp ON tp.id = tpi.territory_partner_id WHERE tpi.id=? LIMIT 1");
$s->bind_param('i', $inv_id);
$s->execute();
$invoice = $s->get_result()->fetch_assoc();
$s->close();
if (!$invoice) { header("Location: manage-tp-invoices"); exit; }

$inv_number = $invoice['invoice_number'];
$tp_db_id   = (int)$invoice['tp_db_id'];

// Load invoice items with product name
$s = $db_conn->prepare("SELECT tpii.*, p.productName FROM tp_invoice_items tpii JOIN products p ON p.id = tpii.product_id WHERE tpii.tp_invoice_id=?");
$s->bind_param('i', $inv_id);
$s->execute();
$inv_items = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();

// Already-returned qty per product (all finalized CNs)
$returnedQty = [];
$s = $db_conn->prepare("SELECT ursi.prid, SUM(ursi.qty) AS rqty FROM user_return_stock_items ursi JOIN user_return_stock urs ON urs.returnid=ursi.returnid WHERE urs.invnumber=? AND urs.from_usertype='territory_partner' AND urs.status='accept' GROUP BY ursi.prid");
$s->bind_param('s', $inv_number);
$s->execute();
foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $returnedQty[(int)$r['prid']] = (int)$r['rqty'];
}
$s->close();

// Load CN items for both draft (editing) and accepted (read-only) views
$draftItems = [];
if ($returnid) {
    $s = $db_conn->prepare("SELECT * FROM user_return_stock_items WHERE returnid=?");
    $s->bind_param('s', $returnid);
    $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $draftItems[(int)$r['prid']] = $r;
    }
    $s->close();
}

// If returnid is accepted, show read-only view
$isAccepted = $returnid && ($returnMaster['status'] ?? '') === 'accept';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Credit Note : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
    <style>
        .inv-info { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px 20px; margin-bottom:20px; }
        .inv-info table td { padding:4px 12px 4px 0; font-size:13px; }
        .inv-info table td:first-child { font-weight:600; color:#64748b; white-space:nowrap; }
        .draft-table th { background:#667eea; color:#fff; font-size:12.5px; font-weight:600; }
        .draft-table td { font-size:13px; vertical-align:middle; }
        .max-badge { background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-accept { background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
        .badge-pending { background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
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
                    <div class="row"><div class="col">
                        <div class="page-description">
                            <h1><table class="headertble"><tr>
                                <td>TP Credit Note</td>
                                <td><a href="tp-cnote-manage" title="Manage CNs">&#9776;</a>&nbsp;<a href="manage-tp-invoices" title="TP Invoices">&#x21A9;</a></td>
                            </tr></table></h1>
                        </div>
                    </div></div>

                    <?php if (!empty($_SESSION['successMessage'])): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['successMessage']); unset($_SESSION['successMessage']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['errorMessage'])): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['errorMessage']); unset($_SESSION['errorMessage']); ?></div>
                    <?php endif; ?>

                    <!-- Invoice Info -->
                    <div class="inv-info">
                        <table><tbody>
                            <tr><td>Invoice</td><td><?php echo htmlspecialchars($inv_number); ?></td>
                                <td style="padding-left:30px;">TP</td><td><?php echo htmlspecialchars($invoice['tp_code'] . ' — ' . $invoice['tp_name']); ?></td></tr>
                            <tr><td>Date</td><td><?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?></td>
                                <td style="padding-left:30px;">Invoice Total</td><td>&#8377;<?php echo number_format($invoice['total_amount'], 2); ?></td></tr>
                        </tbody></table>
                        <?php if ($returnid): ?>
                        <div style="margin-top:8px;">
                            <b>CN Ref:</b> <?php echo htmlspecialchars($returnid); ?>
                            &nbsp;
                            <?php if ($isAccepted): ?>
                                <span class="badge-accept">Accepted</span>
                            <?php else: ?>
                                <span class="badge-pending">Draft</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!$isAccepted): ?>
                    <!-- Add Return Items Form -->
                    <div class="card">
                        <div class="card-header"><b>Select Return Quantities</b></div>
                        <div class="card-body">
                            <form method="POST" action="tp-cnote-action">
                                <input type="hidden" name="inv_id" value="<?php echo $inv_id; ?>">
                                <input type="hidden" name="returnid" value="<?php echo htmlspecialchars($returnid); ?>">
                                <table class="table table-bordered draft-table">
                                    <thead><tr>
                                        <th>#</th><th>Product</th><th>Invoiced Qty</th><th>Already Returned</th><th>Available</th><th>Rate</th><th>Return Qty</th>
                                    </tr></thead>
                                    <tbody>
                                    <?php $n=0; foreach ($inv_items as $item):
                                        $pid = (int)$item['product_id'];
                                        $orig_qty = (int)$item['quantity'];
                                        $ret_qty  = $returnedQty[$pid] ?? 0;
                                        // Also subtract in-draft qty for this CN
                                        $draft_qty = isset($draftItems[$pid]) ? (int)$draftItems[$pid]['qty'] : 0;
                                        $available = max(0, $orig_qty - $ret_qty);
                                        if ($available <= 0) continue;
                                    ?>
                                    <tr>
                                        <td><?php echo ++$n; ?></td>
                                        <td><?php echo htmlspecialchars($item['productName']); ?></td>
                                        <td><?php echo $orig_qty; ?></td>
                                        <td><?php echo $ret_qty; ?></td>
                                        <td><span class="max-badge"><?php echo $available; ?></span></td>
                                        <td>&#8377;<?php echo number_format($item['rate'], 2); ?></td>
                                        <td>
                                            <input type="hidden" name="prid[]" value="<?php echo $pid; ?>">
                                            <input type="hidden" name="rate[]" value="<?php echo $item['rate']; ?>">
                                            <input type="number" name="qty[]" min="0" max="<?php echo $available; ?>"
                                                   value="<?php echo $draft_qty; ?>"
                                                   class="form-control form-control-sm" style="width:90px;">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if ($n === 0): ?>
                                    <tr><td colspan="7" class="text-center text-muted">No returnable items — all quantities already returned.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                                <?php if ($n > 0): ?>
                                <button type="submit" class="btn btn-primary">Save Draft</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <?php if ($returnid && !empty($draftItems)): ?>
                    <!-- Saved Draft Items -->
                    <div class="card">
                        <div class="card-header">
                            <b>Draft CN Items</b>
                            <?php
                            $draft_total = array_sum(array_column($draftItems, 'total'));
                            ?>
                            <span style="margin-left:auto;font-weight:700;">Total: &#8377;<?php echo number_format($draft_total, 2); ?></span>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-bordered draft-table mb-0">
                                <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Rate</th><th>Amount</th><th></th></tr></thead>
                                <tbody>
                                <?php $n=0; foreach ($draftItems as $di):
                                    $pname = '';
                                    foreach ($inv_items as $ii) { if ((int)$ii['product_id'] === (int)$di['prid']) { $pname = $ii['productName']; break; } }
                                ?>
                                <tr>
                                    <td><?php echo ++$n; ?></td>
                                    <td><?php echo htmlspecialchars($pname); ?></td>
                                    <td><?php echo $di['qty']; ?></td>
                                    <td>&#8377;<?php echo number_format($di['amount'], 2); ?></td>
                                    <td>&#8377;<?php echo number_format($di['total'], 2); ?></td>
                                    <td><a href="tp-cnote-item-del?itemid=<?php echo base64_encode($di['id']); ?>&returnid=<?php echo base64_encode($returnid); ?>&inv_id=<?php echo $inv_id; ?>" style="color:red;" onclick="return confirm('Remove item?')">&#10005;</a></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer" style="display:flex;gap:12px;align-items:center;">
                            <a href="tp-cnote-finish?returnid=<?php echo base64_encode($returnid); ?>" class="btn btn-success" onclick="return confirm('Finalise this Credit Note? Stock will be updated and a receipt credit will be posted.')">
                                &#10003; Finalise Credit Note
                            </a>
                            <a href="tp-cnote-del?returnid=<?php echo base64_encode($returnid); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this draft CN?')">
                                Delete Draft
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php else: ?>
                    <!-- Accepted CN — items with individual remove + delete header when empty -->
                    <div class="card">
                        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                            <b>Accepted Credit Note Items</b>
                            <?php if (empty($draftItems)): ?>
                            <a href="tp-cnote-del?returnid=<?php echo base64_encode($returnid); ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Delete this credit note permanently?')">
                                <i class="material-icons-outlined" style="font-size:16px;vertical-align:middle;">delete_outline</i> Delete Credit Note
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                        <?php if (empty($draftItems)): ?>
                            <p style="padding:24px;text-align:center;color:#6b7280;font-size:13px;">All items have been removed. Click <b>Delete Credit Note</b> to remove this record.</p>
                        <?php else: ?>
                            <table class="table table-bordered draft-table mb-0">
                                <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Rate</th><th>Amount</th><th style="width:40px;"></th></tr></thead>
                                <tbody>
                                <?php $n=0; $cn_total=0; foreach ($draftItems as $di):
                                    $pname = '';
                                    foreach ($inv_items as $ii) { if ((int)$ii['product_id'] === (int)$di['prid']) { $pname = $ii['productName']; break; } }
                                    $cn_total += (float)$di['total'];
                                    $enc_item = base64_encode($di['id']);
                                ?>
                                <tr>
                                    <td><?php echo ++$n; ?></td>
                                    <td><?php echo htmlspecialchars($pname); ?></td>
                                    <td><?php echo $di['qty']; ?></td>
                                    <td>&#8377;<?php echo number_format($di['amount'], 2); ?></td>
                                    <td>&#8377;<?php echo number_format($di['total'], 2); ?></td>
                                    <td>
                                        <a href="tp-cnote-item-del?itemid=<?php echo $enc_item; ?>&returnid=<?php echo base64_encode($returnid); ?>&inv_id=<?php echo $inv_id; ?>"
                                           style="color:#dc2626;"
                                           title="Remove item (reverses stock)"
                                           onclick="return confirm('Remove this item? The stock change will be reversed.')">
                                            <i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">remove_circle_outline</i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot><tr>
                                    <td colspan="5" style="text-align:right;font-weight:700;">CN Total</td>
                                    <td style="font-weight:700;">&#8377;<?php echo number_format($cn_total, 2); ?></td>
                                </tr></tfoot>
                            </table>
                        <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

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
