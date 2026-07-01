<?php 
include("checksession.php");
include("config.php"); error_reporting(0);

$select_user_invoiceGST="select tempid,qty from ot_sales_return where total=''";
$exe_user_invoiceGST=mysqli_query($db_conn,$select_user_invoiceGST);
while($fetch_user_invoiceGST=mysqli_fetch_array($exe_user_invoiceGST))
{
	$tempid=$fetch_user_invoiceGST['tempid'];
	$return_qty=$fetch_user_invoiceGST['qty'];
	//
$select_user_invoiceGST12="select price from ot_sales where tempid='$tempid'";
$exe_user_invoiceGST12=mysqli_query($db_conn,$select_user_invoiceGST12);
$fetch_user_invoiceGST12=mysqli_fetch_array($exe_user_invoiceGST12);
//
$pr_price=$fetch_user_invoiceGST12['price'];
$total_amount=$pr_price*$return_qty;
	
$update_invoice_gsttype="update ot_sales_return set price='$pr_price',total='$total_amount' where tempid='$tempid'";
mysqli_query($db_conn,$update_invoice_gsttype);

	
}



?>