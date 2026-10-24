<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);

include("RemoveSpecialChar.php");

//insert details
if(isset($_REQUEST['addInvoice']))
{
	
	$tempid=$_REQUEST['tempid'];
	$from_usertype=$_REQUEST['from_usertype'];
	$from_userid=$_REQUEST['from_userid'];
	$to_usertype=$_REQUEST['to_usertype'];
	$to_userid=$_REQUEST['to_userid'];
	
	$date=date("Y-m-d",strtotime($_REQUEST['date']));
	
	$prid=$_REQUEST['prid'];
	$qty=$_REQUEST['qty'];
		 
	//count available stock
	$select_count_AVSTOCK="select * from stock where product_id=? and user_type=? and user_id=?";
	$FETCH_count_AVSTOCK=db_q($db_conn,$select_count_AVSTOCK,[$prid,$from_usertype,$from_userid]);
	$RESULT_count_AVSTOCK=mysqli_fetch_array($FETCH_count_AVSTOCK);
	$AVMstock=$RESULT_count_AVSTOCK['closing_qty'];
	
	if($AVMstock<$qty)
	{
		//$_SESSION['errorMessage']="There is no stock for the quantity you entered!";
		echo "<script>window.location='add_internal?InvalidStock&&AlertStockError';</script>";
		
	}else{
	 
	
		
	$select_count_INV="select count(*) as numINVOICE from internal_transfer_ss where tempid=? and prid=?";
	$fetch_count_INV=db_q($db_conn,$select_count_INV,[$tempid,$prid]);
	$result_count_INV=mysqli_fetch_array($fetch_count_INV);
	if($result_count_INV['numINVOICE']==0)
	{
			$INSERT_INVOICE="insert into internal_transfer_ss (tempid,prid,qty,date,from_usertype,from_userid,to_usertype,to_userid) values 
			(?,?,?,?,?,?,?,?)";
			db_q($db_conn,$INSERT_INVOICE,[$tempid,$prid,$qty,$date,$from_usertype,$from_userid,$to_usertype,$to_userid]);
			
			
		//STOCK DECREMENT - FROM SUPER STOCKIST
		$select_stockDetails="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails=db_q($db_conn,$select_stockDetails,[$prid,$from_usertype,$from_userid]);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Input_stock=$result_stockDetails['sent_qty']+$qty;
		$update_Closing_stock=$result_stockDetails['closing_qty']-$qty;
		
		$update_stockDetails="update stock set sent_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails,[$update_Input_stock,$update_Closing_stock,$prid,$from_usertype,$from_userid]);
		
		
		//STOCK INCREMENT - TO STOCKIST
		$select_stockDetails2="select * from stock where product_id=? and user_type=? and user_id=?";
		$fetch_stockDetails2=db_q($db_conn,$select_stockDetails2,[$prid,$to_usertype,$to_userid]);
		$result_stockDetails2=mysqli_fetch_array($fetch_stockDetails2);
		
		$update_Input_stock2=$result_stockDetails2['input_qty']+$qty;
		$update_Closing_stock2=$result_stockDetails2['closing_qty']+$qty;
		
		$update_stockDetails2="update stock set input_qty=?,closing_qty=? where product_id=? and user_type=? and user_id=?";
		db_q($db_conn,$update_stockDetails2,[$update_Input_stock2,$update_Closing_stock2,$prid,$to_usertype,$to_userid]);
		
		
		//-------------------------------------------------------------------------
		//-------------------------------------------------------------------------
			
		$_SESSION['sucMessage']="Internal Stock Transfer Details Added Successfully!";
		echo "<script>window.location='manage_internal';</script>";
		
	}
		
	}

}
	
?>