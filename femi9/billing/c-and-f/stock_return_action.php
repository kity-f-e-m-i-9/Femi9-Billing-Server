<?php include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if(isset($_REQUEST['add-return']))
{
	
	$from_usertype=$_REQUEST['from_usertype'];
	$from_userid=$_REQUEST['from_userid'];
	
	$to_usertype=$_REQUEST['to_usertype'];
	$to_userid=$_REQUEST['to_userid'];
	
	$returnid=$_REQUEST['returnid'];
	$invnumber=$_REQUEST['invnumber'];
	$invid=$_REQUEST['invid'];
	
	$prid=$_REQUEST['prid'];
	//get product gst
$selectproducts="select * from products where id='$prid'";
$fetchproducts=mysqli_query($db_conn,$selectproducts);
$resultproducts=mysqli_fetch_array($fetchproducts);
$gst_percentage=$resultproducts['gst'];
$hsn=$resultproducts['hsn'];

	$returnqty=$_REQUEST['returnqty'];
	
	$select_invoicedetails="select * from user_invoice_items where inv_id='$invid' and pr_id='$prid'";
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
			to_usertype,to_userid,status) values ('$returnid','$invnumber','$return_date','0','0','0',
			'$from_usertype','$from_userid','$to_usertype','$to_userid','pending')";
			mysqli_query($db_conn,$insertreturn);
		}
		
		
		
		$seletcountreturn2="select count(*) as numreturncheck2 from user_return_stock_items where returnid='$returnid' and prid='$prid'";
		$fetchcountreturn2=mysqli_query($db_conn,$seletcountreturn2);
		$resultcountreturn2=mysqli_fetch_array($fetchcountreturn2);
		if($resultcountreturn2['numreturncheck2']==0)
		{
			
			$insertreturn="insert into user_return_stock_items (returnid,invnumber,prid,amount,qty,subtotal,gst_percentage,gstamount_total,total,
			from_usertype,from_userid,to_usertype,to_userid,date,status,hsn,damaged_qty) values 
			('$returnid','$invnumber','$prid','$pr_mrp','$returnqty','$subtotal','$gst_percentage',
			'$gstamount_total','$total','$from_usertype','$from_userid',
			'$to_usertype','$to_userid','$return_date','pending','$hsn','0')";
			mysqli_query($db_conn,$insertreturn);
			
			
		$select_stockDetails="select * from stock where product_id='$prid' and user_type='$from_usertype' and user_id='$from_userid'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_returnqty=$result_stockDetails['returnqty']+$returnqty;
		$update_Closing_stock=$result_stockDetails['closing_qty']-$returnqty;
		
		$update_stockDetails="update stock set returnqty='$update_returnqty',closing_qty='$update_Closing_stock' where product_id='$prid' and user_type='$from_usertype' and user_id='$from_userid'";
		mysqli_query($db_conn,$update_stockDetails);
		
		echo "<script>window.location='stock_return_add2.php?returnid=".base64_encode($returnid)."&&invnumber=".base64_encode($invnumber)."&&addedsuccess';</script>";
			
			
		}else{
			
			echo "<script>window.location='stock_return_add2.php?returnid=".base64_encode($returnid)."&&invnumber=".base64_encode($invnumber)."&&productalreadyexists';</script>";
			
		}
		
		
	}
	else
	{
		
		$rtnid_encode=base64_encode($returnid);
		$invnumber_encode=base64_encode($invnumber);
		
		echo "<script>window.location='stock_return_add2.php?returnid=$rtnid_encode&&invnumber=$invnumber_encode&&invalidqty';</script>";
	}



//if not submit
}
else
{
echo "<script>window.location='stock-return-add.php';</script>";	
}	

?>