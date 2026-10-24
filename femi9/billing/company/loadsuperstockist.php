<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");

$stateid=$_REQUEST['stateid'];
$distid=$_REQUEST['distid'];
?>
<select required="" name="ssid" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp">
										<option value="" hidden="">Select</option>
										<option value="company">--Company--</option>
										<?php $select_supersotkist="select * from super_stockiest where state_id=? and district_id=? order by name asc";
										$fetch_supersotkist=db_q($db_conn,$select_supersotkist,[$stateid,$distid]);
										while($result_supersotkist=mysqli_fetch_array($fetch_supersotkist))
										{
											?>
<option value="<?=$result_supersotkist['temp_id'];?>"><?=strtoupper($result_supersotkist['name']);?>, <?=$result_supersotkist['mobile_number'];?></option>
										<?php }?>
												</select>