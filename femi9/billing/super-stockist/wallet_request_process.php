<?php include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");
if(isset($_REQUEST['sent_money_request']))
{
	
	$req_id=$_REQUEST['req_id'];
	$req_status=$_REQUEST['req_status'];
	$user_type=$_REQUEST['user_type'];
	$user_id=$_REQUEST['user_id'];
	$date=date("Y-m-d");
	$time=date("H:i:s");
	
	$acname=str_replace("'","",$_REQUEST['acname']);
	$acnumber=str_replace("'","",$_REQUEST['acnumber']);
	$bankname=str_replace("'","",$_REQUEST['bankname']);
	$ifsc=str_replace("'","",$_REQUEST['ifsc']);
	$pannumber=str_replace("'","",$_REQUEST['pannumber']);
	
	
	//if alread pending request available
	$select_pending_req="select id from wallet_withdraw where user_type='$user_type' and user_id='$user_id' and req_status='pending'";
	$fetch_pending_req=mysqli_query($db_conn,$select_pending_req);
	$result_pending_req=mysqli_num_rows($fetch_pending_req);
	if($result_pending_req!=0)
	{
		$_SESSION['errorMessage']="You have already sent one request, Kindly waiting for company approval.";
		echo "<script>window.location='wallet-history.php?alreadyexists';</script>";
		exit;
	}
	
	
		$seletcountreturn="select * from wallet_withdraw where req_id='$req_id'";
		$fetchcountreturn=mysqli_query($db_conn,$seletcountreturn);
		$resultcountreturn=mysqli_num_rows($fetchcountreturn);
		if($resultcountreturn==0)
		{
			
			$updated_date="1970-01-01";
			$updated_time="12:12:12";
			$remarks="Nil";
			$request_amount=$_REQUEST['request_amount'];
			
			$insertreturn="insert into wallet_withdraw (amount,req_id,req_status,user_type,user_id,date,time,remarks,updated_date,updated_time,
			TDS_percentage,TDS_deduction,sent_amount,acname,acnumber,bankname,ifsc,pannumber) values 
			('$request_amount','$req_id','$req_status','$user_type','$user_id','$date','$time',
			'$remarks','$updated_date','$updated_time','0','0','0','$acname','$acnumber','$bankname','$ifsc','$pannumber')";
			mysqli_query($db_conn,$insertreturn);
			
			// Check and update PAN number in user table if empty
			if(!empty($pannumber))
                {
                	// Check if PAN is empty in user table
                	$check_pan="select pannumber from users_profile where user_tempid='$user_id' and usertype='$user_type'";
                	$fetch_pan=mysqli_query($db_conn,$check_pan);
                	$row_pan=mysqli_fetch_assoc($fetch_pan);
                	
                	if(empty($row_pan['pannumber']))
                	{
                		// Update PAN number in user table
                		$update_pan="update users_profile set pannumber='$pannumber' where user_tempid='$user_id' and usertype='$user_type'";
                		mysqli_query($db_conn,$update_pan);
                	}
                }
		}
		
		$_SESSION['successMessage']="Withdraw request sent success.";
		echo "<script>window.location='wallet-history.php?addedsuccess';</script>";
		exit;
//if not submit
}else {echo "<script>window.location='dashboard';</script>";exit;}	
?>