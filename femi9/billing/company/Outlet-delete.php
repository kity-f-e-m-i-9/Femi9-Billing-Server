<?php include("checksession.php");
include("config.php");
//
$Coupon_category="Outlet";

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

$couponcategory=$_REQUEST['couponcat'];

//user details
$select_userdetails="select * from outlet where id='$prid'";
$fetch_userdtails=mysqli_query($db_conn,$select_userdetails);
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
	$update_cpnsstus="update coupons set coupon_status='none' where coupon_number='$ref_number' and category='$Coupon_category' and user_type='$Login_user_TYPEvl'";
	mysqli_query($db_conn,$update_cpnsstus);
}


//delete outlet details
$del_product="delete from outlet where id='$prid'";
mysqli_query($db_conn,$del_product);


echo "<script>window.location='Outlet-manage?deletedDone';</script>";
?>