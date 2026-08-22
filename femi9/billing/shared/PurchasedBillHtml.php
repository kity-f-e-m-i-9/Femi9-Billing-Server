<?php
/**
 * Renders the .maincontainar bill markup shared by purchased-bill-print.php
 * (the logged-in Print page, where the TP is the BUYER of goods from the
 * company/channel-partner) and purchased-bill-pdf.php (the no-login PDF
 * endpoint used for WhatsApp sharing) — both need byte-identical HTML so the
 * shared PDF looks exactly like what the TP already sees on screen.
 *
 * All business logic (DB queries, GST computation, totals) stays in
 * PurchasedBillData.php — this function only takes the already-computed
 * values and returns markup. It intentionally has no dependency on
 * session/login state, so it's safe to call from the no-login endpoint.
 *
 * $forPdf switches off nothing extra here (the original page had no mobile
 * @media rules — just an @media print block for the old popup-print flow,
 * which is likewise irrelevant to dompdf and skipped when $forPdf is true).
 *
 * Every font-family here is "arial,\"DejaVu Sans\"" — a fallback list, not
 * a switch to a different typeface. Real Arial has no ₹ (Rupee sign, U+20B9)
 * glyph at all, on any platform — that's not a dompdf limitation, browsers
 * hit the exact same gap and silently substitute a different installed
 * font for just that one character (standard font-fallback behavior),
 * which is invisible on screen but means the Print page was never
 * "pure Arial" to begin with. dompdf does the same substitution given the
 * same fallback list, so the PDF keeps Arial's (technically Helvetica's,
 * dompdf's built-in Arial-equivalent with matching metrics) character
 * widths for layout/wrapping — matching the Print page — and only drops
 * to DejaVu Sans for the handful of characters (₹) missing from it.
 */
