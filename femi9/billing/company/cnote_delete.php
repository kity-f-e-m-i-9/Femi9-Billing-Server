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
require_once("include/StockService.php");

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

// Which physical warehouse the original sale drew from, if recorded — see
// cnote_finish.php for why this is always NULL today (invoice creation
// isn't wired to populate it yet) and why it defaults to G1 (warehouse id
// 2) rather than the unassigned bucket. Must match cnote_finish.php's
// default exactly — this file undoes exactly what that one credited.
$warehouseId = null;
if ($to_usertype === 'company') {
    $warehouseId = 2; // Default: G1
    if ($from_usertype === 'customer') {
        $stmt = $db_conn->prepare("SELECT warehouse_id FROM invoice WHERE inv_id = ? LIMIT 1");
    } else {
        $stmt = $db_conn->prepare("SELECT warehouse_id FROM user_invoice WHERE inv_id = ? LIMIT 1");
    }
    $stmt->bind_param("s", $invnumber);
    $stmt->execute();
    $whRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($whRow && $whRow['warehouse_id'] !== null) {
        $warehouseId = (int) $whRow['warehouse_id'];
    }
}

/*
|--------------------------------------------------------------------------
| FETCH RETURN ITEM DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $db_conn->prepare("
    SELECT prid, qty, total, status
    FROM user_return_stock_items
    WHERE id = ? AND deleted_at IS NULL
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

// Stock is only ever moved for an item once cnote_finish.php has run and
// flipped its status to 'accept' (see cnote_finish.php's StockService
// calls, gated the same way on the header's status='pending' check). A
// 'pending' item never touched stock, so reversing it here would subtract
// stock that was never credited — the exact bug that drove company stock
// negative when never-finished returns were deleted.
$item_status = $item_details['status'] ?? 'pending';
$stockWasApplied = ($item_status === 'accept');

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
    | Reverse the stock adjustment made when this item was finished/accepted.
    | Skipped entirely for a 'pending' item — cnote_finish.php never ran for
    | it, so no stock was ever moved to reverse.
    |----------------------------------------------------------------------
    */
    if ($stockWasApplied) {
    // Scoped to the exact warehouse row cnote_finish.php credited (G1 by
    // default for a company receiver, NULL/unassigned for any other — see
    // warehouseId note above). Uses StockService::reverseDeduct(), falling
    // back to credit() (which creates the row via INSERT ... ON DUPLICATE
    // KEY) if no stock row exists yet for this exact (product, warehouse) —
    // e.g. G1 never had this product before — so the qty is never silently
    // lost the way the old raw UPDATE (0 affected_rows, only logged) could.
    $stockService = new StockService($db_conn);
    $reverseResult = $stockService->reverseDeduct(
        $prid, $to_usertype, $to_userid, $returnqty,
        'return', $returnid_decode, $Login_user_TYPEvl ?? 'system',
        true, // externalTransaction
        $warehouseId
    );
    if (empty($reverseResult['success'])) {
        $stockService->credit(
            $prid, $to_usertype, $to_userid, $returnqty,
            'return', $returnid_decode, $Login_user_TYPEvl ?? 'system',
            true, // externalTransaction
            $warehouseId
        );
    }

    /*
    |----------------------------------------------------------------------
    | STOCK REVERSAL - SENDER (FROM USER)
    | For B2B users, reverse the stock reduction
    |----------------------------------------------------------------------
    */
    if (in_array($from_usertype, ['super_stockiest', 'stockiest', 'super_distributor', 'distributor', 'candf'])) {

        // Reverse of cnote_finish.php's reverseCredit() on this same sender —
        // via StockService so this is ledger-audited and scoped consistently
        // with every other write to this account's stock, instead of a raw
        // UPDATE with no warehouse_id and no audit trail.
        $stockService->credit(
            $prid, $from_usertype, $from_userid, $returnqty,
            'return', $returnid_decode, $Login_user_TYPEvl ?? 'system',
            true // externalTransaction
        );
    }
    } else {
        error_log("STOCK REVERSAL SKIPPED: Item $rowid_decode was still 'pending' (never finished) - no stock movement to reverse");
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
        WHERE returnid = ? AND id != ? AND deleted_at IS NULL
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
    | SOFT-DELETE THE RETURN ITEM
    | Never hard-DELETE — this row is the only record of what was returned
    | and what stock adjustment (if any) was reversed above. A raw DELETE
    | here previously destroyed that trail entirely, making an incident
    | like the one this soft-delete closes unrecoverable after the fact.
    |----------------------------------------------------------------------
    */
    $stmt = $db_conn->prepare("UPDATE user_return_stock_items SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
    $deletedBy = $_SESSION['LOGIN_USER'] ?? ($Login_user_TYPEvl ?? 'system');
    $stmt->bind_param("ss", $deletedBy, $rowid_decode);
    $stmt->execute();
    $stmt->close();

    /*
    |----------------------------------------------------------------------
    | RECALCULATE HEADER TOTALS FROM REMAINING ITEMS
    | Without this, user_return_stock.total/subtotal keeps whatever value
    | was last written at Finish time, drifting out of sync with the items
    | that actually remain — the bug this fix closes.
    |----------------------------------------------------------------------
    */
    $stmt = $db_conn->prepare("SELECT COALESCE(SUM(total),0) AS remaining_total FROM user_return_stock_items WHERE returnid = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $returnid_decode);
    $stmt->execute();
    $remaining_total = (float)$stmt->get_result()->fetch_assoc()['remaining_total'];
    $stmt->close();

    $stmt = $db_conn->prepare("SELECT discount FROM user_return_stock WHERE returnid = ? LIMIT 1");
    $stmt->bind_param("s", $returnid_decode);
    $stmt->execute();
    $existing_discount = (float)($stmt->get_result()->fetch_assoc()['discount'] ?? 0);
    $stmt->close();

    $new_total = max(0, $remaining_total - $existing_discount);

    $stmt = $db_conn->prepare("UPDATE user_return_stock SET subtotal = ?, total = ? WHERE returnid = ?");
    $stmt->bind_param("dds", $remaining_total, $new_total, $returnid_decode);
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