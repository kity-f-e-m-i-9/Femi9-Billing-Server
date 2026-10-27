<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('location');
$state_id=$_REQUEST['q'];
?>
<select required="" name="dist_id" onchange="showsuperstockist('<?=htmlspecialchars((string)($state_id), ENT_QUOTES, 'UTF-8', false);?>',this.value)" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp">
										<option value="" hidden="">Select</option>
<?php $select_product_list="select * from district where state_id=? order by dist_name asc";
										$fetch_product_list=db_q($db_conn,$select_product_list,[$state_id]);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
										?>
										<option value="<?php echo htmlspecialchars((string)($result_product_list['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?php echo htmlspecialchars((string)($result_product_list['dist_name']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
										<?php }?>
										</select>