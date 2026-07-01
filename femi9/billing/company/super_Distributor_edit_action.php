<?php include("checksession.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$update_id=$_REQUEST['update_id'];
	$old_icon=$_REQUEST['old_icon'];
	
	$name=str_replace("'","&#39;",$_REQUEST['name']);
	$name = RemoveSpecialChar($name);
	$country_code=$_POST["country_code"];
	
	$mobile_number=str_replace("'","&#39;",$_REQUEST['mobile_number']);
	$mobile_number = RemoveSpecialChar($mobile_number);
	
	$email=str_replace("'","&#39;",$_REQUEST['email']);
	$email = RemoveSpecialChar($email);
	
	$address=str_replace("'","&#39;",$_POST["address"]);
	$address = RemoveSpecialChar($address);
	
	$gstin=str_replace("'","&#39;",$_REQUEST['gstin']);
	$gstin = RemoveSpecialChar($gstin);
	
	$state_id=str_replace("'","&#39;",$_POST["state_id"]);
	$state_id = RemoveSpecialChar($state_id);
	
	$district_id=str_replace("'","&#39;",$_POST["district_id"]);
	$district_id = RemoveSpecialChar($district_id);
	
	$taluk_id=str_replace("'","&#39;",$_POST["taluk_id"]);
	$taluk_id = RemoveSpecialChar($taluk_id);
	
	$pincode_id=str_replace("'","&#39;",$_POST["pincode_id"]);
	$pincode_id = RemoveSpecialChar($pincode_id);
	
	$shop_onboard=$_POST["shop_onboard"];
	
	//upload user icon
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
		
$filetype=$_FILES['user_icon']['type'];
if($filetype != 'image/jpeg' && $filetype != 'image/jpg' && $filetype != 'image/png')
{
$insfilename=$old_icon;
 echo "<script>window.location='super_Distributor_edit?prid=".base64_encode($update_id)."&&imageinvlaid';</script>";
 exit;
}else{


	   $rand_isd=rand(1,9899989);
	   $filename=$rand_isd.$small_jpg;
                 $uploaddir='../super_distributor/user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="user_icon/";
	   $insfilename=$insfoldername.$filename;
	   if($old_icon!="Nil")
	   {
		   unlink("../super_distributor/".$old_icon."");
	   }
}
	}
	else
	{
		$insfilename=$old_icon;
	}
	
	
	//update process
	$update_ss="update super_distributor set user_icon='$insfilename',name='$name',email='$email',
	address='$address',gstin='$gstin',
	country_code='$country_code',state_id='$state_id',district_id='$district_id',taluk_id='$taluk_id',
	pincode_id='$pincode_id',shop_onboard='$shop_onboard' where id='$update_id'";
	mysqli_query($db_conn,$update_ss);
	
	//Update target amount
	$target_amount=$_REQUEST['target_amount'];
	$sd_id=$_REQUEST['sd_id'];
	
	$upadte129="update super_distributor_referral set target_amount='$target_amount' where sd_id='$sd_id'";
	mysqli_query($db_conn,$upadte129);
	
	echo "<script>window.location='super_Distributor_manage?updatedSuccess';</script>";
	exit;

// !isset submit	
}else{
	echo "<script>window.location='dashboard';</script>";
}
	
	?>