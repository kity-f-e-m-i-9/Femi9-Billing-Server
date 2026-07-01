<?php 
//HSN, gst_type (inner, outer) update in [ot_sales_return]

include("checksession.php");
include("config.php"); error_reporting(0);

$select_user_invoiceGST="select tempid from ot_sales_return";
$exe_user_invoiceGST=mysqli_query($db_conn,$select_user_invoiceGST);
while($fetch_user_invoiceGST=mysqli_fetch_array($exe_user_invoiceGST))
{
	$tempid=$fetch_user_invoiceGST['tempid'];
	
$select_user_invoiceGST12="select hsn,gst_type from ot_sales where tempid='$tempid'";
$exe_user_invoiceGST12=mysqli_query($db_conn,$select_user_invoiceGST12);
$fetch_user_invoiceGST12=mysqli_fetch_array($exe_user_invoiceGST12);

$hsn=$fetch_user_invoiceGST12['hsn'];
$gst_type=$fetch_user_invoiceGST12['gst_type'];
	
$update_invoice_gsttype="update ot_sales_return set hsn='$hsn',gst_type='$gst_type' where tempid='$tempid'";
mysqli_query($db_conn,$update_invoice_gsttype);

}

?>