<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/TpCourierPayment.php';
require_once __DIR__ . '/../shared/TpCourierAmountRequest.php';
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$title = "Courier Payment";
$tp_id = (int)$Login_user_IDvl;
$productType = tpResolveProductType($_GET['type'] ?? null);

tpEnsureCourierPaymentTables($db_conn);
tpEnsureCourierAmountRequestTable($db_conn);

// Two entry points into this page:
//   1. Pre-submission (?type=) — the TP's in-progress cart, stashed via
//      stash-po-draft.php before they got here from add-purchase-order.php.
//   2. Post-submission retry (?po_id=) — company rejected the courier
//      payment on an already-submitted order (manage-purchase-orders.php's
//      "Pay Courier Amount Again" button), so the required amount is
//      computed from that PO's real, already-saved line items instead of a
//      session draft, and any new screenshot uploaded here gets linked
//      straight to that po_id rather than sitting in the unlinked pool.
$po_id = (int)($_GET['po_id'] ?? 0);
$items = [];

if ($po_id > 0) {
    $poStmt = $db_conn->prepare("SELECT product_type, status FROM tp_purchase_orders WHERE id = ? AND territory_partner_id = ?");
    $poStmt->bind_param('ii', $po_id, $tp_id);
    $poStmt->execute();
    $poRow = $poStmt->get_result()->fetch_assoc();
    $poStmt->close();

    if (!$poRow || $poRow['status'] !== 'waiting') {
        header("Location: manage-purchase-orders.php");
        exit;
    }
    $productType = $poRow['product_type'];

    tpEnsurePickupColumn($db_conn);
    $itemsStmt = $db_conn->prepare("SELECT product_id, qty, delivery_method FROM tp_purchase_order_items WHERE po_id = ?");
    $itemsStmt->bind_param('i', $po_id);
    $itemsStmt->execute();
    // Only the courier-marked lines feed the box/fee calc — a pickup line
    // was never charged for or included at the original submission either.
    foreach ($itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $it) {
        if (($it['delivery_method'] ?? 'courier') === 'pickup') continue;
        $items[] = ['pid' => (int)$it['product_id'], 'qty' => (int)$it['qty']];
    }
    $itemsStmt->close();
} else {
    // This page only makes sense right after "Pay Courier Amount" stashed
    // the in-progress cart via stash-po-draft.php — without a draft there's
    // no cart to compute a box count/fee from, so bounce back to build one.
    $draft = $_SESSION['po_draft_' . $tp_id] ?? null;
    if (!$draft || empty($draft['lines'])) {
        header("Location: add-purchase-order.php?type=" . urlencode($productType));
        exit;
    }
    // Same pickup exclusion, read from the draft (see stash-po-draft.php) —
    // a line the TP marked "pick up myself" via the Pick Up Order modal
    // never counts toward the courier box/fee calc at all.
    foreach ($draft['lines'] as $l) {
        if (($l['method'] ?? 'courier') === 'pickup') continue;
        $items[] = ['pid' => (int)$l['pr_id'], 'qty' => (int)$l['qty']];
    }
}

$shipment = tpCourierComputeShipmentForItems($db_conn, $items);
$totalBoxes = $shipment['boxes'];
$totalCovers = $shipment['covers'];
$rate = tpCourierRatePerBox($db_conn, $productType, $totalBoxes);
$requiredAmount = tpCourierComputeAmount($db_conn, $productType, $totalBoxes, $totalCovers);

// Pre-submission only ("Change Courier Amount" request to the TP's own
// Sales BDM) — a post-submission (?po_id=) retry has no equivalent
// correction path; the TP just pays whatever the box/cover math says.
$amountRequest = null;
if ($po_id === 0) {
    $courierRequestId = $draft['courier_request_id'] ?? null;
    $amountRequest = $courierRequestId ? tpCourierAmountRequestGetById($db_conn, (int)$courierRequestId, $tp_id) : null;
    if ($amountRequest && $amountRequest['status'] === 'approved') {
        $requiredAmount = (float)$amountRequest['approved_amount'];
    }
}

