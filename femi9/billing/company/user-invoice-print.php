<?php include("checksession.php"); require_once("include/GodownAccess.php"); date_default_timezone_set("Asia/Kolkata");
error_reporting(0);
include("config.php");
$Invoice_ID=$_REQUEST['invoiceid'];
$Invoice_ID=base64_decode($Invoice_ID);

$select_Invoice_Details="select * from user_invoice where inv_id='$Invoice_ID'";
$fetch_Invoice_Details=mysqli_query($db_conn,$select_Invoice_Details);
$result_Invoice_Details=mysqli_fetch_array($fetch_Invoice_Details);
// Needed early (before the item table) as well as in the GST summary
// section further down, so it's pulled up here rather than declared once
// down there.
$gsttype=$result_Invoice_Details['gst_type'];

// Also needed early for the Tax Invoice / Bill of Supply heading below —
// same query the GST summary section further down reuses via $totalgstamount.
$select_sum_gstamount="select sum(gstamount_total) from user_invoice_items where inv_id='$Invoice_ID'";
$fetch_sum_gstamount=mysqli_query($db_conn,$select_sum_gstamount);
$result_sum_gstamount=mysqli_fetch_array($fetch_sum_gstamount);
$totalgstamount=$result_sum_gstamount[0];
$invoice_heading = $totalgstamount > 0 ? 'Tax Invoice' : 'Bill of Supply';

// Trims a rate like 1.50 down to "1.5" or 9.00 down to "9" — CGST/SGST is
// always exactly half the item's GST%, which is often a non-whole number
// (e.g. 3% GST -> 1.5% + 1.5%), so this avoids both misleading rounding
// (inr_format's 0-decimal rounding would show 1.5% as "2%") and clutter
// like "1.50%" for a value that's actually whole.
function fmt_gst_pct($v) {
    return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
}

//customer details
$getinvuser=$result_Invoice_Details['to_user_type'];

if($getinvuser=="candf")
{
	$tablename="c_and_f";
	}
else if($getinvuser=="super_stockiest")
{
	$tablename="super_stockiest";
	}
else if($getinvuser=="stockiest")
{
	$tablename="stockiest";
	}
else if($getinvuser=="super_distributor")
{
	$tablename="super_distributor";
	}
	else if($getinvuser=="distributor")
{
	$tablename="distributor";
	}
	
	else if($getinvuser=="outlet")
{
	$tablename="outlet";
	}
	
else
{
	//$tablename="shop";
	}
	
	
$customer_id=$result_Invoice_Details['to_user_id'];
$select_Cusotmer_Details="select * from ".$tablename." where temp_id='$customer_id'";
$fetch_Customer_Details=mysqli_query($db_conn,$select_Cusotmer_Details);
$result_Customer_Details=mysqli_fetch_array($fetch_Customer_Details);

//state details
$state_id=$result_Customer_Details['state_id'];
$select_state_dtails="select * from state where id='$state_id'";
$fetch_state_dtails=mysqli_query($db_conn,$select_state_dtails);
$result_state_dtails=mysqli_fetch_array($fetch_state_dtails);
$state_name=$result_state_dtails['st_name'];

//district details
$district_id=$result_Customer_Details['district_id'];
$select_distict="select * from district where id='$district_id'";
$fetch_district=mysqli_query($db_conn,$select_distict);
$result_district=mysqli_fetch_array($fetch_district);
$district_name=	$result_district['dist_name'];

