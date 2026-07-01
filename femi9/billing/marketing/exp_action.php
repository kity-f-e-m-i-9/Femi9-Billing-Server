<?php include("checksession.php");
include("config.php");
error_reporting(0);

include("RemoveSpecialChar.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	
	
		$addurl="exp_add.php?distalready";
		$viewurl="exp_manage.php?addedsuccess";
	
	$tempid=$_POST["tempid"];
	$ms_id=$_POST["ms_id"];
	$date=date("Y-m-d");
	$amount=$_POST["amount"];
	$remarks=str_replace("'","&#39;",$_POST["remarks"]);
	$remarks=RemoveSpecialChar($remarks);
	
	
	$select_count_dist="select count(*) as numShop from ms_exp where tempid='$tempid'";
$fetc_count_dist=mysqli_query($db_conn,$select_count_dist);
	$result_count_dist=mysqli_fetch_array($fetc_count_dist);
	if($result_count_dist['numShop']==0)
	{
		
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
}else{
	$filename_bill="";
}
	
	
        $sql="insert into ms_exp (tempid,ms_id,date,amount,remarks,photos) values 
		('$tempid','$ms_id','$date','$amount','$remarks','$filename_bill')";
		mysqli_query($db_conn,$sql);
		
		echo "<script>window.location='".$viewurl."';</script>";


}else{
		//this districtwise super stockiest already exists.
		echo "<script>window.location='".$addurl."';</script>";
	}
	
	
	
}
?>