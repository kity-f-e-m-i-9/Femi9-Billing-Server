<?php include("checksession.php");

$mappingusertype=$_REQUEST['q'];

if($mappingusertype=="company")
{
?>
<select name="mapuserid" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp">
										<option value="company" hidden="">Company</option>
												</select>
												
<?php }else{
	
	if($mappingusertype=="super_stockiest"){$tblename="super_stockiest"; $lablename="Super Stockist";}
	if($mappingusertype=="stockiest"){$tblename="stockiest"; $lablename="Stockist";}
	if($mappingusertype=="distributor"){$tblename="distributor"; $lablename="Distributor";}
	
	?>

<select required="" name="mapuserid" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp">
				<option value="" hidden="">Select <?=$lablename;?></option>
				<?php $select_product_list="select * from ".$tblename." order by name asc";
				$fetch_product_list=mysqli_query($db_conn,$select_product_list);
				while($result_product_list=mysqli_fetch_array($fetch_product_list))
				{
				?>
	<option value="<?=htmlspecialchars((string)($result_product_list['temp_id']), ENT_QUOTES, 'ISO-8859-1', false);?>"><?=htmlspecialchars((string)(strtoupper($result_product_list['name'])), ENT_QUOTES, 'ISO-8859-1', false);?>, <?=htmlspecialchars((string)($result_product_list['mobile_number']), ENT_QUOTES, 'ISO-8859-1', false);?></option>
					<?php }?>
												</select>
												

<?php }?>