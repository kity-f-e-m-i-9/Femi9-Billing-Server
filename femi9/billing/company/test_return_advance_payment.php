<?php
/**
 * Debug Script for Return & Advance Payment Testing
 * This script provides detailed logging to debug 500 errors
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/debug_error.log');
date_default_timezone_set("Asia/Kolkata");

// Don't use output buffering - we want to see output immediately
// ob_start();

// Custom error handler to catch all errors
$debug_logs = [];
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    global $debug_logs;
    $debug_logs[] = "PHP ERROR [$errno]: $errstr in $errfile on line $errline";
    error_log("PHP ERROR [$errno]: $errstr in $errfile on line $errline");
    return false; // Let PHP handle the error too
});

// Custom exception handler
set_exception_handler(function($exception) {
    global $debug_logs;
    $debug_logs[] = "UNCAUGHT EXCEPTION: " . $exception->getMessage();
    $debug_logs[] = "File: " . $exception->getFile() . " Line: " . $exception->getLine();
    $debug_logs[] = "Trace: " . $exception->getTraceAsString();
    error_log("UNCAUGHT EXCEPTION: " . $exception->getMessage());
    
    // Render error page immediately
    echo renderErrorPage($debug_logs);
    exit;
});

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        global $debug_logs;
        $debug_logs[] = "FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}";
        error_log("FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}");
        echo renderErrorPage($debug_logs);
        exit;
    }
});

// Helper function to render error page
function renderErrorPage($logs) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Error</title>
        <style>
            body { font-family: Arial; padding: 20px; background: #f5f5f5; }
            .error { background: #fff; padding: 30px; border-radius: 8px; max-width: 1000px; margin: 0 auto; }
            .error h1 { color: #d32f2f; }
            .logs { background: #1e1e1e; color: #00ff00; padding: 15px; border-radius: 4px; margin-top: 20px; font-family: monospace; font-size: 13px; max-height: 500px; overflow-y: auto; line-height: 1.5; }
            .logs div { margin-bottom: 5px; }
        </style>
    </head>
    <body>
        <div class="error">
            <h1>⚠️ Error Loading Script</h1>
            <p>The script encountered an error during initialization or execution.</p>
            <div class="logs">
                <?php foreach($logs as $log): ?>
                    <div><?= htmlspecialchars($log) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Initialize debug logs array
$debug_logs = [];
$debug_logs[] = "=== SCRIPT STARTED ===";
$debug_logs[] = "Current directory: " . getcwd();
$debug_logs[] = "Script file: " . __FILE__;
$debug_logs[] = "PHP Version: " . phpversion();
$debug_logs[] = "Request Method: " . $_SERVER['REQUEST_METHOD'];
$debug_logs[] = "POST data: " . print_r($_POST, true);

// Include checksession.php FIRST - before any output
try {
    if(file_exists("checksession.php")) {
        require_once("checksession.php");
        $debug_logs[] = "✓ checksession.php loaded";
    } else {
        throw new Exception("checksession.php not found in " . getcwd());
    }
} catch(Exception $e) {
    $debug_logs[] = "✗ Error loading checksession.php: " . $e->getMessage();
    die(renderErrorPage($debug_logs));
}

// NOW we can output - after session is started
echo "<!-- Script is running -->\n";
flush();

// Include config.php
try {
    if(file_exists("config.php")) {
        require_once("config.php");
        $debug_logs[] = "✓ config.php loaded";
    } else {
        throw new Exception("config.php not found in " . getcwd());
    }
} catch(Exception $e) {
    $debug_logs[] = "✗ Error loading config.php: " . $e->getMessage();
    die(renderErrorPage($debug_logs));
}

// Check database connection
if(!isset($db_conn)) {
    $debug_logs[] = "✗ \$db_conn variable not set";
    die(renderErrorPage($debug_logs));
}

if(!$db_conn) {
    $debug_logs[] = "✗ Database connection is false/null";
    die(renderErrorPage($debug_logs));
}

if(mysqli_connect_errno()) {
    $debug_logs[] = "✗ Database connection error: " . mysqli_connect_error();
    die(renderErrorPage($debug_logs));
}

$debug_logs[] = "✓ Database connected successfully";

// Include advance payment functions
try {
    if(file_exists("advance-payment-functions.php")) {
        require_once("advance-payment-functions.php");
        $debug_logs[] = "✓ advance-payment-functions.php loaded";
    } else {
        throw new Exception("advance-payment-functions.php not found in " . getcwd());
    }
} catch(Exception $e) {
    $debug_logs[] = "✗ Error loading advance-payment-functions.php: " . $e->getMessage();
    die(renderErrorPage($debug_logs));
}

// Check if functions exist
if(!function_exists('isAdvancePaymentMandatory')) {
    $debug_logs[] = "✗ Function isAdvancePaymentMandatory() not found";
    die(renderErrorPage($debug_logs));
}

if(!function_exists('addAdvancePaymentCreditForReturn')) {
    $debug_logs[] = "✗ Function addAdvancePaymentCreditForReturn() not found";
    die(renderErrorPage($debug_logs));
}

$debug_logs[] = "✓ All advance payment functions loaded";

// Get session variables
$Login_user_TYPEvl = isset($_SESSION['Login_user_TYPEvl']) ? $_SESSION['Login_user_TYPEvl'] : 'company';
$Login_user_IDvl = isset($_SESSION['Login_user_IDvl']) ? $_SESSION['Login_user_IDvl'] : '1';

$debug_logs[] = "Session User Type: $Login_user_TYPEvl";
$debug_logs[] = "Session User ID: $Login_user_IDvl";

// Determine current step
$step = isset($_POST['step']) ? intval($_POST['step']) : 1;
$debug_logs[] = "Current Step: $step";

// Variables for storing results
$invoice = null;
$items = null;
$success = null;
$return_id = null;
$final_amount = null;

// ==================== STEP 1: LOAD INVOICE ====================
if($step == 1) {
    $invoice_id = isset($_POST['invoice_id']) ? mysqli_real_escape_string($db_conn, trim($_POST['invoice_id'])) : '';
    
    if(!empty($invoice_id)) {
        $debug_logs[] = "=== LOADING INVOICE ===";
        $debug_logs[] = "Invoice ID: $invoice_id";
        
        try {
            $query = "SELECT * FROM user_invoice WHERE inv_id = '$invoice_id' LIMIT 1";
            $debug_logs[] = "Query: $query";
            
            $result = mysqli_query($db_conn, $query);
            
            if($result === false) {
                throw new Exception("Query failed: " . mysqli_error($db_conn));
            }
            
            if(mysqli_num_rows($result) > 0) {
                $invoice = mysqli_fetch_assoc($result);
                $debug_logs[] = "✓ Invoice found: " . $invoice['inv_number'];
                
                // Get invoice items
                $items_query = "SELECT ui.*, p.productName, p.gst, p.hsn, p.rwpoints 
                               FROM user_invoice_items ui 
                               LEFT JOIN products p ON ui.pr_id = p.id 
                               WHERE ui.inv_id = '$invoice_id'";
                $debug_logs[] = "Items Query: $items_query";
                
                $items_result = mysqli_query($db_conn, $items_query);
                
                if($items_result === false) {
                    throw new Exception("Items query failed: " . mysqli_error($db_conn));
                }
                
                $items = [];
                while($item = mysqli_fetch_assoc($items_result)) {
                    $items[] = $item;
                }
                $debug_logs[] = "✓ Found " . count($items) . " items in invoice";
                
            } else {
                $debug_logs[] = "✗ Invoice not found with ID: $invoice_id";
            }
            
        } catch(Exception $e) {
            $debug_logs[] = "✗ Error: " . $e->getMessage();
        }
    }
}

// ==================== STEP 2: PROCESS RETURN ====================
if($step == 2) {
    $success = false;
    
    try {
        $debug_logs[] = "=== STEP 2: PROCESSING RETURN ===";
        
        // Validate inputs
        if(!isset($_POST['invoice_id']) || empty($_POST['invoice_id'])) {
            throw new Exception("Invoice ID is missing");
        }
        
        if(!isset($_POST['product_id']) || empty($_POST['product_id'])) {
            throw new Exception("Product ID is missing");
        }
        
        $invoice_id = mysqli_real_escape_string($db_conn, trim($_POST['invoice_id']));
        $product_id = mysqli_real_escape_string($db_conn, trim($_POST['product_id']));
        $return_qty = isset($_POST['return_qty']) ? floatval($_POST['return_qty']) : 0;
        $discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0;
        
        if($return_qty <= 0) {
            throw new Exception("Invalid return quantity: $return_qty");
        }
        
        $debug_logs[] = "Invoice ID: $invoice_id";
        $debug_logs[] = "Product ID: $product_id";
        $debug_logs[] = "Return Qty: $return_qty";
        $debug_logs[] = "Discount: $discount";
        
        // Get invoice details
        $inv_query = "SELECT * FROM user_invoice WHERE inv_id = '$invoice_id' LIMIT 1";
        $inv_result = mysqli_query($db_conn, $inv_query);
        
        if(!$inv_result) {
            throw new Exception("Invoice query failed: " . mysqli_error($db_conn));
        }
        
        $invoice = mysqli_fetch_assoc($inv_result);
        if(!$invoice) {
            throw new Exception("Invoice not found");
        }
        
        $from_usertype = $invoice['to_user_type'];
        $from_userid = $invoice['to_user_id'];
        $to_usertype = $invoice['from_user_type'];
        $to_userid = $invoice['from_user_id'];
        $invoice_date = !empty($invoice['inv_date']) ? $invoice['inv_date'] : date('Y-m-d'); // Use current date if null
        $invoice_number = $invoice['inv_number'];
        
        $debug_logs[] = "✓ Invoice: $invoice_number";
        $debug_logs[] = "From User: $from_usertype ($from_userid)";
        $debug_logs[] = "To User: $to_usertype ($to_userid)";
        $debug_logs[] = "Invoice Date: $invoice_date" . (empty($invoice['inv_date']) ? " (using current date as fallback)" : "");
        
        // Check if mandatory
        $is_mandatory = isAdvancePaymentMandatory($from_usertype);
        $debug_logs[] = "Advance Payment Mandatory: " . ($is_mandatory ? "YES" : "NO");
        
        // Get product details
        $prod_query = "SELECT * FROM products WHERE id = '$product_id' LIMIT 1";
        $prod_result = mysqli_query($db_conn, $prod_query);
        
        if(!$prod_result) {
            throw new Exception("Product query failed: " . mysqli_error($db_conn));
        }
        
        $product = mysqli_fetch_assoc($prod_result);
        if(!$product) {
            throw new Exception("Product not found");
        }
        
        $debug_logs[] = "✓ Product: " . $product['productName'];
        
        // Get invoice item details
        $item_query = "SELECT * FROM user_invoice_items WHERE inv_id = '$invoice_id' AND pr_id = '$product_id' LIMIT 1";
        $item_result = mysqli_query($db_conn, $item_query);
        
        if(!$item_result) {
            throw new Exception("Invoice item query failed: " . mysqli_error($db_conn));
        }
        
        $item = mysqli_fetch_assoc($item_result);
        if(!$item) {
            throw new Exception("Invoice item not found");
        }
        
        $pr_mrp = floatval($item['amount']);
        $gst_percentage = floatval($product['gst']);
        
        // Calculate amounts
        $subtotal = $pr_mrp * $return_qty;
        $gst_amount = $subtotal * $gst_percentage / 100;
        $total_before_discount = $subtotal + $gst_amount;
        $final_amount = $total_before_discount - $discount;
        
        $debug_logs[] = "=== CALCULATIONS ===";
        $debug_logs[] = "MRP: Rs. " . inr_format($pr_mrp, 2);
        $debug_logs[] = "Subtotal: Rs. " . inr_format($subtotal, 2);
        $debug_logs[] = "GST ($gst_percentage%): Rs. " . inr_format($gst_amount, 2);
        $debug_logs[] = "Total before discount: Rs. " . inr_format($total_before_discount, 2);
        $debug_logs[] = "Discount: Rs. " . inr_format($discount, 2);
        $debug_logs[] = "FINAL AMOUNT: Rs. " . inr_format($final_amount, 2);
        
        // Generate return ID
        $return_id = "RET" . date("YmdHis") . rand(100, 999);
        $return_date = date("Y-m-d");
        
        $debug_logs[] = "=== CREATING RETURN ===";
        $debug_logs[] = "Return ID: $return_id";
        $debug_logs[] = "Return Date: $return_date";
        
        // Begin transaction
        if(!mysqli_begin_transaction($db_conn)) {
            throw new Exception("Failed to begin transaction: " . mysqli_error($db_conn));
        }
        $debug_logs[] = "✓ Transaction started";
        
        // Insert return record
        $insert_return = "INSERT INTO user_return_stock 
            (returnid, invnumber, date, subtotal, discount, total, 
             from_usertype, from_userid, to_usertype, to_userid, 
             status, rwpoints_enable, buyer_gsttype, gst_type) 
            VALUES 
            ('$return_id', '$invoice_id', '$return_date', '$subtotal', '$discount', '$final_amount',
             '$from_usertype', '$from_userid', '$to_usertype', '$to_userid',
             'accept', '0', 'register', 'inclusive')";
        
        if(!mysqli_query($db_conn, $insert_return)) {
            throw new Exception("Failed to insert return: " . mysqli_error($db_conn));
        }
        $debug_logs[] = "✓ Return record created";
        
        // Insert return item
        $insert_item = "INSERT INTO user_return_stock_items 
            (returnid, invnumber, prid, amount, qty, subtotal, 
             gst_percentage, gstamount_total, total, 
             from_usertype, from_userid, to_usertype, to_userid, 
             date, status, hsn, damaged_qty, rwpoints, buyer_gsttype, gst_type) 
            VALUES 
            ('$return_id', '$invoice_id', '$product_id', '$pr_mrp', '$return_qty', '$subtotal',
             '$gst_percentage', '$gst_amount', '$total_before_discount',
             '$from_usertype', '$from_userid', '$to_usertype', '$to_userid',
             '$return_date', 'accept', '{$product['hsn']}', '0', '0', 'register', 'inclusive')";
        
        if(!mysqli_query($db_conn, $insert_item)) {
            throw new Exception("Failed to insert return item: " . mysqli_error($db_conn));
        }
        $debug_logs[] = "✓ Return item created";
        
        // Stock updates (simplified - no error if stock doesn't exist)
        $debug_logs[] = "=== STOCK UPDATES ===";
        
        $stock_query = "SELECT * FROM stock WHERE product_id = '$product_id' 
                       AND user_type = '$to_usertype' AND user_id = '$to_userid' LIMIT 1";
        $stock_result = mysqli_query($db_conn, $stock_query);
        
        if($stock_result && mysqli_num_rows($stock_result) > 0) {
            $stock = mysqli_fetch_assoc($stock_result);
            $new_sales_qty = $stock['sales_qty'] - $return_qty;
            $new_closing_qty = $stock['closing_qty'] + $return_qty;
            
            $update_stock = "UPDATE stock SET sales_qty = '$new_sales_qty', closing_qty = '$new_closing_qty' 
                           WHERE product_id = '$product_id' AND user_type = '$to_usertype' AND user_id = '$to_userid'";
            
            if(mysqli_query($db_conn, $update_stock)) {
                $debug_logs[] = "✓ Company stock updated";
            } else {
                $debug_logs[] = "⚠ Company stock update failed: " . mysqli_error($db_conn);
            }
        } else {
            $debug_logs[] = "ℹ No stock record for company";
        }
        
        // Customer stock (if applicable)
        if(in_array($from_usertype, ['super_stockiest', 'stockiest', 'super_distributor', 'distributor'])) {
            $stock_query2 = "SELECT * FROM stock WHERE product_id = '$product_id' 
                           AND user_type = '$from_usertype' AND user_id = '$from_userid' LIMIT 1";
            $stock_result2 = mysqli_query($db_conn, $stock_query2);
            
            if($stock_result2 && mysqli_num_rows($stock_result2) > 0) {
                $stock2 = mysqli_fetch_assoc($stock_result2);
                $new_input_qty = $stock2['input_qty'] - $return_qty;
                $new_closing_qty2 = $stock2['closing_qty'] - $return_qty;
                
                $update_stock2 = "UPDATE stock SET input_qty = '$new_input_qty', closing_qty = '$new_closing_qty2' 
                               WHERE product_id = '$product_id' AND user_type = '$from_usertype' AND user_id = '$from_userid'";
                
                if(mysqli_query($db_conn, $update_stock2)) {
                    $debug_logs[] = "✓ Customer stock updated";
                } else {
                    $debug_logs[] = "⚠ Customer stock update failed: " . mysqli_error($db_conn);
                }
            } else {
                $debug_logs[] = "ℹ No stock record for customer";
            }
        }
        
        // ==================== ADVANCE PAYMENT CREDIT ====================
        $debug_logs[] = "=== ADVANCE PAYMENT CREDIT ===";
        
        if($is_mandatory && $final_amount > 0) {
            $debug_logs[] = "Processing advance payment credit...";
            $debug_logs[] = "Amount: Rs. " . inr_format($final_amount, 2);
            
            try {
                $credit_result = addAdvancePaymentCreditForReturn(
                    $db_conn,
                    $return_id,
                    $invoice_id,
                    $invoice_number,
                    $final_amount,
                    $return_date,
                    $invoice_date,
                    $from_userid,
                    $from_usertype,
                    $to_userid,
                    $to_usertype,
                    $Login_user_IDvl,
                    $Login_user_TYPEvl
                );
                
                $debug_logs[] = "Function returned:";
                $debug_logs[] = print_r($credit_result, true);
                
                if($credit_result['success']) {
                    $debug_logs[] = "✓✓✓ ADVANCE PAYMENT CREDITED ✓✓✓";
                    $debug_logs[] = "Payment ID: " . $credit_result['payment_id'];
                } else {
                    $debug_logs[] = "✗✗✗ CREDIT FAILED ✗✗✗";
                    $debug_logs[] = "Error: " . $credit_result['message'];
                }
                
                // Verify record - search by reference_number
                $referenceNumber = "CN-" . $return_id;
                $verify_query = "SELECT * FROM advance_payments 
                                WHERE reference_number = '$referenceNumber'
                                AND payment_mode = 'credit_note'
                                AND deleted_at IS NULL
                                LIMIT 1";
                $debug_logs[] = "Verification Query: $verify_query";
                $verify_result = mysqli_query($db_conn, $verify_query);
                
                if($verify_result && mysqli_num_rows($verify_result) > 0) {
                    $payment = mysqli_fetch_assoc($verify_result);
                    $debug_logs[] = "✓ VERIFIED: Record exists in database";
                    $debug_logs[] = "  ID: " . $payment['id'];
                    $debug_logs[] = "  Amount: Rs. " . $payment['amount'];
                    $debug_logs[] = "  Balance: Rs. " . $payment['balance_amount'];
                    $debug_logs[] = "  Status: " . $payment['status'];
                } else {
                    $debug_logs[] = "✗ VERIFICATION FAILED: Record not in database!";
                }
                
            } catch(Exception $e) {
                $debug_logs[] = "✗ EXCEPTION: " . $e->getMessage();
                $debug_logs[] = "Trace: " . $e->getTraceAsString();
            }
            
        } else {
            $debug_logs[] = ($is_mandatory ? "ℹ Amount is zero" : "ℹ Not mandatory for $from_usertype");
        }
        
        // Commit transaction
        if(!mysqli_commit($db_conn)) {
            throw new Exception("Failed to commit: " . mysqli_error($db_conn));
        }
        $debug_logs[] = "✓ Transaction committed";
        $debug_logs[] = "=== SUCCESS ===";
        
        $success = true;
        
        // Force output
        error_log("Return processing completed successfully");
        
    } catch(Exception $e) {
        if(isset($db_conn)) {
            mysqli_rollback($db_conn);
            $debug_logs[] = "Transaction rolled back";
        }
        $debug_logs[] = "✗✗✗ ERROR ✗✗✗";
        $debug_logs[] = "Message: " . $e->getMessage();
        $debug_logs[] = "File: " . $e->getFile();
        $debug_logs[] = "Line: " . $e->getLine();
        $success = false;
        
        // Force output
        error_log("Return processing failed: " . $e->getMessage());
    }
}

// ==================== RENDER HTML ====================
$debug_logs[] = "=== RENDERING HTML ===";
$debug_logs[] = "Step: $step";
$debug_logs[] = "Success: " . ($success === null ? 'null' : ($success ? 'true' : 'false'));
$debug_logs[] = "Debug logs count: " . count($debug_logs);

// Force log output
error_log("About to render HTML. Step: $step, Logs: " . count($debug_logs));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return & Advance Payment Debug</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin-bottom: 10px; }
        .content { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 16px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #667eea; }
        .btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn:hover { opacity: 0.9; }
        .debug-logs { background: #1e1e1e; color: #00ff00; padding: 20px; border-radius: 6px; margin-top: 30px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6; max-height: 600px; overflow-y: auto; }
        .debug-logs div { margin-bottom: 3px; }
        .success { color: #00ff00; }
        .error { color: #ff4444; }
        .info { color: #44aaff; }
        .warning { color: #ffaa44; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .section { margin-bottom: 30px; }
        .section-title { font-size: 20px; font-weight: 600; margin-bottom: 15px; color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        th { background: #f8f9fa; font-weight: 600; }
        #calculation { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px; display: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Return & Advance Payment Debug</h1>
            <p>Comprehensive testing and debugging tool</p>
        </div>
        
        <div class="content">
            
            <?php if($step == 1): ?>
            <!-- STEP 1: Enter Invoice ID -->
            <div class="section">
                <h2 class="section-title">Step 1: Load Invoice</h2>
                <form method="POST" action="">
                    <input type="hidden" name="step" value="1">
                    <div class="form-group">
                        <label>Invoice ID:</label>
                        <input type="text" name="invoice_id" placeholder="Enter invoice ID from user_invoice table" required>
                    </div>
                    <button type="submit" class="btn">Load Invoice</button>
                </form>
            </div>
            
            <?php if($invoice && $items): ?>
            <!-- Invoice Details -->
            <div class="section">
                <h2 class="section-title">Invoice Details</h2>
                <table>
                    <tr><th>Number:</th><td><?= $invoice['inv_number'] ?></td></tr>
                    <tr><th>Date:</th><td><?= date('d/M/Y', strtotime($invoice['inv_date'])) ?></td></tr>
                    <tr><th>From:</th><td><?= $invoice['from_user_type'] ?> (<?= $invoice['from_user_id'] ?>)</td></tr>
                    <tr><th>To:</th><td><?= $invoice['to_user_type'] ?> (<?= $invoice['to_user_id'] ?>)</td></tr>
                </table>
            </div>
            
            <!-- STEP 2: Create Return -->
            <div class="section">
                <h2 class="section-title">Step 2: Create Return</h2>
                <form method="POST" action="">
                    <input type="hidden" name="step" value="2">
                    <input type="hidden" name="invoice_id" value="<?= $invoice['inv_id'] ?>">
                    
                    <div class="form-group">
                        <label>Product:</label>
                        <select name="product_id" required onchange="updateProduct(this)">
                            <option value="">-- Select Product --</option>
                            <?php foreach($items as $item): ?>
                            <option value="<?= $item['pr_id'] ?>" 
                                    data-qty="<?= $item['qty'] ?>"
                                    data-amount="<?= $item['amount'] ?>"
                                    data-gst="<?= $item['gst'] ?>">
                                <?= htmlspecialchars($item['productName']) ?> (Qty: <?= $item['qty'] ?>, MRP: Rs. <?= $item['amount'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="grid">
                        <div class="form-group">
                            <label>Return Quantity:</label>
                            <input type="number" name="return_qty" id="return_qty" min="0" step="0.01" required onkeyup="calc()">
                        </div>
                        <div class="form-group">
                            <label>Discount (Rs.):</label>
                            <input type="number" name="discount" id="discount" min="0" step="0.01" value="0" onkeyup="calc()">
                        </div>
                    </div>
                    
                    <div id="calculation">
                        <h3>Preview:</h3>
                        <div><strong>Subtotal:</strong> Rs. <span id="subtotal">0.00</span></div>
                        <div><strong>GST:</strong> Rs. <span id="gst">0.00</span> (<span id="gst_pct">0</span>%)</div>
                        <div><strong>Total before discount:</strong> Rs. <span id="total_before">0.00</span></div>
                        <div><strong>Discount:</strong> Rs. <span id="disc">0.00</span></div>
                        <div style="font-size: 18px; font-weight: bold; color: #667eea; margin-top: 10px;">
                            <strong>FINAL AMOUNT:</strong> Rs. <span id="final">0.00</span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">Process Return</button>
                </form>
            </div>
            <?php endif; ?>
            
            <?php elseif($step == 2): ?>
            <!-- STEP 2 Results -->
            <?php if($success): ?>
            <div class="alert alert-success">
                <h3>✓ Return Processed Successfully!</h3>
                <p>Return ID: <?= $return_id ?></p>
                <p>Amount: Rs. <?= inr_format($final_amount, 2) ?></p>
            </div>
            <?php else: ?>
            <div class="alert alert-error">
                <h3>✗ Processing Failed</h3>
                <p>Check debug logs below for details.</p>
            </div>
            <?php endif; ?>
            
            <a href="?" class="btn" style="display: inline-block; text-decoration: none; margin-bottom: 20px;">← New Return</a>
            <?php endif; ?>
            
            <!-- Debug Logs -->
            <?php if(!empty($debug_logs)): ?>
            <div class="section">
                <h2 class="section-title">Debug Logs</h2>
                <div class="debug-logs">
                    <?php foreach($debug_logs as $log): ?>
                        <div class="<?= 
                            (strpos($log, '✓') !== false || strpos($log, 'SUCCESS') !== false) ? 'success' : 
                            ((strpos($log, '✗') !== false || strpos($log, 'FAILED') !== false || strpos($log, 'ERROR') !== false) ? 'error' : 
                            ((strpos($log, '⚠') !== false) ? 'warning' :
                            ((strpos($log, 'ℹ') !== false || strpos($log, '===') !== false) ? 'info' : '')))
                        ?>">
                            <?= htmlspecialchars($log) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
    
    <script>
        let prod = null;
        function updateProduct(sel) {
            const opt = sel.options[sel.selectedIndex];
            prod = {
                qty: parseFloat(opt.dataset.qty),
                amount: parseFloat(opt.dataset.amount),
                gst: parseFloat(opt.dataset.gst)
            };
            document.getElementById('return_qty').max = prod.qty;
            calc();
        }
        
        function calc() {
            if(!prod) return;
            const qty = parseFloat(document.getElementById('return_qty').value) || 0;
            const disc = parseFloat(document.getElementById('discount').value) || 0;
            const subtotal = prod.amount * qty;
            const gst = subtotal * prod.gst / 100;
            const totalBefore = subtotal + gst;
            const final = totalBefore - disc;
            
            document.getElementById('subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('gst').textContent = gst.toFixed(2);
            document.getElementById('gst_pct').textContent = prod.gst;
            document.getElementById('total_before').textContent = totalBefore.toFixed(2);
            document.getElementById('disc').textContent = disc.toFixed(2);
            document.getElementById('final').textContent = final.toFixed(2);
            document.getElementById('calculation').style.display = 'block';
        }
    </script>
</body>
</html>