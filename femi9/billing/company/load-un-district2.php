<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('unassigned');

$state_id=$_REQUEST['subcourseID'];
?>
<select name="dist_id" class="form-control" onChange="getCity(<?=htmlspecialchars((string)($state_id), ENT_QUOTES, 'UTF-8', false);?>,this.value)">
<option value="" hidden="">Select</option>
<?php 
$select_product_list="select * from district where state_id=? order by dist_name asc";
$fetch_product_list=db_q($db_conn,$select_product_list,[$state_id]);
while($result_product_list=mysqli_fetch_array($fetch_product_list))
{
?>
<option value="<?=htmlspecialchars((string)($result_product_list['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?=htmlspecialchars((string)($result_product_list['dist_name']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
<?php }?>
</select>