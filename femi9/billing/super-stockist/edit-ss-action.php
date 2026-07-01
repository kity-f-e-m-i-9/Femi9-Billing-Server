<?php include("checksession.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$update_id=$_REQUEST['update_id'];
$old_icon=$_REQUEST['old_icon'];
	
	$name=str_replace("'","&#39;",$_REQUEST['name']);
	
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
 echo "<script>window.location='edit-ss.php?prid=".base64_encode($update_id)."&&imageinvlaid';</script>";
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
	   if($old_icon!="Nil")
	   {unlink("".$old_icon."");}
	}else{$uploadfile=$old_icon;}*/
	
	//UPDATE STOCKIST DETAILS
	$update_ss="update stockiest set user_icon='$insfilename',name='$name',email='$email',mobile_number='$mobile_number',username='$mobile_number',address='$address',
	country_code='$country_code' where id='$update_id'";
	mysqli_query($db_conn,$update_ss);
	
	//UPDATE STOCKIST CATEGORY
	$stockistid=$_REQUEST['stockistid'];
    $st_cat_id=$_REQUEST['st_cat_id'];

	$update_ssRFR="update stockist_referral set st_cat_id='$st_cat_id' where stockist_id='$stockistid'";
	mysqli_query($db_conn,$update_ssRFR);
	
	
	echo "<script>window.location='manage_ss.php?updatedSuccess';</script>";
	
}else{
	
	echo "<script>window.location='add_ss.php';</script>";
}
	
	?>