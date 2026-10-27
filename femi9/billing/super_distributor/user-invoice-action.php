<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
	$randum_number=$_REQUEST['randum_number'];
	$inv_id=$_REQUEST['inv_id'];
	$invuser=$_REQUEST['invuser'];
	
	//invoice accept=0
	if($_REQUEST['invoice_number_accept']==0)
	{
	$_SESSION['errorMessage']="Invoice Number already exists!";
	echo "<script>window.location='user-invoice-add.php?invoicealready&&invuser=".urlencode($invuser)."';</script>";
	exit;
	
	}else{
	$inv_number=str_replace("'","",$_REQUEST['inv_number']);
	$id_only="0";
	//HIDE AUTO INVOICE NUMBER -> below
	//---------------	
	
	$customer_id=$_REQUEST['customer_id'];
	$date=date("Y-m-d",strtotime($_REQUEST['date']));
	$inv_year=date("Y",strtotime($_REQUEST['date']));
	
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

//get company state code
$admin_statecode=$result_LoGuserDtails['state_id'];

	if($invuser=="distributor"){$tablename="distributor";}
	else{ $tablename="shop"; }
	
$selecutomser_dtails="select * from ".$tablename." where temp_id='".db_esc($customer_id)."'";
$fetchcutomser_dtails=mysqli_query($db_conn,$selecutomser_dtails);
$resultcutomser_dtails=mysqli_fetch_array($fetchcutomser_dtails);
$customer_state=$resultcutomser_dtails['state_id'];

$buyer_GSTIN=$resultcutomser_dtails['gstin'];
$buyer_GSTIN_count=strlen($buyer_GSTIN);
if($buyer_GSTIN_count==15){ $buyer_gsttype="register";}else {$buyer_gsttype="unregister";}

if($customer_state==$admin_statecode){$gst_type="inner";}
else{$gst_type="outer";}
//--------------------------------------------------------------------------------
//--------------------------------------------------------------------------------
	
	$select_count_invoice="select count(*) as numInvoice from user_invoice 
	where inv_id=? and from_user_type=? 
	and from_user_id=? and to_user_type=? and to_user_id=?";
	$fetch_count_invoice=db_q($db_conn,$select_count_invoice,[$inv_id,$Login_user_TYPEvl,$Login_user_IDvl,$invuser,$customer_id]);
	$result_count_invoice=mysqli_fetch_array($fetch_count_invoice);
	if($result_count_invoice['numInvoice']==0)
	{
		
		//2. insert invoice
		$insert_Invoice="insert into user_invoice (inv_id,id_only,inv_number,date,inv_year,sub_total,discount,total,
		to_user_type,to_user_id,from_user_type,from_user_id,gst_type,credit,
		roundoff,courier_charges,rwpoints_enable,buyer_gsttype,username,usertype)
		values 
		(?,?,?,?,?,'0','0','0',
		?,?,?,?,?,'0','0','0','1',
		?,'Nil','Nil')";
		db_q($db_conn,$insert_Invoice,[$inv_id,$id_only,$inv_number,$date,$inv_year,$invuser,$customer_id,$Login_user_TYPEvl,$Login_user_IDvl,$gst_type,$buyer_gsttype]);
		
	}
	
	
	//count available stock
	$select_count_AVSTOCK="select * from stock where product_id=? 
	and user_type=? and user_id=?";
	$FETCH_count_AVSTOCK=db_q($db_conn,$select_count_AVSTOCK,[$pr_id,$Login_user_TYPEvl,$Login_user_IDvl]);
	$RESULT_count_AVSTOCK=mysqli_fetch_array($FETCH_count_AVSTOCK);
	$AVMstock=$RESULT_count_AVSTOCK['closing_qty'];
	
	if($AVMstock<$qty)
	{
		echo "<script>window.location='user-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&InvalidStock&&invuser=".urlencode($invuser)."&&action=".urlencode($_SESSION['ACTIONEDIT'])."&&AlertStockError';</script>";
		exit;
		
	}else{
		
		//-------------------------------------------
		//insert product details
		//-------------------------------------------
		
	$select_count_invoiceItem="select count(*) as numInvoiceItem from user_invoice_items 
	where inv_id=? and pr_id=? and from_user_type=? 
	and from_user_id=? and to_user_type=? 
	and to_user_id=?";
	$fetch_count_invoiceItem=db_q($db_conn,$select_count_invoiceItem,[$inv_id,$pr_id,$Login_user_TYPEvl,$Login_user_IDvl,$invuser,$customer_id]);
	$result_count_invoiceItem=mysqli_fetch_array($fetch_count_invoiceItem);
	if($result_count_invoiceItem['numInvoiceItem']==0)
	{
		
		
		//1. insert invoice Items
		$insert_InvoiceItems="insert into user_invoice_items (inv_id,pr_id,amount,qty,total,to_user_type,to_user_id,from_user_type,
		from_user_id,
		gst_percentage,gstamount_singlepr,gstamount_total,subtotal,
		discount_percentage,discount_amount,gst_type,hsn,date,rwpoints,
		buyer_gsttype,rwpoints_sls)
		values (?,?,?,?,?,
		?,?,
		?,
		?,?,?,
		?,?,
		?,?,?,?,
		?,?,?,?)";
		db_q($db_conn,$insert_InvoiceItems,[$inv_id,$pr_id,$amount,$qty,$total,$invuser,$customer_id,$Login_user_TYPEvl,$Login_user_IDvl,$gst_percentage,$gstamount_singlepr,$gstamount_total,$subtotal,$discount_percentage,$discount_amount,$gst_type,$hsn,$date,$rwpoints,$buyer_gsttype,$rwpoints]);
		
		echo "<script>window.location='user-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&AddedSuccess&&invuser=".urlencode($invuser)."&&action=".urlencode($_SESSION['ACTIONEDIT'])."&&FemiAdded';</script>";
		exit;
		
	}else{
		
		echo "<script>window.location='user-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&ItemAlreadyExists&&invuser=".urlencode($invuser)."&&action=".urlencode($_SESSION['ACTIONEDIT'])."&&AlertMessage';</script>";
		exit;
	}
		
}

}

}
	
?>