<?php
/**
 * Renders the #divToPrint markup shared by stockist/user-invoice-print.php
 * (the logged-in Print page) and stockist/user-invoice-pdf.php (the
 * no-login PDF endpoint used for WhatsApp sharing). See
 * StockistUserInvoiceData.php for the business logic, and
 * ShopInvoiceHtml.php for the full rationale behind
 * $forPdf/$pdfScale/$pdfMarginMm.
 *
 * Single "Rate" column (not Excl./Incl. Tax split) and per-item GST% is
 * the row's own stored value — kept exactly as the original page rendered
 * it, see StockistUserInvoiceData.php's own note.
 */
function render_stockist_user_invoice_html(array $ctx, bool $forPdf = false, float $pdfScale = 1.0, float $pdfMarginMm = 6.0): string {
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
<tr><td><?php echo htmlspecialchars($invoice_heading); ?></td></tr>
</table>

<table class="second_containar">
<tr valign="top">
<td width="50%">
<table id="noneborder">
<tr valign="top">
<td>
<?php if (!empty($result_UserProfiles['logo'])): ?>
<img src="<?php echo htmlspecialchars($result_UserProfiles['logo']); ?>" style="width:95px;border-radius:10px;"/>
<?php endif; ?>
</td>
<td valign="top">
<span id="cmpname"><?php echo htmlspecialchars($result_UserProfiles['companyname'] ?? ''); ?></span><br/>
<?php echo htmlspecialchars($business_address); ?><br/>
<b>GSTIN/UIN:</b> <?php echo htmlspecialchars($result_UserdETAILS['gstin'] ?? ''); ?><br/>
<b>State Name:</b> <?php echo htmlspecialchars($state_nameINV); ?><br/>
<b>Contact:</b> <?php echo htmlspecialchars($result_UserdETAILS['mobile_number'] ?? ''); ?><br/>
<b>Email:</b> <?php echo htmlspecialchars($result_UserdETAILS['email'] ?? ''); ?><br/>
</td>
</tr>
</table>
<hr/>

<p class="cusdetaiis">
<b>Consignee (Ship to):</b><br/>
<b><?php echo htmlspecialchars($result_userprofile['companyname'] ?? ''); ?></b><br/>
GSTIN: <?php echo htmlspecialchars($result_Customer_Details['gstin'] ?? ''); ?><br/>
Mobile: <?php echo htmlspecialchars($result_Customer_Details['mobile_number'] ?? ''); ?><br/>
<?php echo htmlspecialchars($deliveryaddress); ?>
</p>

<hr/>
<p class="cusdetaiis">
<b>Buyer (Bill to):</b><br/>
<b><?php echo htmlspecialchars($result_userprofile['companyname'] ?? ''); ?></b><br/>
GSTIN: <?php echo htmlspecialchars($result_Customer_Details['gstin'] ?? ''); ?><br/>
Mobile: <?php echo htmlspecialchars($result_Customer_Details['mobile_number'] ?? ''); ?><br/>
<?php echo htmlspecialchars($buyer_business_address); ?><br/>
State: <?php echo htmlspecialchars($state_name); ?>, District: <?php echo htmlspecialchars($district_name); ?>
</p>

</td>
<td valign="top">
<table id="second_topvl">
<tr id="border_nbottom">
<td>Invoice #<br/><b><?php echo htmlspecialchars($inv['inv_number'] ?? ''); ?></b></td>
<td>Invoice Date:<br/><b><?php echo date("d M Y", strtotime($inv['date'] ?? '')); ?></b></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Delivery Note<br/><?php echo htmlspecialchars($Result_DLDetails['dl_note'] ?? ''); ?></td>
<td>Mode/Terms of Payment<br/><?php echo htmlspecialchars($Result_DLDetails['mode_pmnt'] ?? ''); ?></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Reference No. &amp; Date<br/><?php if (!empty($Result_DLDetails['ref_no'])) { echo htmlspecialchars($Result_DLDetails['ref_no']); ?>, <?php } if (!empty($Result_DLDetails['ref_date'])) { echo date("d/m/Y", strtotime($Result_DLDetails['ref_date'])); } ?></td>
<td>Other References<br/><?php echo htmlspecialchars($Result_DLDetails['ot_ref'] ?? ''); ?></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Buyer's Order No.<br/><?php echo htmlspecialchars($Result_DLDetails['order_no'] ?? ''); ?></td>
<td>Dated<br/><?php if (!empty($Result_DLDetails['dated'])) { echo date("d/m/Y", strtotime($Result_DLDetails['dated'])); } ?></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Dispatch Doc No.<br/><?php echo htmlspecialchars($Result_DLDetails['dispatch_doc_no'] ?? ''); ?></td>
<td>Delivery Note Date<br/><?php if (!empty($Result_DLDetails['dlnote_date'])) { echo date("d/m/Y", strtotime($Result_DLDetails['dlnote_date'])); } ?></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Dispatched through<br/><?php echo htmlspecialchars($Result_DLDetails['dispatch_through'] ?? ''); ?></td>
<td>Destination<br/><?php echo htmlspecialchars($Result_DLDetails['destination'] ?? ''); ?></td>
</tr>
</table>
<p id="shiippingaddress">
Terms of Delivery<br/>
<?php echo htmlspecialchars($Result_DLDetails['terms'] ?? ''); ?>
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
<td id="rightlaign">Rate</td>
<td id="rightlaign">per</td>
<td id="rightlaign">GST(%)</td>
<td id="rightlaign">Disc</td>
<td id="rightlaign">Amount</td>
</tr>

<?php $invno = 0; foreach ($invoice_items as $item):
    $qty = (float)$item['qty'];
?>
<tr>
<td><?php echo ++$invno; ?></td>
<td><b><?php echo htmlspecialchars($item['productName']); ?></b></td>
<td id="rightlaign"><?php echo htmlspecialchars($item['p_hsn']); ?></td>
<td id="rightlaign"><?php echo $qty; ?> Packs</td>
<td id="rightlaign"><?php echo inr_format($item['p_mrp'], 2); ?></td>
<td id="rightlaign"><?php echo inr_format($item['amount'], 2); ?></td>
<td id="rightlaign">Packs</td>
<td id="rightlaign"><?php echo $item['gst_percentage']; ?>%</td>
<td id="rightlaign"><?php echo inr_format($item['discount_amount'], 2); ?> (<?php echo inr_format($item['discount_percentage'], 0); ?>%)</td>
<td id="rightlaign"><?php echo inr_format($item['line_total'], 2); ?></td>
</tr>
<?php endforeach; ?>

<tr>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td></td>
</tr>

<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i></i></b></td>
<td></td>
<td id="rightlaign"><b><?php echo $Totalquantity123; ?> Packs</b></td>
<td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($TotalAMount123, 2); ?></b></td>
</tr>

<?php if ($totalgstamount > 0):
    if ($gsttype == "inner"):
        $SGST = inr_format($totalgstamount / 2, 2);
        $CGST = inr_format($totalgstamount / 2, 2);
?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>SGST</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo $SGST; ?></b></td>
</tr>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>CGST</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo $CGST; ?></b></td>
</tr>
<?php else: ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>IGST</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($totalgstamount, 2); ?></b></td>
</tr>
<?php endif; endif; ?>

<?php if ($discountamount > 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Discount</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($discountamount, 2); ?></b></td>
</tr>
<?php endif; ?>

<?php if (!empty($inv['roundoff']) && $inv['roundoff'] != 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Round off</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($inv['roundoff'], 2); ?></b></td>
</tr>
<?php endif; ?>

<?php if (!empty($inv['courier_charges']) && $inv['courier_charges'] != 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Courier Charges</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($inv['courier_charges'], 2); ?></b></td>
</tr>
<?php endif; ?>

<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Total</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($inv['total'] ?? 0, 2); ?></b></td>
</tr>
</table>
<div style="clear:both;"></div>

<table width="100%">
<tr>
<td width="70%">Amount Chargeable (in words)</td>
<td align="right">E. &amp; O.E</td>
</tr>
<tr>
<td><b><?php echo $Currency_Name; ?>&nbsp;<?php echo ucwords($result); ?> Only</b></td>
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
<td align="right"><b><?php echo inr_format($hsn_grand_total, 2); ?></b></td>
</tr>
</table>

<?php if ($totalgstamount > 0): ?>
<div>&nbsp;Tax Amount (in words): <b><?php echo $Currency_Name; ?>&nbsp;<?php echo ucwords($TAXresult); ?> Only</b></div>
<?php else: ?>
<div>&nbsp;Tax Amount (in words): <b>Nil</b></div>
<?php endif; ?>

<br/>
<div style="width:99%;margin:0 auto;"><u>Declaration:</u><br/>We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</div>

<?php if (!empty($result_UserProfiles['acname'])): ?>
<table align="right">
<tr><td>A/c Name</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($result_UserProfiles['acname']); ?></td></tr>
<tr><td>A/c Number</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($result_UserProfiles['acnumber']); ?></td></tr>
<tr><td>Bank Name</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($result_UserProfiles['bankname']); ?></td></tr>
<tr><td>Branch Name</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($result_UserProfiles['branchname']); ?></td></tr>
<tr><td>IFS Code</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($result_UserProfiles['ifsc']); ?></td></tr>
<tr><td>UPI Number</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($result_UserProfiles['upinumber']); ?></td></tr>
</table>
<?php endif; ?>

<table width="100%" id="sealsign">
<tr>
<td width="50%" align="left">Customer's Seal and Signature</td>
<td align="right">for <b><?php echo htmlspecialchars($result_UserProfiles['companyname'] ?? ''); ?></b></td>
</tr>
<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr>
<td></td>
<td align="right">Authorised Signatory</td>
</tr>
</table>
<div style="clear:both;"></div>
</div><!--maincontainar-->
<div align="center">This is a Computer Generated Invoice</div>
<?php
    return ob_get_clean();
}
