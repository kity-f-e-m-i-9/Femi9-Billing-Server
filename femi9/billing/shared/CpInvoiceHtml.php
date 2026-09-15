<?php
/**
 * Renders one CP invoice-cum-delivery-note as an HTML fragment. This single
 * document serves as both the tax invoice and the delivery note (spec:
 * "the invoice IS the delivery note — one document, one auto-generated
 * number"), so it carries pricing plus a goods-receipt signature block,
 * rather than being split into two separate printouts.
 */

function render_cp_invoice_html(array $invData): string {
    $inv = $invData['result_Invoice_Details'];
    $items = $invData['result_Items'];
    $business_name = $invData['business_name'];

    $delivery_line1 = $inv['use_default_delivery_address'] ? $inv['branch_line1'] : ($inv['custom_delivery_line1'] ?? '');
    $delivery_line2 = $inv['use_default_delivery_address'] ? $inv['branch_line2'] : ($inv['custom_delivery_line2'] ?? '');
    $delivery_city  = $inv['use_default_delivery_address'] ? $inv['branch_city']  : ($inv['custom_delivery_city'] ?? '');
    $delivery_state = $inv['use_default_delivery_address'] ? $inv['branch_state'] : ($inv['custom_delivery_state'] ?? '');
    $delivery_pin   = $inv['use_default_delivery_address'] ? $inv['branch_pincode'] : ($inv['custom_delivery_pincode'] ?? '');

    ob_start();
    ?>
    <div class="maincontainar" style="padding:20px;font-family:Arial,sans-serif;">
        <div style="text-align:center;margin-bottom:10px;">
            <h3 style="margin:0;"><?= htmlspecialchars($business_name) ?></h3>
            <h5 style="margin:4px 0;letter-spacing:1px;">TAX INVOICE CUM DELIVERY NOTE</h5>
        </div>
        <table width="100%" style="border-collapse:collapse;margin-bottom:12px;">
            <tr>
                <td style="width:50%;vertical-align:top;border:1px solid #ccc;padding:8px;">
                    <strong>Bill To / Deliver To:</strong><br>
                    <?= htmlspecialchars($inv['cp_name']) ?> (<?= htmlspecialchars($inv['cp_code']) ?>)<br>
                    <?= htmlspecialchars(implode(' ', array_filter([$delivery_line1, $delivery_line2]))) ?><br>
                    <?= htmlspecialchars(implode(' ', array_filter([$delivery_city, $delivery_state, $delivery_pin]))) ?><br>
                    <?php if (!empty($inv['cp_gstin'])): ?>GSTIN: <?= htmlspecialchars($inv['cp_gstin']) ?><br><?php endif; ?>
                    <?php if (!empty($inv['cp_mobile'])): ?>Mobile: <?= htmlspecialchars($inv['cp_mobile']) ?><?php endif; ?>
                </td>
                <td style="width:50%;vertical-align:top;border:1px solid #ccc;padding:8px;">
                    <strong>Invoice / Delivery Note No:</strong> <?= htmlspecialchars($inv['invoice_number']) ?><br>
                    <strong>Date:</strong> <?= htmlspecialchars(date('d-M-Y', strtotime($inv['invoice_date']) ?: 0)) ?><br>
                    <strong>Dispatched From:</strong> <?= htmlspecialchars($inv['godown_name'] ?? '-') ?>
                </td>
            </tr>
        </table>
        <table width="100%" style="border-collapse:collapse;">
            <thead>
                <tr style="background:#f0f0f0;">
                    <th style="border:1px solid #ccc;padding:6px;">#</th>
                    <th style="border:1px solid #ccc;padding:6px;">Product</th>
                    <th style="border:1px solid #ccc;padding:6px;">Qty</th>
                    <th style="border:1px solid #ccc;padding:6px;">Rate</th>
                    <th style="border:1px solid #ccc;padding:6px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td style="border:1px solid #ccc;padding:6px;text-align:center;"><?= $i + 1 ?></td>
                    <td style="border:1px solid #ccc;padding:6px;"><?= htmlspecialchars($item['productName']) ?></td>
                    <td style="border:1px solid #ccc;padding:6px;text-align:center;"><?= (int)$item['quantity'] ?></td>
                    <td style="border:1px solid #ccc;padding:6px;text-align:right;"><?= number_format((float)$item['rate'], 2) ?></td>
                    <td style="border:1px solid #ccc;padding:6px;text-align:right;"><?= number_format((float)$item['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="border:1px solid #ccc;padding:6px;text-align:right;"><strong>Total</strong></td>
                    <td style="border:1px solid #ccc;padding:6px;text-align:right;"><strong><?= number_format((float)$inv['total_amount'], 2) ?></strong></td>
                </tr>
            </tfoot>
        </table>
        <table width="100%" style="margin-top:40px;">
            <tr>
                <td style="width:50%;">Received the above goods in good condition.</td>
                <td style="width:50%;text-align:right;">For <?= htmlspecialchars($business_name) ?></td>
            </tr>
            <tr>
                <td style="padding-top:40px;">Receiver's Signature: ___________________</td>
                <td style="padding-top:40px;text-align:right;">Authorized Signatory</td>
            </tr>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
