<?php include("checksession.php");
error_reporting(0);

$stateid=$_GET['subcourseID']; //state
$districtid=$_GET['techID'];  // district
$talukID=$_GET['talukID'];  // taluk
$usertype=$_GET['usertype'];
?>

<select required="" name="pincode_id" class="form-control">
<option value="" hidden="">Select</option>
<?php 

if($usertype=="Distributor")
{
$select_Picode_list="select * from pincode where state_id='$stateid' and dist_id='$districtid' and taluk_id='$talukID' and assigned_DID='Nil' order by pincode asc";
}else{
$select_Picode_list="select * from pincode where state_id='$stateid' and dist_id='$districtid' and taluk_id='$talukID' order by pincode asc";	
}

$fetch_Picode_list=mysqli_query($db_conn,$select_Picode_list);
while($Result_Picode_list=mysqli_fetch_array($fetch_Picode_list))
{ ?>
<option value="<?=$Result_Picode_list['id'];?>"><?=$Result_Picode_list['pincode'];?></option>
<?php }?>
</select>