//Taluk details
$taluk_id=$result_Customer_Details['taluk_id'];
$select_taluk="select * from taluk where id='$taluk_id'";
$fetch_taluk=mysqli_query($db_conn,$select_taluk);
$result_taluk=mysqli_fetch_array($fetch_taluk);
$taluk_name =	$result_taluk['taluk'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags 
    <!-- Title -->
    <title>Invoice : <?php echo $business_name;?></title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/plugins/highlight/styles/github-gist.css" rel="stylesheet">


    <!-- Theme Styles -->
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
</head>

<body>
    <div class="app align-content-stretch d-flex flex-wrap">
        <div class="app-sidebar">
            <?php include("logo.php");?>
            <?php include("femi_menu.php");?>
        </div>
        <div class="app-container">
          <?php include("app-header.php");?>
            <div class="app-content">
			
			<script type="text/javascript">     
           function PrintDiv() {    
           var divToPrint = document.getElementById('divToPrint');
           var popupWin = window.open('', '_blank', 'width=990,height=540,left=200,top=80');
           popupWin.document.open();
           popupWin.document.write('<html><body onload="window.print()">' + divToPrint.innerHTML + '</html>');
           popupWin.document.close();}
</script>

<script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
<script src="../../assets/js/whatsapp-invoice-share.js?v=3"></script>
<?php
$waShareOpts = "{elementId:'divToPrint', mobile:'" . htmlspecialchars($result_Customer_Details['mobile_number'] ?? '', ENT_QUOTES) . "', invoiceNumber:'" . htmlspecialchars($result_Invoice_Details['inv_number'] ?? '', ENT_QUOTES) . "', fileName:'Invoice_" . htmlspecialchars($result_Invoice_Details['inv_number'] ?? '', ENT_QUOTES) . "', businessName:'" . htmlspecialchars($business_name ?? '', ENT_QUOTES) . "', button:this}";
?>

<?php if($LoginusertypeGET=="admin"){?>

			<table align="right">
			<tr>
			<td><button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button></td>
			<td><button type="button" id="waShareBtn" onclick="shareInvoiceToWhatsApp(<?=$waShareOpts;?>);" class="btn btn-success m-b-xs m-r-xs"><i class="material-icons" style="font-size:16px;vertical-align:middle;">share</i> Share to WhatsApp</button></td>
			<td><button type="button" onClick="javascript:window.location='user-invoice-add?invuser=<?=$getinvuser;?>';" class="btn btn-success m-b-xs m-r-xs">+ New Invoice</button></td>
			<td><button type="button" onClick="javascript:window.location='user-manage-invoice?invuser=<?=$getinvuser;?>';" class="btn btn-primary m-b-xs m-r-xs">Manage Invoice</button></td>
			</tr>
			</table>
			<br/>

<?php }else{?>

<table align="right">
			<tr>
			<td><button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button></td>
			<td><button type="button" id="waShareBtn" onclick="shareInvoiceToWhatsApp(<?=$waShareOpts;?>);" class="btn btn-success m-b-xs m-r-xs"><i class="material-icons" style="font-size:16px;vertical-align:middle;">share</i> Share to WhatsApp</button></td>
			<td><button type="button" onClick="javascript:window.location='invoiceDLN?invuser=<?=$getinvuser;?>';" class="btn btn-primary m-b-xs m-r-xs">Go Back</button></td>
			</tr>
			</table>

<?php }?>

<?php if (($_REQUEST['whatsapp_share'] ?? '') === '1'): ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () { document.getElementById('waShareBtn').click(); }, 300);
});
</script>
<?php endif; ?>


<!-------------------------------------->
<!-------------Currency----------------->
<?php
if($_REQUEST['crcode']=="Default" || $_REQUEST['crcode']==NULL)
{
$Currency_symbol="&#8377;";
$Currency_Name="INR";
}
else{
$get_ccode=base64_decode($_REQUEST['crcode']);
//
$select_currency223="select * from country where id='$get_ccode'";
$fetch_currency223=mysqli_query($db_conn,$select_currency223);
$result_currency223=mysqli_fetch_array($fetch_currency223);
//
$Currency_symbol="&#".$result_currency223['currency_ascii_code'].";";
$Currency_Name=$result_currency223['currency_name'];
}
?>

<div style="clear:both;"></div>
<div align="right" style="width:100%;margin-bottom:10px;">
<select name="currency_code" class="form-control" style="width:150px;" id="currencySelect">
			<?php if($get_ccode==NULL){?>
			<option value="" hidden>Currency</option>
<?php }else{?>
<option hidden><?=ucwords($result_currency223['c_name']);?> - <?=ucwords($result_currency223['currency_name']);?></option>
<?php }?>
			<option value="Default">Default</option>
			<?php $select_currency="select * from country where currency_name!='' order by c_name asc";
