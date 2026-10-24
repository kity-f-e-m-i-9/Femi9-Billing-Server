<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requireStaffOnly();
include("config.php");
//
$Coupon_category="Outlet";

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$couponcategory=$_REQUEST['couponcat'];

//user details
$select_userdetails="select * from outlet where id=?";
$fetch_userdtails=db_q($db_conn,$select_userdetails,[$prid]);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['user_icon'];
$amount_method=$result_useradetails['amount_method']; //coupon
$ref_number=$result_useradetails['ref_number'];
$Distributor_ID=$result_useradetails['temp_id'];

if($usericon!="Nil")
{
unlink("".$usericon."");
}

if($amount_method=="coupon")
{
	$update_cpnsstus="update coupons set coupon_status='none' where coupon_number=? and category=? and user_type=?";
	db_q($db_conn,$update_cpnsstus,[$ref_number,$Coupon_category,$Login_user_TYPEvl]);
}


//delete outlet details
$del_product="delete from outlet where id=?";
db_q($db_conn,$del_product,[$prid]);


echo "<script>window.location='Outlet-manage?deletedDone';</script>";
?>