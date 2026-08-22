<?php
/**
 * Renders the #divToPrint invoice markup shared by customer-invoice-print.php
 * (the logged-in Print page) and customer-invoice-pdf.php (the no-login PDF
 * endpoint used for WhatsApp sharing) — both need byte-identical HTML so the
 * shared PDF looks exactly like what the TP already sees on screen.
 *
 * All business logic (DB queries, GST computation, totals) stays in
 * CustomerInvoiceData.php — this function only takes the already-computed
 * values and returns markup. It intentionally has no dependency on
 * session/login state, so it's safe to call from the no-login endpoint.
 *
 * $forPdf switches off the responsive/mobile CSS and print-specific
 * @media rules that only make sense inside a live browser tab — dompdf
 * doesn't evaluate @media queries the way a real browser does, and they'd
 * just be dead weight in the PDF's own <style> block.
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
function render_customer_invoice_html(array $ctx, bool $forPdf = false): string {
    extract($ctx, EXTR_SKIP);
    // Expected keys in $ctx: invoice_heading, profile, seller_display_name,
    // seller, buyer, inv, Result_DLDetails, invoice_items, Totalquantity123,
    // TotalAMount123, Currency_symbol, Currency_Name, gsttype,
    // totalgstamount, __inv_gst_pct, hsn_totals, hsn_gst_totals,
    // hsn_gst_pct, result (amount-in-words), TAXresult (tax-amount-in-words).

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

/* The invoice table has 11 columns and doesn't reflow — on a phone screen
   it just gets crushed/misaligned instead. Let it scroll horizontally at
   its natural size instead of shrinking, both on screen and when printed. */
