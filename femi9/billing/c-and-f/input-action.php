<?php include("checksession.php");
include("config.php");

//insert details
if(isset($_REQUEST['add-record']))
{
	$usertype=$Login_user_TYPEvl;
	$userid=$Login_user_IDvl;
	
	$tempid=$_REQUEST['tempid'];
	
	$product_id=$_REQUEST['product_id'];
	$input_qty=str_replace("'","",$_REQUEST['input_qty']);
	$remarks=str_replace("'","&#39;",$_REQUEST['remarks']);
	$input_date=date("Y-m-d",strtotime($_REQUEST['input_date']));
	
	$select_count_product="select count(*) as numCountRcds from input_stock_users where tempid='$tempid'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numCountRcds']==0)
	{
		//insert input stock
		$insert_products="insert into input_stock_users (tempid,usertype,userid,product_id,input_qty,input_date,remarks)
		values ('$tempid','$usertype','$userid','$product_id','$input_qty','$input_date','$remarks')";
		mysqli_query($db_conn,$insert_products);
		
		
		//update stock
		$select_stockDetails="select * from stock where product_id='$product_id' and user_type='$usertype' and user_id='$userid'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Input_stock=$result_stockDetails['input_qty']+$input_qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$input_qty;
		
		$update_stockDetails="update stock set input_qty='$update_Input_stock',closing_qty='$update_Closing_stock' where product_id='$product_id' and user_type='$usertype' and user_id='$userid'";
		mysqli_query($db_conn,$update_stockDetails);
		
		
		echo "<script>window.location='manage-input?addesuccess';</script>";
		
	}
	
	else{ echo "<script>window.location='add-input?alreadyexists';</script>"; }
	
	
	
}else{ echo "<script>window.location='add-input';</script>";}
?>