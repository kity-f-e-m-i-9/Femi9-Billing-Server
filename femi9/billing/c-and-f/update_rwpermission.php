<?php include("checksession.php");
include("config.php");

	$invoiceid_encode=$_REQUEST['invoiceid'];
	$invoiceid=base64_decode($invoiceid_encode);
	
	$rwst=$_REQUEST['rwst'];
	$invuser=$_REQUEST['invuser'];
	
	if($rwst!=NULL)
	{
	
	if($rwst==0)
	{
		//ENABLE SECTION
		$updaterw="update user_invoice set rwpoints_enable='1' where inv_id='$invoiceid'";
		mysqli_query($db_conn,$updaterw);
		
		//UPDATE POINTS
		$selectmultiproduct="select * from user_invoice_items where inv_id='$invoiceid'";
		$fetchmulti=mysqli_query($db_conn,$selectmultiproduct);
		while($resultmulti=mysqli_fetch_array($fetchmulti))
		{
$pr_id=$resultmulti['pr_id'];
$qty=$resultmulti['qty'];

$selectproducts="select * from products where id='$pr_id'";
$fetchproducts=mysqli_query($db_conn,$selectproducts);
$resultproducts=mysqli_fetch_array($fetchproducts);
$rwpoints=$resultproducts['rwpoints']*$qty;
		
$updaterwMULTI="update user_invoice_items set rwpoints='$rwpoints' where inv_id='$invoiceid' and pr_id='$pr_id'";
mysqli_query($db_conn,$updaterwMULTI);		
		}
		
		$selectcount="select rwpoints_enable from user_return_stock where invnumber='$invoiceid'";
		$fetchcount=mysqli_query($db_conn,$selectcount);
		$resultrows=mysqli_num_rows($fetchcount);
		if($resultrows>0)
		{
		$updaterw_rtn="update user_return_stock set rwpoints_enable='1' where invnumber='$invoiceid'";
		mysqli_query($db_conn,$updaterw_rtn);
		
		
		//UPDATE POINTS
		$selectmultiproduct12="select * from user_return_stock_items where invnumber='$invoiceid'";
		$fetchmulti12=mysqli_query($db_conn,$selectmultiproduct12);
		while($resultmulti12=mysqli_fetch_array($fetchmulti12))
		{
$pr_id2=$resultmulti12['prid'];
$qty2=$resultmulti12['qty'];

$selectproducts12="select * from products where id='$pr_id2'";
$fetchproducts12=mysqli_query($db_conn,$selectproducts12);
$resultproducts12=mysqli_fetch_array($fetchproducts12);
$rwpoints12=$resultproducts12['rwpoints']*$qty2;
		
$updaterwMULTI="update user_return_stock_items set rwpoints='$rwpoints12' where invnumber='$invoiceid' and prid='$pr_id2'";
mysqli_query($db_conn,$updaterwMULTI);		
		}
		}
		
$_SESSION['successMessage']="Invoice Number ".base64_decode($_REQUEST['invnumber'])."  Reward Points Enabled Success";
echo "<script>window.location='user-manage-invoice?invuser=$invuser';</script>";
	 
	}
	else
	{
		//DISABLED SECTION
		$updaterw="update user_invoice set rwpoints_enable='0' where inv_id='$invoiceid'";
		mysqli_query($db_conn,$updaterw);
		
		$updaterw2="update user_invoice_items set rwpoints='0' where inv_id='$invoiceid'";
		mysqli_query($db_conn,$updaterw2);
		
		$selectcount="select rwpoints_enable from user_return_stock where invnumber='$invoiceid'";
		$fetchcount=mysqli_query($db_conn,$selectcount);
		$resultrows=mysqli_num_rows($fetchcount);
		if($resultrows>0)
		{
		$updaterw_rtn="update user_return_stock set rwpoints_enable='0' where invnumber='$invoiceid'";
		mysqli_query($db_conn,$updaterw_rtn);
		
		$updaterw_rtn2="update user_return_stock_items set rwpoints='0' where invnumber='$invoiceid'";
		mysqli_query($db_conn,$updaterw_rtn2);
		}
		
$_SESSION['successMessage']="Invoice Number ".base64_decode($_REQUEST['invnumber'])."  Reward Points Disabled Success";
echo "<script>window.location='user-manage-invoice?invuser=$invuser';</script>";
	
	}
	
	//--------------------------------------------------------
	}else{
		echo "<script>window.location='dashboard';</script>";
	}
?>