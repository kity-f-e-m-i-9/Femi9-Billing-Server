<?php
/**
 * Toggle Reward Points for a TP Invoice
 *
 * TP equivalent of update_rwpermission.php (which only operates on
 * user_invoice / user_invoice_items — the SS/Stockist/Distributor/Outlet
 * table). Unlike that legacy endpoint, this one requires POST + CSRF and
 * uses prepared statements throughout.
 */

include("checksession.php");
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'company') {
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

// Trust the DB's current value, not the client-submitted one, before flipping it
$s = $db_conn->prepare("SELECT id, invoice_number, rwpoints_enable FROM tp_invoices WHERE id=? LIMIT 1");
$s->bind_param("i", $inv_id);
$s->execute();
$inv = $s->get_result()->fetch_assoc();
$s->close();
if (!$inv) { header("Location: manage-tp-invoices?error=notfound"); exit; }

$newState = ((int)$inv['rwpoints_enable'] === 1) ? 0 : 1;

$db_conn->begin_transaction();
try {
    $s = $db_conn->prepare("UPDATE tp_invoices SET rwpoints_enable=? WHERE id=?");
    $s->bind_param("ii", $newState, $inv_id);
    $s->execute();
    $s->close();

    if ($newState === 1) {
        // Recompute points from each line item's product + quantity
        $s = $db_conn->prepare("
            UPDATE tp_invoice_items tii
            JOIN products p ON p.id = tii.product_id
            SET tii.rwpoints = p.rwpoints * tii.quantity
            WHERE tii.tp_invoice_id = ?
        ");
        $s->bind_param("i", $inv_id);
        $s->execute();
        $s->close();
    } else {
        $s = $db_conn->prepare("UPDATE tp_invoice_items SET rwpoints=0 WHERE tp_invoice_id=?");
        $s->bind_param("i", $inv_id);
        $s->execute();
        $s->close();
    }

    $db_conn->commit();
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("update-tp-invoice-rwpermission failed for invoice {$inv_id}: " . $e->getMessage());
    header("Location: manage-tp-invoices?error=update_failed"); exit;
}

$_SESSION['successMessage'] = "Invoice Number " . $inv['invoice_number'] . " Reward Points "
    . ($newState === 1 ? "Enabled" : "Disabled") . " Success";
header("Location: manage-tp-invoices");
exit;
