<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");

$cpnnumber=$_REQUEST['q'];

if($cpnnumber!=NULL)
{

$select_count_cpnvalid="select count(*) as numcpnsvalid from coupons where coupon_number=? and coupon_status='none'";
$fetch_count_cpnvalid=db_q($db_conn,$select_count_cpnvalid,[$cpnnumber]);
$result_count_cpnvalid=mysqli_fetch_array($fetch_count_cpnvalid);
if($result_count_cpnvalid['numcpnsvalid']!=0)
{
	echo "<span class='badge badge-style-bordered badge-success'>&#10003;&nbsp;Valid Coupon</span>";
}else{
	echo "<span class='badge badge-style-bordered badge-danger'>&#10005;&nbsp;Invalid Coupon</span>";

}

}
?>

