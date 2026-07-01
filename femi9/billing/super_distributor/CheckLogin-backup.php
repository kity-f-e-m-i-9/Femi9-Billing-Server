<?php session_start(); error_reporting(0); include("include/db-connect.php");

$randum_number=$_SESSION['randum_number'];

if($_REQUEST['signInCaptcha']==$randum_number){ 

     	$signInEmail=$_REQUEST['signInEmail'];
		$signInPassword=$_REQUEST['signInPassword'];
		
		//------------------------------------------------------------------------------------------------
	//------------------------------------------------------------------------------------------------	
	//check candiate valid date
	date_default_timezone_set("Asia/Kolkata");
		$CurrentDate=date("Y-m-d");
	
	/*$Select_candidate_Valid="select * from distributor where binary username='$signInEmail'";
	$Fetch_candidate_Valid=mysqli_query($db_conn,$Select_candidate_Valid);
	$Result_candidate_Valid=mysqli_fetch_array($Fetch_candidate_Valid);
	$ValidToDate=$Result_candidate_Valid['valid_to'];
	
	if(strtotime($ValidToDate)<strtotime($CurrentDate))
	{
		$updtDeact="update distributor set account_status='deactive' where binary username='$signInEmail'";
		mysqli_query($db_conn,$updtDeact);
		echo "<script>window.location='index.php?planexpired';</script>";
	}*/
	//------------------------------------------------------------------------------------------------
	//------------------------------------------------------------------------------------------------
			
    $sql="select count(*) as numlog from super_distributor where binary username='$signInEmail' and binary password='$signInPassword' and account_status='active'";
	$result=mysqli_query($db_conn,$sql);
	$fetch_result=mysqli_fetch_array($result);
	
	if($fetch_result['numlog']==1)
	{
		 $_SESSION['LOGIN_USER']=$signInEmail;
		 
	echo "<script>window.location='dashboard.php';</script>";
	}else{
		$_SESSION['errorMessage'] = 'Invalid Username or Password.';
		echo "<script>window.location='index.php?invaliduser';</script>";
	}
	
}else{
	$_SESSION['errorMessage'] = 'Please try again';
	echo "<script>window.location='index.php?invalidcaptcha';</script>";
}
	
?>