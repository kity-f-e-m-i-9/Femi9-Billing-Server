<?php require_once __DIR__ . '/../shared/db-helpers.php'; require_once __DIR__ . '/../shared/safe-upload.php'; include("checksession.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$update_id=$_REQUEST['update_id'];
	$old_icon=safe_old_file_param($_REQUEST['old_icon']);
	
	$name=str_replace("'","&#39;",$_REQUEST['name']);
	
	$mobile_number=str_replace("'","&#39;",$_REQUEST['mobile_number']);
	$email=str_replace("'","&#39;",$_REQUEST['email']);
	$password=str_replace("'","&#39;",$_REQUEST['password']);
	$address=str_replace("'","&#39;",$_POST["address"]);
	
	//upload user icon
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
	   $rand_isd=rand(1,9899989);
	   $filename=$rand_isd.$small_jpg;
                 $uploaddir='outlet_icon/';
                 $uploadfile=$uploaddir.$filename;
	   safe_move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="outlet_icon/";
	   $insfilename=$insfoldername.$filename;
		   unlink("".$old_icon."");
	}
	else
	{
		$insfilename=$old_icon;
	}
	
	
	//update process
	$update_ss="update outlet set user_icon=?,name=?,email=?,mobile_number=?,password=?,username=?,address=? where id=?";
	db_q($db_conn,$update_ss,[$insfilename,$name,$email,$mobile_number,$password,$mobile_number,$address,$update_id]);
	
	echo "<script>window.location='Outlet-manage?updatedSuccess';</script>";
	
}else{
	
	echo "<script>window.location='Outlet-add';</script>";
}
	
	?>