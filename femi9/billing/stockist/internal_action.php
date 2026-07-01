<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!isset($_POST['addInvoice'])) {
    header("Location: add_internal.php");
    exit;
}

// Sanitize inputs
$tempid        = preg_replace('/[^A-Z0-9\/]/', '', strtoupper(trim($_POST['tempid'] ?? '')));
$from_usertype = (string) trim($_POST['from_usertype'] ?? '');
$from_userid   = (string) trim($_POST['from_userid']   ?? '');
$to_usertype   = (string) trim($_POST['to_usertype']   ?? '');
$to_userid     = (string) trim($_POST['to_userid']     ?? '');
$prid          = (int)    ($_POST['prid'] ?? 0);
$qty           = (int)    ($_POST['qty']  ?? 0);

$date_input = $_POST['date'] ?? '';
$date = date('Y-m-d', strtotime($date_input));

if ($qty <= 0) {
    $_SESSION['errorMessage'] = "Quantity must be greater than zero!";
    header("Location: add_internal.php?to_usertype=" . urlencode($to_usertype));
    exit;
}

if ($date > date('Y-m-d')) {
    $_SESSION['errorMessage'] = "Future dates are not allowed!";
    header("Location: add_internal.php?to_usertype=" . urlencode($to_usertype));
    exit;
}

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

mysqli_begin_transaction($db_conn);

try {
    // STEP 1: Check sender's available stock (FOR UPDATE lock)
    $stmt_check = $db_conn->prepare(
        "SELECT id, closing_qty, sent_qty
           FROM stock
          WHERE product_id = ? AND user_type = ? AND user_id = ?
          ORDER BY id DESC LIMIT 1 FOR UPDATE"
    );
    if (!$stmt_check) {
        throw new Exception("Database prepare error: " . $db_conn->error);
    }
    $stmt_check->bind_param("iss", $prid, $from_usertype, $from_userid);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 0) {
        throw new Exception("Sender does not have this product in stock!");
    }

    $sender_stock    = $result_check->fetch_assoc();
    $available_stock = $sender_stock['closing_qty'];
    $stmt_check->close();

    if ($available_stock < $qty) {
        throw new Exception("Insufficient stock! Available: {$available_stock}, Requested: {$qty}");
    }

    // STEP 2: Check for duplicate transfer
    $stmt_dup = $db_conn->prepare(
        "SELECT COUNT(*) AS count FROM internal_transfer_ss WHERE tempid = ? AND prid = ?"
    );
    if (!$stmt_dup) {
        throw new Exception("Database prepare error: " . $db_conn->error);
    }
    $stmt_dup->bind_param("si", $tempid, $prid);
    $stmt_dup->execute();
    $dup_count = (int) $stmt_dup->get_result()->fetch_assoc()['count'];
    $stmt_dup->close();

    if ($dup_count > 0) {
        throw new Exception("Duplicate transfer detected! This transfer already exists.");
    }

    // STEP 3: Insert transfer record
    $stmt_ins = $db_conn->prepare(
        "INSERT INTO internal_transfer_ss
             (tempid, prid, qty, date, from_usertype, from_userid, to_usertype, to_userid)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt_ins) {
        throw new Exception("Database prepare error: " . $db_conn->error);
    }
    $stmt_ins->bind_param("siisssss",
        $tempid, $prid, $qty, $date,
        $from_usertype, $from_userid, $to_usertype, $to_userid
    );
    if (!$stmt_ins->execute()) {
        throw new Exception("Failed to create transfer record: " . $stmt_ins->error);
    }
    $stmt_ins->close();

    // STEP 4: Deduct sender's stock + ledger
    $stockService->transferOut(
        $prid, $from_usertype, $from_userid, $qty,
        'internal_transfer', $tempid, $createdBy,
        true // externalTransaction — transaction already open
    );

    // STEP 5: Credit receiver's stock + ledger (creates row if none exists)
    $stockService->transferIn(
        $prid, $to_usertype, $to_userid, $qty,
        'internal_transfer', $tempid, $createdBy,
        true // externalTransaction — transaction already open
    );

    // STEP 6: Commit
    mysqli_commit($db_conn);

    $_SESSION['sucMessage'] = "Internal Stock Transfer Details Added Successfully!";
    header("Location: manage_internal.php");
    exit;

} catch (Exception $e) {
    mysqli_rollback($db_conn);
    error_log("Internal transfer failed: " . $e->getMessage());
    $_SESSION['errorMessage'] = $e->getMessage();
    header("Location: add_internal.php?to_usertype=" . urlencode($to_usertype) . "&InvalidStock");
    exit;
}
?>
