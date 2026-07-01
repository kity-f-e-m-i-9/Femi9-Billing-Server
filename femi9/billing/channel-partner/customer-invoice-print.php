<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$Invoice_ID = base64_decode($_REQUEST['invoiceid'] ?? '');

$inv = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM invoice WHERE inv_id='$Invoice_ID' LIMIT 1"));

// Seller: territory_partners
$tp_id  = (int)$Login_user_IDvl;
$seller = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM territory_partners WHERE id='$tp_id' LIMIT 1"));

// Buyer: customers
$customer_id = $inv['customer_id'] ?? 0;
$buyer       = $customer_id ? mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM customers WHERE id='$customer_id' LIMIT 1")) : null;

// Currency
if (($_REQUEST['crcode'] ?? '') == "Default" || empty($_REQUEST['crcode'])) {
    $Currency_symbol = "&#8377;";
    $Currency_Name   = "INR";
} else {
    $get_ccode       = base64_decode($_REQUEST['crcode']);
    $cRow            = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM country WHERE id='$get_ccode' LIMIT 1"));
    $Currency_symbol = "&#" . $cRow['currency_ascii_code'] . ";";
    $Currency_Name   = $cRow['currency_name'];
}
$profile = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM users_profile WHERE user_tempid='$Login_user_IDvl' AND usertype='territory_partner' LIMIT 1"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../../assets/images/neptune.png">
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar"><?php include("logo.php"); ?><?php include("femi_menu.php"); ?></div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">

<script>
function PrintDiv() {
    var d = document.getElementById('divToPrint');
    var w = window.open('', '_blank', 'width=990,height=540,left=200,top=80');
    w.document.open();
    w.document.write('<html><body onload="window.print()">' + d.innerHTML + '</html>');
    w.document.close();
}
</script>

<br/><br/>
<div align="center">
<button type="button" id="butonwidth" onclick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print Invoice</button>
<br/>
<button type="button" onclick="javascript:window.location='customer-invoice-add.php';" class="btn btn-success m-b-xs m-r-xs" id="butonwidth">+ New Invoice</button>
<br/>
<button type="button" onclick="javascript:window.location='customer-manage-invoice.php';" class="btn btn-primary m-b-xs m-r-xs" id="butonwidth">Manage Invoice</button>
</div>

<div align="center" style="margin-top:10px;">
<select name="currency_code" class="form-control" style="padding:5px;width:180px;" id="currencySelect">
    <?php if (empty($_REQUEST['crcode'])): ?>
    <option value="" hidden>Currency</option>
    <?php else: ?>
    <option hidden><?php echo ucwords($cRow['c_name'] ?? ''); ?> - <?php echo ucwords($cRow['currency_name'] ?? ''); ?></option>
    <?php endif; ?>
    <option value="Default">Default</option>
    <?php
    $resCurr = mysqli_query($db_conn, "SELECT * FROM country WHERE currency_name!='' ORDER BY c_name ASC");
    while ($rc = mysqli_fetch_array($resCurr)) {
    ?>
    <option value="<?php echo base64_encode($rc['id']); ?>"><?php echo ucwords($rc['c_name']); ?> - <?php echo ucwords($rc['currency_name']); ?></option>
    <?php } ?>
</select>
</div>
<script>
document.getElementById("currencySelect").addEventListener("change", function() {
    let v = this.value;
    if (v) { window.location.href = "customer-invoice-print.php?invoiceid=<?php echo urlencode($_REQUEST['invoiceid'] ?? ''); ?>&crcode=" + v; }
});
</script>

<div style="clear:both;"></div>
<div style="display:none;">
<div id="divToPrint">

