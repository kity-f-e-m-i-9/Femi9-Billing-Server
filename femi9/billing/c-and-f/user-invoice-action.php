<?php include("checksession.php");
include("config.php");
error_reporting(0);

if(isset($_REQUEST['addInvoice']))
{
	$randum_number=$_REQUEST['randum_number'];
	$inv_id=$_REQUEST['inv_id'];
	$invuser=$_REQUEST['invuser'];
	
	
	//invoice accept=0
	if($_REQUEST['invoice_number_accept']==0)
	{
	$_SESSION['errorMessage']="Invoice Number already exists!";
	echo "<script>window.location='user-invoice-add.php?invoicealready&&invuser=$invuser';</script>";
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
$selectproducts="select * from products where id='$pr_id'";
$fetchproducts=mysqli_query($db_conn,$selectproducts);
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

//get company state code
$admin_statecode=$result_LoGuserDtails['state_id'];

	//get customer state code
	if($invuser=="super_stockiest"){$tablename="super_stockiest";}
	else if($invuser=="stockiest"){	$tablename="stockiest";}
	else if($invuser=="super_distributor"){$tablename="super_distributor";}
	else if($invuser=="distributor"){$tablename="distributor";}
	else if($invuser=="outlet"){$tablename="outlet";}
	else{
		//$tablename="shop";
	}
	
$selecutomser_dtails="select * from ".$tablename." where temp_id='$customer_id'";
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
		/*
		$select_MaxID="select max(id_only) from user_invoice where from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
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
	
		$inv_number="F9".$result_LoGuserDtails['id']."".$randum_number."".$INVNUMUSER."".$format_num."";
		*/
		
		//2. insert invoice
		$insert_Invoice="insert into user_invoice (inv_id,id_only,inv_number,date,inv_year,sub_total,discount,total,to_user_type,
		to_user_id,from_user_type,from_user_id,gst_type,credit,roundoff,courier_charges,rwpoints_enable,
		buyer_gsttype,username,usertype)
		values 
		('$inv_id','$id_only','$inv_number','$date','$inv_year','0','0','0',
		'$invuser','$customer_id','$Login_user_TYPEvl','$Login_user_IDvl','$gst_type','0','0','0','1',
		'$buyer_gsttype','Nil','Nil')";
		mysqli_query($db_conn,$insert_Invoice);
		
	}
	
	
	//count available stock
	$select_count_AVSTOCK="select * from stock where product_id='$pr_id' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
	$FETCH_count_AVSTOCK=mysqli_query($db_conn,$select_count_AVSTOCK);
	$RESULT_count_AVSTOCK=mysqli_fetch_array($FETCH_count_AVSTOCK);
	$AVMstock=$RESULT_count_AVSTOCK['closing_qty'];
	
	if($AVMstock<$qty)
	{
		echo "<script>window.location='user-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&InvalidStock&&invuser=".$invuser."&&AlertStockError&&action=".$_SESSION['ACTIONEDIT']."';</script>";
		
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
		$insert_InvoiceItems="insert into user_invoice_items (inv_id,pr_id,amount,qty,total,to_user_type,to_user_id,from_user_type,from_user_id,
		gst_percentage,gstamount_singlepr,gstamount_total,subtotal,
		discount_percentage,discount_amount,gst_type,hsn,date,rwpoints,buyer_gsttype)
		values ('$inv_id','$pr_id','$amount','$qty','$total','$invuser','$customer_id','$Login_user_TYPEvl','$Login_user_IDvl','$gst_percentage','$gstamount_singlepr','$gstamount_total','$subtotal',
		'$discount_percentage','$discount_amount','$gst_type','$hsn','$date','$rwpoints','$buyer_gsttype')";
		mysqli_query($db_conn,$insert_InvoiceItems);
		
		//------------------------------
		//2. stock decrement to company
		//------------------------------
		$select_stockDetails="select * from stock where product_id='$pr_id' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Sales_stock=$result_stockDetails['sales_qty']+$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']-$qty;
		
		$update_stockDetails="update stock set sales_qty='$update_Sales_stock',closing_qty='$update_Closing_stock' where product_id='$pr_id' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
		mysqli_query($db_conn,$update_stockDetails);
		
		//------------------------------------------------------------------------
		//insert product stock first
		//------------------------------------------------------------------------
		$select_stockDetailscheck="select count(*) as numprcheck from stock where product_id='$pr_id' and user_type='$invuser' and user_id='$customer_id'";
		$fetch_stockDetailscheck=mysqli_query($db_conn,$select_stockDetailscheck);
		$result_stockDetailscheck=mysqli_fetch_array($fetch_stockDetailscheck);
		if($result_stockDetailscheck['numprcheck']==0)
		{
			$insertprstock="insert into stock (product_id,opening_qty,opening_date,input_qty,sales_qty,sent_qty,returnqty,closing_qty,user_type,user_id) values ('$pr_id','0','$date','0','0','0','0','0','$invuser','$customer_id')";
			mysqli_query($db_conn,$insertprstock);
		}
		//-------------------------------------------------------------------------
		
		//-------------------------------------------------------------------
		//3. stock increment to user (super-stockist, stockist, distributor)
		//--------------------------------------------------------------------
		$select_stockDetails12="select * from stock where product_id='$pr_id' and user_type='$invuser' and user_id='$customer_id'";
		$fetch_stockDetails12=mysqli_query($db_conn,$select_stockDetails12);
		$result_stockDetails12=mysqli_fetch_array($fetch_stockDetails12);
		
		$update_Sales_stock12=$result_stockDetails12['input_qty']+$qty;
		$update_Closing_stock12=$result_stockDetails12['closing_qty']+$qty;
		
		$update_stockDetails="update stock set input_qty='$update_Sales_stock12',closing_qty='$update_Closing_stock12' where product_id='$pr_id' and user_type='$invuser' and user_id='$customer_id'";
		mysqli_query($db_conn,$update_stockDetails);
		
		
		echo "<script>window.location='user-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&AddedSuccess&&invuser=".$invuser."&&FemiAdded&&action=".$_SESSION['ACTIONEDIT']."';</script>";
		
	}else{
		
		echo "<script>window.location='user-invoice-add.php?InvoiceID=".base64_encode($inv_id)."&&ItemAlreadyExists&&invuser=".$invuser."&&AlertMessage&&action=".$_SESSION['ACTIONEDIT']."';</script>";
	}
		
}
	}
}
	
?>