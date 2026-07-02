<?php
/**
 * User Invoice Action - FIXED VERSION
 * Femi9 Billing Application
 * 
 * Handles adding items to invoice with:
 * - Stock validation
 * - GST calculation
 * - Reward points
 * - Transaction safety
 * 
 * @version 2.0 - Fixed & Secured
 * @date 2025-01-21
 */

declare(strict_types=1);

include("checksession.php");
include("config.php");
require_once("advance-payment-functions.php");
require_once("include/GodownAccess.php");

// Enable proper error handling (log, don't display)
error_reporting(E_ALL);
ini_set('display_errors', 1); // TEMPORARILY SET TO 1 FOR DEBUGGING
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/invoice-action-errors.log');

// ============================================================================
// VALIDATE AND SANITIZE INPUT
// ============================================================================

$randum_number = mysqli_real_escape_string($db_conn, trim($_REQUEST['randum_number'] ?? ''));
$inv_id = mysqli_real_escape_string($db_conn, trim($_REQUEST['inv_id'] ?? ''));
$invuser = mysqli_real_escape_string($db_conn, trim($_REQUEST['invuser'] ?? ''));
$username = mysqli_real_escape_string($db_conn, trim($_REQUEST['username'] ?? ''));
$usertype = mysqli_real_escape_string($db_conn, trim($_REQUEST['usertype'] ?? ''));

error_log("=== INVOICE ACTION START ===");
error_log("Invoice ID: $inv_id");
error_log("Invoice User Type: $invuser");

// ============================================================================
// CHECK INVOICE NUMBER AVAILABILITY
// ============================================================================

if (isset($_REQUEST['invoice_number_accept']) && $_REQUEST['invoice_number_accept'] == 0) {
    $_SESSION['errorMessage'] = "Invoice Number already exists!";
    $redirect_url = "user-invoice-add.php?invuser=$invuser&invoicealready=1";
    error_log("Invoice number already exists, redirecting");
    echo "<script>window.location='$redirect_url';</script>";
    exit;
}

$inv_number = mysqli_real_escape_string($db_conn, str_replace("'", "", $_REQUEST['inv_number'] ?? ''));
$id_only = "0";

// ============================================================================
// VALIDATE GODOWN/COMPANY
// ============================================================================

$godownid = mysqli_real_escape_string($db_conn, trim($_REQUEST['godownid'] ?? ''));

// Get the actual user type from invoice (more reliable than session or URL)
// First check if invoice exists to get the correct from_user_type and from_user_id
$stmt_check = $db_conn->prepare("SELECT from_user_type, from_user_id FROM user_invoice WHERE inv_id = ?");
$stmt_check->bind_param("s", $inv_id);
$stmt_check->execute();
$existing_invoice = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

// Determine which user_type and user_id to use for stock queries
if ($existing_invoice) {
    // Invoice exists (EDIT mode) - use invoice's stored values
    $stock_user_type = $existing_invoice['from_user_type'];
    $stock_user_id = $existing_invoice['from_user_id'];
    error_log("EDIT MODE: Using from invoice - user_type: $stock_user_type, user_id: $stock_user_id");
    
    // If godownid from URL is empty, use invoice's from_user_id
    if (empty($godownid)) {
        $godownid = $stock_user_id;
        error_log("Godown ID was empty, using from invoice: $godownid");
    }
} else {
    // New invoice - use session and URL parameters
    $stock_user_type = $Login_user_TYPEvl;
    $stock_user_id = !empty($godownid) ? $godownid : $Login_user_IDvl;
    error_log("NEW MODE: Using session - user_type: $stock_user_type, user_id: $stock_user_id");
    
    if (empty($godownid)) {
        error_log("ERROR: Godown ID is empty in new invoice mode");
        echo "<script>alert('Company/Godown is required'); window.history.back();</script>";
        exit;
    }
}

if (!empty($godownid) && !is_godown_allowed($db_conn, (int)$godownid)) {
    error_log("ERROR: godownid $godownid is finance_only and this session is not a finance login");
    echo "<script>alert('You are not authorized to use this company profile'); window.history.back();</script>";
    exit;
}

// Validate that we have the required values
if (empty($stock_user_type) || empty($stock_user_id)) {
    error_log("CRITICAL: stock_user_type or stock_user_id is empty");
    error_log("stock_user_type: '$stock_user_type', stock_user_id: '$stock_user_id'");
    echo "<script>alert('Unable to determine company information. Please try again.'); window.history.back();</script>";
    exit;
}

