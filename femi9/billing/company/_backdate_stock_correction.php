<?php
// TEMPORARY one-time fix — the 4 manual stock-correction ledger entries
// (ref_id manual_corr_20261027_1..4, written when _manual_stock_correction_ot_delete.php
// was run on 2026-10-27) credited the right qty back, but got dated "today"
// (the day the correction was run), not 2026-09-22 (the date the original OT
// invoices — the ones that got deleted — actually happened). The per-day
// Datewise Stock report (include/DatewiseStockReport.php) buckets by
// stock_ledger.created_at, so this showed as a confusing negative-sales dip
// on 27-Oct instead of 26-Oct. This moves just the `created_at` timestamp
// on those 4 rows to 2026-10-26 — qty/before/after are untouched, since
// those were already correct.
//
// Default mode is DRY RUN. Visit with ?apply=1 to actually commit.
// Delete this file after use.
include("checksession.php");
include("config.php");
error_reporting(0);

header('Content-Type: text/plain; charset=utf-8');

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['admin', 'finance'], true)) {
    die("Access denied — admin/finance only.\n");
}

$apply = isset($_GET['apply']) && $_GET['apply'] == '1';
$newDate = '2026-10-26 18:00:00';

echo "=== Backdate manual stock-correction ledger entries to 2026-10-26 ===\n";
echo "Mode: " . ($apply ? "APPLY (will commit)" : "DRY RUN (nothing written — add ?apply=1 to commit)") . "\n\n";

$res = $db_conn->query("SELECT id, ref_id, product_id, qty, created_at FROM stock_ledger WHERE ref_id LIKE 'manual_corr_20261027_%' ORDER BY id");
$rows = [];
while ($row = $res->fetch_assoc()) { $rows[] = $row; }

if (!$rows) {
    echo "No matching ledger rows found (ref_id manual_corr_20261027_%).\n";
    exit;
}

foreach ($rows as $row) {
    echo "ledger id={$row['id']} ref={$row['ref_id']} product={$row['product_id']} qty={$row['qty']}\n";
    echo "  created_at: {$row['created_at']}  ->  $newDate\n";
}

if ($apply) {
    $stmt = $db_conn->prepare("UPDATE stock_ledger SET created_at = ? WHERE ref_id LIKE 'manual_corr_20261027_%'");
    $stmt->bind_param('s', $newDate);
    $stmt->execute();
    echo "\nCOMMITTED. " . $stmt->affected_rows . " rows updated.\n";
    $stmt->close();
} else {
    echo "\nDry run only — revisit with ?apply=1 to commit.\n";
}

echo "Done. Delete this file after use.\n";
