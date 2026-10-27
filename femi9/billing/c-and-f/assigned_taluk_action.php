<?php require_once __DIR__ . '/../shared/db-helpers.php'; 
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
$UPTassignedID="update taluk set assigned_SID=? where state_id=? and dist_id=? and id=?";
db_q($db_conn,$UPTassignedID,[$stockist_tempid,$state_id,$dist_id,$taluk_id]);

//STOCKIST ASSIGNED TO PINCODE
$selectPincode="select * from pincode where state_id=? and dist_id=? and taluk_id=?";
$fethPincode=db_q($db_conn,$selectPincode,[$state_id,$dist_id,$taluk_id]);
while($resultPincode=mysqli_fetch_array($fethPincode))
{
	$pinCodeID=$resultPincode['id'];

$update_pincode_user="update pincode set assigned_SID=? where id=?";	
db_q($db_conn,$update_pincode_user,[$stockist_tempid,$pinCodeID]);

} 

//TALUK ID UPDATE TO STOCKIST TABLE
$SELECTTALUKLIST="SELECT * FROM taluk WHERE assigned_SID=?";
$FETCHTALUIKLIST=db_q($db_conn,$SELECTTALUKLIST,[$stockist_tempid]);
while($RESULTTALUKLIST=mysqli_fetch_array($FETCHTALUIKLIST))
{
	$implodeTalukidGET[]=$RESULTTALUKLIST['id'];
}
$implodedValues = implode(',',$implodeTalukidGET);

$UPDATETALUKID="UPDATE stockiest SET taluk_id=? WHERE temp_id=?";
db_q($db_conn,$UPDATETALUKID,[$implodedValues,$stockist_tempid]);
   
   $_SESSION['successMessage']="Taluk Assigned Successfully !";
   echo "<script>window.location='assigned_taluk.php?AssignedSuccess';</script>";
}

?>