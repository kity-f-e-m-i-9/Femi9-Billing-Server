<?php session_start(); 
include("include/db-connect.php");
include("RemoveSpecialChar.php");
error_reporting(0);

if(isset($_REQUEST['login']))
{

$randum_number=$_SESSION['randum_number'];

if($_REQUEST['signInCaptcha']==$randum_number){ 

     	$signInEmail=$_REQUEST['signInEmail'];
		$signInEmail = RemoveSpecialChar($signInEmail);
		
		$signInPassword=$_REQUEST['signInPassword'];
		$signInPassword = RemoveSpecialChar($signInPassword);
			
    $sql="select * from admin_log where binary username='$signInEmail' and binary password='$signInPassword'";
	$result=mysqli_query($db_conn,$sql);
	$fetch_result=mysqli_num_rows($result);
	
	if($fetch_result==1)
	{
		 $_SESSION['LOGIN_USER']=$signInEmail; 
		 
	echo "<script>window.location='dashboard';</script>";
	}else{
		$_SESSION['errorMessage'] = 'Invalid Username or Password.';
		echo "<script>window.location='index?invaliduser';</script>";
	}
	
}else{
	$_SESSION['errorMessage'] = 'Please try again';
	echo "<script>window.location='index?invalidcaptcha';</script>";
}

//not submit - redirected page $_REQUEST['login']
}else{echo "<script>window.location='index';</script>";}
?>