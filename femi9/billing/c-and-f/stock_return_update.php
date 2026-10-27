<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
 error_reporting(0);
 
 $returnid=$_REQUEST['returnid'];
$returnid_decode=base64_decode($returnid);
 
$urlname=preg_replace('/[^A-Za-z0-9_\-\/]/', '', (string)($_REQUEST['urlname']));
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
	 $updateDmgd="update user_return_stock_items set damaged_qty=? where id=?";
	 db_q($db_conn,$updateDmgd,[$damaged_qty_value,$rtnid_value]);
	 }
}  
 }
//------------------UPDATE DAMAGED QTY----END ****--------------------

 $select_details123="select * from user_return_stock where returnid=?";
				$fetch_details123=db_q($db_conn,$select_details123,[$returnid_decode]);
				$result_details123=mysqli_fetch_array($fetch_details123);
				$total_amount=$result_details123['total'];
				
				
				$fromusertype=$result_details123['from_usertype'];
				$fromuserid=$result_details123['from_userid'];
				
				//-----------------reject action--------------------------
				//--------------------------------------------------------
				if($updatestatus=="reject")
				{
					
$selectitems="select * from user_return_stock_items where returnid=?";
$fetchitems=db_q($db_conn,$selectitems,[$returnid_decode]);
while($resultitems=mysqli_fetch_array($fetchitems))
{
	
	$prid=$resultitems['prid'];
	$qty=$resultitems['qty'];
	$status=$resultitems['status'];
	
	if($status=="pending")
	{
	
	$select_stockDetails="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails=db_q($db_conn,$select_stockDetails,[$prid,$fromusertype,$fromuserid]);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_returnqty=$result_stockDetails['returnqty']-$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty;
		
		$update_stockDetails="update stock set returnqty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_returnqty,$update_Closing_stock,$prid,$fromusertype,$fromuserid]);
		
	}
	
	$updatereject="update user_return_stock_items set status='reject' where returnid=? and prid=?";
	db_q($db_conn,$updatereject,[$returnid_decode,$prid]);
	
}

//decrement credit amount
if($result_details123['status']=="pending")
{
	   $selectcountcredit12="select * from return_credit where usertype=? and userid=?";
		$fetchcountcredit12=db_q($db_conn,$selectcountcredit12,[$fromusertype,$fromuserid]);
		$resultcountcredit12=mysqli_fetch_array($fetchcountcredit12);
		$creditamount=$resultcountcredit12['credit_amount']-$total_amount;
		
		$updatecredit="update return_credit set credit_amount=? where usertype=? and userid=?";
		db_q($db_conn,$updatecredit,[$creditamount,$fromusertype,$fromuserid]);
	
}

$updatereject12="update user_return_stock set status='reject' where returnid=?";
db_q($db_conn,$updatereject12,[$returnid_decode]);

				}else{
				
				//-----------------------accept action--------------------
				//--------------------------------------------------------
				
				$to_usertype=$result_details123['to_usertype'];
				$to_userid=$result_details123['to_userid'];
				
$selectitems="select * from user_return_stock_items where returnid=?";
$fetchitems=db_q($db_conn,$selectitems,[$returnid_decode]);
while($resultitems=mysqli_fetch_array($fetchitems))
{
	
	$prid=$resultitems['prid'];
	$qty=$resultitems['qty'];
	$status=$resultitems['status'];
	
	if($status=="pending")
	{
	
	$select_stockDetails="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails=db_q($db_conn,$select_stockDetails,[$prid,$to_usertype,$to_userid]);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_returnqty=$result_stockDetails['input_qty']+$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']+$qty;
		
		$update_stockDetails="update stock set input_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_returnqty,$update_Closing_stock,$prid,$to_usertype,$to_userid]);
		
	}
	
	$updatereject="update user_return_stock_items set status='accept' where returnid=? and prid=?";
	db_q($db_conn,$updatereject,[$returnid_decode,$prid]);
	
}

$updatereject12="update user_return_stock set status='accept' where returnid=?";
db_q($db_conn,$updatereject12,[$returnid_decode]);
				
				}
				
				
				echo "<script>window.location='".preg_replace('/[^A-Za-z0-9_\-\/]/','',$urlname).".php?updatedsuccess';</script>";
 
 ?>