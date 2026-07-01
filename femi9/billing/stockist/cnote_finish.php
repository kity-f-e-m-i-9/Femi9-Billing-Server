<?php
/**
 * Finish Credit Note - Stockist Version
 * Finalizes return and updates stock
 * NO advance payment for stockist
 */

include("checksession.php");
include("config.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
date_default_timezone_set("Asia/Kolkata");

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
    error_log("FINISH RETURN ERROR: Invalid amounts");
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
| STOCK ADJUSTMENTS FOR ALL RETURN ITEMS (HAPPENS ONLY AT FINISH)
|--------------------------------------------------------------------------
*/
$stmt = $db_conn->prepare("
    SELECT prid, qty 
    FROM user_return_stock_items 
    WHERE returnid = ?
");
$stmt->bind_param("s", $returnid);
$stmt->execute();
$return_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
    if (in_array($from_usertype, ['super_stockiest', 'stockiest', 'super_distributor', 'distributor'])) {
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
$invoice_table = ($from_usertype === 'customer') ? 'invoice' : 'user_invoice';
$stmt = $db_conn->prepare("
    SELECT inv_number 
    FROM $invoice_table 
    WHERE inv_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $invid);
$stmt->execute();
$invdata = $stmt->get_result()->fetch_assoc();
$stmt->close();

$inv_number_display = $invdata['inv_number'] ?? 'Unknown';

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