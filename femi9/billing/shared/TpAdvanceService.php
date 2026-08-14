<?php
/**
 * TpAdvanceService — traceable advance payment deduction and restore.
 *
 * Every deduction writes a row to tp_invoice_advance_log so that deletes
 * and edits can restore exactly the payments that were touched, rather than
 * creating a synthetic "Reversal" payment.
 *
 * All functions must be called inside an active transaction.
 */

/**
 * Deduct $required from the TP's advance payments (FIFO) and log each touch.
 *
 * @param int    $godown_id  Pass > 0 to scope deductions to a specific company_id.
 * @throws Exception if balance is insufficient during processing.
 */
function tpAdvanceDeduct(
    mysqli $db,
    int    $tp_invoice_id,
    string $inv_num,
    int    $tp_id,
    float  $required,
    int    $godown_id = 0
): void {
    if ($godown_id > 0) {
        $s = $db->prepare(
            "SELECT id, balance_amount FROM tp_advance_payments
              WHERE territory_partner_id=? AND company_id=?
                AND balance_amount>0 AND status!='fully_adjusted' AND deleted_at IS NULL
              ORDER BY payment_date ASC, id ASC FOR UPDATE"
        );
        $s->bind_param("ii", $tp_id, $godown_id);
    } else {
        $s = $db->prepare(
            "SELECT id, balance_amount FROM tp_advance_payments
              WHERE territory_partner_id=? AND balance_amount>0 AND status!='fully_adjusted' AND deleted_at IS NULL
              ORDER BY payment_date ASC, id ASC FOR UPDATE"
        );
        $s->bind_param("i", $tp_id);
    }
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    $remaining = $required;
    foreach ($rows as $row) {
        if ($remaining <= 0) break;
        $avail   = (float) $row['balance_amount'];
        $deduct  = min($remaining, $avail);
        $new_bal = round($avail - $deduct, 2);
        $status  = $new_bal > 0 ? 'partially_adjusted' : 'fully_adjusted';

        $u = $db->prepare(
            "UPDATE tp_advance_payments
                SET balance_amount=?, adjusted_amount=adjusted_amount+?, status=?
              WHERE id=?"
        );
        $u->bind_param("ddsi", $new_bal, $deduct, $status, $row['id']);
        $u->execute();
        $u->close();

        // Log this deduction so it can be reversed later
        $log = $db->prepare(
            "INSERT INTO tp_invoice_advance_log
                (tp_invoice_id, tp_invoice_number, tp_advance_id, deducted_amount)
             VALUES (?, ?, ?, ?)"
        );
        $log->bind_param("isid", $tp_invoice_id, $inv_num, $row['id'], $deduct);
        $log->execute();
        $log->close();

        $remaining -= $deduct;
    }

    if ($remaining > 0.005) {
        throw new \Exception("Advance balance became insufficient during processing.");
    }
}

/**
 * Restore advance payments that were deducted for a given invoice,
 * using the log as the source of truth.
 *
 * After restoring each payment row the log entries are deleted.
 * Safe to call even if no log entries exist (e.g. zero-amount invoice).
 */
function tpAdvanceRestore(mysqli $db, int $tp_invoice_id): void
{
    $s = $db->prepare(
        "SELECT id, tp_advance_id, deducted_amount
           FROM tp_invoice_advance_log
          WHERE tp_invoice_id = ?
          FOR UPDATE"
    );
    $s->bind_param("i", $tp_invoice_id);
    $s->execute();
    $entries = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    foreach ($entries as $entry) {
        $adv_id = (int)   $entry['tp_advance_id'];
        $amount = (float) $entry['deducted_amount'];

        // Fetch current state of the payment row
        $r = $db->prepare(
            "SELECT balance_amount, adjusted_amount FROM tp_advance_payments WHERE id=? FOR UPDATE"
        );
        $r->bind_param("i", $adv_id);
        $r->execute();
        $row = $r->get_result()->fetch_assoc();
        $r->close();

        if (!$row) continue; // payment row was deleted — skip

        $new_balance  = round((float)$row['balance_amount']  + $amount, 2);
        $new_adjusted = round((float)$row['adjusted_amount'] - $amount, 2);
        if ($new_adjusted < 0) $new_adjusted = 0;

        if ($new_adjusted <= 0) {
            $status = 'active';
        } else {
            $status = 'partially_adjusted';
        }

        $u = $db->prepare(
            "UPDATE tp_advance_payments
                SET balance_amount=?, adjusted_amount=?, status=?
              WHERE id=?"
        );
        $u->bind_param("ddsi", $new_balance, $new_adjusted, $status, $adv_id);
        $u->execute();
        $u->close();
    }

    // Remove log entries for this invoice
    if (!empty($entries)) {
        $d = $db->prepare("DELETE FROM tp_invoice_advance_log WHERE tp_invoice_id=?");
        $d->bind_param("i", $tp_invoice_id);
        $d->execute();
        $d->close();
    }
}

