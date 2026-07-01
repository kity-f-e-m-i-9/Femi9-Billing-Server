<?php include("checksession.php");
$prid=$_GET['q']; 
error_reporting(0);

//
$select_ProductsPrice="select * from products where id='$prid'";
		$fetch_ProductsPrice=mysqli_query($db_conn,$select_ProductsPrice);
		$Result_ProductsPrice=mysqli_fetch_array($fetch_ProductsPrice);
		
		
		$invuser=$_GET['invuser']; 
		if($invuser=="super_stockiest"){
		$mrpamount=$Result_ProductsPrice['supersstock_price'];
		}
		else if($invuser=="stockiest"){
			$mrpamount=$Result_ProductsPrice['stockist_price'];
		}
		else if($invuser=="distributor"){
			$mrpamount=$Result_ProductsPrice['distributor_price'];
		}
		else if($invuser=="shop"){
			$mrpamount=$Result_ProductsPrice['outlet_price'];
		}
		else{
			$mrpamount=$Result_ProductsPrice['mrp'];
		}
		
?>

<input type="number" min="0" id="amount" step="any" onKeyup="totalkm()" value="<?=$mrpamount;?>" name="amount" required="">

 