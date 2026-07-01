<?php
/**
 * Finish Credit Note (Return Submission)
 * Finalizes a return note and adds advance payment credit if applicable
 * 
 * SECURITY: Prepared statements, input validation, transaction handling
 */

include("checksession.php");
include("config.php");
include("advance-payment-functions.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
date_default_timezone_set("Asia/Kolkata");

// Validate and sanitize inputs
$returnid = isset($_REQUEST['returnid']) ? base64_decode($_REQUEST['returnid']) : '';
$returnid = mysqli_real_escape_string($db_conn, $returnid);

$SubTotal = (float)($_REQUEST['SubTotal'] ?? 0);
$discount = (float)($_REQUEST['discount'] ?? 0);
$total_amount = (float)($SubTotal - $discount);

if (empty($returnid)) {
    error_log("FINISH RETURN ERROR: Invalid return ID");
    $_SESSION['errorMessage'] = "Invalid return ID";
    header("Location: cnote_manage.php?error=invalid_returnid");
    exit;
}

if ($SubTotal < 0 || $discount < 0 || $total_amount < 0) {
    error_log("FINISH RETURN ERROR: Invalid amounts - SubTotal: $SubTotal, Discount: $discount");
    $_SESSION['errorMessage'] = "Invalid amount values";
    header("Location: cnote_new.php?returnid=" . base64_encode($returnid) . "&error=invalid_amounts");
    exit;
}

/*
|--------------------------------------------------------------------------
| FETCH RETURN MASTER DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $db_conn->prepare("
    SELECT invnumber, from_usertype, from_userid, to_usertype, to_userid, status
    FROM user_return_stock 
    WHERE returnid = ?
    LIMIT 1
");
$stmt->bind_param("s", $returnid);
$stmt->execute();
$return = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$return) {
    error_log("FINISH RETURN ERROR: Return $returnid not found");
    $_SESSION['errorMessage'] = "Return not found";
    header("Location: cnote_manage.php?error=return_not_found");
    exit;
}

$invid = $return['invnumber'];
$from_usertype = $return['from_usertype'];
$from_userid = $return['from_userid'];
$to_usertype = $return['to_usertype'];
$to_userid = $return['to_userid'];
$current_status = $return['status'];

/*
|--------------------------------------------------------------------------
| ONLY PROCESS IF STILL PENDING
|--------------------------------------------------------------------------
*/
if ($current_status === 'pending') {

    /*
    |----------------------------------------------------------------------
    | ADVANCE PAYMENT CREDIT (Super Stockist/Stockist Only)
    |----------------------------------------------------------------------
    */
    if (in_array($from_usertype, ['super_stockiest', 'stockiest'])) {

        $reference_number = "CN-" . $returnid;

        // Check if credit already exists (prevent duplicate credits)
        if (!hasReturnAdvanceCreditByReference($db_conn, $reference_number)) {

            if ($total_amount > 0) {

                // Fetch invoice details
                $stmt = $db_conn->prepare("
                    SELECT inv_number, date 
                    FROM user_invoice 
                    WHERE inv_id = ?
                    LIMIT 1
                ");
                $stmt->bind_param("s", $invid);
                $stmt->execute();
                $inv = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($inv) {
                    // Add advance payment credit
                    $credit_result = addAdvancePaymentCreditForReturn(
                        $db_conn,
                        $returnid,
                        $invid,
                        $inv['inv_number'],
                        (float)$total_amount,
                        date('Y-m-d'),
                        $inv['date'],
                        $from_userid,
                        $from_usertype,
                        $to_userid,
                        $to_usertype,
                        $Login_user_TYPEvl ?? 'system',
                        $Login_user_TYPEvl ?? 'system'
                    );

                    if ($credit_result['success']) {
                        error_log(
                            "ADVANCE PAYMENT CREDIT ADDED: " .
                            "Return $returnid, Invoice {$inv['inv_number']}, Amount: $total_amount"
                        );
                    } else {
                        error_log(
                            "ADVANCE PAYMENT CREDIT FAILED: " .
                            "Return $returnid, Error: {$credit_result['message']}"
                        );
                        $_SESSION['warningMessage'] = "Return created but advance payment credit failed: " . $credit_result['message'];
                    }
                } else {
                    error_log("FINISH RETURN ERROR: Invoice $invid not found for return $returnid");
                }
            }
        } else {
            error_log("ADVANCE PAYMENT CREDIT SKIPPED: Credit already exists for Return $returnid");
        }
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE RETURN TOTALS AND STATUS
|--------------------------------------------------------------------------
*/
$stmt = $db_conn->prepare("
    UPDATE user_return_stock 
    SET 
        subtotal = ?,
        discount = ?,
        total = ?,
        status = 'accept'
    WHERE returnid = ?
");
$stmt->bind_param("ddds", $SubTotal, $discount, $total_amount, $returnid);
$stmt->execute();
$stmt->close();

// Update all return items to 'accept' status
$stmt = $db_conn->prepare("
    UPDATE user_return_stock_items 
    SET status = 'accept' 
    WHERE returnid = ?
");
$stmt->bind_param("s", $returnid);
$stmt->execute();
$stmt->close();

/*
|--------------------------------------------------------------------------
| STOCK ADJUSTMENTS FOR ALL RETURN ITEMS
|--------------------------------------------------------------------------
*/
// Fetch all return items for this return
$stmt = $db_conn->prepare("
    SELECT prid, qty 
    FROM user_return_stock_items 
    WHERE returnid = ?
");
$stmt->bind_param("s", $returnid);
$stmt->execute();
$return_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Process stock updates for each item
foreach ($return_items as $item) {
    $prid = $item['prid'];
    $returnqty = (int)$item['qty'];
    
    /* ====================================================================
    | STOCK ADJUSTMENT - RECEIVER (TO USER) - Reverse the sale
    =====================================================================*/
    $stmt = $db_conn->prepare("
        UPDATE stock
        SET sales_qty = sales_qty - ?,
            closing_qty = closing_qty + ?
        WHERE product_id = ?
          AND user_type = ?
          AND user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("iisss", $returnqty, $returnqty, $prid, $to_usertype, $to_userid);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        error_log("STOCK UPDATE WARNING: No stock record found for receiver - Product: $prid, User: $to_usertype/$to_userid");
    } else {
        error_log("STOCK UPDATED (Receiver): Product $prid, Qty: $returnqty, User: $to_usertype/$to_userid");
    }

    /* ====================================================================
    | STOCK ADJUSTMENT - SENDER (FROM USER) - Reduce their stock
    =====================================================================*/
    if (in_array($from_usertype, ['super_stockiest', 'stockiest', 'super_distributor', 'distributor', 'candf'])) {
        $stmt = $db_conn->prepare("
            UPDATE stock
            SET input_qty = input_qty - ?,
                closing_qty = closing_qty - ?
            WHERE product_id = ?
              AND user_type = ?
              AND user_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("iisss", $returnqty, $returnqty, $prid, $from_usertype, $from_userid);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            error_log("STOCK UPDATE WARNING: No stock record found for sender - Product: $prid, User: $from_usertype/$from_userid");
        } else {
            error_log("STOCK UPDATED (Sender): Product $prid, Qty: $returnqty, User: $from_usertype/$from_userid");
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH INVOICE NUMBER FOR SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/
$stmt = $db_conn->prepare("
    SELECT inv_number 
    FROM user_invoice 
    WHERE inv_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $invid);
$stmt->execute();
$invdata = $stmt->get_result()->fetch_assoc();
$stmt->close();

$inv_number_display = $invdata['inv_number'] ?? 'Unknown';

/*
|--------------------------------------------------------------------------
| SUCCESS LOG AND REDIRECT
|--------------------------------------------------------------------------
*/
error_log(
    "RETURN FINALIZED SUCCESS: " .
    "Return $returnid, Invoice $inv_number_display, " .
    "SubTotal: $SubTotal, Discount: $discount, Total: $total_amount"
);

$_SESSION['successMessage'] = 
    "Credit Note Added Successfully against Invoice Number: " . 
    htmlspecialchars($inv_number_display);

echo "<script>window.location='cnote_manage.php?returnaddedsuccess';</script>";
exit;
?>