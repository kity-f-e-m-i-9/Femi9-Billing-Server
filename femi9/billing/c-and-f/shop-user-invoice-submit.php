<?php include("checksession.php"); 
include("config.php"); 
error_reporting(0);

if(isset($_REQUEST['invoice-submit']))
{
	$invoice_id=$_REQUEST['invoice_id'];
	
	
	///UPDATE INVOCIE - IF EDIT INVOICE ACTIO ONLY	
if($_REQUEST['update_invoice_date']!=NULL)
{
$update_invoice_date=date("Y-m-d",strtotime($_REQUEST['update_invoice_date']));

//1
$update_invoice12="update user_invoice set date='$update_invoice_date' where inv_id='$invoice_id' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
mysqli_query($db_conn,$update_invoice12);

//2	
$update_invoice_ITEMS12="update user_invoice_items set date='$update_invoice_date' where inv_id='$invoice_id' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
mysqli_query($db_conn,$update_invoice_ITEMS12);

	}
//--------------------------------------------

	
	//DELETE RECEIPT DETAILS IF EDIT FUNCTION
	if($_SESSION['ACTIONEDIT']=="edit"){
		
		$delReceipt="delete from receipt where inv_id='$invoice_id'";
		mysqli_query($db_conn,$delReceipt);
	}
	
	$SubTotal=$_REQUEST['SubTotal'];
	if($_REQUEST['discount']!=NULL){ $discount=$_REQUEST['discount'];}else{$discount="0";}
	$roundoff=$_REQUEST['roundoff'];
	$courier_charges=$_REQUEST['courier_charges'];
	
	$total_amount=$SubTotal-$discount+$courier_charges;
	$total_amount=round($total_amount);
	
	//get invoice details
	$select_invoicedtails="select * from user_invoice where inv_id='$invoice_id'";
	$fetch_invoicedtails=mysqli_query($db_conn,$select_invoicedtails);
	$result_invoicedetails=mysqli_fetch_array($fetch_invoicedtails);
	//
	$shopid=$result_invoicedetails['to_user_id'];
	$invdate=$result_invoicedetails['date'];
	
	
	//insert receipt
	$insertreceiptcount="select count(*) as numreceipt from receipt where receiptid='$invoice_id'";
	$fetchreceipt=mysqli_query($db_conn,$insertreceiptcount);
	$resultreceipt=mysqli_fetch_array($fetchreceipt);
	if($resultreceipt['numreceipt']==0)
	{
		$receiptdate=$result_invoicedetails['date'];
		if($_REQUEST['receivedamount']!=NULL){
		$receivedamount=$_REQUEST['receivedamount'];
		}else{$receivedamount="0";}
		$receivableamount=$total_amount-$receivedamount;
		$receivableamount=round($receivableamount);
		//
		
		$usertype=$result_invoicedetails['to_user_type'];
	    $userid=$result_invoicedetails['to_user_id'];
	
	$receipt_method=$_REQUEST['receipt_method'];
		$receipt_remarks=str_replace("'","&#39;",$_REQUEST['receipt_remarks']);
		
		$insertreceipt="insert into receipt (receiptid,inv_id,invoice_amount,received,receivable,date,from_user_type,from_user_id,to_user_type,to_user_id,receipt_method,receipt_remarks) 
		values 
		('$invoice_id','$invoice_id','$total_amount','$receivedamount','$receivableamount','$receiptdate','".$result_invoicedetails['from_user_type']."','".$result_invoicedetails['from_user_id']."','$usertype','$userid','$receipt_method','$receipt_remarks')";
		mysqli_query($db_conn,$insertreceipt);
		
	}
	
	
	if($_REQUEST['invoice_id']!=NULL)
	{
	$update_invoice="update user_invoice set sub_total='$SubTotal',discount='$discount',total='$total_amount',
	roundoff='$roundoff',courier_charges='$courier_charges' where inv_id='$invoice_id' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
	mysqli_query($db_conn,$update_invoice);
	}
	
	
	/*
	//----------------------------------------------------------
	//insert current stock
	//----------------------------------------------------------
	$cr_prid = implode("#",$_REQUEST['cr_prid']);
$cr_qty = implode("#",$_REQUEST['cr_qty']);
	
$cr_prid_ex = explode ("#",$cr_prid); 
$cr_qty_ex = explode ("#",$cr_qty); 

$number = count($cr_prid_ex); 
for ($i=0; $i<=$number; $i++) 
{ 
     $cr_prid_value = $cr_prid_ex[$i]; 
     $cr_qty_value = $cr_qty_ex[$i]; 
	 
	 $select_countcst="select count(*) as numcst from shop_current_stock where inv_id='$invoice_id' and prid='$cr_prid_value'";
	$fetch_countcst=mysqli_query($db_conn,$select_countcst);
	$result_countcst=mysqli_fetch_array($fetch_countcst);
	if($result_countcst['numcst']==0 && $cr_prid_value!=NULL)
	{
		$insertcst="insert into shop_current_stock (inv_id,shop_id,prid,qty,date) values 
		('$invoice_id','$shopid','$cr_prid_value','$cr_qty_value','$invdate')";
		mysqli_query($db_conn,$insertcst);
	}
	 
} 
	
	//-------------------------------------------------------------
	//insert competitor stock
	//-------------------------------------------------------------
	
	$cst_prid = implode("#",$_REQUEST['cst_prid']);
$cst_qty = implode("#",$_REQUEST['cst_qty']);
$cst_panty = implode("#",$_REQUEST['cst_panty']);
	
$cst_prid_ex = explode ("#",$cst_prid); 
$cst_qty_ex = explode ("#",$cst_qty); 
$cst_panty_ex = explode ("#",$cst_panty); 

$numbercst = count($cst_prid_ex); 
for ($icst=0; $icst<=$numbercst; $icst++) 
{ 
     $cst_prid_value = $cst_prid_ex[$icst]; 
     $cst_qty_value = $cst_qty_ex[$icst]; 
	 $cst_panty_value = $cst_panty_ex[$icst]; 
	 
	 $select_countcst12="select count(*) as numcstcomp from shop_competitor_stock where inv_id='$invoice_id' and brandid='$cst_prid_value'";
	$fetch_countcst12=mysqli_query($db_conn,$select_countcst12);
	$result_countcst12=mysqli_fetch_array($fetch_countcst12);
	if($result_countcst12['numcstcomp']==0 && $cst_prid_value!=NULL)
	{
		$insertcst12="insert into shop_competitor_stock (inv_id,shop_id,brandid,qty,date,cst_panty) values 
		('$invoice_id','$shopid','$cst_prid_value','$cst_qty_value','$invdate','$cst_panty_value')";
		mysqli_query($db_conn,$insertcst12);
	}
	 
} 
*/

	echo "<script>window.location='shop-user-invoice-print.php?invoiceid=".base64_encode($invoice_id)."';</script>";
}
?>