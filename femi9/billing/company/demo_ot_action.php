<?php 
include("RemoveSpecialChar.php");

if(isset($_REQUEST['add-record']))
{
	
$tempid=str_replace("'","&#39;",$_REQUEST['tempid']);
$godownid=str_replace("'","&#39;",$_REQUEST['godownid']);
$date=date("Y-m-d",strtotime($_REQUEST['date']));
$catname=str_replace("'","&#39;",$_REQUEST['catname']);

$inv_number = RemoveSpecialChar($_REQUEST['inv_number']);
	$inv_number=str_replace("'","",$_REQUEST['inv_number']);
	$id_only="0";
	//---------------

//invoice accept=0
$Select_Count_Invoice="select * from ot_sales_invoice where inv_number='$inv_number' and cat='$catname'";
$Fetch_Count_Invoice=mysqli_query($db_conn,$Select_Count_Invoice);
$Result_Count_Invoice=mysqli_num_rows($Fetch_Count_Invoice);

	if($Result_Count_Invoice!=0)
	{
	$_SESSION['errorMessage']="Invoice Number already exists this category (".$catname.")";
	echo "<script>window.location='ot-sale-add?invoicealready';</script>";
	exit;
	}
	
		
	$customer_name=str_replace("'","&#39;",$_POST["customer_name"]);
	$customer_name = RemoveSpecialChar($customer_name);
	
	if($_POST["customer_mobile"]!=NULL){
	$customer_mobile=str_replace("'","&#39;",$_POST["customer_mobile"]);
	$customer_mobile = RemoveSpecialChar($customer_mobile);
	}else{$customer_mobile="";}
	
	$customer_address=str_replace("'","&#39;",$_POST["customer_address"]);
	$customer_address = RemoveSpecialChar($customer_address);
	
	$shipping_address=str_replace("'","&#39;",$_POST["shipping_address"]);
	$shipping_address = RemoveSpecialChar($shipping_address);
	
	//-------------------------------------------------------------------------
	if($_POST["gst_number"]!=NULL){
	$gst_number=str_replace("'","&#39;",$_POST["gst_number"]);
	$gst_number = RemoveSpecialChar($gst_number);
	}else{$gst_number = "";}
	
$buyer_GSTIN_count=strlen($gst_number);
if($buyer_GSTIN_count==15){ $buyer_gsttype="register";}else {$buyer_gsttype="unregister";}
//-------------------------------------------------------------------------
	
	if($_POST["order_number"]!=NULL){
	$order_number=str_replace("'","&#39;",$_POST["order_number"]);
	$order_number = RemoveSpecialChar($order_number);
	}else{$order_number="";}
	
	if($_REQUEST['order_date']!=NULL){
	$order_date=date("Y-m-d",strtotime($_REQUEST['order_date']));
	}else{$order_date="1991-01-01";}
	
	if($_REQUEST['ship_date']!=NULL){
	$ship_date=date("Y-m-d",strtotime($_REQUEST['ship_date']));
	}else{$ship_date="1991-01-01";}
		
	$amount_received="0";
	$amount_date="1991-01-01";
	$courier_charges = RemoveSpecialChar($_REQUEST['courier_charges']);
	
	$state_id = $_REQUEST['state_id'];
	$admin_state_id = $_REQUEST['admin_state_id'];
	if($state_id==$admin_state_id)
	{
		$gst_type="inner";
	}else{ $gst_type="outer";}
	
	
$product_id = implode("#",$_REQUEST['product_id']);
$qty = implode("#",$_REQUEST['qty']);
$rate = implode("#",$_REQUEST['rate']);
$discount = implode("#",$_REQUEST['discount']);
	
$product_id_ex = explode ("#",$product_id); 
$qty_ex = explode ("#",$qty);
$rate_ex = explode ("#",$rate);
$discount_ex = explode ("#",$discount); 

$number = count($product_id_ex); 
for ($i=0; $i<=$number; $i++) 
{ 
     $product_id_value = $product_id_ex[$i]; 
     $qty_value = $qty_ex[$i]; 
	 $qty_value = RemoveSpecialChar($qty_value);
	 
	 $rate_value = $rate_ex[$i];
	 $discount_value = $discount_ex[$i];
	 
	 if($product_id_value!=NULL)
	 {
		 
		 //count available stock
	$select_count_AVSTOCK="select * from stock where product_id='$product_id_value' and user_type='$Login_user_TYPEvl' and user_id='$godownid'";
	$FETCH_count_AVSTOCK=mysqli_query($db_conn,$select_count_AVSTOCK);
	$RESULT_count_AVSTOCK=mysqli_fetch_array($FETCH_count_AVSTOCK);
	$AVMstock=$RESULT_count_AVSTOCK['closing_qty'];
	
	if($AVMstock<$qty_value)
	{
		$_SESSION['errorMessageOT']="There is no stock for the quantity you entered!";
		echo "<script>window.location='ot-sale-add?InvalidStock&&AlertStockError';</script>";
		
	}else{
	 
	 //get product details
	 $selectprdteails="select * from products where id='$product_id_value'";
	 $fetchprdetails=mysqli_query($db_conn,$selectprdteails);
	 $resultprdetailis=mysqli_fetch_array($fetchprdetails);
	 //$pr_price=$resultprdetailis['mrp'];
	 
	 $sub_total_rate=$rate_value*$qty_value;
	 $sub_total=$sub_total_rate-$discount_value;
	 
	 $gst=$resultprdetailis['gst'];
	 $gst_amount=$sub_total*$gst/100;
	 $gst_amount=number_format($gst_amount,2,'.','');
	 
	 $total=$sub_total+$gst_amount;
	 
	 
	 $hsn=$resultprdetailis['hsn'];
	 //---------------------------------------------------------------------------
	 //---------------------------------------------------------------------------
	 //$inv_number=$_REQUEST['inv_number'];
	 
	 //1. get last id_only (invoice number generate)
		/*$select_MaxID="select max(inv_id) from ot_sales_invoice";
		$fetch_MaxID=mysqli_query($db_conn,$select_MaxID);
		$result_MaxID=mysqli_fetch_row($fetch_MaxID);
	    $id_only=$result_MaxID[0]+1;
		$format_num = str_pad($id_only, 3, '0', STR_PAD_LEFT);
		
		$INVDATE=date("ymd",strtotime($_REQUEST['date']));
		$INVNUMUSER="OT";
		$inv_number="F9".$randum_number."".$INVNUMUSER."".$format_num."";*/
		
	$select_count_INV="select count(*) as numINVOICE from ot_sales_invoice where tempid='$tempid'";
	$fetch_count_INV=mysqli_query($db_conn,$select_count_INV);
	$result_count_INV=mysqli_fetch_array($fetch_count_INV);
	if($result_count_INV['numINVOICE']==0)
	{
			$INSERT_INVOICE="insert into ot_sales_invoice (tempid,inv_id,inv_number,courier_charges,
			subtotal,round_off,total,buyer_gsttype,cat) values 
			('$tempid','$id_only','$inv_number','$courier_charges','0','0','0','$buyer_gsttype','$catname')";
			mysqli_query($db_conn,$INSERT_INVOICE);
	}
		
		//-------------------------------------------------------------------------
		//-------------------------------------------------------------------------
	 
	 $username=$_REQUEST['username'];
	 $usertype=$_REQUEST['usertype'];
	 
	$select_count_product="select count(*) as numCountRcds from ot_sales where tempid='$tempid' and prid='$product_id_value'";
	$fetch_count_product=mysqli_query($db_conn,$select_count_product);
	$result_count_product=mysqli_fetch_array($fetch_count_product);
	if($result_count_product['numCountRcds']==0)
	{
		//INSERT RECORD
		$insert_products="insert into ot_sales (godownid,cat,qty,date,tempid,prid,price,discount,sub_total,total,gst,gst_amount,
		customer_name,customer_mobile,
		customer_address,order_number,amount_received,amount_date,shipping_address,gst_number,
		order_date,ship_date,hsn,buyer_gsttype,state_id,gst_type,username,usertype)
		values 
		('$godownid','$catname','$qty_value','$date','$tempid','$product_id_value',
		'$rate_value','$discount_value','$sub_total','$total','$gst','$gst_amount','$customer_name','$customer_mobile',
		'$customer_address','$order_number','$amount_received','$amount_date','$shipping_address','$gst_number',
		'$order_date','$ship_date','$hsn','$buyer_gsttype','$state_id','$gst_type','$username','$usertype')";
		mysqli_query($db_conn,$insert_products);
		
		//UPDATE STOCK
		$select_stockDetails="select * from stock where product_id='$product_id_value' and user_type='$Login_user_TYPEvl' and user_id='$godownid'";
		$fetch_stockDetails=mysqli_query($db_conn,$select_stockDetails);
		$result_stockDetails=mysqli_fetch_array($fetch_stockDetails);
		
		$update_Input_stock=$result_stockDetails['sales_qty']+$qty_value;
		$update_Closing_stock=$result_stockDetails['closing_qty']-$qty_value;
		
		$update_stockDetails="update stock set sales_qty='$update_Input_stock',closing_qty='$update_Closing_stock' where product_id='$product_id_value' and user_type='$Login_user_TYPEvl' and user_id='$godownid'";
		mysqli_query($db_conn,$update_stockDetails);
		
	}
	 }
	 }
	
} 



//GET SUBTOTAL
	$select_subtotal="select sum(total) from ot_sales where tempid='$tempid'";
	$fetch_subtotal=mysqli_query($db_conn,$select_subtotal);
	$result_subtotal=mysqli_fetch_array($fetch_subtotal);
	if($result_subtotal[0]!=NULL)
	{
		$unroundvalue=$result_subtotal[0];
		$roundvalue=round($unroundvalue);
		$roundoff=$roundvalue-$unroundvalue;
		
		$update_roundvalue="update ot_sales_invoice set subtotal='$unroundvalue',round_off='$roundoff',total='$roundvalue' where tempid='$tempid'";
		mysqli_query($db_conn,$update_roundvalue);
	} 
	
	
		echo "<script>window.location='ot-sale-print?tempid=$tempid';</script>";
		exit;
		

}
?>