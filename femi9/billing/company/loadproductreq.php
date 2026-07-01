<?php include("checksession.php");
$prid=$_GET['q'];
$reqid=$_GET['reqid'];
$reqid=base64_decode($reqid); 
error_reporting(0);

$select_ProductsPrice="select * from stock_request_items where prid='$prid' and reqid='$reqid'";
		$fetch_ProductsPrice=mysqli_query($db_conn,$select_ProductsPrice);
		$Result_ProductsPrice=mysqli_fetch_array($fetch_ProductsPrice);
		 
?>

 <input type="number" min="0" name="qty" value="<?=$Result_ProductsPrice['qty'];?>" readonly id="qty" onKeyup="totalkm()" required="" placeholder="Qty" class="numberinput">

<input type="number" min="0" name="amount" value="<?=$Result_ProductsPrice['amount'];?>" readonly id="amount" onKeyup="totalkm()" required="" placeholder="Price">

<input type="number" min="0" name="total" value="<?=$Result_ProductsPrice['subtotal'];?>" readonly id="output" class="numberinput" required="" placeholder="Total">
		
		<script>
  function discamount(){
   var output = document.getElementById('output').value;
   var discountpercentae = document.getElementById('discountpercentae').value;
   var outputdisaamount=(output*discountpercentae/100);
   document.getElementById('discountamount').value = outputdisaamount.toFixed(2); 
 }
</script>

<input type="number" min="0" id="discountpercentae" name="discount_percentage" onKeyup="discamount()" required="" placeholder="Disc(%)" class="numberinput">

<input type="number" min="0" id="discountamount" name="discount_amount" step="any" required="" placeholder="Disc(Rs.)" class="numberinput">
 