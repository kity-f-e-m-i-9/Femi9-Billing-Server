<?php require_once __DIR__ . '/../shared/db-helpers.php'; require_once __DIR__ . '/../shared/safe-upload.php'; include("checksession.php");
include("RemoveSpecialChar.php");
error_reporting(0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {


$update_id=$_REQUEST['update_id'];
	$old_icon=safe_old_file_param($_REQUEST['old_icon']);
	
	$amount=$_POST["amount"];
	$remarks=str_replace("'","&#39;",$_POST["remarks"]);
	$remarks=RemoveSpecialChar($remarks);
	
	//upload user icon
	$file_extension_AADHAR = pathinfo($_FILES['photos']['name'], PATHINFO_EXTENSION);
$small_jpg_AADHAR= $_FILES['photos']['name'];
if($small_jpg_AADHAR!=NULL)
{
$DATETIME_AADHAR=date("YmdHis");
$rand_isd_AADHAR=bin2hex(random_bytes(32));
$filename_bill="".$DATETIME_AADHAR."".$rand_isd_AADHAR.".".$file_extension_AADHAR."";
$uploaddir_AADHAR='bill_copy_photos/';
$uploadfile_AADHAR=$uploaddir_AADHAR.$filename_bill;
safe_move_uploaded_file($_FILES['photos']['tmp_name'],$uploadfile_AADHAR);

unlink("bill_copy_photos/".$old_icon."");
}else{
	$filename_bill=$old_icon;
}
	
	
	//update process
	$update_ss="update ms_exp set amount=?,remarks=?,photos=? where id=?";
	db_q($db_conn,$update_ss,[$amount,$remarks,$filename_bill,$update_id]);
	
echo "<script>window.location='exp_manage.php?updatedSuccess';</script>";
	
	
	
}else{ echo "<script>window.location='exp_add.php';</script>"; }
?>