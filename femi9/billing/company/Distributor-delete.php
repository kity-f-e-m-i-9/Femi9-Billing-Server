<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('dt_delete');
include("config.php");
//
$Coupon_category="Distributor";

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$couponcategory=$_REQUEST['couponcat'];

//user details
$select_userdetails="select * from distributor where id=?";
$fetch_userdtails=db_q($db_conn,$select_userdetails,[$prid]);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['user_icon'];
$amount_method=$result_useradetails['amount_method']; //coupon
$ref_number=$result_useradetails['ref_number'];
$Distributor_ID=$result_useradetails['temp_id'];
$usertype=$result_useradetails['usertype'];

if($usericon!="Nil")
{
unlink("../stockist/".$usericon."");
}

/*if($amount_method=="coupon")
{
	$update_cpnsstus="update coupons set coupon_status='none' where coupon_number=? and category=? and user_type=?";
	db_q($db_conn,$update_cpnsstus,[$ref_number,$Coupon_category,$Login_user_TYPEvl]);
}

if($usertype=="Distributor")
{
//un assigned pincode to distributor
$UnassignedDST="update pincode set assigned_DID='Nil' where assigned_DID=?";
db_q($db_conn,$UnassignedDST,[$Distributor_ID]);
}*/

//delete distributor details
$del_product="delete from distributor where id=?";
db_q($db_conn,$del_product,[$prid]);


echo "<script>window.location='Distributor-manage?deletedDone';</script>";
?>