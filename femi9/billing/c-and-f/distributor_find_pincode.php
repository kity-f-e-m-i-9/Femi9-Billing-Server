<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");

$stateid=$_GET['subcourseID']; //state
$districtid=$_GET['techID'];  // district
$talukID=$_GET['talukID'];  // taluk
?>

<select required="" name="pincode_id" class="form-control">
<option value="" hidden="">Select</option>
<?php $select_Picode_list="select * from pincode where state_id=? and dist_id=? and taluk_id=? order by pincode asc";
										$fetch_Picode_list=db_q($db_conn,$select_Picode_list,[$stateid,$districtid,$talukID]);
										while($Result_Picode_list=mysqli_fetch_array($fetch_Picode_list))
										{
											?>
											<option value="<?php echo htmlspecialchars((string)($Result_Picode_list['id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?php echo htmlspecialchars((string)($Result_Picode_list['pincode']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
										<?php }?>
												</select>