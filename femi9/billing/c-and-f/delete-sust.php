<?php include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//user details
$select_userdetails="select * from super_stockiest where id='$prid'";
$fetch_userdtails=mysqli_query($db_conn,$select_userdetails);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['user_icon'];
$amount_method=$result_useradetails['amount_method']; //coupon
$ref_number=$result_useradetails['ref_number'];
$super_stokist_id=$result_useradetails['temp_id'];

if($usericon!="Nil")
{
unlink("".$usericon."");
}

/*if($amount_method=="coupon")
{
	$update_cpnsstus="update coupons set coupon_status='none' where coupon_number='$ref_number' and user_type='company'";
	mysqli_query($db_conn,$update_cpnsstus);
}*/

//delete coupons
//$del_ss_coupns="delete from coupons where stock_user_tempid='$super_stokist_id' and user_type='super_stockiest'";
//mysqli_query($db_conn,$del_ss_coupns);

//delete super stokist details
$del_product="delete from super_stockiest where id='$prid'";
mysqli_query($db_conn,$del_product);

//un assigned super stokist
$stateID=$result_useradetails['state_id'];
$districtID=$result_useradetails['district_id'];

$UnassignSSID="update district set assigned_SSID='Nil' where id='$districtID' and state_id='$stateID'";
mysqli_query($db_conn,$UnassignSSID);

echo "<script>window.location='manage_sust?deletedDone';</script>";
?>