<?php
// View/edit one DM order assigned to this CP — see dm-orders.php's own
// comment for the full picture. Scope-checked to assigned_cp_id = this CP's
// own id; editing is blocked once Company has invoiced it
// (msOrderIsInvoiced()).
include("checksession.php");
include("config.php");
require_once(__DIR__ . '/../shared/MsOrderEdits.php');
error_reporting(0);

$cpId = (int)$Login_user_IDvl;
msOrderEditsEnsureSchema($db_conn);

$order_id = $_GET['order_id'] ?? '';
if ($order_id === '') { header('Location: dm-orders.php'); exit; }

$stmt = $db_conn->prepare(
    "SELECT id, shop_id, ms_id, order_date, pr_id, qty FROM ms_orders
     WHERE order_id = ? AND assigned_cp_id = ? AND new_order = 'yes' AND tp_id IS NULL
     ORDER BY id ASC"
);
$stmt->bind_param('si', $order_id, $cpId);
$stmt->execute();
$lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($lines)) {
    $_SESSION['errorMessage'] = "This order is not assigned to you.";
    header('Location: dm-orders.php'); exit;
}

$shop_id = (int)$lines[0]['shop_id'];
$ms_id = (int)$lines[0]['ms_id'];

$shopInfo = $db_conn->query("SELECT name, mobile_number, address, district_name, taluk_name FROM ms_shop WHERE id=" . (int)$shop_id)->fetch_assoc() ?: [];
$dmName = $db_conn->query("SELECT ms_name FROM marketing_staff WHERE id=" . (int)$ms_id)->fetch_assoc()['ms_name'] ?? '-';

$prodIds = array_column($lines, 'pr_id');
$prodMap = [];
if (!empty($prodIds)) {
    $idList = implode(',', array_map('intval', $prodIds));
    $r = $db_conn->query("SELECT id, productName FROM products WHERE id IN ($idList)");
    while ($p = $r->fetch_assoc()) { $prodMap[$p['id']] = $p['productName']; }
}

$isInvoiced = msOrderIsInvoiced($db_conn, $order_id);
$editLog = msOrderFetchEditLog($db_conn, $order_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DM Order : <?php echo $business_name; ?></title>
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
        body { font-family: 'Poppins', sans-serif; }
        .card { border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.07); border:none; margin-bottom:20px; }
        .qty-input { width:90px; display:inline-block; }
        .log-row { font-size:12.5px; color:#6b7280; padding:8px 0; border-bottom:1px solid #f6f6f6; }
        .log-row:last-child { border-bottom:none; }
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
                                <h1>DM Order<?php echo $isInvoiced ? ' (Invoiced)' : ''; ?></h1>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <p><b>Shop:</b> <?php echo htmlspecialchars($shopInfo['name'] ?? '-'); ?> (<?php echo htmlspecialchars($shopInfo['mobile_number'] ?? ''); ?>)</p>
                                    <p><b>Location:</b> <?php echo htmlspecialchars(trim(($shopInfo['taluk_name'] ?? '') . ', ' . ($shopInfo['district_name'] ?? ''), ', ')); ?></p>
                                    <p><b>DM:</b> <?php echo htmlspecialchars($dmName); ?></p>
                                    <p><b>Date:</b> <?php echo date('d M Y', strtotime($lines[0]['order_date'])); ?></p>

                                    <?php if ($isInvoiced): ?>
                                    <div class="alert alert-info">This order has already been invoiced by Company — no further edits possible.</div>
                                    <?php endif; ?>

                                    <div class="table-responsive">
                                    <table class="table table-bordered" id="prodTable">
                                        <thead><tr><th>Product</th><th>Qty</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($lines as $ln): $pid = (int)$ln['pr_id']; if ($pid <= 0) continue; ?>
                                        <tr data-pr-id="<?php echo $pid; ?>">
                                            <td><?php echo htmlspecialchars($prodMap[$pid] ?? '-'); ?></td>
                                            <td>
                                                <?php if ($isInvoiced): ?>
                                                <?php echo (int)$ln['qty']; ?>
                                                <?php else: ?>
                                                <input type="number" min="0" class="form-control qty-input" value="<?php echo (int)$ln['qty']; ?>">
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>

                                    <?php if (!$isInvoiced): ?>
                                    <label class="form-label">Reason for edit (required if you change any qty)</label>
                                    <textarea id="editReason" class="form-control" rows="2" placeholder="e.g. Only 5 in stock, giving 5 instead of 8"></textarea>
                                    <br/>
                                    <button type="button" id="saveEditBtn" class="btn btn-primary">
                                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">save</i> Save
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <b>Change History</b>
                                    <div style="margin-top:10px;">
                                        <?php if (empty($editLog)): ?>
                                        <div class="text-muted" style="font-size:12.5px;">No edits yet.</div>
                                        <?php else: foreach ($editLog as $e): ?>
                                        <div class="log-row">
                                            <b><?php echo htmlspecialchars($e['productName'] ?? ('#' . $e['pr_id'])); ?></b>:
                                            <?php echo (int)$e['old_qty']; ?> &rarr; <?php echo (int)$e['new_qty']; ?>
                                            <?php if ($e['old_price'] !== null || $e['new_price'] !== null): ?>
                                            , price <?php echo $e['old_price'] !== null ? number_format((float)$e['old_price'], 2) : '-'; ?> &rarr; <?php echo $e['new_price'] !== null ? number_format((float)$e['new_price'], 2) : '-'; ?>
                                            <?php endif; ?>
                                            <br/>by <?php echo htmlspecialchars($e['edited_by_name'] ?? ucfirst($e['edited_by_type'])); ?> (<?php echo htmlspecialchars(ucfirst($e['edited_by_type'])); ?>) on <?php echo date('d M Y, h:i A', strtotime($e['edited_at'])); ?>
                                            <br/><i>"<?php echo htmlspecialchars($e['reason']); ?>"</i>
                                        </div>
                                        <?php endforeach; endif; ?>
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
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
(function ($) {
    $('#saveEditBtn').on('click', function () {
        var qty = {};
        $('#prodTable tbody tr').each(function () {
            var prId = $(this).data('pr-id');
            var val = $(this).find('.qty-input').val();
            qty[prId] = val;
        });
        var reason = $.trim($('#editReason').val());
        if (!reason) { alert('Please enter a reason.'); return; }
        $('#saveEditBtn').prop('disabled', true);
        $.post('dm-order-edit-action.php', { order_id: <?php echo json_encode($order_id); ?>, qty: qty, reason: reason }, function (resp) {
            if (resp && resp.success) {
                window.location.href = 'dm-orders.php';
            } else {
                alert((resp && resp.error) || 'Failed to save.');
                $('#saveEditBtn').prop('disabled', false);
            }
        }, 'json').fail(function () {
            alert('Failed to save. Please try again.');
            $('#saveEditBtn').prop('disabled', false);
        });
    });
})(jQuery);
</script>
</body>
</html>
