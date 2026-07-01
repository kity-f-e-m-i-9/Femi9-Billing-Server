<?php include("checksession.php");

$onboardby=$_REQUEST['q'];

if($onboardby=='candf' || $onboardby=='super_stockiest')
{	
if($onboardby=='candf')
{
	$Tablename="c_and_f";
	$LableName="C & F";   
}
if($onboardby=='super_stockiest')
{
	$Tablename="super_stockiest";
	$LableName="Super Stockist";
}
?>
<!-------C & F || Super Stockist--------------->
<select name="to_user_id" class="form-control" required>
<option value="" hidden="">Select <?=$LableName;?> (Assigned to)</option>
<?php $select_supersotkist="select * from ".$Tablename." order by name asc";
$fetch_supersotkist=mysqli_query($db_conn,$select_supersotkist);
while($result_supersotkist=mysqli_fetch_array($fetch_supersotkist))
{
?>
<option value="<?=$result_supersotkist['temp_id'];?>"><?=strtoupper($result_supersotkist['name']);?>, <?=$result_supersotkist['mobile_number'];?></option>
<?php }?>
</select>
<?php } else{?>
<!-------Company--------------->
<select name="to_user_id" class="form-control">
<option value="company">Company</option>
</select>

<?php }?>