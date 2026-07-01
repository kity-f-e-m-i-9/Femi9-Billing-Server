<?php include("checksession.php");
include("config.php");
error_reporting(0);
include("RemoveSpecialChar.php");

//insert details
if(isset($_REQUEST['add-record']))
{
	$tempid=$_REQUEST['tempid'];
	
	$usertype=$_REQUEST['usertype'];
	$userid=$_REQUEST['userid'];
	
	$date=date("Y-m-d",strtotime($_REQUEST['date']));
	$remarks = RemoveSpecialChar($_REQUEST['remarks']);
	$category=$_REQUEST['category'];
	
$product_id = implode("#",$_REQUEST['product_id']);
$qty = implode("#",$_REQUEST['qty']);
	
$product_id_ex = explode ("#",$product_id); 
$qty_ex = explode ("#",$qty); 

$number = count($product_id_ex); 
for ($i=0; $i<=$number; $i++) 
{ 
     $product_id_value = $product_id_ex[$i]; 
     $qty_value = $qty_ex[$i]; 
	 $qty_value = RemoveSpecialChar($qty_value);
	 
	 if($product_id_value!=NULL)
	 {
		 
		 //count available stock
	$select_count_AVSTOCK="select * from stock where product_id='$product_id_value' and user_type='$usertype' and user_id='$userid'";
	$FETCH_count_AVSTOCK=mysqli_query($db_conn,$select_count_AVSTOCK);
	$RESULT_count_AVSTOCK=mysqli_fetch_array($FETCH_count_AVSTOCK);
	$AVMstock=$RESULT_count_AVSTOCK['closing_qty'];
	
	if($AVMstock<$qty_value)
	{
		$_SESSION['errorMessage']="There is no stock for the quantity you entered!";
		echo "<script>window.location='demofree_new?InvalidStock&&AlertStockError';</script>";
		
	}else{
		
	 
	 $select_count_product="select count(*) as numCountRcds from demofreedamage where tempid='$tempid' and product_id='$product_id_value'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numCountRcds']==0)
	{
		//insert input stock
		$insert_products="insert into demofreedamage 
		(tempid,date,remarks,product_id,qty,category,usertype,userid) values 
		('$tempid','$date','$remarks','$product_id_value','$qty_value','$category','$usertype','$userid')";
		mysqli_query($db_conn,$insert_products);
		
		//STOCK DECREMENT - FROM COMPANY
		$select_stockDetails="select * from stock where product_id='$product_id_value' and user_type='$usertype' and user_id='$userid'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Input_stock=$result_stockDetails['sent_qty']+$qty_value;
		$update_Closing_stock=$result_stockDetails['closing_qty']-$qty_value;
		
		$update_stockDetails="update stock set sent_qty='$update_Input_stock',closing_qty='$update_Closing_stock' where product_id='$product_id_value' and user_type='$usertype' and user_id='$userid'";
		mysqli_query($db_conn,$update_stockDetails);
		
	}
	 }
	 
}
} //for loop
	
		$_SESSION['sucMessage']="Demo/Free/Damage Details Added Successfully!";
		echo "<script>window.location='demofree_manage?addesuccess';</script>";

}





//------------------------------------------------------------------------
//------------------------------------------------------------------------
//UPDATE
if(isset($_REQUEST['update-record']))
{

$tempid=$_REQUEST['update_tempid'];

	$date=date("Y-m-d",strtotime($_REQUEST['date']));
	$remarks = RemoveSpecialChar($_REQUEST['remarks']);
	$category=$_REQUEST['category'];
	
	$UPDATERECORDS="update demofreedamage set category='$category',date='$date',remarks='$remarks' where tempid='$tempid'";
	mysqli_query($db_conn,$UPDATERECORDS);
	
	$_SESSION['sucMessage']="Demo/Free/Damage Details Updated Successfully!";
	echo "<script>window.location='demofree_manage?updatedsuccess';</script>";
}
?>