$fetch_currency=mysqli_query($db_conn,$select_currency);
while($result_currency=mysqli_fetch_array($fetch_currency))
{
			?>
			<option value="<?=base64_encode($result_currency['id']);?>"><?=ucwords($result_currency['c_name']);?> - <?=ucwords($result_currency['currency_name']);?></option>
<?php }?>
			</select>
			</div>
			
			<script>
    document.getElementById("currencySelect").addEventListener("change", function() {
        let selectedValue = this.value;
        if (selectedValue) {
            window.location.href = "user-invoice-print?invoiceid=<?=$_REQUEST['invoiceid'] ?>&crcode=" + selectedValue;
        }
    });
</script>
<!------------Currency end ***---------->
<!-------------------------------------->


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
<tr>
<td><?=htmlspecialchars($invoice_heading);?></td>
</tr>
</table>

<!------INVOICE DETAILS----->
<table class="second_containar">
<tr valign="top">
<td width="50%">
<table id="noneborder">
<?php
//get godown details
$from_user_id=$result_Invoice_Details['from_user_id'];
$select_Godown="select * from company_godown where id='$from_user_id' AND " . godown_finance_filter_sql($db_conn);
$fetch_Godown=mysqli_query($db_conn,$select_Godown);
$result_Godown=mysqli_fetch_array($fetch_Godown);
?>
<tr valign="top">
<td>
<?php if($result_Godown['logo']!=NULL){?>
<img src="<?=$result_Godown['logo'];?>" style="width:150px;margin-right:5px;"/>
<?php }?>
</td>

<td valign="top">
<span id="cmpname"><?=$result_Godown['gname'];?></span><br/>
<?=$result_Godown['address_line1'];?><br/>
<?=$result_Godown['address_line2'];?><br/>
<b>GSTIN/UIN :</b> <?=$result_Godown['gstin'];?><br/>
<b>State Name</b> : <?=$result_Godown['state'];?> <b>Code</b> : <?=$result_Godown['state_code'];?><br/>
<b>Contact</b> : <?=$result_Godown['contact'];?><br/>
<b>Email</b> : <?=$result_Godown['email'];?>
</td>
</tr>
</table>
<hr/>

<?php 
//fetch user profile
$select_userprofile="select * from users_profile where user_tempid='$customer_id' and usertype='$getinvuser'";
$fetch_userprofile=mysqli_query($db_conn,$select_userprofile);
$result_userprofile=mysqli_fetch_array($fetch_userprofile);

$selectstockreq="select * from stock_request where reqid='$Invoice_ID'";
$fetchstockreq=mysqli_query($db_conn,$selectstockreq);
$resultstockreq=mysqli_fetch_array($fetchstockreq);

if($resultstockreq['delivery_address']!=NULL){$deliveryaddress=$resultstockreq['delivery_address'];}
else{$deliveryaddress=$result_userprofile['deliveryaddress'];}
?>
<p class="cusdetaiis">
Consignee (Ship to):<br/>
<b><?=$result_userprofile['companyname'];?></b><br/>
GSTIN: <?=$result_Customer_Details['gstin'];?><br/>
Mobile:&nbsp;<?=$result_Customer_Details['mobile_number'];?><br/>
<?=$deliveryaddress;?>
</p>


<hr/>
<p class="cusdetaiis">
Buyer (Bill to):<br/>
<b><?=$result_userprofile['companyname'];?></b><br/>
GSTIN: <?=$result_Customer_Details['gstin'];?><br/>
Mobile:&nbsp;<?=$result_Customer_Details['mobile_number'];?><br/>
State : <?=$state_name;?>, District: <?=$district_name?>, Taluk : <?=$taluk_name?>
</p>
</td>
<td valign="top">
<table id="second_topvl">
<tr id="border_nbottom">
<td>Invoice #<br/><b><?php echo $result_Invoice_Details['inv_number'];?></b></td>
<td>Invoice Date:<br/><b><?php if($result_Invoice_Details['date']!=NULL){ echo date("d M Y",strtotime($result_Invoice_Details['date'])); }?></b></td>
</tr>

