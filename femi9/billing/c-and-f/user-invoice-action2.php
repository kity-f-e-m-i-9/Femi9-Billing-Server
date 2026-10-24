<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);

if(isset($_REQUEST['addInvoice2']))
{
	$inv_id=$_REQUEST['inv_id'];
	//
	$select_INVProductDetails="select * from user_invoice where inv_id=?";
	$fetch_INVProductDetails=db_q($db_conn,$select_INVProductDetails,[$inv_id]);
	$result_INVProductDetails=mysqli_fetch_array($fetch_INVProductDetails);
	$gst_type=$result_INVProductDetails['gst_type'];
	$buyer_gsttype=$result_INVProductDetails['buyer_gsttype'];
	
	$invuser=$_REQUEST['invuser'];
	
	$customer_id=$_REQUEST['customer_id'];
	$date=date("Y-m-d",strtotime($_REQUEST['date']));
	$inv_year=date("Y",strtotime($_REQUEST['date']));
		
		//3. update invoice
		$update_Invoice="update user_invoice set to_user_id=?,date=?,inv_year=? where inv_id=? and from_user_type=? and from_user_id=? and to_user_type=? and to_user_id=?";
		db_q($db_conn,$update_Invoice,[$customer_id,$date,$inv_year,$inv_id,$Login_user_TYPEvl,$Login_user_IDvl,$invuser,$customer_id]);
		
		//-------------------------------------------
		//insert product details
		//-------------------------------------------
		
	$pr_id=$_REQUEST['pr_id'];
	$amount=$_REQUEST['amount'];
	$qty=$_REQUEST['qty'];
	
//--------------------------------------------------------------------------------
//--------------------------------------------------------------------------------
$totalamount=$amount*$qty;
	
//--------------------------TOTAL, GST, DISCOUNT------------------
//----------------------------------------------------------------
//get product gst
$selectproducts="select * from products where id=?";
$fetchproducts=db_q($db_conn,$selectproducts,[$pr_id]);
$resultproducts=mysqli_fetch_array($fetchproducts);
$gst_percentage=$resultproducts['gst'];
$hsn=$resultproducts['hsn'];
$rwpoints=$resultproducts['rwpoints']*$qty;

//$gstamount_singlepr=($amount*$gst_percentage/100);
//$gstamount_singlepr=number_format($gstamount_singlepr,2,'.','');
$gstamount_singlepr="0";

if($_REQUEST['discount_percentage']>0)
{
$discount_percentage=$_REQUEST['discount_percentage'];
$discount_amount=$totalamount*$discount_percentage/100;
$discount_amount=number_format($discount_amount,2,'.','');
}else{
$discount_amount=$_REQUEST['discount_amount'];
$discount_percentage=$discount_amount*100/$totalamount;
$discount_percentage=number_format($discount_percentage,2,'.','');
}

$subtotal=$totalamount-$discount_amount;
$subtotal=number_format($subtotal,2,'.','');
 
$gstamount_total=($subtotal*$gst_percentage/100); 
$total=$subtotal+$gstamount_total;
//----------------------------------------------------------------
	
	
	//count available stock
	$select_count_AVSTOCK="select * from stock where product_id=? and user_type=? and user_id=?";
	$FETCH_count_AVSTOCK=db_q($db_conn,$select_count_AVSTOCK,[$pr_id,$Login_user_TYPEvl,$Login_user_IDvl]);
	$RESULT_count_AVSTOCK=mysqli_fetch_array($FETCH_count_AVSTOCK);
	$AVMstock=$RESULT_count_AVSTOCK['closing_qty'];
	
	if($AVMstock<$qty)
	{
		echo "<script>window.location='user-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&InvalidStock&&invuser=".$invuser."&&AlertStockError&&action=".$_SESSION['ACTIONEDIT']."';</script>";
		
	}else{
	
	$select_count_invoiceItem="select count(*) as numInvoiceItem from user_invoice_items where inv_id=? and pr_id=? and from_user_type=? and from_user_id=? and to_user_type=? and to_user_id=?";
	$fetch_count_invoiceItem=db_q($db_conn,$select_count_invoiceItem,[$inv_id,$pr_id,$Login_user_TYPEvl,$Login_user_IDvl,$invuser,$customer_id]);
	$result_count_invoiceItem=mysqli_fetch_array($fetch_count_invoiceItem);
	if($result_count_invoiceItem['numInvoiceItem']==0)
	{
		
		//1. insert invoice Items
		$insert_InvoiceItems="insert into user_invoice_items (inv_id,pr_id,amount,qty,total,to_user_type,to_user_id,from_user_type,from_user_id,
		gst_percentage,gstamount_singlepr,gstamount_total,
		subtotal,discount_percentage,discount_amount,gst_type,hsn,date,rwpoints,buyer_gsttype)
		values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
		db_q($db_conn,$insert_InvoiceItems,[$inv_id,$pr_id,$amount,$qty,$total,$invuser,$customer_id,$Login_user_TYPEvl,$Login_user_IDvl,$gst_percentage,$gstamount_singlepr,$gstamount_total,$subtotal,$discount_percentage,$discount_amount,$gst_type,$hsn,$date,$rwpoints,$buyer_gsttype]);
		
		//------------------------------
		//2. stock decrement to company
		//------------------------------
		$select_stockDetails="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails=db_q($db_conn,$select_stockDetails,[$pr_id,$Login_user_TYPEvl,$Login_user_IDvl]);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Sales_stock=$result_stockDetails['sales_qty']+$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']-$qty;
		
		$update_stockDetails="update stock set sales_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_Sales_stock,$update_Closing_stock,$pr_id,$Login_user_TYPEvl,$Login_user_IDvl]);
		
		//------------------------------------------------------------------------
		//insert product stock first
		//------------------------------------------------------------------------
		$select_stockDetailscheck="select count(*) as numprcheck from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetailscheck=db_q($db_conn,$select_stockDetailscheck,[$pr_id,$invuser,$customer_id]);
		$result_stockDetailscheck=mysqli_fetch_array($fetch_stockDetailscheck);
		if($result_stockDetailscheck['numprcheck']==0)
		{
			$insertprstock="insert into stock (product_id,opening_qty,opening_date,input_qty,sales_qty,sent_qty,returnqty,closing_qty,user_type,user_id) values (?,'0',?,'0','0','0','0','0',?,?)";
			db_q($db_conn,$insertprstock,[$pr_id,$date,$invuser,$customer_id]);
		}
		//-------------------------------------------------------------------------
		
		//-------------------------------------------------------------------
		//3. stock increment to user (super-stockist, stockist, distributor)
		//--------------------------------------------------------------------
		$select_stockDetails12="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails12=db_q($db_conn,$select_stockDetails12,[$pr_id,$invuser,$customer_id]);
		$result_stockDetails12=mysqli_fetch_array($fetch_stockDetails12);
		
		$update_Sales_stock12=$result_stockDetails12['input_qty']+$qty;
		$update_Closing_stock12=$result_stockDetails12['closing_qty']+$qty;
		
		$update_stockDetails="update stock set input_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_Sales_stock12,$update_Closing_stock12,$pr_id,$invuser,$customer_id]);
		
		
		echo "<script>window.location='user-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&AddedSuccess&&invuser=".$invuser."&&FemiAdded&&action=".$_SESSION['ACTIONEDIT']."';</script>";
		
	}else{
		
		echo "<script>window.location='user-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&ItemAlreadyExists&&invuser=".$invuser."&&AlertMessage&&action=".$_SESSION['ACTIONEDIT']."';</script>";
	}
		
}


}
	
?>