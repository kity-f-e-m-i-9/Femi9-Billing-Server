<?php
declare(strict_types=1);

ob_start();
include("checksession.php");
include("config.php");
date_default_timezone_set("Asia/Kolkata");

error_reporting(0);

function validateCSRFTokenPLS(): bool {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function redirectPLS(string $url): void {
    header("Location: $url");
    exit();
}

/**
 * Get current closing_qty for a location+product. Returns 0 if no row exists.
 */
function getCurrentQty(mysqli $db, int $location_id, int $product_id): int {
    $stmt = $db->prepare("SELECT closing_qty FROM partner_location_stock WHERE partner_location_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $location_id, $product_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['closing_qty'] : 0;
}

/**
 * Lock row and get current closing_qty inside a transaction. Returns 0 if no row exists.
 */
function lockAndGetCurrentQty(mysqli $db, int $location_id, int $product_id): int {
    $stmt = $db->prepare("SELECT closing_qty FROM partner_location_stock WHERE partner_location_id = ? AND product_id = ? FOR UPDATE");
    $stmt->bind_param("ii", $location_id, $product_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['closing_qty'] : 0;
}

/**
 * Upsert snapshot row after a credit (input or transfer_in).
 */
function applyCredit(mysqli $db, int $location_id, int $product_id, int $qty, string $col): void {
    // $col is 'input_qty' or 'transfer_in_qty'
    $stmt = $db->prepare("
        INSERT INTO partner_location_stock (partner_location_id, product_id, {$col}, closing_qty)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            {$col}      = {$col} + VALUES({$col}),
            closing_qty = closing_qty + VALUES({$col})
    ");
    $stmt->bind_param("iiii", $location_id, $product_id, $qty, $qty);
    $stmt->execute();
    $stmt->close();
}

/**
 * Update snapshot row after a debit (transfer_out or deduct).
 */
function applyDebit(mysqli $db, int $location_id, int $product_id, int $qty, string $col): void {
    $stmt = $db->prepare("
        UPDATE partner_location_stock
        SET {$col} = {$col} + ?, closing_qty = closing_qty - ?
        WHERE partner_location_id = ? AND product_id = ?
    ");
    $stmt->bind_param("iiii", $qty, $qty, $location_id, $product_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Insert a ledger entry.
 */
function insertLedger(
    mysqli $db,
    int    $location_id,
    int    $product_id,
    string $action,
    int    $qty,
    int    $qty_before,
    int    $qty_after,
    string $ref_type,
    string $ref_id,
    string $note,
    string $created_by
): void {
    $stmt = $db->prepare("
        INSERT INTO partner_location_stock_ledger
            (partner_location_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisiiiisss", $location_id, $product_id, $action, $qty, $qty_before, $qty_after, $ref_type, $ref_id, $note, $created_by);
    $stmt->execute();
    $stmt->close();
}

// ── INPUT STOCK ───────────────────────────────────────────────────────────────
if (isset($_POST['add-pl-stock'])) {

    if (!validateCSRFTokenPLS()) {
        redirectPLS('overall-partner-location-stock?csrf_error');
    }

    $location_id = filter_var($_POST['location_id'] ?? 0, FILTER_VALIDATE_INT);
    $ref_id      = trim($_POST['ref_id'] ?? '');
    $input_date  = trim($_POST['input_date'] ?? date('Y-m-d'));
    $created_by  = $Login_user_IDvl ?? '';

    $product_ids = $_POST['product_id'] ?? [];
    $input_qtys  = $_POST['input_qty']  ?? [];
    $notes       = $_POST['note']       ?? [];

    if (!$location_id || empty($product_ids)) {
        redirectPLS("add-partner-location-stock?location_id=$location_id&error");
    }

    $db_conn->begin_transaction();

    try {
        foreach ($product_ids as $i => $raw_pid) {
            $product_id = (int)$raw_pid;
            $qty        = (int)($input_qtys[$i] ?? 0);
            $note       = trim($notes[$i] ?? '');

            if ($product_id < 1 || $qty < 1) continue;

            $qty_before = getCurrentQty($db_conn, $location_id, $product_id);
            $qty_after  = $qty_before + $qty;

            applyCredit($db_conn, $location_id, $product_id, $qty, 'input_qty');
            insertLedger($db_conn, $location_id, $product_id, 'credit', $qty, $qty_before, $qty_after, 'input', $ref_id, $note, $created_by);
        }

        $db_conn->commit();
        redirectPLS("view-partner-location-stock?location_id=$location_id&addesuccess");

    } catch (Exception $e) {
        $db_conn->rollback();
        redirectPLS("add-partner-location-stock?location_id=$location_id&error");
    }
}

// ── TRANSFER STOCK ────────────────────────────────────────────────────────────
if (isset($_POST['transfer-pl-stock'])) {

    if (!validateCSRFTokenPLS()) {
        redirectPLS('overall-partner-location-stock?csrf_error');
    }

    $from_id        = filter_var($_POST['from_location_id'] ?? 0, FILTER_VALIDATE_INT);
    $to_id          = filter_var($_POST['to_location_id']   ?? 0, FILTER_VALIDATE_INT);
    $ref_id         = trim($_POST['ref_id'] ?? '');
    $transfer_date  = trim($_POST['transfer_date'] ?? date('Y-m-d'));
    $created_by     = $Login_user_IDvl ?? '';

    $product_ids    = $_POST['product_id']    ?? [];
    $transfer_qtys  = $_POST['transfer_qty']  ?? [];
    $notes          = $_POST['note']          ?? [];

    if (!$from_id || !$to_id || $from_id === $to_id || empty($product_ids)) {
        redirectPLS("transfer-partner-location-stock?location_id=$from_id&error");
    }

    // Pre-validate all products have sufficient stock before touching DB
    foreach ($product_ids as $i => $raw_pid) {
        $product_id = (int)$raw_pid;
        $qty        = (int)($transfer_qtys[$i] ?? 0);
        if ($product_id < 1 || $qty < 1) continue;
        $available = getCurrentQty($db_conn, $from_id, $product_id);
        if ($qty > $available) {
            redirectPLS("transfer-partner-location-stock?location_id=$from_id&insufficient");
        }
    }

    $db_conn->begin_transaction();

    try {
        foreach ($product_ids as $i => $raw_pid) {
            $product_id = (int)$raw_pid;
            $qty        = (int)($transfer_qtys[$i] ?? 0);
            $note       = trim($notes[$i] ?? '');

            if ($product_id < 1 || $qty < 1) continue;

            // Source: lock row, re-validate, then transfer_out
            $from_before = lockAndGetCurrentQty($db_conn, $from_id, $product_id);
            if ($qty > $from_before) {
                throw new Exception("Insufficient stock for product $product_id after lock.");
            }
            $from_after  = $from_before - $qty;
            applyDebit($db_conn, $from_id, $product_id, $qty, 'transfer_out_qty');
            insertLedger($db_conn, $from_id, $product_id, 'transfer_out', $qty, $from_before, $from_after, 'transfer', $ref_id, $note, $created_by);

            // Destination: transfer_in
            $to_before = getCurrentQty($db_conn, $to_id, $product_id);
            $to_after  = $to_before + $qty;
            applyCredit($db_conn, $to_id, $product_id, $qty, 'transfer_in_qty');
            insertLedger($db_conn, $to_id, $product_id, 'transfer_in', $qty, $to_before, $to_after, 'transfer', $ref_id, $note, $created_by);
        }

        $db_conn->commit();
        redirectPLS("view-partner-location-stock?location_id=$from_id&transfersuccess");

    } catch (Exception $e) {
        $db_conn->rollback();
        redirectPLS("transfer-partner-location-stock?location_id=$from_id&error");
    }
}

redirectPLS('overall-partner-location-stock');
