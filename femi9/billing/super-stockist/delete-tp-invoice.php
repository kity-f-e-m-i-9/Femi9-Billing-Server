<?php
ob_start();
include("checksession.php");
error_reporting(0);
require_once __DIR__ . '/../shared/TpAdvanceService.php';

if (($Login_user_TYPEvl ?? '') !== 'super_stockiest') {
    header("Location: manage-tp-invoices?error=unauthorized"); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage-tp-invoices?error=invalid"); exit;
}
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: manage-tp-invoices?error=csrf"); exit;
}

$enc    = $_POST['invoice_enc'] ?? '';
$inv_id = (int)base64_decode($enc);
if (!$inv_id) { header("Location: manage-tp-invoices?error=invalid"); exit; }

// Fetch invoice — ownership via TP.onboard_ss_id
$s = $db_conn->prepare("
    SELECT tpi.*, tp.onboard_ss_id
    FROM tp_invoices tpi
    JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
    WHERE tpi.id = ? AND tp.onboard_ss_id = ?
    LIMIT 1
");
$s->bind_param("is", $inv_id, $Login_user_IDvl);
$s->execute();
$inv = $s->get_result()->fetch_assoc();
$s->close();
if (!$inv) { header("Location: manage-tp-invoices?error=notfound"); exit; }

$tp_id      = (int)$inv['territory_partner_id'];
$inv_num    = $inv['invoice_number'];
$subtotal   = round((float)$inv['total_amount'] - (float)($inv['courier_charges'] ?? 0), 2);
$created_by = $_SESSION['LOGIN_USER'] ?? '';
$ss_id      = $Login_user_IDvl;

// Fetch line items
$s2 = $db_conn->prepare("SELECT * FROM tp_invoice_items WHERE tp_invoice_id=?");
$s2->bind_param("i", $inv_id); $s2->execute();
$items = $s2->get_result()->fetch_all(MYSQLI_ASSOC); $s2->close();

$db_conn->begin_transaction();
try {
    // Lock invoice row
    $lock = $db_conn->prepare("SELECT id FROM tp_invoices WHERE id=? FOR UPDATE");
    $lock->bind_param("i", $inv_id); $lock->execute();
    if (!$lock->get_result()->fetch_assoc()) {
        throw new \Exception("Invoice no longer exists.");
    }
    $lock->close();

    foreach ($items as $item) {
        $pid = (int)$item['product_id'];
        $qty = (int)$item['quantity'];

        // Restore SS stock
        $u = $db_conn->prepare("UPDATE stock SET sent_qty=GREATEST(0,sent_qty-?), closing_qty=closing_qty+? WHERE user_type='super_stockiest' AND user_id=? AND product_id=?");
        $u->bind_param("iisi", $qty, $qty, $ss_id, $pid); $u->execute(); $u->close();

        // SS ledger: reversal
        $before_s = $db_conn->prepare("SELECT closing_qty FROM stock WHERE user_type='super_stockiest' AND user_id=? AND product_id=?");
        $before_s->bind_param("si", $ss_id, $pid); $before_s->execute();
        $row_s = $before_s->get_result()->fetch_assoc(); $before_s->close();
        $after_ss      = $row_s ? (int)$row_s['closing_qty'] : 0;
        $before_ss_was = $after_ss - $qty;
        $utype = 'super_stockiest'; $act_s = 'transfer_in'; $ref_s = 'tp_invoice'; $note_s = 'Reversal: ' . $inv_num;
        $ins_s = $db_conn->prepare("INSERT INTO stock_ledger (product_id,user_type,user_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $ins_s->bind_param("isssiiissss", $pid, $utype, $ss_id, $act_s, $qty, $before_ss_was, $after_ss, $ref_s, $inv_num, $note_s, $created_by);
        $ins_s->execute(); $ins_s->close();

        // Debit TP stock
        $u2 = $db_conn->prepare("UPDATE territory_partner_stock SET input_qty=GREATEST(0,input_qty-?), closing_qty=GREATEST(0,closing_qty-?) WHERE territory_partner_id=? AND product_id=?");
        $u2->bind_param("iiii", $qty, $qty, $tp_id, $pid); $u2->execute(); $u2->close();

        // TP ledger: reversal
        $rt = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
        $rt->bind_param("ii", $tp_id, $pid); $rt->execute();
        $rowt = $rt->get_result()->fetch_assoc(); $rt->close();
        $after_tp      = $rowt ? (int)$rowt['closing_qty'] : 0;
        $before_tp_was = $after_tp + $qty;
        $act_t = 'deduct'; $ref_t = 'tp_invoice'; $note_t = 'Reversal: ' . $inv_num;
        $ins2 = $db_conn->prepare("INSERT INTO territory_partner_stock_ledger (territory_partner_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $ins2->bind_param("iisiiissss", $tp_id, $pid, $act_t, $qty, $before_tp_was, $after_tp, $ref_t, $inv_num, $note_t, $created_by);
        $ins2->execute(); $ins2->close();
    }

    // Restore advance balance — reverse exactly the payments that were deducted
    tpAdvanceRestore($db_conn, $inv_id);

    // Delete receipts (FK)
    $d0 = $db_conn->prepare("DELETE FROM tp_invoice_receipts WHERE tp_invoice_id=?");
    $d0->bind_param("i", $inv_id); $d0->execute(); $d0->close();

    // Delete items and invoice
    $d1 = $db_conn->prepare("DELETE FROM tp_invoice_items WHERE tp_invoice_id=?");
    $d1->bind_param("i", $inv_id); $d1->execute(); $d1->close();
    $d2 = $db_conn->prepare("DELETE FROM tp_invoices WHERE id=?");
    $d2->bind_param("i", $inv_id); $d2->execute();
    if ($d2->affected_rows === 0) throw new \Exception("Invoice was already deleted.");
    $d2->close();

    $db_conn->commit();
    header("Location: manage-tp-invoices?deleted=1&inv=" . urlencode($inv_num)); exit;

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("[SS delete-tp-invoice] Failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    header("Location: manage-tp-invoices?error=db"); exit;
}
