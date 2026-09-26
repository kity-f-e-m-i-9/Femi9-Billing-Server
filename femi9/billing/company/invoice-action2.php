<?php include("checksession.php");
include("config.php");
require_once("include/StockService.php");

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

		// Invoice-level gst_type ("inner"/"outer") + buyer_gsttype were set
		// once at header creation (invoice-action.php) — reuse them here
		// rather than recomputing, same convention as customer-user-invoice-action2.php.
		$select_InvHeader=mysqli_query($db_conn,"select gst_type,buyer_gsttype from invoice where inv_id='$inv_id'");
		$result_InvHeader=mysqli_fetch_array($select_InvHeader);
		$gst_type=$result_InvHeader['gst_type'] ?: 'inner';
		$buyer_gsttype=$result_InvHeader['buyer_gsttype'] ?: 'unregister';

		//-------------------------------------------
		//insert product details
		//-------------------------------------------

	$pr_id=$_REQUEST['pr_id'];
	$amount=$_REQUEST['amount'];
	$qty=$_REQUEST['qty'];
	$totalamount=$amount*$qty;

	// Product GST — inclusive-tax products already have GST baked into the
	// entered price, so the tax is carved out of subtotal (and NOT added
	// again into total); exclusive-tax products get GST added on top —
	// same convention as tp-invoice-print.php.
	$select_prod=mysqli_query($db_conn,"select gst,gst_type,hsn from products where id='$pr_id'");
	$result_prod=mysqli_fetch_array($select_prod);
	$gst_percentage=$result_prod['gst'] ?? 0;
	$product_gst_type=($result_prod['gst_type'] ?? 'exclusive')==='inclusive' ? 'inclusive' : 'exclusive';
	$hsn=$result_prod['hsn'] ?? '';

	$subtotal=$totalamount;
	$discount_percentage=0;
	$discount_amount=0;
	if($product_gst_type==='inclusive' && $gst_percentage>0){
		$gstamount_total=$subtotal-($subtotal*100/(100+$gst_percentage));
		$total=$subtotal;
	}else{
		$gstamount_total=$subtotal*$gst_percentage/100;
		$total=$subtotal+$gstamount_total;
	}
	$gstamount_singlepr=0;


	//count available stock — same unassigned-warehouse row invoice-submit.php
	//deducts from (customer invoices have no warehouse picker), not whichever
	//row a warehouse_id-less query happens to return first.
	$stockService = new StockService($db_conn);
	$AVMstock = $stockService->getClosingQty($pr_id, $user_type_Loginvl, $user_id_Loginvl);

	if($AVMstock===null || $AVMstock<$qty)
	{
		echo "<script>window.location='invoice?InvoiceID=".base64_encode($inv_id)."&&InvalidStock&&AlertStockError';</script>";
		
	}else{
	
	$select_count_invoiceItem="select count(*) as numInvoiceItem from invoice_items where inv_id='$inv_id' and pr_id='$pr_id' and user_type='$user_type_Loginvl' and user_id='$user_id_Loginvl'";
	$fetch_count_invoiceItem=mysqli_query($db_conn,$select_count_invoiceItem);
	$result_count_invoiceItem=mysqli_fetch_array($fetch_count_invoiceItem);
	if($result_count_invoiceItem['numInvoiceItem']==0)
	{
		
		//1. insert invoice Items
		$insert_InvoiceItems="insert into invoice_items (inv_id,pr_id,amount,qty,total,user_type,user_id,
		gst_percentage,gstamount_singlepr,gstamount_total,subtotal,discount_percentage,discount_amount,gst_type,hsn,date,buyer_gsttype)
		values ('$inv_id','$pr_id','$amount','$qty','$total','$Login_user_TYPEvl','$Login_user_IDvl',
		'$gst_percentage','$gstamount_singlepr','$gstamount_total','$subtotal','$discount_percentage','$discount_amount','$gst_type','$hsn','$date','$buyer_gsttype')";
		mysqli_query($db_conn,$insert_InvoiceItems);

		//2. update stock — via StockService so this hits the same single
		//(unassigned) row getClosingQty() just checked, and gets a proper
		//ledger entry instead of silently bypassing it.
		try {
			$stockService->deduct(
				(int)$pr_id, $user_type_Loginvl, $user_id_Loginvl, (int)$qty,
				'invoice', $inv_id, $user_id_Loginvl, false
			);
		} catch (StockException $e) {
			echo "<script>window.location='invoice?InvoiceID=".base64_encode($inv_id)."&&InvalidStock&&AlertStockError';</script>";
			exit;
		}


		echo "<script>window.location='invoice?InvoiceID=".base64_encode($inv_id)."&&AddedSuccess&&FemiAdded';</script>";
		
	}else{
		
		echo "<script>window.location='invoice?InvoiceID=".base64_encode($inv_id)."&&ItemAlreadyExists&&AlertMessage';</script>";
	}
		
}


}
	
?>