<?php include("checksession.php");
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
$Select_Invoice_Details="select rwpoints_enable,gst_type from user_invoice where inv_id='$invid'";
$Fetch_Invoice_Details=mysqli_query($db_conn,$Select_Invoice_Details);
$Result_Invoice_Details=mysqli_fetch_array($Fetch_Invoice_Details);
if($Result_Invoice_Details['rwpoints_enable']!=NULL)
{
$rwpoints_enable=$Result_Invoice_Details['rwpoints_enable'];
}else{ $rwpoints_enable="0";}

$gst_type=$Result_Invoice_Details['gst_type'];
	
	$prid=$_REQUEST['prid'];
	//get product gst
$selectproducts="select * from products where id='$prid'";
$fetchproducts=mysqli_query($db_conn,$selectproducts);
$resultproducts=mysqli_fetch_array($fetchproducts);
$gst_percentage=$resultproducts['gst'];
$hsn=$resultproducts['hsn'];

	$returnqty=$_REQUEST['returnqty'];
	$damaged_qty=$_REQUEST['damaged_qty'];
	$rwpoints=$resultproducts['rwpoints']*$returnqty;
	
	if($from_usertype=="customer")
	{
	$select_invoicedetails="select * from invoice_items where inv_id='$invid' and pr_id='$prid'";
	}else{
	$select_invoicedetails="select * from user_invoice_items where inv_id='$invid' and pr_id='$prid'";
	}
	$fetch_invoicedetails=mysqli_query($db_conn,$select_invoicedetails);
	$result_invoicedtails=mysqli_fetch_array($fetch_invoicedetails);
	//
	$invoiceqty=$result_invoicedtails['qty'];
	$pr_mrp=$result_invoicedtails['amount'];
	
	$subtotal=$pr_mrp*$returnqty;
	$gstamount_total=$subtotal*$gst_percentage/100;
	$total=$subtotal+$gstamount_total;
	
	if($returnqty<=$invoiceqty)
	{
		$return_date=date("Y-m-d");
		
		$seletcountreturn="select count(*) as numreturncheck from user_return_stock where returnid='$returnid'";
		$fetchcountreturn=mysqli_query($db_conn,$seletcountreturn);
		$resultcountreturn=mysqli_fetch_array($fetchcountreturn);
		if($resultcountreturn['numreturncheck']==0)
		{
			
			$insertreturn="insert into user_return_stock (returnid,invnumber,date,subtotal,discount,total,from_usertype,from_userid,
			to_usertype,to_userid,status,rwpoints_enable,buyer_gsttype,gst_type) values 
			('$returnid','$invnumber','$return_date','0','0','0',
			'$from_usertype','$from_userid','$to_usertype','$to_userid','pending','$rwpoints_enable',
			'$buyer_gsttype','$gst_type')";
			mysqli_query($db_conn,$insertreturn);
		}
		
		
		
		$seletcountreturn2="select count(*) as numreturncheck2 from user_return_stock_items where invnumber='$invnumber' and prid='$prid'";
		$fetchcountreturn2=mysqli_query($db_conn,$seletcountreturn2);
		$resultcountreturn2=mysqli_fetch_array($fetchcountreturn2);
		if($resultcountreturn2['numreturncheck2']==0)
		{
			
			$insertreturn="insert into user_return_stock_items (returnid,invnumber,prid,amount,qty,subtotal,gst_percentage,gstamount_total,total,
			from_usertype,from_userid,to_usertype,to_userid,date,status,hsn,damaged_qty,
			rwpoints,buyer_gsttype,gst_type) values 
			('$returnid','$invnumber','$prid','$pr_mrp','$returnqty','$subtotal','$gst_percentage',
			'$gstamount_total','$total','$from_usertype','$from_userid',
			'$to_usertype','$to_userid','$return_date','pending','$hsn','$damaged_qty','$rwpoints',
			'$buyer_gsttype','$gst_type')";
			mysqli_query($db_conn,$insertreturn);
			
			
		//--------------------------------------------------
		//STOCK INCREMENT TO RECEIVED USERS (ex:- company)
		$select_stockDetails="select * from stock where product_id='$prid' and user_type='$to_usertype' and user_id='$to_userid'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_returnqty=$result_stockDetails['sales_qty']-$returnqty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$returnqty;
		
		$update_stockDetails="update stock set sales_qty='$update_returnqty',closing_qty='$update_Closing_stock' where product_id='$prid' and user_type='$to_usertype' and user_id='$to_userid'";
		mysqli_query($db_conn,$update_stockDetails);
		
		
		if($from_usertype=="super_stockiest" || $from_usertype=="stockiest" || $from_usertype=="distributor" || $from_usertype=="super_distributor")
		{
		
		//STOCK DECREMENT TO SENT USERS (ex:- super-stockist, stockist, distributor)
		$select_stockDetails12="select * from stock where product_id='$prid' and user_type='$from_usertype' and user_id='$from_userid'";
		$fetch_stockDetails12=mysqli_query($db_conn,$select_stockDetails12);
		$result_stockDetails12=mysqli_fetch_array($fetch_stockDetails12);
		
		$update_returnqty12=$result_stockDetails12['input_qty']-$returnqty;
		$update_Closing_stock12=$result_stockDetails12['closing_qty']-$returnqty;
		
		$update_stockDetails12="update stock set input_qty='$update_returnqty12',closing_qty='$update_Closing_stock12' where product_id='$prid' and user_type='$from_usertype' and user_id='$from_userid'";
		mysqli_query($db_conn,$update_stockDetails12);
		
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