function render_purchased_bill_html(array $ctx, bool $forPdf = false): string {
    extract($ctx, EXTR_SKIP);
    // Expected keys in $ctx: result_Invoice_Details, result_Godown,
    // invoice_items, TotalAMount123, Totalquantity123, totalgstamount,
    // hsn_totals, courier_charges, discount_amount, grand_total,
    // has_gst_product, invoice_heading, result (amount-in-words),
    // TAXresult (tax-amount-in-words), Currency_symbol, Currency_Name.

    ob_start();
    ?>
<style type="text/css">
/* box-sizing:border-box so .maincontainar's own 1px border sits INSIDE its
   declared 100% width instead of being added on top of it (the default
   content-box behavior). Without this, an inner width:100% table's own
   border-collapse right border lands a hair past where the outer div's
   border was meant to be, rendering as a faint doubled/misaligned line at
   the right edge — most visible where the invoice table runs long. */
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
/* Last column's own border-right sits flush against .maincontainar's outer
   border (both are 1px solid #000 with no gap between them), so right-
   aligned numeric content (Amount, GST%, etc.) reads as crowding/overflowing
   the page edge, especially once the table is captured at full desktop
   width for the WhatsApp PDF share. Drop the redundant inner border and give
   the last column a bit of breathing room instead of a border-on-border. */
.item_list td:last-child{border-right:0;padding-right:8px;}
#bordervl td{border-bottom:1px solid #000;padding:5px;}
#rightlaign{text-align:right;}
#bottombordervl{border-top:1px solid #000;border-bottom:1px solid #000;}
.amount_word{font-family:arial,"DejaVu Sans";padding:4px;border-bottom:1px solid #000;}
.amount_payable{font-family:arial,"DejaVu Sans";padding:4px;border-bottom:1px solid #000;text-align:right;}

#bottom_bank{font-family:arial,"DejaVu Sans";width:100%;border-bottom:1px solid #000;}
#bottom_bank tr td:nth-child(1){border-right:1px solid #000;}
#bottom_bank table td{border:0px !important;}

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

<?php if (!$forPdf): ?>
@media print {
    @page { margin: 0; size: auto; }
    body { margin: 10mm; }
}
<?php endif; ?>
</style>

<div class="maincontainar">

<table id="toptl">
<tr>
<td><?= $invoice_heading; ?></td>
</tr>
</table>

<!------INVOICE DETAILS----->
<table class="second_containar">
<tr valign="top">
<td width="50%">
<table id="noneborder">
<tr valign="top">
<td>
<?php if (empty($result_Invoice_Details['cp_gst_enabled']) && $result_Godown['logo'] != NULL): ?>
<img src="<?= $result_Godown['logo']; ?>" style="width:150px;margin-right:5px;"/>
<?php endif; ?>
</td>
<td valign="top">
<?php if (!empty($result_Invoice_Details['cp_gst_enabled'])): ?>
<?php
$cp = $result_Invoice_Details;
$cp_seller_parts = array_filter([
    $cp['cp_branch_line1'],
    $cp['cp_branch_line2'],
    implode(', ', array_filter([$cp['cp_branch_city'], $cp['cp_branch_district_full']])),
    implode(', ', array_filter([$cp['cp_branch_state'], $cp['cp_branch_country']])),
]);
?>
<span id="cmpname"><?= htmlspecialchars($result_Godown['gname']); ?></span><br/>
<?= implode('<br/>', array_map('htmlspecialchars', $cp_seller_parts)); ?><br/>
<?php if (!empty($result_Godown['gstin'])): ?><b>GSTIN/UIN :</b> <?= htmlspecialchars($result_Godown['gstin']); ?><br/><?php endif; ?>
<?php if (!empty($cp['cp_mobile'])): ?><b>Contact</b> : <?= htmlspecialchars($cp['cp_mobile']); ?><?php endif; ?>
<?php else: ?>
<span id="cmpname"><?= htmlspecialchars($result_Godown['gname']); ?></span><br/>
<?= htmlspecialchars($result_Godown['address_line1']); ?><br/>
<?= htmlspecialchars($result_Godown['address_line2']); ?><br/>
<b>GSTIN/UIN :</b> <?= htmlspecialchars($result_Godown['gstin']); ?><br/>
<b>State Name</b> : <?= htmlspecialchars($result_Godown['state']); ?> <b>Code</b> : <?= htmlspecialchars($result_Godown['state_code']); ?><br/>
<b>Contact</b> : <?= htmlspecialchars($result_Godown['contact']); ?><br/>
<b>Email</b> : <?= htmlspecialchars($result_Godown['email']); ?>
<?php endif; ?>
</td>
</tr>
</table>
<hr/>

<?php
$d = $result_Invoice_Details;
// Build delivery address lines — use the one-off address typed at PO time
// if the TP chose not to ship to their default registered address.
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
<?php if (!empty($d['tp_company_name'])): ?><b><?= htmlspecialchars($d['tp_company_name']); ?></b><br/><?php endif; ?>
<?= htmlspecialchars($d['tp_name']); ?><br/>
<?php if (!empty($d['tp_gstin'])): ?>GSTIN: <?= htmlspecialchars($d['tp_gstin']); ?><br/><?php endif; ?>
Mobile:&nbsp;<?= htmlspecialchars($d['tp_mobile']); ?><br/>
<?= implode('<br/>', array_map('htmlspecialchars', $delivery_parts)); ?>
</p>

<hr/>
<p class="cusdetaiis">
Buyer (Bill to):<br/>
<?php if (!empty($d['tp_company_name'])): ?><b><?= htmlspecialchars($d['tp_company_name']); ?></b><br/><?php endif; ?>
<?= htmlspecialchars($d['tp_name']); ?><br/>
<?php if (!empty($d['tp_gstin'])): ?>GSTIN: <?= htmlspecialchars($d['tp_gstin']); ?><br/><?php endif; ?>
Mobile:&nbsp;<?= htmlspecialchars($d['tp_mobile']); ?><br/>
<?= implode('<br/>', array_map('htmlspecialchars', $branch_parts)); ?>
</p>
</td>

<td valign="top">
<table id="second_topvl">
<tr id="border_nbottom">
<td>Invoice #<br/><b><?= htmlspecialchars($result_Invoice_Details['invoice_number']); ?></b></td>
<td>Invoice Date:<br/><b><?= date("d M Y", strtotime($result_Invoice_Details['invoice_date'])); ?></b></td>
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

<!------ITEM DETAILS----->
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
    $gst_type      = $item['gst_type'] ?? 'exclusive';
    $mrp           = (float)$item['mrp'];
    $taxable_value = $item['taxable_value'];
?>
<tr>
<td><?= $invno; ?></td>
<td><b><?= htmlspecialchars($item['productName']); ?></b></td>
<td id="rightlaign"><?= htmlspecialchars($item['hsn']); ?></td>
<td id="rightlaign"><?= inr_format($qty, 0); ?> Packs</td>
<td id="rightlaign"><?= inr_format($mrp, 2); ?></td>
<td id="rightlaign"><?= inr_format($item['taxable_rate'], 2); ?></td>
<td id="rightlaign"><?= inr_format($item['taxable_rate_incl'], 2); ?></td>
<td id="rightlaign">Packs</td>
<td id="rightlaign"><?= $gst_pct; ?>%</td>
<td id="rightlaign">0.00<br/>(0%)</td>
<td id="rightlaign"><?= inr_format($taxable_value, 2); ?></td>
</tr>
<?php endforeach; ?>

<tr>
<td></td><td></td><td></td>
<td id="rightlaign"><b><?= inr_format($Totalquantity123, 0); ?> Packs</b></td>
<td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= inr_format($TotalAMount123, 2); ?></b></td>
</tr>

<?php if ($discount_amount > 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Discount</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b>−<?= $Currency_symbol; ?>&nbsp;<?= inr_format($discount_amount, 2); ?></b></td>
</tr>
<?php endif; ?>
<?php if ($totalgstamount > 0):
    $SGST = inr_format($totalgstamount / 2, 2);
    $CGST = inr_format($totalgstamount / 2, 2);
?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>SGST</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= $SGST; ?></b></td>
</tr>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>CGST</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= $CGST; ?></b></td>
</tr>
<?php endif; ?>

<?php if ($courier_charges > 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Courier Charges</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= inr_format($courier_charges, 2); ?></b></td>
</tr>
<?php endif; ?>

<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Total</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= inr_format($grand_total, 2); ?></b></td>
</tr>
</table>
<div style="clear:both;"></div>

<table width="100%">
<tr>
<td width="70%">Amount Chargeable (in words)</td>
<td align="right">E. &amp; O.E</td>
</tr>
<tr>
<td><b><?= $Currency_Name; ?> <?= ucwords($result); ?> Only</b></td>
<td></td>
</tr>
</table>

<!---------------------HSN WISE TOTAL------------------------------>
<table width="100%" id="hsnsac">
<tr>
<td width="70%" align="center">HSN/SAC</td>
<td align="right">Taxable Value</td>
</tr>
<?php foreach ($hsn_totals as $hsncode => $hsnamt): ?>
<tr>
<td><?= htmlspecialchars($hsncode); ?></td>
<td align="right"><?= inr_format($hsnamt, 2); ?></td>
</tr>
<?php endforeach; ?>
<tr>
<td align="right"><b>Total&nbsp;</b></td>
<td align="right"><b><?= inr_format($TotalAMount123, 2); ?></b></td>
</tr>
</table>
<!---------------------HSN WISE TOTAL----END***------------------------->

<table width="100%">
<tr>
<td width="50%">
<?php if ($totalgstamount > 0): ?>
<div>&nbsp;Tax Amount (in words): <b><?= $Currency_Name; ?> <?= ucwords($TAXresult); ?> Only</b></div>
<?php else: ?>
<div>&nbsp;Tax Amount (in words): <b>Nil</b></div>
<?php endif; ?>

<br/>
<div style="width:99%;margin:0 auto;"><u>Declaration:</u><br/>We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</div>
</td>
<td>
<table align="right">
<tr><td>A/c Name</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['acname']); ?></td></tr>
<tr><td>A/c Number</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['acnumber']); ?></td></tr>
<tr><td>Bank Name</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['bankname']); ?></td></tr>
<tr><td>Branch Name</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['branchname']); ?></td></tr>
<tr><td>IFS Code</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['ifsc']); ?></td></tr>
<tr><td>UPI Number</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['upinumber']); ?></td></tr>
</table>
</td>
</tr>
</table>

<table width="100%" id="sealsign">
<tr>
<td width="50%" align="left">Territory Partner's Seal and Signature</td>
<td align="right">for <b><?= htmlspecialchars($result_Godown['gname']); ?></b></td>
</tr>
<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr>
<td></td>
<td align="right">Authorised Signatory</td>
</tr>
</table>
<div style="clear:both;"></div>
</div><!--maincontainar-->
<div align="center">
    This is a Computer Generated Invoice
    <?php if (!empty($result_Invoice_Details['cp_district'])): ?>
    &nbsp;|&nbsp; <?= htmlspecialchars($result_Invoice_Details['cp_district']); ?>
    <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}
