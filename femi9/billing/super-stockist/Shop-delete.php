<?php include("checksession.php");
error_reporting(0);

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//user details
$select_userdetails="select * from shop where id='$prid'";
$fetch_userdtails=mysqli_query($db_conn,$select_userdetails);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['user_icon'];

if($usericon!="Nil")
{
unlink("../distributor/".$usericon."");
}

$del_product="delete from shop where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='Shop-manage.php?deletedDone';</script>";
?>