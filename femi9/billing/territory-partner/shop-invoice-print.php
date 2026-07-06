<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$Invoice_ID = base64_decode($_REQUEST['invoiceid'] ?? '');
$inv        = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM user_invoice WHERE inv_id='$Invoice_ID' LIMIT 1"));
$getinvuser = $inv['to_user_type'] ?? 'shop';

// TP (seller) details
$tp_id   = (int)$Login_user_IDvl;
$tpRow   = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM territory_partners WHERE id='$tp_id' LIMIT 1"));

// Customer (shop) details
$customer_id = $inv['to_user_id'] ?? '';
$shop        = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM shop WHERE temp_id='$customer_id' LIMIT 1"));
$state_row   = mysqli_fetch_array(mysqli_query($db_conn, "SELECT name FROM partner_location_nodes WHERE id='{$shop['state_id']}' LIMIT 1"));
$state_name  = $state_row['name'] ?? '';
$district_row = mysqli_fetch_array(mysqli_query($db_conn, "SELECT name FROM partner_location_nodes WHERE id='{$shop['district_id']}' LIMIT 1"));
$district_name = $district_row['name'] ?? '';

// Currency
if (($_REQUEST['crcode'] ?? '') == "Default" || empty($_REQUEST['crcode'])) {
    $Currency_symbol  = "&#8377;";
    $Currency_Name    = "INR";
    $result_currency223 = null;
} else {
    $get_ccode = base64_decode($_REQUEST['crcode']);
    $result_currency223 = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM country WHERE id='$get_ccode' LIMIT 1"));
    $Currency_symbol = "&#" . $result_currency223['currency_ascii_code'] . ";";
    $Currency_Name   = $result_currency223['currency_name'];
}

// Delivery note
$Result_DLDetails = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM delivery_note WHERE inv_id='$Invoice_ID' LIMIT 1"));

