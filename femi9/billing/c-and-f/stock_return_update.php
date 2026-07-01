<?php include("checksession.php");
include("config.php");
 error_reporting(0);
 
 $returnid=$_REQUEST['returnid'];
$returnid_decode=base64_decode($returnid);
 
$urlname=$_REQUEST['urlname'];
$updatestatus=$_REQUEST['updatestatus'];
 
//------------------UPDATE DAMAGED QTY------------------------
 if($updatestatus=="accept")
 {
$rtnid = implode(",",$_REQUEST['rtnid']);
$damaged_qty = implode(",",$_REQUEST['damaged_qty']);
	
$rtnidex = explode (",",$rtnid); 
$damaged_qty_ex = explode (",",$damaged_qty); 

$number = count($rtnidex); 
for ($i=0; $i<=$number; $i++) 
{ 
     $rtnid_value = $rtnidex[$i]; 
     $damaged_qty_value = $damaged_qty_ex[$i]; 
	 
	 if($rtnid_value!=NULL)
	 {
	 $updateDmgd="update user_return_stock_items set damaged_qty='$damaged_qty_value' where id='$rtnid_value'";
	 mysqli_query($db_conn,$updateDmgd);
	 }
}  
 }
//------------------UPDATE DAMAGED QTY----END ****--------------------

 $select_details123="select * from user_return_stock where returnid='$returnid_decode'";
				$fetch_details123=mysqli_query($db_conn,$select_details123);
				$result_details123=mysqli_fetch_array($fetch_details123);
				$total_amount=$result_details123['total'];
				
				
				$fromusertype=$result_details123['from_usertype'];
				$fromuserid=$result_details123['from_userid'];
				
				//-----------------reject action--------------------------
				//--------------------------------------------------------
				if($updatestatus=="reject")
				{
					
$selectitems="select * from user_return_stock_items where returnid='$returnid_decode'";
$fetchitems=mysqli_query($db_conn,$selectitems);
while($resultitems=mysqli_fetch_array($fetchitems))
{
	
	$prid=$resultitems['prid'];
	$qty=$resultitems['qty'];
	$status=$resultitems['status'];
	
	if($status=="pending")
	{
	
	$select_stockDetails="select * from stock where product_id='$prid' and user_type='$fromusertype' and user_id='$fromuserid'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_returnqty=$result_stockDetails['returnqty']-$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty;
		
		$update_stockDetails="update stock set returnqty='$update_returnqty',closing_qty='$update_Closing_stock' where product_id='$prid' and user_type='$fromusertype' and user_id='$fromuserid'";
		mysqli_query($db_conn,$update_stockDetails);
		
	}
	
	$updatereject="update user_return_stock_items set status='reject' where returnid='$returnid_decode' and prid='$prid'";
	mysqli_query($db_conn,$updatereject);
	
}

//decrement credit amount
if($result_details123['status']=="pending")
{
	   $selectcountcredit12="select * from return_credit where usertype='$fromusertype' and userid='$fromuserid'";
		$fetchcountcredit12=mysqli_query($db_conn,$selectcountcredit12);
		$resultcountcredit12=mysqli_fetch_array($fetchcountcredit12);
		$creditamount=$resultcountcredit12['credit_amount']-$total_amount;
		
		$updatecredit="update return_credit set credit_amount='$creditamount' where usertype='$fromusertype' and userid='$fromuserid'";
		mysqli_query($db_conn,$updatecredit);
	
}

$updatereject12="update user_return_stock set status='reject' where returnid='$returnid_decode'";
mysqli_query($db_conn,$updatereject12);

				}else{
				
				//-----------------------accept action--------------------
				//--------------------------------------------------------
				
				$to_usertype=$result_details123['to_usertype'];
				$to_userid=$result_details123['to_userid'];
				
$selectitems="select * from user_return_stock_items where returnid='$returnid_decode'";
$fetchitems=mysqli_query($db_conn,$selectitems);
while($resultitems=mysqli_fetch_array($fetchitems))
{
	
	$prid=$resultitems['prid'];
	$qty=$resultitems['qty'];
	$status=$resultitems['status'];
	
	if($status=="pending")
	{
	
	$select_stockDetails="select * from stock where product_id='$prid' and user_type='$to_usertype' and user_id='$to_userid'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_returnqty=$result_stockDetails['input_qty']+$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty;
		
		$update_stockDetails="update stock set input_qty='$update_returnqty',closing_qty='$update_Closing_stock' where product_id='$prid' and user_type='$to_usertype' and user_id='$to_userid'";
		mysqli_query($db_conn,$update_stockDetails);
		
	}
	
	$updatereject="update user_return_stock_items set status='accept' where returnid='$returnid_decode' and prid='$prid'";
	mysqli_query($db_conn,$updatereject);
	
}

$updatereject12="update user_return_stock set status='accept' where returnid='$returnid_decode'";
mysqli_query($db_conn,$updatereject12);
				
				}
				
				
				echo "<script>window.location='".$urlname.".php?updatedsuccess';</script>";
 
 ?>