$courierRates = tpCourierGetRateSettings($db_conn);
$acceptedPool = $po_id > 0 ? tpCourierPoolTotalForPo($db_conn, $po_id) : tpCourierPoolTotal($db_conn, $tp_id, $productType);
$remaining = max(0, round($requiredAmount - $acceptedPool, 2));
// requiredAmount can be genuinely 0 — every line in this cart/order was
// marked "pick up myself" — in which case there's nothing to pay and this
// page should read as already satisfied, not stuck waiting for a payment
// that was never going to happen.
$isPaid = $requiredAmount <= 0 || $remaining <= 0.001;

// Where every "Back"/"Cancel" link on this page returns to — the cart page
// for a pre-submission draft, or the order list for a post-submission retry
// (there's no draft to go back to in that mode).
$backUrl = $po_id > 0 ? 'manage-purchase-orders.php' : ('add-purchase-order.php?type=' . urlencode($productType));
$backLabel = $po_id > 0 ? 'Back to My Purchase Orders' : 'Back to Purchase Order';

$qrRelPath = tpCourierGetQrImagePath($db_conn);
$upiDetails = tpCourierGetUpiDetails($db_conn);

// Existing uploads to show: for a pre-submission cart, still-unclaimed pool
// screenshots (po_id IS NULL, same "unlinked draft" pattern as
// tp_purchase_order_screenshots); for a post-submission retry, every
// screenshot already tried against this specific po_id (including any
// earlier rejected attempt, so the TP can see why it failed).
if ($po_id > 0) {
    $poolStmt = $db_conn->prepare(
        "SELECT id, status, detected_amount, rejection_reason, file_path
         FROM tp_courier_payments WHERE po_id = ? ORDER BY id ASC"
    );
    $poolStmt->bind_param('i', $po_id);
} else {
    $poolStmt = $db_conn->prepare(
        "SELECT id, status, detected_amount, rejection_reason, file_path
         FROM tp_courier_payments WHERE territory_partner_id = ? AND product_type = ? AND po_id IS NULL ORDER BY id ASC"
    );
    $poolStmt->bind_param('is', $tp_id, $productType);
}
$poolStmt->execute();
$poolRows = $poolStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$poolStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $title;?> | Femi9</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .cpay-wrap { max-width: 560px; margin: 24px auto; padding: 0 14px; }
        .cpay-card { background: #fff; border-radius: 12px; padding: 20px 22px; margin-bottom: 16px; border: 1px solid #eef0f2; }
        .cpay-title { font-size: 14.5px; font-weight: 700; color: #374151; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .cpay-title i { font-size: 18px; color: #667eea; }
        .cpay-amount-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 14px; padding: 20px 22px;
            color: #fff; margin-bottom: 16px;
        }
        .cpay-amount-card .label { font-size: 12px; opacity: .85; }
        .cpay-amount-card .value { font-size: 30px; font-weight: 700; }
        .cpay-amount-card .sub { font-size: 12.5px; opacity: .9; margin-top: 4px; }
        .cpay-breakdown {
            margin-top: 14px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,.25);
        }
        .cpay-breakdown > div { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px; opacity: .95; }
        .cpay-breakdown .cpay-breakdown-remaining { font-size: 14.5px; font-weight: 700; opacity: 1; margin-top: 4px; }
        .cpay-qr { text-align: center; padding: 10px 0; }
        .cpay-qr img { max-width: 240px; width: 100%; border-radius: 10px; border: 1px solid #e5e7eb; }
        .cpay-qr-download {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            width: 100%; background: #fff; border: 2px solid #667eea; color: #667eea;
            font-weight: 700; font-size: 15px; padding: 12px; border-radius: 10px;
            text-decoration: none; margin-bottom: 12px;
        }
        .cpay-qr-download:hover { background: #f5f6ff; color: #4f46e5; }
        .cpay-qr-download:active { transform: scale(.98); }
        .cpay-upi-btn {
            display: block; text-align: center; width: 100%; background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: #fff; font-weight: 700; padding: 12px; border-radius: 10px; font-size: 14.5px; text-decoration: none;
        }
        .cpay-upi-btn:hover { color: #fff; opacity: .92; }
        .cpay-copy-box { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; }
        .cpay-copy-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
        .cpay-copy-row:last-of-type { margin-bottom: 0; }
        .cpay-copy-label { font-size: 10.5px; color: #9ca3af; text-transform: uppercase; letter-spacing: .3px; }
        .cpay-copy-value { font-size: 15px; font-weight: 700; color: #1f2937; word-break: break-all; }
        .cpay-copy-btn { flex-shrink: 0; border: 1px solid #667eea; background: #fff; color: #667eea; font-weight: 700; font-size: 12.5px; padding: 6px 14px; border-radius: 8px; cursor: pointer; }
        .cpay-copy-btn.copied { background: #10b981; border-color: #10b981; color: #fff; }
        .cpay-upload-row { background: #fff; border: 1px dashed #d1d5db; border-radius: 10px; padding: 14px; }
        .cpay-screenshot-card {
            background: #fff; border: 1px solid #f1f5f9; border-radius: 10px; padding: 12px 14px;
            display: flex; align-items: center; gap: 12px; margin-bottom: 10px;
        }
        .cpay-screenshot-card .thumb { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; border: 1px solid #e5e7eb; flex-shrink: 0; }
        .cpay-status-pill { padding: 3px 10px; border-radius: 20px; font-size: 10.5px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; }
        .cpay-status-pill.accepted { background: #d1fae5; color: #065f46; }
        .cpay-status-pill.rejected { background: #fee2e2; color: #991b1b; }
        .cpay-status-pill.pending_review { background: #fef3c7; color: #92400e; }
        .cpay-remove-chip { border: none; background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; cursor: pointer; }
        .cpay-continue-btn {
            display: block; text-align: center; width: 100%; background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none; color: #fff; font-weight: 700; padding: 12px; border-radius: 10px; font-size: 15px; text-decoration: none;
        }
        .cpay-dispute-btn {
            display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%;
            background: #fff7ed; border: 2px solid #fb923c; color: #c2410c; font-weight: 700;
            padding: 10px; border-radius: 10px; font-size: 13.5px; margin-bottom: 14px; cursor: pointer;
            transition: background .15s ease-in-out, color .15s ease-in-out;
        }
        .cpay-dispute-btn:hover { background: #fb923c; color: #fff; }
        .cpay-continue-btn:hover { color: #fff; opacity: .92; }
        .cpay-continue-disabled { display: block; text-align: center; width: 100%; background: #e5e7eb; color: #9ca3af; font-weight: 700; padding: 12px; border-radius: 10px; font-size: 15px; }
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
                    <div class="cpay-wrap">
                        <!-- A dedicated in-page Back link, not relying on the
                             phone's own back button — tapping the "Pay via
                             UPI app" link below switches to a separate app,
                             and on some Android browsers the tab's back
                             history gets confused after switching back, so
                             the device back button can appear to do nothing. -->
                        <a href="<?=htmlspecialchars($backUrl)?>" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:#fff;border:2px solid #667eea;color:#667eea;font-weight:700;font-size:16px;padding:14px;border-radius:10px;text-decoration:none;margin-bottom:16px;">
                            <i class="material-icons" style="font-size:22px;">arrow_back</i> <?=htmlspecialchars($backLabel)?>
                        </a>
                        <h1 style="font-size:20px;margin-bottom:4px;"><?php echo $title;?></h1>
                        <p style="color:#6b7280;font-size:13px;margin-bottom:16px;">
                            Required before this <?=htmlspecialchars(tpProductTypeLabel($productType))?> purchase order can be submitted.
                        </p>

                        <div class="cpay-amount-card">
                            <div class="label">Courier Amount for this order</div>
                            <div class="value">&#8377;<?=number_format($requiredAmount, 2)?></div>
                            <div class="sub">
                                <?php if ($amountRequest && $amountRequest['status'] === 'approved'): ?>
                                Corrected by your Sales BDM (was &#8377;<?=number_format($amountRequest['calculated_amount'],2)?>)
                                <?php else: ?>
                                <?php if ($totalBoxes > 0): ?><?=$totalBoxes?> box<?=$totalBoxes !== 1 ? 'es' : ''?> &times; &#8377;<?=number_format($rate,2)?>/box<?php if ($productType === 'napkin' && $totalBoxes > 10): ?> (10+ box rate applied)<?php endif; ?><?php endif; ?>
                                <?php if ($totalBoxes > 0 && $totalCovers > 0): ?> + <?php endif; ?>
                                <?php if ($totalCovers > 0): ?><?=$totalCovers?> cover<?=$totalCovers !== 1 ? 's' : ''?> &times; &#8377;<?=number_format($courierRates['cover_rate'],2)?>/cover<?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($acceptedPool > 0 && !$isPaid): ?>
                            <!-- A partial payment already exists (e.g. from an
                                 earlier, smaller cart) — spell out Required /
                                 Already Paid / Remaining explicitly so it's
                                 never ambiguous why the page isn't showing
                                 "paid" yet despite a screenshot already
                                 sitting in the pool. -->
                            <div class="cpay-breakdown">
                                <div><span>Required</span><b>&#8377;<?=number_format($requiredAmount, 2)?></b></div>
                                <div><span>Already Paid</span><b>&#8377;<?=number_format(min($acceptedPool, $requiredAmount), 2)?></b></div>
                                <div class="cpay-breakdown-remaining"><span>Remaining to Pay</span><b>&#8377;<?=number_format($remaining, 2)?></b></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($po_id === 0 && !$isPaid): ?>
                            <?php if ($amountRequest && $amountRequest['status'] === 'pending'): ?>
                            <div class="cpay-card" style="background:#fffbeb;border-color:#fde68a;padding:14px 16px;">
                                <div style="color:#92400e;font-weight:700;font-size:13px;"><i class="material-icons-outlined" style="vertical-align:middle;font-size:17px;">hourglass_top</i> Your request to change this amount is pending review by your Sales BDM.</div>
                                <div style="color:#92400e;font-size:12px;margin-top:3px;">You requested &#8377;<?=number_format($amountRequest['calculated_amount'],2)?> be reviewed<?php if ($amountRequest['note']): ?> — "<?=htmlspecialchars($amountRequest['note'])?>"<?php endif; ?>.</div>
                            </div>
                            <?php elseif (!$amountRequest): ?>
                            <button type="button" class="cpay-dispute-btn" onclick="document.getElementById('cpayChangeAmountModal').style.display='flex';">
                                <i class="material-icons-outlined" style="font-size:17px;">edit_note</i> This amount looks wrong — Change Courier Amount
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($isPaid): ?>
                        <div class="cpay-card" style="background:#ecfdf5;border-color:#a7f3d0;">
                            <div style="color:#065f46;font-weight:700;font-size:14px;"><i class="material-icons-outlined" style="vertical-align:middle;font-size:18px;">check_circle</i> Courier payment received — taking you back to the purchase order…</div>
                        </div>
                        <script>
                        // No manual "Back to Purchase Order" click needed once paid —
                        // return automatically so the TP doesn't have to notice and tap
                        // a button themselves (per explicit request, 2026-09-05).
                        setTimeout(function () { window.location.href = <?=json_encode($backUrl)?>; }, 1200);
                        </script>
                        <?php else: ?>
                        <div class="cpay-card">
                            <div class="cpay-title"><i class="material-icons-outlined">qr_code_2</i>Scan &amp; Pay</div>

                            <?php if ($upiDetails['upi_id']): ?>
                            <!-- Recommended: copy the UPI ID and pay manually inside the
                                 TP's own UPI app. Some banks apply extra fraud-prevention
                                 friction specifically to a upi://pay link triggered FROM A
                                 BROWSER (a common phishing vector), even though the exact
                                 same amount to the exact same UPI ID succeeds when typed in
                                 manually — confirmed 2026-09-04. This sidesteps that entirely. -->
                            <div class="cpay-copy-box">
                                <div class="cpay-copy-row">
                                    <div>
                                        <div class="cpay-copy-label">UPI ID</div>
                                        <div class="cpay-copy-value" id="cpayUpiIdValue"><?=htmlspecialchars($upiDetails['upi_id'])?></div>
                                    </div>
                                    <button type="button" class="cpay-copy-btn" onclick="copyCourierText('cpayUpiIdValue', this)">Copy</button>
                                </div>
                                <div class="cpay-copy-row">
                                    <div>
                                        <div class="cpay-copy-label">Amount</div>
                                        <div class="cpay-copy-value" id="cpayAmountValue"><?=number_format($remaining, 2, '.', '')?></div>
                                    </div>
                                    <button type="button" class="cpay-copy-btn" onclick="copyCourierText('cpayAmountValue', this)">Copy</button>
                                </div>
                                <div style="font-size:11.5px;color:#6b7280;margin-top:4px;">Open your UPI app yourself, paste this UPI ID, enter the amount, and pay.</div>
                            </div>
                            <div style="text-align:center;font-size:11.5px;color:#9ca3af;margin:12px 0;">— or scan the QR below with another phone —</div>
                            <?php endif; ?>

                            <?php if ($qrRelPath): ?>
                            <a href="courier_qr/<?=htmlspecialchars($qrRelPath)?>" download class="cpay-qr-download">
                                <i class="material-icons-outlined" style="font-size:19px;vertical-align:middle;">download</i> Download QR
                            </a>
                            <div class="cpay-qr"><img src="courier_qr/<?=htmlspecialchars($qrRelPath)?>" alt="Courier Payment QR"></div>
                            <?php else: ?>
                            <div class="text-muted" style="font-size:13px;">Payment QR code is not set up yet — please contact the company.</div>
                            <?php endif; ?>
                            <div style="font-size:12.5px;color:#6b7280;margin-top:6px;">
                                Pay &#8377;<?=number_format($remaining, 2)?><?=$remaining < $requiredAmount ? ' more' : ''?> and upload the payment screenshot below.
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="cpay-card">
                            <div class="cpay-title"><i class="material-icons-outlined">receipt_long</i>Payment Screenshot</div>

                            <div id="cpayScreenshotList">
                                <?php foreach ($poolRows as $s): ?>
                                <div class="cpay-screenshot-card" data-id="<?=$s['id']?>">
                                    <img class="thumb" src="courier_payment_screenshots/<?=htmlspecialchars($s['file_path'])?>" alt="">
                                    <div style="flex:1;min-width:0;font-size:12.5px;color:#4b5563;">
                                        <?php if ($s['detected_amount'] !== null): ?>Amount: &#8377;<?=number_format($s['detected_amount'],2)?><br><?php endif; ?>
                                        <span class="cpay-status-pill <?=$s['status']?>"><?=str_replace('_',' ',$s['status'])?></span>
                                        <?php if ($s['status'] === 'rejected' && $s['rejection_reason']): ?><div style="color:#991b1b;margin-top:3px;"><?=htmlspecialchars($s['rejection_reason'])?></div><?php endif; ?>
                                    </div>
                                    <?php if ($s['status'] !== 'accepted'): ?>
                                    <button type="button" class="cpay-remove-chip" onclick="removeCourierScreenshot(<?=$s['id']?>, this)">Remove</button>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (!$isPaid): ?>
                            <div class="cpay-upload-row">
                                <input type="file" id="cpay_file" accept="image/*,.heic,.heif" class="form-control d-inline-block mb-2" style="max-width:280px;">
                                <button type="button" class="btn btn-secondary btn-sm" id="cpayUploadBtn" onclick="uploadCourierScreenshot()">Upload</button>
                                <small class="text-muted d-block mt-1">Max file size: 10 MB.</small>
                                <div id="cpayUploadStatus" class="mt-1" style="font-size:12.5px;"></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($isPaid): ?>
                        <a href="<?=htmlspecialchars($backUrl)?>" class="cpay-continue-btn">
                            <i class="material-icons" style="vertical-align:middle;font-size:18px;">arrow_back</i> <?=htmlspecialchars($backLabel)?>
                        </a>
                        <?php else: ?>
                        <div class="cpay-continue-disabled">Upload a matching payment screenshot to continue</div>
                        <!-- Same Back button as the top of the page, repeated here — a
                             screenshot stuck on "pending_review" (needs company approval
                             first) leaves the TP with nothing usable to do on this page for
                             a while, so they need an easy way back without scrolling up. -->
                        <a href="<?=htmlspecialchars($backUrl)?>" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:#fff;border:2px solid #667eea;color:#667eea;font-weight:700;font-size:15px;padding:12px;border-radius:10px;text-decoration:none;margin-top:12px;">
                            <i class="material-icons" style="font-size:20px;">arrow_back</i> <?=htmlspecialchars($backLabel)?>
                        </a>
                        <?php endif; ?>
                        <a href="<?=htmlspecialchars($backUrl)?>" style="display:block;text-align:center;margin-top:10px;font-size:12.5px;color:#6b7280;">Cancel and go back without paying</a>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($po_id === 0 && !$isPaid && !$amountRequest): ?>
    <div id="cpayChangeAmountModal" style="display:none;position:fixed;inset:0;background:rgba(17,24,39,.55);z-index:1050;align-items:center;justify-content:center;padding:16px;">
        <div style="background:#fff;border-radius:14px;padding:22px;max-width:400px;width:100%;">
            <div style="font-weight:700;font-size:15px;color:#1f2937;margin-bottom:6px;">Request a Courier Amount Change</div>
            <div style="font-size:12.5px;color:#6b7280;margin-bottom:12px;">This sends the current calculated amount (&#8377;<?=number_format($requiredAmount,2)?>) to your assigned Sales BDM for review. Tell them why you think it's wrong.</div>
            <textarea id="cpayChangeReason" class="form-control" rows="3" placeholder="e.g. nearest location, some items were dropped, etc." style="font-size:13px;"></textarea>
            <div id="cpayChangeAmountStatus" style="font-size:12.5px;margin-top:8px;"></div>
            <div style="display:flex;gap:10px;margin-top:14px;">
                <button type="button" class="btn btn-light" style="flex:1;" onclick="document.getElementById('cpayChangeAmountModal').style.display='none';">Cancel</button>
                <button type="button" class="btn btn-primary" id="cpayChangeAmountSubmitBtn" style="flex:1;" onclick="submitCourierAmountChangeRequest();">Send Request</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js"></script>
    <script>
    var MAX_SCREENSHOT_BYTES = 10 * 1024 * 1024;

    function uploadCourierScreenshot() {
        var fileInput = document.getElementById('cpay_file');
        var file = fileInput.files[0];
        var statusEl = document.getElementById('cpayUploadStatus');
        if (!file) { statusEl.textContent = 'Choose a file first.'; statusEl.style.color = '#991b1b'; return; }
        if (file.size > MAX_SCREENSHOT_BYTES) { statusEl.textContent = 'File too large (max 10 MB).'; statusEl.style.color = '#991b1b'; return; }

        var btn = document.getElementById('cpayUploadBtn');
        btn.disabled = true;
        statusEl.textContent = 'Uploading…';
        statusEl.style.color = '#6b7280';

        function doUpload(uploadFile) {
            var fd = new FormData();
            fd.append('screenshot', uploadFile, uploadFile.name);
            fd.append('product_type', <?=json_encode($productType)?>);
            fd.append('total_boxes', <?=json_encode($totalBoxes)?>);
            fd.append('total_covers', <?=json_encode($totalCovers)?>);
            fd.append('po_id', <?=json_encode($po_id)?>);
            fd.append('required_amount', <?=json_encode($requiredAmount)?>);
            fetch('upload-courier-payment-screenshot.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    btn.disabled = false;
                    if (!data.success) {
                        statusEl.textContent = data.message || 'Upload failed.';
                        statusEl.style.color = '#991b1b';
                        return;
                    }
                    statusEl.textContent = '';
                    fileInput.value = '';
                    window.location.reload();
                })
                .catch(function() {
                    btn.disabled = false;
                    statusEl.textContent = 'Could not reach the server — please try again.';
                    statusEl.style.color = '#991b1b';
                });
        }

        var ext = (file.name.split('.').pop() || '').toLowerCase();
        if ((file.type === 'image/heic' || file.type === 'image/heif' || ext === 'heic' || ext === 'heif') && window.heic2any) {
            statusEl.textContent = 'Converting HEIC image…';
            heic2any({ blob: file, toType: 'image/jpeg', quality: 0.85 })
                .then(function(converted) {
                    var convertedFile = new File([converted], file.name.replace(/\.(heic|heif)$/i, '.jpg'), { type: 'image/jpeg' });
                    doUpload(convertedFile);
                })
                .catch(function() { doUpload(file); });
        } else {
            doUpload(file);
        }
    }

    function copyCourierText(elId, btn) {
        var text = document.getElementById(elId).textContent.trim();
        var done = function() {
            var original = btn.textContent;
            btn.textContent = 'Copied!';
            btn.classList.add('copied');
            setTimeout(function() { btn.textContent = original; btn.classList.remove('copied'); }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function() { fallbackCopy(text, done); });
        } else {
            fallbackCopy(text, done);
        }
    }

    function fallbackCopy(text, done) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) { alert('Could not copy — please select and copy manually: ' + text); }
        document.body.removeChild(ta);
    }

    function submitCourierAmountChangeRequest() {
        var note = document.getElementById('cpayChangeReason').value.trim();
        var statusEl = document.getElementById('cpayChangeAmountStatus');
        var btn = document.getElementById('cpayChangeAmountSubmitBtn');
        btn.disabled = true;
        statusEl.textContent = 'Sending…';
        statusEl.style.color = '#6b7280';

        fetch('request-courier-amount-change.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'product_type=' + encodeURIComponent(<?=json_encode($productType)?>)
                + '&total_boxes=' + encodeURIComponent(<?=json_encode($totalBoxes)?>)
                + '&total_covers=' + encodeURIComponent(<?=json_encode($totalCovers)?>)
                + '&calculated_amount=' + encodeURIComponent(<?=json_encode($requiredAmount)?>)
                + '&note=' + encodeURIComponent(note)
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                if (!data.success) {
                    statusEl.textContent = data.message || 'Could not send request.';
                    statusEl.style.color = '#991b1b';
                    return;
                }
                window.location.reload();
            })
            .catch(function() {
                btn.disabled = false;
                statusEl.textContent = 'Could not reach the server — please try again.';
                statusEl.style.color = '#991b1b';
            });
    }

    function removeCourierScreenshot(id, btn) {
        btn.disabled = true;
        fetch('remove-courier-payment-screenshot.php', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id)
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) window.location.reload();
                else { btn.disabled = false; alert(data.message || 'Could not remove.'); }
            })
            .catch(function() { btn.disabled = false; alert('Could not reach the server.'); });
    }
    </script>
</body>
</html>
