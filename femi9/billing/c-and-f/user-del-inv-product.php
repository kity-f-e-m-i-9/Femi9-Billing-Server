<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");

if(isset($_REQUEST['inv_id']))
{
	$invoice_id_encode=$_REQUEST['inv_id'];
	$invuser=$_REQUEST['invuser'];
	$customer_id=$_REQUEST['userid'];
	
	$rowid_encode=$_REQUEST['rowid'];
	$rowid_decode=base64_decode($rowid_encode);
	
	//
	$select_INVProductDetails="select * from user_invoice_items where id=?";
	$fetch_INVProductDetails=db_q($db_conn,$select_INVProductDetails,[$rowid_decode]);
	$result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails);
	if($result_INVProductDetails['pr_id']!=NULL)
	{
	
		$pr_id=$result_INVProductDetails['pr_id'];
		$qty=$result_INVProductDetails['qty'];
		
		//------------------------------
		//2. stock increment to company
		//------------------------------
		$select_stockDetails="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails=db_q($db_conn,$select_stockDetails,[$pr_id,$Login_user_TYPEvl,$Login_user_IDvl]);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Sales_stock=$result_stockDetails['sales_qty']-$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty;
		
		$update_stockDetails="update stock set sales_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_Sales_stock,$update_Closing_stock,$pr_id,$Login_user_TYPEvl,$Login_user_IDvl]);
		
		//-------------------------------------------------------------------
		//3. stock decrement to user (super-stockist, stockist, distributor)
		//--------------------------------------------------------------------
		$select_stockDetails12="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails12=db_q($db_conn,$select_stockDetails12,[$pr_id,$invuser,$customer_id]);
		$result_stockDetails12=mysqli_fetch_array($fetch_stockDetails12);
		
		$update_Sales_stock12=$result_stockDetails12['input_qty']-$qty;
		$update_Closing_stock12=$result_stockDetails12['closing_qty']-$qty;
		
		$update_stockDetails="update stock set input_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_Sales_stock12,$update_Closing_stock12,$pr_id,$invuser,$customer_id]);
		
	}
	
	$delRecord="delete from user_invoice_items where id=?";
	db_q($db_conn,$delRecord,[$rowid_decode]);
	
	echo "<script>window.location='user-invoice-add.php?InvoiceID=".$invoice_id_encode."&&DeleteSuccess&&invuser=".$invuser."&&ActionRemove&&action=".$_SESSION['ACTIONEDIT']."';</script>";
	
}
?>