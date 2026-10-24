<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php"); require_once("include/PermissionCheck.php"); requireAnyPermission(['dt','st']);

$pamnet_method=$_REQUEST['q'];
$couponcategory=$_REQUEST['couponcategory'];

if($pamnet_method=="coupon")
{
?>
<label class="form-label">Coupon Number</label>
<input type="text" required="" name="ref_number" placeholder="Enter Coupon Number" class="form-control" onkeyup="showcouoponvalid(this.value)">
<?php }else{ ?>

<label class="form-label">Choose Plan</label>
            <select required="" name="plan_id" class="form-control">
												<option value="" hidden="">Select</option>
												<?php $select_plans="select * from plans where cat=? order by amount asc";
												$fetch_plans=db_q($db_conn,$select_plans,[$couponcategory]);
												while($result_plans=mysqli_fetch_array($fetch_plans))
												{?>
											<option value="<?php echo $result_plans['id'];?>">&#8377; <?php echo $result_plans['amount'];?> / <?php echo $result_plans['valid_months'];?> Days</option>
												<?php } ?>
												</select>
												
<?php }?>

