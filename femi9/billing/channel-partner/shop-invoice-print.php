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
$state_row   = mysqli_fetch_array(mysqli_query($db_conn, "SELECT st_name FROM state WHERE id='{$shop['state_id']}' LIMIT 1"));
$state_name  = $state_row['st_name'] ?? '';

// Currency
$Currency_symbol = "&#8377;";
$Currency_Name   = "INR";
if (!empty($_REQUEST['crcode']) && $_REQUEST['crcode'] !== 'Default') {
    $cc_id = base64_decode($_REQUEST['crcode']);
    $cr = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM country WHERE id='$cc_id' LIMIT 1"));
    if ($cr) { $Currency_symbol = "&#".$cr['currency_ascii_code'].";"; $Currency_Name = $cr['currency_name']; }
}

// Delivery note
$dlnote = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM delivery_note WHERE inv_id='$Invoice_ID' LIMIT 1"));

// Profile / logo
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
                var w = window.open('','_blank','width=990,height=540,left=200,top=80');
                w.document.open();
                w.document.write('<html><body onload="window.print()">' + d.innerHTML + '</html>');
                w.document.close();
            }
            </script>
            <br/><br/>
            <div align="center">
                <button type="button" id="butonwidth" onclick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print Invoice</button><br/>
                <button type="button" onclick="window.location='shop-invoice-add.php?invuser=<?php echo $getinvuser; ?>';" class="btn btn-success m-b-xs m-r-xs" id="butonwidth">+ New Invoice</button><br/>
                <button type="button" onclick="window.location='shop-manage-invoice.php?invuser=<?php echo $getinvuser; ?>';" class="btn btn-primary m-b-xs m-r-xs" id="butonwidth">Manage Invoice</button>
            </div>

            <div align="center" style="margin-top:10px;">
            <select class="form-control" style="width:180px;" onchange="window.location='shop-invoice-print.php?invoiceid=<?php echo $_REQUEST['invoiceid']; ?>&crcode='+this.value;">
                <option value="Default">Default (INR)</option>
                <?php $crs = mysqli_query($db_conn, "SELECT * FROM country WHERE currency_name!='' ORDER BY c_name ASC");
                while ($cr = mysqli_fetch_array($crs)) { ?>
                <option value="<?php echo base64_encode($cr['id']); ?>"><?php echo $cr['c_name']; ?> - <?php echo $cr['currency_name']; ?></option>
                <?php } ?>
            </select>
            </div>

            <div style="display:none;"><div id="divToPrint">
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
#cmpname{font-size:17px;font-weight:bold;}
.cusdetaiis{margin-left:10px;font-family:arial;font-size:14px;line-height:20px;}
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
<td><?php if (!empty($profile['logo'])): ?><img src="<?php echo $profile['logo']; ?>" style="width:95px;border-radius:10px;"/><?php endif; ?></td>
<td valign="top">
<span id="cmpname"><?php echo htmlspecialchars($tpRow['company_name'] ?: $tpRow['name']); ?></span><br/>
<?php echo htmlspecialchars($tpRow['branch_line1'] . ' ' . $tpRow['branch_city']); ?><br/>
<b>GSTIN/UIN:</b> <?php echo htmlspecialchars($tpRow['gstin']); ?><br/>
<b>State:</b> <?php echo htmlspecialchars($tpRow['branch_state']); ?><br/>
<b>Contact:</b> <?php echo htmlspecialchars($tpRow['mobile']); ?>
</td>
</tr></table>
<hr/>
<p class="cusdetaiis">
Consignee (Ship to):<br/>
<b><?php echo ucwords(htmlspecialchars($shop['name'])); ?></b><br/>
<?php echo htmlspecialchars($shop['address']); ?><br/>
<?php if (!empty($shop['gstin'])): ?>GSTIN: <?php echo $shop['gstin']; ?><br/><?php endif; ?>
Mobile: <?php echo $shop['mobile_number']; ?><br/>
State: <?php echo $state_name; ?>
</p>
<hr/>
<p class="cusdetaiis">
Buyer (Bill to):<br/>
<b><?php echo ucwords(htmlspecialchars($shop['name'])); ?></b><br/>
<?php echo htmlspecialchars($shop['address']); ?><br/>
<?php if (!empty($shop['gstin'])): ?>GSTIN: <?php echo $shop['gstin']; ?><br/><?php endif; ?>
Mobile: <?php echo $shop['mobile_number']; ?>
</p>
</td>
<td valign="top">
<table id="second_topvl">
<tr id="border_nbottom">
<td>Invoice #<br/><b><?php echo $inv['inv_number']; ?></b></td>
<td>Invoice Date:<br/><b><?php echo date("d M Y", strtotime($inv['date'])); ?></b></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Delivery Note<br/><?php echo $dlnote['dl_note'] ?? ''; ?></td>
<td>Mode/Terms of Payment<br/><?php echo $dlnote['mode_pmnt'] ?? ''; ?></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Reference No. &amp; Date<br/><?php if (!empty($dlnote['ref_no'])) echo $dlnote['ref_no'].','; if (!empty($dlnote['ref_date'])) echo date("d/m/Y",strtotime($dlnote['ref_date'])); ?></td>
<td>Other References<br/><?php echo $dlnote['ot_ref'] ?? ''; ?></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Dispatched through<br/><?php echo $dlnote['dispatch_through'] ?? ''; ?></td>
<td>Destination<br/><?php echo $dlnote['destination'] ?? ''; ?></td>
</tr>
</table>
<p style="margin-left:10px;">Terms of Delivery<br/><?php echo $dlnote['terms'] ?? ''; ?></p>
</td>
</tr>
</table>

