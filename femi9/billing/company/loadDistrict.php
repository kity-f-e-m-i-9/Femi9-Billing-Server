<?php include("checksession.php");

$state_id=$_REQUEST['q'];
?>
<select required="" name="dist_id" onchange="showsuperstockist('<?=$state_id;?>',this.value)" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp">
										<option value="" hidden="">Select</option>
										<?php $select_product_list="select * from district where state_id='$state_id' and assigned_SSID='Nil' order by dist_name asc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
										?>
										<option value="<?php echo $result_product_list['id'];?>"><?php echo $result_product_list['dist_name'];?></option>
										<?php }?>
										</select>
												
												
									    <?php /* $select_product_list="select * from district where state_id='$state_id' order by dist_name asc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											
											if($result_product_list['assigned_SSID']=="Nil")
											{
										?>
											<option value="<?php echo $result_product_list['id'];?>"><?php echo $result_product_list['dist_name'];?></option>
											<?php }else{?>
											<option disabled><?php echo $result_product_list['dist_name'];?></option>
											<?php }?>
										<?php } */ ?>