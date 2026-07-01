<?php
/**
 * Delete Single Return Item - Stockist Version
 * Deletes one product from return and reverses stock if already finalized
 */

include("checksession.php");
include("config.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
date_default_timezone_set("Asia/Kolkata");

$returnid = isset($_REQUEST['returnid']) ? base64_decode($_REQUEST['returnid']) : '';
$returnid = mysqli_real_escape_string($db_conn, $returnid);

$rowid = isset($_REQUEST['rowid']) ? base64_decode($_REQUEST['rowid']) : '';
$rowid = mysqli_real_escape_string($db_conn, $rowid);

if (empty($returnid) || empty($rowid)) {
    error_log("DELETE ITEM ERROR: Invalid parameters");
    header("Location: cnote_manage.php?error=invalid_params");
    exit;
}

/*
|--------------------------------------------------------------------------
| FETCH RETURN DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $db_conn->prepare("
    SELECT from_usertype, from_userid, to_usertype, to_userid, status
    FROM user_return_stock 
    WHERE returnid = ?
    LIMIT 1
");
$stmt->bind_param("s", $returnid);
$stmt->execute();
$return = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$return) {
    error_log("DELETE ITEM ERROR: Return $returnid not found");
    header("Location: cnote_manage.php?error=return_not_found");
    exit;
}

$from_usertype = $return['from_usertype'];
$from_userid = $return['from_userid'];
$to_usertype = $return['to_usertype'];
$to_userid = $return['to_userid'];
$return_status = $return['status'];

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
$stmt->bind_param("s", $rowid);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    error_log("DELETE ITEM ERROR: Item $rowid not found");
    header("Location: cnote_manage.php?error=item_not_found");
    exit;
}

$prid = $item['prid'];
$returnqty = (int)$item['qty'];
$return_amount = (float)$item['total'];

/*
|--------------------------------------------------------------------------
| REVERSE STOCK IF RETURN WAS ALREADY FINALIZED
|--------------------------------------------------------------------------
*/
if ($return_status === 'accept' || $return_status === 'completed') {
    
    /* ====================================================================
    | STOCK REVERSAL - RECEIVER (TO USER)
    =====================================================================*/
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
        error_log("STOCK REVERSAL WARNING: No stock record found for receiver - Product: $prid");
    } else {
        error_log("STOCK REVERSED (Receiver): Product $prid, Qty: $returnqty");
    }

    /* ====================================================================
    | STOCK REVERSAL - SENDER (FROM USER)
    =====================================================================*/
    if (in_array($from_usertype, ['super_stockiest', 'stockiest', 'super_distributor', 'distributor'])) {
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
            error_log("STOCK REVERSAL WARNING: No stock record found for sender - Product: $prid");
        } else {
            error_log("STOCK REVERSED (Sender): Product $prid, Qty: $returnqty");
        }
    }
}

/*
|--------------------------------------------------------------------------
| DELETE RETURN ITEM
|--------------------------------------------------------------------------
*/
$stmt = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE id = ?");
$stmt->bind_param("s", $rowid);
$stmt->execute();
$stmt->close();

error_log("RETURN ITEM DELETED: Return $returnid, Item $rowid, Product $prid, Qty: $returnqty");

/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/
$encoded_returnid = base64_encode($returnid);

if (isset($_REQUEST['redirurl']) && $_REQUEST['redirurl'] == 'cnote_details') {
    header("Location: cnote_details.php?returnid=$encoded_returnid&DeleteSuccess");
} else {
    $encoded_invid = $_REQUEST['InvoiceID'] ?? '';
    header("Location: cnote_new.php?returnid=$encoded_returnid&InvoiceID=$encoded_invid&DeleteSuccess");
}
exit;
?>