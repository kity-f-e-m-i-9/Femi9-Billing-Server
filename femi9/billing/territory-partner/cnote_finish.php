<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid     = mysqli_real_escape_string($db_conn, base64_decode($_REQUEST['returnid'] ?? ''));
$SubTotal     = (float)($_REQUEST['SubTotal'] ?? 0);
$discount     = (float)($_REQUEST['discount'] ?? 0);
$total_amount = $SubTotal - $discount;
$created_by   = $_SESSION['LOGIN_USER'] ?? 'system';

if (empty($returnid)) {
    header("Location: manage-return.php?error=invalid_returnid"); exit;
}

// Fetch return master + double-submit guard
$stmt = $db_conn->prepare(
    "SELECT invnumber, from_usertype, from_userid, to_usertype, to_userid, status
     FROM user_return_stock WHERE returnid=? LIMIT 1"
);
$stmt->bind_param('s', $returnid);
$stmt->execute();
$return = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$return) {
    header("Location: manage-return.php?error=not_found"); exit;
}

// Guard: only process if still pending — prevents double-submit corrupting stock
if ($return['status'] !== 'pending') {
    $_SESSION['successMessage'] = "This credit note has already been finalised.";
    echo "<script>window.location='manage-return.php';</script>"; exit;
}

$invid         = $return['invnumber'];
$from_usertype = $return['from_usertype'];
$from_userid   = $return['from_userid'];
$to_userid     = $return['to_userid'];   // TP's integer id
$tp_id         = (int)$to_userid;

