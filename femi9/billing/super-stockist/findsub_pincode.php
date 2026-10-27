<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
$StateID=$_GET['StateID']; 
$DistrictID=$_GET['DistrictID']; 
$TalukID=$_GET['subcourseID']; 
?>
<!--display: none; name="pincode_id[]"-->
<select class="js-states form-control" readonly tabindex="-1" style="width: 100%" multiple="multiple">
<?php $select_Pincodevl_list="select * from pincode where state_id=? and dist_id=? and taluk_id=? and assigned_SID='Nil' order by pincode asc";
		$Fetch_Pincodevl_list=db_q($db_conn,$select_Pincodevl_list,[$StateID,$DistrictID,$TalukID]);
		while($Result_Pincodevl_list=mysqli_fetch_array($Fetch_Pincodevl_list))
									{
								?>
<option value="<?php echo htmlspecialchars((string)($Result_Pincodevl_list['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?php echo htmlspecialchars((string)($Result_Pincodevl_list['pincode']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
						<?php }?>
						</select>

 