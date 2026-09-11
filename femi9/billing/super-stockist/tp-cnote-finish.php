<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpAdvanceService.php';
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (($Login_user_TYPEvl ?? '') !== 'super_stockiest') {
    header("Location: manage-tp-invoices"); exit;
}
// The SS identifier used throughout — see tp-cnote-manage.php for why
// ownership is scoped via the TP's own onboard_ss_id rather than
// tp_invoices.created_by_user_id (blank on a lot of real invoices). Also
// doubles as the id the `stock` table keys super_stockiest rows on.
$ss_stock_id = (string)$Login_user_IDvl;

$returnid   = trim(base64_decode($_REQUEST['returnid'] ?? ''));
$created_by = $_SESSION['LOGIN_USER'] ?? 'system';

if (!$returnid) { header("Location: tp-cnote-manage"); exit; }

// Fetch CN master — scoped to CNs against THIS SS's own issued invoices only.
$s = $db_conn->prepare("SELECT * FROM user_return_stock WHERE returnid=? AND from_usertype='territory_partner' AND to_usertype='super_stockiest' AND to_userid=? LIMIT 1");
$s->bind_param('ss', $returnid, $ss_stock_id);
$s->execute();
$cn = $s->get_result()->fetch_assoc();
$s->close();
if (!$cn) { header("Location: tp-cnote-manage?error=not_found"); exit; }

if ($cn['status'] !== 'pending') {
    $_SESSION['errorMessage'] = "This credit note has already been finalised.";
    header("Location: tp-cnote-manage"); exit;
}

$inv_number = $cn['invnumber'];
$tp_db_id   = (int)$cn['from_userid'];

// Fetch original TP invoice (need source info) — must still belong to this SS.
$s = $db_conn->prepare("SELECT tpi.id, tpi.source_godown_id, tpi.source_cp_id, tpi.source_location_id FROM tp_invoices tpi JOIN territory_partners tp ON tp.id = tpi.territory_partner_id WHERE tpi.invoice_number=? AND tpi.created_by_user_type='super_stockiest' AND tp.onboard_ss_id=? LIMIT 1");
$s->bind_param('ss', $inv_number, $ss_stock_id);
$s->execute();
$tpInv = $s->get_result()->fetch_assoc();
$s->close();
if (!$tpInv) {
    $_SESSION['errorMessage'] = "Original TP invoice not found.";
    header("Location: tp-cnote-manage"); exit;
}
$tp_invoice_id    = (int)$tpInv['id'];
$source_godown_id = (int)($tpInv['source_godown_id'] ?? 0);
$source_cp_id     = (int)($tpInv['source_cp_id'] ?? 0);
$source_loc_id    = (int)($tpInv['source_location_id'] ?? 0);
// An SS-issued invoice always deducts straight from the SS's own stock (see
// tp-invoice-action.php's debitSs()) — source_godown_id/source_cp_id are 0
// and source_location_id is NULL for these, unlike a company-issued
// invoice which always sets exactly one of the three.
$use_ss_stock     = ($source_godown_id === 0 && $source_cp_id === 0 && $source_loc_id === 0);

// Fetch CN items
$s = $db_conn->prepare("SELECT * FROM user_return_stock_items WHERE returnid=?");
$s->bind_param('s', $returnid);
$s->execute();
$items = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();

if (empty($items)) {
    $_SESSION['errorMessage'] = "No items in this credit note.";
    header("Location: tp-cnote-manage"); exit;
}

$subtotal = round(array_sum(array_column($items, 'total')), 2);
$cn_total = $subtotal;

