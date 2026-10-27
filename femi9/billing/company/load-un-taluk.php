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
<option value="<?=htmlspecialchars((string)($result_product_list244['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?=htmlspecialchars((string)($result_product_list244['taluk']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
								<?php }?>
											</select>