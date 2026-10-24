<?php require_once __DIR__ . '/../shared/db-helpers.php';
session_start(); include("checksession.php");
include("include/db-connect.php");
include("config.php");
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
	$invid=$_POST["invid"];
	$invuser=$_POST["invuser"];
	$old_customer_id=$_POST["old_customer_id"];
	$new_customer_id=$_POST["new_customer_id"];

if($old_customer_id==$new_customer_id)
{
	echo "<script>window.location='update_customer3?invuser=$invuser&&InvoiceID=$invid&&alreadyexists';</script>";
	
}else{
//-------------------------------------------------------------------	
//-------------------------------------------------------------------	

$update_user_invoice="update invoice set customer_id=? where inv_id=?";
db_q($db_conn,$update_user_invoice,[$new_customer_id,$invid]);

$update_user_invoice12="update invoice_items set customer_id=? where inv_id=?";
db_q($db_conn,$update_user_invoice12,[$new_customer_id,$invid]);

$update_user_receipt="update receipt set to_user_id=? where inv_id=?";
db_q($db_conn,$update_user_receipt,[$new_customer_id,$invid]);

//------------------------------------------------------------------
//------------------------------------------------------------------	
	
	//$_SESSION['successMessage']="Customer Update Success!";
	echo "<script>window.location='update_customer3?invuser=$invuser&&InvoiceID=$invid&&updatedsuccess';</script>";
	
}
	
	
}
else
{ echo "<script>window.location='dashboard';</script>";
}
?>