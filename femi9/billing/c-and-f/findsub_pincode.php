<?php include("checksession.php");
$StateID=$_GET['StateID']; 
$DistrictID=$_GET['DistrictID']; 
$TalukID=$_GET['subcourseID']; 
?>
<!--display: none; name="pincode_id[]"-->
<select class="js-states form-control" readonly tabindex="-1" style="width: 100%" multiple="multiple">
<?php $select_Pincodevl_list="select * from pincode where state_id='$StateID' and dist_id='$DistrictID' and taluk_id='$TalukID' and assigned_SID='Nil' order by pincode asc";
		$Fetch_Pincodevl_list=mysqli_query($db_conn,$select_Pincodevl_list);
		while($Result_Pincodevl_list=mysqli_fetch_array($Fetch_Pincodevl_list))
									{
								?>
<option value="<?php echo $Result_Pincodevl_list['id'];?>"><?php echo $Result_Pincodevl_list['pincode'];?></option>
						<?php }?>
						</select>

 