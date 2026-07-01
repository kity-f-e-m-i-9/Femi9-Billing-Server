<?php include("checksession.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$update_id=$_REQUEST['update_id'];
	$old_icon=$_REQUEST['old_icon'];
	
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
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="outlet_icon/";
	   $insfilename=$insfoldername.$filename;
		   unlink("".$old_icon."");
	}
	else
	{
		$insfilename=$old_icon;
	}
	
	
	//update process
	$update_ss="update outlet set user_icon='$insfilename',name='$name',email='$email',mobile_number='$mobile_number',password='$password',username='$mobile_number',address='$address' where id='$update_id'";
	mysqli_query($db_conn,$update_ss);
	
	echo "<script>window.location='Outlet-manage?updatedSuccess';</script>";
	
}else{
	
	echo "<script>window.location='Outlet-add';</script>";
}
	
	?>