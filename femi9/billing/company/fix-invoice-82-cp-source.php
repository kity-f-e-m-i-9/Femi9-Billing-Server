<?php
// ONE-TIME FIX — invoice TP/26-27/1701 (tp_invoices.id=1964) was wrongly
// recorded as Company-godown-fulfilled (FEMI NAYAN LLP) instead of
// CP-fulfilled (Ranjith Kanagaraj, CP-0002). User confirmed (2026-11-27) the
// real stock came from the CP, and wants the FULL trail corrected, not just
// the invoice label:
//   1. Reverse the original wrong "Company deducted stock for this sale" entry.
//   2. Record a real Company(LLP) -> CP(Ranjith) stock transfer, backdated to
//      the invoice's own date (2026-11-23), so it shows up in Internal
//      Transfer history exactly as if it had really happened that day.
//   3. Record the CP's own stock leaving to fulfil this same invoice (same
//      date), so Ranjith's own stock ledger shows it passing through him.
//   4. Point the invoice itself at the CP as its source.
//
// Net effect on CURRENT balances: FEMI NAYAN LLP ends exactly where it is
// today (the reversal and the new transfer-out cancel out); Ranjith's CP
// stock also ends exactly where it is today (the new transfer-in and the new
// transfer-out cancel out) — only the LEDGER TRAIL changes, not today's
// numbers. This is intentional: today's numbers were already correct in
// total, only the *path* the stock took was recorded wrong.
//
// SAFE TO RUN ONLY ONCE. Guarded: refuses to run a second time (checks
// tp_invoices.source_cp_id is still 0 before doing anything).
//
// Upload to company/, open once in a logged-in company browser tab, read the
// full output carefully, then DELETE this file from the server.

include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('ms');
include("config.php");
require_once("include/StockService.php");
header('Content-Type: text/plain');

// ── Dry-run by default — add ?confirm=yes to the URL to actually save. ──────
// A dry run does every step for real (so the printed before/after numbers are
// exact), but ROLLS BACK instead of committing — nothing is saved, nothing
// else on the site is affected, safe to run as many times as you like.
$DRY_RUN = !(isset($_GET['confirm']) && $_GET['confirm'] === 'yes');
echo $DRY_RUN
    ? "###################################################\n# DRY RUN — nothing will be saved. Re-run with     #\n# ?confirm=yes in the URL once this output looks   #\n# correct, to actually apply the fix.               #\n###################################################\n\n"
    : "###################################################\n# LIVE RUN — this WILL be saved (?confirm=yes).    #\n###################################################\n\n";

const INV_ID       = 1964;
const INV_NUMBER   = 'TP/26-27/1701';
const PRODUCT_ID   = 9;
const QTY          = 50;
const GODOWN_ID    = 1;   // FEMI NAYAN LLP
const WAREHOUSE_ID = 2;
const CP_ID        = 2;   // Ranjith Kanagaraj, CP-0002
const CREATED_BY   = '8807693328';
const INVOICE_DATE = '2026-11-23';
const INVOICE_DATETIME = '2026-11-23 12:02:18';

