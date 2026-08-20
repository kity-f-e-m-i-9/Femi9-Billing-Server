<?php
/**
 * Lists TP advance-payment rows whose funding history genuinely spans both
 * Napkin and Diaper invoices — the ~22 rows the original backfill
 * deliberately left at the 'napkin' default rather than auto-splitting by
 * ratio (per an explicit "don't guess, a human decides these" instruction).
 * Recomputes the classification live (funding history can grow after the
 * original backfill ran), and lets a company reviewer pick a side for each
 * row still unreviewed — the pick is remembered via
 * product_type_reviewed so a resolved row never resurfaces here again.
 *
 * This only relabels which wallet the row's REMAINING balance belongs to
 * going forward — it never moves money or touches amount/balance_amount.
 */
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('territory_partner');
require_once __DIR__ . '/../shared/TpProductType.php';
error_reporting(0);

tpEnsureAdvanceWalletColumns($db_conn);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$rows = $db_conn->query("
    SELECT tap.id, tap.territory_partner_id, tap.amount, tap.balance_amount, tap.adjusted_amount,
           tap.product_type, tap.payment_date, tap.status,
           tp.name AS tp_name, tp.tp_id AS tp_code
    FROM tp_advance_payments tap
    JOIN territory_partners tp ON tp.id = tap.territory_partner_id
    WHERE tap.deleted_at IS NULL AND tap.product_type_reviewed = 0
    ORDER BY tap.payment_date DESC, tap.id DESC
")->fetch_all(MYSQLI_ASSOC);

$mixedRows = [];
foreach ($rows as $r) {
    $history = tpAdvancePaymentFundingHistory($db_conn, (int)$r['id']);
    if (!$history['mixed']) continue;

    // Pull the actual invoice-by-invoice breakdown so the reviewer can see
    // exactly what this payment funded, not just "it's mixed".
    $invStmt = $db_conn->prepare("
        SELECT ti.id AS invoice_id, ti.invoice_number, ti.invoice_date, l.deducted_amount,
               GROUP_CONCAT(DISTINCT CASE WHEN p.category = 'diaper' THEN 'Diaper' ELSE 'Napkin' END SEPARATOR '/') AS line_types
        FROM tp_invoice_advance_log l
        JOIN tp_invoices ti ON ti.id = l.tp_invoice_id
        JOIN tp_invoice_items tii ON tii.tp_invoice_id = ti.id
        JOIN products p ON p.id = tii.product_id
        WHERE l.tp_advance_id = ?
        GROUP BY ti.id, ti.invoice_number, ti.invoice_date, l.deducted_amount
        ORDER BY ti.invoice_date ASC
    ");
    $invStmt->bind_param('i', $r['id']);
    $invStmt->execute();
    $r['invoices'] = $invStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $invStmt->close();

    // Already-spent totals per type, straight from real invoice history —
    // this part is never a judgment call, so the split flow only asks the
    // reviewer to decide the still-UNSPENT balance_amount, not this.
    $adjustedNapkin = 0.0; $adjustedDiaper = 0.0;
    foreach ($r['invoices'] as $inv) {
        if ($inv['line_types'] === 'Diaper') $adjustedDiaper += (float)$inv['deducted_amount'];
        elseif ($inv['line_types'] === 'Napkin') $adjustedNapkin += (float)$inv['deducted_amount'];
        // A single invoice mixing both types is rare and ambiguous at the
        // line level — left out of the auto totals; the reviewer can still
        // see it in the table above and use the simple/manual-split path.
    }
    $r['adjusted_napkin'] = round($adjustedNapkin, 2);
    $r['adjusted_diaper'] = round($adjustedDiaper, 2);

    $mixedRows[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Mixed Advance Payments : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .mix-card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:18px 20px; margin-bottom:16px; }
        .mix-head { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px; margin-bottom:10px; }
        .mix-tp { font-weight:700; font-size:15px; }
        .mix-code { color:#6b7280; font-size:12.5px; }
        .mix-amt { text-align:right; }
        .mix-amt .big { font-size:17px; font-weight:700; color:#1f2937; }
        .mix-amt .sub { font-size:11.5px; color:#6b7280; }
        table.mix-inv { width:100%; font-size:12.5px; border-collapse:collapse; margin:10px 0; }
        table.mix-inv th { text-align:left; color:#6b7280; font-weight:600; padding:4px 8px; border-bottom:1px solid #e5e7eb; }
        table.mix-inv td { padding:4px 8px; border-bottom:1px solid #f3f4f6; }
        .mix-actions { display:flex; align-items:center; gap:10px; margin-top:12px; }
        .mix-actions select { max-width:220px; }
        .badge-diaper { background:#ede9fe; color:#6d28d9; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
        .badge-napkin { background:#dcfce7; color:#15803d; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
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
                    <div class="page-description"><h1 style="margin:0;">Review Mixed Advance Payments</h1></div>
                    <p style="color:#6b7280;margin-top:8px;">
                        These advance payments have funded <strong>both</strong> Napkin and Diaper invoices at
                        different times, so the backfill left them at the Napkin default rather than guessing a
                        split. Pick which wallet each row's <strong>remaining balance</strong> should belong to
                        going forward — this only relabels the row, it never moves or changes any amount.
                    </p>

                    <?php if (isset($_GET['saved'])): ?>
                    <div class="alert alert-success">Saved — that row won't appear here again.</div>
                    <?php endif; ?>

                    <?php if (empty($mixedRows)): ?>
                    <div class="alert alert-info">No mixed advance payments need review right now.</div>
                    <?php else: ?>
                    <p style="color:#6b7280;"><?=count($mixedRows)?> row(s) need a decision.</p>

                    <?php foreach ($mixedRows as $r): ?>
                    <div class="mix-card">
                        <div class="mix-head">
                            <div>
                                <div class="mix-tp"><?=htmlspecialchars($r['tp_name'])?></div>
                                <div class="mix-code"><?=htmlspecialchars($r['tp_code'])?> &middot; Paid <?=htmlspecialchars($r['payment_date'])?></div>
                            </div>
                            <div class="mix-amt">
                                <div class="big">&#8377;<?=number_format((float)$r['amount'], 2)?></div>
                                <div class="sub">Balance remaining: &#8377;<?=number_format((float)$r['balance_amount'], 2)?> &middot; Currently: <span class="badge-<?=$r['product_type']?>"><?=tpProductTypeLabel($r['product_type'])?></span></div>
                            </div>
                        </div>

                        <table class="mix-inv">
                            <thead><tr><th>Invoice</th><th>Date</th><th>Products</th><th style="text-align:right;">Amount from this payment</th></tr></thead>
                            <tbody>
                                <?php foreach ($r['invoices'] as $inv): ?>
                                <tr>
                                    <td><?=htmlspecialchars($inv['invoice_number'])?></td>
                                    <td><?=htmlspecialchars($inv['invoice_date'])?></td>
                                    <td><?=htmlspecialchars($inv['line_types'])?></td>
                                    <td style="text-align:right;">&#8377;<?=number_format((float)$inv['deducted_amount'], 2)?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <form method="post" action="review-mixed-advance-payment-action.php" class="mix-review-form" data-balance="<?=number_format((float)$r['balance_amount'], 2, '.', '')?>">
                            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                            <input type="hidden" name="advance_id" value="<?=(int)$r['id']?>">

                            <div class="mix-actions">
                                <label style="margin:0;font-size:13px;font-weight:600;">Assign whole row to one wallet:</label>
                                <select name="product_type" class="form-control form-control-sm mix-single-select">
                                    <option value="napkin" <?=$r['product_type']==='napkin'?'selected':''?>>Napkin</option>
                                    <option value="diaper" <?=$r['product_type']==='diaper'?'selected':''?>>Lumi Diaper</option>
                                </select>
                                <button type="submit" name="mode" value="simple" class="btn btn-primary btn-sm mix-simple-submit">Confirm</button>
                            </div>

                            <div class="mix-split-panel" style="margin-top:10px;padding:10px 12px;background:#f9fafb;border-radius:8px;">
                                <div style="font-size:12px;color:#6b7280;margin-bottom:8px;">
                                    <strong>Split by actual usage</strong> &mdash; already funded
                                    <span class="badge-napkin">&#8377;<?=number_format($r['adjusted_napkin'], 2)?> Napkin</span>
                                    <span class="badge-diaper">&#8377;<?=number_format($r['adjusted_diaper'], 2)?> Diaper</span>
                                    from real invoices (kept exactly as-is, never changes here).
                                    <?php if ((float)$r['balance_amount'] > 0): ?>
                                    Decide the remaining <strong>&#8377;<?=number_format((float)$r['balance_amount'], 2)?></strong> unspent balance below.
                                    <?php else: ?>
                                    No balance remains — this only fixes the type label on record, for accurate reporting.
                                    <?php endif; ?>
                                </div>
                                <?php if ((float)$r['balance_amount'] > 0): ?>
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                    <label style="margin:0;font-size:12.5px;">Napkin &#8377;</label>
                                    <input type="number" name="split_napkin_amount" class="form-control form-control-sm mix-split-napkin" style="width:130px;" min="0" step="0.01" placeholder="0.00">
                                    <label style="margin:0;font-size:12.5px;">Lumi Diaper &#8377;</label>
                                    <input type="number" name="split_diaper_amount" class="form-control form-control-sm mix-split-diaper" style="width:130px;" min="0" step="0.01" placeholder="0.00">
                                    <span class="mix-split-check-total" style="font-size:12px;"></span>
                                    <button type="submit" name="mode" value="split" class="btn btn-outline-primary btn-sm mix-split-submit" disabled>Confirm Split</button>
                                </div>
                                <?php else: ?>
                                <input type="hidden" name="split_napkin_amount" value="0">
                                <input type="hidden" name="split_diaper_amount" value="0">
                                <button type="submit" name="mode" value="split" class="btn btn-outline-primary btn-sm">Split by Invoice History</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script>
$(document).ready(function () {
    $('.mix-split-napkin, .mix-split-diaper').on('input', function () {
        var $form = $(this).closest('form');
        var balance = parseFloat($form.data('balance')) || 0;
        var napkin = parseFloat($form.find('.mix-split-napkin').val()) || 0;
        var diaper = parseFloat($form.find('.mix-split-diaper').val()) || 0;
        var total = Math.round((napkin + diaper) * 100) / 100;
        var $total = $form.find('.mix-split-check-total');
        var matches = Math.abs(total - balance) < 0.01 && napkin >= 0 && diaper >= 0;
        $total.text('Total: ₹' + total.toFixed(2) + ' / ₹' + balance.toFixed(2))
            .css('color', matches ? '#15803d' : '#b91c1c');
        $form.find('.mix-split-submit').prop('disabled', !matches);
    });
});
</script>
</body>
</html>
