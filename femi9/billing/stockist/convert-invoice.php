<?php include("checksession.php");
include("config.php");
error_reporting(0);

if(isset($_REQUEST['convertinvoice']))
{
	$reqid_encode=$_REQUEST['reqid'];
	$reqid=base64_decode($reqid_encode);
	//
	$select_requestdetails="select * from stock_request where reqid='$reqid'";
	 $fetch_requestdetails=mysqli_query($db_conn,$select_requestdetails);
	 $result_requestdetails=mysqli_fetch_array($fetch_requestdetails);
	
	$randum_number=rand(1,9899989);;
	$inv_id=$reqid;
	$invuser=$result_requestdetails['fromusertype'];
	$customer_id=$result_requestdetails['fromuserid'];
	
	date_default_timezone_set("Asia/Kolkata");
	$invoice_date=date("Y-m-d");
	
	$date=date("Y-m-d",strtotime($invoice_date));
	$inv_year=date("Y",strtotime($invoice_date));
	
	$prid=$_REQUEST['prid'];
	 
	 //
	 $select_pramount="select * from stock_request_items where reqid='$reqid' and prid='$prid'";
	 $fetch_pramount=mysqli_query($db_conn,$select_pramount);
	 $result_pramount=mysqli_fetch_array($fetch_pramount);
	 
	$amount=$result_pramount['amount'];
	$qty=$result_pramount['qty'];
	$total=$result_pramount['total'];
	
	$select_count_invoice="select count(*) as numInvoice from user_invoice where inv_id='$inv_id' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and to_user_type='$invuser' and to_user_id='$customer_id'";
	$fetch_count_invoice=mysqli_query($db_conn,$select_count_invoice);
	$result_count_invoice=mysqli_fetch_array($fetch_count_invoice);
	if($result_count_invoice['numInvoice']==0)
	{
		//1. get last id_only (invoice number generate)
		$select_MaxID="select max(id_only) from user_invoice where from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
		$fetch_MaxID=mysqli_query($db_conn,$select_MaxID);
		$result_MaxID=mysqli_fetch_array($fetch_MaxID);
		$id_only=$result_MaxID[0]+1;
		$format_num = str_pad($id_only, 3, '0', STR_PAD_LEFT);
		
		$INVDATE=date("Ymd",strtotime($invoice_date));
		$inv_number="".$INVDATE."/".$randum_number."/".$format_num."";
		
		//2. insert invoice
		$insert_Invoice="insert into user_invoice (inv_id,id_only,inv_number,date,inv_year,sub_total,discount,total,to_user_type,to_user_id,from_user_type,from_user_id)
		values 
		('$inv_id','$id_only','$inv_number','$date','$inv_year','0','0','0',
		'$invuser','$customer_id','$Login_user_TYPEvl','$Login_user_IDvl')";
		mysqli_query($db_conn,$insert_Invoice);
		
	}
	
	
	//count available stock
	$select_count_AVSTOCK="select * from stock where product_id='$prid' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
	$FETCH_count_AVSTOCK=mysqli_query($db_conn,$select_count_AVSTOCK);
	$RESULT_count_AVSTOCK=mysqli_fetch_array($FETCH_count_AVSTOCK);
	$AVMstock=$RESULT_count_AVSTOCK['closing_qty'];
	
	if($AVMstock<$qty)
	{
		echo "<script>window.location='stock_request_details.php?reqid=".$reqid_encode."&&InvalidStock&&AlertStockError';</script>";
		
	}else{
		
		//-------------------------------------------
		//insert product details
		//-------------------------------------------
		
	$select_count_invoiceItem="select count(*) as numInvoiceItem from user_invoice_items where inv_id='$inv_id' and pr_id='$prid' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and to_user_type='$invuser' and to_user_id='$customer_id'";
	$fetch_count_invoiceItem=mysqli_query($db_conn,$select_count_invoiceItem);
	$result_count_invoiceItem=mysqli_fetch_array($fetch_count_invoiceItem);
	if($result_count_invoiceItem['numInvoiceItem']==0)
	{
		
		//1. insert invoice Items
		$insert_InvoiceItems="insert into user_invoice_items (inv_id,pr_id,amount,qty,total,to_user_type,to_user_id,from_user_type,from_user_id)
		values ('$inv_id','$prid','$amount','$qty','$total','$invuser','$customer_id','$Login_user_TYPEvl','$Login_user_IDvl')";
		mysqli_query($db_conn,$insert_InvoiceItems);
		
		//------------------------------
		//2. stock decrement to stockist
		//------------------------------
		$select_stockDetails="select * from stock where product_id='$prid' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Sales_stock=$result_stockDetails['sales_qty']+$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']-$qty;
		
		$update_stockDetails="update stock set sales_qty='$update_Sales_stock',closing_qty='$update_Closing_stock' where product_id='$prid' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
		mysqli_query($db_conn,$update_stockDetails);
		
		//-------------------------------------------------------------------
		//3. stock increment to user (distributor)
		//--------------------------------------------------------------------
		$select_stockDetails12="select * from stock where product_id='$prid' and user_type='$invuser' and user_id='$customer_id'";
		$fetch_stockDetails12=mysqli_query($db_conn,$select_stockDetails12);
		$result_stockDetails12=mysqli_fetch_array($fetch_stockDetails12);
		
		$update_Sales_stock12=$result_stockDetails12['input_qty']+$qty;
		$update_Closing_stock12=$result_stockDetails12['closing_qty']+$qty;
		
		$update_stockDetails="update stock set input_qty='$update_Sales_stock12',closing_qty='$update_Closing_stock12' where product_id='$prid' and user_type='$invuser' and user_id='$customer_id'";
		mysqli_query($db_conn,$update_stockDetails);
		
		
		echo "<script>window.location='stock_request_details.php?reqid=".$reqid_encode."&&AddedSuccess&&&&FemiAdded';</script>";
		
	}else{
		
		echo "<script>window.location='stock_request_details.php?reqid=".$reqid_encode."&&ItemAlreadyExists&&AlertMessage';</script>";
	}
		
}

}
	
?>