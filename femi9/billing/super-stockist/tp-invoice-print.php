<?php include("checksession.php"); date_default_timezone_set("Asia/Kolkata");
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'super_stockiest') {
    header("Location: manage-tp-invoices"); exit;
}

$ss_id = $Login_user_IDvl;

$enc_id = $_GET['id'] ?? '';
$inv_id = (int)base64_decode($enc_id);
if (!$inv_id) { header("Location: manage-tp-invoices"); exit; }

// Invoice header — ownership via TP.onboard_ss_id
$stmt = $db_conn->prepare("
    SELECT tpi.*,
           tp.name AS tp_name, tp.company_name AS tp_company_name, tp.tp_id AS tp_code, tp.mobile AS tp_mobile, tp.gstin AS tp_gstin,
           tp.branch_line1, tp.branch_line2, tp.branch_city, tp.branch_district, tp.branch_state, tp.branch_country,
           tp.delivery_line1, tp.delivery_line2, tp.delivery_city, tp.delivery_district, tp.delivery_state, tp.delivery_country
    FROM tp_invoices tpi
    JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
    WHERE tpi.id = ? AND tp.onboard_ss_id = ?
");
$stmt->bind_param("is", $inv_id, $ss_id);
$stmt->execute();
$result_Invoice_Details = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$result_Invoice_Details) { header("Location: manage-tp-invoices"); exit; }

// Seller = this Super Stockist's own account
$ss_stmt = $db_conn->prepare("SELECT * FROM super_stockiest WHERE temp_id = ? LIMIT 1");
$ss_stmt->bind_param("s", $ss_id);
$ss_stmt->execute();
$result_Seller = $ss_stmt->get_result()->fetch_assoc();
$ss_stmt->close();

