<?php include("checksession.php");
include("config.php");

if(isset($_REQUEST['addInvoice']))
{
	$user_type_Loginvl=$Login_user_TYPEvl;
	$user_id_Loginvl=$Login_user_IDvl;
	
	$inv_id=$_REQUEST['inv_id'];
	
	$customer_id=$_REQUEST['customer_id'];
	$date=date("Y-m-d",strtotime($_REQUEST['date']));
	$inv_year=date("Y",strtotime($_REQUEST['date']));
	
		
		//3. update invoice
		$update_Invoice="update invoice set customer_id='$customer_id',date='$date',inv_year='$inv_year' where inv_id='$inv_id' and user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'";
		mysqli_query($db_conn,$update_Invoice);
		
		//-------------------------------------------
		//insert product details
		//-------------------------------------------
		
	$pr_id=$_REQUEST['pr_id'];
	$amount=$_REQUEST['amount'];
	$qty=$_REQUEST['qty'];
	$total=$amount*$qty;
	
	
	//count available stock
	$select_count_AVSTOCK="select * from stock where product_id='$pr_id' and user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'";
	$FETCH_count_AVSTOCK=mysqli_query($db_conn,$select_count_AVSTOCK);
	$RESULT_count_AVSTOCK=mysqli_fetch_array($FETCH_count_AVSTOCK);
	$AVMstock=$RESULT_count_AVSTOCK['closing_qty'];
	
	if($AVMstock<$qty)
	{
		echo "<script>window.location='invoice?InvoiceID=".base64_encode($inv_id)."&&InvalidStock&&AlertStockError';</script>";
		
	}else{
	
	$select_count_invoiceItem="select count(*) as numInvoiceItem from invoice_items where inv_id='$inv_id' and pr_id='$pr_id' and user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'";
	$fetch_count_invoiceItem=mysqli_query($db_conn,$select_count_invoiceItem);
	$result_count_invoiceItem=mysqli_fetch_array($fetch_count_invoiceItem);
	if($result_count_invoiceItem['numInvoiceItem']==0)
	{
		
		//1. insert invoice Items
		$insert_InvoiceItems="insert into invoice_items (inv_id,pr_id,amount,qty,total,user_type,user_id)
		values ('$inv_id','$pr_id','$amount','$qty','$total','$Login_user_TYPEvl','$Login_user_IDvl')";
		mysqli_query($db_conn,$insert_InvoiceItems);
		
		//2. update stock
		$select_stockDetails="select * from stock where product_id='$pr_id' and user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Sales_stock=$result_stockDetails['sales_qty']+$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']-$qty;
		
		$update_stockDetails="update stock set sales_qty='$update_Sales_stock',closing_qty='$update_Closing_stock' where product_id='$pr_id' and user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'";
		mysqli_query($db_conn,$update_stockDetails);
		
		
		echo "<script>window.location='invoice?InvoiceID=".base64_encode($inv_id)."&&AddedSuccess&&FemiAdded';</script>";
		
	}else{
		
		echo "<script>window.location='invoice?InvoiceID=".base64_encode($inv_id)."&&ItemAlreadyExists&&AlertMessage';</script>";
	}
		
}


}
	
?>