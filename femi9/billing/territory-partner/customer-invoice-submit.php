<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (!isset($_REQUEST['invoice-submit'])) { header("Location: customer-manage-invoice.php"); exit; }

$invoice_id     = $_REQUEST['invoice_id'] ?? '';
$SubTotal       = (float)($_REQUEST['sub_total']       ?? 0);
$discount       = (float)($_REQUEST['discount']        ?? 0);
$roundoff       = (float)($_REQUEST['roundoff']        ?? 0);
$courier        = (float)($_REQUEST['courier_charges'] ?? 0);
$total_amount   = round($SubTotal - $discount + $courier);
$tp_id          = (int)$Login_user_IDvl;
$created_by     = $_SESSION['LOGIN_USER'] ?? 'system';

$db_conn->begin_transaction();
try {

    // Update invoice date if editing
    if (!empty($_REQUEST['update_invoice_date'])) {
        $upd_date = date("Y-m-d", strtotime($_REQUEST['update_invoice_date']));
        $s = $db_conn->prepare("UPDATE invoice SET date=? WHERE inv_id=? AND user_type=? AND user_id=?");
        $s->bind_param('sssi', $upd_date, $invoice_id, $Login_user_TYPEvl, $tp_id);
        $s->execute(); $s->close();
        $s = $db_conn->prepare("UPDATE invoice_items SET date=? WHERE inv_id=? AND user_type=? AND user_id=?");
        $s->bind_param('sssi', $upd_date, $invoice_id, $Login_user_TYPEvl, $tp_id);
        $s->execute(); $s->close();
    }

    $s = $db_conn->prepare("SELECT * FROM invoice WHERE inv_id=? LIMIT 1");
    $s->bind_param('s', $invoice_id);
    $s->execute();
    $inv = $s->get_result()->fetch_assoc();
    $s->close();

    // Reverse any existing ledger entries before deducting fresh.
    // Idempotent: correct on first submit, re-submit, and edit.
    // FOR UPDATE prevents concurrent double-submit from restoring the same entries twice.
    $s = $db_conn->prepare(
        "SELECT product_id, qty FROM territory_partner_stock_ledger
         WHERE territory_partner_id=? AND ref_type='tp_invoice' AND ref_id=? FOR UPDATE"
    );
    $s->bind_param('is', $tp_id, $invoice_id);
    $s->execute();
    $prevEntries = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    foreach ($prevEntries as $le) {
        $s = $db_conn->prepare("UPDATE territory_partner_stock SET closing_qty=closing_qty+? WHERE territory_partner_id=? AND product_id=?");
        $s->bind_param('iii', $le['qty'], $tp_id, $le['product_id']);
        $s->execute(); $s->close();
    }
    if ($prevEntries) {
        $s = $db_conn->prepare("DELETE FROM territory_partner_stock_ledger WHERE territory_partner_id=? AND ref_type='tp_invoice' AND ref_id=?");
        $s->bind_param('is', $tp_id, $invoice_id);
        $s->execute(); $s->close();
    }

    // Fetch all invoice items
    $s = $db_conn->prepare("SELECT pr_id, qty FROM invoice_items WHERE inv_id=? AND user_type=? AND user_id=?");
    $s->bind_param('ssi', $invoice_id, $Login_user_TYPEvl, $tp_id);
    $s->execute();
    $items = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    // Deduct stock per item — FOR UPDATE prevents TOCTOU race on closing_qty
    foreach ($items as $item) {
        $prid = (int)$item['pr_id'];
        $qty  = (int)$item['qty'];

        $s = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=? FOR UPDATE");
        $s->bind_param('ii', $tp_id, $prid);
        $s->execute();
        $before = (int)($s->get_result()->fetch_assoc()['closing_qty'] ?? 0);
        $s->close();

        $after = max(0, $before - $qty);

        $s = $db_conn->prepare("UPDATE territory_partner_stock SET closing_qty=? WHERE territory_partner_id=? AND product_id=?");
        $s->bind_param('iii', $after, $tp_id, $prid);
        $s->execute(); $s->close();

        $s = $db_conn->prepare(
            "INSERT INTO territory_partner_stock_ledger
             (territory_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
             VALUES (?, ?, 'deduct', ?, ?, ?, 'tp_invoice', ?, 'customer invoice', ?)"
        );
        $s->bind_param('iiiiiss', $tp_id, $prid, $qty, $before, $after, $invoice_id, $created_by);
        $s->execute(); $s->close();
    }

    // Update invoice totals
    $s = $db_conn->prepare(
        "UPDATE invoice SET sub_total=?,discount=?,total=?,roundoff=?,courier_charges=?
         WHERE inv_id=? AND user_type=? AND user_id=?"
    );
    $s->bind_param('dddddssi', $SubTotal, $discount, $total_amount, $roundoff, $courier, $invoice_id, $Login_user_TYPEvl, $tp_id);
    $s->execute(); $s->close();

    // Insert/update receipt
    $receivedamount  = (float)($_REQUEST['receivedamount']  ?? 0);
    $receipt_method  = $_REQUEST['receipt_method']           ?? '';
    $receipt_remarks = htmlspecialchars(strip_tags($_REQUEST['receipt_remarks'] ?? ''), ENT_QUOTES, 'UTF-8');
    $receiptdate     = $inv['date']        ?? date("Y-m-d");
    $to_utype        = 'customer';
    $to_uid          = $inv['customer_id'] ?? '';
    $from_utype      = $inv['user_type']   ?? $Login_user_TYPEvl;
    $from_uid        = $inv['user_id']     ?? (string)$Login_user_IDvl;

    $s = $db_conn->prepare("SELECT id,received FROM receipt WHERE inv_id=? AND (payment_type IS NULL OR payment_type != 'credit_note') LIMIT 1");
    $s->bind_param('s', $invoice_id);
    $s->execute();
    $existingReceipt = $s->get_result()->fetch_assoc();
    $s->close();

    if ($existingReceipt) {
        $new_received   = (float)$existingReceipt['received'] + $receivedamount;
        $new_receivable = round($total_amount - $new_received);
        $rcpt_id        = (int)$existingReceipt['id'];
        $s = $db_conn->prepare("UPDATE receipt SET received=?,receivable=?,invoice_amount=?,receipt_method=?,receipt_remarks=? WHERE id=?");
        $s->bind_param('dddssi', $new_received, $new_receivable, $total_amount, $receipt_method, $receipt_remarks, $rcpt_id);
        $s->execute(); $s->close();
    } else {
        $receivable = round($total_amount - $receivedamount);
        $s = $db_conn->prepare(
            "INSERT INTO receipt (receiptid,inv_id,invoice_amount,received,receivable,date,
             from_user_type,from_user_id,to_user_type,to_user_id,receipt_method,receipt_remarks)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        // receiptid=s, inv_id=s, invoice_amount=d, received=d, receivable=d,
        // date=s, from_user_type=s, from_user_id=s, to_user_type=s, to_user_id=s,
        // receipt_method=s, receipt_remarks=s  (12 params)
        $s->bind_param('ssdddsssssss',
            $invoice_id, $invoice_id, $total_amount, $receivedamount, $receivable,
            $receiptdate, $from_utype, $from_uid, $to_utype, $to_uid,
            $receipt_method, $receipt_remarks
        );
        $s->execute(); $s->close();
    }

    $db_conn->commit();

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log('[TP customer-invoice-submit] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $_SESSION['errorMessage'] = "An error occurred while submitting the invoice. Please try again.";
    header("Location: customer-manage-invoice.php?submiterror");
    exit;
}

unset($_SESSION['ACTIONEDIT']);
echo "<script>window.location='customer-invoice-print.php?invoiceid=" . base64_encode($invoice_id) . "';</script>";
