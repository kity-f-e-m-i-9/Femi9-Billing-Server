<?php require_once __DIR__ . '/../shared/db-helpers.php'; /* include("checksession.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$update_id=$_REQUEST['update_id'];
$stockistid=$_REQUEST['stockistid'];
$old_taluk_id=$_REQUEST['old_taluk_id'];

$state_id=$_POST["state_id"];
	$district_id=$_POST["dist_id"];
	$taluk_id=$_POST["taluk_id"];

//-----------------------------------------------------
//------------UN ASSIGNED TALUK , PINCODE--------------
//1
$SELECTTALUKLIST_unassign="UPDATE taluk SET assigned_SID='Nil' WHERE assigned_SID=? and id=?";
db_q($db_conn,$SELECTTALUKLIST_unassign,[$stockistid,$old_taluk_id]);

//2
$SELECPINCODELIST_unassign="UPDATE pincode SET assigned_SID='Nil' WHERE assigned_SID=? and taluk_id=?";
db_q($db_conn,$SELECPINCODELIST_unassign,[$stockistid,$taluk_id]);

	
//TALUK ID UPDATE TO STOCKIST TABLE
$SELECTTALUKLIST="SELECT * FROM taluk WHERE assigned_SID=?";
$FETCHTALUIKLIST=db_q($db_conn,$SELECTTALUKLIST,[$stockistid]);
while($RESULTTALUKLIST=mysqli_fetch_array($FETCHTALUIKLIST))
{
	$implodeTalukidGET[]=$RESULTTALUKLIST['id'];
}
$implodedValues = implode(',',$implodeTalukidGET);
	
		$updateLocation="update stockiest set state_id=?,district_id=?,
		taluk_id=? where id=?";
		db_q($db_conn,$updateLocation,[$state_id,$district_id,$implodedValues,$update_id]);
		

//ASSIGNED STOCKIST TO PINCODE
$selectPincode="select * from pincode where state_id=? and dist_id=? and taluk_id=?";
$fethPincode=db_q($db_conn,$selectPincode,[$state_id,$district_id,$taluk_id]);
while($resultPincode=mysqli_fetch_array($fethPincode))
{
	$pinCodeID=$resultPincode['id'];

$update_pincode_user="update pincode set assigned_SID=? where id=?";	
db_q($db_conn,$update_pincode_user,[$stockistid,$pinCodeID]);

} 
		
		echo "<script>window.location='stockist-manage?locationupdated';</script>";
		
	
}
	*/
	?>