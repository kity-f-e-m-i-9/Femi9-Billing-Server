<?php include("checksession.php");
include("config.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//user details
$select_userdetails="select * from distributor where id='$prid'";
$fetch_userdtails=mysqli_query($db_conn,$select_userdetails);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['user_icon'];
//$amount_method=$result_useradetails['amount_method']; //coupon
//$ref_number=$result_useradetails['ref_number'];
$Distributor_ID=$result_useradetails['temp_id'];
$usertype=$result_useradetails['usertype'];

if($usericon!="Nil")
{
unlink("".$usericon."");
}

/*
if($usertype=="Distributor")
{
//un assigned pincode to distributor
$UnassignedDST="update pincode set assigned_DID='Nil' where assigned_DID='$Distributor_ID'";
mysqli_query($db_conn,$UnassignedDST);
}
*/

//delete distributor details
$del_product="delete from distributor where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='manage_ss.php?deletedDone';</script>";
?>