<?php /* include("checksession.php");

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
$SELECTTALUKLIST_unassign="UPDATE taluk SET assigned_SID='Nil' WHERE assigned_SID='$stockistid' and id='$old_taluk_id'";
mysqli_query($db_conn,$SELECTTALUKLIST_unassign);

//2
$SELECPINCODELIST_unassign="UPDATE pincode SET assigned_SID='Nil' WHERE assigned_SID='$stockistid' and taluk_id='$taluk_id'";
mysqli_query($db_conn,$SELECPINCODELIST_unassign);

	
//TALUK ID UPDATE TO STOCKIST TABLE
$SELECTTALUKLIST="SELECT * FROM taluk WHERE assigned_SID='$stockistid'";
$FETCHTALUIKLIST=mysqli_query($db_conn,$SELECTTALUKLIST);
while($RESULTTALUKLIST=mysqli_fetch_array($FETCHTALUIKLIST))
{
	$implodeTalukidGET[]=$RESULTTALUKLIST['id'];
}
$implodedValues = implode(',',$implodeTalukidGET);
	
		$updateLocation="update stockiest set state_id='$state_id',district_id='$district_id',
		taluk_id='$implodedValues' where id='$update_id'";
		mysqli_query($db_conn,$updateLocation);
		

//ASSIGNED STOCKIST TO PINCODE
$selectPincode="select * from pincode where state_id='$state_id' and dist_id='$district_id' and taluk_id='$taluk_id'";
$fethPincode=mysqli_query($db_conn,$selectPincode);
while($resultPincode=mysqli_fetch_array($fethPincode))
{
	$pinCodeID=$resultPincode['id'];

$update_pincode_user="update pincode set assigned_SID='$stockistid' where id='$pinCodeID'";	
mysqli_query($db_conn,$update_pincode_user);

} 
		
		echo "<script>window.location='stockist-manage?locationupdated';</script>";
		
	
}
	*/
	?>