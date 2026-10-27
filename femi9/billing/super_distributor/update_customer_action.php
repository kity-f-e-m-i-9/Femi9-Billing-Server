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

//-------------------------------------------------------------------	
//-------------------------------------------------------------------	

$select_invdetails="select * from user_invoice where inv_id=?";
$fetch_invdetails=db_q($db_conn,$select_invdetails,[$invid]);
$result_invdetails=mysqli_fetch_array($fetch_invdetails);
$cus_tempid=$result_invdetails['to_user_id'];

if($old_customer_id==$cus_tempid)
{	


$select_prlist="select * from user_invoice_items where inv_id=?";
$fetch_prlist=db_q($db_conn,$select_prlist,[$invid]);
while($resultprlist=mysqli_fetch_array($fetch_prlist))
{

$product_id=$resultprlist['pr_id'];
$qty=$resultprlist['qty'];

//stock decrement to old customer
$select_old_cus_stock="select * from stock where user_id=? and product_id=?";
$fetch_old_cus_stock=db_q($db_conn,$select_old_cus_stock,[$old_customer_id,$product_id]);
$result_old_cus_stock=mysqli_fetch_array($fetch_old_cus_stock);

$update_old_cus_selling_stock=$result_old_cus_stock['input_qty']-$qty;
$update_old_cus_closing_stock=$result_old_cus_stock['closing_qty']-$qty;

$update_old_cus_stock="update stock set input_qty=?,closing_qty=? where user_id=? and product_id=?";
db_q($db_conn,$update_old_cus_stock,[$update_old_cus_selling_stock,$update_old_cus_closing_stock,$old_customer_id,$product_id]);


//stock increment to new customer
$select_new_cus_stock="select * from stock where user_id=? and product_id=?";
$fetch_new_cus_stock=db_q($db_conn,$select_new_cus_stock,[$new_customer_id,$product_id]);
$result_new_cus_stock=mysqli_fetch_array($fetch_new_cus_stock);

$update_new_cus_selling_stock=$result_new_cus_stock['input_qty']+$qty;
$update_new_cus_closing_stock=$result_new_cus_stock['closing_qty']+$qty;

$update_new_cus_stock="update stock set input_qty=?,closing_qty=? where user_id=? and product_id=?";
db_q($db_conn,$update_new_cus_stock,[$update_new_cus_selling_stock,$update_new_cus_closing_stock,$new_customer_id,$product_id]);


}

}


$update_user_invoice="update user_invoice set to_user_id=? where inv_id=?";
db_q($db_conn,$update_user_invoice,[$new_customer_id,$invid]);

$update_user_invoice12="update user_invoice_items set to_user_id=? where inv_id=?";
db_q($db_conn,$update_user_invoice12,[$new_customer_id,$invid]);

$update_user_receipt="update receipt set to_user_id=? where inv_id=?";
db_q($db_conn,$update_user_receipt,[$new_customer_id,$invid]);

//------------------------------------------------------------------
//------------------------------------------------------------------	
	
	//$_SESSION['successMessage']="Customer Update Success!";
	echo "<script>window.location='update_customer?invuser=".urlencode($invuser)."&&InvoiceID=".urlencode($invid)."&&updatedsuccess';</script>";
	
	
	
}
else
{ echo "<script>window.location='dashboard';</script>";
}
?>