<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
$stateid=$_REQUEST['subcourseID'];
$districtid=$_REQUEST['techID'];
?>

 <select required="" name="taluk_id" class="form-control" onChange="showPincode(<?php echo $stateid;?>,<?php echo $districtid;?>,this.value)">
 <option value="" hidden="">Select</option>
	<?php $select_taluk_list="select * from taluk where state_id=? and dist_id=? and assigned_SID='Nil' order by taluk asc";
										$fetch_taluk_list=db_q($db_conn,$select_taluk_list,[$stateid,$districtid]);
										while($result_taluk_list=mysqli_fetch_array($fetch_taluk_list))
										{
											?>
<option value="<?php echo $result_taluk_list['id'];?>"><?php echo $result_taluk_list['taluk'];?></option>
										<?php }?>
												</select>
												
 