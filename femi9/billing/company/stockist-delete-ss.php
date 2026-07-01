<?php include("checksession.php");
include("config.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//user details
$select_userdetails="select * from stockiest where id='$prid'";
$fetch_userdtails=mysqli_query($db_conn,$select_userdetails);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['user_icon'];
$amount_method=$result_useradetails['amount_method'];
$ref_number=$result_useradetails['ref_number'];
$stokist_id=$result_useradetails['temp_id'];

//un assigned pincode to stkist
$update_unassigned="update pincode set assigned_SID='Nil' where assigned_SID='$stokist_id'";
mysqli_query($db_conn,$update_unassigned);

if($usericon!="Nil"){unlink("../super-stockist/".$usericon."");}

if($amount_method=="coupon")
{
	$update_cpnsstus="update coupons set coupon_status='none' where coupon_number='$ref_number' and user_type='$Login_user_TYPEvl'";
	mysqli_query($db_conn,$update_cpnsstus);
}

//delete coupons
//$del_ss_coupns="delete from coupons where stock_user_tempid='$stokist_id' and user_type='stockiest'";
//mysqli_query($db_conn,$del_ss_coupns);

//un assigned stokist
$stateID=$result_useradetails['state_id'];
$districtID=$result_useradetails['district_id'];
$talukID=$result_useradetails['taluk_id'];

$UnassignSSID="update taluk set assigned_SID='Nil' where assigned_SID='$stokist_id'";
mysqli_query($db_conn,$UnassignSSID);


//delete stockist details
$del_product="delete from stockiest where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='stockist-manage?deletedDone';</script>";
?>