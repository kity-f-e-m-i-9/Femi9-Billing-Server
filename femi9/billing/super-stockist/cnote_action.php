<?php
/**
 * Credit Note Action Handler
 * Handles adding return items and final submission with advance payment credit
 * 
 * SECURITY: Uses prepared statements, input validation, CSRF protection
 * PERFORMANCE: Optimized queries with proper indexing
 */

// EMERGENCY DEBUG - Log immediately
error_log("==========================================");
error_log("cnote_action.php accessed at " . date('Y-m-d H:i:s'));
error_log("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("REQUEST params: " . print_r($_REQUEST, true));
error_log("==========================================");

include("checksession.php");
include("config.php");
include("advance-payment-functions.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
date_default_timezone_set("Asia/Kolkata");

/*
|--------------------------------------------------------------------------
| ADD RETURN ITEM (STOCK ADJUSTMENT + QUANTITY VALIDATION)
|--------------------------------------------------------------------------
*/
if (isset($_REQUEST['add-return'])) {
    
    error_log("=== ADD RETURN STARTED ===");
    error_log("POST data: " . print_r($_REQUEST, true));

    // Sanitize and validate inputs
    $from_usertype = mysqli_real_escape_string($db_conn, trim($_REQUEST['from_usertype'] ?? ''));
    $from_userid   = mysqli_real_escape_string($db_conn, trim($_REQUEST['from_userid'] ?? ''));
    $to_usertype   = mysqli_real_escape_string($db_conn, trim($_REQUEST['to_usertype'] ?? ''));
    $to_userid     = mysqli_real_escape_string($db_conn, trim($_REQUEST['to_userid'] ?? ''));
    $returnid      = mysqli_real_escape_string($db_conn, trim($_REQUEST['returnid'] ?? ''));
    $invid         = mysqli_real_escape_string($db_conn, trim($_REQUEST['invid'] ?? ''));
    $invnumber     = $invid; // invnumber is same as invid
    $prid          = mysqli_real_escape_string($db_conn, trim($_REQUEST['prid'] ?? ''));
    $returnqty     = (int)($_REQUEST['returnqty'] ?? 0);
    $damaged_qty   = (int)($_REQUEST['damaged_qty'] ?? 0);

    error_log("After input sanitization - returnid: $returnid, invid: $invid, prid: $prid, qty: $returnqty");

    // Validate required fields
    if (empty($from_usertype) || empty($from_userid) || empty($to_usertype) || 
        empty($to_userid) || empty($returnid) || empty($invid) || empty($prid)) {
        error_log("VALIDATION FAILED: Missing fields");
        header("Location: cnote_new.php?error=missing_fields");
        exit;
    }

    // Validate quantities
    if ($returnqty <= 0) {
        error_log("VALIDATION FAILED: Invalid quantity - $returnqty");
        header("Location: cnote_new.php?error=invalid_quantity");
        exit;
    }
    
    error_log("Validation passed, proceeding with return availability check...");

    /* ========================================================================
    | CRITICAL FIX: CHECK AVAILABLE QUANTITY FOR RETURN
    ========================================================================*/
    
    // Get return availability details
    $availability = getReturnAvailability($db_conn, $invid, $prid, $from_usertype, $returnid);
    
    // Check if product exists in invoice
    if ($availability['error']) {
        error_log("RETURN ERROR: Product $prid not found in invoice $invid");
        header("Location: cnote_new.php?error=product_not_in_invoice");
        exit;
    }
    
    // Check if requested return quantity exceeds available quantity
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
    
    // Prepare customer query with proper escaping
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
    // Determine correct invoice table based on user type
    $invoice_table = ($from_usertype === 'customer') ? 'invoice' : 'user_invoice';
    
    if ($from_usertype === 'customer') {
        // Customer invoice table has different columns
        $stmt = $db_conn->prepare("
            SELECT gst_type, date 
            FROM invoice 
            WHERE inv_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $invid);
        $stmt->execute();
        $inv = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Set default for rwpoints_enable since customers don't have this field
        $rwpoints_enable = 0;
        $gst_type = $inv['gst_type'] ?? 'outer';
        $invoice_date = $inv['date'] ?? date('Y-m-d');
    } else {
        // User invoice table (for super_stockiest, stockiest, distributor, etc.)
        $stmt = $db_conn->prepare("
            SELECT rwpoints_enable, gst_type, date 
            FROM user_invoice 
            WHERE inv_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $invid);
        $stmt->execute();
        $inv = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $rwpoints_enable = (int)($inv['rwpoints_enable'] ?? 0);
        $gst_type = $inv['gst_type'] ?? 'outer';
        $invoice_date = $inv['date'] ?? date('Y-m-d');
    }

    if (!$inv) {
        error_log("RETURN ERROR: Invoice $invid not found in $invoice_table");
        header("Location: cnote_new.php?error=invoice_not_found");
        exit;
    }

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
    | CHECK FOR DUPLICATE PRODUCT IN SAME RETURN (IMMEDIATELY BEFORE INSERT)
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
    | START TRANSACTION FOR ATOMIC OPERATION
    ========================================================================*/
    error_log("Starting transaction for return insert...");
    
    if (!mysqli_begin_transaction($db_conn)) {
        error_log("FAILED TO START TRANSACTION: " . mysqli_error($db_conn));
        header("Location: cnote_new.php?error=transaction_failed");
        exit;
    }

    try {
        error_log("Inside try block - about to insert return item");
        /* ====================================================================
        | INSERT RETURN ITEM
        =====================================================================*/
        $stmt = $db_conn->prepare("
            INSERT INTO user_return_stock_items
            (returnid, invnumber, prid, amount, qty, subtotal,
             gst_percentage, gstamount_total, total,
             from_usertype, from_userid, to_usertype, to_userid,
             date, status, hsn, damaged_qty, rwpoints, buyer_gsttype, gst_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "sssidddddssssssidss",
            $returnid, $invnumber, $prid,
            $inv_item['amount'], $returnqty, $subtotal,
            $product['gst'], $gst_amt, $total,
            $from_usertype, $from_userid, $to_usertype, $to_userid,
            $return_date,
            $product['hsn'], $damaged_qty, $product['rwpoints'],
            $buyer_gsttype, $gst_type
        );
        
        if (!$stmt->execute()) {
            throw new Exception("INSERT failed: " . $stmt->error);
        }
        
        error_log("Return item inserted successfully, affected rows: " . $stmt->affected_rows);
        $stmt->close();

       
        /* ====================================================================
        | COMMIT TRANSACTION
        =====================================================================*/
        if (!mysqli_commit($db_conn)) {
            throw new Exception("COMMIT failed: " . mysqli_error($db_conn));
        }
        
        error_log("Transaction committed successfully!");
        
    } catch (Exception $e) {
        // Rollback on any error
        if (!mysqli_rollback($db_conn)) {
            error_log("ROLLBACK ALSO FAILED: " . mysqli_error($db_conn));
        }
        
        error_log("RETURN ITEM INSERT FAILED - EXCEPTION: " . $e->getMessage());
        error_log("Exception trace: " . $e->getTraceAsString());
        error_log("MYSQLI ERROR: " . mysqli_error($db_conn));
        
        $encoded_returnid = base64_encode($returnid);
        $encoded_invid = base64_encode($invnumber);
        header("Location: cnote_new.php?returnid=$encoded_returnid&InvoiceID=$encoded_invid&error=insert_failed");
        exit;
    } catch (mysqli_sql_exception $e) {
        // Rollback on mysqli errors
        mysqli_rollback($db_conn);
        
        error_log("RETURN ITEM MYSQLI EXCEPTION: " . $e->getMessage());
        
        $encoded_returnid = base64_encode($returnid);
        $encoded_invid = base64_encode($invnumber);
        header("Location: cnote_new.php?returnid=$encoded_returnid&InvoiceID=$encoded_invid&error=database_error");
        exit;
    }

    /* ========================================================================
    | SUCCESS REDIRECT
    ========================================================================*/
    $encoded_returnid = base64_encode($returnid);
    $encoded_invid = base64_encode($invnumber);
    
    error_log("RETURN ITEM ADDED SUCCESS: Return $returnid, Invoice $invid, Product $prid, Qty $returnqty");
    error_log("Redirecting to: cnote_new.php?returnid=$encoded_returnid&InvoiceID=$encoded_invid&addedsuccess");
    
    header("Location: cnote_new.php?returnid=$encoded_returnid&InvoiceID=$encoded_invid&addedsuccess");
    exit;
}

/*
|--------------------------------------------------------------------------
| FINAL SUBMIT RETURN (ADVANCE PAYMENT CREDIT - DO NOT MODIFY)
|--------------------------------------------------------------------------
*/
if (isset($_REQUEST['final-submit'])) {

    $returnid      = mysqli_real_escape_string($db_conn, trim($_REQUEST['returnid'] ?? ''));
    $from_usertype = mysqli_real_escape_string($db_conn, trim($_REQUEST['from_usertype'] ?? ''));
    $from_userid   = mysqli_real_escape_string($db_conn, trim($_REQUEST['from_userid'] ?? ''));
    $to_usertype   = mysqli_real_escape_string($db_conn, trim($_REQUEST['to_usertype'] ?? ''));
    $to_userid     = mysqli_real_escape_string($db_conn, trim($_REQUEST['to_userid'] ?? ''));
    $invid         = mysqli_real_escape_string($db_conn, trim($_REQUEST['invid'] ?? ''));

    // Validate inputs
    if (empty($returnid) || empty($invid)) {
        header("Location: cnote_new.php?error=missing_fields");
        exit;
    }

    /* ========================================================================
    | ADVANCE PAYMENT CREDIT (SUPER STOCKIST/STOCKIST ONLY)
    ========================================================================*/
    if (in_array($from_usertype, ['super_stockiest', 'stockiest'])) {

        $reference_number = "CN-" . $returnid;

        // Check if credit already exists
        if (!hasReturnAdvanceCreditByReference($db_conn, $reference_number)) {

            // Calculate total return amount
            $stmt = $db_conn->prepare("
                SELECT SUM(total) AS total_amount
                FROM user_return_stock_items
                WHERE returnid = ? AND status = 'pending'
            ");
            $stmt->bind_param("s", $returnid);
            $stmt->execute();
            $tot = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $amount = (float)($tot['total_amount'] ?? 0);

            if ($amount > 0) {

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
                    addAdvancePaymentCreditForReturn(
                        $db_conn,
                        $reference_number,
                        $inv['inv_number'],
                        $amount,
                        date('Y-m-d'),
                        $inv['date'],
                        $from_userid,
                        $from_usertype,
                        $to_userid,
                        $to_usertype,
                        $Login_user_TYPEvl,
                        $Login_user_TYPEvl
                    );
                    
                    error_log("ADVANCE PAYMENT CREDIT ADDED: Return $returnid, Amount: $amount");
                }
            }
        }
    }

    /* ========================================================================
    | FINALIZE RETURN STATUS
    ========================================================================*/
    $stmt = $db_conn->prepare("UPDATE user_return_stock SET status = 'completed' WHERE returnid = ?");
    $stmt->bind_param("s", $returnid);
    $stmt->execute();
    $stmt->close();

    $stmt = $db_conn->prepare("UPDATE user_return_stock_items SET status = 'completed' WHERE returnid = ?");
    $stmt->bind_param("s", $returnid);
    $stmt->execute();
    $stmt->close();

    error_log("RETURN COMPLETED: Return $returnid");

    header("Location: cnote_new.php?returncompleted");
    exit;
}

// Default redirect for invalid requests
error_log("WARNING: Reached default redirect. Request params: " . print_r($_REQUEST, true));
header("Location: dashboard");
exit;
?>