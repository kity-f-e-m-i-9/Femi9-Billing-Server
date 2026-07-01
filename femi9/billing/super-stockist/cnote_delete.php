<?php 
/**
 * Delete Individual Return Item
 * Removes an item from a return note and handles stock/payment reversal
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
$returnid = $_REQUEST['returnid'] ?? '';
$returnid_decode = base64_decode($returnid);
$returnid_decode = mysqli_real_escape_string($db_conn, $returnid_decode);

$rowid = $_REQUEST['rowid'] ?? '';
$rowid_decode = base64_decode($rowid);
$rowid_decode = mysqli_real_escape_string($db_conn, $rowid_decode);

$redirurl = $_REQUEST['redirurl'] ?? 'cnote_new';
$InvoiceID = $_REQUEST['InvoiceID'] ?? '';

if (empty($returnid_decode) || empty($rowid_decode)) {
    error_log("DELETE ITEM ERROR: Invalid returnid or rowid");
    header("Location: cnote_manage.php?error=invalid_parameters");
    exit;
}

/*
|--------------------------------------------------------------------------
| FETCH RETURN DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $db_conn->prepare("
    SELECT from_usertype, from_userid, to_usertype, to_userid, invnumber
    FROM user_return_stock 
    WHERE returnid = ?
    LIMIT 1
");
$stmt->bind_param("s", $returnid_decode);
$stmt->execute();
$return_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$return_details) {
    error_log("DELETE ITEM ERROR: Return $returnid_decode not found");
    header("Location: cnote_manage.php?error=return_not_found");
    exit;
}

$from_usertype = $return_details['from_usertype'];
$from_userid = $return_details['from_userid'];
$to_usertype = $return_details['to_usertype'];
$to_userid = $return_details['to_userid'];
$invnumber = $return_details['invnumber'];

/*
|--------------------------------------------------------------------------
| FETCH RETURN ITEM DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $db_conn->prepare("
    SELECT prid, qty, total 
    FROM user_return_stock_items 
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("s", $rowid_decode);
$stmt->execute();
$item_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item_details || empty($item_details['prid'])) {
    error_log("DELETE ITEM ERROR: Item $rowid_decode not found or invalid");
    
    if ($redirurl == "cnote_details") {
        echo "<script>window.location='cnote_details.php?returnid=$returnid&&error=item_not_found';</script>";
    } else {
        echo "<script>window.location='cnote_new.php?returnid=$returnid&&InvoiceID=$InvoiceID&&error=item_not_found';</script>";
    }
    exit;
}

$prid = $item_details['prid'];
$returnqty = (int)$item_details['qty'];
$return_amount = (float)$item_details['total'];

/*
|--------------------------------------------------------------------------
| START TRANSACTION
|--------------------------------------------------------------------------
*/
mysqli_begin_transaction($db_conn);

try {
    /*
    |----------------------------------------------------------------------
    | STOCK REVERSAL - RECEIVER (TO USER)
    | Reverse the stock adjustment made during return item creation
    |----------------------------------------------------------------------
    */
    $stmt = $db_conn->prepare("
        UPDATE stock
        SET sales_qty = sales_qty + ?,
            closing_qty = closing_qty - ?
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
        error_log("STOCK REVERSAL WARNING: No stock record found for receiver - Product: $prid, User: $to_usertype/$to_userid");
    }

    /*
    |----------------------------------------------------------------------
    | STOCK REVERSAL - SENDER (FROM USER)
    | For B2B users, reverse the stock reduction
    |----------------------------------------------------------------------
    */
    if (in_array($from_usertype, ['super_stockiest', 'stockiest', 'super_distributor', 'distributor', 'candf'])) {
        
        $stmt = $db_conn->prepare("
            UPDATE stock
            SET input_qty = input_qty + ?,
                closing_qty = closing_qty + ?
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
            error_log("STOCK REVERSAL WARNING: No stock record found for sender - Product: $prid, User: $from_usertype/$from_userid");
        }
    }

    /*
    |----------------------------------------------------------------------
    | CHECK IF THIS IS THE LAST ITEM IN RETURN
    | If yes, reverse advance payment credit
    |----------------------------------------------------------------------
    */
    $stmt = $db_conn->prepare("
        SELECT COUNT(*) AS remaining_items 
        FROM user_return_stock_items 
        WHERE returnid = ? AND id != ?
    ");
    $stmt->bind_param("ss", $returnid_decode, $rowid_decode);
    $stmt->execute();
    $remaining_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $remaining_items_count = (int)$remaining_result['remaining_items'];

    /*
    |----------------------------------------------------------------------
    | REVERSE ADVANCE PAYMENT CREDIT (if last item)
    |----------------------------------------------------------------------
    */
    if ($remaining_items_count === 0 && in_array($from_usertype, ['super_stockiest', 'stockiest'])) {
        
        // Get invoice number for reversal
        $stmt = $db_conn->prepare("
            SELECT inv_number 
            FROM user_invoice 
            WHERE inv_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $invnumber);
        $stmt->execute();
        $inv_result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($inv_result) {
            $inv_number_display = $inv_result['inv_number'];
            $deletion_date = date("Y-m-d");
            $reason = "Return item deleted - Last item removed";

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
                    "ADVANCE PAYMENT REVERSAL SUCCESS (Last Item Deleted): " .
                    "Return ID $returnid_decode, Amount: {$reversal_result['reversed_amount']}"
                );
            } else {
                error_log(
                    "ADVANCE PAYMENT REVERSAL FAILED (Last Item Deleted): " .
                    "Return ID $returnid_decode, Error: {$reversal_result['message']}"
                );
            }
        }
    }

    /*
    |----------------------------------------------------------------------
    | DELETE THE RETURN ITEM
    |----------------------------------------------------------------------
    */
    $stmt = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE id = ?");
    $stmt->bind_param("s", $rowid_decode);
    $stmt->execute();
    $stmt->close();

    // Commit transaction
    mysqli_commit($db_conn);

    error_log("RETURN ITEM DELETED SUCCESS: Return $returnid_decode, Item $rowid_decode, Product $prid, Qty $returnqty");

} catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($db_conn);
    
    error_log("DELETE ITEM ERROR: " . $e->getMessage());
    $_SESSION['errorMessage'] = "Failed to delete item: " . $e->getMessage();
}

/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/
if ($redirurl == "cnote_details") {
    echo "<script>window.location='cnote_details.php?returnid=$returnid&&DeleteSuccess';</script>";
} else {
    echo "<script>window.location='cnote_new.php?returnid=$returnid&&InvoiceID=$InvoiceID&&DeleteSuccess';</script>";
}

exit;
?>