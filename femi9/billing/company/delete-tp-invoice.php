<?php
ob_start();
include("checksession.php");
error_reporting(0);
require_once __DIR__ . '/../shared/TpAdvanceService.php';

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: manage-tp-invoices?error=unauthorized"); exit;
}

// Require POST with CSRF — prevents GET-based re-triggering (back button, browser retry)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage-tp-invoices?error=invalid"); exit;
}
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: manage-tp-invoices?error=csrf"); exit;
}

$enc = $_POST['invoice_enc'] ?? '';
$inv_id = (int)base64_decode($enc);
if (!$inv_id) { header("Location: manage-tp-invoices?error=invalid"); exit; }

// Quick existence check before entering transaction
$s = $db_conn->prepare("SELECT * FROM tp_invoices WHERE id=? LIMIT 1");
$s->bind_param("i", $inv_id); $s->execute();
$inv = $s->get_result()->fetch_assoc(); $s->close();
if (!$inv) { header("Location: manage-tp-invoices?error=notfound"); exit; }

$tp_id         = (int)$inv['territory_partner_id'];
$source_loc_id = (int)$inv['source_location_id'];
$inv_num       = $inv['invoice_number'];
$subtotal      = round((float)$inv['total_amount'] - (float)($inv['courier_charges'] ?? 0), 2);
$created_by    = $_SESSION['LOGIN_USER'] ?? '';

// Fetch line items
$s2 = $db_conn->prepare("SELECT * FROM tp_invoice_items WHERE tp_invoice_id=?");
$s2->bind_param("i", $inv_id); $s2->execute();
$items = $s2->get_result()->fetch_all(MYSQLI_ASSOC); $s2->close();

// ── Transaction ────────────────────────────────────────────────────────────────
$db_conn->begin_transaction();
try {

    // Lock invoice row — prevents a second concurrent delete from also running reversals
    $lock = $db_conn->prepare("SELECT id FROM tp_invoices WHERE id=? FOR UPDATE");
    $lock->bind_param("i", $inv_id); $lock->execute();
    $locked = $lock->get_result()->fetch_assoc(); $lock->close();
    if (!$locked) {
        throw new \Exception("Invoice no longer exists — may have been deleted by another request.");
    }

    foreach ($items as $item) {
        $pid = (int)$item['product_id'];
        $qty = (int)$item['quantity'];

        // Restore stock to partner location (only if invoice has a real source location that still exists)
        if ($source_loc_id > 0) {
            $lchk = $db_conn->prepare("SELECT id FROM partner_location_nodes WHERE id=? LIMIT 1");
            $lchk->bind_param("i", $source_loc_id); $lchk->execute();
            $loc_exists = $lchk->get_result()->num_rows > 0; $lchk->close();

            if ($loc_exists) {
                $u = $db_conn->prepare("UPDATE partner_location_stock SET transfer_out_qty=GREATEST(0,transfer_out_qty-?), closing_qty=closing_qty+? WHERE partner_location_id=? AND product_id=?");
                $u->bind_param("iiii", $qty, $qty, $source_loc_id, $pid); $u->execute(); $u->close();

                // Location ledger: reversal entry
                $r = $db_conn->prepare("SELECT closing_qty FROM partner_location_stock WHERE partner_location_id=? AND product_id=?");
                $r->bind_param("ii", $source_loc_id, $pid); $r->execute();
                $row = $r->get_result()->fetch_assoc(); $r->close();
                $before_loc     = $row ? (int)$row['closing_qty'] : 0;
                $before_loc_was = $before_loc - $qty;
                $action_loc = 'transfer_in'; $ref_loc = 'tp_invoice';
                $ins = $db_conn->prepare("INSERT INTO partner_location_stock_ledger (partner_location_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $note_loc = 'Reversal: ' . $inv_num;
                $ins->bind_param("iisiisssss", $source_loc_id, $pid, $action_loc, $qty, $before_loc_was, $before_loc, $ref_loc, $inv_num, $note_loc, $created_by);
                $ins->execute(); $ins->close();
            }
        }

        // Debit TP stock
        $u2 = $db_conn->prepare("UPDATE territory_partner_stock SET input_qty=GREATEST(0,input_qty-?), closing_qty=GREATEST(0,closing_qty-?) WHERE territory_partner_id=? AND product_id=?");
        $u2->bind_param("iiii", $qty, $qty, $tp_id, $pid); $u2->execute(); $u2->close();

        // TP ledger: reversal
        $rt = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
        $rt->bind_param("ii", $tp_id, $pid); $rt->execute();
        $rowt = $rt->get_result()->fetch_assoc(); $rt->close();
        $after_tp      = $rowt ? (int)$rowt['closing_qty'] : 0;
        $before_tp_was = $after_tp + $qty;
        $action_tp = 'deduct'; $ref_tp = 'tp_invoice';
        $ins2 = $db_conn->prepare("INSERT INTO territory_partner_stock_ledger (territory_partner_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $note_tp = 'Reversal: ' . $inv_num;
        $ins2->bind_param("iisiisssss", $tp_id, $pid, $action_tp, $qty, $before_tp_was, $after_tp, $ref_tp, $inv_num, $note_tp, $created_by);
        $ins2->execute(); $ins2->close();
    }

    // Restore advance balance — reverse exactly the payments that were deducted
    tpAdvanceRestore($db_conn, $inv_id);

    // Delete receipts first (FK: tp_invoice_receipts → tp_invoices)
    $d0 = $db_conn->prepare("DELETE FROM tp_invoice_receipts WHERE tp_invoice_id=?");
    $d0->bind_param("i", $inv_id); $d0->execute(); $d0->close();

    // Delete items and invoice
    $d1 = $db_conn->prepare("DELETE FROM tp_invoice_items WHERE tp_invoice_id=?");
    $d1->bind_param("i", $inv_id); $d1->execute(); $d1->close();
    $d2 = $db_conn->prepare("DELETE FROM tp_invoices WHERE id=?");
    $d2->bind_param("i", $inv_id); $d2->execute();
    if ($d2->affected_rows === 0) {
        throw new \Exception("Invoice was already deleted by another request.");
    }
    $d2->close();

    $db_conn->commit();
    header("Location: manage-tp-invoices?deleted=1&inv=" . urlencode($inv_num)); exit;

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("[delete-tp-invoice] Failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    header("Location: manage-tp-invoices?error=db"); exit;
}
