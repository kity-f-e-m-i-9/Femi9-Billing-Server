<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('unassigned');

$state_id=$_REQUEST['subcourseID'];
$district_id=$_REQUEST['techID'];
?>
<select name="talukid" class="form-control">
								<option value="" hidden="">Select</option>
<?php $select_product_list244="select * from taluk where state_id=? and dist_id=? order by taluk asc";
			$fetch_product_list244=db_q($db_conn,$select_product_list244,[$state_id,$district_id]);
			while($result_product_list244=mysqli_fetch_array($fetch_product_list244))
										{
											?>
<option value="<?=$result_product_list244['id'];?>"><?=$result_product_list244['taluk'];?></option>
								<?php }?>
											</select>