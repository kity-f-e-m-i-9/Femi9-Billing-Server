<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
$TalukID=$_GET['subcourseID']; 
$StateID=$_GET['StateID']; 
$DistrictID=$_GET['DistrictID']; 
$usertype=$_GET['usertype'];

if($usertype=="Distributor") {?>

<select required="" name="pincode_id[]" class="form-control" multiple>
<option value="" hidden="">Select</option>
<?php 
$select_Picode_list="select * from pincode where state_id=? and dist_id=? and taluk_id=? and assigned_DID='Nil' order by pincode asc";

		$Fetch_Pincodevl_list=db_q($db_conn,$select_Picode_list,[$StateID,$DistrictID,$TalukID]);
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
$select_Picode_list="select * from pincode where state_id=? and dist_id=? and taluk_id=? order by pincode asc";	

		$Fetch_Pincodevl_list=db_q($db_conn,$select_Picode_list,[$StateID,$DistrictID,$TalukID]);
		while($Result_Pincodevl_list=mysqli_fetch_array($Fetch_Pincodevl_list))
									{
								?>
<option value="<?=$Result_Pincodevl_list['id'];?>"><?=$Result_Pincodevl_list['pincode'];?></option>
						<?php }?>
						</select>
						
<?php }?>

 