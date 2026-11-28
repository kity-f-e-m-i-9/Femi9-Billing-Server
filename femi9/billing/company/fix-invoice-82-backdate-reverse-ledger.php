<?php
// ONE-TIME FOLLOW-UP FIX — fix-invoice-82-cp-source.php (run live on
// 2026-11-27) correctly reversed the wrong Company-side deduct for invoice
// TP/26-27/1701 (tp_invoices.id=1964, product 330mm XL SW-9 PCS, id=9,
// qty=50), but that reverseDeduct() call was never explicitly backdated —
// unlike the transferOut() leg in the same script, which WAS backdated to
// the invoice's own date (2026-11-23 12:02:18).
//
// Effect: company/datewise-stock-report.php nets 'deduct' vs 'reverse_deduct'
// PER DAY. The original wrong deduct (50 units) is dated 23-Nov; its
// reversal is dated 27-Nov (today, when the fix ran) — so the 23-Nov report
// still shows the stale +50 as a Sale with nothing to net it against that
// day, while the correctly-backdated transferOut (also +50, "Movement to
// CP") is ALSO counted that day — double-counting 50 units and dragging
// Closing Stock negative (-28) for this product on 23-Nov.
//
// This script finds that one stock_ledger row (action='reverse_deduct',
// ref_type='tp_invoice', ref_id='TP/26-27/1701', product=9, LLP warehouse 2)
// and backdates its created_at to match every other backdated entry from the
// original fix (2026-11-23 12:02:18) — a pure timestamp correction, no
// quantity/balance anywhere is touched.
//
// SAFE TO RUN ONLY ONCE. Guarded: refuses if the row isn't found exactly
// once, or if its created_at is already the target date.
//
// Upload to company/, open once in a logged-in company browser tab, read the
// full output, then DELETE this file from the server.

include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
header('Content-Type: text/plain');

$DRY_RUN = !(isset($_GET['confirm']) && $_GET['confirm'] === 'yes');
echo $DRY_RUN
    ? "###################################################\n# DRY RUN — nothing will be saved.                 #\n###################################################\n\n"
    : "###################################################\n# LIVE RUN — this WILL be saved (?confirm=yes).    #\n###################################################\n\n";

const PRODUCT_ID       = 9;
const GODOWN_ID        = 1;   // FEMI NAYAN LLP
const WAREHOUSE_ID     = 2;   // G1
const INV_NUMBER       = 'TP/26-27/1701';
const TARGET_DATETIME  = '2026-11-23 12:02:18';

echo "=== Guard check ===\n";
$pid = PRODUCT_ID;
$godownIdStr = (string) GODOWN_ID;
$wh = WAREHOUSE_ID;
$ref = INV_NUMBER;
$stmt = $db_conn->prepare("
    SELECT id, created_at, qty, qty_before, qty_after
    FROM stock_ledger
    WHERE product_id = ? AND user_type = 'company' AND user_id = ?
      AND warehouse_id = ? AND action = 'reverse_deduct'
      AND ref_type = 'tp_invoice' AND ref_id = ?
");
$stmt->bind_param('isis', $pid, $godownIdStr, $wh, $ref);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (count($rows) !== 1) {
    die("ABORT: expected exactly 1 matching stock_ledger row, found " . count($rows) . ". Nothing changed. Investigate manually.\n");
}
$row = $rows[0];
echo "Found stock_ledger id={$row['id']}, created_at={$row['created_at']}, qty={$row['qty']} ({$row['qty_before']} -> {$row['qty_after']})\n\n";

if ($row['created_at'] === TARGET_DATETIME) {
    die("ABORT: created_at is already " . TARGET_DATETIME . " — this fix looks like it already ran. Nothing changed. Delete this file.\n");
}

echo "=== What this fix changes ===\n";
echo "- stock_ledger id={$row['id']}: created_at '{$row['created_at']}' -> '" . TARGET_DATETIME . "'\n";
echo "- Nothing else. No quantity, balance, or other row is touched.\n\n";

if ($DRY_RUN) {
    echo "=== DRY RUN COMPLETE — NOTHING SAVED ===\n";
    echo "If this looks correct, re-open this same URL with ?confirm=yes to apply.\n";
} else {
    $u = $db_conn->prepare("UPDATE stock_ledger SET created_at = ? WHERE id = ?");
    $target = TARGET_DATETIME;
    $u->bind_param('si', $target, $row['id']);
    $u->execute();
    $affected = $u->affected_rows;
    $u->close();
    echo "Rows updated: $affected (expected 1)\n";
    echo "\n=== DONE. ===\n";
    echo "Now delete this file from the server.\n";
}
