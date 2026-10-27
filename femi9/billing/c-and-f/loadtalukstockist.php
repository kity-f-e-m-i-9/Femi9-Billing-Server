<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
$stateid=$_REQUEST['subcourseID'];
$districtid=$_REQUEST['techID'];

/*onChange="showPincode(<?php echo htmlspecialchars((string)($stateid), ENT_QUOTES, 'UTF-8', false);?>,<?php echo htmlspecialchars((string)($districtid), ENT_QUOTES, 'UTF-8', false);?>,this.value)"*/
?>

 <select required="" name="taluk_id" class="form-control">
 <option value="" hidden="">Select</option>
	<?php $select_taluk_list="select * from taluk where state_id=? and dist_id=? and assigned_SID='Nil' order by taluk asc";
										$fetch_taluk_list=db_q($db_conn,$select_taluk_list,[$stateid,$districtid]);
										while($result_taluk_list=mysqli_fetch_array($fetch_taluk_list))
										{
											?>
<option value="<?php echo htmlspecialchars((string)($result_taluk_list['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?php echo htmlspecialchars((string)($result_taluk_list['taluk']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
										<?php }?>
												</select>
												
 