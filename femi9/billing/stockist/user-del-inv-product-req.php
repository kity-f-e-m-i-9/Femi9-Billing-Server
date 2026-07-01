<?php include("checksession.php");
include("config.php");

if(isset($_REQUEST['inv_id']))
{
	$invoice_id_encode=$_REQUEST['inv_id'];
	$invuser=$_REQUEST['invuser'];
	$customer_id=$_REQUEST['userid'];
	
	$rowid_encode=$_REQUEST['rowid'];
	$rowid_decode=base64_decode($rowid_encode);
	
	//
	$select_INVProductDetails="select * from user_invoice_items where id='$rowid_decode'";
	$fetch_INVProductDetails=mysqli_query($db_conn,$select_INVProductDetails);
	$result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails);
	if($result_INVProductDetails['pr_id']!=NULL)
	{
	
		$pr_id=$result_INVProductDetails['pr_id'];
		$qty=$result_INVProductDetails['qty'];
		
		$Login_user_IDvl=$result_INVProductDetails['from_user_id'];
		
		//------------------------------
		//2. stock increment to company
		//------------------------------
		$select_stockDetails="select * from stock where product_id='$pr_id' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Sales_stock=$result_stockDetails['sales_qty']-$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty;
		
		$update_stockDetails="update stock set sales_qty='$update_Sales_stock',closing_qty='$update_Closing_stock' where product_id='$pr_id' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
		mysqli_query($db_conn,$update_stockDetails);
		
		//-------------------------------------------------------------------
		//3. stock decrement to user (super-stockist, stockist, distributor, outlet)
		//--------------------------------------------------------------------
		$select_stockDetails12="select * from stock where product_id='$pr_id' and user_type='$invuser' and user_id='$customer_id'";
		$fetch_stockDetails12=mysqli_query($db_conn,$select_stockDetails12);
		$result_stockDetails12=mysqli_fetch_array($fetch_stockDetails12);
		
		$update_Sales_stock12=$result_stockDetails12['input_qty']-$qty;
		$update_Closing_stock12=$result_stockDetails12['closing_qty']-$qty;
		
		$update_stockDetails="update stock set input_qty='$update_Sales_stock12',closing_qty='$update_Closing_stock12' where product_id='$pr_id' and user_type='$invuser' and user_id='$customer_id'";
		mysqli_query($db_conn,$update_stockDetails);
		
	}
	
	$delRecord="delete from user_invoice_items where id='$rowid_decode'";
	mysqli_query($db_conn,$delRecord);
	
	echo "<script>window.location='stock_request_details.php?reqid=".$invoice_id_encode."&&DeleteSuccess&&invuser=".$invuser."&&ActionRemove&&gid=".$Login_user_IDvl."';</script>";
	
}
?>