function creditCpFix(mysqli $db, int $cp_id, int $pid, int $qty): void {
    $s = $db->prepare("INSERT INTO channel_partner_stock (channel_partner_id,product_id,input_qty,closing_qty) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE input_qty=input_qty+VALUES(input_qty), closing_qty=closing_qty+VALUES(input_qty)");
    $s->bind_param("iiii", $cp_id, $pid, $qty, $qty); $s->execute(); $s->close();
}
function debitCpFix(mysqli $db, int $cp_id, int $pid, int $qty): void {
    $s = $db->prepare("UPDATE channel_partner_stock SET closing_qty=closing_qty-? WHERE channel_partner_id=? AND product_id=?");
    $s->bind_param("iii", $qty, $cp_id, $pid); $s->execute(); $s->close();
}
function getCpQtyFix(mysqli $db, int $cp_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM channel_partner_stock WHERE channel_partner_id=? AND product_id=? FOR UPDATE");
    $s->bind_param("ii", $cp_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}
function insertCpLedgerFix(mysqli $db, int $cp_id, int $pid, string $action, int $qty, int $before, int $after, string $ref_type, string $ref_id, string $by): int {
    $note = 'Backfill correction for ' . INV_NUMBER . ' — source corrected from Company godown to this CP';
    $s = $db->prepare("INSERT INTO channel_partner_stock_ledger (channel_partner_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param("iisiiissss", $cp_id, $pid, $action, $qty, $before, $after, $ref_type, $ref_id, $note, $by);
    $s->execute();
    $id = (int)$db->insert_id;
    $s->close();
    return $id;
}
function backdate(mysqli $db, string $table, int $id, string $datetime): void {
    $s = $db->prepare("UPDATE `$table` SET created_at = ? WHERE id = ?");
    $s->bind_param("si", $datetime, $id);
    $s->execute();
    $s->close();
}

echo "=== Guard check ===\n";
$chk = $db_conn->query("SELECT id, source_cp_id, source_godown_id, warehouse_id FROM tp_invoices WHERE id = " . INV_ID . " AND invoice_number = '" . INV_NUMBER . "'")->fetch_assoc();
if (!$chk) { die("ABORT: invoice not found — nothing changed.\n"); }
if ((int)$chk['source_cp_id'] !== 0) {
    die("ABORT: source_cp_id is already {$chk['source_cp_id']} (not 0) — this fix looks like it already ran. Nothing changed. Delete this file.\n");
}
echo "OK — invoice still shows source_cp_id=0, source_godown_id={$chk['source_godown_id']}, warehouse_id={$chk['warehouse_id']}. Proceeding.\n\n";

$db_conn->begin_transaction();
try {
    $stockService = new StockService($db_conn);

    // ── Current state, BEFORE touching anything — exactly what this fix sees ──
    $productRow = $db_conn->query("SELECT productName FROM products WHERE id = " . PRODUCT_ID)->fetch_assoc();
    $productName = $productRow['productName'] ?? ('Product #' . PRODUCT_ID);
    $llpNow = $stockService->getClosingQty(PRODUCT_ID, 'company', (string) GODOWN_ID, WAREHOUSE_ID);
    $cpNow  = getCpQtyFix($db_conn, CP_ID, PRODUCT_ID);
    echo "=== Current state (before this fix touches anything) ===\n";
    echo "Product: $productName (id=" . PRODUCT_ID . ")\n";
    echo "FEMI NAYAN LLP (warehouse " . WAREHOUSE_ID . ") closing_qty right now: $llpNow\n";
    echo "Ranjith Kanagaraj (CP-0002) closing_qty right now: $cpNow\n";
    echo "Qty being corrected: " . QTY . "\n\n";
    echo "=== What this fix changes ===\n";
    echo "- LLP's closing_qty: will end at $llpNow again (unchanged) — only its LEDGER TRAIL\n";
    echo "  changes, from 'this invoice deducted it directly' to 'it was transferred to the CP,\n";
    echo "  then the CP used it for this invoice'.\n";
    echo "- Ranjith Kanagaraj's closing_qty: will end at $cpNow again (unchanged) — a transfer-in\n";
    echo "  of " . QTY . " then a transfer-out of " . QTY . " for this invoice, both dated the invoice's\n";
    echo "  own date (" . INVOICE_DATE . "), net zero change to his current balance.\n";
    echo "- A new, real Internal Transfer record (LLP -> Ranjith Kanagaraj, " . QTY . " units,\n";
    echo "  dated " . INVOICE_DATE . ") will appear in Internal Transfer history.\n";
    echo "- The invoice itself (TP/26-27/1701) will show Ranjith Kanagaraj as its source.\n";
    echo "- Nothing else — no other invoice, product, TP, or CP is touched.\n\n";

    // ── Step 1: reverse the original wrong company-side deduct ─────────────
    echo "=== Step 1: reverse original wrong deduct (LLP, warehouse 2) ===\n";
    $before1 = $stockService->getClosingQty(PRODUCT_ID, 'company', (string) GODOWN_ID, WAREHOUSE_ID);
    echo "LLP closing_qty before reverse: $before1\n";
    $stockService->reverseDeduct(
        PRODUCT_ID, 'company', (string) GODOWN_ID, QTY,
        'tp_invoice', INV_NUMBER, CREATED_BY,
        true, WAREHOUSE_ID
    );
    $after1 = $stockService->getClosingQty(PRODUCT_ID, 'company', (string) GODOWN_ID, WAREHOUSE_ID);
    echo "LLP closing_qty after reverse: $after1 (expected: " . ($before1 + QTY) . ")\n\n";

    // ── Step 2: real Company -> CP transfer, backdated to the invoice date ──
    echo "=== Step 2: create pl_godown_transfers (LLP -> Ranjith Kanagaraj) ===\n";
    $note = 'Backfill: ' . INV_NUMBER . ' was originally recorded as Company-fulfilled by mistake — this stock actually came from this CP';
    $godownIdVar2 = GODOWN_ID;
    $cpIdVar2 = CP_ID;
    $invoiceDateVar = INVOICE_DATE;
    $refPlaceholder = '';
    $createdByVar2 = CREATED_BY;
    $s = $db_conn->prepare("INSERT INTO pl_godown_transfers (transfer_type,godown_id,cp_id,transfer_date,ref_number,note,created_by) VALUES ('godown_to_location',?,?,?,?,?,?)");
    $s->bind_param("iissss", $godownIdVar2, $cpIdVar2, $invoiceDateVar, $refPlaceholder, $note, $createdByVar2);
    $s->execute();
    $transfer_id = (int)$db_conn->insert_id;
    $s->close();

    $ref_id = 'PLT-BACKFILL-' . str_pad((string)$transfer_id, 5, '0', STR_PAD_LEFT);
    $db_conn->query("UPDATE pl_godown_transfers SET ref_number='" . $db_conn->real_escape_string($ref_id) . "' WHERE id=$transfer_id");
    $db_conn->query("UPDATE pl_godown_transfers SET created_at = '" . INVOICE_DATETIME . "' WHERE id=$transfer_id");
    echo "pl_godown_transfers row created: id=$transfer_id, ref_number=$ref_id\n";

    $si = $db_conn->prepare("INSERT INTO pl_godown_transfer_items (transfer_id,product_id,quantity) VALUES (?,?,?)");
    $qtyVar = QTY; $pidVar = PRODUCT_ID;
    $si->bind_param("iii", $transfer_id, $pidVar, $qtyVar);
    $si->execute();
    $si->close();
    echo "pl_godown_transfer_items row created (product=" . PRODUCT_ID . ", qty=" . QTY . ")\n\n";

    echo "=== Step 3: debit LLP for this transfer (real transfer_out, not a sale) ===\n";
    $before2 = $stockService->getClosingQty(PRODUCT_ID, 'company', (string) GODOWN_ID, WAREHOUSE_ID);
    $outResult = $stockService->transferOut(
        PRODUCT_ID, 'company', (string) GODOWN_ID, QTY,
        'transfer', $ref_id, CREATED_BY,
        true, WAREHOUSE_ID
    );
    $after2 = $stockService->getClosingQty(PRODUCT_ID, 'company', (string) GODOWN_ID, WAREHOUSE_ID);
    echo "LLP closing_qty before transfer-out: $before2, after: $after2 (expected: " . ($before2 - QTY) . ")\n";
    $newLedgerId1 = (int)$outResult['ledger_id'];
    backdate($db_conn, 'stock_ledger', $newLedgerId1, INVOICE_DATETIME);
    echo "stock_ledger id=$newLedgerId1 backdated to " . INVOICE_DATETIME . "\n\n";

    echo "=== Step 4: credit Ranjith Kanagaraj (CP-0002) for this transfer ===\n";
    $cpBefore1 = getCpQtyFix($db_conn, CP_ID, PRODUCT_ID);
    creditCpFix($db_conn, CP_ID, PRODUCT_ID, QTY);
    $cpAfter1 = $cpBefore1 + QTY;
    echo "CP closing_qty before transfer-in: $cpBefore1, after: $cpAfter1\n";
    $cpLedgerId1 = insertCpLedgerFix($db_conn, CP_ID, PRODUCT_ID, 'transfer_in', QTY, $cpBefore1, $cpAfter1, 'transfer', $ref_id, CREATED_BY);
    backdate($db_conn, 'channel_partner_stock_ledger', $cpLedgerId1, INVOICE_DATETIME);
    echo "channel_partner_stock_ledger id=$cpLedgerId1 (transfer_in) backdated\n\n";

    echo "=== Step 5: debit Ranjith Kanagaraj for fulfilling this invoice (same date) ===\n";
    $cpBefore2 = getCpQtyFix($db_conn, CP_ID, PRODUCT_ID);
    debitCpFix($db_conn, CP_ID, PRODUCT_ID, QTY);
    $cpAfter2 = $cpBefore2 - QTY;
    echo "CP closing_qty before invoice debit: $cpBefore2, after: $cpAfter2 (expected 0, matching where it stood before this script ran)\n";
    $cpLedgerId2 = insertCpLedgerFix($db_conn, CP_ID, PRODUCT_ID, 'transfer_out', QTY, $cpBefore2, $cpAfter2, 'tp_invoice', INV_NUMBER, CREATED_BY);
    backdate($db_conn, 'channel_partner_stock_ledger', $cpLedgerId2, INVOICE_DATETIME);
    echo "channel_partner_stock_ledger id=$cpLedgerId2 (transfer_out, ref=" . INV_NUMBER . ") backdated\n\n";

    echo "=== Step 6: point the invoice at its real source (CP) ===\n";
    $u = $db_conn->prepare("UPDATE tp_invoices SET source_cp_id = ?, source_godown_id = 0, warehouse_id = NULL WHERE id = ? AND invoice_number = ?");
    $cpIdVar = CP_ID; $invIdVar = INV_ID; $invNumVar = INV_NUMBER;
    $u->bind_param("iis", $cpIdVar, $invIdVar, $invNumVar);
    $u->execute();
    $invoiceRowsAffected = $u->affected_rows;
    $u->close();
    echo "tp_invoices rows updated: $invoiceRowsAffected (expected 1)\n";

    if ($invoiceRowsAffected !== 1) {
        throw new Exception("tp_invoices update did not affect exactly 1 row — rolling back everything.");
    }

    $llpFinal = $stockService->getClosingQty(PRODUCT_ID, 'company', (string) GODOWN_ID, WAREHOUSE_ID);
    $cpFinal  = getCpQtyFix($db_conn, CP_ID, PRODUCT_ID);

    if ($DRY_RUN) {
        $db_conn->rollback();
        echo "\n=== DRY RUN COMPLETE — NOTHING SAVED (rolled back) ===\n";
        echo "If this had been a real run: LLP closing_qty would end at $llpFinal (matches current $llpNow? " . ($llpFinal == $llpNow ? 'YES' : 'NO — STOP, investigate') . ")\n";
        echo "If this had been a real run: CP closing_qty would end at $cpFinal (matches current $cpNow? " . ($cpFinal == $cpNow ? 'YES' : 'NO — STOP, investigate') . ")\n";
        echo "\nIf everything above looks correct, re-open this same URL with ?confirm=yes added\n";
        echo "to actually apply the fix. Example:\n";
        echo "  " . (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http') . "://" . ($_SERVER['HTTP_HOST'] ?? 'femi9billing.com') . $_SERVER['SCRIPT_NAME'] . "?confirm=yes\n";
    } else {
        $db_conn->commit();
        echo "\n=== DONE. Transaction committed (real run). ===\n";
        echo "Final check — LLP closing_qty: $llpFinal (matches what it was before this script ran? " . ($llpFinal == $llpNow ? 'YES' : 'NO') . ")\n";
        echo "Final check — CP closing_qty: $cpFinal (matches what it was before this script ran? " . ($cpFinal == $cpNow ? 'YES' : 'NO') . ")\n";
        echo "\nNow delete this file from the server.\n";
    }

} catch (\Throwable $e) {
    $db_conn->rollback();
    echo "\n!!! FAILED, ROLLED BACK: " . $e->getMessage() . "\n";
    echo "Nothing was changed. Safe to investigate and re-run.\n";
}
