<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
include("config.php");

$talukid=$_REQUEST['q'];
$districtid=$_REQUEST['districtid'];
$usertype=$_REQUEST['usertype'];
$stateid=$_REQUEST['stateid'];


if($usertype=="Distributor") {?>

<select required="" name="pincode_id[]" class="form-control" multiple>
<option value="" hidden="">Select</option>
<?php 
$select_Picode_list="select * from pincode where state_id=? and dist_id=? and taluk_id=? and assigned_DID='Nil' and assigned_SID=? order by pincode asc";

		$Fetch_Pincodevl_list=db_q($db_conn,$select_Picode_list,[$stateid,$districtid,$talukid,$Login_user_IDvl]);
		while($Result_Pincodevl_list=mysqli_fetch_array($Fetch_Pincodevl_list))
									{
								?>
<option value="<?=htmlspecialchars((string)($Result_Pincodevl_list['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?=htmlspecialchars((string)($Result_Pincodevl_list['pincode']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
						<?php }?>
						</select>
						
<?php }else{?>
						
						
<select required="" name="pincode_id2" class="form-control">
<option value="" hidden="">Select</option>
<?php 
$select_Picode_list="select * from pincode where state_id=? and dist_id=? and taluk_id=? and assigned_SID=? order by pincode asc";	

		$Fetch_Pincodevl_list=db_q($db_conn,$select_Picode_list,[$stateid,$districtid,$talukid,$Login_user_IDvl]);
		while($Result_Pincodevl_list=mysqli_fetch_array($Fetch_Pincodevl_list))
									{
								?>
<option value="<?=htmlspecialchars((string)($Result_Pincodevl_list['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?=htmlspecialchars((string)($Result_Pincodevl_list['pincode']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
						<?php }?>
						</select>
						
<?php }?>

<?php /*?>
<select required="" name="pincode_id" class="form-control">
<option value="" hidden="">Select</option>
<?php 
if($usertype=="Distributor")
{
$SelectPincodeList="select * from pincode where assigned_SID='".db_esc($Login_user_IDvl)."' and assigned_DID='Nil' and taluk_id='".db_esc($talukid)."' and dist_id='".db_esc($districtid)."' order by pincode asc";
}else{
$SelectPincodeList="select * from pincode where assigned_SID=? and taluk_id=? and dist_id=? order by pincode asc";	
}

$FetchPincodeList=db_q($db_conn,$SelectPincodeList,[$Login_user_IDvl,$talukid,$districtid]);
while($ResultPincodeList=mysqli_fetch_array($FetchPincodeList))
{
?>
<option value="<?php echo htmlspecialchars((string)($ResultPincodeList['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?php echo htmlspecialchars((string)($ResultPincodeList['pincode']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
<?php }?>
</select>
<?php */?>
 