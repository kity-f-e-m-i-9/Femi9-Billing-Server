<?php
ob_start();
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: manage-cp-invoices?error=unauthorized"); exit;
}

// Require POST with CSRF — prevents GET-based re-triggering (back button, browser retry)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage-cp-invoices?error=invalid"); exit;
}
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: manage-cp-invoices?error=csrf"); exit;
}

$enc = $_POST['invoice_enc'] ?? '';
$inv_id = (int)base64_decode($enc);
if (!$inv_id) { header("Location: manage-cp-invoices?error=invalid"); exit; }

// Quick existence check before entering transaction
$s = $db_conn->prepare("SELECT id, invoice_number FROM cp_invoices WHERE id=? LIMIT 1");
$s->bind_param("i", $inv_id); $s->execute();
$inv = $s->get_result()->fetch_assoc(); $s->close();
if (!$inv) { header("Location: manage-cp-invoices?error=notfound"); exit; }

$inv_num = $inv['invoice_number'];

// ── Transaction ──────────────────────────────────────────────────────────
// Deliberately narrow: this reverses only the paper/invoice record, never
// stock. The stock movement behind this invoice is a real
// pl_godown_transfers row with its own existing return/delete flow — see
// internal_transfer_return_action.php — which already knows how to reverse
// partial or full stock correctly. Duplicating that logic here would create
// two independent reversal paths that could disagree with each other.
$db_conn->begin_transaction();
try {
    // Lock invoice row — prevents a second concurrent delete from racing
    $lock = $db_conn->prepare("SELECT id FROM cp_invoices WHERE id=? FOR UPDATE");
    $lock->bind_param("i", $inv_id); $lock->execute();
    $locked = $lock->get_result()->fetch_assoc(); $lock->close();
    if (!$locked) {
        throw new \Exception("Invoice no longer exists — may have been deleted by another request.");
    }

    // Un-link the PO so it doesn't keep pointing at a deleted invoice.
    // The PO's status and transfer_id are intentionally left alone — the
    // stock transfer behind it remains a real, valid record.
    $po = $db_conn->prepare("UPDATE channel_partner_purchase_orders SET cp_invoice_id = NULL WHERE cp_invoice_id = ?");
    $po->bind_param("i", $inv_id); $po->execute(); $po->close();

    $d1 = $db_conn->prepare("DELETE FROM cp_invoice_items WHERE cp_invoice_id=?");
    $d1->bind_param("i", $inv_id); $d1->execute(); $d1->close();

    $d2 = $db_conn->prepare("DELETE FROM cp_invoices WHERE id=?");
    $d2->bind_param("i", $inv_id); $d2->execute();
    if ($d2->affected_rows === 0) {
        throw new \Exception("Invoice was already deleted by another request.");
    }
    $d2->close();

    $db_conn->commit();
    header("Location: manage-cp-invoices?deleted=1&inv=" . urlencode($inv_num)); exit;

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("[delete-cp-invoice] Failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    header("Location: manage-cp-invoices?error=db"); exit;
}
