<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('st_delete');
include("config.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//user details
$select_userdetails="select * from stockiest where id=?";
$fetch_userdtails=db_q($db_conn,$select_userdetails,[$prid]);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['user_icon'];
$amount_method=$result_useradetails['amount_method'];
$ref_number=$result_useradetails['ref_number'];
$stokist_id=$result_useradetails['temp_id'];

//un assigned pincode to stkist
$update_unassigned="update pincode set assigned_SID='Nil' where assigned_SID=?";
db_q($db_conn,$update_unassigned,[$stokist_id]);

if($usericon!="Nil"){unlink("../super-stockist/".$usericon."");}

if($amount_method=="coupon")
{
	$update_cpnsstus="update coupons set coupon_status='none' where coupon_number=? and user_type=?";
	db_q($db_conn,$update_cpnsstus,[$ref_number,$Login_user_TYPEvl]);
}

//delete coupons
//$del_ss_coupns="delete from coupons where stock_user_tempid=? and user_type='stockiest'";
//db_q($db_conn,$del_ss_coupns,[$stokist_id]);

//un assigned stokist
$stateID=$result_useradetails['state_id'];
$districtID=$result_useradetails['district_id'];
$talukID=$result_useradetails['taluk_id'];

$UnassignSSID="update taluk set assigned_SID='Nil' where assigned_SID=?";
db_q($db_conn,$UnassignSSID,[$stokist_id]);


//delete stockist details
$del_product="delete from stockiest where id=?";
db_q($db_conn,$del_product,[$prid]);

echo "<script>window.location='stockist-manage?deletedDone';</script>";
?>