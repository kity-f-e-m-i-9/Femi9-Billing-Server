<?php session_start(); include("include/db-connect.php");
include("config.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if (isset($_REQUEST['updateInvoiceNum'])) {
	
	$invuser=$_POST["invuser"];
	$InvoiceID=base64_decode($_POST["InvoiceID"]);
	$action=$_POST["action"];
	$gid=$_POST["gid"];
	
	$invnumber = RemoveSpecialChar($_POST["invnumber"]);
	$invnumber=str_replace("'","",$invnumber);
	
	if($_REQUEST["tblenme"]==1)
	{
		$tablename="user_invoice";
$Select_Count_Invoice="select * from user_invoice where inv_number='$invnumber' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
		
	}else{ 
	$tablename="invoice";
$Select_Count_Invoice="select * from invoice where inv_number='$invnumber' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
	}
	
$Fetch_Count_Invoice=mysqli_query($db_conn,$Select_Count_Invoice);
$Result_Count_Invoice=mysqli_num_rows($Fetch_Count_Invoice);
if($Result_Count_Invoice==0){
	
	$update_DLNote="update ".$tablename." set inv_number='$invnumber' where inv_id='$InvoiceID'";
	mysqli_query($db_conn,$update_DLNote);
	
	echo "<script>window.location='".$_REQUEST['redirurl']."?invuser=$invuser&&InvoiceID=".$_POST["InvoiceID"]."&&action=$action&&gid=$gid&&InvoiceUpdatedSuccess';</script>";
	
}
else{
	
	echo "<script>window.location='".$_REQUEST['redirurl']."?invuser=$invuser&&InvoiceID=".$_POST["InvoiceID"]."&&action=$action&&gid=$gid&&invoicealready';</script>";
}
	
}
else
{ echo "<script>window.location='dashboard';</script>";
}
?>