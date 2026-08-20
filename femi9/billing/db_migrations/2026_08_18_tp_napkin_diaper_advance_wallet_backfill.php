<?php
/**
 * One-time backfill for tp_advance_payments.product_type (and
 * tp_advance_payment_submissions.product_type), run once after
 * 2026_08_18_tp_napkin_diaper_advance_wallet.sql has been applied.
 *
 * Classification rule per advance-payment row, traced via
 * tp_invoice_advance_log (which invoice(s) that specific payment actually
 * funded) -> tp_invoices.product_type:
 *   - Funded ONLY Napkin invoices so far       -> 'napkin'
 *   - Funded ONLY Diaper invoices so far        -> 'diaper'
 *   - Funded BOTH at different times ("Mixed")  -> left at 'napkin' (the
 *     column default), NOT auto-split — printed to a review list instead.
 *     Splitting a mixed row's balance by guessed ratio was explicitly
 *     rejected; a human decides these individually, later, if needed.
 *   - Never funded anything yet (no log rows)   -> left at 'napkin' — no
 *     history to infer from, napkin is the safe majority default.
 *
 * tp_advance_payment_submissions (not-yet-approved TP-side submissions)
 * have no invoice history to trace at all — always defaults to 'napkin'
 * (the column default already covers this; nothing to backfill there).
 *
 * Safe to re-run: only touches rows currently at the column default
 * ('napkin') that have unambiguous single-type history, so re-running
 * after a partial run or after new invoices land just re-derives the same
 * (or updated) answer — it never overwrites a row a human already
 * corrected away from 'napkin'.
 *
 * Usage: php 2026_08_18_tp_napkin_diaper_advance_wallet_backfill.php [--apply]
 *   Without --apply: dry run, prints what it WOULD change, writes nothing.
 *   With --apply: performs the UPDATEs.
 */

require_once __DIR__ . '/../shared/env-loader.php';
require_once __DIR__ . '/../shared/TpProductType.php';

$servername = $_ENV['DB_HOST']     ?? 'localhost';
$db_port    = (int)($_ENV['DB_PORT'] ?? 3306);
$username   = $_ENV['DB_USERNAME'] ?? 'billing0femi9_femi9admin';
$password   = $_ENV['DB_PASSWORD'] ?? 'mavNip-xukvyk-9veqra';
$dbname     = $_ENV['DB_NAME']     ?? 'billing0femi9_billingapp';

$db = new mysqli($servername, $username, $password, $dbname, $db_port);
if ($db->connect_errno) {
    fwrite(STDERR, "DB connection failed: {$db->connect_error}\n");
    exit(1);
}

tpEnsureAdvanceWalletColumns($db);

$apply = in_array('--apply', $argv ?? [], true);

$payments = $db->query("
    SELECT id, territory_partner_id, amount, balance_amount, product_type
    FROM tp_advance_payments
    WHERE deleted_at IS NULL
    ORDER BY territory_partner_id, payment_date
")->fetch_all(MYSQLI_ASSOC);

$toNapkin = [];
$toDiaper = [];
$mixed = [];
$noHistory = [];

foreach ($payments as $p) {
    // Trace through the actual invoice line items' product category — NOT
    // tp_invoices.product_type, which for pre-Phase-2 invoices is just the
    // column's own 'napkin' default and carries no real historical signal.
    $log = $db->query("
        SELECT DISTINCT
            CASE WHEN p.category = 'diaper' THEN 'diaper' ELSE 'napkin' END AS derived_type
        FROM tp_invoice_advance_log l
        JOIN tp_invoice_items tii ON tii.tp_invoice_id = l.tp_invoice_id
        JOIN products p ON p.id = tii.product_id
        WHERE l.tp_advance_id = {$p['id']}
    ")->fetch_all(MYSQLI_ASSOC);

    $types = array_unique(array_column($log, 'derived_type'));

    if (count($types) === 0) {
        $noHistory[] = $p['id'];
    } elseif (count($types) === 1) {
        if ($types[0] === 'diaper') {
            $toDiaper[] = $p['id'];
        } else {
            $toNapkin[] = $p['id'];
        }
    } else {
        $mixed[] = $p;
    }
}

echo "Total advance payment rows: " . count($payments) . "\n";
echo "  -> napkin (unambiguous):  " . count($toNapkin) . "\n";
echo "  -> diaper (unambiguous):  " . count($toDiaper) . "\n";
echo "  -> mixed (left at napkin, needs manual review): " . count($mixed) . "\n";
echo "  -> no history (left at napkin default): " . count($noHistory) . "\n";

if (!empty($mixed)) {
    echo "\n--- MIXED rows (manual review) ---\n";
    foreach ($mixed as $p) {
        echo "  advance_id={$p['id']} tp_id={$p['territory_partner_id']} amount={$p['amount']} balance={$p['balance_amount']}\n";
    }
}

if (!$apply) {
    echo "\nDry run only — no changes written. Re-run with --apply to perform the UPDATEs.\n";
    exit(0);
}

$db->begin_transaction();
try {
    if (!empty($toNapkin)) {
        $ids = implode(',', array_map('intval', $toNapkin));
        $db->query("UPDATE tp_advance_payments SET product_type='napkin' WHERE id IN ($ids)");
    }
    if (!empty($toDiaper)) {
        $ids = implode(',', array_map('intval', $toDiaper));
        $db->query("UPDATE tp_advance_payments SET product_type='diaper' WHERE id IN ($ids)");
    }
    $db->commit();
    echo "\nApplied. " . count($toDiaper) . " rows set to diaper, " . count($toNapkin) . " rows confirmed napkin (already default).\n";
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, "Backfill failed, rolled back: {$e->getMessage()}\n");
    exit(1);
}