// Check if stock is updated for this godown
$stmt = $db_conn->prepare("
    SELECT COUNT(*) as numopstock12 
    FROM stock 
    WHERE user_type = ? AND user_id = ?
");
$stmt->bind_param("ss", $stock_user_type, $stock_user_id);
$stmt->execute();
$result = $stmt->get_result();
$result_count_opstock13 = $result->fetch_assoc();
$stmt->close();

if ($result_count_opstock13['numopstock12'] == 0) {
    error_log("ERROR: Stock not updated for godown: $godownid");
    $redirect_url = "user-invoice-add.php?invuser=$invuser&gid=$godownid&stocknotupdated=1&action=" . 
                    ($_SESSION['ACTIONEDIT'] ?? '') . "&stockerror=1";
    echo "<script>window.location='$redirect_url';</script>";
    exit;
}

// ============================================================================
// GET CUSTOMER AND PRODUCT DETAILS
// ============================================================================

$customer_id = mysqli_real_escape_string($db_conn, trim($_REQUEST['customer_id'] ?? ''));
$date = date("Y-m-d", strtotime($_REQUEST['date'] ?? 'now'));
$inv_year = date("Y", strtotime($_REQUEST['date'] ?? 'now'));
$pr_id = mysqli_real_escape_string($db_conn, trim($_REQUEST['pr_id'] ?? ''));
$amount = floatval($_REQUEST['amount'] ?? 0);
$qty = floatval($_REQUEST['qty'] ?? 0);

if (empty($customer_id) || empty($pr_id) || $qty <= 0) {
    error_log("ERROR: Invalid input - customer: $customer_id, product: $pr_id, qty: $qty");
    echo "<script>alert('Invalid input data'); window.history.back();</script>";
    exit;
}

$totalamount = $amount * $qty;

error_log("Adding product $pr_id (Qty: $qty) to invoice $inv_id");

// ============================================================================
// CALCULATE TOTALS, GST, DISCOUNT
// ============================================================================

// Get product details
$stmt = $db_conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("s", $pr_id);
$stmt->execute();
$resultproducts = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$resultproducts) {
    error_log("ERROR: Product not found: $pr_id");
    echo "<script>alert('Product not found'); window.history.back();</script>";
    exit;
}

$gst_percentage = floatval($resultproducts['gst'] ?? 0);
$hsn = $resultproducts['hsn'] ?? '';
$rwpoints = floatval($resultproducts['rwpoints'] ?? 0) * $qty;

// GST amount per product (currently set to 0 as per original logic)
$gstamount_singlepr = 0.00;

// Calculate discount
if (isset($_REQUEST['discount_percentage']) && $_REQUEST['discount_percentage'] > 0) {
    $discount_percentage = floatval($_REQUEST['discount_percentage']);
    $discount_amount = ($totalamount * $discount_percentage) / 100;
    $discount_amount = number_format($discount_amount, 2, '.', '');
} else {
    $discount_amount = floatval($_REQUEST['discount_amount'] ?? 0);
    $discount_percentage = $totalamount > 0 ? ($discount_amount * 100) / $totalamount : 0;
    $discount_percentage = number_format($discount_percentage, 2, '.', '');
}

$subtotal = $totalamount - $discount_amount;
$subtotal = number_format($subtotal, 2, '.', '');
 
$gstamount_total = ($subtotal * $gst_percentage) / 100; 
$total = $subtotal + $gstamount_total;

error_log("Item totals - Subtotal: Rs.$subtotal, GST: Rs.$gstamount_total, Total: Rs.$total");

// ============================================================================
// GET STATE CODES FOR GST DETERMINATION
// ============================================================================

// Get admin state code
$stmt = $db_conn->prepare("SELECT state FROM admin_log WHERE usertype = 'admin' LIMIT 1");
$stmt->execute();
$fetch_resultlog = $stmt->get_result()->fetch_assoc();
$stmt->close();

$admin_statecode = $fetch_resultlog['state'] ?? '';

// Get customer state code
$tablename = '';
switch ($invuser) {
    case "candf":
        $tablename = "c_and_f";
        break;
    case "super_stockiest":
        $tablename = "super_stockiest";
        break;
    case "stockiest":
        $tablename = "stockiest";
        break;
    case "super_distributor":
        $tablename = "super_distributor";
        break;
    case "distributor":
        $tablename = "distributor";
        break;
    case "outlet":
        $tablename = "outlet";
        break;
    default:
        error_log("ERROR: Invalid user type: $invuser");
        echo "<script>alert('Invalid user type'); window.history.back();</script>";
        exit;
}

