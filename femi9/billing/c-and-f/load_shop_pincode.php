<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
$TalukID=$_GET['divID']; 
$StateID=$_GET['subcourseID']; 
$DistrictID=$_GET['techID']; 

?>	
						
<select required="" name="pincode_id" class="form-control">
<option value="" hidden="">Select</option>
<?php 
$select_Picode_list="select * from pincode where state_id=? and dist_id=? and taluk_id=? order by pincode asc";	

		$Fetch_Pincodevl_list=db_q($db_conn,$select_Picode_list,[$StateID,$DistrictID,$TalukID]);
		while($Result_Pincodevl_list=mysqli_fetch_array($Fetch_Pincodevl_list))
									{
								?>
<option value="<?=htmlspecialchars((string)($Result_Pincodevl_list['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?=htmlspecialchars((string)($Result_Pincodevl_list['pincode']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
						<?php }?>
						</select>
						

 