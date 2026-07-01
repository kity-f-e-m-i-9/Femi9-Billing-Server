<?php include("checksession.php");
include("RemoveSpecialChar.php");
error_reporting(0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {


$update_id=$_REQUEST['update_id'];
	$old_icon=$_REQUEST['old_icon'];
	
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
move_uploaded_file($_FILES['photos']['tmp_name'],$uploadfile_AADHAR);

unlink("bill_copy_photos/".$old_icon."");
}else{
	$filename_bill=$old_icon;
}
	
	
	//update process
	$update_ss="update ms_exp set amount='$amount',remarks='$remarks',photos='$filename_bill' where id='$update_id'";
	mysqli_query($db_conn,$update_ss);
	
echo "<script>window.location='exp_manage.php?updatedSuccess';</script>";
	
	
	
}else{ echo "<script>window.location='exp_add.php';</script>"; }
?>