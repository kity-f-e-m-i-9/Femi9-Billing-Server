<?php 
error_reporting(0);
include("checksession.php");
include("config.php");
$prid=base64_decode($_REQUEST['prid']);

$select_userdetails="select * from super_distributor where id='$prid'";
$fetch_userdtails=mysqli_query($db_conn,$select_userdetails);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['user_icon'];

if($usericon!="Nil" && $usericon!=NULL)
{
unlink("../super_distributor/".$usericon."");
}

$del_product="delete from super_distributor where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='super_Distributor_manage?deletedDone';</script>";
exit;
?>