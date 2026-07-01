<?php /* include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//user details
$select_userdetails="select * from distributor where id='$prid'";
$fetch_userdtails=mysqli_query($db_conn,$select_userdetails);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['user_icon'];
$amount_method=$result_useradetails['amount_method']; //coupon
$ref_number=$result_useradetails['ref_number'];

if($usericon!="Nil")
{
unlink("".$usericon."");
}

if($amount_method=="coupon")
{
	$update_cpnsstus="update coupons set coupon_status='none' where coupon_number='$ref_number' and user_type='super_stockiest'";
	mysqli_query($db_conn,$update_cpnsstus);
}

$del_product="delete from distributor where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='manage_ss.php?deletedDone';</script>";
*/
?>