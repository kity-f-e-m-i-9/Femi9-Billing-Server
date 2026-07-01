<?php
include("checksession.php");
include("config.php");
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: manage-tp-invoices"); exit;
}

$itemid   = (int)base64_decode($_REQUEST['itemid']   ?? '');
$returnid = trim(base64_decode($_REQUEST['returnid'] ?? ''));
$inv_id   = (int)($_REQUEST['inv_id'] ?? 0);

if (!$itemid || !$returnid) { header("Location: tp-cnote-manage"); exit; }

$enc     = base64_encode($returnid);
$redir   = "tp-cnote-new?inv_id={$inv_id}&returnid={$enc}";

// Load CN master
$s = $db_conn->prepare("SELECT status, from_userid, invnumber FROM user_return_stock WHERE returnid=? AND from_usertype='territory_partner' AND to_usertype='company' LIMIT 1");
$s->bind_param('s', $returnid);
$s->execute();
$cn = $s->get_result()->fetch_assoc(); $s->close();

if (!$cn) { header("Location: tp-cnote-manage"); exit; }

// Load item before deleting
$s = $db_conn->prepare("SELECT prid, qty, total FROM user_return_stock_items WHERE id=? AND returnid=? LIMIT 1");
$s->bind_param('is', $itemid, $returnid);
$s->execute();
$item = $s->get_result()->fetch_assoc(); $s->close();

if (!$item) { header("Location: $redir"); exit; }

if ($cn['status'] === 'accept') {
    // Accepted CN — must reverse stock changes made during finalization
    $tp_db_id   = (int)$cn['from_userid'];
    $inv_number = $cn['invnumber'];
    $prid       = (int)$item['prid'];
    $qty        = (int)$item['qty'];
    $item_total = (float)$item['total'];
    $created_by = $_SESSION['LOGIN_USER'] ?? 'system';

    // Determine original source (godown or CP)
    $s = $db_conn->prepare("SELECT id, source_godown_id, source_cp_id FROM tp_invoices WHERE invoice_number=? LIMIT 1");
    $s->bind_param('s', $inv_number);
    $s->execute();
    $tpInv = $s->get_result()->fetch_assoc(); $s->close();

    if (!$tpInv) {
        $_SESSION['errorMessage'] = "Original invoice not found. Cannot reverse stock.";
        header("Location: $redir"); exit;
    }

    $tp_invoice_id    = (int)$tpInv['id'];
    $source_godown_id = (int)($tpInv['source_godown_id'] ?? 0);
    $source_cp_id     = (int)($tpInv['source_cp_id'] ?? 0);
    $use_godown       = ($source_godown_id > 0 && !$source_cp_id);

    $db_conn->begin_transaction();
    try {

        // 1a. Read current TP stock before reversal
        $s = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=? FOR UPDATE");
        $s->bind_param('ii', $tp_db_id, $prid);
        $s->execute();
        $tp_before = (int)($s->get_result()->fetch_assoc()['closing_qty'] ?? 0);
        $s->close();
        $tp_after = $tp_before + $qty;

        // 1b. Restore TP stock
        $s = $db_conn->prepare("UPDATE territory_partner_stock SET deduct_qty=GREATEST(0,deduct_qty-?), closing_qty=closing_qty+? WHERE territory_partner_id=? AND product_id=?");
        $s->bind_param('iiii', $qty, $qty, $tp_db_id, $prid);
        $s->execute(); $s->close();

        // TP ledger reversal entry
        $note = 'tp cn item removed';
        $s = $db_conn->prepare("INSERT INTO territory_partner_stock_ledger (territory_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by) VALUES (?,?,'return',?,?,?,'credit_note',?,?,?)");
        $s->bind_param('iiiiisss', $tp_db_id, $prid, $qty, $tp_before, $tp_after, $returnid, $note, $created_by);
        $s->execute(); $s->close();

        // 2. Reverse source stock
        if ($use_godown) {
            $gid = (string)$source_godown_id;
            $s = $db_conn->prepare("SELECT closing_qty FROM stock WHERE user_type='company' AND user_id=? AND product_id=? FOR UPDATE");
            $s->bind_param('si', $gid, $prid);
            $s->execute();
            $src_before = (int)($s->get_result()->fetch_assoc()['closing_qty'] ?? 0);
            $s->close();
            $src_after = max(0, $src_before - $qty);

            $s = $db_conn->prepare("UPDATE stock SET sent_qty=sent_qty+?, returnqty=GREATEST(0,returnqty-?), closing_qty=closing_qty-? WHERE user_type='company' AND user_id=? AND product_id=?");
            $s->bind_param('iiisi', $qty, $qty, $qty, $gid, $prid);
            $s->execute(); $s->close();

            $s = $db_conn->prepare("INSERT INTO stock_ledger (product_id, user_type, user_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by) VALUES (?,'company',?,'transfer_out',?,?,?,'return',?,?,?)");
            $s->bind_param('isiiisss', $prid, $gid, $qty, $src_before, $src_after, $returnid, $note, $created_by);
            $s->execute(); $s->close();
        } else {
            $s = $db_conn->prepare("UPDATE channel_partner_stock SET closing_qty=GREATEST(0,closing_qty-?) WHERE channel_partner_id=? AND product_id=?");
            $s->bind_param('iii', $qty, $source_cp_id, $prid);
            $s->execute(); $s->close();
        }

        // 3. Adjust the CN receipt
        $remarks = 'Credit Note: ' . $returnid;
        $s = $db_conn->prepare("SELECT id, amount FROM tp_invoice_receipts WHERE tp_invoice_id=? AND payment_mode='credit_note' AND remarks=? LIMIT 1");
        $s->bind_param('is', $tp_invoice_id, $remarks);
        $s->execute();
        $rcpt = $s->get_result()->fetch_assoc(); $s->close();

        if ($rcpt) {
            $new_amount = round(max(0, (float)$rcpt['amount'] - $item_total), 2);
            if ($new_amount <= 0) {
                $s = $db_conn->prepare("DELETE FROM tp_invoice_receipts WHERE id=?");
                $s->bind_param('i', $rcpt['id']);
            } else {
                $s = $db_conn->prepare("UPDATE tp_invoice_receipts SET amount=? WHERE id=?");
                $s->bind_param('di', $new_amount, $rcpt['id']);
            }
            $s->execute(); $s->close();
        }

        // 4. Delete the item
        $s = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE id=? AND returnid=?");
        $s->bind_param('is', $itemid, $returnid);
        $s->execute(); $s->close();

        $db_conn->commit();

    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("[TP CN Item Del] Failed: " . $e->getMessage());
        $_SESSION['errorMessage'] = "Failed to remove item. Please try again.";
        header("Location: $redir"); exit;
    }

} else {
    // Pending CN — just remove the item, no stock was moved yet
    $s = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE id=? AND returnid=?");
    $s->bind_param('is', $itemid, $returnid);
    $s->execute(); $s->close();
}

header("Location: $redir");
exit;
