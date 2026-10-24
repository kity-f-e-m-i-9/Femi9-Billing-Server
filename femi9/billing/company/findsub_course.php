<?php require_once __DIR__ . '/../shared/db-helpers.php'; include("checksession.php");
$stateid=$_GET['subcourseID']; 
?>

<select required="" name="dist_id" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp" onChange="getCity(<?php echo $stateid;?>,this.value)">
<option value="" hidden="">Select</option>
<?php $select_product_list="select * from district where state_id=? order by dist_name asc";
										$fetch_product_list=db_q($db_conn,$select_product_list,[$stateid]);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											?>
											<option value="<?php echo $result_product_list['id'];?>"><?php echo $result_product_list['dist_name'];?></option>
										<?php }?>
												</select>

 