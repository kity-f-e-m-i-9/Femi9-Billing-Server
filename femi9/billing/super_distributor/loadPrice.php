<?php include("checksession.php");
$prid=$_GET['q']; 
error_reporting(0);
//
$select_ProductsPrice="select * from products where id='$prid'";
		$fetch_ProductsPrice=mysqli_query($db_conn,$select_ProductsPrice);
		$Result_ProductsPrice=mysqli_fetch_array($fetch_ProductsPrice);
		
		
		$invuser = isset($_GET['invuser']) ? trim($_GET['invuser']) : '';
		
		if($invuser=="super_stockiest"){
		$mrpamount=$Result_ProductsPrice['supersstock_price'];
		}
		else if($invuser=="stockiest"){
			$mrpamount=$Result_ProductsPrice['stockist_price'];
		}
		else if($invuser=="distributor"){
			$mrpamount=$Result_ProductsPrice['distributor_price'];
		}
		else if($invuser=="super_distributor"){
			$mrpamount=$Result_ProductsPrice['super_distributor_price'];
		}
		else if($invuser=="shop"){
			$mrpamount=$Result_ProductsPrice['outlet_price'];
		}
		else{
			$mrpamount=$Result_ProductsPrice['mrp'];
		}
		
		// Make editable for shop and customer
		$isReadonly = !($invuser=="shop" || $invuser=="customer");
?>
<input type="number" min="0" max="<?=$mrpamount;?>" step="any" id="amount" value="<?=$mrpamount;?>" name="amount" onKeyup="totalkm()">