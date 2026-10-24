<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
$stateid=$_REQUEST['subcourseID'];
$districtid=$_REQUEST['techID'];
$talukid=$_REQUEST['divID'];
?>

 <select required="" readonly multiple class="form-control" style="height:250px !important;">
 <option value="" hidden="">Select</option>
	<?php 
$select_PINlist="select * from pincode where state_id=? and dist_id=? and taluk_id=? and assigned_SID='Nil' order by pincode asc";
										$fetch_PINlist=db_q($db_conn,$select_PINlist,[$stateid,$districtid,$talukid]);
										while($result_PINlist=mysqli_fetch_array($fetch_PINlist))
										{
											?>
<option value="<?php echo $result_PINlist['id'];?>"><?php echo $result_PINlist['pincode'];?></option>
										<?php }?>
												</select>
												
 