<!-- Items -->
<table class="item_list">
<tr id="bordervl">
<td>Sl No.</td><td>Description of Goods</td><td id="rightlaign">HSN/SAC</td>
<td id="rightlaign">Quantity</td><td id="rightlaign">MRP</td><td id="rightlaign">Rate</td>
<td id="rightlaign">per</td><td id="rightlaign">GST(%)</td><td id="rightlaign">Disc</td><td id="rightlaign">Amount</td>
</tr>
<?php
$invno = 0; $TotalAMount123 = 0; $Totalquantity123 = 0;
$items = mysqli_query($db_conn, "SELECT * FROM user_invoice_items WHERE inv_id='$Invoice_ID' ORDER BY id DESC");
while ($ri = mysqli_fetch_array($items)) {
    $pr = mysqli_fetch_array(mysqli_query($db_conn, "SELECT * FROM products WHERE id='{$ri['pr_id']}' LIMIT 1"));
    $lineTotal = ($ri['qty'] * $ri['amount']) - $ri['discount_amount'];
    $TotalAMount123 += $lineTotal;
    $Totalquantity123 += $ri['qty'];
?>
<tr>
<td><?php echo ++$invno; ?></td>
<td><b><?php echo $pr['productName']; ?></b></td>
<td id="rightlaign"><?php echo $pr['hsn']; ?></td>
<td id="rightlaign"><?php echo $ri['qty']; ?> Packs</td>
<td id="rightlaign"><?php echo inr_format($pr['mrp'], 2); ?></td>
<td id="rightlaign"><?php echo inr_format($ri['amount'], 2); ?></td>
<td id="rightlaign">Packs</td>
<td id="rightlaign"><?php echo $ri['gst_percentage']; ?>%</td>
<td id="rightlaign"><?php echo inr_format($ri['discount_amount'], 2); ?> (<?php echo inr_format($ri['discount_percentage'], 0); ?>%)</td>
<td id="rightlaign"><?php echo inr_format($lineTotal, 2); ?></td>
</tr>
<?php } ?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b></b></td><td></td>
<td id="rightlaign"><b><?php echo $Totalquantity123; ?> Packs</b></td>
<td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($TotalAMount123, 2); ?></b></td>
</tr>

<!-- GST -->
<?php
$gsttype = $inv['gst_type'];
$totalgst = mysqli_fetch_array(mysqli_query($db_conn, "SELECT SUM(gstamount_total) FROM user_invoice_items WHERE inv_id='$Invoice_ID'"))[0];
if ($totalgst > 0):
    if ($gsttype === 'inner'): $half = inr_format($totalgst/2, 2); ?>
<tr id="bottombordervl"><td></td><td id="rightlaign"><b><i>SGST</i></b></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo $half; ?></b></td></tr>
<tr id="bottombordervl"><td></td><td id="rightlaign"><b><i>CGST</i></b></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo $half; ?></b></td></tr>
<?php else: ?>
<tr id="bottombordervl"><td></td><td id="rightlaign"><b><i>IGST</i></b></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($totalgst, 2); ?></b></td></tr>
<?php endif; endif; ?>

<?php if ($inv['discount'] > 0): ?>
<tr id="bottombordervl"><td></td><td id="rightlaign"><b><i>Discount</i></b></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($inv['discount'], 2); ?></b></td></tr>
<?php endif; ?>
<?php if ($inv['courier_charges'] > 0): ?>
<tr id="bottombordervl"><td></td><td id="rightlaign"><b><i>Courier Charges</i></b></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($inv['courier_charges'], 2); ?></b></td></tr>
<?php endif; ?>
<tr id="bottombordervl"><td></td><td id="rightlaign"><b><i>Total</i></b></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td id="rightlaign"><b><?php echo $Currency_symbol; ?>&nbsp;<?php echo inr_format($inv['total'], 2); ?></b></td></tr>
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
<table id="sealsign" style="width:100%;">
<tr><td width="50%" style="padding:60px 10px 10px 10px;">Receiver's Signature</td><td style="padding:60px 10px 10px 10px;text-align:right;">for <?php echo htmlspecialchars($tpRow['company_name'] ?: $tpRow['name']); ?><br/><br/>Authorised Signatory</td></tr>
</table>
</div>
</div></div><!-- /divToPrint -->
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
