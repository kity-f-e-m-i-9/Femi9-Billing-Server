<?php
ob_start();
include("checksession.php");
include("config.php");

// CSRF check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    header("Location: add-tp-input-stock.php?error=1");
    exit;
}

$tp_db_id  = (int)($_POST['tp_db_id'] ?? 0);
$note      = trim($_POST['note'] ?? '');
$qty_map   = $_POST['qty'] ?? [];
$created_by = $_SESSION['username'] ?? 'system';

if ($tp_db_id <= 0 || !is_array($qty_map)) {
    header("Location: add-tp-input-stock.php?error=1");
    exit;
}

// Verify TP exists and is active
$stmt = $db_conn->prepare("SELECT id FROM territory_partners WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param("i", $tp_db_id);
$stmt->execute();
$tp_row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$tp_row) {
    header("Location: add-tp-input-stock.php?error=1");
    exit;
}

// Filter to products with a non-zero qty (positive = add, negative = reduce)
$entries = [];
foreach ($qty_map as $product_id => $qty) {
    $product_id = (int)$product_id;
    $qty        = (int)$qty;
    if ($product_id > 0 && $qty !== 0) {
        $entries[$product_id] = $qty;
    }
}

if (empty($entries)) {
    // No quantities entered — mark as initialized (all-zero first save is accepted) and done
    $db_conn->query("UPDATE territory_partners SET stock_initialized=1 WHERE id=$tp_db_id");
    header("Location: add-tp-input-stock.php?tp_db_id={$tp_db_id}&success=1");
    exit;
}

// Process each product inside a transaction
$db_conn->begin_transaction();
try {
    foreach ($entries as $product_id => $qty) {
        // Read current closing_qty for ledger (qty_before)
        $sel = $db_conn->prepare(
            "SELECT COALESCE(closing_qty, 0) AS closing_qty FROM territory_partner_stock WHERE territory_partner_id = ? AND product_id = ? LIMIT 1"
        );
        $sel->bind_param("ii", $tp_db_id, $product_id);
        $sel->execute();
        $row        = $sel->get_result()->fetch_assoc();
        $qty_before = $row ? (int)$row['closing_qty'] : 0;
        $sel->close();

        $qty_after = $qty_before + $qty;

        // Upsert territory_partner_stock
        $ins = $db_conn->prepare(
            "INSERT INTO territory_partner_stock (territory_partner_id, product_id, input_qty, closing_qty)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE input_qty = input_qty + VALUES(input_qty), closing_qty = closing_qty + VALUES(input_qty)"
        );
        $ins->bind_param("iiii", $tp_db_id, $product_id, $qty, $qty);
        $ins->execute();
        $ins->close();

        // Ledger record — deduct for reductions, credit for additions
        $action    = $qty < 0 ? 'deduct' : 'credit';
        $ledger_qty = abs($qty);
        $ref_type  = 'manual_input';
        $ref_id    = 'MANUAL';
        $led = $db_conn->prepare(
            "INSERT INTO territory_partner_stock_ledger
             (territory_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $led->bind_param(
            "iisiiissss",
            $tp_db_id, $product_id, $action, $ledger_qty, $qty_before, $qty_after,
            $ref_type, $ref_id, $note, $created_by
        );
        $led->execute();
        $led->close();
    }

    $db_conn->query("UPDATE territory_partners SET stock_initialized=1 WHERE id=$tp_db_id");
    $db_conn->commit();
    header("Location: add-tp-input-stock.php?tp_db_id={$tp_db_id}&success=1");
    exit;
} catch (Exception $e) {
    $db_conn->rollback();
    header("Location: add-tp-input-stock.php?tp_db_id={$tp_db_id}&error=1");
    exit;
}