/**
 * Restore up to $amount of what was deducted from advance payments for a
 * given invoice, without touching the rest — used when a TP returns part of
 * an invoice (credit note) rather than the whole thing. Unlike
 * tpAdvanceRestore(), this never deletes the invoice's full deduction
 * history: it only unwinds as much as the return is actually worth, walking
 * the invoice's own tp_invoice_advance_log entries most-recent-first (LIFO —
 * the last advance payment spent on this invoice is the first one credited
 * back), shrinking each log row's deducted_amount as it goes and deleting a
 * row only once it reaches zero.
 *
 * If $amount exceeds what was ever deducted for this invoice (e.g. the
 * invoice was paid in cash/screenshot, not from advance balance, or the
 * return is worth more than the deduction), only the actually-deductible
 * portion is restored — the excess has nowhere to go back to and is
 * silently capped, not credited as new free money.
 *
 * @return float The amount actually restored (may be less than $amount).
 */
function tpAdvanceRestorePartial(mysqli $db, int $tp_invoice_id, float $amount): float
{
    if ($amount <= 0) return 0.0;

    $s = $db->prepare(
        "SELECT id, tp_advance_id, deducted_amount
           FROM tp_invoice_advance_log
          WHERE tp_invoice_id = ?
          ORDER BY id DESC
          FOR UPDATE"
    );
    $s->bind_param("i", $tp_invoice_id);
    $s->execute();
    $entries = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    $remaining = round($amount, 2);
    $restored  = 0.0;

    foreach ($entries as $entry) {
        if ($remaining <= 0.005) break;

        $log_id  = (int)   $entry['id'];
        $adv_id  = (int)   $entry['tp_advance_id'];
        $logged  = (float) $entry['deducted_amount'];
        $take    = min($remaining, $logged);

        $r = $db->prepare(
            "SELECT balance_amount, adjusted_amount FROM tp_advance_payments WHERE id=? FOR UPDATE"
        );
        $r->bind_param("i", $adv_id);
        $r->execute();
        $row = $r->get_result()->fetch_assoc();
        $r->close();

        if (!$row) continue; // payment row was deleted — skip, log entry left as-is

        $new_balance  = round((float)$row['balance_amount']  + $take, 2);
        $new_adjusted = round((float)$row['adjusted_amount'] - $take, 2);
        if ($new_adjusted < 0) $new_adjusted = 0;
        $status = $new_adjusted <= 0 ? 'active' : 'partially_adjusted';

        $u = $db->prepare(
            "UPDATE tp_advance_payments
                SET balance_amount=?, adjusted_amount=?, status=?
              WHERE id=?"
        );
        $u->bind_param("ddsi", $new_balance, $new_adjusted, $status, $adv_id);
        $u->execute();
        $u->close();

        $new_logged = round($logged - $take, 2);
        if ($new_logged <= 0.005) {
            $d = $db->prepare("DELETE FROM tp_invoice_advance_log WHERE id=?");
            $d->bind_param("i", $log_id);
            $d->execute();
            $d->close();
        } else {
            $ul = $db->prepare("UPDATE tp_invoice_advance_log SET deducted_amount=? WHERE id=?");
            $ul->bind_param("di", $new_logged, $log_id);
            $ul->execute();
            $ul->close();
        }

        $remaining -= $take;
        $restored  += $take;
    }

    return round($restored, 2);
}