// Profile / logo
$profile = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM users_profile WHERE user_tempid='$Login_user_IDvl' AND usertype='territory_partner' LIMIT 1"));
$seller_display_name = $profile['companyname'] ?? $tpRow['company_name'] ?? $tpRow['name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
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

<script type="text/javascript">
function PrintDiv() {
    var divToPrint = document.getElementById('divToPrint');
    var popupWin = window.open('', '_blank', 'width=990,height=540,left=200,top=80');
    popupWin.document.open();
    popupWin.document.write('<html><body onload="window.print()">' + divToPrint.innerHTML + '</html>');
    popupWin.document.close();
}
</script>

<table align="right">
<tr>
<td><button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button></td>
<td><button type="button" onClick="javascript:window.location='shop-invoice-add.php?invuser=<?php echo $getinvuser; ?>';" class="btn btn-success m-b-xs m-r-xs">+ New Invoice</button></td>
<td><button type="button" onClick="javascript:window.location='shop-manage-invoice.php?invuser=<?php echo $getinvuser; ?>';" class="btn btn-primary m-b-xs m-r-xs">Manage Invoice</button></td>
</tr>
</table>

<div style="clear:both;"></div>
<div align="right" style="width:100%;margin-bottom:10px;">
<select name="currency_code" class="form-control" style="width:150px;" id="currencySelect">
<?php if ($result_currency223 == null): ?>
    <option value="" hidden>Currency</option>
<?php else: ?>
    <option hidden><?php echo ucwords($result_currency223['c_name']); ?> - <?php echo ucwords($result_currency223['currency_name']); ?></option>
<?php endif; ?>
    <option value="Default">Default</option>
    <?php
    $fetch_currency = mysqli_query($db_conn, "SELECT * FROM country WHERE currency_name!='' ORDER BY c_name ASC");
    while ($result_currency = mysqli_fetch_array($fetch_currency)) {
    ?>
    <option value="<?php echo base64_encode($result_currency['id']); ?>"><?php echo ucwords($result_currency['c_name']); ?> - <?php echo ucwords($result_currency['currency_name']); ?></option>
    <?php } ?>
</select>
</div>
<script>
document.getElementById("currencySelect").addEventListener("change", function() {
    let selectedValue = this.value;
    if (selectedValue) {
        window.location.href = "shop-invoice-print.php?invoiceid=<?php echo urlencode($_REQUEST['invoiceid'] ?? ''); ?>&crcode=" + selectedValue;
    }
});
</script>

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
#bottom_bank{font-family:arial;width:100%;border-bottom:1px solid #000;}
#bottom_bank tr td:nth-child(1){border-right:1px solid #000;}
#bottom_bank table td{border:0px !important;}
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
</style>

<div class="maincontainar">

<table id="toptl">
<tr><td>Bill of Supply</td></tr>
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
<?php echo htmlspecialchars($tpRow['branch_line1'] ?? ''); ?><?php if (!empty($tpRow['branch_line2'])) echo '<br/>' . htmlspecialchars($tpRow['branch_line2']); ?><br/>
<?php echo htmlspecialchars($tpRow['branch_city'] ?? ''); ?><?php if (!empty($tpRow['branch_state'])) echo ', ' . htmlspecialchars($tpRow['branch_state']); ?><?php if (!empty($tpRow['branch_pincode'])) echo ' - ' . htmlspecialchars($tpRow['branch_pincode']); ?><br/>
<b>GSTIN/UIN :</b> <?php echo htmlspecialchars($tpRow['gstin'] ?? ''); ?><br/>
<b>Contact</b> : <?php echo htmlspecialchars($tpRow['mobile'] ?? ''); ?><br/>
<b>Email</b> : <?php echo htmlspecialchars($tpRow['email'] ?? ''); ?>
</td>
</tr>
</table>
<hr/>
<p class="cusdetaiis">
Consignee (Ship to):<br/>
<b><?php echo htmlspecialchars(ucwords($shop['name'])); ?></b><br/>
<?php echo htmlspecialchars($shop['address'] ?? ''); ?><br/>
<?php if (!empty($shop['gstin'])): ?>GSTIN: <?php echo htmlspecialchars($shop['gstin']); ?><br/><?php endif; ?>
Mobile:&nbsp;<?php echo htmlspecialchars($shop['mobile_number'] ?? ''); ?><br/>
State : <?php echo htmlspecialchars($state_name); ?>, District: <?php echo htmlspecialchars($district_name); ?>
</p>
<hr/>
<p class="cusdetaiis">
Buyer (Bill to):<br/>
<b><?php echo htmlspecialchars(ucwords($shop['name'])); ?></b><br/>
<?php echo htmlspecialchars($shop['address'] ?? ''); ?><br/>
<?php if (!empty($shop['gstin'])): ?>GSTIN: <?php echo htmlspecialchars($shop['gstin']); ?><br/><?php endif; ?>
Mobile:&nbsp;<?php echo htmlspecialchars($shop['mobile_number'] ?? ''); ?><br/>
State : <?php echo htmlspecialchars($state_name); ?>, District: <?php echo htmlspecialchars($district_name); ?>
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

<?php
$invno = 0; $TotalAMount123 = 0; $Totalquantity123 = 0;
$fetch_INVProductDetails = mysqli_query($db_conn, "SELECT * FROM user_invoice_items WHERE inv_id='$Invoice_ID' ORDER BY id DESC");
while ($result_INVProductDetails = mysqli_fetch_array($fetch_INVProductDetails)) {
    $result_ProductDetails123 = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM products WHERE id='{$result_INVProductDetails['pr_id']}' LIMIT 1"));
    $TotalAMount    = $result_INVProductDetails['qty'] * $result_INVProductDetails['amount'];
    $TotalAMount23  = $TotalAMount - $result_INVProductDetails['discount_amount'];
    $TotalAMount123 += $TotalAMount23;
    $Totalquantity123 += $result_INVProductDetails['qty'];
?>
<tr>
<td><?php echo ++$invno; ?></td>
<td><b><?php echo htmlspecialchars($result_ProductDetails123['productName']); ?></b></td>
<td id="rightlaign"><?php echo htmlspecialchars($result_ProductDetails123['hsn']); ?></td>
<td id="rightlaign"><?php echo $result_INVProductDetails['qty']; ?> Packs</td>
<td id="rightlaign"><?php echo inr_format($result_ProductDetails123['mrp'], 2); ?></td>
<td id="rightlaign"><?php echo inr_format($result_INVProductDetails['amount'], 2); ?></td>
<td id="rightlaign">Packs</td>
<td id="rightlaign"><?php echo $result_INVProductDetails['gst_percentage']; ?>%</td>
<td id="rightlaign"><?php echo $result_INVProductDetails['discount_amount']; ?> (<?php echo $result_INVProductDetails['discount_percentage']; ?>%)</td>
<td id="rightlaign"><?php echo inr_format($TotalAMount23, 2); ?></td>
</tr>
<?php } ?>

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

<?php
$gsttype = $inv['gst_type'];
$totalgstamount = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(gstamount_total) FROM user_invoice_items WHERE inv_id='$Invoice_ID'"))[0];

if ($totalgstamount > 0) {
    if ($gsttype == "inner") {
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
<?php } else { ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>IGST</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($totalgstamount, 2); ?></b></td>
</tr>
<?php } } ?>

<?php if (!empty($inv['discount']) && $inv['discount'] > 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Discount</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($inv['discount'], 2); ?></b></td>
</tr>
<?php endif; ?>

<?php if (!empty($inv['roundoff']) && $inv['roundoff'] != 0): ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Round off</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($inv['roundoff'], 2); ?></b></td>
</tr>
<?php endif; ?>

<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Total</i></b></td><td></td><td id="rightlaign"></td><td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($inv['total'] ?? 0, 2); ?></b></td>
</tr>
</table>
<div style="clear:both;"></div>

<?php
// Amount in words
$number   = (float)($inv['total'] ?? 0);
$no       = floor($number);
$digits_1 = strlen((string)$no);
$i        = 0;
$str      = [];
$words    = ['0'=>'','1'=>'one','2'=>'two','3'=>'three','4'=>'four','5'=>'five','6'=>'six','7'=>'seven','8'=>'eight','9'=>'nine','10'=>'ten','11'=>'eleven','12'=>'twelve','13'=>'thirteen','14'=>'fourteen','15'=>'fifteen','16'=>'sixteen','17'=>'seventeen','18'=>'eighteen','19'=>'nineteen','20'=>'twenty','30'=>'thirty','40'=>'forty','50'=>'fifty','60'=>'sixty','70'=>'seventy','80'=>'eighty','90'=>'ninety'];
$digits   = ['','hundred','thousand','lakh','crore'];
while ($i < $digits_1) {
    $divider = ($i == 2) ? 10 : 100;
    $number2 = floor($no % $divider);
    $no      = floor($no / $divider);
    $i      += ($divider == 10) ? 1 : 2;
    if ($number2) {
        $plural  = (($counter = count($str)) && $number2 > 9) ? 's' : null;
        $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
        $str[]   = ($number2 < 21) ? $words[$number2] . " " . $digits[$counter] . $plural . " " . $hundred
            : $words[floor($number2/10)*10] . " " . $words[$number2%10] . " " . $digits[$counter] . $plural . " " . $hundred;
    } else { $str[] = null; }
}
$str    = array_reverse($str);
$result = implode('', $str);

// Tax amount in words
$TAXnumber   = (float)$totalgstamount;
$TAXno       = floor($TAXnumber);
$TAXdigits_1 = strlen((string)$TAXno);
$TAXi        = 0;
$TAXstr      = [];
while ($TAXi < $TAXdigits_1) {
    $TAXdivider = ($TAXi == 2) ? 10 : 100;
    $TAXnumber2 = floor($TAXno % $TAXdivider);
    $TAXno      = floor($TAXno / $TAXdivider);
    $TAXi      += ($TAXdivider == 10) ? 1 : 2;
    if ($TAXnumber2) {
        $TAXplural  = (($TAXcounter = count($TAXstr)) && $TAXnumber2 > 9) ? 's' : null;
        $TAXhundred = ($TAXcounter == 1 && $TAXstr[0]) ? ' and ' : null;
        $TAXstr[]   = ($TAXnumber2 < 21) ? $words[$TAXnumber2] . " " . $digits[$TAXcounter] . $TAXplural . " " . $TAXhundred
            : $words[floor($TAXnumber2/10)*10] . " " . $words[$TAXnumber2%10] . " " . $digits[$TAXcounter] . $TAXplural . " " . $TAXhundred;
    } else { $TAXstr[] = null; }
}
$TAXstr    = array_reverse($TAXstr);
$TAXresult = implode('', $TAXstr);
?>

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

<!---------------------HSN WISE TOTAL------------------------------>
<table width="100%" id="hsnsac">
<tr>
<td width="70%" align="center">HSN/SAC</td>
<td align="right">Taxable Value</td>
</tr>
<?php
$fetchhsn = mysqli_query($db_conn, "SELECT DISTINCT hsn FROM user_invoice_items WHERE inv_id='$Invoice_ID'");
while ($resulthsn = mysqli_fetch_array($fetchhsn)) {
    $hsncode = $resulthsn['hsn'];
    $resulthsnTaxamount = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(total) FROM user_invoice_items WHERE inv_id='$Invoice_ID' AND hsn='$hsncode'"));
?>
<tr>
<td><?php echo htmlspecialchars($hsncode); ?></td>
<td align="right"><?php echo inr_format($resulthsnTaxamount[0], 2); ?></td>
</tr>
<?php }
$resulthsnTaxamount12 = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(total) FROM user_invoice_items WHERE inv_id='$Invoice_ID'"));
?>
<tr>
<td align="right"><b>Total&nbsp;</b></td>
<td align="right"><b><?php echo inr_format($resulthsnTaxamount12[0], 2); ?></b></td>
</tr>
</table>
<!---------------------HSN WISE TOTAL END------------------------------>

<table width="100%">
<tr>
<td width="50%">
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
<div align="center">This is a Computer Generated Invoice</div>

</div><!--divToPrint-->

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
