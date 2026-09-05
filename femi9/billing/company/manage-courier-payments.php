<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
include("config.php");
require_once __DIR__ . '/../shared/TpCourierPayment.php';
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

// Standalone review queue — same idea as manage-tp-advance-submissions.php,
// but for tp_courier_payments. Necessary because tp-today-orders.php's
// Courier Payment column only shows rows already linked to a submitted PO
// (po_id set) — a screenshot that came back "pending_review" happens
// BEFORE the PO exists (it's the gate blocking submission), so without this
// page a company reviewer would have no way to ever approve it and the TP
// could never submit that order at all. Confirmed as a real deadlock 2026-09-04.
$title = "Courier Payments";
tpEnsureCourierPaymentTables($db_conn);
tpEnsureCourierOverrideColumn($db_conn);

$successMessage = '';
$errorMessage = '';

// Manual correction for a specific PO's courier amount — for when the auto
// box/cover calculation got it wrong. Overrides tpCourierComputeAmount()'s
// own math for every future retry-payment calc on that order (see
// pay-courier-payment.php / upload-courier-payment-screenshot.php's po_id
// branches) until cleared. Only meaningful for an already-submitted order
// (po_id set) — a pre-submission cart has no PO row to attach this to yet.
if (isset($_POST['set_override'])) {
    $overridePoId = (int)($_POST['po_id'] ?? 0);
    $overrideRaw = trim($_POST['override_amount'] ?? '');
    if ($overridePoId > 0) {
        if ($overrideRaw === '') {
            $upd = $db_conn->prepare("UPDATE tp_purchase_orders SET courier_amount_override = NULL WHERE id = ?");
            $upd->bind_param('i', $overridePoId);
        } else {
            $overrideAmount = round((float)$overrideRaw, 2);
            $upd = $db_conn->prepare("UPDATE tp_purchase_orders SET courier_amount_override = ? WHERE id = ?");
            $upd->bind_param('di', $overrideAmount, $overridePoId);
        }
        $upd->execute();
        $upd->close();
        $successMessage = 'Courier amount updated for this order.';
    }
}

$statusFilter = in_array($_GET['status'] ?? '', ['pending_review', 'accepted', 'rejected'], true) ? $_GET['status'] : 'pending_review';

