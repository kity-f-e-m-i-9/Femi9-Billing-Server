<?php 
include("checksession.php"); 
include("config.php"); 
error_reporting(0);

// ✨ DAILY REWARD INTEGRATION - Load reward helper functions
require_once 'include/invoice-reward-integration.php';

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
	$receiptdate     = $result_invoicedetails['date'];
    $receivedamount  = ($_REQUEST['receivedamount'] != NULL) ? (float)$_REQUEST['receivedamount'] : 0;
    $receipt_method  = $_REQUEST['receipt_method'];
    $receipt_remarks = str_replace("'", "&#39;", $_REQUEST['receipt_remarks']);
    
    $usertype = $result_invoicedetails['to_user_type'];
    $userid   = $result_invoicedetails['to_user_id'];
    
    // Check if receipt row already exists for this invoice
    $checkReceipt      = "SELECT id, received FROM receipt WHERE inv_id='$invoice_id' LIMIT 1";
    $fetchCheckReceipt = mysqli_query($db_conn, $checkReceipt);
    $existingReceipt   = mysqli_fetch_array($fetchCheckReceipt);
    
    if ($existingReceipt) {
        // Receipt exists — ADD new amount to existing received, recalculate receivable
        $new_total_received  = (float)$existingReceipt['received'] + $receivedamount;
        $new_receivable      = round($total_amount - $new_total_received);
    
        $updateReceipt = "UPDATE receipt 
                          SET received        = '$new_total_received',
                              receivable      = '$new_receivable',
                              invoice_amount  = '$total_amount',
                              receipt_method  = '$receipt_method',
                              receipt_remarks = '$receipt_remarks'
                          WHERE inv_id='$invoice_id'";
        mysqli_query($db_conn, $updateReceipt);
    
    } else {
        // No receipt yet — insert fresh row
        $receivableamount = round($total_amount - $receivedamount);
    
        $insertreceipt = "INSERT INTO receipt 
                          (receiptid, inv_id, invoice_amount, received, receivable, date,
                           from_user_type, from_user_id, to_user_type, to_user_id,
                           receipt_method, receipt_remarks) 
                          VALUES 
                          ('$invoice_id','$invoice_id','$total_amount','$receivedamount',
                           '$receivableamount','$receiptdate',
                           '".$result_invoicedetails['from_user_type']."',
                           '".$result_invoicedetails['from_user_id']."',
                           '$usertype','$userid',
                           '$receipt_method','$receipt_remarks')";
        mysqli_query($db_conn, $insertreceipt);
    }
	
	
	if($_REQUEST['invoice_id']!=NULL)
	{
	$update_invoice="update user_invoice set sub_total='$SubTotal',discount='$discount',total='$total_amount',
	roundoff='$roundoff',courier_charges='$courier_charges' where inv_id='$invoice_id' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
	$invoice_update_result = mysqli_query($db_conn,$update_invoice);
	
	// ============================================================================
	// ✨ DAILY REWARD INTEGRATION - Check and award daily reward
	// ============================================================================
	
	if ($invoice_update_result) {
		// Only award rewards for NEW invoices (not when editing)
		if ($_SESSION['ACTIONEDIT'] != 'edit') {
			
			// Get invoice number from database
			$invoice_number = $result_invoicedetails['inv_number'];
			
			// Check and award daily reward
			$rewardResult = checkAndAwardDailyReward(
				$db_conn,
				$Login_user_TYPEvl,          // User type (e.g., 'super_stockiest')
				$Login_user_IDvl,            // User ID (temp_id)
				$invoice_id,                 // Invoice ID
				$invoice_number              // Invoice number
			);
			
			// Store reward result in session for notification on next page
			if (isset($rewardResult['success']) && $rewardResult['success']) {
				$_SESSION['reward_notification'] = $rewardResult;
			}
			
			// Log reward attempt for debugging (optional)
			if (!$rewardResult['success'] && !isset($rewardResult['already_rewarded'])) {
				error_log("Daily Reward Error: " . ($rewardResult['message'] ?? 'Unknown error'));
			}
		}
	}
	
	// ============================================================================
	// End of Daily Reward Integration
	// ============================================================================
	
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
	 
}*/ 

    // ============================================================================
    // STOCK UPDATE — reverse old stock on edit, then re-apply from current items
    // ============================================================================

    $is_edit_submission = (isset($_SESSION['ACTIONEDIT']) && $_SESSION['ACTIONEDIT'] === 'edit');
    require_once('include/StockService.php');
    $stockService = new StockService($db_conn);

    error_log('=== PROCESSING STOCK UPDATES ===');
    error_log('Submission type: ' . ($is_edit_submission ? 'EDIT (reverse + re-apply)' : 'NEW'));

    if ($is_edit_submission) {
        try {
            $reversed = $stockService->reverseAll('user_invoice', $invoice_id, $Login_user_IDvl);
            error_log('EDIT: reversed $reversed ledger entries for invoice $invoice_id');
        } catch (\Throwable $e) {
            error_log('EDIT: reversal warning (non-blocking): ' . $e->getMessage());
        }
    }

    try {
        $is_customer_invoice = false;
        if (!defined('INVOICE_STOCK_UPDATE_INCLUDED')) {
            define('INVOICE_STOCK_UPDATE_INCLUDED', true);
        }
        include('invoice-stock-update.php');
        error_log('Stock updates completed successfully');
    } catch (\Throwable $e) {
        error_log('CRITICAL: Stock update failed for invoice $invoice_id — ' . $e->getMessage());
        error_log('Action Required: Manual stock adjustment needed for invoice: $invoice_id');
    }

    error_log('=== STOCK UPDATES COMPLETED ===');
    
    unset($_SESSION['ACTIONEDIT']);
    
    error_log(str_repeat("=", 80));
    error_log("=== INVOICE SUBMISSION COMPLETED SUCCESSFULLY ===");
    error_log("Redirecting to invoice print: $invoice_id");
    error_log(str_repeat("=", 80));

	echo "<script>window.location='shop-user-invoice-print.php?invoiceid=".base64_encode($invoice_id)."';</script>";
}
?>