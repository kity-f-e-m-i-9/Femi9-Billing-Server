<?php include("checksession.php");
require_once("include/GodownAccess.php");
date_default_timezone_set("Asia/Kolkata");
error_reporting(0);
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

$id = (int) base64_decode($_GET['id'] ?? '');
if (!$id) { header("Location: neksomo-manufacturer-purchase-manage.php"); exit; }

// Trims a rate like 1.50 down to "1.5" or 9.00 down to "9" — CGST/SGST is
// always exactly half the item's GST%, which is often a non-whole number
// (e.g. 3% GST -> 1.5% + 1.5%), so this avoids both misleading rounding
// (inr_format's 0-decimal rounding would show 1.5% as "2%") and clutter
// like "1.50%" for a value that's actually whole. Same helper as
// tp-invoice-print.php.
function fmt_gst_pct($v) {
    return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
}

$stmt = $db_conn->prepare(
    "SELECT mp.id, mp.invoice_number, mp.purchase_date, mp.total_amount,
            mp.total_taxable_value, mp.total_gst_amount, mp.created_by,
            v.vendor_name, v.address AS vendor_address, v.gstin AS vendor_gstin, v.phone AS vendor_phone, v.email AS vendor_email
     FROM neksomo_manufacturer_purchases mp
     JOIN neksomo_vendors v ON v.id = mp.vendor_id
     WHERE mp.id = ?"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$purchase = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$purchase) { header("Location: neksomo-manufacturer-purchase-manage.php"); exit; }

$itemStmt = $db_conn->prepare(
    "SELECT npi.quantity_pieces, npi.cost_per_piece, npi.total_cost,
            npi.taxable_value, npi.gst_amount, npi.gst_rate, npi.gst_type,
            p.productName, p.hsn, p.unit_type, p.pieces_per_pack
     FROM neksomo_purchase_items npi
     JOIN products p ON p.id = npi.product_id
     WHERE npi.purchase_id = ?
     ORDER BY npi.id"
);
$itemStmt->bind_param('i', $id);
$itemStmt->execute();
$items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

// Purchases are always stored piece-wise (quantity_pieces / cost_per_piece),
// but pack-based products (unit_type='pack') were entered on the purchase
// form in whole packs — quantity_pieces is always an exact multiple of
// pieces_per_pack for those, so the pack count can be derived back out for
// display, same convention the entry form itself uses. Rate is the taxable
// (excl.-of-tax) per-unit price, same convention as tp-invoice-print.php.
$total_qty      = 0;
$TotalAMount123 = 0; // sum of taxable values (exclusive of GST)
$totalgstamount = 0;
$hsn_totals     = []; // hsn => taxable sum
$hsn_gst_totals = []; // hsn => gst amount sum
$hsn_gst_pct    = []; // hsn => gst rate (assumed uniform per HSN)
$__inv_gst_pct  = 0;  // MAX gst rate across all lines — for the SGST/CGST label below, not a per-HSN figure
foreach ($items as &$item) {
    $pph = (int) $item['pieces_per_pack'];
    $item['is_pack'] = ($item['unit_type'] === 'pack' && $pph > 0);
    if ($item['is_pack']) {
        $item['display_qty'] = (int) $item['quantity_pieces'] / $pph;
        $item['per_label']   = 'Pack';
    } else {
        $item['display_qty'] = (int) $item['quantity_pieces'];
        $item['per_label']   = 'Pc';
    }
    $item['rate'] = $item['display_qty'] > 0 ? (float)$item['taxable_value'] / $item['display_qty'] : 0;

    $total_qty      += (int) $item['quantity_pieces'];
    $TotalAMount123 += (float) $item['taxable_value'];
    $totalgstamount += (float) $item['gst_amount'];
    $hsn = $item['hsn'] ?: '-';
    $hsn_totals[$hsn]     = ($hsn_totals[$hsn] ?? 0) + (float)$item['taxable_value'];
    $hsn_gst_totals[$hsn] = ($hsn_gst_totals[$hsn] ?? 0) + (float)$item['gst_amount'];
    $hsn_gst_pct[$hsn]    = (float)$item['gst_rate'];
    $__inv_gst_pct        = max($__inv_gst_pct, (float)$item['gst_rate']);
}
unset($item);

$result_Godown = $db_conn->query(
    "SELECT * FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1"
)->fetch_assoc();

$grand_total     = (float) $purchase['total_amount'];
$has_gst_product = $totalgstamount > 0;

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

// Paise portion — without this, a tax amount under ₹1 (e.g. ₹0.73, common on
// small-value lines) has $TAXno=0 above, so $TAXresult comes out empty and
// "Tax Amount (in words)" prints as just "INR  Only" with nothing in it.
$TAXpaise = (int) round(($totalgstamount - floor($totalgstamount)) * 100);
$TAXpaise_words = '';
if ($TAXpaise > 0) {
    $TAXpaise_words = ($TAXpaise < 21)
        ? $words[$TAXpaise]
        : trim($words[floor($TAXpaise / 10) * 10] . " " . $words[$TAXpaise % 10]);
}
if (trim($TAXresult) !== '' && $TAXpaise_words !== '') {
    $TAXresult = trim($TAXresult) . ' Rupees and ' . $TAXpaise_words . ' Paise';
} elseif ($TAXpaise_words !== '') {
    $TAXresult = $TAXpaise_words . ' Paise';
} elseif (trim($TAXresult) === '') {
    $TAXresult = 'Zero';
}

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
$invoice_heading = $has_gst_product ? 'Manufacturer Purchase Tax Invoice' : 'Manufacturer Purchase Receipt';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase <?php echo htmlspecialchars($purchase['invoice_number']); ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
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
                    <td><button type="button" onClick="javascript:window.location='neksomo-manufacturer-purchase.php';" class="btn btn-success m-b-xs m-r-xs">+ New Purchase</button></td>
                    <td><button type="button" onClick="javascript:window.location='neksomo-manufacturer-purchase-manage.php';" class="btn btn-primary m-b-xs m-r-xs">Manage Purchases</button></td>
                </tr>
                </table>
                <br/>
                <div style="clear:both;"></div>

                <div id="divToPrint"><!--Print content start-->

<style type="text/css">
.maincontainar{width:100%;height:auto;border:1px solid #000;}
.maincontainar hr{border-bottom:1px solid #000;}
#toptl{width:100%;padding:5px;font-family:arial;font-weight:bold;border-bottom:1px solid #000;text-align:center;font-size:22px;}
.second_containar{width:100%;border-collapse:collapse;}
.second_containar td:nth-child(1){border-right:1px solid #000;padding:0px;}
#second_topvl{width:100%;padding:5px;font-family:arial;border-bottom:1px solid #000;border-collapse:collapse;}
#second_topvl td{padding:5px;}
#border_nbottom td{border-bottom:1px solid #000;}
#noneborder td{border:0px !important;font-family:arial;font-size:14px;line-height:20px;}
.item_list{width:100%;border-top:1px solid #000;border-collapse:collapse;font-family:arial;}
.item_list td{border-right:1px solid #000;padding:5px;font-size:14px;vertical-align:top;}
#bordervl td{border-bottom:1px solid #000;padding:5px;}
#rightlaign{text-align:right;}
#bottombordervl{border-top:1px solid #000;border-bottom:1px solid #000;}
#cmpname{font-size:17px;font-weight:bold;}
.cusdetaiis{margin-left:10px;font-family:arial;font-size:14px;line-height:20px;}
#pageno{font-family:arial;padding:20px 0px 20px 0px;}
#hsnsac{border-collapse:collapse;}
#hsnsac tr td{border:1px solid #000;}
#hsnsac tr td:nth-child(1){border-left:0px;}
#hsnsac tr td:nth-child(2){border-right:0px;}
#sealsign{border-collapse:collapse;width:100%;}
#sealsign td{padding:3px;}
#sealsign tr:nth-child(1){border-top:1px solid #000;}
#sealsign tr td:nth-child(1){border-right:1px solid #000;}
.vendor_block{width:100%;border-top:1px solid #000;border-bottom:1px solid #000;font-family:arial;padding:5px;}
.vendor_block .vendor_label{font-size:14px;margin-bottom:2px;}
.vendor_block .vendor_name{font-size:17px;font-weight:bold;margin-bottom:2px;}
.vendor_block .vendor_grid{display:table;width:100%;margin-top:2px;}
.vendor_block .vendor_grid .vendor_row{display:table-row;}
.vendor_block .vendor_grid .vendor_cell{display:table-cell;font-size:14px;line-height:20px;padding:0 30px 0 0;vertical-align:top;}
.vendor_block .vendor_cell b{display:inline-block;min-width:70px;}
@media print {
    @page { margin: 0; size: auto; }
    body { margin: 10mm; }
}
</style>

<div class="maincontainar">

<table id="toptl">
<tr><td><?= $invoice_heading; ?></td></tr>
</table>

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
<span id="cmpname"><?= htmlspecialchars($result_Godown['gname'] ?? 'Neksomo Hygiene Industries'); ?></span><br/>
<?= htmlspecialchars($result_Godown['address_line1'] ?? ''); ?><br/>
<?= htmlspecialchars($result_Godown['address_line2'] ?? ''); ?><br/>
<?php if (!empty($result_Godown['gstin'])): ?><b>GSTIN/UIN :</b> <?= htmlspecialchars($result_Godown['gstin']); ?><br/><?php endif; ?>
<?php if (!empty($result_Godown['contact'])): ?><b>Contact</b> : <?= htmlspecialchars($result_Godown['contact']); ?><?php endif; ?>
</td>
</tr>
</table>
</td>

<td valign="top">
<table id="second_topvl">
<tr id="border_nbottom">
<td>Invoice #<br/><b><?= htmlspecialchars($purchase['invoice_number']); ?></b></td>
<td>Purchase Date:<br/><b><?= date("d M Y", strtotime($purchase['purchase_date'])); ?></b></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Recorded By<br/><b><?= htmlspecialchars($purchase['created_by'] ?? ''); ?></b></td>
<td>Received At<br/><b><?= htmlspecialchars($result_Godown['gname'] ?? 'Neksomo Hygiene Industries'); ?></b></td>
</tr>
</table>
</td>
</tr>
</table>

<div class="vendor_block">
<div class="vendor_label">Purchased From (Vendor)</div>
<div class="vendor_name"><?= htmlspecialchars($purchase['vendor_name']); ?></div>
<div class="vendor_grid">
<div class="vendor_row">
<?php if (!empty($purchase['vendor_address'])): ?><div class="vendor_cell"><b>Address</b> <?= nl2br(htmlspecialchars($purchase['vendor_address'])); ?></div><?php endif; ?>
<?php if (!empty($purchase['vendor_gstin'])): ?><div class="vendor_cell"><b>GSTIN</b> <?= htmlspecialchars($purchase['vendor_gstin']); ?></div><?php endif; ?>
</div>
<div class="vendor_row">
<?php if (!empty($purchase['vendor_phone'])): ?><div class="vendor_cell"><b>Phone</b> <?= htmlspecialchars($purchase['vendor_phone']); ?></div><?php endif; ?>
<?php if (!empty($purchase['vendor_email'])): ?><div class="vendor_cell"><b>Email</b> <?= htmlspecialchars($purchase['vendor_email']); ?></div><?php endif; ?>
</div>
</div>
</div>

<!------ITEM DETAILS----->
<table class="item_list">
<tr id="bordervl">
<td>Sl No.</td>
<td>Description of Goods</td>
<td id="rightlaign">HSN/SAC</td>
<td id="rightlaign">Quantity</td>
<td id="rightlaign">Rate</td>
<td id="rightlaign">per</td>
<td id="rightlaign">GST(%)</td>
<td id="rightlaign">Amount</td>
</tr>

<?php $sl = 0; foreach ($items as $item): $sl++;
    $gst_pct = (float) $item['gst_rate'];
?>
<tr>
<td><?= $sl; ?></td>
<td><b><?= htmlspecialchars($item['productName']); ?></b><?= $item['gst_type'] === 'inclusive' ? ' <small style="color:#666">(GST incl.)</small>' : ''; ?></td>
<td id="rightlaign"><?= htmlspecialchars($item['hsn'] ?? ''); ?></td>
<td id="rightlaign"><?= inr_format($item['display_qty'], 0); ?> <?= $item['per_label']; ?><?= $item['display_qty'] == 1 ? '' : 's'; ?><?php if ($item['is_pack']): ?> (<?= inr_format((int)$item['quantity_pieces'], 0); ?> pcs)<?php endif; ?></td>
<td id="rightlaign"><?= inr_format($item['rate'], 2); ?></td>
<td id="rightlaign"><?= $item['per_label']; ?></td>
<td id="rightlaign"><?= fmt_gst_pct($gst_pct); ?>%</td>
<td id="rightlaign"><?= inr_format((float)$item['taxable_value'], 2); ?></td>
</tr>
<?php endforeach; ?>

<tr>
<td></td><td></td><td></td>
<td id="rightlaign"><b><?= inr_format($total_qty, 0); ?> Pcs</b></td>
<td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= inr_format($TotalAMount123, 2); ?></b></td>
</tr>

<?php if ($totalgstamount > 0):
    $SGST = inr_format($totalgstamount / 2, 2);
    $CGST = inr_format($totalgstamount / 2, 2);
    $__half_pct = fmt_gst_pct($__inv_gst_pct / 2);
?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>SGST (<?= $__half_pct; ?>%)</i></b></td>
<td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= $SGST; ?></b></td>
</tr>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>CGST (<?= $__half_pct; ?>%)</i></b></td>
<td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= $CGST; ?></b></td>
</tr>
<?php endif; ?>

<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Total</i></b></td>
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
<td align="center">HSN/SAC</td>
<td align="right">Taxable<br/>Value</td>
<td align="right" colspan="2">CGST</td>
<td align="right" colspan="2">SGST/UTGST</td>
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
<div style="width:99%;margin:0 auto;"><u>Declaration:</u><br/>We declare that this receipt shows the actual price of the goods purchased and that all particulars are true and correct.</div>
</td>
<td>
<table align="right">
<tr><td>A/c Name</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['acname'] ?? ''); ?></td></tr>
<tr><td>A/c Number</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['acnumber'] ?? ''); ?></td></tr>
<tr><td>Bank Name</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['bankname'] ?? ''); ?></td></tr>
<tr><td>Branch Name</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['branchname'] ?? ''); ?></td></tr>
<tr><td>IFS Code</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['ifsc'] ?? ''); ?></td></tr>
<tr><td>UPI Number</td><td>&nbsp;:&nbsp;<?= htmlspecialchars($result_Godown['upinumber'] ?? ''); ?></td></tr>
</table>
</td>
</tr>
</table>

<table width="100%" id="sealsign">
<tr>
<td width="50%" align="left">Vendor's Seal and Signature</td>
<td align="right">for <b><?= htmlspecialchars($result_Godown['gname'] ?? 'Neksomo Hygiene Industries'); ?></b></td>
</tr>
<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr>
<td></td>
<td align="right">Authorised Signatory</td>
</tr>
</table>
<div style="clear:both;"></div>
</div>
<div align="center">This is a Computer Generated Purchase Receipt</div>

                </div><!--Print content end-->

            </div>
        </div>
    </div>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
