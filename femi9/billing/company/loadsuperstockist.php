<?php include("checksession.php");

$stateid=$_REQUEST['stateid'];
$distid=$_REQUEST['distid'];
?>
<select required="" name="ssid" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp">
										<option value="" hidden="">Select</option>
										<option value="company">--Company--</option>
										<?php $select_supersotkist="select * from super_stockiest where state_id='$stateid' and district_id='$distid' order by name asc";
										$fetch_supersotkist=mysqli_query($db_conn,$select_supersotkist);
										while($result_supersotkist=mysqli_fetch_array($fetch_supersotkist))
										{
											?>
<option value="<?=$result_supersotkist['temp_id'];?>"><?=strtoupper($result_supersotkist['name']);?>, <?=$result_supersotkist['mobile_number'];?></option>
										<?php }?>
												</select>