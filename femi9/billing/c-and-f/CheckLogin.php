<?php session_start(); error_reporting(0); include("include/db-connect.php");

$randum_number=$_SESSION['randum_number'];

if($_REQUEST['signInCaptcha']==$randum_number){ 

     	$signInEmail=$_REQUEST['signInEmail'];
		$signInPassword=$_REQUEST['signInPassword'];
		
    $sql="select count(*) as numlog from c_and_f where binary username='$signInEmail' and binary password='$signInPassword' and account_status='active'";
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