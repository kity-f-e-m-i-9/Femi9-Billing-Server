<?php
session_start();
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

$select_invdetails="select * from user_invoice where inv_id='$invid'";
$fetch_invdetails=mysqli_query($db_conn,$select_invdetails);
$result_invdetails=mysqli_fetch_array($fetch_invdetails);
$cus_tempid=$result_invdetails['to_user_id'];

if($old_customer_id==$cus_tempid)
{	


$select_prlist="select * from user_invoice_items where inv_id='$invid'";
$fetch_prlist=mysqli_query($db_conn,$select_prlist);
while($resultprlist=mysqli_fetch_array($fetch_prlist))
{

$product_id=$resultprlist['pr_id'];
$qty=$resultprlist['qty'];

//stock decrement to old customer
$select_old_cus_stock="select * from stock where user_id='$old_customer_id' and product_id='$product_id'";
$fetch_old_cus_stock=mysqli_query($db_conn,$select_old_cus_stock);
$result_old_cus_stock=mysqli_fetch_array($fetch_old_cus_stock);

$update_old_cus_selling_stock=$result_old_cus_stock['input_qty']-$qty;
$update_old_cus_closing_stock=$result_old_cus_stock['closing_qty']-$qty;

$update_old_cus_stock="update stock set input_qty='$update_old_cus_selling_stock',closing_qty='$update_old_cus_closing_stock' where user_id='$old_customer_id' and product_id='$product_id'";
mysqli_query($db_conn,$update_old_cus_stock);


//stock increment to new customer
$select_new_cus_stock="select * from stock where user_id='$new_customer_id' and product_id='$product_id'";
$fetch_new_cus_stock=mysqli_query($db_conn,$select_new_cus_stock);
$result_new_cus_stock=mysqli_fetch_array($fetch_new_cus_stock);

$update_new_cus_selling_stock=$result_new_cus_stock['input_qty']+$qty;
$update_new_cus_closing_stock=$result_new_cus_stock['closing_qty']+$qty;

$update_new_cus_stock="update stock set input_qty='$update_new_cus_selling_stock',closing_qty='$update_new_cus_closing_stock' where user_id='$new_customer_id' and product_id='$product_id'";
mysqli_query($db_conn,$update_new_cus_stock);


}

}


$update_user_invoice="update user_invoice set to_user_id='$new_customer_id' where inv_id='$invid'";
mysqli_query($db_conn,$update_user_invoice);

$update_user_invoice12="update user_invoice_items set to_user_id='$new_customer_id' where inv_id='$invid'";
mysqli_query($db_conn,$update_user_invoice12);

$update_user_receipt="update receipt set to_user_id='$new_customer_id' where inv_id='$invid'";
mysqli_query($db_conn,$update_user_receipt);

//------------------------------------------------------------------
//------------------------------------------------------------------	
	
	//$_SESSION['successMessage']="Customer Update Success!";
	echo "<script>window.location='update_customer?invuser=$invuser&&InvoiceID=$invid&&updatedsuccess';</script>";
	
	
	
}
else
{ echo "<script>window.location='dashboard';</script>";
}
?>