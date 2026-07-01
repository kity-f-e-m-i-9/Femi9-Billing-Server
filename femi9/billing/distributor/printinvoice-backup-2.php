<?php 
include("include/db-connect.php");
include("config-pdf.php");
error_reporting(0);

$Invoice_ID=$_REQUEST['invid'];
$Invoice_ID=base64_decode($Invoice_ID);
//
$select_Invoice_Details="select * from user_invoice where inv_id='$Invoice_ID'";
$fetch_Invoice_Details=mysqli_query($db_conn,$select_Invoice_Details);
$result_Invoice_Details=mysqli_fetch_array($fetch_Invoice_Details);

//customer details
$getinvuser=$result_Invoice_Details['to_user_type'];
$tablename="shop";
	
	
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


// Include the TCPDF library
require_once('../tcpdf/tcpdf.php');

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Femi9');
$pdf->SetAuthor('Femi9');
$pdf->SetTitle('Femi9 Invoice');
$pdf->SetSubject('Invoice');
$pdf->SetKeywords('Invoice');

// Set default header data
//$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE.' 006', PDF_HEADER_STRING);

// Set header and footer fonts
//$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT, true);
//$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Set font
$pdf->SetFont('helvetica', '', 12);

// Add a page
$pdf->AddPage();

// Set HTML content
$html= '<style>.maincontainar{width:100%;height:auto;border:1px solid #000;}
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
#sealsign tr td:nth-child(1){border-right:1px solid #000;}</style>';

$html=$html.'
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
<tr valign="top">
<td></td>
<td valign="top">
<span id="cmpname">'.$invoice_from_line1.'</span><br/>
'.$invoice_from_line2.'<br/>
'.$invoice_from_line3.'<br/>
'.$invoice_from_line4.'<br/>'.$invoice_from_line5.'<br/>'.$invoice_from_line6.'
</td>
</tr>
</table>
<hr/>
<p class="cusdetaiis">
Consignee (Ship to):<br/>
<b>'.ucwords($result_Customer_Details['name']).'</b><br/>
GSTIN: '.$result_Customer_Details['gstin'].'<br/>
State : '.$state_name.', District: '.$district_name.'
</p>
<hr/>
<p class="cusdetaiis">
Buyer (Bill to):<br/>
<b>'.ucwords($result_Customer_Details['name']).'</b><br/>
GSTIN: '.$result_Customer_Details['gstin'].'<br/>
State : '.$state_name.', District: '.$district_name.'
</p>
</td>
<td valign="top">
<table id="second_topvl">
<tr id="border_nbottom">
<td>Invoice #<br/><b>'.$result_Invoice_Details['inv_number'].'</b></td>
<td>Invoice Date:<br/><b>'.date("d M Y",strtotime($result_Invoice_Details['date'])).'</b></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Delivery Note</td>
<td>Mode/Terms of Payment</td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Reference No. & Date</td>
<td>Other References</td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Buyers Order No.</td>
<td>Dated</td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Dispatch Doc No.</td>
<td>Delivery Note Date</td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Dispatched through</td>
<td>Destination</td>
</tr>
</table>
<p id="shiippingaddress">
Terms of Delivery<br/>
<p>&nbsp;</p>
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
<td id="rightlaign">Amount</td>
</tr>
';


$select_INVProductDetails="select * from user_invoice_items where inv_id='$Invoice_ID' order by id desc";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	while($result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails))
	{
	
	//product dteails
		$InV_Product_ID=$result_INVProductDetails['pr_id'];
		$select_ProductDetails123="select * from products where id='$InV_Product_ID'";
		$fetch_ProductDetails123=mysqli_query($db_conn,$select_ProductDetails123);
		$result_ProductDetails123=mysqli_fetch_array($fetch_ProductDetails123);
		
		$TotalAMount=$result_INVProductDetails['total'];
		$TotalAMount123+=$TotalAMount;
		
		$Totalquantity=$result_INVProductDetails['qty'];
		$Totalquantity123+=$Totalquantity;
	
	$html=$html.'
<tr>
<td>1</td>
<td><b>'.$result_ProductDetails123['productName'].'</b></td>
<td id="rightlaign">961900</td>
<td id="rightlaign">'.$Totalquantity.' Packs</td>
<td id="rightlaign">'.number_format($result_INVProductDetails['amount'],2,'.','').'</td>
<td id="rightlaign">Packs</td>
<td id="rightlaign">'.number_format($TotalAMount,2,'.','').'</td>
</tr>';

	}
	
	$html=$html.'
	<tr>
	<td></td><td></td><td></td><td></td><td></td><td></td><td></td>
	</tr>

<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i></i></b></td>
<td></td>
<td id="rightlaign"><b>'.$Totalquantity123.' Packs</b></td>
<td></td>
<td></td>
<td id="rightlaign"><b>&#8377; '.number_format($TotalAMount123,2,'.','').'</b></td>
</tr>';