<?php 
$Select_DLDetails="select * from delivery_note where inv_id='$Invoice_ID'";
$Fetch_DLDetails=mysqli_query($db_conn,$Select_DLDetails);
$Result_DLDetails=mysqli_fetch_array($Fetch_DLDetails);
?>

<tr id="border_nbottom" valign="top">
<td height="50">Delivery Note<br/><?=$Result_DLDetails['dl_note'];?></td>
<td>Mode/Terms of Payment<br/><?=$Result_DLDetails['mode_pmnt'];?></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Reference No. & Date<br/><?php if($Result_DLDetails['ref_no']!=NULL){ echo $Result_DLDetails['ref_no'];?>, <?php } if($Result_DLDetails['ref_date']!=NULL){ echo date("d/m/Y",strtotime($Result_DLDetails['ref_date'])); }?></td>
<td>Other References<br/><?=$Result_DLDetails['ot_ref'];?></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Buyer's Order No.<br/><?=$Result_DLDetails['order_no'];?></td>
<td>Dated<br/>
<?php if($Result_DLDetails['dated']!=NULL){ echo date("d/m/Y",strtotime($Result_DLDetails['dated'])); }?>
</td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Dispatch Doc No.<br/><?=$Result_DLDetails['dispatch_doc_no'];?></td>
<td>Delivery Note Date<br/>
<?php if($Result_DLDetails['dlnote_date']!=NULL){ echo date("d/m/Y",strtotime($Result_DLDetails['dlnote_date'])); }?>
</td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Dispatched through<br/><?=$Result_DLDetails['dispatch_through'];?></td>
<td>Destination<br/><?=$Result_DLDetails['destination'];?></td>
</tr>
</table>
<p id="shiippingaddress">
Terms of Delivery<br/>
<?=$Result_DLDetails['terms'];?>
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

<?php
	$select_INVProductDetails="select * from user_invoice_items where inv_id='$Invoice_ID' order by id desc";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	while($result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails))
	{
	
	//product dteails
		$InV_Product_ID=$result_INVProductDetails['pr_id'];
		$select_ProductDetails123="select * from products where id='$InV_Product_ID'";
		$fetch_ProductDetails123=mysqli_query($db_conn,$select_ProductDetails123);
		$result_ProductDetails123=mysqli_fetch_array($fetch_ProductDetails123);
		
		// Taxable (excl.-of-tax) line value, derived from the stored total/
		// gstamount_total columns rather than recomputed from qty*rate — this
		// is correct regardless of the product's GST setting: for 'exclusive'
		// products gstamount_total was added on top of subtotal to get total,
		// for 'inclusive' products it was carved out of subtotal instead (see
		// the GST fix in user-invoice-action2.php etc.), so total minus
		// gstamount_total always equals the taxable value either way.
		$TotalAMount23=(float)$result_INVProductDetails['total']-(float)$result_INVProductDetails['gstamount_total'];
		$TotalAMount123+=$TotalAMount23;

		$Totalquantity=$result_INVProductDetails['qty'];
		$Totalquantity123+=$Totalquantity;

		$taxable_rate = $Totalquantity > 0 ? $TotalAMount23 / $Totalquantity : 0;
		$gst_pct_item = (float)$result_INVProductDetails['gst_percentage'];
		$taxable_rate_incl = $taxable_rate + ($gst_pct_item > 0 ? $taxable_rate * $gst_pct_item / 100 : 0);

		$discountamount_show=inr_format($result_INVProductDetails['discount_amount'], 2);
		$discountpercentage_show=inr_format($result_INVProductDetails['discount_percentage'], 0);
	?>
<tr>
<td><?=$invno=$invno+1;?></td>
<td><b><?=$result_ProductDetails123['productName'];?></b></td>
<td id="rightlaign"><?=$result_ProductDetails123['hsn'];?></td>
<td id="rightlaign"><?=$Totalquantity?> Packs</td>
<td id="rightlaign"><?php echo inr_format($result_ProductDetails123['mrp'], 2);?></td>
<td id="rightlaign"><?php echo inr_format($taxable_rate, 2);?></td>
<td id="rightlaign"><?php echo inr_format($taxable_rate_incl, 2);?></td>
<td id="rightlaign">Packs</td>
<td id="rightlaign"><?=$result_INVProductDetails['gst_percentage'];?>%</td>
<td id="rightlaign"><?=$discountamount_show;?> (<?=$discountpercentage_show;?>%)</td>
<td id="rightlaign"><?php echo inr_format($TotalAMount23, 2);?></td>
</tr>

	<?php } ?>
	<tr>
	<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
	<td></td>
	</tr>

