<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");
error_reporting(0);
//
$Coupon_category="Distributor";

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//user details
$select_userdetails="select * from distributor where id=?";
$fetch_userdtails=db_q($db_conn,$select_userdetails,[$prid]);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['user_icon'];
$Distributor_ID=$result_useradetails['temp_id'];
$usertype=$result_useradetails['usertype'];

if($usericon!="Nil")
{
unlink("../stockist/".$usericon."");
}

/*if($usertype=="Distributor")
{
//un assigned pincode to distributor
$UnassignedDST="update pincode set assigned_DID='Nil' where assigned_DID=?";
db_q($db_conn,$UnassignedDST,[$Distributor_ID]);
}*/

$del_product="delete from distributor where id=?";
db_q($db_conn,$del_product,[$prid]);


echo "<script>window.location='Distributor-manage.php?deletedDone';</script>";
?>