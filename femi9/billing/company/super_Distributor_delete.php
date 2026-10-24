<?php require_once __DIR__ . '/../shared/db-helpers.php';
error_reporting(0);
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('sdt_delete');
include("config.php");
$prid=base64_decode($_REQUEST['prid']);

$select_userdetails="select * from super_distributor where id=?";
$fetch_userdtails=db_q($db_conn,$select_userdetails,[$prid]);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['user_icon'];

if($usericon!="Nil" && $usericon!=NULL)
{
unlink("../super_distributor/".$usericon."");
}

$del_product="delete from super_distributor where id=?";
db_q($db_conn,$del_product,[$prid]);

echo "<script>window.location='super_Distributor_manage?deletedDone';</script>";
exit;
?>