<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i></i></b></td>
<td></td>
<td id="rightlaign"><b><?=$Totalquantity123;?> Packs</b></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td id="rightlaign"><b><?=$Currency_symbol;?>&nbsp;<?php echo inr_format($TotalAMount123, 2);?></b></td>
</tr>


<!------------------------------------------------------------------>
<!------------------------------GST--------------------------------->
<?php
// $totalgstamount already computed near the top of the file for the
// Tax Invoice / Bill of Supply heading — reused here.
if($totalgstamount>0)
{
// Rate shown alongside the SGST/CGST/IGST label below — MAX() rather than
// an average, since a mixed-rate invoice has no single correct "the" rate;
// MAX at least reflects a real line on the invoice rather than a blended
// number that matches nothing on it.
$__gst_pct_row = mysqli_fetch_assoc(mysqli_query($db_conn, "SELECT MAX(gst_percentage) AS pct FROM user_invoice_items WHERE inv_id='$Invoice_ID'"));
$__inv_gst_pct = (float)($__gst_pct_row['pct'] ?? 0);

if($gsttype=="inner"){

$SGST=$totalgstamount/2;
$SGST=inr_format($SGST, 2);

$CGST=$totalgstamount/2;
$CGST=inr_format($CGST, 2);
$__half_pct = fmt_gst_pct($__inv_gst_pct / 2);
?>
<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i>SGST (<?=$__half_pct;?>%)</i></b></td>
<td></td>
<td id="rightlaign"></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td id="rightlaign"><b><?=$Currency_symbol;?>&nbsp;<?=$SGST;?></b></td>
</tr>
<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i>CGST (<?=$__half_pct;?>%)</i></b></td>
<td></td>
<td id="rightlaign"></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td id="rightlaign"><b><?=$Currency_symbol;?>&nbsp;<?=$CGST;?></b></td>
</tr>
<?php }else{?>
<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i>IGST (<?=fmt_gst_pct($__inv_gst_pct);?>%)</i></b></td>
<td></td>
<td id="rightlaign"></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td id="rightlaign"><b><?=$Currency_symbol;?>&nbsp;<?=inr_format($totalgstamount, 2);?></b></td>
</tr>
<?php }} ?>
<!------------------------------------------------------------------>
<!------------------------------GST-end**--------------------------->

<?php 
$discountamount=$result_Invoice_Details['discount']+$result_Invoice_Details['credit'];

if($discountamount>0){?>
<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i>Discount</i></b></td>
<td></td>
<td id="rightlaign"></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td id="rightlaign"><b><?=$Currency_symbol;?>&nbsp;<?php echo inr_format($discountamount, 2);?></b></td>
</tr>
<?php }?>

<?php if($result_Invoice_Details['roundoff']!=0){?>
<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i>Round off</i></b></td>
<td></td>
<td id="rightlaign"></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td id="rightlaign"><b><?=$Currency_symbol;?>&nbsp;<?=inr_format($result_Invoice_Details['roundoff'], 2);?></b></td>
</tr>
<?php }?>

<?php if($result_Invoice_Details['courier_charges']!=0){?>
<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i>Courier Charges</i></b></td>
<td></td>
<td id="rightlaign"></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td id="rightlaign"><b><?=$Currency_symbol;?>&nbsp;<?=inr_format($result_Invoice_Details['courier_charges'], 2);?></b></td>
</tr>
<?php }?>

