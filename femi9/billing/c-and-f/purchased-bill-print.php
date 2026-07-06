<?php include("checksession.php"); date_default_timezone_set("Asia/Kolkata"); 
error_reporting(0);
include("config.php");
$Invoice_ID=$_REQUEST['invoiceid'];
$Invoice_ID=base64_decode($Invoice_ID);

$select_Invoice_Details="select * from user_invoice where inv_id='$Invoice_ID'";
$fetch_Invoice_Details=mysqli_query($db_conn,$select_Invoice_Details);
$result_Invoice_Details=mysqli_fetch_array($fetch_Invoice_Details);

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



<table align="right">
			<tr>
			<td><button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button></td>
			<td><button type="button" onClick="javascript:window.location='purchasebill';" class="btn btn-primary m-b-xs m-r-xs">Go Back</button></td>
			</tr>
			</table>
			
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
<td>Bill of Supply</td>
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
$select_Godown="select * from company_godown where id='$from_user_id'";
$fetch_Godown=mysqli_query($db_conn,$select_Godown);
$result_Godown=mysqli_fetch_array($fetch_Godown);
?>
<tr valign="top">
<td><img src="<?=$invoice_logo?>"/></td>
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
<?=$deliveryaddress;?>
</p>


<hr/>
<p class="cusdetaiis">
Buyer (Bill to):<br/>
<b><?php echo ucwords($result_Customer_Details['name']);?></b><br/>
GSTIN: <?=$result_Customer_Details['gstin'];?><br/>
State : <?=$state_name;?>, District: <?=$district_name?>
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
<td id="rightlaign">Rate</td>
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
		
		$TotalAMount=$result_INVProductDetails['qty']*$result_INVProductDetails['amount'];
		$TotalAMount23=$TotalAMount-$result_INVProductDetails['discount_amount'];
		$TotalAMount123+=$TotalAMount23;
		
		$Totalquantity=$result_INVProductDetails['qty'];
		$Totalquantity123+=$Totalquantity;
		
		$discountamount_show=inr_format($result_INVProductDetails['discount_amount'], 2);
		$discountpercentage_show=inr_format($result_INVProductDetails['discount_percentage'], 0);
	?>
<tr>
<td>1</td>
<td><b><?=$result_ProductDetails123['productName'];?></b></td>
<td id="rightlaign"><?=$result_ProductDetails123['hsn'];?></td>
<td id="rightlaign"><?=$Totalquantity?> Packs</td>
<td id="rightlaign"><?php echo inr_format($result_INVProductDetails['amount'], 2);?></td>
<td id="rightlaign">Packs</td>
<td id="rightlaign"><?=$result_INVProductDetails['gst_percentage'];?>%</td>
<td id="rightlaign"><?=$discountamount_show;?> (<?=$discountpercentage_show;?>%)</td>
<td id="rightlaign"><?php echo inr_format($TotalAMount23, 2);?></td>
</tr>

	<?php } ?>
	<tr>
	<td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
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
<td id="rightlaign"><b>&#8377; <?php echo inr_format($TotalAMount123, 2);?></b></td>
</tr>


<!------------------------------------------------------------------>
<!------------------------------GST--------------------------------->
<?php 
$gsttype=$result_Invoice_Details['gst_type'];

$select_sum_gstamount="select sum(gstamount_total) from user_invoice_items where inv_id='$Invoice_ID'";
$fetch_sum_gstamount=mysqli_query($db_conn,$select_sum_gstamount);
$result_sum_gstamount=mysqli_fetch_array($fetch_sum_gstamount);
$totalgstamount=$result_sum_gstamount[0];

if($totalgstamount>0)
{
if($gsttype=="inner"){
	
$SGST=$totalgstamount/2;
$SGST=inr_format($SGST, 2);

$CGST=$totalgstamount/2;
$CGST=inr_format($CGST, 2);
?>
<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i>SGST</i></b></td>
<td></td>
<td id="rightlaign"></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td id="rightlaign"><b>&#8377; <?=$SGST;?></b></td>
</tr>
<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i>CGST</i></b></td>
<td></td>
<td id="rightlaign"></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td id="rightlaign"><b>&#8377; <?=$CGST;?></b></td>
</tr>
<?php }else{?>
<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i>IGST</i></b></td>
<td></td>
<td id="rightlaign"></td>
<td></td>
<td></td>
<td></td>
<td></td>
<td id="rightlaign"><b>&#8377; <?=inr_format($totalgstamount, 2);?></b></td>
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
<td id="rightlaign"><b>&#8377; <?php echo inr_format($discountamount, 2);?></b></td>
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
<td id="rightlaign"><b>&#8377; <?=inr_format($result_Invoice_Details['roundoff'], 2);?></b></td>
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
<td id="rightlaign"><b>&#8377; <?=inr_format($result_Invoice_Details['total'], 2);?></b></td>
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
<td><b>INR <?=ucwords($result);?> Only</b></td>
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
//$selecthsn="select distinct hsn from user_invoice_items where inv_id='$Invoice_ID' and gst_percentage>0";
$selecthsn="select distinct hsn from user_invoice_items where inv_id='$Invoice_ID'";
$fetchhsn=mysqli_query($db_conn,$selecthsn);
while($resulthsn=mysqli_fetch_array($fetchhsn)){
	
	$hsncode=$resulthsn['hsn'];
//sum hsn taxable Amount
$selecthsnTaxamount="select sum(total) from user_invoice_items where inv_id='$Invoice_ID' and hsn='$hsncode'";
$fetchhsnTaxamount=mysqli_query($db_conn,$selecthsnTaxamount);
$resulthsnTaxamount=mysqli_fetch_array($fetchhsnTaxamount);
?>
<tr>
<td><?=$hsncode;?></td>
<td  align="right"><?=inr_format($resulthsnTaxamount[0], 2)?></td>
</tr>
<?php }

//sum hsn taxable Amount
//$selecthsnTaxamount12="select sum(total) from user_invoice_items where inv_id='$Invoice_ID' and gst_percentage>0";
$selecthsnTaxamount12="select sum(total) from user_invoice_items where inv_id='$Invoice_ID'";
$fetchhsnTaxamount12=mysqli_query($db_conn,$selecthsnTaxamount12);
$resulthsnTaxamount12=mysqli_fetch_array($fetchhsnTaxamount12);
?>
<tr>
<td align="right"><b>Total&nbsp;</b></td>
<td align="right"><b><?=inr_format($resulthsnTaxamount12[0], 2)?></b></td>
</tr>
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
  /*$TAXpoints = ($TAXpoint) ?
    "." . $TAXwords[$TAXpoint / 10] . " " . 
          $TAXwords[$TAXpoint = $TAXpoint % 10] : '';*/
		  //$TAXresult . "Rupees  " . $TAXpoints . " Paise";
  //echo $TAXresult;
 ?>  
<?php if($totalgstamount>0){?>
<div>&nbsp;Tax Amount (in words): <b>INR <?=ucwords($TAXresult); ?> Only</b></div>
<?php }else{?>
<div>&nbsp;Tax Amount (in words): <b>Nil</b></div>
<?php }?>

<br/>
<div style="width:99%;margin:0 auto;"><u>Declaration:</u><br/>We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</div>


<table width="100%" id="sealsign">
<tr>
<td width="50%" align="left">Customer's Seal and Signature</td>
<td align="right">for <b><?=$forlable;?></b></td>
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