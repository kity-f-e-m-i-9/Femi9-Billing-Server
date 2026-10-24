<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
$state_id=$_REQUEST['subcourseID'];
?>
<select required="" name="district_id" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp" onChange="showTaluk(<?php echo htmlspecialchars((string)($state_id), ENT_QUOTES, 'UTF-8', false);?>,this.value)">
<option value="" hidden="">Select</option>
<?php $select_product_list="select * from district where state_id=? order by dist_name asc";
$fetch_product_list=db_q($db_conn,$select_product_list,[$state_id]);
while($result_PRDTlist=mysqli_fetch_array($fetch_product_list))
{ ?>
<option value="<?php echo htmlspecialchars((string)($result_PRDTlist['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?php echo htmlspecialchars((string)($result_PRDTlist['dist_name']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
<?php }?>
</select>