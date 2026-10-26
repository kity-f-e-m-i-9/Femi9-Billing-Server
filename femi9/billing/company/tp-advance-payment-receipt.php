<?php
// Printable receipt for one TP advance payment row. Cash shows as a plain
// manually-recorded entry; every other payment mode shows the reference/bank
// details and, if this payment came from a TP's screenshot submission
// (tp_advance_payment_submissions.advance_payment_id -> this row), the
// accepted proof screenshot itself — the same file the TP originally
// uploaded, served straight from territory-partner/advance_payment_screenshots/.
// A payment entered directly by Company (no submission row) has no
// screenshot to show even if its mode isn't Cash.
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner_edit');
include("config.php");
error_reporting(0);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: manage-tp-advance-payments"); exit; }

$stmt = $db_conn->prepare(
    "SELECT tap.*, tp.name AS tp_name, tp.tp_id AS tp_code, cg.gname AS receiver_name
     FROM tp_advance_payments tap
     JOIN territory_partners tp ON tp.id = tap.territory_partner_id
     LEFT JOIN company_godown cg ON cg.id = tap.company_id
     WHERE tap.id = ?"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$p) { header("Location: manage-tp-advance-payments"); exit; }

$isCash = strcasecmp(trim((string)$p['payment_mode']), 'Cash') === 0;

// Only a non-cash payment can have a proof screenshot, and only if it came
// through a TP submission (direct Company entries have none).
$screenshotPath = null;
if (!$isCash) {
    $sStmt = $db_conn->prepare(
        "SELECT s.file_path
         FROM tp_advance_payment_submissions sub
         JOIN tp_advance_payment_screenshots s ON s.submission_id = sub.id
         WHERE sub.advance_payment_id = ?
         ORDER BY (s.status = 'accepted') DESC, s.created_at DESC
         LIMIT 1"
    );
    $sStmt->bind_param('i', $id);
    $sStmt->execute();
    $screenshotRow = $sStmt->get_result()->fetch_assoc();
    $sStmt->close();
    if ($screenshotRow) { $screenshotPath = $screenshotRow['file_path']; }
}

function fv($val) { return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Advance Payment Receipt — <?php echo fv($p['tp_name']); ?></title>
<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; color: #1f2937; background: #f3f4f6; margin: 0; padding: 24px; }
    .receipt { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.08); overflow: hidden; }
    .receipt-head { background: #0d9488; color: #fff; padding: 20px 28px; }
    .receipt-head h1 { margin: 0 0 4px; font-size: 20px; }
    .receipt-head .sub { font-size: 13px; opacity: .9; }
    .receipt-body { padding: 24px 28px; }
    .row { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    .row .label { color: #6b7280; }
    .row .value { font-weight: 600; text-align: right; }
    .amount-box { text-align: center; margin: 18px 0; padding: 16px; background: #f0fdfa; border-radius: 8px; }
    .amount-box .amt { font-size: 28px; font-weight: 700; color: #0d9488; }
    .mode-badge { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    .mode-cash { background: #fef3c7; color: #92400e; }
    .mode-other { background: #dbeafe; color: #1e40af; }
    .proof-section { margin-top: 20px; padding-top: 16px; border-top: 1px dashed #d1d5db; }
    .proof-section h3 { font-size: 14px; margin: 0 0 10px; color: #374151; }
    .proof-section img { max-width: 100%; border: 1px solid #e5e7eb; border-radius: 8px; }
    .cash-note { margin-top: 20px; padding: 14px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; font-size: 13px; color: #92400e; }
    .no-print-bar { max-width: 640px; margin: 0 auto 12px; text-align: right; }
    @media print { body { background: #fff; padding: 0; } .receipt { box-shadow: none; } .no-print-bar { display: none; } }
</style>
</head>
<body>
    <div class="no-print-bar">
        <button onclick="window.print()" style="padding:8px 18px;border:none;border-radius:6px;background:#0d9488;color:#fff;font-weight:600;cursor:pointer;">Print</button>
    </div>
    <div class="receipt">
        <div class="receipt-head">
            <h1><?php echo fv($business_name ?? 'Femi9'); ?> — Advance Payment Receipt</h1>
            <div class="sub">Receipt No: ADV-<?php echo (int)$p['id']; ?> &middot; Issued: <?php echo fv(date('d-M-Y')); ?></div>
        </div>
        <div class="receipt-body">
            <div class="amount-box">
                <div class="amt">&#8377;<?php echo fv(number_format((float)$p['amount'], 2)); ?></div>
                <span class="mode-badge <?php echo $isCash ? 'mode-cash' : 'mode-other'; ?>"><?php echo fv($p['payment_mode']); ?></span>
            </div>
            <div class="row"><span class="label">Territory Partner</span><span class="value"><?php echo fv($p['tp_name']); ?> (<?php echo fv($p['tp_code']); ?>)</span></div>
            <div class="row"><span class="label">Received By</span><span class="value"><?php echo fv($p['receiver_name'] ?: '—'); ?></span></div>
            <div class="row"><span class="label">Payment Date</span><span class="value"><?php echo fv($p['payment_date']); ?></span></div>
            <div class="row"><span class="label">Product Wallet</span><span class="value"><?php echo fv(ucfirst($p['product_type'] ?? '')); ?></span></div>
            <?php if (!$isCash): ?>
            <div class="row"><span class="label">Reference Number</span><span class="value"><?php echo fv($p['reference_number'] ?: '—'); ?></span></div>
            <div class="row"><span class="label">Bank Name</span><span class="value"><?php echo fv($p['bank_name'] ?: '—'); ?></span></div>
            <?php endif; ?>
            <?php if (!empty($p['remarks'])): ?>
            <div class="row"><span class="label">Remarks</span><span class="value"><?php echo fv($p['remarks']); ?></span></div>
            <?php endif; ?>
            <div class="row"><span class="label">Recorded By</span><span class="value"><?php echo fv($p['created_by']); ?></span></div>

            <?php if ($isCash): ?>
            <div class="cash-note">This was a <strong>Cash</strong> payment, recorded manually by Company — no bank/transfer proof applies.</div>
            <?php elseif ($screenshotPath): ?>
            <div class="proof-section">
                <h3>Payment Proof (as uploaded by the Territory Partner)</h3>
                <img src="../territory-partner/<?php echo fv($screenshotPath); ?>" alt="Payment proof screenshot">
            </div>
            <?php else: ?>
            <div class="proof-section">
                <h3>Payment Proof</h3>
                <p style="font-size:13px;color:#6b7280;">No screenshot on file — this payment was entered directly by Company, not converted from a TP submission.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
