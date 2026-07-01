<?php include("checksession.php");

$cpnnumber=$_REQUEST['q'];
$couponcategory=$_REQUEST['couponcategory'];

if($cpnnumber!=NULL)
{

$select_count_cpnvalid="select count(*) as numcpnsvalid from coupons where coupon_number='$cpnnumber' and coupon_status='none' and category='$couponcategory'";
$fetch_count_cpnvalid=mysqli_query($db_conn,$select_count_cpnvalid);
$result_count_cpnvalid=mysqli_fetch_array($fetch_count_cpnvalid);
if($result_count_cpnvalid['numcpnsvalid']!=0)
{
	echo "<span class='badge badge-style-bordered badge-success'>&#10003;&nbsp;Valid Coupon</span>";
}else{
	echo "<span class='badge badge-style-bordered badge-danger'>&#10005;&nbsp;Invalid Coupon</span>";

}

}
?>