if($result_Invoice_Details['discount']>0){
	$html=$html.'
<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i>Discount</i></b></td>
<td></td>
<td id="rightlaign"></td>
<td></td>
<td></td>
<td id="rightlaign"><b>&#8377; '.number_format($result_Invoice_Details['discount'],2,'.','').'</b></td>
</tr>
';

 }
 
 $html=$html.'
<tr id="bottombordervl">
<td></td>
<td id="rightlaign"><b><i>Total</i></b></td>
<td></td>
<td id="rightlaign"></td>
<td></td>
<td></td>
<td id="rightlaign"><b>&#8377; '.number_format($result_Invoice_Details['total'],2,'.','').'</b></td>
</tr>
</table>
<div style="clear:both;"></div>
';

function numberTowords($num)
{

$ones = array(
0 =>"Zero",
1 => "One",
2 => "Two",
3 => "Three",
4 => "Four",
5 => "Five",
6 => "Six",
7 => "Seven",
8 => "Eight",
9 => "Nine",
10 => "Ten",
11 => "Eleven",
12 => "Twelve",
13 => "Thirteen",
14 => "Fourteen",
15 => "Fifteen",
16 => "Sixteen",
17 => "Seventeen",
18 => "Eighteen",
19 => "Nineteen",
"014" => "Fourteen"
);
$tens = array( 
0 => "Zero",
1 => "Ten",
2 => "Twenty",
3 => "Thirty", 
4 => "Forty", 
5 => "Fifty", 
6 => "Sixty", 
7 => "Seventy", 
8 => "Eighty", 
9 => "Ninety" 
); 
$hundreds = array( 
"Hundred", 
"Thousand", 
"Million", 
"Billion", 
"Trillion", 
"Quardrillion" 
); 

$num = number_format($num,2,".",","); 
$num_arr = explode(".",$num); 
$wholenum = $num_arr[0]; 
$decnum = $num_arr[1]; 
$whole_arr = array_reverse(explode(",",$wholenum)); 
krsort($whole_arr,1); 
$rettxt = ""; 
foreach($whole_arr as $key => $i){
	
while(substr($i,0,1)=="0")
		$i=substr($i,1,5);
if($i < 20){ 


$rettxt .= $ones[$i]; 
}elseif($i < 100){ 
if(substr($i,0,1)!="0")  $rettxt .= $tens[substr($i,0,1)]; 
if(substr($i,1,1)!="0") $rettxt .= " ".$ones[substr($i,1,1)]; 
}else{ 
if(substr($i,0,1)!="0") $rettxt .= $ones[substr($i,0,1)]." ".$hundreds[0]; 
if(substr($i,1,1)!="0")$rettxt .= " ".$tens[substr($i,1,1)]; 
if(substr($i,2,1)!="0")$rettxt .= " ".$ones[substr($i,2,1)]; 
} 
if($key > 0){ 
$rettxt .= " ".$hundreds[$key]." "; 
}
} 
if($decnum > 0){
$rettxt .= " and ";
if($decnum < 20){
$rettxt .= $ones[$decnum];
}elseif($decnum < 100){
$rettxt .= $tens[substr($decnum,0,1)];
$rettxt .= " ".$ones[substr($decnum,1,1)];
$rettxt .=" Paise";
}
}
return $rettxt;
}
//$num=number_format($TotalAMount123,2,'.','');

$html=$html.'
<table width="100%">
<tr>
<td width="70%">Amount Chargeable (in words)</td>
<td align="right">E. & O.E</td>
</tr>
<tr>
<td><b>INR '.numberTowords($result_Invoice_Details['total']).' Only</b></td>
<td></td>
</tr>
</table>

<table width="100%" id="hsnsac">
<tr>
<td width="70%" align="center">HSN/SAC</td>
<td align="right">Taxable Value</td>
</tr>
<tr>
<td>961900</td>
<td  align="right">'.number_format($result_Invoice_Details['total'],2,'.','').'</td>
</tr>
<tr>
<td align="right"><b>Total&nbsp;</b></td>
<td align="right"><b>'.number_format($result_Invoice_Details['total'],2,'.','').'</b></td>
</tr>
</table>

<div>&nbsp;Tax Amount (in words): <b>NIL</b></div>

<br/>
<div style="width:99%;margin:0 auto;"><u>Declaration:</u><br/>We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</div>


<table width="100%" id="sealsign">
<tr>
<td width="50%" align="left">Customers Seal and Signature</td>
<td align="right">for <b>'.$forlable.'</b></td>
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
';


// Write HTML content to PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Output the PDF to browser
$pdf->Output('femi9-invoice.pdf', 'I');
?>
			