$stmt = $db_conn->prepare("SELECT * FROM $tablename WHERE temp_id = ?");
$stmt->bind_param("s", $customer_id);
$stmt->execute();
$resultcutomser_dtails = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$resultcutomser_dtails) {
    error_log("ERROR: Customer not found: $customer_id in table $tablename");
    echo "<script>alert('Customer not found'); window.history.back();</script>";
    exit;
}

$customer_state = $resultcutomser_dtails['state_id'] ?? '';
$buyer_GSTIN = $resultcutomser_dtails['gstin'] ?? '';
$buyer_GSTIN_count = strlen($buyer_GSTIN);
$buyer_gsttype = ($buyer_GSTIN_count == 15) ? "register" : "unregister";

$gst_type = ($customer_state == $admin_statecode) ? "inner" : "outer";

error_log("GST Type: $gst_type, Buyer GST Type: $buyer_gsttype");

// ============================================================================
// START TRANSACTION FOR DATA CONSISTENCY
// ============================================================================

$db_conn->begin_transaction();

try {
    // ========================================================================
    // CHECK IF INVOICE EXISTS, CREATE IF NOT
    // ========================================================================
    
    $stmt = $db_conn->prepare("
        SELECT COUNT(*) as numInvoice 
        FROM user_invoice 
        WHERE inv_id = ? AND from_user_type = ? AND from_user_id = ? 
        AND to_user_type = ? AND to_user_id = ?
    ");
    $stmt->bind_param("sssss", $inv_id, $stock_user_type, $stock_user_id, $invuser, $customer_id);
    $stmt->execute();
    $result_count_invoice = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result_count_invoice['numInvoice'] == 0) {
        error_log("Creating new invoice header: $inv_id");
        
        // Insert invoice header - use stock_user_type and stock_user_id
        $stmt = $db_conn->prepare("
            INSERT INTO user_invoice (
                inv_id, id_only, inv_number, date, inv_year, sub_total, discount, total,
                to_user_type, to_user_id, from_user_type, from_user_id, gst_type,
                credit, roundoff, courier_charges, rwpoints_enable, buyer_gsttype,
                username, usertype
            ) VALUES (?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?, ?, ?, 0, 0, 0, 1, ?, ?, ?)
        ");
        
        // 13 placeholders: inv_id, id_only, inv_number, date, inv_year, to_user_type, to_user_id, 
        //                  from_user_type, from_user_id, gst_type, buyer_gsttype, username, usertype
        $stmt->bind_param(
            "sssssssssssss",
            $inv_id,
            $id_only,
            $inv_number,
            $date,
            $inv_year,
            $invuser,
            $customer_id,
            $stock_user_type,
            $stock_user_id,
            $gst_type,
            $buyer_gsttype,
            $username,
            $usertype
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create invoice: " . $stmt->error);
        }
        $stmt->close();
        
        error_log("Invoice header created successfully");
    }
    
    // ========================================================================
    // VALIDATE AVAILABLE STOCK
    // ========================================================================
    
    error_log("=== STOCK CHECK START ===");
    error_log("Product ID: $pr_id");
    error_log("Requested Qty: $qty");
    error_log("Query params - user_type: '$stock_user_type', user_id: '$stock_user_id'");
    
    $stmt = $db_conn->prepare("
        SELECT * FROM stock 
        WHERE product_id = ? AND user_type = ? AND user_id = ?
    ");
    $stmt->bind_param("sss", $pr_id, $stock_user_type, $stock_user_id);
    $stmt->execute();
    $RESULT_count_AVSTOCK = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$RESULT_count_AVSTOCK) {
        error_log("ERROR: Stock record NOT FOUND");
        error_log("Tried: product_id='$pr_id', user_type='$stock_user_type', user_id='$stock_user_id'");
        
        // Check if stock exists with different parameters
        $debug_check = mysqli_query($db_conn, "SELECT user_type, user_id, closing_qty FROM stock WHERE product_id='$pr_id' LIMIT 5");
        if ($debug_check && mysqli_num_rows($debug_check) > 0) {
            error_log("Stock EXISTS for this product but with different user_type/user_id:");
            while ($debug_row = mysqli_fetch_assoc($debug_check)) {
                error_log("  - user_type='{$debug_row['user_type']}', user_id='{$debug_row['user_id']}', qty={$debug_row['closing_qty']}");
            }
        } else {
            error_log("Product $pr_id has NO stock records in database at all");
        }
        
        throw new Exception("Stock record not found for product: $pr_id");
    }
    
    $AVMstock = floatval($RESULT_count_AVSTOCK['closing_qty'] ?? 0);
    
    error_log("Stock found: closing_qty = $AVMstock");
    error_log("Comparison: $AVMstock < $qty = " . ($AVMstock < $qty ? 'TRUE (INSUFFICIENT)' : 'FALSE (OK)'));
    
    if ($AVMstock < $qty) {
        // Insufficient stock
        error_log("ERROR: Insufficient stock for product $pr_id");
        error_log("=== STOCK CHECK FAILED ===");
        throw new Exception("INSUFFICIENT_STOCK");
    }
    
    error_log("=== STOCK CHECK PASSED ===");
    
    // ========================================================================
    // CHECK IF PRODUCT ALREADY IN INVOICE
    // ========================================================================
    
    $stmt = $db_conn->prepare("
        SELECT COUNT(*) as numInvoiceItem 
        FROM user_invoice_items 
        WHERE inv_id = ? AND pr_id = ? AND from_user_type = ? AND from_user_id = ?
        AND to_user_type = ? AND to_user_id = ?
    ");
    $stmt->bind_param("ssssss", $inv_id, $pr_id, $stock_user_type, $stock_user_id, $invuser, $customer_id);
    $stmt->execute();
    $result_count_invoiceItem = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result_count_invoiceItem['numInvoiceItem'] > 0) {
        // Product already exists in invoice
        error_log("ERROR: Product $pr_id already exists in invoice $inv_id");
        throw new Exception("ITEM_ALREADY_EXISTS");
    }
    
    // ========================================================================
    // INSERT INVOICE ITEM
    // ========================================================================
    
    $stmt = $db_conn->prepare("
        INSERT INTO user_invoice_items (
            inv_id, pr_id, amount, qty, total, to_user_type, to_user_id,
            from_user_type, from_user_id, gst_percentage, gstamount_singlepr,
            gstamount_total, subtotal, discount_percentage, discount_amount,
            gst_type, hsn, date, rwpoints, buyer_gsttype
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // 20 placeholders: 2 strings, 2 decimals, 6 strings, 5 decimals, 1 string, 1 string, 1 string, 1 decimal, 1 string
    // Types: ss dd ssssss ddddd s s s d s = ssddssssssdddddsssds
    $stmt->bind_param(
        "ssddssssssdddddsssds",
        $inv_id,
        $pr_id,
        $amount,
        $qty,
        $total,
        $invuser,
        $customer_id,
        $stock_user_type,
        $stock_user_id,
        $gst_percentage,
        $gstamount_singlepr,
        $gstamount_total,
        $subtotal,
        $discount_percentage,
        $discount_amount,
        $gst_type,
        $hsn,
        $date,
        $rwpoints,
        $buyer_gsttype
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert invoice item: " . $stmt->error);
    }
    $stmt->close();
    
    error_log("Invoice item added successfully");
    
    
    // ========================================================================
    // COMMIT TRANSACTION
    // ========================================================================
    
    $db_conn->commit();
    
    error_log("=== INVOICE ACTION SUCCESS ===");
    
    // Use stock_user_id (from invoice) for redirect to ensure correct gid
    $redirect_url = "user-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . 
                    "&AddedSuccess=1&invuser=$invuser&FemiAdded=1&action=" . 
                    ($_SESSION['ACTIONEDIT'] ?? '') . "&gid=$stock_user_id";
    
    echo "<script>window.location='$redirect_url';</script>";
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db_conn->rollback();
    
    error_log("=== INVOICE ACTION FAILED ===");
    error_log("ERROR: " . $e->getMessage());
    
    $error_message = $e->getMessage();
    
    // Use stock_user_id for redirect (from invoice if exists)
    $redirect_gid = isset($stock_user_id) ? $stock_user_id : $godownid;
    
    if ($error_message === "INSUFFICIENT_STOCK") {
        $redirect_url = "user-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . 
                        "&InvalidStock=1&invuser=$invuser&AlertStockError=1&action=" . 
                        ($_SESSION['ACTIONEDIT'] ?? '') . "&gid=$redirect_gid";
    } elseif ($error_message === "ITEM_ALREADY_EXISTS") {
        $redirect_url = "user-invoice-add.php?InvoiceID=" . base64_encode($inv_id) . 
                        "&ItemAlreadyExists=1&invuser=$invuser&AlertMessage=1&action=" . 
                        ($_SESSION['ACTIONEDIT'] ?? '') . "&gid=$redirect_gid";
    } else {
        $safe_error = htmlspecialchars($error_message, ENT_QUOTES);
        echo "<script>alert('Error: $safe_error'); window.history.back();</script>";
        exit;
    }
    
    echo "<script>window.location='$redirect_url';</script>";
    exit;
}
?>