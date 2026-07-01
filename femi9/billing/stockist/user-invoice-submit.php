<?php 
include("checksession.php"); 
include("config.php");
error_reporting(1);
ini_set('display_errors','1');

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


	//
	$selectdetails="select * from user_invoice where inv_id='$invoice_id'";
	$fetchdetails=mysqli_query($db_conn,$selectdetails);
	$resultdetails=mysqli_fetch_array($fetchdetails);
	//
	$usertype=$resultdetails['to_user_type'];
	$userid=$resultdetails['to_user_id'];
	
	
	$SubTotal=$_REQUEST['SubTotal'];
	if($_REQUEST['discount']!=NULL){ $discount=$_REQUEST['discount'];}else{$discount="0";}
	$credit=$_REQUEST['credit'];
	$roundoff=$_REQUEST['roundoff'];
	$courier_charges=$_REQUEST['courier_charges'];
	
	$total_amount=$SubTotal-$discount-$credit+$courier_charges;;
	$total_amount=round($total_amount);
	
	// Use invoice date if available, otherwise use today's date
$receiptdate = !empty($result_invoicedetails['date']) 
               ? $result_invoicedetails['date'] 
               : date('Y-m-d');
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
                  ('$invoice_id', '$invoice_id', '$total_amount', '$receivedamount',
                   '$receivableamount', '$receiptdate',
                   '".$result_invoicedetails['from_user_type']."',
                   '".$result_invoicedetails['from_user_id']."',
                   '$usertype', '$userid',
                   '$receipt_method', '$receipt_remarks')";
        mysqli_query($db_conn, $insertreceipt);
    }
	
	
	if($_REQUEST['invoice_id']!=NULL)
	{
		
		if($_SESSION['INVOICEFINISH']!=NULL && $credit!=0)
		{
			if($_SESSION['ACTIONEDIT']==NULL)
			{
	//LESS CREDIT AMOUNT - IF NEW INVOICE ONLY
	/*$selectcountcredit12="select * from return_credit where usertype='$usertype' and userid='$userid'";
	$fetchcountcredit12=mysqli_query($db_conn,$selectcountcredit12);
	$resultcountcredit12=mysqli_fetch_array($fetchcountcredit12);
	$creditamount=$resultcountcredit12['credit_amount']-$credit;
		
	$updatecredit="update return_credit set credit_amount='$creditamount' where usertype='$usertype' and userid='$userid'";
	mysqli_query($db_conn,$updatecredit);*/
			}
		}
		
		unset($_SESSION['INVOICEFINISH']);
	
	//UPDATE USER-INVOICE TABLE
	$update_invoice="update user_invoice set credit='$credit',sub_total='$SubTotal',discount='$discount',total='$total_amount',roundoff='$roundoff',courier_charges='$courier_charges' where inv_id='$invoice_id' and from_user_type='$Login_user_TYPEvl' and from_user_id='$Login_user_IDvl'";
	$invoice_update_result = mysqli_query($db_conn,$update_invoice);
	
	// ============================================================================
	// ✨ DAILY REWARD INTEGRATION - Check and award daily reward
	// ============================================================================
	
	if ($invoice_update_result) {
		// Only award rewards for NEW invoices (not when editing)
		if ($_SESSION['ACTIONEDIT'] != 'edit') {
			
			// Get invoice number from database
			$invoice_number = $resultdetails['inv_number'];
			
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
    
	
	unset($_SESSION['ACTIONEDIT']);
	
	echo "<script>window.location='user-invoice-print.php?invoiceid=".base64_encode($invoice_id)."';</script>";
}
?>