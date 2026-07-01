<?php include("checksession.php");
include("RemoveSpecialChar.php");
error_reporting(0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$update_id=$_REQUEST['update_id'];
	$old_icon=$_REQUEST['old_icon'];
	$shop_cat=$_POST["shop_cat"];
	
	$name=str_replace("'","&#39;",$_POST["name"]);
	$name=RemoveSpecialChar($name);
	
	$state_name=$_POST["state_name"];
	$state_name=RemoveSpecialChar($state_name);
	
	$district_name=$_POST["district_name"];
	$district_name=RemoveSpecialChar($district_name);
	
	$taluk_name=$_POST["taluk_name"];
	$taluk_name=RemoveSpecialChar($taluk_name);
	
	$pincode=$_POST["pincode"];
	$pincode=RemoveSpecialChar($pincode);
	
	$country_code=$_POST["country_code"];
	$landline=str_replace("'","&#39;",$_POST["landline"]);
	
	$email=str_replace("'","&#39;",$_POST["email"]);
	$email=RemoveSpecialChar($email);
	
	$gstin=str_replace("'","&#39;",$_POST["gstin"]);
	$gstin=RemoveSpecialChar($gstin);
	
	$address=str_replace("'","&#39;",$_POST["address"]);
	$address=RemoveSpecialChar($address);
	
	$google_location=str_replace("'","&#39;",$_POST["google_location"]);
	
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


	   $file_extension = pathinfo($_FILES['user_icon']['name'], PATHINFO_EXTENSION);
		$rand_isd=bin2hex(random_bytes(64));
	    $filename=$rand_isd . '.' . $file_extension;
		
                 $uploaddir='shop_icon/';
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
	
	
	//update process
	$update_ss="update ms_shop set user_icon='$insfilename',name='$name',
	state_name='$state_name',district_name='$district_name',taluk_name='$taluk_name',
	pincode='$pincode',email='$email',gstin='$gstin',address='$address',shop_cat='$shop_cat',
country_code='$country_code',landline='$landline',google_location='$google_location' where id='$update_id'";
	mysqli_query($db_conn,$update_ss);
	
	echo "<script>window.location='manage_ss.php?updatedSuccess';</script>";
	
}else{
	
	echo "<script>window.location='add_ss.php';</script>";
}
	
	?>