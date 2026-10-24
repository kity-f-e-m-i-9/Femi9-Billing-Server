<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if(isset($_REQUEST['add-return']))
{
	
	$from_usertype=$_REQUEST['from_usertype']; //ss, stockist, distributor, shop, customer
	$from_userid=$_REQUEST['from_userid'];
	
	//-------------------------------------------------------------------------------
	if($from_usertype=="super_stockiest"){$tablename="super_stockiest";}
	else if($from_usertype=="stockiest"){	$tablename="stockiest";}
	else if($from_usertype=="super_distributor"){$tablename="super_distributor";}
	else if($from_usertype=="distributor"){$tablename="distributor";}
	else if($from_usertype=="outlet"){$tablename="outlet";}
	else if($from_usertype=="shop"){$tablename="shop";}
	else{
		$tablename="customers";
	}
	
	if($tablename!="customers"){
$selecutomser_dtails="select * from ".$tablename." where temp_id='$from_userid'";
	}
	else{
		$selecutomser_dtails="select * from ".$tablename." where id='$from_userid'";
	}
	
$fetchcutomser_dtails=mysqli_query($db_conn,$selecutomser_dtails);
$resultcutomser_dtails=mysqli_fetch_array($fetchcutomser_dtails);

$buyer_GSTIN=$resultcutomser_dtails['gstin'];
$buyer_GSTIN_count=strlen($buyer_GSTIN);
if($buyer_GSTIN_count==15){ $buyer_gsttype="register";}else {$buyer_gsttype="unregister";}
//-------------------------------------------------------------------------------

	$to_usertype=$_REQUEST['to_usertype'];
	$to_userid=$_REQUEST['to_userid'];
	
	$returnid=$_REQUEST['returnid'];
	
	$invnumber=$_REQUEST['invid'];
	$invid=$_REQUEST['invid'];
	
	//INVOICE DETAILS
$Select_Invoice_Details="select rwpoints_enable,gst_type from user_invoice where inv_id=?";
$Fetch_Invoice_Details=db_q($db_conn,$Select_Invoice_Details,[$invid]);
$Result_Invoice_Details=mysqli_fetch_array($Fetch_Invoice_Details);
if($Result_Invoice_Details['rwpoints_enable']!=NULL)
{
$rwpoints_enable=$Result_Invoice_Details['rwpoints_enable'];
}else{ $rwpoints_enable="0";}

$gst_type=$Result_Invoice_Details['gst_type'];
	
	$prid=$_REQUEST['prid'];
	//get product gst
$selectproducts="select * from products where id=?";
$fetchproducts=db_q($db_conn,$selectproducts,[$prid]);
$resultproducts=mysqli_fetch_array($fetchproducts);
$gst_percentage=$resultproducts['gst'];
$hsn=$resultproducts['hsn'];

	$returnqty=$_REQUEST['returnqty'];
	$damaged_qty=$_REQUEST['damaged_qty'];
	$rwpoints=$resultproducts['rwpoints']*$returnqty;
	
	if($from_usertype=="customer")
	{
	$select_invoicedetails="select * from invoice_items where inv_id='".db_esc($invid)."' and pr_id='".db_esc($prid)."'";
	}else{
	$select_invoicedetails="select * from user_invoice_items where inv_id=? and pr_id=?";
	}
	$fetch_invoicedetails=db_q($db_conn,$select_invoicedetails,[$invid,$prid]);
	$result_invoicedtails=mysqli_fetch_array($fetch_invoicedetails);
	//
	$invoiceqty=$result_invoicedtails['qty'];
	$pr_mrp=$result_invoicedtails['amount'];
	
	// Respect the product's own price-tax setting (same convention as
	// user-invoice-action.php / internal_transfer_action.php). An 'inclusive'
	// product's invoiced rate already has GST baked in, so the tax is carved
	// OUT of subtotal and total collapses to subtotal; adding GST on top again
	// would inflate the credit note and any advance-payment credit from it.
	$product_gst_type=(($resultproducts['gst_type'] ?? 'exclusive')==='inclusive') ? 'inclusive' : 'exclusive';
	$subtotal=$pr_mrp*$returnqty;
	if($product_gst_type==='inclusive' && $gst_percentage>0){
		$gstamount_total=$subtotal-($subtotal*100/(100+$gst_percentage));
		$total=$subtotal;
	}else{
		$gstamount_total=$subtotal*$gst_percentage/100;
		$total=$subtotal+$gstamount_total;
	}
	
	if($returnqty<=$invoiceqty)
	{
		$return_date=date("Y-m-d");
		
		$seletcountreturn="select count(*) as numreturncheck from user_return_stock where returnid=?";
		$fetchcountreturn=db_q($db_conn,$seletcountreturn,[$returnid]);
		$resultcountreturn=mysqli_fetch_array($fetchcountreturn);
		if($resultcountreturn['numreturncheck']==0)
		{
			
			$insertreturn="insert into user_return_stock (returnid,invnumber,date,subtotal,discount,total,from_usertype,from_userid,
			to_usertype,to_userid,status,rwpoints_enable,buyer_gsttype,gst_type) values 
			(?,?,?,'0','0','0',
			?,?,?,?,'pending',?,
			?,?)";
			db_q($db_conn,$insertreturn,[$returnid,$invnumber,$return_date,$from_usertype,$from_userid,$to_usertype,$to_userid,$rwpoints_enable,$buyer_gsttype,$gst_type]);
		}
		
		
		
		$seletcountreturn2="select count(*) as numreturncheck2 from user_return_stock_items where invnumber=? and prid=?";
		$fetchcountreturn2=db_q($db_conn,$seletcountreturn2,[$invnumber,$prid]);
		$resultcountreturn2=mysqli_fetch_array($fetchcountreturn2);
		if($resultcountreturn2['numreturncheck2']==0)
		{
			
			$insertreturn="insert into user_return_stock_items (returnid,invnumber,prid,amount,qty,subtotal,gst_percentage,gstamount_total,total,
			from_usertype,from_userid,to_usertype,to_userid,date,status,hsn,damaged_qty,
			rwpoints,buyer_gsttype,gst_type) values 
			(?,?,?,?,?,?,?,
			?,?,?,?,
			?,?,?,'pending',?,?,?,
			?,?)";
			db_q($db_conn,$insertreturn,[$returnid,$invnumber,$prid,$pr_mrp,$returnqty,$subtotal,$gst_percentage,$gstamount_total,$total,$from_usertype,$from_userid,$to_usertype,$to_userid,$return_date,$hsn,$damaged_qty,$rwpoints,$buyer_gsttype,$gst_type]);
			
			
		//--------------------------------------------------
		//STOCK INCREMENT TO RECEIVED USERS (ex:- company)
		$select_stockDetails="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails=db_q($db_conn,$select_stockDetails,[$prid,$to_usertype,$to_userid]);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_returnqty=$result_stockDetails['sales_qty']-$returnqty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$returnqty;
		
		$update_stockDetails="update stock set sales_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_returnqty,$update_Closing_stock,$prid,$to_usertype,$to_userid]);
		
		
		if($from_usertype=="super_stockiest" || $from_usertype=="stockiest" || $from_usertype=="distributor" || $from_usertype=="super_distributor")
		{
		
		//STOCK DECREMENT TO SENT USERS (ex:- super-stockist, stockist, distributor)
		$select_stockDetails12="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails12=db_q($db_conn,$select_stockDetails12,[$prid,$from_usertype,$from_userid]);
		$result_stockDetails12=mysqli_fetch_array($fetch_stockDetails12);
		
		$update_returnqty12=$result_stockDetails12['input_qty']-$returnqty;
		$update_Closing_stock12=$result_stockDetails12['closing_qty']-$returnqty;
		
		$update_stockDetails12="update stock set input_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails12,[$update_returnqty12,$update_Closing_stock12,$prid,$from_usertype,$from_userid]);
		
		}
		//---------------------------------------------
		
		echo "<script>window.location='cnote_new.php?returnid=".base64_encode($returnid)."&&InvoiceID=".base64_encode($invnumber)."&&addedsuccess';</script>";
			
			
		}else{
			
			echo "<script>window.location='cnote_new.php?returnid=".base64_encode($returnid)."&&InvoiceID=".base64_encode($invnumber)."&&productalreadyexists';</script>";
			
		}
		
		
	}
	else
	{
		
		echo "<script>window.location='cnote_new.php?returnid=".base64_encode($returnid)."&&InvoiceID=".base64_encode($invnumber)."&&invalidqty';</script>";
	}


//if not submit
}
else
{
echo "<script>window.location='dashboard';</script>";	
}	

?>