<?php include("include/db-connect.php");
include("config.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if (isset($_REQUEST['updateInvoiceNum'])) {
	
	$tempid=$_POST["tempid"];
	
	$invnumber = RemoveSpecialChar($_POST["invnumber"]);
	$invnumber=str_replace("'","",$invnumber);
	
$Select_Count_Invoice="select * from internal_transfer_invoice where inv_number='$invnumber'";
$Fetch_Count_Invoice=mysqli_query($db_conn,$Select_Count_Invoice);
$Result_Count_Invoice=mysqli_num_rows($Fetch_Count_Invoice);
if($Result_Count_Invoice==0){
	
	$update_DLNote="update internal_transfer_invoice set inv_number='$invnumber' where tempid='$tempid'";
	mysqli_query($db_conn,$update_DLNote);
	
	echo "<script>window.location='internal_transfer_details?tempid=$tempid&&InvoiceUpdatedSuccess';</script>";
	
}
else{
	
	echo "<script>window.location='internal_transfer_details?tempid=$tempid&&invoicealready';</script>";
}
	
}
else
{ echo "<script>window.location='dashboard';</script>";
}
?>