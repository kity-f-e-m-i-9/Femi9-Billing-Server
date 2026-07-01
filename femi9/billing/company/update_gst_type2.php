<?php 

include("checksession.php");
include("config.php"); error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today_date=date("Y-m-d");

//---------------------------------------------------------------------------------

$select_user_invoiceGST2="select inv_id,customer_id from invoice where buyer_gsttype=''";
$exe_user_invoiceGST2=mysqli_query($db_conn,$select_user_invoiceGST2);
while($fetch_user_invoiceGST2=mysqli_fetch_array($exe_user_invoiceGST2))
{
	$invoice_id2=$fetch_user_invoiceGST2['inv_id'];
	$invoice_customer_id=$fetch_user_invoiceGST2['customer_id'];

$selecutomser_dtails2="select * from customers where id='$invoice_customer_id'";
$fetchcutomser_dtails2=mysqli_query($db_conn,$selecutomser_dtails2);
$resultcutomser_dtails2=mysqli_fetch_array($fetchcutomser_dtails2);

$buyer_GSTIN2=$resultcutomser_dtails2['gstin'];
$buyer_GSTIN_count2=strlen($buyer_GSTIN2);
if($buyer_GSTIN_count2==15){ $buyer_gsttype2="register";}else {$buyer_gsttype2="unregister";}

//UPDATE BUYER-GST-TYPE
$update_invoice_gsttype2="update invoice set buyer_gsttype='$buyer_gsttype2' where inv_id='$invoice_id2'";
mysqli_query($db_conn,$update_invoice_gsttype2);
//
$update_invoice_gsttype23="update invoice_items set buyer_gsttype='$buyer_gsttype2' where inv_id='$invoice_id2'";
mysqli_query($db_conn,$update_invoice_gsttype23);
	
}



?>