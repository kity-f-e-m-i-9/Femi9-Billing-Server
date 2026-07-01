<?php include("checksession.php");

$prid=$_REQUEST['prid'];
$prid=base64_decode($prid);

//user details
$select_userdetails="select * from offers_manage where id='$prid'";
$fetch_userdtails=mysqli_query($db_conn,$select_userdetails);
$result_useradetails=mysqli_fetch_array($fetch_userdtails);

$usericon=$result_useradetails['offer_img'];

if($usericon!="")
{
unlink("offers_img/".$usericon."");
}

$del_product="delete from offers_manage where id='$prid'";
mysqli_query($db_conn,$del_product);

echo "<script>window.location='offers_manage?deletedDone';</script>";
?>