<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i>Total</i></b></td>
<td></td>
<td id="rightlaign"></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td id="rightlaign"><b><?=$Currency_symbol;?>&nbsp;<?=inr_format($result_Invoice_Details['total'], 2);?></b></td>
</tr>
</table>
<div style="clear:both;"></div>

<?php
$number = $result_Invoice_Details['total'];
   $no = floor($number);
   $point = round($number - $no, 2) * 100;
   $hundred = null;
   $digits_1 = strlen($no);
   $i = 0;
   $str = array();
   $words = array('0' => '', '1' => 'one', '2' => 'two',
    '3' => 'three', '4' => 'four', '5' => 'five', '6' => 'six',
    '7' => 'seven', '8' => 'eight', '9' => 'nine',
    '10' => 'ten', '11' => 'eleven', '12' => 'twelve',
    '13' => 'thirteen', '14' => 'fourteen',
    '15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
    '18' => 'eighteen', '19' =>'nineteen', '20' => 'twenty',
    '30' => 'thirty', '40' => 'forty', '50' => 'fifty',
    '60' => 'sixty', '70' => 'seventy',
    '80' => 'eighty', '90' => 'ninety');
   $digits = array('', 'hundred', 'thousand', 'lakh', 'crore');
   while ($i < $digits_1) {
     $divider = ($i == 2) ? 10 : 100;
     $number = floor($no % $divider);
     $no = floor($no / $divider);
     $i += ($divider == 10) ? 1 : 2;
     if ($number) {
        $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
        $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
        $str [] = ($number < 21) ? $words[$number] .
            " " . $digits[$counter] . $plural . " " . $hundred
            :
            $words[floor($number / 10) * 10]
            . " " . $words[$number % 10] . " "
            . $digits[$counter] . $plural . " " . $hundred;
     } else $str[] = null;
  }
  
  $str = array_reverse($str);
  $result = implode('', $str);
  /*$points = ($point) ?
    "." . $words[$point / 10] . " " . 
          $words[$point = $point % 10] : '';*/
		  //$result . "Rupees  " . $points . " Paise";
  //echo $result;
 ?> 

<table width="100%">
<tr>
<td width="70%">Amount Chargeable (in words)</td>
<td align="right">E. & O.E</td>
</tr>
<tr>
<td><b><?=$Currency_Name;?> <?=ucwords($result);?> Only</b></td>
<td></td>
</tr>
</table>

