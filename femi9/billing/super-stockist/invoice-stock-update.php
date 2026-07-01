<?php
/**
 * Invoice Stock Update Handler
 * Femi9 Billing Application
 * 
 * This file handles stock updates when an invoice is submitted.
 * It processes all invoice items and updates stock for both company and customer.
 * 
 * USAGE: Include this file in user-invoice-submit.php after invoice validation
 * 
 * @version 1.0
 * @date 2025-02-05
 */

// This file should only be included, not accessed directly
if (!defined('INVOICE_STOCK_UPDATE_INCLUDED')) {
    die('Direct access not permitted');
}

// Enable error logging
error_log("=== STOCK UPDATE PROCESS START ===");
error_log("Invoice ID: $invoice_id");

// ============================================================================
// VALIDATE REQUIRED VARIABLES
// ============================================================================

if (empty($invoice_id) || empty($db_conn)) {
    error_log("FATAL: Required variables missing for stock update");
    die("Error: Required variables missing");
}

// ============================================================================
// GET INVOICE DETAILS
// ============================================================================


if ($is_customer_invoice) {
    $invoice_table = 'invoice';
    $invoice_items_table = 'invoice_items';
} else {
    $invoice_table = 'user_invoice';
    $invoice_items_table = 'user_invoice_items';
}

$stmt_inv = $db_conn->prepare("SELECT * FROM $invoice_table WHERE inv_id = ?");
if (!$stmt_inv) {
    error_log("FATAL: Failed to prepare invoice query: " . $db_conn->error);
    die("Database error occurred");
}

$stmt_inv->bind_param("s", $invoice_id);
$stmt_inv->execute();
$invoice_details = $stmt_inv->get_result()->fetch_assoc();
$stmt_inv->close();

if (!$invoice_details) {
    error_log("FATAL: Invoice not found: $invoice_id");
    die("Error: Invoice not found");
}

if ($is_customer_invoice) {
$company_id = $invoice_details['user_id'];
$company_type = $invoice_details['user_type'];
$customer_id = $invoice_details['customer_id'];
$customer_type = "customer";
$invoice_date = $invoice_details['date'];
} else {
$company_id = $invoice_details['from_user_id'];
$company_type = $invoice_details['from_user_type'];
$customer_id = $invoice_details['to_user_id'];
$customer_type = $invoice_details['to_user_type'];
$invoice_date = $invoice_details['date'];    
}    


error_log("Company: $company_id ($company_type)");
error_log("Customer: $customer_id ($customer_type)");

// ============================================================================
// DETERMINE STOCK BEHAVIOR BASED ON CUSTOMER TYPE
// ============================================================================

// Customer types that maintain stock inventory
$stock_maintaining_types = ['super_stockiest', 'stockiest', 'super_distributor', 'distributor', 'candf'];

// Check if customer maintains stock
$customer_maintains_stock = in_array($customer_type, $stock_maintaining_types);

if ($customer_maintains_stock) {
    error_log("Customer type '$customer_type' MAINTAINS STOCK - will increment customer stock");
    echo "<script>console.log('customer_maintains_stock MAINTAINS STOCK : $customer_type');</script>";
} else {
    error_log("Customer type '$customer_type' DOES NOT MAINTAIN STOCK - will only decrement seller stock");
    echo "<script>console.log('customer_maintains_stock DOES NOT MAINTAIN STOC: $customer_type');</script>";
}

// ============================================================================
// GET ALL INVOICE ITEMS
// ============================================================================

