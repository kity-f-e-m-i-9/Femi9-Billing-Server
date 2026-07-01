<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ✨ DAILY REWARD INTEGRATION - Load reward helper functions

if(isset($_REQUEST['invoice-submit']))
{
	$invoice_id=$_REQUEST['invoice_id'];
	
	$selectdetails="select * from invoice where inv_id='$invoice_id'";
	$fetchdetails=mysqli_query($db_conn,$selectdetails);
	$resultdetails=mysqli_fetch_array($fetchdetails);
	$Login_user_IDvl=$resultdetails['user_id']; 
	
///UPDATE INVOCIE - IF EDIT INVOICE ACTIO ONLY	
if(isset($_REQUEST['update_invoice_date']) && $_REQUEST['update_invoice_date'] != NULL){
$update_invoice_date=date("Y-m-d",strtotime($_REQUEST['update_invoice_date']));

//1
$update_invoice12="update invoice set date='$update_invoice_date' where inv_id='$invoice_id' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
mysqli_query($db_conn,$update_invoice12);

//2	
$update_invoice_ITEMS12="update invoice_items set date='$update_invoice_date' where inv_id='$invoice_id' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
mysqli_query($db_conn,$update_invoice_ITEMS12);

	}
//--------------------------------------------
	
	
	$SubTotal=$_REQUEST['sub_total'];
	if($_REQUEST['discount']!=NULL){ $discount=$_REQUEST['discount'];}else{$discount="0";}
	$roundoff=$_REQUEST['roundoff'];
	$courier_charges=$_REQUEST['courier_charges'];
	
	$total_amount=$SubTotal-$discount+$courier_charges;
	$total_amount=round($total_amount);
	
	
	//DELETE RECEIPT DETAILS IF EDIT FUNCTION
	if($_SESSION['ACTIONEDIT']=="edit"){
		
		$delReceipt="delete from receipt where inv_id='$invoice_id'";
		mysqli_query($db_conn,$delReceipt);
	}
	
	
	//INSERT RECEIPT DETAILS
	$insertreceiptcount="select count(*) as numreceipt from receipt where receiptid='$invoice_id'";
	$fetchreceipt=mysqli_query($db_conn,$insertreceiptcount);
	$resultreceipt=mysqli_fetch_array($fetchreceipt);
	if($resultreceipt['numreceipt']==0)
	{
		$receiptdate=$resultdetails['date'];
		
		if($_REQUEST['receivedamount']!=NULL)
		{
		$receivedamount=$_REQUEST['receivedamount'];
		}
		else
		{$receivedamount="0";}
	
		$receivableamount=$total_amount-$receivedamount;
		$receivableamount=round($receivableamount);
		
		$usertype="customer";
		$userid=$resultdetails['customer_id'];
		
		$receipt_method=$_REQUEST['receipt_method'];
		$receipt_remarks=str_replace("'","&#39;",$_REQUEST['receipt_remarks']);
		
		$insertreceipt="insert into receipt (receiptid,inv_id,invoice_amount,received,receivable,date,from_user_type,
		from_user_id,to_user_type,to_user_id,receipt_method,receipt_remarks) 
		values 
		('$invoice_id','$invoice_id','$total_amount','$receivedamount','$receivableamount','$receiptdate','".$resultdetails['user_type']."','".$resultdetails['user_id']."','$usertype','$userid','$receipt_method','$receipt_remarks')";
		mysqli_query($db_conn,$insertreceipt);
	}
	//-------------------------------------
	
	
	if($_REQUEST['invoice_id']!=NULL)
	{
		$update_invoice="update invoice set sub_total='$SubTotal',discount='$discount',
	total='$total_amount',roundoff='$roundoff',courier_charges='$courier_charges' where inv_id='$invoice_id' and user_type='$Login_user_TYPEvl' and user_id='$Login_user_IDvl'";
	$invoice_update_result = mysqli_query($db_conn,$update_invoice);
	

	}

    // ============================================================================
    // STEP 8: UPDATE STOCK — reverse old stock on edit, then re-apply from current items
    // ============================================================================

    $is_edit_submission = (isset($_SESSION['ACTIONEDIT']) && $_SESSION['ACTIONEDIT'] === 'edit');
    $stockService = new StockService($db_conn);

    error_log("=== PROCESSING STOCK UPDATES ===");
    error_log("Submission type: " . ($is_edit_submission ? 'EDIT (reverse + re-apply)' : 'NEW'));

    if ($is_edit_submission) {
        try {
            $reversed = $stockService->reverseAll('invoice', $invoice_id, $Login_user_IDvl);
            error_log("CUSTOMER EDIT: reversed $reversed ledger entries for invoice $invoice_id");
        } catch (\Throwable $e) {
            error_log("CUSTOMER EDIT: reversal warning (non-blocking): " . $e->getMessage());
        }
    }

    try {
        $is_customer_invoice = true;
        if (!defined('INVOICE_STOCK_UPDATE_INCLUDED')) {
            define('INVOICE_STOCK_UPDATE_INCLUDED', true);
        }
        include("invoice-stock-update.php");
        error_log("Stock updates completed successfully");
    } catch (\Throwable $e) {
        error_log("CRITICAL: Stock update failed for invoice $invoice_id — " . $e->getMessage());
        error_log("Action Required: Manual stock adjustment needed for invoice: $invoice_id");
    }

    error_log("=== STOCK UPDATES COMPLETED ===");
    
    unset($_SESSION['ACTIONEDIT']);
    
    error_log(str_repeat("=", 80));
    error_log("=== INVOICE SUBMISSION COMPLETED SUCCESSFULLY ===");
    error_log("Redirecting to invoice print: $invoice_id");
    error_log(str_repeat("=", 80));
	
	echo "<script>window.location='customer-user-invoice-print.php?invoiceid=".base64_encode($invoice_id)."';</script>";
}
?>