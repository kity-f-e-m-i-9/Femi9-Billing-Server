<?php include("checksession.php");

$user_table=$_REQUEST['usr'];
$usrname=base64_decode($_REQUEST['usrname']);
$back_url=$_REQUEST['backurl'];
$usrid=base64_decode($_REQUEST['usrid']);

$del_product="UPDATE ".$user_table." SET account_status='active' WHERE id='$usrid'";
mysqli_query($db_conn,$del_product);

//UNASSIGNED TALUK IF=STOCKIEST ONLY
if($user_table=='stockiest')
{
	$select_stockst_details="select taluk_id,temp_id from ".$user_table." where id='$usrid'";
	$fetch_stockist_details=mysqli_query($db_conn,$select_stockst_details);
	$result_stockist_details=mysqli_fetch_array($fetch_stockist_details);
	$talukID=$result_stockist_details['taluk_id'];
	$stockiestID=$result_stockist_details['temp_id'];
	
	$update_taluk="update taluk set assigned_SID='$stockiestID' where id='$talukID'";
	mysqli_query($db_conn,$update_taluk);
}
//-----------------------------------

$_SESSION['successMessage']="".$_REQUEST['userlabel']." User (".strtoupper($usrname).") Activated Success!";

if($back_url=="overallusers")
{
	echo "<script>window.location='overallusers?invuser=stockiest&&activatesuccess';</script>";
}
else if($back_url=="super_Distributor_overallusers")
{
	echo "<script>window.location='super_Distributor_overallusers?invuser=super_distributor&&activatesuccess';</script>";
}
else
{
	echo "<script>window.location='$back_url?activatesuccess';</script>";
}
?>