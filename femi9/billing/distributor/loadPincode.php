<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");

$talukid=$_REQUEST['q'];
$distid=$_REQUEST['talukid'];
?>


 <select required="" name="pincode_id" class="form-control">
 <option value="" hidden="">Select</option>
	<?php $select_pincode_list="select * from pincode where dist_id=? and taluk_id=? order by pincode asc";
		$fetch_pincode_list=db_q($db_conn,$select_pincode_list,[$distid,$talukid]);
		while($result_taluk_list=mysqli_fetch_array($fetch_pincode_list))
					{
						?>
<option value="<?=$result_taluk_list['id'];?>"><?=$result_taluk_list['pincode'];?></option>
										<?php }?>
												</select>
												
 