<?php 

include("checksession.php");
include("config.php"); error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today_date=date("Y-m-d");

//UPDATE - CUSTOMER GST TYPE - register (or) unregister
$select_user_invoiceGST="select invnumber,from_usertype,from_userid from user_return_stock";
$exe_user_invoiceGST=mysqli_query($db_conn,$select_user_invoiceGST);
while($fetch_user_invoiceGST=mysqli_fetch_array($exe_user_invoiceGST))
{
	$invnumber=$fetch_user_invoiceGST['invnumber'];
	$from_usertype=$fetch_user_invoiceGST['from_usertype'];
	$from_userid=$fetch_user_invoiceGST['from_userid'];
	
// get gst type (inner/outer)
if($from_usertype!="customer")
{
$select_gst_type="select gst_type from user_invoice where inv_id='$invnumber'";
}else{
$select_gst_type="select gst_type from invoice where inv_id='$invnumber'";
}
$fetch_gst_type=mysqli_query($db_conn,$select_gst_type);
$result_gst_type=mysqli_fetch_array($fetch_gst_type);
//
$gst_type=$result_gst_type['gst_type'];
	
//-------------------------------------------------------------------------------
	if($from_usertype=="super_stockiest"){$tablename="super_stockiest";}
	else if($from_usertype=="stockiest"){	$tablename="stockiest";}
	else if($from_usertype=="distributor"){$tablename="distributor";}
	else if($from_usertype=="outlet"){$tablename="outlet";}
	else if($from_usertype=="shop"){$tablename="shop";}
	else{
		$tablename="customers";
	}
	
	if($tablename!="customers"){
$selecutomser_dtails="select * from ".$tablename." where temp_id='$from_userid'";
	}
	else{
		$selecutomser_dtails="select * from ".$tablename." where id='$from_userid'";
	}
	
$fetchcutomser_dtails=mysqli_query($db_conn,$selecutomser_dtails);
$resultcutomser_dtails=mysqli_fetch_array($fetchcutomser_dtails);

$buyer_GSTIN=$resultcutomser_dtails['gstin'];
$buyer_GSTIN_count=strlen($buyer_GSTIN);
if($buyer_GSTIN_count==15){ $buyer_gsttype="register";}else {$buyer_gsttype="unregister";}
//-------------------------------------------------------------------------------

//UPDATE BUYER-GST-TYPE
$update_invoice_gsttype="update user_return_stock set buyer_gsttype='$buyer_gsttype',gst_type='$gst_type' where invnumber='$invnumber'";
mysqli_query($db_conn,$update_invoice_gsttype);
//
$update_invoice_gsttype2="update user_return_stock_items set buyer_gsttype='$buyer_gsttype',gst_type='$gst_type' where invnumber='$invnumber'";
mysqli_query($db_conn,$update_invoice_gsttype2);
	
}



?>