<style>
#butonwidth{width:180px!important;}
.maincontainar{width:100%;height:auto;border:1px solid #000;}
.maincontainar hr{border-bottom:1px solid #000;}
#toptl{width:100%;padding:5px;font-family:arial;font-weight:bold;border-bottom:1px solid #000;text-align:center;font-size:22px;}
.second_containar{width:100%;border-collapse:collapse;}
.second_containar td:nth-child(1){border-right:1px solid #000;padding:0px;}
#second_topvl{width:100%;padding:5px;font-family:arial;border-bottom:1px solid #000;border-collapse:collapse;}
#second_topvl td{padding:5px;}
#border_nbottom td{border-bottom:1px solid #000;}
#noneborder td{border:0px!important;font-family:arial;font-size:14px;line-height:20px;}
.item_list{width:100%;border-top:1px solid #000;border-collapse:collapse;font-family:arial;}
.item_list td{border-right:1px solid #000;padding:5px;font-size:14px;vertical-align:top;}
#bordervl td{border-bottom:1px solid #000;padding:5px;}
#rightlaign{text-align:right;}
#bottombordervl{border-top:1px solid #000;border-bottom:1px solid #000;}
.amount_word{font-family:arial;padding:4px;border-bottom:1px solid #000;}
#bottom_bank{font-family:arial;width:100%;border-bottom:1px solid #000;}
#bottom_bank tr td:nth-child(1){border-right:1px solid #000;}
#vlnotes{font-family:arial;width:100%;}
#vlnotes tr td:nth-child(1){border-right:1px solid #000;width:35%;}
#cmpname{font-size:17px;font-weight:bold;}
.cusdetaiis{margin-left:10px;font-family:arial;font-size:14px;line-height:20px;}
#shiippingaddress{margin-left:10px;font-family:arial;}
#hsnsac{border-collapse:collapse;}
#hsnsac tr td{border:1px solid #000;}
#hsnsac tr td:nth-child(1){border-left:0px;}
#hsnsac tr td:nth-child(2){border-right:0px;}
#sealsign{border-collapse:collapse;}
#sealsign td{padding:3px;}
#sealsign tr:nth-child(1){border-top:1px solid #000;}
#sealsign tr td:nth-child(1){border-right:1px solid #000;}
</style>

<div class="maincontainar">
<table id="toptl"><tr><td>Bill of Supply</td></tr></table>

<table class="second_containar">
<tr valign="top">
<td width="50%">
<table id="noneborder"><tr valign="top">
<td valign="top">
<span id="cmpname"><?php echo htmlspecialchars($seller['company_name'] ?? $seller['name'] ?? ''); ?></span><br/>
<?php echo htmlspecialchars($seller['branch_line1'] ?? ''); ?><?php if (!empty($seller['branch_line2'])) echo ", " . htmlspecialchars($seller['branch_line2']); ?><br/>
<?php echo htmlspecialchars($seller['branch_city'] ?? ''); ?>, <?php echo htmlspecialchars($seller['branch_state'] ?? ''); ?> - <?php echo htmlspecialchars($seller['branch_pincode'] ?? ''); ?><br/>
<b>GSTIN/UIN:</b> <?php echo htmlspecialchars($seller['gstin'] ?? ''); ?><br/>
<b>Contact:</b> <?php echo htmlspecialchars($seller['mobile'] ?? ''); ?><br/>
</td>
</tr></table>
<hr/>
<?php if ($buyer): ?>
<p class="cusdetaiis">
Consignee (Ship to):<br/>
<b><?php echo htmlspecialchars(ucwords($buyer['name'])); ?></b><br/>
GSTIN: <?php echo htmlspecialchars($buyer['gstin'] ?? ''); ?><br/>
Mobile: <?php echo htmlspecialchars($buyer['mobile']); ?><br/>
<?php echo htmlspecialchars($buyer['address'] ?? ''); ?>
</p>
<hr/>
<p class="cusdetaiis">
Buyer (Bill to):<br/>
<b><?php echo htmlspecialchars(ucwords($buyer['name'])); ?></b><br/>
GSTIN: <?php echo htmlspecialchars($buyer['gstin'] ?? ''); ?><br/>
Mobile: <?php echo htmlspecialchars($buyer['mobile']); ?><br/>
<?php echo htmlspecialchars($buyer['address'] ?? ''); ?>
</p>
<?php else: ?>
<p class="cusdetaiis">Consignee (Ship to):<br/><b>Walking Customer</b></p>
<hr/>
<p class="cusdetaiis">Buyer (Bill to):<br/><b>Walking Customer</b></p>
<?php endif; ?>
</td>
<td valign="top">
<?php
$dlRes = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM delivery_note WHERE inv_id='$Invoice_ID' LIMIT 1"));
?>
<table id="second_topvl">
<tr id="border_nbottom">
<td>Invoice #<br/><b><?php echo htmlspecialchars($inv['inv_number'] ?? ''); ?></b></td>
<td>Invoice Date:<br/><b><?php echo date("d M Y", strtotime($inv['date'] ?? '')); ?></b></td>
</tr>
<tr id="border_nbottom" valign="top"><td height="50">Delivery Note<br/><?php echo htmlspecialchars($dlRes['dl_note'] ?? ''); ?></td><td>Mode/Terms of Payment<br/><?php echo htmlspecialchars($dlRes['mode_pmnt'] ?? ''); ?></td></tr>
<tr id="border_nbottom" valign="top"><td height="50">Reference No. &amp; Date<br/><?php if (!empty($dlRes['ref_no'])) echo htmlspecialchars($dlRes['ref_no']); if (!empty($dlRes['ref_date'])) echo ", " . date("d/m/Y", strtotime($dlRes['ref_date'])); ?></td><td>Other References<br/><?php echo htmlspecialchars($dlRes['ot_ref'] ?? ''); ?></td></tr>
<tr id="border_nbottom" valign="top"><td height="50">Buyer's Order No.<br/><?php echo htmlspecialchars($dlRes['order_no'] ?? ''); ?></td><td>Dated<br/><?php if (!empty($dlRes['dated'])) echo date("d/m/Y", strtotime($dlRes['dated'])); ?></td></tr>
<tr id="border_nbottom" valign="top"><td height="50">Dispatch Doc No.<br/><?php echo htmlspecialchars($dlRes['dispatch_doc_no'] ?? ''); ?></td><td>Delivery Note Date<br/><?php if (!empty($dlRes['dlnote_date'])) echo date("d/m/Y", strtotime($dlRes['dlnote_date'])); ?></td></tr>
<tr id="border_nbottom" valign="top"><td height="50">Dispatched through<br/><?php echo htmlspecialchars($dlRes['dispatch_through'] ?? ''); ?></td><td>Destination<br/><?php echo htmlspecialchars($dlRes['destination'] ?? ''); ?></td></tr>
</table>
<p id="shiippingaddress">Terms of Delivery<br/><?php echo htmlspecialchars($dlRes['terms'] ?? ''); ?></p>
</td>
</tr>
</table>

<!-- Items -->
<table class="item_list">
<tr id="bordervl">
<td>Sl No.</td><td>Description of Goods</td><td id="rightlaign">HSN/SAC</td>
<td id="rightlaign">Quantity</td><td id="rightlaign">Rate</td><td id="rightlaign">per</td>
<td id="rightlaign">GST(%)</td><td id="rightlaign">Disc</td><td id="rightlaign">Amount</td>
</tr>
<?php
$resItems = mysqli_query($db_conn, "SELECT ii.*, p.productName, p.hsn AS phsn FROM invoice_items ii LEFT JOIN products p ON ii.pr_id=p.id WHERE ii.inv_id='$Invoice_ID' ORDER BY ii.id DESC");
$TotalAMount123 = 0; $Totalquantity123 = 0; $invno = 0;
while ($ri = mysqli_fetch_array($resItems)) {
    $lineAmt   = $ri['qty'] * $ri['amount'];
    $lineAfter = $lineAmt - (float)$ri['discount_amount'];
    $TotalAMount123   += $lineAfter;
    $Totalquantity123 += $ri['qty'];
    $invno++;
?>
<tr>
<td><?php echo $invno; ?></td>
<td><b><?php echo htmlspecialchars($ri['productName']); ?></b></td>
<td id="rightlaign"><?php echo htmlspecialchars($ri['hsn']); ?></td>
<td id="rightlaign"><?php echo $ri['qty']; ?> Packs</td>
<td id="rightlaign"><?php echo number_format((float)$ri['amount'], 2, '.', ''); ?></td>
<td id="rightlaign">Packs</td>
<td id="rightlaign"><?php echo $ri['gst_percentage']; ?>%</td>
<td id="rightlaign"><?php echo number_format((float)$ri['discount_amount'], 2, '.', ''); ?> (<?php echo number_format((float)$ri['discount_percentage']); ?>%)</td>
<td id="rightlaign"><?php echo number_format($lineAfter, 2, '.', ''); ?></td>
</tr>
<?php } ?>
<tr><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
<tr id="bottombordervl">
<td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Totalquantity123; ?> Packs</b></td>
<td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo number_format($TotalAMount123, 2, '.', ''); ?></b></td>
</tr>

<?php
$gsttype      = $inv['gst_type'] ?? 'inner';
$totalgst     = (float)(mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(gstamount_total) FROM invoice_items WHERE inv_id='$Invoice_ID'"))[0]);
if ($totalgst > 0) {
    if ($gsttype == 'inner') {
        $half = number_format($totalgst / 2, 2, '.', '');
?>
<tr id="bottombordervl"><td></td><td id="rightlaign"><b><i>SGST</i></b></td><td></td><td></td><td></td><td></td><td></td><td></td><td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo $half; ?></b></td></tr>
<tr id="bottombordervl"><td></td><td id="rightlaign"><b><i>CGST</i></b></td><td></td><td></td><td></td><td></td><td></td><td></td><td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo $half; ?></b></td></tr>
<?php } else { ?>
<tr id="bottombordervl"><td></td><td id="rightlaign"><b><i>IGST</i></b></td><td></td><td></td><td></td><td></td><td></td><td></td><td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo number_format($totalgst, 2, '.', ''); ?></b></td></tr>
<?php } } ?>

<?php if ((float)($inv['discount'] ?? 0) > 0): ?>
<tr id="bottombordervl"><td></td><td id="rightlaign"><b><i>Discount</i></b></td><td></td><td></td><td></td><td></td><td></td><td></td><td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo number_format((float)$inv['discount'], 2, '.', ''); ?></b></td></tr>
<?php endif; ?>
<?php if ((float)($inv['roundoff'] ?? 0) != 0): ?>
<tr id="bottombordervl"><td></td><td id="rightlaign"><b><i>Round off</i></b></td><td></td><td></td><td></td><td></td><td></td><td></td><td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo number_format((float)$inv['roundoff'], 2, '.', ''); ?></b></td></tr>
<?php endif; ?>
<tr id="bottombordervl"><td></td><td id="rightlaign"><b><i>Total</i></b></td><td></td><td></td><td></td><td></td><td></td><td></td><td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo number_format((float)($inv['total'] ?? 0), 2, '.', ''); ?></b></td></tr>
</table>

<?php
// Amount in words
$number = (float)($inv['total'] ?? 0);
$no     = floor($number);
$words  = ['0'=>'','1'=>'one','2'=>'two','3'=>'three','4'=>'four','5'=>'five','6'=>'six','7'=>'seven','8'=>'eight','9'=>'nine','10'=>'ten','11'=>'eleven','12'=>'twelve','13'=>'thirteen','14'=>'fourteen','15'=>'fifteen','16'=>'sixteen','17'=>'seventeen','18'=>'eighteen','19'=>'nineteen','20'=>'twenty','30'=>'thirty','40'=>'forty','50'=>'fifty','60'=>'sixty','70'=>'seventy','80'=>'eighty','90'=>'ninety'];
$digits = ['','hundred','thousand','lakh','crore'];
$i2 = 0; $str = [];
$digits_1 = strlen((string)$no);
while ($i2 < $digits_1) {
    $divider = ($i2 == 2) ? 10 : 100;
    $n2 = floor($no % $divider); $no = floor($no / $divider); $i2 += ($divider == 10) ? 1 : 2;
    if ($n2) {
        $plural  = (($counter = count($str)) && $n2 > 9) ? 's' : null;
        $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
        $str[]   = ($n2 < 21) ? $words[$n2] . " " . $digits[$counter] . $plural . " " . $hundred
            : $words[floor($n2/10)*10] . " " . $words[$n2%10] . " " . $digits[$counter] . $plural . " " . $hundred;
    } else { $str[] = null; }
}
$str    = array_reverse($str);
$result = implode('', $str);
?>

<table width="100%">
<tr><td width="70%">Amount Chargeable (in words)</td><td align="right">E. &amp; O.E</td></tr>
<tr><td><b><?php echo $Currency_Name; ?> <?php echo ucwords($result); ?> Only</b></td><td></td></tr>
</table>

<table width="100%" id="hsnsac">
<tr><td width="70%" align="center">HSN/SAC</td><td align="right">Taxable Value</td></tr>
<?php
$resHSN = mysqli_query($db_conn, "SELECT DISTINCT hsn FROM invoice_items WHERE inv_id='$Invoice_ID'");
while ($rh = mysqli_fetch_array($resHSN)) {
    $hc  = $rh['hsn'];
    $hta = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(total) FROM invoice_items WHERE inv_id='$Invoice_ID' AND hsn='$hc'"));
?>
<tr><td><?php echo htmlspecialchars($hc); ?></td><td align="right"><?php echo number_format((float)$hta[0], 2, '.', ''); ?></td></tr>
<?php }
$htaTotal = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(total) FROM invoice_items WHERE inv_id='$Invoice_ID'"));
?>
<tr><td align="right"><b>Total&nbsp;</b></td><td align="right"><b><?php echo number_format((float)$htaTotal[0], 2, '.', ''); ?></b></td></tr>
</table>

<table width="100%">
<tr><td>Tax Amount (in words): <b><?php echo $totalgst > 0 ? $Currency_Name . ' ' . ucwords(implode('', array_reverse([]))) . ' Only' : 'Nil'; ?></b></td></tr>
<tr><td><div style="width:99%;margin:0 auto;"><u>Declaration:</u><br/>We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</div></td></tr>
</table>

<?php if (!empty($profile['acname'])): ?>
<table width="100%">
<tr>
<td width="50%"></td>
<td>
<table align="right">
<tr><td>A/c Name</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($profile['acname']); ?></td></tr>
<tr><td>A/c Number</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($profile['acnumber']); ?></td></tr>
<tr><td>Bank Name</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($profile['bankname']); ?></td></tr>
<tr><td>Branch Name</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($profile['branchname']); ?></td></tr>
<tr><td>IFS Code</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($profile['ifsc']); ?></td></tr>
<tr><td>UPI Number</td><td>&nbsp;:&nbsp;<?php echo htmlspecialchars($profile['upinumber']); ?></td></tr>
</table>
</td>
</tr>
</table>
<?php endif; ?>
<table width="100%" id="sealsign">
<tr><td width="50%" align="left">Customer's Seal and Signature</td><td align="right">for <b><?php echo htmlspecialchars($seller['company_name'] ?? $seller['name'] ?? ''); ?></b></td></tr>
<tr><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td></td><td align="right">Authorised Signatory</td></tr>
</table>
<div align="center">This is a Computer Generated Invoice</div>
</div><!--divToPrint-->
</div><!--display:none-->
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>
</body>
</html>
