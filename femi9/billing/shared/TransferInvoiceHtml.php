<?php
/**
 * Renders the #divToPrint markup for an internal stock transfer, rendered
 * as a full GST tax invoice. This is TpInvoiceHtml.php's render_tp_invoice_html()
 * template reused byte-for-byte for CSS/table structure, with TP-specific
 * concepts (Territory Partner ship-to/bill-to split, discount, courier
 * charges, product-type badge, PDF/WhatsApp variant) removed since none of
 * them apply to an internal transfer. See
 * docs/superpowers/specs/2026-09-15-transfer-tax-invoice-design.md for the
 * full rationale.
 *
 * All business logic (DB queries, GST computation, totals) stays in
 * TransferInvoiceData.php — this function only takes the already-computed
 * values and returns markup.
 */
function render_transfer_invoice_html(array $ctx, bool $show_carton_cols): string {
    extract($ctx, EXTR_SKIP);
    // Expected keys in $ctx: result_Invoice_Details, result_Godown,
    // invoice_items, TotalAMount123, Totalquantity123, totalgstamount,
    // hsn_totals, hsn_gst_totals, hsn_gst_pct, __inv_gst_pct,
    // has_mixed_gst_rates,
    // TotalCartons123, has_carton_data, grand_total, has_gst_product,
    // invoice_heading, result (amount-in-words), TAXresult (tax-amount-in-
    // words), Currency_symbol, Currency_Name.

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
@media print {
    @page { margin: 0; size: auto; }
    body { margin: 10mm; }
}
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
<?php if (!empty($result_Godown['logo'])): ?>
<img src="<?= $result_Godown['logo']; ?>" style="width:150px;margin-right:5px;"/>
<?php endif; ?>
</td>
<td valign="top">
<span id="cmpname"><?= htmlspecialchars($result_Godown['gname']); ?></span><br/>
<?= htmlspecialchars($result_Godown['address_line1']); ?><br/>
<?= htmlspecialchars($result_Godown['address_line2']); ?><br/>
<b>GSTIN/UIN :</b> <?= htmlspecialchars($result_Godown['gstin']); ?><br/>
<b>State Name</b> : <?= htmlspecialchars($result_Godown['state']); ?> <b>Code</b> : <?= htmlspecialchars($result_Godown['state_code']); ?><br/>
<b>Contact</b> : <?= htmlspecialchars($result_Godown['contact']); ?><br/>
<b>Email</b> : <?= htmlspecialchars($result_Godown['email']); ?>
</td>
</tr>
</table>
<hr/>

<p class="cusdetaiis">
Consignee &amp; Buyer:<br/>
<?= htmlspecialchars($result_Invoice_Details['buyer_name']); ?><br/>
<?php if (!empty($result_Invoice_Details['buyer_gstin'])): ?>GSTIN: <?= htmlspecialchars($result_Invoice_Details['buyer_gstin']); ?><br/><?php endif; ?>
<?php if (!empty($result_Invoice_Details['buyer_mobile'])): ?>Mobile:&nbsp;<?= htmlspecialchars($result_Invoice_Details['buyer_mobile']); ?><br/><?php endif; ?>
<?= implode('<br/>', array_map('htmlspecialchars', $result_Invoice_Details['buyer_address_parts'])); ?>
</p>
</td>

<td valign="top">
<table id="second_topvl">
<tr id="border_nbottom">
<td>Delivery Slip #<br/><b><?= htmlspecialchars($result_Invoice_Details['dn_number']); ?></b></td>
<td>Delivery Slip Date:<br/><b><?= date("d M Y", strtotime($result_Invoice_Details['transfer_date'])); ?></b></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Mode/Terms of Payment<br/>&nbsp;</td>
<td>Reference No. &amp; Date<br/><b><?= htmlspecialchars($result_Invoice_Details['ref_number']); ?></b></td>
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
<?php if ($show_carton_cols): ?>
<td id="rightlaign">Packs/Carton</td>
<td id="rightlaign">Cartons</td>
<?php endif; ?>
<td id="rightlaign">MRP</td>
<td id="rightlaign">Rate (Excl. Tax)</td>
<td id="rightlaign">Rate (Incl. Tax)</td>
<td id="rightlaign">per</td>
<td id="rightlaign">GST(%)</td>
<td id="rightlaign">Amount</td>
</tr>

<?php $invno = 0; foreach ($invoice_items as $item):
    $invno++;
    $qty           = (int)$item['quantity'];
    $gst_pct       = (int)$item['gst_percentage'];
    $mrp           = (float)$item['mrp'];
    $taxable_value = $item['taxable_value'];
    $rate          = (float)$item['taxable_rate'];
    $rate_incl     = (float)$item['taxable_rate_incl'];
?>
<tr>
<td><?= $invno; ?></td>
<td><b><?= htmlspecialchars($item['productName']); ?></b></td>
<td id="rightlaign"><?= htmlspecialchars($item['hsn']); ?></td>
<td id="rightlaign"><?= inr_format($qty, 0); ?> Packs</td>
<?php if ($show_carton_cols): ?>
<td id="rightlaign"><?= ($item['packs_per_carton'] !== null && $item['packs_per_carton'] !== '') ? inr_format((int)$item['packs_per_carton'], 0) : '—'; ?></td>
<td id="rightlaign"><?= $item['carton_display']; ?></td>
<?php endif; ?>
<td id="rightlaign"><?= inr_format($mrp, 2); ?></td>
<td id="rightlaign"><?= inr_format($rate, 2); ?></td>
<td id="rightlaign"><?= inr_format($rate_incl, 2); ?></td>
<td id="rightlaign">Packs</td>
<td id="rightlaign"><?= $gst_pct; ?>%</td>
<td id="rightlaign"><?= inr_format($taxable_value, 2); ?></td>
</tr>
<?php endforeach; ?>

<tr>
<td></td><td></td><td></td>
<td id="rightlaign"><b><?= inr_format($Totalquantity123, 0); ?> Packs</b></td>
<?php if ($show_carton_cols): ?>
<td></td>
<td id="rightlaign"><b><?= inr_format($TotalCartons123, 0); ?> ctn</b></td>
<?php endif; ?>
<td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= inr_format($TotalAMount123, 2); ?></b></td>
</tr>

<?php if ($totalgstamount > 0):
    $SGST = inr_format($totalgstamount / 2, 2);
    $CGST = inr_format($totalgstamount / 2, 2);
    $__half_pct = fmt_gst_pct($__inv_gst_pct / 2);
    // The blended percentage is only accurate when every GST-bearing line
    // shares one rate — when the transfer mixes rates (e.g. 5% and 18%
    // products in the same transfer), showing either line's rate on the
    // combined SGST/CGST total would be misleading, so the label is
    // suppressed to plain "SGST"/"CGST" in that case. The amounts below
    // stay correct either way; only this label is affected.
    $__sgst_label = empty($has_mixed_gst_rates) ? "SGST ({$__half_pct}%)" : 'SGST';
    $__cgst_label = empty($has_mixed_gst_rates) ? "CGST ({$__half_pct}%)" : 'CGST';
?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i><?= $__sgst_label; ?></i></b></td>
<td></td><td></td>
<?php if ($show_carton_cols): ?><td></td><td></td><?php endif; ?>
<td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= $SGST; ?></b></td>
</tr>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i><?= $__cgst_label; ?></i></b></td>
<td></td><td></td>
<?php if ($show_carton_cols): ?><td></td><td></td><?php endif; ?>
<td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= $CGST; ?></b></td>
</tr>
<?php endif; ?>

<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Total</i></b></td>
<td></td><td></td>
<?php if ($show_carton_cols): ?><td></td><td></td><?php endif; ?>
<td></td><td></td><td></td><td></td><td></td>
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
    $hsn_gst   = $hsn_gst_totals[$hsncode] ?? 0;
    $hsn_half  = $hsn_gst / 2;
    $hsn_rate  = ($hsn_gst_pct[$hsncode] ?? 0) / 2;
?>
<tr>
<td><?= htmlspecialchars($hsncode); ?></td>
<td align="right"><?= inr_format($hsnamt, 2); ?></td>
<td align="right"><?= fmt_gst_pct($hsn_rate); ?>%</td>
<td align="right"><?= inr_format($hsn_half, 2); ?></td>
<td align="right"><?= fmt_gst_pct($hsn_rate); ?>%</td>
<td align="right"><?= inr_format($hsn_half, 2); ?></td>
<td align="right"><?= inr_format($hsn_gst, 2); ?></td>
</tr>
<?php endforeach; ?>
<tr>
<td align="right"><b>Total&nbsp;</b></td>
<td align="right"><b><?= inr_format($TotalAMount123, 2); ?></b></td>
<td></td>
<td align="right"><b><?= inr_format($totalgstamount / 2, 2); ?></b></td>
<td></td>
<td align="right"><b><?= inr_format($totalgstamount / 2, 2); ?></b></td>
<td align="right"><b><?= inr_format($totalgstamount, 2); ?></b></td>
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
<td width="50%" align="left">Received By: Seal and Signature</td>
<td align="right">for <b><?= htmlspecialchars($result_Godown['gname']); ?></b></td>
</tr>
<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr>
<td></td>
<td align="right">Authorised Signatory</td>
</tr>
</table>
<div style="clear:both;"></div>
</div>
<div align="center">SUBJECT TO ERODE JURISDICTION</div>
<div align="center">This is a Computer Generated Invoice</div>
<?php
    return ob_get_clean();
}