<!---------------------HSN WISE TOTAL------------------------------>
<table width="100%" id="hsnsac">
<?php if($gsttype=="inner"){ ?>
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
<?php }else{ ?>
<tr>
<td align="center">HSN/SAC</td>
<td align="right">Taxable<br/>Value</td>
<td align="right">IGST<br/>Rate</td>
<td align="right">IGST<br/>Amount</td>
<td align="right">Total<br/>Tax Amount</td>
</tr>
<?php } ?>
<?php
$selecthsn="select distinct hsn from user_invoice_items where inv_id='$Invoice_ID'";
$fetchhsn=mysqli_query($db_conn,$selecthsn);
$hsn_grand_taxable=0; $hsn_grand_gst=0;
while($resulthsn=mysqli_fetch_array($fetchhsn)){

	$hsncode=$resulthsn['hsn'];
	// total-gstamount_total = taxable value regardless of whether GST was added on top or carved out (see the GST fix in user-invoice-action2.php etc.)
	$selecthsnTaxamount="select sum(total-gstamount_total) as taxable, sum(gstamount_total) as gst, max(gst_percentage) as pct from user_invoice_items where inv_id='$Invoice_ID' and hsn='$hsncode'";
	$fetchhsnTaxamount=mysqli_query($db_conn,$selecthsnTaxamount);
	$resulthsnTaxamount=mysqli_fetch_array($fetchhsnTaxamount);
	$hsn_taxable = (float)$resulthsnTaxamount['taxable'];
	$hsn_gst     = (float)$resulthsnTaxamount['gst'];
	$hsn_pct     = (float)$resulthsnTaxamount['pct'];
	$hsn_grand_taxable += $hsn_taxable;
	$hsn_grand_gst      += $hsn_gst;
?>
<?php if($gsttype=="inner"): $hsn_half_rate=$hsn_pct/2; $hsn_half_amt=$hsn_gst/2; ?>
<tr>
<td><?=$hsncode;?></td>
<td align="right"><?=inr_format($hsn_taxable, 2)?></td>
<td align="right"><?=fmt_gst_pct($hsn_half_rate)?>%</td>
<td align="right"><?=inr_format($hsn_half_amt, 2)?></td>
<td align="right"><?=fmt_gst_pct($hsn_half_rate)?>%</td>
<td align="right"><?=inr_format($hsn_half_amt, 2)?></td>
<td align="right"><?=inr_format($hsn_gst, 2)?></td>
</tr>
<?php else: ?>
<tr>
<td><?=$hsncode;?></td>
<td align="right"><?=inr_format($hsn_taxable, 2)?></td>
<td align="right"><?=fmt_gst_pct($hsn_pct)?>%</td>
<td align="right"><?=inr_format($hsn_gst, 2)?></td>
<td align="right"><?=inr_format($hsn_gst, 2)?></td>
</tr>
<?php endif; ?>
<?php } ?>
<?php if($gsttype=="inner"): ?>
<tr>
<td align="right"><b>Total&nbsp;</b></td>
<td align="right"><b><?=inr_format($hsn_grand_taxable, 2)?></b></td>
<td></td>
<td align="right"><b><?=inr_format($hsn_grand_gst/2, 2)?></b></td>
<td></td>
<td align="right"><b><?=inr_format($hsn_grand_gst/2, 2)?></b></td>
<td align="right"><b><?=inr_format($hsn_grand_gst, 2)?></b></td>
</tr>
<?php else: ?>
<tr>
<td align="right"><b>Total&nbsp;</b></td>
<td align="right"><b><?=inr_format($hsn_grand_taxable, 2)?></b></td>
<td></td>
<td align="right"><b><?=inr_format($hsn_grand_gst, 2)?></b></td>
<td align="right"><b><?=inr_format($hsn_grand_gst, 2)?></b></td>
</tr>
<?php endif; ?>
</table>
<!---------------------HSN WISE TOTAL----END***------------------------->

