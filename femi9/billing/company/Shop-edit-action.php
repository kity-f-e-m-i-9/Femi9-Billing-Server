<?php include("checksession.php");
include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$update_id=$_REQUEST['update_id'];
	$old_icon=$_REQUEST['old_icon'];
	$shop_cat=$_POST["shop_cat"];
	
	$name=str_replace("'","&#39;",$_REQUEST['name']);
	$name = RemoveSpecialChar($name);
	$country_code=$_POST["country_code"];
	
	$mobile_number=str_replace("'","&#39;",$_REQUEST['mobile_number']);
	$mobile_number = RemoveSpecialChar($mobile_number);
	
	$landline=str_replace("'","&#39;",$_POST["landline"]);
	$landline = RemoveSpecialChar($landline);
	
	$email=str_replace("'","&#39;",$_REQUEST['email']);
	$email = RemoveSpecialChar($email);
	
	$address=str_replace("'","&#39;",$_POST["address"]);
	$address = RemoveSpecialChar($address);
	
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$gstin = RemoveSpecialChar($gstin);
	
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
	   unlink("../distributor/".$old_icon."");
	}else{$insfilename=$old_icon;}*/
	
	
	//upload user icon
	$small_jpg= $_FILES['user_icon']['name'];
	if($small_jpg!=NULL)
	{
		
$filetype=$_FILES['user_icon']['type'];
if($filetype != 'image/jpeg' && $filetype != 'image/jpg' && $filetype != 'image/png')
{
$insfilename=$old_icon;
 echo "<script>window.location='Shop-edit?prid=".base64_encode($update_id)."&&imageinvlaid';</script>";
}else{


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
}
	}
	else
	{
		$insfilename=$old_icon;
	}
	
	
	
	//update process
	$update_ss="update shop set user_icon='$insfilename',name='$name',
	email='$email',mobile_number='$mobile_number',address='$address',
	gstin='$gstin',shop_cat='$shop_cat',country_code='$country_code',landline='$landline' where id='$update_id'";
	mysqli_query($db_conn,$update_ss);
	
	echo "<script>window.location='Shop-manage?updatedSuccess';</script>";
	
}else{
	
	echo "<script>window.location='Shop-add';</script>";
}
?>