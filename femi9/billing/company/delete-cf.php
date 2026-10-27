<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requireStaffOnly();

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//user details
$select_userdetails="select * from c_and_f where id=?";
$fetch_userdtails=db_q($db_conn,$select_userdetails,[$prid]);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['user_icon'];

if($usericon!="Nil")
{
unlink("".$usericon."");
}

$del_product="delete from c_and_f where id=?";
db_q($db_conn,$del_product,[$prid]);

echo "<script>window.location='manage_cf?deletedDone';</script>";
?>