<?php
$TAXnumber = $totalgstamount;
   $TAXno = floor($TAXnumber);
   $TAXpoint = round($TAXnumber - $TAXno, 2) * 100;
   $TAXhundred = null;
   $TAXdigits_1 = strlen($TAXno);
   $TAXi = 0;
   $TAXstr = array();
   $TAXwords = array('0' => '', '1' => 'one', '2' => 'two',
    '3' => 'three', '4' => 'four', '5' => 'five', '6' => 'six',
    '7' => 'seven', '8' => 'eight', '9' => 'nine',
    '10' => 'ten', '11' => 'eleven', '12' => 'twelve',
    '13' => 'thirteen', '14' => 'fourteen',
    '15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
    '18' => 'eighteen', '19' =>'nineteen', '20' => 'twenty',
    '30' => 'thirty', '40' => 'forty', '50' => 'fifty',
    '60' => 'sixty', '70' => 'seventy',
    '80' => 'eighty', '90' => 'ninety');
   $TAXdigits = array('', 'hundred', 'thousand', 'lakh', 'crore');
   while ($TAXi < $TAXdigits_1) {
     $TAXdivider = ($TAXi == 2) ? 10 : 100;
     $TAXnumber = floor($TAXno % $TAXdivider);
     $TAXno = floor($TAXno / $TAXdivider);
     $TAXi += ($TAXdivider == 10) ? 1 : 2;
     if ($TAXnumber) {
        $TAXplural = (($TAXcounter = count($TAXstr)) && $TAXnumber > 9) ? 's' : null;
        $TAXhundred = ($TAXcounter == 1 && $TAXstr[0]) ? ' and ' : null;
        $TAXstr [] = ($TAXnumber < 21) ? $TAXwords[$TAXnumber] .
            " " . $TAXdigits[$TAXcounter] . $TAXplural . " " . $TAXhundred
            :
            $TAXwords[floor($TAXnumber / 10) * 10]
            . " " . $TAXwords[$TAXnumber % 10] . " "
            . $TAXdigits[$TAXcounter] . $TAXplural . " " . $TAXhundred;
     } else $TAXstr[] = null;
  }
  
  $TAXstr = array_reverse($TAXstr);
  $TAXresult = implode('', $TAXstr);

  // Paise portion — without this, a tax amount under ₹1 (e.g. ₹0.73,
  // common on small-value lines) has $TAXno=0 above, so $TAXresult comes out
  // empty and "Tax Amount (in words)" prints as just "INR  Only" with
  // nothing in it.
  $TAXpaise = (int) round(($totalgstamount - floor($totalgstamount)) * 100);
  $TAXpaise_words = '';
  if ($TAXpaise > 0) {
      $TAXpaise_words = ($TAXpaise < 21)
          ? $TAXwords[$TAXpaise]
          : trim($TAXwords[floor($TAXpaise / 10) * 10] . " " . $TAXwords[$TAXpaise % 10]);
  }
  if (trim($TAXresult) !== '' && $TAXpaise_words !== '') {
      $TAXresult = trim($TAXresult) . ' Rupees and ' . $TAXpaise_words . ' Paise';
  } elseif ($TAXpaise_words !== '') {
      $TAXresult = $TAXpaise_words . ' Paise';
  } elseif (trim($TAXresult) === '') {
      $TAXresult = 'Zero';
  }
  /*$TAXpoints = ($TAXpoint) ?
    "." . $TAXwords[$TAXpoint / 10] . " " . 
          $TAXwords[$TAXpoint = $TAXpoint % 10] : '';*/
		  //$TAXresult . "Rupees  " . $TAXpoints . " Paise";
  //echo $TAXresult;
 ?>  
 
 
 <table width="100%">
 <tr>
 <td width="50%">
 <?php if($totalgstamount>0){?>
<div>&nbsp;Tax Amount (in words): <b><?=$Currency_Name;?> <?=ucwords($TAXresult); ?> Only</b></div>
<?php }else{?>
<div>&nbsp;Tax Amount (in words): <b>Nil</b></div>
<?php }?>

<br/>
<div style="width:99%;margin:0 auto;"><u>Declaration:</u><br/>We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</div>
</td>
 <td>
 <table align="right">
 <tr>
 <td>A/c Name</td>
 <td>&nbsp;:&nbsp;<?=$result_Godown['acname'];?></td>
 </tr>
 <tr>
 <td>A/c Number</td>
 <td>&nbsp;:&nbsp;<?=$result_Godown['acnumber'];?></td>
 </tr>
 <tr>
 <td>Bank Name</td>
 <td>&nbsp;:&nbsp;<?=$result_Godown['bankname'];?></td>
 </tr>
 <tr>
 <td>Branch Name</td>
 <td>&nbsp;:&nbsp;<?=$result_Godown['branchname'];?></td>
 </tr>
 <tr>
 <td>IFS Code</td>
 <td>&nbsp;:&nbsp;<?=$result_Godown['ifsc'];?></td>
 </tr>
 <tr>
 <td>UPI Number</td>
 <td>&nbsp;:&nbsp;<?=$result_Godown['upinumber'];?></td>
 </tr>
 </table>
 </td>
 </tr>
 </table>
 
 

<table width="100%" id="sealsign">
<tr>
<td width="50%" align="left">Customer's Seal and Signature</td>
<td align="right">for <b><?=$result_Godown['gname'];?></b></td>
</tr>
<tr>
<td>&nbsp;</td>
<td>&nbsp;</td>
</tr>
<tr>
<td></td>
<td align="right">Authorised Signatory</td>
</tr>
</table>
<div style="clear:both;"></div>
</div>
<div align="center">SUBJECT TO ERODE JURISDICTION</div>
<div align="center">This is a Computer Generated Invoice</div>
			
			
		</div><!----------------PRINT DIV END-------------->
				
                </div>
            </div>
        </div>
    </div>

    <!-- Javascripts -->
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