$stmt = $db_conn->prepare("
    SELECT c.id, c.territory_partner_id, c.product_type, c.total_boxes, c.total_covers, c.required_amount,
           c.detected_amount, c.reference_number, c.file_path, c.status, c.rejection_reason, c.po_id, c.created_at,
           tp.name AS tp_name, tp.tp_id AS tp_code, po.courier_amount_override
    FROM tp_courier_payments c
    JOIN territory_partners tp ON tp.id = c.territory_partner_id
    LEFT JOIN tp_purchase_orders po ON po.id = c.po_id
    WHERE c.status = ?
    ORDER BY c.created_at DESC
    LIMIT 200
");
$stmt->bind_param('s', $statusFilter);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
        .mcp-wrap { max-width: 900px; margin: 24px auto; padding: 0 14px; }
        .mcp-filter a { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 12.5px; font-weight: 600; text-decoration: none; margin-right: 8px; color: #6b7280; background: #f3f4f6; }
        .mcp-filter a.active { background: #667eea; color: #fff; }
        .mcp-card { background: #fff; border: 1px solid #eef0f2; border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; display: flex; gap: 14px; align-items: flex-start; }
        .mcp-card img { width: 64px; height: 64px; border-radius: 8px; object-fit: cover; border: 1px solid #e5e7eb; flex-shrink: 0; }
        .mcp-download-link { display: block; text-align: center; margin-top: 4px; font-size: 10px; font-weight: 600; color: #667eea; text-decoration: none; }
        .mcp-download-link:hover { text-decoration: underline; }
        .mcp-card .info { flex: 1; min-width: 0; font-size: 13px; color: #374151; }
        .mcp-card .info b { color: #1f2937; }
        .mcp-badge { font-size: 10.5px; font-weight: 700; padding: 2px 9px; border-radius: 20px; text-transform: uppercase; }
        .mcp-badge.napkin { background: #dcfce7; color: #15803d; }
        .mcp-badge.diaper { background: #ede9fe; color: #6d28d9; }
        .mcp-actions button { border: none; font-weight: 700; font-size: 12.5px; padding: 6px 16px; border-radius: 8px; cursor: pointer; margin-left: 6px; }
        .mcp-approve { background: #d1fae5; color: #065f46; }
        .mcp-reject { background: #fee2e2; color: #991b1b; }
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
                    <div class="mcp-wrap">
                        <h1 style="font-size:20px;margin-bottom:12px;"><?php echo $title;?></h1>

                        <?php if ($successMessage): ?><div class="alert alert-success"><?=htmlspecialchars($successMessage)?></div><?php endif; ?>
                        <?php if ($errorMessage): ?><div class="alert alert-danger"><?=htmlspecialchars($errorMessage)?></div><?php endif; ?>

                        <div class="mcp-filter mb-3">
                            <a href="?status=pending_review" class="<?=$statusFilter==='pending_review'?'active':''?>">Pending Review</a>
                            <a href="?status=accepted" class="<?=$statusFilter==='accepted'?'active':''?>">Accepted</a>
                            <a href="?status=rejected" class="<?=$statusFilter==='rejected'?'active':''?>">Rejected</a>
                        </div>

                        <?php if (empty($rows)): ?>
                        <div class="text-muted" style="font-size:13.5px;">No courier payments in this status.</div>
                        <?php else: foreach ($rows as $r): ?>
                        <div class="mcp-card" data-id="<?=$r['id']?>">
                            <div style="flex-shrink:0;">
                                <a href="../territory-partner/courier_payment_screenshots/<?=htmlspecialchars($r['file_path'])?>" target="_blank">
                                    <img src="../territory-partner/courier_payment_screenshots/<?=htmlspecialchars($r['file_path'])?>" alt="">
                                </a>
                                <a href="../territory-partner/courier_payment_screenshots/<?=htmlspecialchars($r['file_path'])?>" download class="mcp-download-link">
                                    <i class="material-icons-outlined" style="font-size:13px;vertical-align:middle;">download</i> Download
                                </a>
                            </div>
                            <div class="info">
                                <b><?=htmlspecialchars($r['tp_name'])?></b> (<?=htmlspecialchars($r['tp_code'])?>)
                                <span class="mcp-badge <?=$r['product_type']?>"><?=htmlspecialchars(tpProductTypeLabel($r['product_type']))?></span>
                                <?php if ($r['po_id']): ?><span class="text-muted" style="font-size:11px;">· PO #<?=$r['po_id']?></span><?php else: ?><span class="text-muted" style="font-size:11px;">· not yet submitted</span><?php endif; ?>
                                <br>
                                <?=(int)$r['total_boxes']?> box<?=(int)$r['total_boxes']!==1?'es':''?><?php if ($r['total_covers'] > 0): ?> + <?=(int)$r['total_covers']?> cover<?=(int)$r['total_covers']!==1?'s':''?><?php endif; ?>
                                — Required: ₹<?=number_format($r['required_amount'],2)?>
                                <?php if (isset($r['courier_amount_override']) && $r['courier_amount_override'] !== null): ?>
                                <span style="color:#92400e;font-weight:600;"> (corrected to ₹<?=number_format($r['courier_amount_override'],2)?>)</span>
                                <?php endif; ?>
                                <?php if ($r['detected_amount'] !== null): ?> · Detected: ₹<?=number_format($r['detected_amount'],2)?><?php endif; ?>
                                <br>
                                <span class="text-muted" style="font-size:11.5px;"><?=date('d-M-Y h:i A', strtotime($r['created_at']))?></span>
                                <?php if ($r['status'] === 'rejected' && $r['rejection_reason']): ?><br><span style="color:#991b1b;font-size:11.5px;"><?=htmlspecialchars($r['rejection_reason'])?></span><?php endif; ?>
                                <?php if ($r['po_id']): ?>
                                <!-- Manual correction for wrong auto box/cover
                                     calculations — overrides every future
                                     retry-payment calc on this specific order.
                                     Only meaningful once a real PO exists. -->
                                <form method="post" style="margin-top:6px;display:flex;align-items:center;gap:6px;">
                                    <input type="hidden" name="po_id" value="<?=$r['po_id']?>">
                                    <input type="number" step="0.01" min="0" name="override_amount" placeholder="Correct amount" value="<?=$r['courier_amount_override'] ?? ''?>" style="width:110px;font-size:11.5px;padding:3px 6px;border:1px solid #d1d5db;border-radius:6px;">
                                    <button type="submit" name="set_override" style="border:none;background:#eef2ff;color:#4338ca;font-size:10.5px;font-weight:700;padding:4px 10px;border-radius:14px;cursor:pointer;">Save</button>
                                    <?php if ($r['courier_amount_override'] !== null): ?>
                                    <button type="submit" name="set_override" formnovalidate onclick="this.form.override_amount.value='';" style="border:none;background:#fee2e2;color:#991b1b;font-size:10.5px;font-weight:700;padding:4px 10px;border-radius:14px;cursor:pointer;">Clear</button>
                                    <?php endif; ?>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php if ($r['status'] === 'pending_review'): ?>
                            <div class="mcp-actions">
                                <button type="button" class="mcp-approve" onclick="courierApprove(<?=$r['id']?>, <?=$r['detected_amount'] !== null ? $r['detected_amount'] : $r['required_amount']?>)">Approve</button>
                                <button type="button" class="mcp-reject" onclick="courierReject(<?=$r['id']?>)">Reject</button>
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
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script>
    function courierApprove(id, suggestedAmount) {
        var amt = prompt('Confirm the amount paid in this screenshot:', suggestedAmount);
        if (amt === null) return;
        $.post('courier-payment-review-action-ajax.php', { courier_payment_id: id, action: 'approve', confirmed_amount: amt })
            .done(function (data) {
                if (data.success) window.location.reload();
                else alert(data.message || 'Action failed.');
            })
            .fail(function () { alert('Could not reach the server.'); });
    }
    function courierReject(id) {
        if (!confirm('Reject this courier payment screenshot?')) return;
        $.post('courier-payment-review-action-ajax.php', { courier_payment_id: id, action: 'reject' })
            .done(function (data) {
                if (data.success) window.location.reload();
                else alert(data.message || 'Action failed.');
            })
            .fail(function () { alert('Could not reach the server.'); });
    }
    </script>
</body>
</html>