@media (max-width: 768px) {
    .maincontainar { width: max-content; min-width: 100%; }
    #divToPrintScroll { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }

    /* Action buttons (Print / WhatsApp / New Invoice / Manage Invoice) were
       laid out in a <table align="right"> that doesn't wrap — buttons ran
       off the right edge of the screen. Wrap them and let them fill the
       width evenly instead. */
    #invoiceActionBar { justify-content: center !important; }
    #invoiceActionBar .btn { flex: 1 1 auto; min-width: 45%; margin: 3px !important; }

    /* Currency selector row sat flush right at a fixed 150px width, which
       pushed part of it off-screen next to the page's small margins. */
    #currencySelectWrap { text-align: center; }
    #currencySelectWrap select { width: 100% !important; max-width: 260px; }

    /* Seller/customer details vs. invoice meta panel is a rigid 50/50
       table row — on a phone this crushed both halves unreadably. Stack
       them full-width instead. */
    .second_containar, .second_containar tbody, .second_containar tr { display: block; width: 100%; }
    .second_containar td { display: block; width: 100% !important; box-sizing: border-box; }
    .second_containar td:nth-child(1) { border-right: 0; border-bottom: 1px solid #000; }

    /* Bank details / seal & signature blocks used align="right" tables
       sized to desktop content, causing them to overflow the viewport. */
    #bottom_bank, #bottom_bank table, #sealsign { width: 100% !important; }
    table[align="right"] { width: 100%; }
}
/* Printing the page directly (window.print()) instead of a popup — hide
   everything except the invoice itself, on any screen size. */
@media print {
    body * { visibility: hidden; }
    #divToPrint, #divToPrint * { visibility: visible; }
    #divToPrint { position: absolute; left: 0; top: 0; width: 100%; }
    #divToPrintScroll { overflow: visible !important; }
    .maincontainar { width: 100% !important; min-width: 0 !important; }
}
<?php endif; ?>
</style>

<div id="divToPrintScroll">
<div class="maincontainar">

<table id="toptl">
<tr><td><?php echo $invoice_heading; ?></td></tr>
</table>

<!------INVOICE DETAILS----->
<table class="second_containar">
<tr valign="top">
<td width="50%">
<table id="noneborder">
<tr valign="top">
<td>
<?php if (!empty($profile['logo'])): ?>
<img src="bussiness_logo/<?php echo htmlspecialchars($profile['logo']); ?>" style="width:150px;margin-right:5px;"/>
<?php endif; ?>
</td>
<td valign="top">
<span id="cmpname"><?php echo htmlspecialchars($seller_display_name); ?></span><br/>
<?php echo htmlspecialchars($seller['branch_line1'] ?? ''); ?><?php if (!empty($seller['branch_line2'])) echo '<br/>' . htmlspecialchars($seller['branch_line2']); ?><br/>
<?php echo htmlspecialchars($seller['branch_city'] ?? ''); ?><?php if (!empty($seller['branch_state'])) echo ', ' . htmlspecialchars($seller['branch_state']); ?><?php if (!empty($seller['branch_pincode'])) echo ' - ' . htmlspecialchars($seller['branch_pincode']); ?><br/>
<b>GSTIN/UIN :</b> <?php echo htmlspecialchars($seller['gstin'] ?? ''); ?><br/>
<b>Contact</b> : <?php echo htmlspecialchars($seller['mobile'] ?? ''); ?><br/>
<b>Email</b> : <?php echo htmlspecialchars($seller['email'] ?? ''); ?>
</td>
</tr>
</table>
<hr/>
<?php if ($buyer): ?>
<p class="cusdetaiis">
Consignee (Ship to):<br/>
<b><?php echo htmlspecialchars(ucwords($buyer['name'])); ?></b><br/>
GSTIN: <?php echo htmlspecialchars($buyer['gstin'] ?? ''); ?><br/>
Mobile:&nbsp;<?php echo htmlspecialchars($buyer['mobile']); ?><br/>
<?php echo htmlspecialchars($buyer['address'] ?? ''); ?>
</p>
<hr/>
<p class="cusdetaiis">
Buyer (Bill to):<br/>
<b><?php echo htmlspecialchars(ucwords($buyer['name'])); ?></b><br/>
GSTIN: <?php echo htmlspecialchars($buyer['gstin'] ?? ''); ?><br/>
Mobile:&nbsp;<?php echo htmlspecialchars($buyer['mobile']); ?><br/>
<?php echo htmlspecialchars($buyer['address'] ?? ''); ?>
</p>
<?php else: ?>
<p class="cusdetaiis">Consignee (Ship to):<br/><b>Walking Customer</b></p>
<hr/>
<p class="cusdetaiis">Buyer (Bill to):<br/><b>Walking Customer</b></p>
<?php endif; ?>
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

<?php $invno = 0; foreach ($invoice_items as $result_INVProductDetails):
    $qty = (int)$result_INVProductDetails['qty'];
    $discountamount_show     = inr_format($result_INVProductDetails['discount_amount'], 2);
    $discountpercentage_show = fmt_gst_pct($result_INVProductDetails['discount_percentage']);
?>
<tr>
<td><?php echo ++$invno; ?></td>
<td><b><?php echo htmlspecialchars($result_INVProductDetails['productName']); ?></b></td>
<td id="rightlaign"><?php echo htmlspecialchars($result_INVProductDetails['p_hsn']); ?></td>
<td id="rightlaign"><?php echo $qty; ?> Packs</td>
<td id="rightlaign"><?php echo inr_format($result_INVProductDetails['p_mrp'], 2); ?></td>
<td id="rightlaign"><?php echo inr_format($result_INVProductDetails['taxable_rate'], 2); ?></td>
<td id="rightlaign"><?php echo inr_format($result_INVProductDetails['taxable_rate_incl'], 2); ?></td>
<td id="rightlaign">Packs</td>
<td id="rightlaign"><?php echo $result_INVProductDetails['gst_pct']; ?>%</td>
<td id="rightlaign"><?php echo $discountamount_show; ?> (<?php echo $discountpercentage_show; ?>%)</td>
<td id="rightlaign"><?php echo inr_format($result_INVProductDetails['taxable_value'], 2); ?></td>
</tr>
<?php endforeach; ?>

<tr>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
</tr>

<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i></i></b></td>
<td></td>
<td id="rightlaign"><b><?php echo $Totalquantity123; ?> Packs</b></td>
<td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($TotalAMount123, 2); ?></b></td>
</tr>

<?php
$__half_pct = fmt_gst_pct($__inv_gst_pct / 2);

if ($totalgstamount > 0) {
    if ($gsttype == "inner") {
        $SGST = inr_format($totalgstamount / 2, 2);
        $CGST = inr_format($totalgstamount / 2, 2);
?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>SGST (<?php echo $__half_pct; ?>%)</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo $SGST; ?></b></td>
</tr>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>CGST (<?php echo $__half_pct; ?>%)</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo $CGST; ?></b></td>
</tr>
<?php } else { ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>IGST (<?php echo fmt_gst_pct($__inv_gst_pct); ?>%)</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($totalgstamount, 2); ?></b></td>
</tr>
<?php } } ?>

<?php if (!empty($inv['discount']) && $inv['discount'] > 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Discount</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($inv['discount'], 2); ?></b></td>
</tr>
<?php endif; ?>

<?php if (!empty($inv['roundoff']) && $inv['roundoff'] != 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Round off</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($inv['roundoff'], 2); ?></b></td>
</tr>
<?php endif; ?>

<?php if (!empty($inv['courier_charges']) && $inv['courier_charges'] != 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Courier Charges</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($inv['courier_charges'], 2); ?></b></td>
</tr>
<?php endif; ?>

<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Total</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td><td></td>
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
<td><b><?php echo $Currency_Name; ?> <?php echo ucwords($result); ?> Only</b></td>
<td></td>
</tr></table>

<!---------------------HSN WISE TOTAL------------------------------>
<?php if ($gsttype == "inner"): ?>
<table width="100%" id="hsnsac">
<tr>
<td align="center">HSN/SAC</td>
<td align="right">Taxable<br/>Value</td>
<td align="right" colspan="2">CGST</td>
<td align="right" colspan="2">SGST</td>
<td align="right">Total<br/>Tax Amount</td>
</tr>
<tr>
<td></td><td></td>
<td align="right">Rate</td><td align="right">Amount</td>
<td align="right">Rate</td><td align="right">Amount</td>
<td></td>
</tr>
<?php foreach ($hsn_totals as $hsncode => $hsnamt):
    $hsn_gst  = $hsn_gst_totals[$hsncode] ?? 0;
    $hsn_half = $hsn_gst / 2;
    $hsn_rate = ($hsn_gst_pct[$hsncode] ?? 0) / 2;
?>
<tr>
<td><?php echo htmlspecialchars($hsncode); ?></td>
<td align="right"><?php echo inr_format($hsnamt, 2); ?></td>
<td align="right"><?php echo fmt_gst_pct($hsn_rate); ?>%</td>
<td align="right"><?php echo inr_format($hsn_half, 2); ?></td>
<td align="right"><?php echo fmt_gst_pct($hsn_rate); ?>%</td>
<td align="right"><?php echo inr_format($hsn_half, 2); ?></td>
<td align="right"><?php echo inr_format($hsn_gst, 2); ?></td>
</tr>
<?php endforeach; ?>
<tr>
<td align="right"><b>Total&nbsp;</b></td>
<td align="right"><b><?php echo inr_format($TotalAMount123, 2); ?></b></td>
<td></td>
<td align="right"><b><?php echo inr_format($totalgstamount / 2, 2); ?></b></td>
<td></td>
<td align="right"><b><?php echo inr_format($totalgstamount / 2, 2); ?></b></td>
<td align="right"><b><?php echo inr_format($totalgstamount, 2); ?></b></td>
</tr>
</table>
<?php else: ?>
<table width="100%" id="hsnsac">
<tr>
<td align="center">HSN/SAC</td>
<td align="right">Taxable<br/>Value</td>
<td align="right">IGST Rate</td>
<td align="right">IGST Amount</td>
</tr>
<?php foreach ($hsn_totals as $hsncode => $hsnamt):
    $hsn_gst  = $hsn_gst_totals[$hsncode] ?? 0;
    $hsn_rate = $hsn_gst_pct[$hsncode] ?? 0;
?>
<tr>
<td><?php echo htmlspecialchars($hsncode); ?></td>
<td align="right"><?php echo inr_format($hsnamt, 2); ?></td>
<td align="right"><?php echo fmt_gst_pct($hsn_rate); ?>%</td>
<td align="right"><?php echo inr_format($hsn_gst, 2); ?></td>
</tr>
<?php endforeach; ?>
<tr>
<td align="right"><b>Total&nbsp;</b></td>
<td align="right"><b><?php echo inr_format($TotalAMount123, 2); ?></b></td>
<td></td>
<td align="right"><b><?php echo inr_format($totalgstamount, 2); ?></b></td>
</tr>
</table>
<?php endif; ?>
<!---------------------HSN WISE TOTAL END------------------------------>

<table width="100%">
<tr>
<td width="60%">
<?php if ($totalgstamount > 0): ?>
<div>&nbsp;Tax Amount (in words): <b><?php echo $Currency_Name; ?> <?php echo ucwords($TAXresult); ?> Only</b></div>
<?php else: ?>
<div>&nbsp;Tax Amount (in words): <b>Nil</b></div>
<?php endif; ?>
<br/>
<div style="width:99%;margin:0 auto;"><u>Declaration:</u><br/>We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</div>
</td>
<td>
<?php if (!empty($profile['acname'])): ?>
<table align="right">
<tr><td>A/c Name</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($profile['acname']); ?></td></tr>
<tr><td>A/c Number</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($profile['acnumber']); ?></td></tr>
<tr><td>Bank Name</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($profile['bankname']); ?></td></tr>
<tr><td>Branch Name</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($profile['branchname']); ?></td></tr>
<tr><td>IFS Code</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($profile['ifsc']); ?></td></tr>
<tr><td>UPI Number</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($profile['upinumber']); ?></td></tr>
</table>
<?php endif; ?>
</td>
</tr>
</table>

<table width="100%" id="sealsign">
<tr>
<td width="50%" align="left">Customer's Seal and Signature</td>
<td align="right">for <b><?php echo htmlspecialchars($seller_display_name); ?></b></td>
</tr>
<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr>
<td></td>
<td align="right">Authorised Signatory</td>
</tr>
</table>
<div style="clear:both;"></div>
</div><!--maincontainar-->
</div><!--divToPrintScroll-->
<div align="center">This is a Computer Generated Invoice</div>
<?php
    return ob_get_clean();
}
