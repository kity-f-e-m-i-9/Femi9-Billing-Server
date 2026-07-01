<?php
/**
 * Credit Note Action Handler - Stockist Version
 * Handles adding return items WITHOUT stock adjustments (stock updates happen in finish)
 * NO advance payment for stockist
 */

include("checksession.php");
include("config.php");
include("return-validation-functions.php"); // Validation helpers only

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
date_default_timezone_set("Asia/Kolkata");

/*
|--------------------------------------------------------------------------
| ADD RETURN ITEM (NO STOCK ADJUSTMENT - ONLY VALIDATION)
|--------------------------------------------------------------------------
*/
if (isset($_REQUEST['add-return'])) {

    error_log("=== STOCKIST ADD RETURN STARTED ===");

    // Sanitize and validate inputs
    $from_usertype = mysqli_real_escape_string($db_conn, trim($_REQUEST['from_usertype'] ?? ''));
    $from_userid   = mysqli_real_escape_string($db_conn, trim($_REQUEST['from_userid'] ?? ''));
    $to_usertype   = mysqli_real_escape_string($db_conn, trim($_REQUEST['to_usertype'] ?? ''));
    $to_userid     = mysqli_real_escape_string($db_conn, trim($_REQUEST['to_userid'] ?? ''));
    $returnid      = mysqli_real_escape_string($db_conn, trim($_REQUEST['returnid'] ?? ''));
    $invid         = mysqli_real_escape_string($db_conn, trim($_REQUEST['invid'] ?? ''));
    $invnumber     = $invid;
    $prid          = mysqli_real_escape_string($db_conn, trim($_REQUEST['prid'] ?? ''));
    $returnqty     = (int)($_REQUEST['returnqty'] ?? 0);
    $damaged_qty   = (int)($_REQUEST['damaged_qty'] ?? 0);

    // Validate required fields
    if (empty($from_usertype) || empty($from_userid) || empty($to_usertype) || 
        empty($to_userid) || empty($returnid) || empty($invid) || empty($prid)) {
        error_log("VALIDATION FAILED: Missing fields");
        header("Location: cnote_new.php?error=missing_fields");
        exit;
    }

    if ($returnqty <= 0) {
        error_log("VALIDATION FAILED: Invalid quantity - $returnqty");
        header("Location: cnote_new.php?error=invalid_quantity");
        exit;
    }

    /* ========================================================================
    | CHECK AVAILABLE QUANTITY FOR RETURN
    ========================================================================*/
    $availability = getReturnAvailability($db_conn, $invid, $prid, $from_usertype, $returnid);
    
    if ($availability['error']) {
        error_log("RETURN ERROR: Product $prid not found in invoice $invid");
        header("Location: cnote_new.php?error=product_not_in_invoice");
        exit;
    }
    
    if ($returnqty > $availability['available_qty']) {
        error_log(
            "RETURN QTY EXCEEDED: Invoice $invid, Product $prid, " .
            "Requested: $returnqty, Available: {$availability['available_qty']}, " .
            "Original: {$availability['original_qty']}, Already Returned: {$availability['returned_qty']}"
        );
        
        $encoded_returnid = base64_encode($returnid);
        $encoded_invid = base64_encode($invnumber);
        header(
            "Location: cnote_new.php?returnid=$encoded_returnid&InvoiceID=$encoded_invid" .
            "&invalidqty&available={$availability['available_qty']}" .
            "&requested=$returnqty&already_returned={$availability['returned_qty']}"
        );
        exit;
    }

    /* ========================================================================
    | USER TABLE MAPPING
    ========================================================================*/
    $table_map = [
        'super_stockiest' => 'super_stockiest',
        'stockiest' => 'stockiest',
        'super_distributor' => 'super_distributor',
        'distributor' => 'distributor',
        'outlet' => 'outlet',
        'shop' => 'shop',
        'customer' => 'customers'
    ];

    $table = $table_map[$from_usertype] ?? 'customers';
    
    if ($table === 'customers') {
        $stmt = $db_conn->prepare("SELECT gstin FROM $table WHERE id = ? LIMIT 1");
        $stmt->bind_param("s", $from_userid);
    } else {
        $stmt = $db_conn->prepare("SELECT gstin FROM $table WHERE temp_id = ? LIMIT 1");
        $stmt->bind_param("s", $from_userid);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();
    
    $buyer_gsttype = (strlen($customer['gstin'] ?? '') === 15) ? 'register' : 'unregister';

    /* ========================================================================
    | FETCH INVOICE DETAILS
    ========================================================================*/
    $stmt = $db_conn->prepare("
        SELECT rwpoints_enable, gst_type 
        FROM user_invoice 
        WHERE inv_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $invid);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inv) {
        error_log("RETURN ERROR: Invoice $invid not found");
        header("Location: cnote_new.php?error=invoice_not_found");
        exit;
    }

    $rwpoints_enable = (int)($inv['rwpoints_enable'] ?? 0);
    $gst_type        = $inv['gst_type'];

    /* ========================================================================
    | FETCH PRODUCT DETAILS
    ========================================================================*/
    $stmt = $db_conn->prepare("
        SELECT gst, hsn, rwpoints 
        FROM products 
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $prid);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        error_log("RETURN ERROR: Product $prid not found");
        header("Location: cnote_new.php?error=product_not_found");
        exit;
    }

    /* ========================================================================
    | FETCH INVOICE ITEM DETAILS
    ========================================================================*/
    $item_table = ($from_usertype === 'customer') ? 'invoice_items' : 'user_invoice_items';
    
    $stmt = $db_conn->prepare("
        SELECT qty, amount 
        FROM $item_table 
        WHERE inv_id = ? AND pr_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $invid, $prid);
    $stmt->execute();
    $inv_item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inv_item) {
        error_log("RETURN ERROR: Product $prid not found in invoice $invid items");
        header("Location: cnote_new.php?error=product_not_in_invoice_items");
        exit;
    }

    /* ========================================================================
    | CALCULATE RETURN AMOUNTS
    ========================================================================*/
    $subtotal = (float)$inv_item['amount'] * $returnqty;
    $gst_amt  = ($subtotal * (float)$product['gst']) / 100;
    $total    = $subtotal + $gst_amt;
    $rwpoints = (float)$product['rwpoints'] * $returnqty;
    $return_date = date('Y-m-d');

    /* ========================================================================
    | CREATE/UPDATE RETURN MASTER RECORD
    ========================================================================*/
    $stmt = $db_conn->prepare("
        INSERT INTO user_return_stock
        (returnid, invnumber, date, subtotal, discount, total,
         from_usertype, from_userid, to_usertype, to_userid,
         status, rwpoints_enable, buyer_gsttype, gst_type)
        VALUES (?, ?, ?, 0, 0, 0, ?, ?, ?, ?, 'pending', ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        from_usertype = VALUES(from_usertype),
        from_userid = VALUES(from_userid),
        to_usertype = VALUES(to_usertype),
        to_userid = VALUES(to_userid)
    ");
    
    $stmt->bind_param(
        "sssssssiss",
        $returnid, $invnumber, $return_date,
        $from_usertype, $from_userid, $to_usertype, $to_userid,
        $rwpoints_enable, $buyer_gsttype, $gst_type
    );
    $stmt->execute();
    $stmt->close();

    /* ========================================================================
    | CHECK FOR DUPLICATE PRODUCT
    ========================================================================*/
    $stmt = $db_conn->prepare("
        SELECT COUNT(*) AS cnt 
        FROM user_return_stock_items 
        WHERE returnid = ? AND prid = ?
    ");
    $stmt->bind_param("ss", $returnid, $prid);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int)$exists['cnt'] > 0) {
        error_log("DUPLICATE PRODUCT BLOCKED: Return $returnid, Product $prid already exists");
        $encoded_returnid = base64_encode($returnid);
        $encoded_invid = base64_encode($invnumber);
        header("Location: cnote_new.php?returnid=$encoded_returnid&InvoiceID=$encoded_invid&productalreadyexists");
        exit;
    }

    /* ========================================================================
    | INSERT RETURN ITEM (NO STOCK UPDATES HERE)
    ========================================================================*/
    $stmt = $db_conn->prepare("
        INSERT INTO user_return_stock_items
        (returnid, invnumber, prid, amount, qty, subtotal,
         gst_percentage, gstamount_total, total,
         from_usertype, from_userid, to_usertype, to_userid,
         date, status, hsn, damaged_qty, rwpoints, buyer_gsttype, gst_type, rwpoints_sls)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "sssidddddssssssidssd",
        $returnid, $invnumber, $prid,
        $inv_item['amount'], $returnqty, $subtotal,
        $product['gst'], $gst_amt, $total,
        $from_usertype, $from_userid, $to_usertype, $to_userid,
        $return_date,
        $product['hsn'], $damaged_qty, $rwpoints,
        $buyer_gsttype, $gst_type, $rwpoints
    );
    
    if (!$stmt->execute()) {
        error_log("INSERT FAILED: " . $stmt->error);
        header("Location: cnote_new.php?error=insert_failed");
        exit;
    }
    
    $stmt->close();

    error_log("RETURN ITEM ADDED SUCCESS: Return $returnid, Invoice $invid, Product $prid, Qty $returnqty");
    
    $encoded_returnid = base64_encode($returnid);
    $encoded_invid = base64_encode($invnumber);
    header("Location: cnote_new.php?returnid=$encoded_returnid&InvoiceID=$encoded_invid&addedsuccess");
    exit;
}

// Default redirect
error_log("WARNING: Reached default redirect. Request params: " . print_r($_REQUEST, true));
header("Location: dashboard");
exit;
?>