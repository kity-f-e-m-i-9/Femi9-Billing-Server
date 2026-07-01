<?php include("checksession.php");
$userid=$_GET['q']; 
$invuser=$_GET['invuser']; 

//invuser = super_stockiest
//invuser = stockiest
//invuser = distributor
//invuser = shop

if($invuser=="super_stockiest")
{
	$displaytitle="Super Stockist";
	}
else if($invuser=="stockiest")
{
	$displaytitle="Stockist";
	}
else if($invuser=="distributor")
{
	$displaytitle="Distributor";
	}
	else if($invuser=="outlet")
{
	$displaytitle="Outlet";
	}
else
{
	//$displaytitle="Shop";
	}

//
$slctcheckstock="select count(*) as numstockcheck from stock where user_type='$invuser' and user_id='$userid'";
$fetch_ProductsPrice=mysqli_query($db_conn,$slctcheckstock);
$Result_ProductsPrice=mysqli_fetch_array($fetch_ProductsPrice);
if($Result_ProductsPrice['numstockcheck']!=0)
{
?>
 <button type="submit" name="addInvoice" class="btn btn-primary" id="add"><i class="material-icons">add</i>Add</button>
<?php }else{?>
<span style="color:red;">Please update opening stock! (<?=$displaytitle?>)</span>
<?php }?>

 