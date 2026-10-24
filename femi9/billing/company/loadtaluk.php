<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('location');

$distid=$_REQUEST['q'];
?>

 <select required="" name="taluk_id" class="form-control">
 <option value="" hidden="">Select</option>
	<?php $select_taluk_list="select * from taluk where dist_id=? order by taluk asc";
										$fetch_taluk_list=db_q($db_conn,$select_taluk_list,[$distid]);
										while($result_taluk_list=mysqli_fetch_array($fetch_taluk_list))
										{
											?>
<option value="<?php echo $result_taluk_list['id'];?>"><?php echo $result_taluk_list['taluk'];?></option>
										<?php }?>
												</select>
												
 