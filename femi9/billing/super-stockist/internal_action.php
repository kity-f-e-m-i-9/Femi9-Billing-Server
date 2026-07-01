<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

// Enable error logging (not display)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log('Internal transfer processing started');

// Process transfer
if (isset($_POST['addInvoice'])) {
    
    // Sanitize inputs
    $tempid = mysqli_real_escape_string($db_conn, trim($_POST['tempid']));
    $from_usertype = mysqli_real_escape_string($db_conn, trim($_POST['from_usertype']));
    $from_userid = mysqli_real_escape_string($db_conn, trim($_POST['from_userid']));
    $to_usertype = mysqli_real_escape_string($db_conn, trim($_POST['to_usertype']));
    $to_userid = mysqli_real_escape_string($db_conn, trim($_POST['to_userid']));
    $prid = intval($_POST['prid']);
    $qty = intval($_POST['qty']);
    
    // Validate and format date
    $date_input = $_POST['date'];
    $date = date('Y-m-d', strtotime($date_input));
    
    // Validate quantity
    if ($qty <= 0) {
        $_SESSION['errorMessage'] = "Quantity must be greater than zero!";
        header("Location: add_internal.php?to_usertype=" . urlencode($to_usertype));
        exit;
    }
    
    // Validate date (no future dates)
    if ($date > date('Y-m-d')) {
        $_SESSION['errorMessage'] = "Future dates are not allowed!";
        header("Location: add_internal.php?to_usertype=" . urlencode($to_usertype));
        exit;
    }
    
    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    // Start database transaction
    mysqli_begin_transaction($db_conn);

    try {
        
        // ============================================
        // STEP 1: Check sender's available stock
        // ============================================
        $stmt_check_stock = $db_conn->prepare(
            "SELECT id, closing_qty, sent_qty 
             FROM stock 
             WHERE product_id = ? AND user_type = ? AND user_id = ? 
             ORDER BY id DESC 
             LIMIT 1 
             FOR UPDATE"
        );
        
        if (!$stmt_check_stock) {
            throw new Exception("Database prepare error: " . $db_conn->error);
        }
        
        $stmt_check_stock->bind_param("iss", $prid, $from_usertype, $from_userid);
        $stmt_check_stock->execute();
        $result_check_stock = $stmt_check_stock->get_result();
        
        if ($result_check_stock->num_rows === 0) {
            throw new Exception("Sender does not have this product in stock!");
        }
        
        $sender_stock = $result_check_stock->fetch_assoc();
        $available_stock = $sender_stock['closing_qty'];
        $sender_stock_id = $sender_stock['id'];
        $sender_sent_qty = $sender_stock['sent_qty'];
        
        $stmt_check_stock->close();
        
        // Validate sufficient stock
        if ($available_stock < $qty) {
            throw new Exception("Insufficient stock! Available: {$available_stock}, Requested: {$qty}");
        }
        
        
        // ============================================
        // STEP 2: Check for duplicate transfer
        // ============================================
        $stmt_check_duplicate = $db_conn->prepare(
            "SELECT COUNT(*) as count 
             FROM internal_transfer_ss 
             WHERE tempid = ? AND prid = ?"
        );
        
        if (!$stmt_check_duplicate) {
            throw new Exception("Database prepare error: " . $db_conn->error);
        }
        
        $stmt_check_duplicate->bind_param("si", $tempid, $prid);
        $stmt_check_duplicate->execute();
        $result_duplicate = $stmt_check_duplicate->get_result();
        $row_duplicate = $result_duplicate->fetch_assoc();
        $stmt_check_duplicate->close();
        
        if ($row_duplicate['count'] > 0) {
            throw new Exception("Duplicate transfer detected! This transfer already exists.");
        }
        
        
        // ============================================
        // STEP 3: Insert transfer record
        // ============================================
        $stmt_insert_transfer = $db_conn->prepare(
            "INSERT INTO internal_transfer_ss 
             (tempid, prid, qty, date, from_usertype, from_userid, to_usertype, to_userid) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        if (!$stmt_insert_transfer) {
            throw new Exception("Database prepare error: " . $db_conn->error);
        }
        
        $stmt_insert_transfer->bind_param(
            "siisssss", 
            $tempid, $prid, $qty, $date, 
            $from_usertype, $from_userid, $to_usertype, $to_userid
        );
        
        if (!$stmt_insert_transfer->execute()) {
            throw new Exception("Failed to create transfer record: " . $stmt_insert_transfer->error);
        }
        
        $stmt_insert_transfer->close();
        
        
        // ============================================
        // STEP 4: Update sender's stock + ledger (DEDUCT via StockService)
        // ============================================
        $stockService->transferOut(
            $prid, $from_usertype, $from_userid, $qty,
            'transfer', $tempid, $createdBy,
            true // externalTransaction — transaction already open
        );
        
        
        // ============================================
        // STEP 5: Update receiver's stock + ledger (CREDIT)
        // TPs use territory_partner_stock; others use the generic stock table.
        // ============================================
        if ($to_usertype === 'territory_partner') {
            $tp_id = (int) $to_userid;

            // Get current TP stock (lock for update)
            $stmt_tp_lock = $db_conn->prepare(
                "SELECT closing_qty FROM territory_partner_stock
                  WHERE territory_partner_id = ? AND product_id = ? FOR UPDATE"
            );
            $stmt_tp_lock->bind_param("ii", $tp_id, $prid);
            $stmt_tp_lock->execute();
            $tp_row = $stmt_tp_lock->get_result()->fetch_assoc();
            $stmt_tp_lock->close();

            $tp_before = $tp_row ? (int) $tp_row['closing_qty'] : 0;
            $tp_after  = $tp_before + $qty;

            // Upsert TP stock
            $stmt_tp_credit = $db_conn->prepare(
                "INSERT INTO territory_partner_stock (territory_partner_id, product_id, input_qty, closing_qty)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE input_qty = input_qty + VALUES(input_qty), closing_qty = closing_qty + VALUES(input_qty)"
            );
            $stmt_tp_credit->bind_param("iiii", $tp_id, $prid, $qty, $qty);
            if (!$stmt_tp_credit->execute()) {
                throw new Exception("Failed to credit TP stock: " . $stmt_tp_credit->error);
            }
            $stmt_tp_credit->close();

            // Write TP stock ledger
            $tp_action  = 'internal_transfer_in';
            $tp_reftype = 'internal_transfer';
            $tp_note    = '';
            $stmt_tp_ledger = $db_conn->prepare(
                "INSERT INTO territory_partner_stock_ledger
                    (territory_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt_tp_ledger->bind_param("iisiiissss", $tp_id, $prid, $tp_action, $qty, $tp_before, $tp_after, $tp_reftype, $tempid, $tp_note, $createdBy);
            if (!$stmt_tp_ledger->execute()) {
                throw new Exception("Failed to write TP stock ledger: " . $stmt_tp_ledger->error);
            }
            $stmt_tp_ledger->close();

        } else {
            $stockService->transferIn(
                $prid, $to_usertype, $to_userid, $qty,
                'transfer', $tempid, $createdBy,
                true // externalTransaction — transaction already open
            );
        }
        
        
        // ============================================
        // STEP 6: Commit transaction - SUCCESS!
        // ============================================
        mysqli_commit($db_conn);
        
        $_SESSION['sucMessage'] = "Internal Stock Transfer Details Added Successfully!";
        header("Location: manage_internal.php");
        exit;
        
        
    } catch (Exception $e) {
        
        // ============================================
        // ERROR HANDLING: Rollback all changes
        // ============================================
        mysqli_rollback($db_conn);
        
        error_log("Internal transfer failed: " . $e->getMessage());
        
        $_SESSION['errorMessage'] = $e->getMessage();
        header("Location: add_internal.php?to_usertype=" . urlencode($to_usertype) . "&InvalidStock");
        exit;
    }
    
} else {
    // Direct access without form submission
    header("Location: add_internal.php");
    exit;
}
?>