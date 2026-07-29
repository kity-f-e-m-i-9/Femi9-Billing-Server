<?php
include("checksession.php");
include("config.php");
require_once("include/ShopInvoiceHistory.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$invid_enc = $_REQUEST['invid'] ?? '';
$invuser   = $_REQUEST['invuser'] ?? 'shop';
$inv_id    = base64_decode($invid_enc);
$tp_id     = (int)$Login_user_IDvl;
$tp_id_str = (string)$tp_id;

if ($inv_id === '') {
    header('Location: shop-manage-invoice.php');
    exit;
}

$stmt = $db_conn->prepare(
    "SELECT inv_id, status FROM user_invoice WHERE inv_id=? AND from_user_type=? AND from_user_id=? LIMIT 1"
);
$stmt->bind_param('sss', $inv_id, $Login_user_TYPEvl, $tp_id_str);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$inv) {
    $_SESSION['errorMessage'] = "Invoice not found.";
    header('Location: shop-manage-invoice.php');
    exit;
}
if ($inv['status'] === 'cancelled') {
    $_SESSION['errorMessage'] = "This invoice has already been voided.";
    header('Location: shop-manage-invoice.php');
    exit;
}

// Only a completed invoice (a receipt has been recorded) can be voided —
// matches the "Continue Invoice" vs "Completed Invoice" distinction used
// elsewhere. A still-in-progress draft isn't a real invoice yet.
$stmtRcpt = $db_conn->prepare("SELECT 1 FROM receipt WHERE inv_id=? LIMIT 1");
$stmtRcpt->bind_param('s', $inv_id);
$stmtRcpt->execute();
$hasReceipt = $stmtRcpt->get_result()->num_rows > 0;
$stmtRcpt->close();
if (!$hasReceipt) {
    $_SESSION['errorMessage'] = "This invoice isn't completed yet, so it can't be voided.";
    header('Location: shop-manage-invoice.php');
    exit;
}

$db_conn->begin_transaction();
try {
    // Reverse stock — same idempotent "restore closing_qty from ledger, then
    // delete those ledger rows" block already used by shop-invoice-submit.php.
    // If the invoice was never submitted (still "Continue Invoice"), this is
    // naturally a no-op — the SELECT simply returns nothing.
    $s = $db_conn->prepare(
        "SELECT product_id, qty FROM territory_partner_stock_ledger
         WHERE territory_partner_id=? AND ref_type='tp_invoice' AND ref_id=? FOR UPDATE"
    );
    $s->bind_param('is', $tp_id, $inv_id);
    $s->execute();
    $ledgerEntries = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    foreach ($ledgerEntries as $le) {
        $s = $db_conn->prepare("UPDATE territory_partner_stock SET closing_qty=closing_qty+? WHERE territory_partner_id=? AND product_id=?");
        $s->bind_param('iii', $le['qty'], $tp_id, $le['product_id']);
        $s->execute(); $s->close();
    }
    if ($ledgerEntries) {
        $s = $db_conn->prepare("DELETE FROM territory_partner_stock_ledger WHERE territory_partner_id=? AND ref_type='tp_invoice' AND ref_id=?");
        $s->bind_param('is', $tp_id, $inv_id);
        $s->execute(); $s->close();
    }

    $s = $db_conn->prepare(
        "UPDATE user_invoice SET status='cancelled', voided_at=NOW(), voided_by_user_type=?, voided_by_user_id=?
         WHERE inv_id=? AND from_user_type=? AND from_user_id=?"
    );
    $s->bind_param('sssss', $Login_user_TYPEvl, $tp_id_str, $inv_id, $Login_user_TYPEvl, $tp_id_str);
    $s->execute(); $s->close();

    logShopInvoiceChange($db_conn, $inv_id, null, 'voided', null, null, $Login_user_TYPEvl, $tp_id_str, 'Invoice voided');

    $db_conn->commit();
    $_SESSION['successMessage'] = "Invoice voided successfully.";
} catch (Throwable $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = "Could not void this invoice. Please try again.";
}

header('Location: shop-manage-invoice.php');
exit;
