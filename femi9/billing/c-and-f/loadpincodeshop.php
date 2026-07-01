<?php include("checksession.php");
$TalukID=$_GET['subcourseID']; 
$StateID=$_GET['StateID']; 
$DistrictID=$_GET['DistrictID']; 
?>

<select required="" name="pincode_id" class="form-control">
<option value="" hidden="">Select</option>
<?php $select_Pincodevl_list="select * from pincode where state_id='$StateID' and dist_id='$DistrictID' and taluk_id='$TalukID' order by pincode asc";
		$Fetch_Pincodevl_list=mysqli_query($db_conn,$select_Pincodevl_list);
		while($Result_Pincodevl_list=mysqli_fetch_array($Fetch_Pincodevl_list))
									{
								?>
<option value="<?php echo $Result_Pincodevl_list['id'];?>"><?php echo $Result_Pincodevl_list['pincode'];?></option>
						<?php }?>
						</select>

 