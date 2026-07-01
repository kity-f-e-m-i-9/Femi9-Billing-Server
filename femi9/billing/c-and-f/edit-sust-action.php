<?php include("checksession.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$update_id=$_REQUEST['update_id'];
	$old_icon=$_REQUEST['old_icon'];
	$country_code=$_POST["country_code"];
	
	$name=str_replace("'","&#39;",$_REQUEST['name']);
	$name = RemoveSpecialChar($name);
	
	$mobile_number=str_replace("'","&#39;",$_REQUEST['mobile_number']);
	$mobile_number = RemoveSpecialChar($mobile_number);
	
	$email=str_replace("'","&#39;",$_REQUEST['email']);
	$email = RemoveSpecialChar($email);
	
	$address=str_replace("'","&#39;",$_POST["address"]);
	$address = RemoveSpecialChar($address);
	
	$gstin=str_replace("'","&#39;",$_REQUEST['gstin']);
	$gstin = RemoveSpecialChar($gstin);
	
	//upload user icon
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
		
$filetype=$_FILES['user_icon']['type'];
if($filetype != 'image/jpeg' && $filetype != 'image/jpg' && $filetype != 'image/png')
{
$insfilename=$old_icon;
 echo "<script>window.location='edit-sust?prid=".base64_encode($update_id)."&&imageinvlaid';</script>";
}else{


	   $rand_isd=rand(1,9899989);
	   $filename=$rand_isd.$small_jpg;
                 $uploaddir='user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfilename=$uploadfile;
	   if($old_icon!="Nil")
	   {
		   unlink("".$old_icon."");
	   }
}
	}
	else
	{
		$insfilename=$old_icon;
	}
	
	//upload user icon
	/*$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
	   $rand_isd=rand(1,9899989);
	   $filename=$rand_isd.$small_jpg;
                 $uploaddir='user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   unlink("".$old_icon."");
	}else{$uploadfile=$old_icon;}*/
	
	//update process
	$update_ss="update super_stockiest set user_icon='$insfilename',name='$name',email='$email',mobile_number='$mobile_number',username='$mobile_number',address='$address',gstin='$gstin',country_code='$country_code' where id='$update_id'";
	mysqli_query($db_conn,$update_ss);
	
	echo "<script>window.location='manage_sust?updatedSuccess';</script>";
	
}else{
	
	echo "<script>window.location='add_sust';</script>";
}
?>