<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");

$reqid_encode=$_REQUEST['reqid'];
$reqid=base64_decode($reqid_encode);
	
if(isset($_REQUEST['truncate']))
{
	
	$select_requestdetails="select * from stock_request where reqid=?";
	 $fetch_requestdetails=db_q($db_conn,$select_requestdetails,[$reqid]);
	 $result_requestdetails=mysqli_fetch_array($fetch_requestdetails);
	 
	$invuser=$result_requestdetails['fromusertype'];
	$customer_id=$result_requestdetails['fromuserid'];
	
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
		//2. stock increment to stokist
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
	
	echo "<script>window.location='stock_request_details.php?reqid=".$reqid_encode."&&DeleteSuccess&&ActionRemove';</script>";
	
}
else{
	
	echo "<script>window.location='stock_request_details.php?reqid=".$reqid_encode."';</script>";
	
}
?>