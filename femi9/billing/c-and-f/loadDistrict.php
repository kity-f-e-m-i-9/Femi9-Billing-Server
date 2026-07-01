<?php include("checksession.php");

$state_id=$_REQUEST['q'];
?>
<select required="" name="dist_id" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp">
												<option value="" hidden="">Select</option>
										<?php $select_product_list="select * from district where state_id='$state_id' order by dist_name asc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											?>
											<option value="<?php echo $result_product_list['id'];?>"><?php echo $result_product_list['dist_name'];?></option>
										<?php }?>
												</select>