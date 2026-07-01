<?php 
include("checksession.php");
include("config.php");
error_reporting(0);

if(isset($_REQUEST['AssignedTalukAction']))
{
	$stockist_tempid=$_REQUEST['stockist_tempid'];
	$state_id=$_REQUEST['state_id'];
	$dist_id=$_REQUEST['dist_id'];
	$taluk_id=$_REQUEST['taluk_id'];
	
//STOCKIST ASSIGNED TO TALUK
$UPTassignedID="update taluk set assigned_SID='$stockist_tempid' where state_id='$state_id' and dist_id='$dist_id' and id='$taluk_id'";
mysqli_query($db_conn,$UPTassignedID);

//STOCKIST ASSIGNED TO PINCODE
$selectPincode="select * from pincode where state_id='$state_id' and dist_id='$dist_id' and taluk_id='$taluk_id'";
$fethPincode=mysqli_query($db_conn,$selectPincode);
while($resultPincode=mysqli_fetch_array($fethPincode))
{
	$pinCodeID=$resultPincode['id'];

$update_pincode_user="update pincode set assigned_SID='$stockist_tempid' where id='$pinCodeID'";	
mysqli_query($db_conn,$update_pincode_user);

} 

//TALUK ID UPDATE TO STOCKIST TABLE
$SELECTTALUKLIST="SELECT * FROM taluk WHERE assigned_SID='$stockist_tempid'";
$FETCHTALUIKLIST=mysqli_query($db_conn,$SELECTTALUKLIST);
while($RESULTTALUKLIST=mysqli_fetch_array($FETCHTALUIKLIST))
{
	$implodeTalukidGET[]=$RESULTTALUKLIST['id'];
}
$implodedValues = implode(',',$implodeTalukidGET);

$UPDATETALUKID="UPDATE stockiest SET taluk_id='$implodedValues' WHERE temp_id='$stockist_tempid'";
mysqli_query($db_conn,$UPDATETALUKID);
   
   $_SESSION['successMessage']="Taluk Assigned Successfully !";
   echo "<script>window.location='assigned_taluk.php?AssignedSuccess';</script>";
}

?>