// Fetch all items before touching stock
$stmt = $db_conn->prepare("SELECT prid, qty FROM user_return_stock_items WHERE returnid=?");
$stmt->bind_param('s', $returnid);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Wrap everything in a transaction so a mid-loop failure rolls back completely
$db_conn->begin_transaction();
try {

    // 1. Finalise header
    $stmt = $db_conn->prepare(
        "UPDATE user_return_stock SET subtotal=?, discount=?, total=?, status='accept' WHERE returnid=?"
    );
    $stmt->bind_param('ddds', $SubTotal, $discount, $total_amount, $returnid);
    $stmt->execute();
    $stmt->close();

    // 2. Finalise items
    $stmt = $db_conn->prepare("UPDATE user_return_stock_items SET status='accept' WHERE returnid=?");
    $stmt->bind_param('s', $returnid);
    $stmt->execute();
    $stmt->close();

    // 3. Stock adjustments + ledger entries per item
    foreach ($items as $item) {
        $prid      = (int)$item['prid'];
        $returnqty = (int)$item['qty'];

        // ── RECEIVER = TP: restore returned qty to territory_partner_stock ──────
        $stmtBefore = $db_conn->prepare(
            "SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=? LIMIT 1"
        );
        $stmtBefore->bind_param('ii', $tp_id, $prid);
        $stmtBefore->execute();
        $before = (int)($stmtBefore->get_result()->fetch_assoc()['closing_qty'] ?? 0);
        $stmtBefore->close();

        $after = $before + $returnqty;

        $stmtUpd = $db_conn->prepare(
            "UPDATE territory_partner_stock SET closing_qty=? WHERE territory_partner_id=? AND product_id=?"
        );
        $stmtUpd->bind_param('iii', $after, $tp_id, $prid);
        $stmtUpd->execute();
        $stmtUpd->close();

        // ── Ledger entry (return credit) ─────────────────────────────────────────
        $stmtLed = $db_conn->prepare(
            "INSERT INTO territory_partner_stock_ledger
             (territory_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
             VALUES (?, ?, 'return', ?, ?, ?, 'credit_note', ?, 'credit note return', ?)"
        );
        $stmtLed->bind_param('iiiiiss', $tp_id, $prid, $returnqty, $before, $after, $returnid, $created_by);
        $stmtLed->execute();
        $stmtLed->close();

        // ── SENDER: reduce their stock if they maintain one ───────────────────────
        // Use FOR UPDATE + floor (GREATEST) to prevent race and negative stock.
        if (in_array($from_usertype, ['super_stockiest','stockiest','super_distributor','distributor'])) {
            $stmtSdrLock = $db_conn->prepare(
                "SELECT id, input_qty, closing_qty FROM stock
                 WHERE product_id=? AND user_type=? AND user_id=? LIMIT 1 FOR UPDATE"
            );
            $stmtSdrLock->bind_param('iss', $prid, $from_usertype, $from_userid);
            $stmtSdrLock->execute();
            $sdrRow = $stmtSdrLock->get_result()->fetch_assoc();
            $stmtSdrLock->close();

            if ($sdrRow) {
                $sdr_input_before   = (int)$sdrRow['input_qty'];
                $sdr_closing_before = (int)$sdrRow['closing_qty'];
                $sdr_input_after    = max(0, $sdr_input_before   - $returnqty);
                $sdr_closing_after  = max(0, $sdr_closing_before - $returnqty);

                $stmtSdr = $db_conn->prepare(
                    "UPDATE stock SET input_qty=?, closing_qty=?
                     WHERE id=? LIMIT 1"
                );
                $stmtSdr->bind_param('iii', $sdr_input_after, $sdr_closing_after, $sdrRow['id']);
                $stmtSdr->execute();
                $stmtSdr->close();
            }
        }
    }

    // 4. Insert CN receipt credit against the original invoice
    $inv_table_rc = ($from_usertype === 'customer') ? 'invoice' : 'user_invoice';
    $stmtInvTot = $db_conn->prepare("SELECT total FROM $inv_table_rc WHERE inv_id=? LIMIT 1");
    $stmtInvTot->bind_param('s', $invid);
    $stmtInvTot->execute();
    $inv_total = (int)($stmtInvTot->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtInvTot->close();

    $cn_received   = (int)round($total_amount);
    $cn_receivable = max(0, $inv_total - $cn_received);
    $cn_receiptid  = 'CN-' . $returnid;
    $cn_date       = date('Y-m-d');
    $cn_from_type  = 'territory_partner';
    $cn_from_id    = (string)$tp_id;
    $cn_to_type    = $from_usertype;
    $cn_to_id      = $from_userid;
    $cn_remarks    = 'Credit Note: ' . $returnid;

    $stmtChk = $db_conn->prepare("SELECT id FROM receipt WHERE receiptid=? LIMIT 1");
    $stmtChk->bind_param('s', $cn_receiptid);
    $stmtChk->execute();
    $chkRow = $stmtChk->get_result()->fetch_assoc();
    $stmtChk->close();

    if (!$chkRow) {
        $stmtRcpt = $db_conn->prepare(
            "INSERT INTO receipt (receiptid, inv_id, invoice_amount, received, receivable, date,
             from_user_type, from_user_id, to_user_type, to_user_id,
             receipt_method, receipt_remarks, payment_type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Credit Note', ?, 'credit_note')"
        );
        $stmtRcpt->bind_param('ssiiissssss',
            $cn_receiptid, $invid, $inv_total, $cn_received, $cn_receivable, $cn_date,
            $cn_from_type, $cn_from_id, $cn_to_type, $cn_to_id, $cn_remarks
        );
        $stmtRcpt->execute();
        $stmtRcpt->close();
    }

    $db_conn->commit();

} catch (Exception $e) {
    $db_conn->rollback();
    error_log("[TP cnote_finish] Transaction failed: " . $e->getMessage());
    $_SESSION['errorMessage'] = "Failed to finalise credit note. Please try again.";
    echo "<script>window.location='manage-return.php?error=transaction_failed';</script>"; exit;
}

// Success message
$inv_table = ($from_usertype === 'customer') ? 'invoice' : 'user_invoice';
$stmt = $db_conn->prepare("SELECT inv_number FROM $inv_table WHERE inv_id=? LIMIT 1");
$stmt->bind_param('s', $invid);
$stmt->execute();
$invdata = $stmt->get_result()->fetch_assoc();
$stmt->close();

$_SESSION['successMessage'] = "Credit Note finalised against Invoice: " . htmlspecialchars($invdata['inv_number'] ?? $invid);
echo "<script>window.location='manage-return.php?returnaddedsuccess';</script>";
exit;
?>
