<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requireAnyPermission(['dt','sdt','shop']);

$stateid=$_GET['subcourseID']; //state
$districtid=$_GET['techID'];  // district
?>

<select required="" name="taluk_id" class="form-control" onChange="getPincode(<?php echo $stateid;?>,<?php echo $districtid;?>,this.value)">
<option value="" hidden="">Select</option>
<?php $select_Taluk_list="select * from taluk where state_id=? and dist_id=? order by taluk asc";
										$fetch_Taluk_list=db_q($db_conn,$select_Taluk_list,[$stateid,$districtid]);
										while($result_Taluk_list=mysqli_fetch_array($fetch_Taluk_list))
										{
											?>
											<option value="<?php echo $result_Taluk_list['id'];?>"><?php echo $result_Taluk_list['taluk'];?></option>
										<?php }?>
												</select>