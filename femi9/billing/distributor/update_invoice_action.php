<?php require_once __DIR__ . '/../shared/db-helpers.php'; session_start(); include("checksession.php"); include("include/db-connect.php");
include("config.php");
error_reporting(0);

include("RemoveSpecialChar.php");
$_REQUEST['redirurl']=preg_match('/^[A-Za-z0-9_\-\.\/]+$/',(string)($_REQUEST['redirurl']??'')) ? $_REQUEST['redirurl'] : 'dashboard';

if (isset($_REQUEST['updateInvoiceNum'])) {
	
	$invuser=urlencode((string)$_POST["invuser"]);
	$InvoiceID=mysqli_real_escape_string($db_conn,base64_decode($_POST["InvoiceID"]));
	$action=urlencode((string)$_POST["action"]);
	$gid=urlencode((string)$_POST["gid"]);
	
	$invnumber = RemoveSpecialChar($_POST["invnumber"]);
	$invnumber=mysqli_real_escape_string($db_conn,str_replace("'","",$invnumber));
	
	if($_REQUEST["tblenme"]==1)
	{
		$tablename="user_invoice";
$Select_Count_Invoice="select * from user_invoice where inv_number='$invnumber' and from_user_type='".db_esc($Login_user_TYPEvl)."' and from_user_id='".db_esc($Login_user_IDvl)."'";
		
	}else{ 
	$tablename="invoice";
$Select_Count_Invoice="select * from invoice where inv_number='$invnumber' and user_type='".db_esc($Login_user_TYPEvl)."' and user_id='".db_esc($Login_user_IDvl)."'";
	}
	
$Fetch_Count_Invoice=mysqli_query($db_conn,$Select_Count_Invoice);
$Result_Count_Invoice=mysqli_num_rows($Fetch_Count_Invoice);
if($Result_Count_Invoice==0){
	
	$owner_sql = ($tablename=="user_invoice")
		? " and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'"
		: " and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
	$update_DLNote="update ".$tablename." set inv_number='$invnumber' where inv_id='$InvoiceID'".$owner_sql;
	mysqli_query($db_conn,$update_DLNote);
	
	echo "<script>window.location='".$_REQUEST['redirurl']."?invuser=$invuser&&InvoiceID=".urlencode((string)$_POST["InvoiceID"])."&&action=$action&&gid=$gid&&InvoiceUpdatedSuccess';</script>";
	
}
else{
	
	echo "<script>window.location='".$_REQUEST['redirurl']."?invuser=$invuser&&InvoiceID=".urlencode((string)$_POST["InvoiceID"])."&&action=$action&&gid=$gid&&invoicealready';</script>";
}
	
}
else
{ echo "<script>window.location='dashboard';</script>";
}
?>