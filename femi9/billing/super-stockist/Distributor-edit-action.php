<?php include("checksession.php");
include("RemoveSpecialChar.php");
error_reporting(0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$update_id=$_REQUEST['update_id'];
$old_icon=$_REQUEST['old_icon'];
	
	$name=str_replace("'","&#39;",$_REQUEST['name']);
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$mobile_number=str_replace("'","&#39;",$_REQUEST['mobile_number']);
	$email=str_replace("'","&#39;",$_REQUEST['email']);
	$address=str_replace("'","&#39;",$_POST["address"]);
	$country_code=$_POST["country_code"];
	
	//upload user icon
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
		
$filetype=$_FILES['user_icon']['type'];
if($filetype != 'image/jpeg' && $filetype != 'image/jpg' && $filetype != 'image/png')
{
$insfilename=$old_icon;
 echo "<script>
 window.location='Distributor-edit.php?prid=".base64_encode($update_id)."&&imageinvlaid';
 </script>";
 exit;
 
}else{

	  $rand_isd=rand(1,9899989);
	   $filename=$rand_isd.$small_jpg;
                 $uploaddir='../stockist/user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="user_icon/";
	   $insfilename=$insfoldername.$filename;
	   if($old_icon!="Nil")
	   {
		   unlink("../stockist/".$old_icon."");
	   }
}
	}
	else
	{
		$insfilename=$old_icon;
	}
	
	//upload user icon
	/*
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
	   $rand_isd=rand(1,9899989);
	   $filename=$rand_isd.$small_jpg;
                 $uploaddir='../stockist/user_icon/';
                 $uploadfile=$uploaddir.$filename;
	   move_uploaded_file($_FILES['user_icon']['tmp_name'],$uploadfile);
	   $insfoldername="user_icon/";
	   $insfilename=$insfoldername.$filename;
		   unlink("../stockist/".$old_icon."");
	}
	else
	{
		$insfilename=$old_icon;
	}*/
	
	$state_id=str_replace("'","&#39;",$_POST["state_id"]);
	$state_id = RemoveSpecialChar($state_id);
	
	$district_id=str_replace("'","&#39;",$_POST["district_id"]);
	$district_id = RemoveSpecialChar($district_id);
	
	$taluk_id=str_replace("'","&#39;",$_POST["taluk_id"]);
	$taluk_id = RemoveSpecialChar($taluk_id);
	
	$pincode_id=str_replace("'","&#39;",$_POST["pincode_id"]);
	$pincode_id = RemoveSpecialChar($pincode_id);
	
	$shop_onboard=$_POST["shop_onboard"];
	
	
	//update process
	$update_ss="update distributor set user_icon='$insfilename',name='$name',
	email='$email',mobile_number='$mobile_number',username='$mobile_number',
	address='$address',country_code='$country_code',gstin='$gstin',state_id='$state_id',
    district_id='$district_id',taluk_id='$taluk_id',pincode_id='$pincode_id',shop_onboard='$shop_onboard' 
	where id='$update_id'";
	mysqli_query($db_conn,$update_ss);
	
	//Update target amount
	$target_amount=$_REQUEST['target_amount'];
	$distributor_id=$_REQUEST['distributor_id'];
	
	//Update Distributor Referral
	$select_count_referral="select id from distributor_referral where distributor_id='$distributor_id'";
	$fetch_count_referral=mysqli_query($db_conn,$select_count_referral);
	$result_count_referral=mysqli_num_rows($fetch_count_referral);
	if($result_count_referral==0)
	{
		$insert_referral="insert into distributor_referral (distributor_id,target_amount,ref_by_user_type,ref_by_user_id,updated) values 
		('$distributor_id','$target_amount','company','','0')";
		mysqli_query($db_conn,$insert_referral);
		
	}
	else
	{
	$upadte129="update distributor_referral set target_amount='$target_amount' where distributor_id='$distributor_id'";
	mysqli_query($db_conn,$upadte129);
	}
	
	echo "<script>window.location='Distributor-manage.php?updatedSuccess';</script>";
	exit;
	
}else{
	echo "<script>window.location='Distributor-add.php';</script>";
	exit;
}
?>