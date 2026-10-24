<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requireAnyPermission(['dt','st']);

$cpnnumber=$_REQUEST['q'];
$couponcategory=$_REQUEST['couponcategory'];

if($cpnnumber!=NULL)
{

$select_count_cpnvalid="select count(*) as numcpnsvalid from coupons where coupon_number=? and coupon_status='none' and category=?";
$fetch_count_cpnvalid=db_q($db_conn,$select_count_cpnvalid,[$cpnnumber,$couponcategory]);
$result_count_cpnvalid=mysqli_fetch_array($fetch_count_cpnvalid);
if($result_count_cpnvalid['numcpnsvalid']!=0)
{
	echo "<span class='badge badge-style-bordered badge-success'>&#10003;&nbsp;Valid Coupon</span>";
}else{
	echo "<span class='badge badge-style-bordered badge-danger'>&#10005;&nbsp;Invalid Coupon</span>";

}

}
?>

