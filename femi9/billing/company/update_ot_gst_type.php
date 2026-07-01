<?php 

include("checksession.php");
include("config.php"); error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today_date=date("Y-m-d");

//UPDATE - CUSTOMER GST TYPE - register (or) unregister
$select_user_invoiceGST="select tempid,gst_number from ot_sales where buyer_gsttype=''";
$exe_user_invoiceGST=mysqli_query($db_conn,$select_user_invoiceGST);
while($fetch_user_invoiceGST=mysqli_fetch_array($exe_user_invoiceGST))
{
	$tempid=$fetch_user_invoiceGST['tempid'];
	$gst_number=$fetch_user_invoiceGST['gst_number'];
	
//-------------------------------------------------------------------------------
$buyer_GSTIN_count=strlen($gst_number);
if($buyer_GSTIN_count==15){ $buyer_gsttype="register";}else {$buyer_gsttype="unregister";}
//-------------------------------------------------------------------------------

//UPDATE BUYER-GST-TYPE
$update_invoice_gsttype="update ot_sales_invoice set buyer_gsttype='$buyer_gsttype' where tempid='$tempid'";
mysqli_query($db_conn,$update_invoice_gsttype);
//
$update_invoice_gsttype2="update ot_sales set buyer_gsttype='$buyer_gsttype' where tempid='$tempid'";
mysqli_query($db_conn,$update_invoice_gsttype2);
//
$update_invoice_gsttype23="update ot_sales_return set buyer_gsttype='$buyer_gsttype' where tempid='$tempid'";
mysqli_query($db_conn,$update_invoice_gsttype23);
	
}



?>