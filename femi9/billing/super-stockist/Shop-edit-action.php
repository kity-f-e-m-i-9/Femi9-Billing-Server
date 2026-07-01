<?php include("checksession.php");
include("config.php");
error_reporting(0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$update_id=$_REQUEST['update_id'];
	$old_icon=$_REQUEST['old_icon'];
	$shop_cat=$_POST["shop_cat"];
	
	$name=str_replace("'","&#39;",$_REQUEST['name']);
	
	$mobile_number=str_replace("'","&#39;",$_REQUEST['mobile_number']);
	$landline=str_replace("'","&#39;",$_POST["landline"]);
	$email=str_replace("'","&#39;",$_REQUEST['email']);
	$address=str_replace("'","&#39;",$_POST["address"]);
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$country_code=$_POST["country_code"];
	
	//upload user icon
	/*$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
	   $rand_isd=rand(1,9899989);
	   $filename=$rand_isd.$small_jpg;
                 $uploaddir='../distributor/user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="user_icon/";
	   $insfilename=$insfoldername.$filename;
	   if($old_icon!="Nil")
	   {
	   unlink("../distributor/".$old_icon."");
	   }
	}else{$insfilename=$old_icon;}*/
	
	
	//upload user icon
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
		
$filetype=$_FILES['user_icon']['type'];
if($filetype != 'image/jpeg' && $filetype != 'image/jpg' && $filetype != 'image/png')
{
$insfilename=$old_icon;
 echo "<script>window.location='Shop-edit.php?prid=".base64_encode($update_id)."&&imageinvlaid';</script>";
}else{


$file_extension = pathinfo($_FILES['user_icon']['name'], PATHINFO_EXTENSION);
		$rand_isd=bin2hex(random_bytes(64));
	    $filename=$rand_isd . '.' . $file_extension;
		
                 $uploaddir='../distributor/user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="user_icon/";
	   $insfilename=$insfoldername.$filename;
	   if($old_icon!="Nil")
	   {
	   unlink("../distributor/".$old_icon."");
	   }
}
	}
	else
	{
		$insfilename=$old_icon;
	}
	
	
	//update process
	$update_ss="update shop set user_icon='$insfilename',name='$name',
	email='$email',mobile_number='$mobile_number',
	address='$address',shop_cat='$shop_cat',
	gstin='$gstin',country_code='$country_code',landline='$landline' where id='$update_id'";
	mysqli_query($db_conn,$update_ss);
	
	echo "<script>window.location='Shop-manage.php?updatedSuccess';</script>";
	exit;
	
}else{
	
	echo "<script>window.location='Shop-add.php';</script>";
	exit;
}
?>