<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
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
		$updaterw="update user_invoice set rwpoints_enable='1' where inv_id=?";
		db_q($db_conn,$updaterw,[$invoiceid]);
		
		//UPDATE POINTS
		$selectmultiproduct="select * from user_invoice_items where inv_id=?";
		$fetchmulti=db_q($db_conn,$selectmultiproduct,[$invoiceid]);
		while($resultmulti=mysqli_fetch_array($fetchmulti))
		{
$pr_id=$resultmulti['pr_id'];
$qty=$resultmulti['qty'];

$selectproducts="select * from products where id=?";
$fetchproducts=db_q($db_conn,$selectproducts,[$pr_id]);
$resultproducts=mysqli_fetch_array($fetchproducts);
$rwpoints=$resultproducts['rwpoints']*$qty;
		
$updaterwMULTI="update user_invoice_items set rwpoints=? where inv_id=? and pr_id=?";
db_q($db_conn,$updaterwMULTI,[$rwpoints,$invoiceid,$pr_id]);		
		}
		
		$selectcount="select rwpoints_enable from user_return_stock where invnumber=? and deleted_at is null";
		$fetchcount=db_q($db_conn,$selectcount,[$invoiceid]);
		$resultrows=mysqli_num_rows($fetchcount);
		if($resultrows>0)
		{
		$updaterw_rtn="update user_return_stock set rwpoints_enable='1' where invnumber=? and deleted_at is null";
		db_q($db_conn,$updaterw_rtn,[$invoiceid]);


		//UPDATE POINTS
		$selectmultiproduct12="select * from user_return_stock_items where invnumber=? and deleted_at is null";
		$fetchmulti12=db_q($db_conn,$selectmultiproduct12,[$invoiceid]);
		while($resultmulti12=mysqli_fetch_array($fetchmulti12))
		{
$pr_id2=$resultmulti12['prid'];
$qty2=$resultmulti12['qty'];

$selectproducts12="select * from products where id=?";
$fetchproducts12=db_q($db_conn,$selectproducts12,[$pr_id2]);
$resultproducts12=mysqli_fetch_array($fetchproducts12);
$rwpoints12=$resultproducts12['rwpoints']*$qty2;
		
$updaterwMULTI="update user_return_stock_items set rwpoints=? where invnumber=? and prid=?";
db_q($db_conn,$updaterwMULTI,[$rwpoints12,$invoiceid,$pr_id2]);		
		}
		}
		
$_SESSION['successMessage']="Invoice Number ".base64_decode($_REQUEST['invnumber'])."  Reward Points Enabled Success";
echo "<script>window.location='user-manage-invoice?invuser=$invuser';</script>";
	 
	}
	else
	{
		//DISABLED SECTION
		$updaterw="update user_invoice set rwpoints_enable='0' where inv_id=?";
		db_q($db_conn,$updaterw,[$invoiceid]);
		
		$updaterw2="update user_invoice_items set rwpoints='0' where inv_id=?";
		db_q($db_conn,$updaterw2,[$invoiceid]);
		
		$selectcount="select rwpoints_enable from user_return_stock where invnumber=? and deleted_at is null";
		$fetchcount=db_q($db_conn,$selectcount,[$invoiceid]);
		$resultrows=mysqli_num_rows($fetchcount);
		if($resultrows>0)
		{
		$updaterw_rtn="update user_return_stock set rwpoints_enable='0' where invnumber=? and deleted_at is null";
		db_q($db_conn,$updaterw_rtn,[$invoiceid]);

		$updaterw_rtn2="update user_return_stock_items set rwpoints='0' where invnumber=? and deleted_at is null";
		db_q($db_conn,$updaterw_rtn2,[$invoiceid]);
		}
		
$_SESSION['successMessage']="Invoice Number ".base64_decode($_REQUEST['invnumber'])."  Reward Points Disabled Success";
echo "<script>window.location='user-manage-invoice?invuser=$invuser';</script>";
	
	}
	
	//--------------------------------------------------------
	}else{
		echo "<script>window.location='dashboard';</script>";
	}
?>