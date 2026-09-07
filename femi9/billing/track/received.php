<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$title = "Received";
$today = date("Y-m-d");

$statusFilter = in_array($_GET['status'] ?? '', ['pending_review', 'accepted', 'rejected'], true) ? $_GET['status'] : 'pending_review';

// Today's purchase orders whose courier payment is in the selected status —
// same underlying tp_courier_payments table the company's own Courier
// Payments review page uses, so an Accept/Reject here is the SAME action,
// visible on both sides immediately (not a separate parallel approval).
$stmt = $db_conn->prepare("
    SELECT c.id AS courier_payment_id, c.total_boxes, c.total_covers, c.required_amount, c.detected_amount,
           c.file_path, c.status, c.rejection_reason, c.created_at,
           po.id AS po_id, po.product_type,
           tp.id AS tp_db_id, tp.tp_id AS tp_code, tp.name AS tp_name
    FROM tp_courier_payments c
    JOIN tp_purchase_orders po ON po.id = c.po_id
    JOIN territory_partners tp ON tp.id = po.territory_partner_id
    WHERE po.order_date = ? AND c.status = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param('ss', $today, $statusFilter);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Product names per PO — shown alongside the courier amount so the reviewer
// knows what the order actually contains, not just its fee.
$poIds = array_values(array_unique(array_column($rows, 'po_id')));
$productsByPo = [];
if (!empty($poIds)) {
    $placeholders = implode(',', array_fill(0, count($poIds), '?'));
    $types = str_repeat('i', count($poIds));
    $pStmt = $db_conn->prepare("
        SELECT i.po_id, p.productName, i.qty
        FROM tp_purchase_order_items i
        JOIN products p ON p.id = i.product_id
        WHERE i.po_id IN ($placeholders)
        ORDER BY i.id
    ");
    $pStmt->bind_param($types, ...$poIds);
    $pStmt->execute();
    foreach ($pStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $pr) {
        $productsByPo[$pr['po_id']][] = $pr['productName'] . ' (' . $pr['qty'] . ')';
    }
    $pStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $title;?> : <?php echo $business_name;?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .trk-filter a { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 12.5px; font-weight: 600; text-decoration: none; margin-right: 8px; color: #6b7280; background: #f3f4f6; }
        .trk-filter a.active { background: #667eea; color: #fff; }
        .trk-card { background: #fff; border: 1px solid #eef0f2; border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; display: flex; gap: 14px; align-items: flex-start; }
        .trk-card img { width: 64px; height: 64px; border-radius: 8px; object-fit: cover; border: 1px solid #e5e7eb; flex-shrink: 0; }
        .trk-card .info { flex: 1; min-width: 0; font-size: 13px; color: #374151; }
        .trk-card .info b { color: #1f2937; }
        .trk-badge { font-size: 10.5px; font-weight: 700; padding: 2px 9px; border-radius: 20px; text-transform: uppercase; }
        .trk-badge.napkin { background: #dcfce7; color: #15803d; }
        .trk-badge.diaper { background: #ede9fe; color: #6d28d9; }
        .trk-actions button { border: none; font-weight: 700; font-size: 12.5px; padding: 6px 16px; border-radius: 8px; cursor: pointer; margin-left: 6px; }
        .trk-approve { background: #d1fae5; color: #065f46; }
        .trk-reject { background: #fee2e2; color: #991b1b; }
    </style>
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
                        <h1 style="font-size:20px;margin-bottom:4px;"><?php echo $title;?></h1>
                        <p style="color:#6b7280;font-size:13px;margin-bottom:14px;">Purchase orders placed today — courier payment review.</p>

                        <div class="trk-filter mb-3">
                            <a href="?status=pending_review" class="<?=$statusFilter==='pending_review'?'active':''?>">Pending Review</a>
                            <a href="?status=accepted" class="<?=$statusFilter==='accepted'?'active':''?>">Accepted</a>
                            <a href="?status=rejected" class="<?=$statusFilter==='rejected'?'active':''?>">Rejected</a>
                        </div>

                        <?php if (empty($rows)): ?>
                        <div class="text-muted" style="font-size:13.5px;">No orders in this status today.</div>
                        <?php else: foreach ($rows as $r): ?>
                        <div class="trk-card" data-id="<?=$r['courier_payment_id']?>">
                            <a href="../territory-partner/courier_payment_screenshots/<?=htmlspecialchars($r['file_path'])?>" target="_blank">
                                <img src="../territory-partner/courier_payment_screenshots/<?=htmlspecialchars($r['file_path'])?>" alt="">
                            </a>
                            <div class="info">
                                <b><?=htmlspecialchars($r['tp_code'])?></b> — <?=htmlspecialchars($r['tp_name'])?>
                                <span class="trk-badge <?=$r['product_type']?>"><?=htmlspecialchars(tpProductTypeLabel($r['product_type']))?></span>
                                <br>
                                <?=htmlspecialchars(implode(', ', $productsByPo[$r['po_id']] ?? []))?>
                                <br>
                                <?=(int)$r['total_boxes']?> box<?=(int)$r['total_boxes']!==1?'es':''?><?php if ($r['total_covers'] > 0): ?> + <?=(int)$r['total_covers']?> cover<?=(int)$r['total_covers']!==1?'s':''?><?php endif; ?>
                                — Courier Amount: <b>₹<?=number_format($r['required_amount'],2)?></b>
                                <?php if ($r['detected_amount'] !== null): ?> · Detected: ₹<?=number_format($r['detected_amount'],2)?><?php endif; ?>
                                <br>
                                <span class="text-muted" style="font-size:11.5px;"><?=date('d-M-Y h:i A', strtotime($r['created_at']))?></span>
                                <?php if ($r['status'] === 'rejected' && $r['rejection_reason']): ?><br><span style="color:#991b1b;font-size:11.5px;"><?=htmlspecialchars($r['rejection_reason'])?></span><?php endif; ?>
                            </div>
                            <?php if ($r['status'] === 'pending_review'): ?>
                            <div class="trk-actions">
                                <button type="button" class="trk-approve" onclick="trackApprove(<?=$r['courier_payment_id']?>, <?=$r['detected_amount'] !== null ? $r['detected_amount'] : $r['required_amount']?>)">Accept</button>
                                <button type="button" class="trk-reject" onclick="trackReject(<?=$r['courier_payment_id']?>)">Reject</button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; endif; ?>

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
    function trackApprove(id, suggestedAmount) {
        var amt = prompt('Confirm the amount paid in this screenshot:', suggestedAmount);
        if (amt === null) return;
        $.post('received-action-ajax.php', { courier_payment_id: id, action: 'approve', confirmed_amount: amt })
            .done(function (data) {
                if (data.success) window.location.reload();
                else alert(data.message || 'Action failed.');
            })
            .fail(function () { alert('Could not reach the server.'); });
    }
    function trackReject(id) {
        var reason = prompt('Reason for rejecting this courier payment:', '');
        if (reason === null) return;
        $.post('received-action-ajax.php', { courier_payment_id: id, action: 'reject', reason: reason })
            .done(function (data) {
                if (data.success) window.location.reload();
                else alert(data.message || 'Action failed.');
            })
            .fail(function () { alert('Could not reach the server.'); });
    }
    </script>
</body>
</html>
