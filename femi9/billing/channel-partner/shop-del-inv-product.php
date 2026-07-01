<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$invoice_id_encode = $_REQUEST['invid']   ?? '';
$invuser           = $_REQUEST['invuser'] ?? 'shop';
$rowid             = (int)base64_decode($_REQUEST['rowid'] ?? '');
$tp_id             = (int)$Login_user_IDvl;
$actionEdit        = $_SESSION['ACTIONEDIT'] ?? '';

if ($rowid <= 0) {
    header("Location: shop-invoice-add.php?InvoiceID=$invoice_id_encode&invuser=$invuser&action=$actionEdit"); exit;
}

// Fetch item
$stmt = $db_conn->prepare("SELECT pr_id, qty, inv_id FROM user_invoice_items WHERE id=?");
$stmt->bind_param('i', $rowid);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($item) {
    $prid   = (int)$item['pr_id'];
    $qty    = (int)$item['qty'];
    $inv_id = (string)$item['inv_id'];

    // Check if stock was already deducted (invoice was submitted)
    $stmtChk = $db_conn->prepare("SELECT COUNT(*) AS n FROM territory_partner_stock_ledger WHERE territory_partner_id=? AND ref_type='tp_invoice' AND ref_id=?");
    $stmtChk->bind_param('is', $tp_id, $inv_id);
    $stmtChk->execute();
    $hasLedger = (int)$stmtChk->get_result()->fetch_assoc()['n'] > 0;
    $stmtChk->close();

    if ($hasLedger) {
        // Reverse stock deduction for this product
        $stmt = $db_conn->prepare("UPDATE territory_partner_stock SET closing_qty=closing_qty+? WHERE territory_partner_id=? AND product_id=?");
        $stmt->bind_param('iii', $qty, $tp_id, $prid);
        $stmt->execute(); $stmt->close();

        // Remove matching ledger entry
        $stmtDel = $db_conn->prepare("DELETE FROM territory_partner_stock_ledger WHERE territory_partner_id=? AND product_id=? AND ref_type='tp_invoice' AND ref_id=? LIMIT 1");
        $stmtDel->bind_param('iis', $tp_id, $prid, $inv_id);
        $stmtDel->execute(); $stmtDel->close();
    }
}

// Delete the item
$stmtDel = $db_conn->prepare("DELETE FROM user_invoice_items WHERE id=?");
$stmtDel->bind_param('i', $rowid);
$stmtDel->execute(); $stmtDel->close();

echo "<script>window.location='shop-invoice-add.php?InvoiceID={$invoice_id_encode}&&DeleteSuccess&&invuser={$invuser}&&ActionRemove&&action={$actionEdit}';</script>";