if ($is_customer_invoice) {
    // For customer invoices - simpler query
    $stmt_items = $db_conn->prepare("
        SELECT * FROM $invoice_items_table 
        WHERE inv_id = ?
    ");
    
    if (!$stmt_items) {
        error_log("FATAL: Failed to prepare items query: " . $db_conn->error);
        die("Database error occurred");
    }
    
    $stmt_items->bind_param("s", $invoice_id);
    
    error_log("Executing customer invoice items query with params:");
    error_log("  - inv_id: $invoice_id");
    
} else {
    // For user invoices - detailed query with user types
    $stmt_items = $db_conn->prepare("
        SELECT * FROM $invoice_items_table 
        WHERE inv_id = ? 
        AND from_user_type = ? 
        AND from_user_id = ?
        AND to_user_type = ?
        AND to_user_id = ?
    ");
    
    if (!$stmt_items) {
        error_log("FATAL: Failed to prepare items query: " . $db_conn->error);
        die("Database error occurred");
    }
    
    $stmt_items->bind_param("sssss", $invoice_id, $company_type, $company_id, $customer_type, $customer_id);
    
    error_log("Executing user invoice items query with params:");
    error_log("  - inv_id: $invoice_id");
    error_log("  - company_type: $company_type");
    error_log("  - company_id: $company_id");
    error_log("  - customer_type: $customer_type");
    error_log("  - customer_id: $customer_id");
}

$stmt_items->execute();
$result_items = $stmt_items->get_result();

// DON'T CLOSE STATEMENT YET - we need to fetch all rows first
$total_items = $result_items->num_rows;
error_log("Query returned $total_items rows");

if ($total_items == 0) {
    $stmt_items->close(); // Close here if no items
    error_log("WARNING: No items found in invoice");
    error_log("=== STOCK UPDATE PROCESS END (No Items) ===");
    return; // Exit gracefully
}

// Fetch all items into an array so we can close the statement
$invoice_items = [];
$fetch_count = 0;
while ($row = $result_items->fetch_assoc()) {
    $fetch_count++;
    error_log("Fetching row $fetch_count - Product ID: {$row['pr_id']}, Qty: {$row['qty']}");
    $invoice_items[] = $row;
}

// Now we can safely close the statement
$stmt_items->close();

error_log("Successfully fetched " . count($invoice_items) . " items into array");
error_log("Array contents:");
foreach ($invoice_items as $idx => $item) {
    error_log("  Item " . ($idx + 1) . ": Product {$item['pr_id']}, Qty {$item['qty']}");
}

// ============================================================================
// START TRANSACTION FOR STOCK UPDATES
// ============================================================================

$db_conn->begin_transaction();

try {
    $items_processed = 0;
    $stock_updates_success = 0;
    $stock_updates_failed = 0;
    
    // Process each invoice item from the array
    foreach ($invoice_items as $item) {
        $items_processed++;
        $pr_id = $item['pr_id'];
        $qty = floatval($item['qty']);
        
        error_log("Processing Item #$items_processed: Product $pr_id, Qty: $qty");
        
        // ====================================================================
        // STEP 1: UPDATE COMPANY STOCK (DECREMENT)
        // ====================================================================
        
        error_log("  → Updating company stock (decrement)");
        
        // Get current company stock
        $stmt_company_stock = $db_conn->prepare("
            SELECT * FROM stock 
            WHERE product_id = ? 
            AND user_type = ? 
            AND user_id = ?
        ");
        
        if (!$stmt_company_stock) {
            throw new Exception("Failed to prepare company stock query: " . $db_conn->error);
        }
        
        $stmt_company_stock->bind_param("sss", $pr_id, $company_type, $company_id);
        $stmt_company_stock->execute();
        $company_stock = $stmt_company_stock->get_result()->fetch_assoc();
        $stmt_company_stock->close();
        
        if (!$company_stock) {
            error_log("  ✗ FATAL: Company stock record not found for product $pr_id");
            throw new Exception("Company stock record not found for product: $pr_id");
        }
        
        // Calculate new stock values for company
        $new_sales_qty = floatval($company_stock['sales_qty']) + $qty;
        $new_closing_qty = floatval($company_stock['closing_qty']) - $qty;
        
        // Validate stock availability
        if ($new_closing_qty < 0) {
            error_log("  ✗ FATAL: Insufficient stock - Product $pr_id");
            error_log("    Available: {$company_stock['closing_qty']}, Required: $qty");
            throw new Exception("Insufficient stock for product: $pr_id");
        }
        
        // Update company stock
        $stmt_update_company = $db_conn->prepare("
            UPDATE stock 
            SET sales_qty = ?, closing_qty = ?
            WHERE product_id = ? 
            AND user_type = ? 
            AND user_id = ?
        ");
        
        if (!$stmt_update_company) {
            throw new Exception("Failed to prepare company stock update: " . $db_conn->error);
        }
        
        $stmt_update_company->bind_param(
            "ddsss",
            $new_sales_qty,
            $new_closing_qty,
            $pr_id,
            $company_type,
            $company_id
        );
        
        if (!$stmt_update_company->execute()) {
            throw new Exception("Failed to update company stock: " . $stmt_update_company->error);
        }
        $stmt_update_company->close();
        
        error_log("  ✓ Company stock updated - Sales: $new_sales_qty, Closing: $new_closing_qty");
        
        // ====================================================================
        // STEP 2 & 3: CUSTOMER STOCK - CONDITIONAL BASED ON CUSTOMER TYPE
        // ====================================================================
        
        if ($customer_maintains_stock) {
            // Customer maintains stock - create/update their inventory
            error_log("  → Processing customer stock (customer maintains inventory)");
            
            // Check if customer stock record exists
            $stmt_check_customer = $db_conn->prepare("
                SELECT COUNT(*) as count 
                FROM stock 
                WHERE product_id = ? 
                AND user_type = ? 
                AND user_id = ?
            ");
            
            if (!$stmt_check_customer) {
                throw new Exception("Failed to prepare customer stock check: " . $db_conn->error);
            }
            
            $stmt_check_customer->bind_param("sss", $pr_id, $customer_type, $customer_id);
            $stmt_check_customer->execute();
            $customer_stock_exists = $stmt_check_customer->get_result()->fetch_assoc();
            $stmt_check_customer->close();
            
            if ($customer_stock_exists['count'] == 0) {
                // Create stock record for customer
                error_log("  → Creating customer stock record");
                
                $stmt_create_customer = $db_conn->prepare("
                    INSERT INTO stock (
                        product_id, 
                        opening_qty, 
                        opening_date, 
                        input_qty, 
                        sales_qty,
                        sent_qty, 
                        returnqty, 
                        closing_qty, 
                        user_type, 
                        user_id
                    ) VALUES (?, 0, ?, 0, 0, 0, 0, 0, ?, ?)
                ");
                
                if (!$stmt_create_customer) {
                    throw new Exception("Failed to prepare customer stock creation: " . $db_conn->error);
                }
                
                $stmt_create_customer->bind_param("ssss", $pr_id, $invoice_date, $customer_type, $customer_id);
                
                if (!$stmt_create_customer->execute()) {
                    throw new Exception("Failed to create customer stock record: " . $stmt_create_customer->error);
                }
                $stmt_create_customer->close();
                
                error_log("  ✓ Customer stock record created");
            }
            
            // Get current customer stock
            $stmt_customer_stock = $db_conn->prepare("
                SELECT * FROM stock 
                WHERE product_id = ? 
                AND user_type = ? 
                AND user_id = ?
            ");
            
            if (!$stmt_customer_stock) {
                throw new Exception("Failed to prepare customer stock query: " . $db_conn->error);
            }
            
            $stmt_customer_stock->bind_param("sss", $pr_id, $customer_type, $customer_id);
            $stmt_customer_stock->execute();
            $customer_stock = $stmt_customer_stock->get_result()->fetch_assoc();
            $stmt_customer_stock->close();
            
            if (!$customer_stock) {
                throw new Exception("Customer stock record not found after creation");
            }
            
            // Calculate new stock values for customer
            $new_input_qty = floatval($customer_stock['input_qty']) + $qty;
            $new_customer_closing = floatval($customer_stock['closing_qty']) + $qty;
            
            // Update customer stock
            $stmt_update_customer = $db_conn->prepare("
                UPDATE stock 
                SET input_qty = ?, closing_qty = ?
                WHERE product_id = ? 
                AND user_type = ? 
                AND user_id = ?
            ");
            
            if (!$stmt_update_customer) {
                throw new Exception("Failed to prepare customer stock update: " . $db_conn->error);
            }
            
            $stmt_update_customer->bind_param(
                "ddsss",
                $new_input_qty,
                $new_customer_closing,
                $pr_id,
                $customer_type,
                $customer_id
            );
            
            if (!$stmt_update_customer->execute()) {
                throw new Exception("Failed to update customer stock: " . $stmt_update_customer->error);
            }
            $stmt_update_customer->close();
            
            error_log("  ✓ Customer stock updated - Input: $new_input_qty, Closing: $new_customer_closing");
            
        } else {
            // Customer doesn't maintain stock (e.g., outlet, shop)
            error_log("  → Skipping customer stock update (customer type doesn't maintain inventory)");
        }
        
        $stock_updates_success++;
        error_log("  ✓ Item #$items_processed processed successfully");
    }
    
    // ========================================================================
    // COMMIT TRANSACTION
    // ========================================================================
    
    $db_conn->commit();
    
    error_log(str_repeat("=", 80));
    error_log("=== STOCK UPDATE PROCESS COMPLETED SUCCESSFULLY ===");
    error_log("Total Items Processed: $items_processed");
    error_log("Stock Updates Successful: $stock_updates_success");
    error_log("Stock Updates Failed: $stock_updates_failed");
    error_log(str_repeat("=", 80));
    
    
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db_conn->rollback();
    
    error_log(str_repeat("!", 80));
    error_log("=== STOCK UPDATE PROCESS FAILED ===");
    error_log("ERROR: " . $e->getMessage());
    error_log("Items Processed Before Error: $items_processed");
    error_log(str_repeat("!", 80));
    
    // Log detailed error for debugging
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Throw error to be caught by calling script
    throw new Exception("Stock update failed: " . $e->getMessage());
}

// ============================================================================
// END OF STOCK UPDATE PROCESS
// ============================================================================
?>