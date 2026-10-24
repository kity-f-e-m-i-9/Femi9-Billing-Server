<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
require_once("include/StockService.php");
error_reporting(0);

if(isset($_REQUEST['convertinvoice']))
{
	$reqid_encode=$_REQUEST['reqid'];
	$reqid=base64_decode($reqid_encode);
	//
	$select_requestdetails="select * from stock_request where reqid=?";
	 $fetch_requestdetails=db_q($db_conn,$select_requestdetails,[$reqid]);
	 $result_requestdetails=mysqli_fetch_array($fetch_requestdetails);
	
	$randum_number=rand(1,9899989);;
	$inv_id=$reqid;
	$invuser=$result_requestdetails['fromusertype'];
	$customer_id=$result_requestdetails['fromuserid'];
	
	
	//get customer state code
	if($invuser=="super_stockiest"){$tablename="super_stockiest";}
	else if($invuser=="stockiest"){	$tablename="stockiest";}
	else if($invuser=="distributor"){$tablename="distributor";}
	else if($invuser=="outlet"){$tablename="outlet";}
	else{
		//$tablename="shop";
	}
	
$selecutomser_dtails="select * from ".$tablename." where temp_id='$customer_id'";
$fetchcutomser_dtails=mysqli_query($db_conn,$selecutomser_dtails);
$resultcutomser_dtails=mysqli_fetch_array($fetchcutomser_dtails);
$customer_state=$resultcutomser_dtails['state_id'];

if($customer_state==$admin_statecode){$gst_type="inner";}
else{$gst_type="outer";}
	
	date_default_timezone_set("Asia/Kolkata");
	$invoice_date=date("Y-m-d");
	
	$date=date("Y-m-d",strtotime($invoice_date));
	$inv_year=date("Y",strtotime($invoice_date));
	
	$prid=$_REQUEST['prid'];
	
	$selectproducts="select * from products where id=?";
$fetchproducts=db_q($db_conn,$selectproducts,[$prid]);
$resultproducts=mysqli_fetch_array($fetchproducts);
$gst_percentage=$resultproducts['gst'];
$product_gst_type=($resultproducts['gst_type'] ?? 'exclusive')==='inclusive' ? 'inclusive' : 'exclusive';

$gstamount_singlepr="0";
	 
	 //
	 $select_pramount="select * from stock_request_items where reqid=? and prid=?";
	 $fetch_pramount=db_q($db_conn,$select_pramount,[$reqid,$prid]);
	 $result_pramount=mysqli_fetch_array($fetch_pramount);
	 
	$amount=$result_pramount['amount'];
	$qty=$result_pramount['qty'];
	$subtotal=$result_pramount['total'];

	// Inclusive-tax products already have GST baked into the requested price, so
	// the tax is carved out of subtotal (and NOT added again into total);
	// exclusive-tax products get GST added on top — same convention as
	// tp-invoice-print.php. (Previously $total was read here before it was
	// ever assigned, so gstamount_total silently evaluated to 0 regardless
	// of GST% — no tax was ever actually applied.)
	if($product_gst_type==='inclusive' && $gst_percentage>0){
		$gstamount_total=$subtotal-($subtotal*100/(100+$gst_percentage));
		$total=$subtotal;
	}else{
		$gstamount_total=$subtotal*$gst_percentage/100;
		$total=$subtotal+$gstamount_total;
	}
	
	$select_count_invoice="select count(*) as numInvoice from user_invoice where inv_id=? and from_user_type=? and from_user_id=? and to_user_type=? and to_user_id=?";
	$fetch_count_invoice=db_q($db_conn,$select_count_invoice,[$inv_id,$Login_user_TYPEvl,$Login_user_IDvl,$invuser,$customer_id]);
	$result_count_invoice=mysqli_fetch_array($fetch_count_invoice);
	if($result_count_invoice['numInvoice']==0)
	{
		//1. get last id_only (invoice number generate)
		$select_MaxID="select max(id_only) from user_invoice where from_user_type=? and from_user_id=?";
		$fetch_MaxID=db_q($db_conn,$select_MaxID,[$Login_user_TYPEvl,$Login_user_IDvl]);
		$result_MaxID=mysqli_fetch_array($fetch_MaxID);
		$id_only=$result_MaxID[0]+1;
		$format_num = str_pad($id_only, 3, '0', STR_PAD_LEFT);
		
		$INVDATE=date("Ymd",strtotime($invoice_date));
		$inv_number="".$INVDATE."/".$randum_number."/".$format_num."";
		
		//2. insert invoice
		$insert_Invoice="insert into user_invoice (inv_id,id_only,inv_number,date,inv_year,sub_total,discount,total,to_user_type,to_user_id,from_user_type,from_user_id,credit,gst_type)
		values 
		(?,?,?,?,?,'0','0','0',
		?,?,?,?,'0',?)";
		db_q($db_conn,$insert_Invoice,[$inv_id,$id_only,$inv_number,$date,$inv_year,$invuser,$customer_id,$Login_user_TYPEvl,$Login_user_IDvl,$gst_type]);
		
	}
	
	
	//count available stock — same unassigned row deductAndCredit() below
	//actually deducts from.
	$stockService = new StockService($db_conn);
	$AVMstock = $stockService->getClosingQty($prid, $Login_user_TYPEvl, $Login_user_IDvl);

	if($AVMstock===null || $AVMstock<$qty)
	{
		echo "<script>window.location='stock_request_details?reqid=".$reqid_encode."&&InvalidStock&&AlertStockError';</script>";
		
	}else{
		
		//-------------------------------------------
		//insert product details
		//-------------------------------------------
		
	$select_count_invoiceItem="select count(*) as numInvoiceItem from user_invoice_items where inv_id=? and pr_id=? and from_user_type=? and from_user_id=? and to_user_type=? and to_user_id=?";
	$fetch_count_invoiceItem=db_q($db_conn,$select_count_invoiceItem,[$inv_id,$prid,$Login_user_TYPEvl,$Login_user_IDvl,$invuser,$customer_id]);
	$result_count_invoiceItem=mysqli_fetch_array($fetch_count_invoiceItem);
	if($result_count_invoiceItem['numInvoiceItem']==0)
	{
		
		//1. insert invoice Items
		$insert_InvoiceItems="insert into user_invoice_items (inv_id,pr_id,amount,qty,gst_percentage,gstamount_singlepr,gstamount_total,total,to_user_type,to_user_id,from_user_type,from_user_id)
		values (?,?,?,?,?,?,'',?,?,?,?,?)";
		db_q($db_conn,$insert_InvoiceItems,[$inv_id,$prid,$amount,$qty,$gst_percentage,$gstamount_singlepr,$total,$invuser,$customer_id,$Login_user_TYPEvl,$Login_user_IDvl]);

		//------------------------------------------------------------------
		//2/3. Move stock from this seller to the buyer via StockService —
		//scoped to the same unassigned rows both sides always used (no
		//warehouse picker here), with a proper ledger entry.
		//------------------------------------------------------------------
		try {
			$stockService->deductAndCredit(
				(int)$prid, $Login_user_TYPEvl, $Login_user_IDvl, $invuser, $customer_id, (int)$qty,
				'user_invoice', $inv_id, $Login_user_IDvl
			);
		} catch (StockException $e) {
			echo "<script>window.location='stock_request_details?reqid=".$reqid_encode."&&InvalidStock&&AlertStockError';</script>";
			exit;
		}

		echo "<script>window.location='stock_request_details?reqid=".$reqid_encode."&&AddedSuccess&&&&FemiAdded';</script>";
		
	}else{
		
		echo "<script>window.location='stock_request_details?reqid=".$reqid_encode."&&ItemAlreadyExists&&AlertMessage';</script>";
	}
		
}

}
	
?>