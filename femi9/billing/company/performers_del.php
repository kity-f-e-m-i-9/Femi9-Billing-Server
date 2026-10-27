<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requireStaffOnly();

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//user details
$select_userdetails="select * from top_performar where id=?";
$fetch_userdtails=db_q($db_conn,$select_userdetails,[$prid]);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['per_photo'];

if($usericon!="")
{
unlink("top_performers_photo/".$usericon."");
}

$del_product="delete from top_performar where id=?";
db_q($db_conn,$del_product,[$prid]);

echo "<script>window.location='performers_manage?deletedDone';</script>";
?>