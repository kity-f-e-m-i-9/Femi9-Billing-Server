<?php
include("checksession.php");
error_reporting(0);

$enc_id = $_GET['id'] ?? '';
$inv_id = (int)base64_decode($enc_id);
if (!$inv_id) { header("Location: manage-tp-invoices"); exit; }

// Ownership check via TP
$stmt = $db_conn->prepare("
    SELECT tpi.*, tp.name AS tp_name, tp.tp_id AS tp_code, tp.mobile AS tp_mobile,
           tp.email AS tp_email, tp.company_name AS tp_company,
           tp.delivery_line1, tp.delivery_line2, tp.delivery_city,
           tp.delivery_district, tp.delivery_state, tp.delivery_pincode
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

// SS details
$ss = $result_LoGuserDtails;
$subtotal = array_sum(array_column($items, 'amount'));
$courier  = (float)($inv['courier_charges'] ?? 0);
$discount = (float)($inv['discount_amount'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice <?php echo htmlspecialchars($inv['invoice_number']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; font-size: 13px; color: #1e293b; background: #fff; padding: 20px; }
        .invoice-wrapper { max-width: 800px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .invoice-header { background: #1e293b; color: #fff; padding: 24px 30px; display: flex; justify-content: space-between; align-items: flex-start; }
        .invoice-header h1 { font-size: 22px; font-weight: 700; letter-spacing: 1px; }
        .invoice-header .inv-meta { text-align: right; }
        .invoice-header .inv-meta h2 { font-size: 18px; font-weight: 700; color: #60a5fa; }
        .invoice-header .inv-meta p { font-size: 12px; color: #94a3b8; margin-top: 4px; }
        .section-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
        .address-block { padding: 20px 30px; border-bottom: 1px solid #f1f5f9; }
        .address-block:first-child { border-right: 1px solid #f1f5f9; }
        .address-block .label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 8px; }
        .address-block h3 { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
        .address-block p { font-size: 12px; color: #475569; line-height: 1.6; }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table thead { background: #f8fafc; }
        .items-table thead th { padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border-bottom: 2px solid #e5e7eb; }
        .items-table thead th.text-right { text-align: right; }
        .items-table tbody td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .items-table tbody td.text-right { text-align: right; }
        .items-table tbody tr:hover { background: #f8fafc; }
        .totals-section { padding: 16px 30px; background: #f8fafc; }
        .totals-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; }
        .totals-row.grand-total { font-size: 16px; font-weight: 700; border-top: 2px solid #e5e7eb; padding-top: 12px; margin-top: 4px; color: #1a237e; }
        .footer { padding: 16px 30px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 11px; color: #94a3b8; }
        @media print {
            body { padding: 0; }
            .invoice-wrapper { border: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
<div class="no-print" style="margin-bottom:16px;">
    <button onclick="window.print()" style="background:#1e293b;color:#fff;border:none;padding:8px 18px;border-radius:6px;font-size:13px;cursor:pointer;font-family:'Poppins',sans-serif;">
        🖨️ Print / Save as PDF
    </button>
    <a href="view-tp-invoice?id=<?php echo $enc_id; ?>" style="margin-left:10px;color:#667eea;font-size:13px;text-decoration:none;">← Back</a>
</div>

<div class="invoice-wrapper">
    <div class="invoice-header">
        <div>
            <h1><?php echo htmlspecialchars($business_name); ?></h1>
            <?php if ($ss): ?>
                <p style="font-size:12px;color:#94a3b8;margin-top:6px;">
                    <?php echo htmlspecialchars($ss['name'] ?? ''); ?><br>
                    <?php echo htmlspecialchars($ss['mobile_number'] ?? ''); ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="inv-meta">
            <h2><?php echo htmlspecialchars($inv['invoice_number']); ?></h2>
            <p>Date: <?php echo date('d M Y', strtotime($inv['invoice_date'])); ?></p>
        </div>
    </div>

    <div class="section-grid">
        <div class="address-block">
            <div class="label">Bill To</div>
            <h3><?php echo htmlspecialchars($inv['tp_name']); ?></h3>
            <?php if ($inv['tp_company']): ?>
                <p><?php echo htmlspecialchars($inv['tp_company']); ?></p>
            <?php endif; ?>
            <?php
            $del_parts = array_filter([
                $inv['delivery_line1'],
                $inv['delivery_line2'],
                $inv['delivery_city'],
                $inv['delivery_district'],
                $inv['delivery_state'],
                $inv['delivery_pincode'],
            ]);
            if ($del_parts): ?>
                <p><?php echo htmlspecialchars(implode(', ', $del_parts)); ?></p>
            <?php endif; ?>
            <p>📞 <?php echo htmlspecialchars($inv['tp_mobile']); ?></p>
        </div>
        <div class="address-block">
            <div class="label">Invoice Info</div>
            <h3><?php echo htmlspecialchars($inv['invoice_number']); ?></h3>
            <p>Date: <?php echo date('d M Y', strtotime($inv['invoice_date'])); ?><br>
            TP ID: <strong><?php echo htmlspecialchars($inv['tp_code']); ?></strong></p>
        </div>
    </div>

    <table class="items-table">
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
                <td style="color:#64748b;"><?php echo htmlspecialchars($item['hsn'] ?? ''); ?></td>
                <td class="text-right"><?php echo (int)$item['quantity']; ?></td>
                <td class="text-right">₹<?php echo inr_format((float)$item['rate'], 2); ?></td>
                <td class="text-right"><strong>₹<?php echo inr_format((float)$item['amount'], 2); ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals-section">
        <div class="totals-row">
            <span style="color:#64748b;">Subtotal</span>
            <span>₹<?php echo inr_format($subtotal, 2); ?></span>
        </div>
        <?php if ($discount > 0): ?>
        <div class="totals-row">
            <span style="color:#64748b;">Discount</span>
            <span style="color:#10b981;">−₹<?php echo inr_format($discount, 2); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($courier > 0): ?>
        <div class="totals-row">
            <span style="color:#64748b;">Courier Charges</span>
            <span>₹<?php echo inr_format($courier, 2); ?></span>
        </div>
        <?php endif; ?>
        <div class="totals-row grand-total">
            <span>Grand Total</span>
            <span>₹<?php echo inr_format((float)$inv['total_amount'], 2); ?></span>
        </div>
    </div>

    <div class="footer">
        This is a computer-generated invoice. No signature required.
    </div>
</div>
</body>
</html>
