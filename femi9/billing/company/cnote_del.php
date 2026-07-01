<?php 
/**
 * Delete Incomplete Return (Credit Note)
 * Deletes an incomplete return and reverses advance payment credit if applicable
 * 
 * SECURITY: Prepared statements, input validation, CSRF protection
 */

include("checksession.php");
include("config.php");
include("advance-payment-functions.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
date_default_timezone_set("Asia/Kolkata");

// Validate and sanitize input
$returnid = $_REQUEST['returnid'] ?? '';
$returnid_decode = base64_decode($returnid);
$returnid_decode = mysqli_real_escape_string($db_conn, $returnid_decode);

if (empty($returnid_decode)) {
    error_log("DELETE RETURN ERROR: Invalid return ID");
    header("Location: cnote_manage.php?error=invalid_returnid");
    exit;
}

// Get return details before deletion
$stmt = $db_conn->prepare("
    SELECT from_usertype, invnumber, status 
    FROM user_return_stock 
    WHERE returnid = ?
    LIMIT 1
");
$stmt->bind_param("s", $returnid_decode);
$stmt->execute();
$result = $stmt->get_result();
$result_return_details = $result->fetch_assoc();
$stmt->close();

if (!$result_return_details) {
    error_log("DELETE RETURN ERROR: Return ID $returnid_decode not found");
    header("Location: cnote_manage.php?error=return_not_found");
    exit;
}

$from_usertype = $result_return_details['from_usertype'];
$invnumber = $result_return_details['invnumber'];
$status = $result_return_details['status'];

/*
|--------------------------------------------------------------------------
| REVERSE ADVANCE PAYMENT CREDIT (Super Stockist/Stockist only)
|--------------------------------------------------------------------------
*/
if (in_array($from_usertype, ['super_stockiest', 'stockiest'])) {
    
    // Get invoice number for reversal
    $stmt = $db_conn->prepare("
        SELECT inv_number 
        FROM user_invoice 
        WHERE inv_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $invnumber);
    $stmt->execute();
    $inv_result = $stmt->get_result();
    $result_inv_number = $inv_result->fetch_assoc();
    $stmt->close();

    if ($result_inv_number) {
        $inv_number_display = $result_inv_number['inv_number'];
        $deletion_date = date("Y-m-d");
        $reason = "Incomplete return deleted - Status: $status";

        // Reverse advance payment credit
        $reversal_result = reverseAdvancePaymentCreditForReturn(
            $db_conn,
            $returnid_decode,
            $inv_number_display,
            $deletion_date,
            $Login_user_TYPEvl ?? 'system',
            $Login_user_TYPEvl ?? 'system',
            $reason
        );

        if ($reversal_result['success']) {
            error_log(
                "ADVANCE PAYMENT REVERSAL SUCCESS (Incomplete Return): " .
                "Return ID $returnid_decode, Amount: {$reversal_result['reversed_amount']}"
            );
        } else {
            error_log(
                "ADVANCE PAYMENT REVERSAL FAILED (Incomplete Return): " .
                "Return ID $returnid_decode, Error: {$reversal_result['message']}"
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| DELETE RETURN ITEMS AND RECORD
|--------------------------------------------------------------------------
*/
// Start transaction for data integrity
mysqli_begin_transaction($db_conn);

try {
    // Delete return items
    $stmt = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE returnid = ?");
    $stmt->bind_param("s", $returnid_decode);
    $stmt->execute();
    $items_deleted = $stmt->affected_rows;
    $stmt->close();

    // Delete return record
    $stmt = $db_conn->prepare("DELETE FROM user_return_stock WHERE returnid = ?");
    $stmt->bind_param("s", $returnid_decode);
    $stmt->execute();
    $stmt->close();

    // Commit transaction
    mysqli_commit($db_conn);

    $_SESSION['successMessage'] = "Incomplete Return (Credit Note) Deleted Successfully!";
    
    error_log("RETURN DELETED SUCCESS: Return ID $returnid_decode, Items Deleted: $items_deleted");
    
    echo "<script>window.location='cnote_manage.php?DeleteSuccess';</script>";
    
} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($db_conn);
    
    error_log("DELETE RETURN ERROR: " . $e->getMessage());
    $_SESSION['errorMessage'] = "Failed to delete return: " . $e->getMessage();
    
    echo "<script>window.location='cnote_manage.php?error=delete_failed';</script>";
}

exit;
?>