// Line items with product details
$stmt2 = $db_conn->prepare("
    SELECT tpii.quantity, tpii.rate, tpii.amount,
           p.productName, p.hsn, p.gst AS gst_percentage, p.gst_type, p.mrp
    FROM tp_invoice_items tpii
    JOIN products p ON p.id = tpii.product_id
    WHERE tpii.tp_invoice_id = ?
    ORDER BY tpii.id
");
$stmt2->bind_param("i", $inv_id);
$stmt2->execute();
$invoice_items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// Totals
$TotalAMount123   = 0; // sum of taxable values (exclusive of GST)
$Totalquantity123 = 0;
$totalgstamount   = 0;
$hsn_totals       = []; // hsn => taxable sum
foreach ($invoice_items as &$item) {
    $line_total = (float)$item['amount'];
    $gst_pct    = (int)$item['gst_percentage'];
    $gst_type   = $item['gst_type'] ?? 'exclusive';

    if ($gst_type === 'inclusive' && $gst_pct > 0) {
        $taxable_value = $line_total * 100 / (100 + $gst_pct);
        $gst_amount    = $line_total - $taxable_value;
    } else {
        $taxable_value = $line_total;
        $gst_amount    = $line_total * $gst_pct / 100;
    }
    $item['taxable_value'] = $taxable_value;
    $item['gst_amount']    = $gst_amount;

    $TotalAMount123   += $taxable_value;
    $Totalquantity123 += (int)$item['quantity'];
    $totalgstamount   += $gst_amount;
    $hsn = $item['hsn'] ?: '-';
    $hsn_totals[$hsn] = ($hsn_totals[$hsn] ?? 0) + $taxable_value;
}
unset($item);
$courier_charges  = (float)$result_Invoice_Details['courier_charges'];
$discount_amount  = (float)($result_Invoice_Details['discount_amount'] ?? 0);
$grand_total      = (float)$result_Invoice_Details['total_amount'];
$has_gst_product  = $totalgstamount > 0;
$invoice_heading  = $has_gst_product ? 'Tax Invoice' : 'Bill of Supply';

// Amount in words
$number = $grand_total;
$no = floor($number); $digits_1 = strlen($no); $i = 0; $str = [];
$words = ['0'=>'','1'=>'one','2'=>'two','3'=>'three','4'=>'four','5'=>'five','6'=>'six',
    '7'=>'seven','8'=>'eight','9'=>'nine','10'=>'ten','11'=>'eleven','12'=>'twelve',
    '13'=>'thirteen','14'=>'fourteen','15'=>'fifteen','16'=>'sixteen','17'=>'seventeen',
    '18'=>'eighteen','19'=>'nineteen','20'=>'twenty','30'=>'thirty','40'=>'forty',
    '50'=>'fifty','60'=>'sixty','70'=>'seventy','80'=>'eighty','90'=>'ninety'];
$digits = ['','hundred','thousand','lakh','crore'];
while ($i < $digits_1) {
    $divider = ($i == 2) ? 10 : 100; $number = floor($no % $divider); $no = floor($no / $divider);
    $i += ($divider == 10) ? 1 : 2;
    if ($number) {
        $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
        $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
        $str[] = ($number < 21) ? $words[$number]." ".$digits[$counter].$plural." ".$hundred
                 : $words[floor($number/10)*10]." ".$words[$number%10]." ".$digits[$counter].$plural." ".$hundred;
    } else $str[] = null;
}
$str = array_reverse($str); $result = implode('', $str);

// Tax amount in words
$TAXnumber = $totalgstamount;
$TAXno = floor($TAXnumber); $TAXdigits_1 = strlen($TAXno); $TAXi = 0; $TAXstr = [];
while ($TAXi < $TAXdigits_1) {
    $TAXdivider = ($TAXi == 2) ? 10 : 100; $TAXnum = floor($TAXno % $TAXdivider); $TAXno = floor($TAXno / $TAXdivider);
    $TAXi += ($TAXdivider == 10) ? 1 : 2;
    if ($TAXnum) {
        $TAXplural = (($TAXcounter = count($TAXstr)) && $TAXnum > 9) ? 's' : null;
        $TAXhundred = ($TAXcounter == 1 && $TAXstr[0]) ? ' and ' : null;
        $TAXstr[] = ($TAXnum < 21) ? $words[$TAXnum]." ".$digits[$TAXcounter].$TAXplural." ".$TAXhundred
                    : $words[floor($TAXnum/10)*10]." ".$words[$TAXnum%10]." ".$digits[$TAXcounter].$TAXplural." ".$TAXhundred;
    } else $TAXstr[] = null;
}
$TAXstr = array_reverse($TAXstr); $TAXresult = implode('', $TAXstr);

// Taxable amount in words
$TXBnumber = $TotalAMount123;
$TXBno = floor($TXBnumber); $TXBdigits_1 = strlen($TXBno); $TXBi = 0; $TXBstr = [];
while ($TXBi < $TXBdigits_1) {
    $TXBdivider = ($TXBi == 2) ? 10 : 100; $TXBnum = floor($TXBno % $TXBdivider); $TXBno = floor($TXBno / $TXBdivider);
    $TXBi += ($TXBdivider == 10) ? 1 : 2;
    if ($TXBnum) {
        $TXBplural = (($TXBcounter = count($TXBstr)) && $TXBnum > 9) ? 's' : null;
        $TXBhundred = ($TXBcounter == 1 && $TXBstr[0]) ? ' and ' : null;
        $TXBstr[] = ($TXBnum < 21) ? $words[$TXBnum]." ".$digits[$TXBcounter].$TXBplural." ".$TXBhundred
                    : $words[floor($TXBnum/10)*10]." ".$words[$TXBnum%10]." ".$digits[$TXBcounter].$TXBplural." ".$TXBhundred;
    } else $TXBstr[] = null;
}
$TXBstr = array_reverse($TXBstr); $TXBresult = implode('', $TXBstr);

$Currency_symbol = "&#8377;";
$Currency_Name   = "INR";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TP Invoice : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
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

                <script type="text/javascript">
                function PrintDiv() {
                    var divToPrint = document.getElementById('divToPrint');
                    var popupWin = window.open('', '_blank', 'width=990,height=540,left=200,top=80');
                    popupWin.document.open();
                    popupWin.document.write(
                        '<html><head><style>' +
                        '@page { margin: 0; size: auto; }' +
                        'body { margin: 10mm; }' +
                        '</style></head>' +
                        '<body onload="window.print()">' + divToPrint.innerHTML + '</body></html>'
                    );
                    popupWin.document.close();
                }
                </script>

                <table align="right">
                <tr>
                    <td><button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button></td>
                    <td><button type="button" onClick="javascript:window.location='add-tp-invoice';" class="btn btn-success m-b-xs m-r-xs">+ New TP Invoice</button></td>
                    <td><button type="button" onClick="javascript:window.location='manage-tp-invoices';" class="btn btn-primary m-b-xs m-r-xs">Manage TP Invoices</button></td>
                </tr>
                </table>
                <br/>
                <div style="clear:both;"></div>

                <div id="divToPrint"><!--Print content start-->

<style type="text/css">
.maincontainar{width:100%;height:auto;border:1px solid #000;}
.maincontainar hr{border-bottom:1px solid #000;}

#toptl{width:100%;padding:5px;font-family:arial;font-weight:bold;border-bottom:1px solid #000;text-align:center;font-size:22px;}

.second_containar{width:100%;}

#second_topvl{width:100%;padding:5px;font-family:arial;border-bottom:1px solid #000;border-collapse:collapse;}
#second_topvl td{padding:5px;}
#border_nbottom td{border-bottom:1px solid #000;}

.second_containar{width:100%;border-collapse:collapse;}
.second_containar td:nth-child(1){border-right:1px solid #000;padding:0px;}

#noneborder td{border:0px !important;font-family:arial;font-size:14px;line-height:20px;}

.item_list{width:100%;border-top:1px solid #000;border-collapse:collapse;font-family:arial;}
.item_list td{border-right:1px solid #000;padding:5px;font-size:14px;vertical-align:top;}
#bordervl td{border-bottom:1px solid #000;padding:5px;}
#rightlaign{text-align:right;}
#bottombordervl{border-top:1px solid #000;border-bottom:1px solid #000;}
.amount_word{font-family:arial;padding:4px;border-bottom:1px solid #000;}
.amount_payable{font-family:arial;padding:4px;border-bottom:1px solid #000;text-align:right;}

#vlnotes{font-family:arial;width:100%;}
#vlnotes tr td:nth-child(1){border-right:1px solid #000;width:35%;}
#cmpname{font-size:17px;font-weight:bold;}
.cusdetaiis{margin-left:10px;font-family:arial;font-size:14px;line-height:20px;}
#shiippingaddress{margin-left:10px;font-family:arial;}
#pageno{font-family:arial;padding:20px 0px 20px 0px;}

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
<td valign="top">
<span id="cmpname"><?= htmlspecialchars($result_Seller['name'] ?? ''); ?></span><br/>
<?= htmlspecialchars($result_Seller['address'] ?? ''); ?><br/>
<?php if (!empty($result_Seller['gstin'])): ?><b>GSTIN/UIN :</b> <?= htmlspecialchars($result_Seller['gstin']); ?><br/><?php endif; ?>
<b>Contact</b> : <?= htmlspecialchars($result_Seller['mobile_number'] ?? ''); ?><br/>
<b>Email</b> : <?= htmlspecialchars($result_Seller['email'] ?? ''); ?>
</td>
</tr>
</table>
<hr/>

<?php
$d = $result_Invoice_Details;
// Build delivery address lines
$delivery_parts = array_filter([
    $d['delivery_line1'],
    $d['delivery_line2'],
    implode(', ', array_filter([$d['delivery_city'], $d['delivery_district']])),
    implode(', ', array_filter([$d['delivery_state'], $d['delivery_country']])),
]);
// Build billing address lines
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
<td id="rightlaign">Rate</td>
<td id="rightlaign">per</td>
<td id="rightlaign">GST(%)</td>
<td id="rightlaign">Disc</td>
<td id="rightlaign">Amount</td>
</tr>

<?php $invno = 0; foreach ($invoice_items as $item):
    $invno++;
    $qty           = (int)$item['quantity'];
    $rate          = (float)$item['rate'];
    $gst_pct       = (int)$item['gst_percentage'];
    $gst_type      = $item['gst_type'] ?? 'exclusive';
    $mrp           = (float)$item['mrp'];
    $taxable_value = $item['taxable_value'];
?>
<tr>
<td><?= $invno; ?></td>
<td><b><?= htmlspecialchars($item['productName']); ?></b><?= $gst_type === 'inclusive' ? ' <small style="color:#666">(GST incl.)</small>' : ''; ?></td>
<td id="rightlaign"><?= htmlspecialchars($item['hsn']); ?></td>
<td id="rightlaign"><?= inr_format($qty, 0); ?> Packs</td>
<td id="rightlaign"><?= inr_format($mrp, 2); ?></td>
<td id="rightlaign"><?= inr_format($rate, 2); ?></td>
<td id="rightlaign">Packs</td>
<td id="rightlaign"><?= $gst_pct; ?>%</td>
<td id="rightlaign">0.00<br/>(0%)</td>
<td id="rightlaign"><?= inr_format($taxable_value, 2); ?></td>
</tr>
<?php endforeach; ?>

<tr>
<td></td><td></td><td></td>
<td id="rightlaign"><b><?= inr_format($Totalquantity123, 0); ?> Packs</b></td>
<td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= inr_format($TotalAMount123, 2); ?></b></td>
</tr>

<?php if ($discount_amount > 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Discount</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b>−<?= $Currency_symbol; ?>&nbsp;<?= inr_format($discount_amount, 2); ?></b></td>
</tr>
<?php endif; ?>
<?php if ($totalgstamount > 0):
    $SGST = inr_format($totalgstamount / 2, 2);
    $CGST = inr_format($totalgstamount / 2, 2);
?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>SGST</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= $SGST; ?></b></td>
</tr>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>CGST</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= $CGST; ?></b></td>
</tr>
<?php endif; ?>

<?php if ($courier_charges > 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Courier Charges</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= inr_format($courier_charges, 2); ?></b></td>
</tr>
<?php endif; ?>

<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Total</i></b></td>
<td></td><td></td><td></td><td></td><td></td><td></td><td></td>
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
<tr>
<td style="padding-top:6px;font-size:13px;">Amount Taxable (in words)</td>
<td align="right" style="font-size:13px;"><?= $Currency_symbol; ?>&nbsp;<?= inr_format($TotalAMount123, 2); ?></td>
</tr>
<tr>
<td><b><?= $Currency_Name; ?> <?= ucwords($TXBresult); ?> Only</b></td>
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
<td width="100%">
<?php if ($totalgstamount > 0): ?>
<div>&nbsp;Tax Amount (in words): <b><?= $Currency_Name; ?> <?= ucwords($TAXresult); ?> Only</b></div>
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
<td align="right">for <b><?= htmlspecialchars($result_Seller['name'] ?? ''); ?></b></td>
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

                </div><!--Print content end-->

            </div>
        </div>
    </div>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/plugins/highlight/highlight.pack.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
