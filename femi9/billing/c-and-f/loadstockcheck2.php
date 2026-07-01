<?php include("checksession.php");
$userid=$_GET['q']; 
$invuser=$_GET['invuser']; 
$oldcustomerid=$_GET['oldcustomerid'];

//invuser = stockiest
//invuser = distributor

if($invuser=="stockiest")
{$displaytitle="Stockist";}
else
{$displaytitle="Distributor";}
	
$slctcheckstock="select * from stock where user_type='$invuser' and user_id='$userid'";
$fetch_ProductsPrice=mysqli_query($db_conn,$slctcheckstock);
$Result_ProductsPrice=mysqli_num_rows($fetch_ProductsPrice);

if($oldcustomerid==$userid)
{
	echo "<span style='color:red;'>This customer already exists</span>";
	
}else{

if($Result_ProductsPrice!=0)
{
?>
 <button type="submit" name="updateCustomer" class="btn btn-primary" id="add"><i class="material-icons">add</i>Update</button>
<?php }else{?>
<span style="color:red;">Please update opening stock! (<?=$displaytitle?>)</span>
<?php 
}
}
?>

 