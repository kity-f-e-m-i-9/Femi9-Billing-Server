<?php 

include("checksession.php");
include("config.php"); error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today_date=date("Y-m-d");

//UPDATE - CUSTOMER GST TYPE - register (or) unregister
$select_user_invoiceGST="select inv_id,to_user_type,to_user_id from user_invoice where buyer_gsttype=''";
$exe_user_invoiceGST=mysqli_query($db_conn,$select_user_invoiceGST);
while($fetch_user_invoiceGST=mysqli_fetch_array($exe_user_invoiceGST))
{
	$invoice_id=$fetch_user_invoiceGST['inv_id'];
	$invoice_to_user_type=$fetch_user_invoiceGST['to_user_type'];
	$invoice_to_user_id=$fetch_user_invoiceGST['to_user_id'];
	
//FIND TABLE NAME
if($invoice_to_user_type=="super_stockiest")
{$tablename="super_stockiest";}
if($invoice_to_user_type=="stockiest")
{$tablename="stockiest";}
if($invoice_to_user_type=="distributor")
{$tablename="distributor";}
if($invoice_to_user_type=="shop")
{$tablename="shop";}
	
	
$selecutomser_dtails="select * from ".$tablename." where temp_id='$invoice_to_user_id'";
$fetchcutomser_dtails=mysqli_query($db_conn,$selecutomser_dtails);
$resultcutomser_dtails=mysqli_fetch_array($fetchcutomser_dtails);

$buyer_GSTIN=$resultcutomser_dtails['gstin'];
$buyer_GSTIN_count=strlen($buyer_GSTIN);
if($buyer_GSTIN_count==15){ $buyer_gsttype="register";}else {$buyer_gsttype="unregister";}

//UPDATE BUYER-GST-TYPE
$update_invoice_gsttype="update user_invoice set buyer_gsttype='$buyer_gsttype' where inv_id='$invoice_id'";
mysqli_query($db_conn,$update_invoice_gsttype);
//
$update_invoice_gsttype2="update user_invoice_items set buyer_gsttype='$buyer_gsttype' where inv_id='$invoice_id'";
mysqli_query($db_conn,$update_invoice_gsttype2);
	
}



?>