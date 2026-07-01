<?php include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");

	$randum_number=$_REQUEST['randum_number'];
	$inv_id=$_REQUEST['inv_id'];
	$invuser=$_REQUEST['invuser'];
	
	$godownid=$_REQUEST['godownid'];
	$Login_user_IDvl=$godownid;
	
	//invoice accept=0
	if($_REQUEST['invoice_number_accept']==0)
	{
	$_SESSION['errorMessage']="Invoice Number already exists!";
	echo "<script>window.location='shop-user-invoice-add?invoicealready';</script>";
	}else{
    $inv_number = RemoveSpecialChar($_REQUEST['inv_number']);
	$inv_number=str_replace("'","",$_REQUEST['inv_number']);
	$id_only="0";
	//HIDE AUTO INVOICE NUMBER -> below
	//---------------	
	
	
$select_count_opstock13="select count(*) as numopstock12 from stock where user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
$fetch_count_opstock13=mysqli_query($db_conn,$select_count_opstock13);
$result_count_opstock13=mysqli_fetch_array($fetch_count_opstock13);
if($result_count_opstock13['numopstock12']==0)
{
	echo "<script>window.location='shop-user-invoice-add?invuser=".$invuser."&&gid=".$godownid."&&action=".$_SESSION['ACTIONEDIT']."&&stocknotupdated';</script>";
	
}else{
	
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
$selectproducts="select * from products where id='$pr_id'";
$fetchproducts=mysqli_query($db_conn,$selectproducts);
$resultproducts=mysqli_fetch_array($fetchproducts);
$gst_percentage=$resultproducts['gst'];
$hsn=$resultproducts['hsn'];
$rwpoints="0";

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

//get company state code
$sqladminlog="select * from admin_log where usertype='admin'";
$resultadminlog=mysqli_query($db_conn,$sqladminlog);
$fetch_resultlog=mysqli_fetch_array($resultadminlog);
$admin_statecode=$fetch_resultlog['state'];

$selecutomser_dtails="select * from shop where temp_id='$customer_id'";
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

	
	$select_count_invoice="select count(*) as numInvoice from user_invoice where inv_id='$inv_id' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and to_user_type='$invuser' and to_user_id='$customer_id'";
	$fetch_count_invoice=mysqli_query($db_conn,$select_count_invoice);
	$result_count_invoice=mysqli_fetch_array($fetch_count_invoice);
	if($result_count_invoice['numInvoice']==0)
	{
		//1. get last id_only (invoice number generate)
		/*$select_MaxID="select max(id_only) from user_invoice where from_user_type='$Login_user_TYPEvl' and from_user_id='$godownid'";
		$fetch_MaxID=mysqli_query($db_conn,$select_MaxID);
		$result_MaxID=mysqli_fetch_row($fetch_MaxID);
	    $id_only=$result_MaxID[0]+1;
		$format_num = str_pad($id_only, 3, '0', STR_PAD_LEFT);
		
		$INVDATE=date("ymd",strtotime($_REQUEST['date']));
		
	if($invuser=="super_stockiest"){$INVNUMUSER="SS";}
	else if($invuser=="stockiest"){	$INVNUMUSER="S";}
	else if($invuser=="distributor"){$INVNUMUSER="D";}
	else if($invuser=="outlet"){$INVNUMUSER="";}
	else{$INVNUMUSER="R";}
	
		$inv_number="F9".$randum_number."".$INVNUMUSER."".$format_num."";*/
		
		$username=$_REQUEST['username'];
	    $usertype=$_REQUEST['usertype'];
	
		//2. insert invoice
		$insert_Invoice="insert into user_invoice (inv_id,id_only,inv_number,date,inv_year,sub_total,discount,total,to_user_type,to_user_id,from_user_type,from_user_id,gst_type,credit,roundoff,courier_charges,rwpoints_enable,buyer_gsttype,username,usertype)
		values 
		('$inv_id','$id_only','$inv_number','$date','$inv_year','0','0','0',
		'$invuser','$customer_id','$Login_user_TYPEvl','$Login_user_IDvl','$gst_type','0','0','0','1',
		'$buyer_gsttype','$username','$usertype')";
		mysqli_query($db_conn,$insert_Invoice);
		
	}
	
	
	//count available stock
	$select_count_AVSTOCK="select * from stock where product_id='$pr_id' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
	$FETCH_count_AVSTOCK=mysqli_query($db_conn,$select_count_AVSTOCK);
	$RESULT_count_AVSTOCK=mysqli_fetch_array($FETCH_count_AVSTOCK);
	$AVMstock=$RESULT_count_AVSTOCK['closing_qty'];
	
	if($AVMstock<$qty)
	{
		echo "<script>window.location='shop-user-invoice-add?InvoiceID=".base64_encode($inv_id)."&&InvalidStock&&invuser=".$invuser."&&AlertStockError&&action=".$_SESSION['ACTIONEDIT']."&&gid=".$godownid."';</script>";
		
	}else{
		
		//-------------------------------------------
		//insert product details
		//-------------------------------------------
		
	$select_count_invoiceItem="select count(*) as numInvoiceItem from user_invoice_items where inv_id='$inv_id' and pr_id='$pr_id' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl' and to_user_type='$invuser' and to_user_id='$customer_id'";
	$fetch_count_invoiceItem=mysqli_query($db_conn,$select_count_invoiceItem);
	$result_count_invoiceItem=mysqli_fetch_array($fetch_count_invoiceItem);
	if($result_count_invoiceItem['numInvoiceItem']==0)
	{
		
		//1. insert invoice Items
		$insert_InvoiceItems="insert into user_invoice_items (inv_id,pr_id,amount,qty,total,to_user_type,to_user_id,from_user_type,from_user_id,gst_percentage,gstamount_singlepr,gstamount_total,subtotal,discount_percentage,discount_amount,gst_type,hsn,date,rwpoints,buyer_gsttype)
		values ('$inv_id','$pr_id','$amount','$qty','$total','$invuser','$customer_id',
		'$Login_user_TYPEvl','$Login_user_IDvl','$gst_percentage','$gstamount_singlepr',
		'$gstamount_total','$subtotal','$discount_percentage','$discount_amount',
		'$gst_type','$hsn','$date','$rwpoints','$buyer_gsttype')";
		mysqli_query($db_conn,$insert_InvoiceItems);
	
		
		echo "<script>window.location='shop-user-invoice-add?InvoiceID=".base64_encode($inv_id)."&&AddedSuccess&&invuser=".$invuser."&&FemiAdded&&action=".$_SESSION['ACTIONEDIT']."&&gid=".$godownid."';</script>";
		
	}else{
		
		echo "<script>window.location='shop-user-invoice-add?InvoiceID=".base64_encode($inv_id)."&&ItemAlreadyExists&&invuser=".$invuser."&&AlertMessage&&action=".$_SESSION['ACTIONEDIT']."&&gid=".$godownid."';</script>";
	}
		
}


}

	}
	
?>