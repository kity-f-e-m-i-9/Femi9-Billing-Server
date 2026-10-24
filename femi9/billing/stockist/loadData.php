<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");

$distid=$_REQUEST['q'];
?>

 <select required="" name="taluk_id" class="form-control" onchange="showpincode(<?php echo htmlspecialchars((string)($distid), ENT_QUOTES, 'UTF-8', false);?>,this.value)">
 <option value="" hidden="">Select</option>
	<?php $select_taluk_list="select * from taluk where dist_id=? order by taluk asc";
										$fetch_taluk_list=db_q($db_conn,$select_taluk_list,[$distid]);
										while($result_taluk_list=mysqli_fetch_array($fetch_taluk_list))
										{
											?>
<option value="<?php echo $result_taluk_list['id'];?>"><?php echo $result_taluk_list['taluk'];?> </option>
										<?php }?>
												</select>
												
 