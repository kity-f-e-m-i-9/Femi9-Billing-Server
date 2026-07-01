<?php include("checksession.php");

$distid=$_REQUEST['q'];
?>

 <select required="" name="taluk_id" class="form-control" onchange="showpincode(<?php echo $distid;?>,this.value)">
 <option value="" hidden="">Select</option>
	<?php $select_taluk_list="select * from taluk where dist_id='$distid' order by taluk asc";
										$fetch_taluk_list=mysqli_query($db_conn,$select_taluk_list);
										while($result_taluk_list=mysqli_fetch_array($fetch_taluk_list))
										{
											?>
<option value="<?php echo $result_taluk_list['id'];?>"><?php echo $result_taluk_list['taluk'];?> </option>
										<?php }?>
												</select>
												
 