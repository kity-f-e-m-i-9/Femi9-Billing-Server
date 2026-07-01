<?php include("checksession.php");
$stateid=$_GET['subcourseID']; 
?>


<select required="" name="district_id" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp" onChange="getTaluk(<?php echo $stateid;?>,this.value)">
<option value="" hidden="">Select</option>
<?php $select_product_list="select * from district where state_id='$stateid' order by dist_name asc";
										$fetch_product_list=mysqli_query($db_conn,$select_product_list);
										while($result_product_list=mysqli_fetch_array($fetch_product_list))
										{
											?>
											<option value="<?php echo $result_product_list['id'];?>"><?php echo $result_product_list['dist_name'];?></option>
										<?php }?>
											</select>

 