// ── TRANSACTION ─────────────────────────────────────────────────────────────
$db_conn->begin_transaction();
try {

    // 1. Finalise CN header
    $s = $db_conn->prepare("UPDATE user_return_stock SET subtotal=?, discount=0, total=?, status='accept' WHERE returnid=?");
    $s->bind_param('dds', $subtotal, $cn_total, $returnid);
    $s->execute(); $s->close();

    // 2. Finalise CN items
    $s = $db_conn->prepare("UPDATE user_return_stock_items SET status='accept' WHERE returnid=?");
    $s->bind_param('s', $returnid);
    $s->execute(); $s->close();

    // 3. Stock adjustments per item
    foreach ($items as $item) {
        $prid      = (int)$item['prid'];
        $returnqty = (int)$item['qty'];

        // ── 3a. Decrease TP stock ────────────────────────────────────────────
        $s = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=? FOR UPDATE");
        $s->bind_param('ii', $tp_db_id, $prid);
        $s->execute();
        $tp_before = (int)($s->get_result()->fetch_assoc()['closing_qty'] ?? 0);
        $s->close();

        $tp_after = max(0, $tp_before - $returnqty);

        $s = $db_conn->prepare("UPDATE territory_partner_stock SET deduct_qty=deduct_qty+?, closing_qty=? WHERE territory_partner_id=? AND product_id=?");
        $s->bind_param('iiii', $returnqty, $tp_after, $tp_db_id, $prid);
        $s->execute(); $s->close();

        // TP stock ledger
        $note = 'tp credit note return';
        $s = $db_conn->prepare("INSERT INTO territory_partner_stock_ledger (territory_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by) VALUES (?,?,'deduct',?,?,?,'credit_note',?,?,?)");
        $s->bind_param('iiiiisss', $tp_db_id, $prid, $returnqty, $tp_before, $tp_after, $returnid, $note, $created_by);
        $s->execute(); $s->close();

        // ── 3b. Increase source stock (SS/godown/CP) ─────────────────────────
        if ($use_ss_stock) {
            $s = $db_conn->prepare("SELECT closing_qty, sent_qty FROM stock WHERE user_type='super_stockiest' AND user_id=? AND product_id=? FOR UPDATE");
            $s->bind_param('si', $ss_stock_id, $prid);
            $s->execute();
            $src_row = $s->get_result()->fetch_assoc(); $s->close();

            $src_before  = (int)($src_row['closing_qty'] ?? 0);
            $src_after   = $src_before + $returnqty;
            $new_sent    = max(0, (int)($src_row['sent_qty'] ?? 0) - $returnqty);

            if ($src_row) {
                $s = $db_conn->prepare("UPDATE stock SET sent_qty=?, returnqty=returnqty+?, closing_qty=? WHERE user_type='super_stockiest' AND user_id=? AND product_id=?");
                $s->bind_param('iiisi', $new_sent, $returnqty, $src_after, $ss_stock_id, $prid);
                $s->execute(); $s->close();
            } else {
                // Create row if somehow missing
                $s = $db_conn->prepare("INSERT INTO stock (product_id, opening_qty, opening_date, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id) VALUES (?,0,CURDATE(),0,0,0,?,?,'super_stockiest',?)");
                $s->bind_param('iiis', $prid, $returnqty, $returnqty, $ss_stock_id);
                $s->execute(); $s->close();
                $src_before = 0; $src_after = $returnqty;
            }

            // SS stock ledger
            $s = $db_conn->prepare("INSERT INTO stock_ledger (product_id, user_type, user_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by) VALUES (?,'super_stockiest',?,'transfer_in',?,?,?,'return',?,?,?)");
            $s->bind_param('isiiisss', $prid, $ss_stock_id, $returnqty, $src_before, $src_after, $returnid, $note, $created_by);
            $s->execute(); $s->close();

        } elseif ($source_godown_id > 0 && !$source_cp_id) {
            $gid = (string)$source_godown_id;
            $s = $db_conn->prepare("SELECT closing_qty, sent_qty FROM stock WHERE user_type='company' AND user_id=? AND product_id=? FOR UPDATE");
            $s->bind_param('si', $gid, $prid);
            $s->execute();
            $src_row = $s->get_result()->fetch_assoc(); $s->close();

            $src_before  = (int)($src_row['closing_qty'] ?? 0);
            $src_after   = $src_before + $returnqty;
            $new_sent    = max(0, (int)($src_row['sent_qty'] ?? 0) - $returnqty);

            if ($src_row) {
                $s = $db_conn->prepare("UPDATE stock SET sent_qty=?, returnqty=returnqty+?, closing_qty=? WHERE user_type='company' AND user_id=? AND product_id=?");
                $s->bind_param('iiisi', $new_sent, $returnqty, $src_after, $gid, $prid);
                $s->execute(); $s->close();
            } else {
                $s = $db_conn->prepare("INSERT INTO stock (product_id, opening_qty, opening_date, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id) VALUES (?,0,CURDATE(),0,0,0,?,?,'company',?)");
                $s->bind_param('iiis', $prid, $returnqty, $returnqty, $gid);
                $s->execute(); $s->close();
                $src_before = 0; $src_after = $returnqty;
            }

            $s = $db_conn->prepare("INSERT INTO stock_ledger (product_id, user_type, user_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by) VALUES (?,'company',?,'transfer_in',?,?,?,'return',?,?,?)");
            $s->bind_param('isiiisss', $prid, $gid, $returnqty, $src_before, $src_after, $returnid, $note, $created_by);
            $s->execute(); $s->close();

        } elseif (!$source_cp_id && !$source_godown_id && $source_loc_id > 0) {
            // Legacy: return to partner_location_stock
            $s = $db_conn->prepare("SELECT closing_qty FROM partner_location_stock WHERE partner_location_id=? AND product_id=? FOR UPDATE");
            $s->bind_param('ii', $source_loc_id, $prid);
            $s->execute();
            $src_before = (int)($s->get_result()->fetch_assoc()['closing_qty'] ?? 0);
            $s->close();
            $src_after = $src_before + $returnqty;

            $s = $db_conn->prepare("UPDATE partner_location_stock SET closing_qty=closing_qty+?, transfer_out_qty=GREATEST(0,transfer_out_qty-?) WHERE partner_location_id=? AND product_id=?");
            $s->bind_param('iiii', $returnqty, $returnqty, $source_loc_id, $prid);
            $s->execute(); $s->close();

            $act = 'transfer_in'; $rtype = 'return';
            $s2 = $db_conn->prepare("INSERT INTO partner_location_stock_ledger (partner_location_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $s2->bind_param('iisiiissss', $source_loc_id, $prid, $act, $returnqty, $src_before, $src_after, $rtype, $returnid, $note, $created_by);
            $s2->execute(); $s2->close();

        } else {
            // CP stock
            $s = $db_conn->prepare("SELECT closing_qty FROM channel_partner_stock WHERE channel_partner_id=? AND product_id=? FOR UPDATE");
            $s->bind_param('ii', $source_cp_id, $prid);
            $s->execute();
            $src_before = (int)($s->get_result()->fetch_assoc()['closing_qty'] ?? 0);
            $s->close();
            $src_after = $src_before + $returnqty;

            $s = $db_conn->prepare("UPDATE channel_partner_stock SET closing_qty=closing_qty+? WHERE channel_partner_id=? AND product_id=?");
            $s->bind_param('iii', $returnqty, $source_cp_id, $prid);
            $s->execute(); $s->close();

            // CP stock ledger
            $s = $db_conn->prepare("INSERT INTO channel_partner_stock_ledger (channel_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by) VALUES (?,?,'transfer_in',?,?,?,'transfer',?,?,?)");
            $s->bind_param('iiiiisss', $source_cp_id, $prid, $returnqty, $src_before, $src_after, $returnid, $note, $created_by);
            $s->execute(); $s->close();
        }
    }

    // 4. Insert CN receipt credit against the TP invoice
    if ($cn_total > 0 && $tp_invoice_id) {
        $cn_remarks = 'Credit Note: ' . $returnid;
        $cn_date    = date('Y-m-d');
        $s = $db_conn->prepare("INSERT INTO tp_invoice_receipts (tp_invoice_id, invoice_number, amount, receipt_date, payment_mode, remarks, created_by) VALUES (?,?,?,?,'credit_note',?,?)");
        $s->bind_param('isdsss', $tp_invoice_id, $inv_number, $cn_total, $cn_date, $cn_remarks, $created_by);
        $s->execute(); $s->close();
    }

    // 5. Credit the return value back to the TP's advance balance — restores
    // whatever was deducted from tp_advance_payments to pay for this
    // invoice, up to the returned amount (LIFO: most recently deducted
    // advance payment first). This is what actually increases the TP's
    // spendable balance for future purchase orders; the receipt above only
    // marks the original invoice as paid and doesn't touch the balance.
    if ($cn_total > 0 && $tp_invoice_id) {
        $cn_date = $cn_date ?? date('Y-m-d');
        $restoredAmount = tpAdvanceRestorePartial($db_conn, $tp_invoice_id, $cn_total);

        // If the invoice's deduction log couldn't fully account for the
        // return (e.g. no log rows exist for it at all), the TP would
        // otherwise silently lose that portion of their return value.
        // Credit the shortfall as a brand-new advance payment instead, same
        // shape as a real one, so the TP's balance always reflects what
        // they're owed.
        $shortfall = round($cn_total - $restoredAmount, 2);
        if ($shortfall > 0.005) {
            $fallbackCompanyId = ($source_godown_id > 0 && !$source_cp_id) ? $source_godown_id : null;
            $fallbackRemarks   = 'Credit Note (return) — Invoice: ' . $inv_number . ', Return ID: ' . $returnid
                . ($restoredAmount > 0 ? '. Partially restored from original deduction log; remainder credited fresh.' : '. No deduction log found for the original invoice; credited fresh.');
            $balance  = $shortfall;
            $adjusted = 0.00;
            $status   = 'active';
            $s = $db_conn->prepare(
                "INSERT INTO tp_advance_payments
                    (company_id, territory_partner_id, amount, payment_date, payment_mode, reference_number, remarks,
                     adjusted_amount, balance_amount, status, created_by)
                 VALUES (?,?,?,?,'credit_note',?,?,?,?,?,?)"
            );
            $s->bind_param(
                'iidsssdsss',
                $fallbackCompanyId, $tp_db_id, $shortfall, $cn_date, $returnid, $fallbackRemarks,
                $adjusted, $balance, $status, $created_by
            );
            $s->execute(); $s->close();
        }
    }

    $db_conn->commit();

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("[TP CN Finish - SS] Failed for returnid=$returnid: " . $e->getMessage());
    $_SESSION['errorMessage'] = "Failed to finalise credit note. Please try again.";
    header("Location: tp-cnote-new?inv_id=" . $tpInv['id'] . "&returnid=" . base64_encode($returnid)); exit;
}

$_SESSION['successMessage'] = "Credit Note finalised against Invoice: " . htmlspecialchars($inv_number) . ". CN Total: ₹" . inr_format($cn_total, 2);
header("Location: tp-cnote-manage?success=1");
exit;
