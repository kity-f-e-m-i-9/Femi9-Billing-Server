<?php
/**
 * Renders the #divToPrint markup shared by
 * super-stockist/tp-invoice-print.php (the logged-in Print page) and
 * super-stockist/tp-invoice-pdf.php (the no-login PDF endpoint used for
 * WhatsApp sharing). See SuperStockistTpInvoiceData.php for the business
 * logic, and ShopInvoiceHtml.php for the full rationale behind
 * $forPdf/$pdfScale/$pdfMarginMm.
 */
function render_superstockist_tp_invoice_html(array $ctx, bool $forPdf = false, float $pdfScale = 1.0, float $pdfMarginMm = 6.0): string {
    extract($ctx, EXTR_SKIP);

    $px = function (float $basePx) use ($pdfScale): string {
        return round($basePx * $pdfScale, 1) . 'px';
    };

    ob_start();
    ?>
<style type="text/css">
.maincontainar{width:100%;height:auto;border:1px solid #000;box-sizing:border-box;}
.maincontainar hr{border-bottom:1px solid #000;}
#toptl{width:100%;padding:5px;font-family:arial,"DejaVu Sans";font-weight:bold;border-bottom:1px solid #000;text-align:center;font-size:22px;}
.second_containar{width:100%;}
#second_topvl{width:100%;padding:5px;font-family:arial,"DejaVu Sans";border-bottom:1px solid #000;border-collapse:collapse;}
#second_topvl td{padding:5px;}
#border_nbottom td{border-bottom:1px solid #000;}
.second_containar{width:100%;border-collapse:collapse;}
.second_containar td:nth-child(1){border-right:1px solid #000;padding:0px;}
#noneborder td{border:0px !important;font-family:arial,"DejaVu Sans";font-size:14px;line-height:20px;}
.item_list{width:100%;border-top:1px solid #000;border-collapse:collapse;font-family:arial,"DejaVu Sans";}
.item_list td{border-right:1px solid #000;padding:5px;font-size:14px;vertical-align:top;}
.item_list td:last-child{border-right:0;padding-right:8px;}
#bordervl td{border-bottom:1px solid #000;padding:5px;}
#rightlaign{text-align:right;}
#bottombordervl{border-top:1px solid #000;border-bottom:1px solid #000;}
.amount_word{font-family:arial,"DejaVu Sans";padding:4px;border-bottom:1px solid #000;}
.amount_payable{font-family:arial,"DejaVu Sans";padding:4px;border-bottom:1px solid #000;text-align:right;}
#vlnotes{font-family:arial,"DejaVu Sans";width:100%;}
#vlnotes tr td:nth-child(1){border-right:1px solid #000;width:35%;}
#cmpname{font-size:17px;font-weight:bold;}
.cusdetaiis{margin-left:10px;font-family:arial,"DejaVu Sans";font-size:14px;line-height:20px;}
#shiippingaddress{margin-left:10px;font-family:arial,"DejaVu Sans";}
#pageno{font-family:arial,"DejaVu Sans";padding:20px 0px 20px 0px;}
#hsnsac{border-collapse:collapse;}
#hsnsac tr td{border:1px solid #000;}
#hsnsac tr td:nth-child(1){border-left:0px;}
#hsnsac tr td:nth-child(2){border-right:0px;}
#sealsign{border-collapse:collapse;}
#sealsign td{padding:3px;}
#sealsign tr:nth-child(1){border-top:1px solid #000;}
#sealsign tr td:nth-child(1){border-right:1px solid #000;}
<?php if ($forPdf): ?>
@page { margin: <?php echo $pdfMarginMm; ?>mm; }
.item_list td{font-size:<?php echo $px(11); ?>;padding:<?php echo $px(3); ?>;}
.item_list td:last-child{padding-right:<?php echo $px(6); ?>;}
#noneborder td{font-size:<?php echo $px(12); ?>;line-height:<?php echo $px(16); ?>;}
.cusdetaiis{font-size:<?php echo $px(12); ?>;line-height:<?php echo $px(16); ?>;margin:6px 0;}
#second_topvl td{padding:<?php echo $px(3); ?>;font-size:<?php echo $px(12); ?>;}
#hsnsac{font-size:<?php echo $px(11); ?>;}
#hsnsac td{padding:<?php echo $px(2); ?> <?php echo $px(4); ?>;}
#sealsign td{padding:<?php echo $px(2); ?>;font-size:<?php echo $px(12); ?>;}
#toptl{font-size:<?php echo $px(17); ?>;padding:<?php echo $px(3); ?>;}
.amount_word,.amount_payable{padding:<?php echo $px(2); ?>;font-size:<?php echo $px(12); ?>;}
#second_topvl td[height]{height:<?php echo $px(24); ?> !important;}
#shiippingaddress{margin:4px 0;font-size:<?php echo $px(12); ?>;}
hr{margin:3px 0;}
#divToPrint table{margin:0;}
<?php endif; ?>
<?php if (!$forPdf): ?>
@media print {
    @page { size: A4; margin: 6mm; }
    body * { visibility: hidden; }
    #divToPrint, #divToPrint * { visibility: visible; }
    #divToPrint { position: absolute; left: 0; top: 0; width: 100%; }
    .maincontainar { width: 100% !important; min-width: 0 !important; }

    .item_list td{font-size:11px;padding:3px;}
    .item_list td:last-child{padding-right:6px;}
    #noneborder td{font-size:12px;line-height:16px;}
    .cusdetaiis{font-size:12px;line-height:16px;margin:6px 0;}
    #second_topvl td{padding:3px;font-size:12px;}
    #hsnsac{font-size:11px;}
    #hsnsac td{padding:2px 4px;}
    #sealsign td{padding:2px;font-size:12px;}
    #toptl{font-size:17px;padding:3px;}
    .amount_word,.amount_payable{padding:2px;font-size:12px;}
    #second_topvl td[height]{height:24px !important;}
    #shiippingaddress{margin:4px 0;font-size:12px;}
    hr{margin:3px 0;}
    #divToPrint table{margin:0;}
}
<?php endif; ?>
</style>

<div class="maincontainar">

<table id="toptl">
<tr><td><?php echo $invoice_heading; ?></td></tr>
</table>

<table class="second_containar">
<tr valign="top">
<td width="50%">
<table id="noneborder">
<tr valign="top">
<td valign="top">
<span id="cmpname"><?php echo htmlspecialchars($result_Seller['name'] ?? ''); ?></span><br/>
<?php echo htmlspecialchars($result_Seller['address'] ?? ''); ?><br/>
<?php if (!empty($result_Seller['gstin'])): ?><b>GSTIN/UIN :</b> <?php echo htmlspecialchars($result_Seller['gstin']); ?><br/><?php endif; ?>
<b>Contact</b> : <?php echo htmlspecialchars($result_Seller['mobile_number'] ?? ''); ?><br/>
<b>Email</b> : <?php echo htmlspecialchars($result_Seller['email'] ?? ''); ?>
</td>
</tr>
</table>
<hr/>

<?php
$d = $result_Invoice_Details;
$useCustomDelivery = empty($d['use_default_delivery_address']) && !empty($d['custom_delivery_line1']);
$delivery_parts = $useCustomDelivery ? array_filter([
    $d['custom_delivery_line1'],
    $d['custom_delivery_line2'],
    implode(', ', array_filter([$d['custom_delivery_city'], $d['custom_delivery_district']])),
    implode(', ', array_filter([$d['custom_delivery_state'], $d['custom_delivery_country']])),
]) : array_filter([
    $d['delivery_line1'],
    $d['delivery_line2'],
    implode(', ', array_filter([$d['delivery_city'], $d['delivery_district']])),
    implode(', ', array_filter([$d['delivery_state'], $d['delivery_country']])),
]);
$branch_parts = array_filter([
    $d['branch_line1'],
    $d['branch_line2'],
    implode(', ', array_filter([$d['branch_city'], $d['branch_district']])),
    implode(', ', array_filter([$d['branch_state'], $d['branch_country']])),
]);
?>
<p class="cusdetaiis">
Consignee (Ship to):<br/>
<?php if (!empty($d['tp_company_name'])): ?><b><?php echo htmlspecialchars($d['tp_company_name']); ?></b><br/><?php endif; ?>
<?php echo htmlspecialchars($d['tp_name']); ?><br/>
<?php if (!empty($d['tp_gstin'])): ?>GSTIN: <?php echo htmlspecialchars($d['tp_gstin']); ?><br/><?php endif; ?>
Mobile:&nbsp;<?php echo htmlspecialchars($d['tp_mobile']); ?><br/>
<?php echo implode('<br/>', array_map('htmlspecialchars', $delivery_parts)); ?>
</p>

<hr/>
<p class="cusdetaiis">
Buyer (Bill to):<br/>
<?php if (!empty($d['tp_company_name'])): ?><b><?php echo htmlspecialchars($d['tp_company_name']); ?></b><br/><?php endif; ?>
<?php echo htmlspecialchars($d['tp_name']); ?><br/>
<?php if (!empty($d['tp_gstin'])): ?>GSTIN: <?php echo htmlspecialchars($d['tp_gstin']); ?><br/><?php endif; ?>
Mobile:&nbsp;<?php echo htmlspecialchars($d['tp_mobile']); ?><br/>
<?php echo implode('<br/>', array_map('htmlspecialchars', $branch_parts)); ?>
</p>
</td>

<td valign="top">
<table id="second_topvl">
<tr id="border_nbottom">
<td>Invoice #<br/><b><?php echo htmlspecialchars($result_Invoice_Details['invoice_number']); ?></b> <?php $_invType = tpResolveProductType($result_Invoice_Details['product_type'] ?? null); ?><span style="font-size:10px;font-weight:700;padding:1px 6px;border-radius:8px;background:#eee;border:1px solid #ccc;"><?php echo htmlspecialchars(tpProductTypeLabel($_invType)); ?></span></td>
<td>Invoice Date:<br/><b><?php echo date("d M Y", strtotime($result_Invoice_Details['invoice_date'])); ?></b></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Delivery Note<br/>&nbsp;</td>
<td>Mode/Terms of Payment<br/><b>Advance Payment</b></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Reference No. &amp; Date<br/>&nbsp;</td>
<td>Other References<br/>&nbsp;</td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Buyer's Order No.<br/>&nbsp;</td>
<td>Dated<br/>&nbsp;</td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Dispatch Doc No.<br/>&nbsp;</td>
<td>Delivery Note Date<br/>&nbsp;</td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Dispatched through<br/>&nbsp;</td>
<td>Destination<br/>&nbsp;</td>
</tr>
</table>
<p id="shiippingaddress">
Terms of Delivery<br/>&nbsp;
</p>
</td>
</tr>
</table>

<table class="item_list">
<tr id="bordervl">
<td>Sl No.</td>
<td>Description of Goods</td>
<td id="rightlaign">HSN/SAC</td>
<td id="rightlaign">Quantity</td>
<td id="rightlaign">MRP</td>
<td id="rightlaign">Rate (Excl. Tax)</td>
<td id="rightlaign">Rate (Incl. Tax)</td>
<td id="rightlaign">per</td>
<td id="rightlaign">GST(%)</td>
<td id="rightlaign">Disc</td>
<td id="rightlaign">Amount</td>
</tr>

<?php $invno = 0; foreach ($invoice_items as $item):
    $invno++;
    $qty           = (int)$item['quantity'];
    $gst_pct       = (int)$item['gst_percentage'];
    $mrp           = (float)$item['mrp'];
    $taxable_value = $item['taxable_value'];
?>
<tr>
<td><?php echo $invno; ?></td>
<td><b><?php echo htmlspecialchars($item['productName']); ?></b></td>
<td id="rightlaign"><?php echo htmlspecialchars($item['hsn']); ?></td>
<td id="rightlaign"><?php echo inr_format($qty, 0); ?> Packs</td>
<td id="rightlaign"><?php echo inr_format($mrp, 2); ?></td>
<td id="rightlaign"><?php echo inr_format($item['taxable_rate'], 2); ?></td>
<td id="rightlaign"><?php echo inr_format($item['taxable_rate_incl'], 2); ?></td>
<td id="rightlaign">Packs</td>
<td id="rightlaign"><?php echo $gst_pct; ?>%</td>
<td id="rightlaign">0.00<br/>(0%)</td>
<td id="rightlaign"><?php echo inr_format($taxable_value, 2); ?></td>
</tr>
<?php endforeach; ?>

<tr>
<td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo inr_format($Totalquantity123, 0); ?> Packs</b></td>
<td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($TotalAMount123, 2); ?></b></td>
</tr>

<?php if ($discount_amount > 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Discount</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b>&minus;<?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($discount_amount, 2); ?></b></td>
</tr>
<?php endif; ?>
<?php if ($totalgstamount > 0):
    $SGST = inr_format($totalgstamount / 2, 2);
    $CGST = inr_format($totalgstamount / 2, 2);
?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>SGST</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo $SGST; ?></b></td>
</tr>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>CGST</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo $CGST; ?></b></td>
</tr>
<?php endif; ?>

<?php if ($courier_charges > 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Courier Charges</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($courier_charges, 2); ?></b></td>
</tr>
<?php endif; ?>

<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Total</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($grand_total, 2); ?></b></td>
</tr>
</table>
<div style="clear:both;"></div>

<table width="100%">
<tr>
<td width="70%">Amount Chargeable (in words)</td>
<td align="right">E. &amp; O.E</td>
</tr>
<tr>
<td><b><?php echo $Currency_Name; ?> <?php echo ucwords($result); ?> Only</b></td>
<td></td>
</tr>
</table>

<table width="100%" id="hsnsac">
<tr>
<td width="70%" align="center">HSN/SAC</td>
<td align="right">Taxable Value</td>
</tr>
<?php foreach ($hsn_totals as $hsncode => $hsnamt): ?>
<tr>
<td><?php echo htmlspecialchars($hsncode); ?></td>
<td align="right"><?php echo inr_format($hsnamt, 2); ?></td>
</tr>
<?php endforeach; ?>
<tr>
<td align="right"><b>Total&nbsp;</b></td>
<td align="right"><b><?php echo inr_format($TotalAMount123, 2); ?></b></td>
</tr>
</table>

<table width="100%">
<tr>
<td width="100%">
<?php if ($totalgstamount > 0): ?>
<div>&nbsp;Tax Amount (in words): <b><?php echo $Currency_Name; ?> <?php echo ucwords($TAXresult); ?> Only</b></div>
<?php else: ?>
<div>&nbsp;Tax Amount (in words): <b>Nil</b></div>
<?php endif; ?>

<br/>
<div style="width:99%;margin:0 auto;"><u>Declaration:</u><br/>We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</div>
</td>
</tr>
</table>

<table width="100%" id="sealsign">
<tr>
<td width="50%" align="left">Territory Partner's Seal and Signature</td>
<td align="right">for <b><?php echo htmlspecialchars($result_Seller['name'] ?? ''); ?></b></td>
</tr>
<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr>
<td></td>
<td align="right">Authorised Signatory</td>
</tr>
</table>
<div style="clear:both;"></div>
</div>
<div align="center">
    This is a Computer Generated Invoice
</div>
<?php
    return ob_get_clean();
}
