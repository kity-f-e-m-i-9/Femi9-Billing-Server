<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ss_delete');

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//user details
$select_userdetails="select * from super_stockiest where id=?";
$fetch_userdtails=db_q($db_conn,$select_userdetails,[$prid]);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['user_icon'];
$amount_method=$result_useradetails['amount_method']; //coupon
$ref_number=$result_useradetails['ref_number'];
$super_stokist_id=$result_useradetails['temp_id'];

if($usericon!="Nil")
{
unlink("".$usericon."");
}

if($amount_method=="coupon")
{
	$update_cpnsstus="update coupons set coupon_status='none' where coupon_number=? and user_type='company'";
	db_q($db_conn,$update_cpnsstus,[$ref_number]);
}

//delete coupons
//$del_ss_coupns="delete from coupons where stock_user_tempid=? and user_type='super_stockiest'";
//db_q($db_conn,$del_ss_coupns,[$super_stokist_id]);

//delete super stokist details
$del_product="delete from super_stockiest where id=?";
db_q($db_conn,$del_product,[$prid]);

//un assigned super stokist
$stateID=$result_useradetails['state_id'];
$districtID=$result_useradetails['district_id'];

$UnassignSSID="update district set assigned_SSID='Nil' where id=? and state_id=?";
db_q($db_conn,$UnassignSSID,[$districtID,$stateID]);

echo "<script>window.location='manage_ss?deletedDone';</script>";
?>