<?php include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//user details
$select_userdetails="select * from top_performar where id='$prid'";
$fetch_userdtails=mysqli_query($db_conn,$select_userdetails);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['per_photo'];

if($usericon!="")
{
unlink("top_performers_photo/".$usericon."");
}

$del_product="delete from top_performar where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='performers_manage?deletedDone';</script>";
?>