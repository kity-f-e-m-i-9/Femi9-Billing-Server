<?php include("checksession.php");
include("config.php");
error_reporting(0);

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//user details
$select_userdetails="select * from demo_awareness where id='$prid'";
$fetch_userdtails=mysqli_query($db_conn,$select_userdetails);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['photo'];
if($usericon!="Nil"){unlink("".$usericon."");}

$del_product="delete from demo_awareness where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='manage_demo.php?deletedDone';</script>";
?>