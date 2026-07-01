<?php include("checksession.php");
include("config.php");

$talukid=$_REQUEST['q'];
$districtid=$_REQUEST['districtid'];
$usertype=$_REQUEST['usertype'];
$stateid=$_REQUEST['stateid'];


if($usertype=="Distributor") {?>

<select required="" name="pincode_id[]" class="form-control" multiple>
<option value="" hidden="">Select</option>
<?php 
$select_Picode_list="select * from pincode where state_id='$stateid' and dist_id='$districtid' and taluk_id='$talukid' and assigned_DID='Nil' and assigned_SID='$Login_user_IDvl' order by pincode asc";

		$Fetch_Pincodevl_list=mysqli_query($db_conn,$select_Picode_list);
		while($Result_Pincodevl_list=mysqli_fetch_array($Fetch_Pincodevl_list))
									{
								?>
<option value="<?=$Result_Pincodevl_list['id'];?>"><?=$Result_Pincodevl_list['pincode'];?></option>
						<?php }?>
						</select>
						
<?php }else{?>
						
						
<select required="" name="pincode_id2" class="form-control">
<option value="" hidden="">Select</option>
<?php 
$select_Picode_list="select * from pincode where state_id='$stateid' and dist_id='$districtid' and taluk_id='$talukid' and assigned_SID='$Login_user_IDvl' order by pincode asc";	

		$Fetch_Pincodevl_list=mysqli_query($db_conn,$select_Picode_list);
		while($Result_Pincodevl_list=mysqli_fetch_array($Fetch_Pincodevl_list))
									{
								?>
<option value="<?=$Result_Pincodevl_list['id'];?>"><?=$Result_Pincodevl_list['pincode'];?></option>
						<?php }?>
						</select>
						
<?php }?>

<?php /*?>
<select required="" name="pincode_id" class="form-control">
<option value="" hidden="">Select</option>
<?php 
if($usertype=="Distributor")
{
$SelectPincodeList="select * from pincode where assigned_SID='$Login_user_IDvl' and assigned_DID='Nil' and taluk_id='$talukid' and dist_id='$districtid' order by pincode asc";
}else{
$SelectPincodeList="select * from pincode where assigned_SID='$Login_user_IDvl' and taluk_id='$talukid' and dist_id='$districtid' order by pincode asc";	
}

$FetchPincodeList=mysqli_query($db_conn,$SelectPincodeList);
while($ResultPincodeList=mysqli_fetch_array($FetchPincodeList))
{
?>
<option value="<?php echo $ResultPincodeList['id'];?>"><?php echo $ResultPincodeList['pincode'];?></option>
<?php }?>
</select>
<?php */?>
 