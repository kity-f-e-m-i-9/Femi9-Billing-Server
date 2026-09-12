<?php
/**
 * fix-reverse-transfer-in-floor-bug.php
 *
 * One-time repair script for the Sep 8, 2026 StockService bug:
 * StockService::reverseTransferIn() used to do
 *   $after = max(0, $before - $qty);
 * which silently floored closing_qty to 0 instead of refusing the
 * reversal when the transferred-in stock had already been partly
 * sold/moved on. See STOCK_AUDIT_2026.md and
 * femi9/billing/company/include/StockService.php:801 (fixed in commit
 * d3c6e51 on main — this script only repairs already-corrupted DATA,
 * it does not change behavior going forward).
 *
 * WHAT IT FIXES (conservatively — see SCOPE below):
 * A stock row where the single most recent stock_ledger entry is a
 * 'transfer_in_reverse' with qty_before < qty (the bug's exact
 * signature) AND that entry's qty_after matches the row's CURRENT
 * closing_qty (i.e. nothing legitimate has happened on the row since
 * the bug fired, so it's still sitting broken right now).
 *
 * For such a row, the correct closing_qty is simply qty_before from
 * that bad ledger entry — the reversal should have been refused
 * entirely, leaving stock untouched.
 *
 * SCOPE / WHAT IT DELIBERATELY DOES NOT TOUCH:
 * - Rows where the bad entry was NOT the latest at the time of running
 *   (later legitimate transactions already happened) — these may have
 *   self-healed (see product 44 in the investigation) and blindly
 *   "fixing" them risks double-correcting. Reported for manual review.
 * - Rows affected by CASCADING paired-transfer deletions (delete of a
 *   receive-then-forward pair, e.g. products 9/11/14 in the original
 *   incident) — the correct target value there depends on
 *   understanding the specific pair, not a generic formula. Reported
 *   for manual review with full context, not auto-fixed.
 * - Any row already carrying a correction (created_by =
 *   'system-correction') for this bug — skipped as already done.
 *
 * USAGE:
 *   php fix-reverse-transfer-in-floor-bug.php            # dry run (default) — reports only, writes nothing
 *   php fix-reverse-transfer-in-floor-bug.php --apply     # actually applies the safe fixes
 *
 * Run from the machine/network that can reach the target DB configured
 * in femi9/billing/shared/.env (point that .env at production before
 * running --apply there, or override via env vars — see CONFIG below).
 */

require_once __DIR__ . '/../include/db-connect.php'; // uses $db_conn

$DRY_RUN = !in_array('--apply', $argv, true);

echo "==========================================================\n";
echo " reverseTransferIn floor-bug repair script\n";
echo " Mode: " . ($DRY_RUN ? "DRY RUN (no writes)" : "APPLY (will write to DB)") . "\n";
echo " DB:   " . ($db_conn->host_info ?? 'unknown') . " / " . $dbname . "\n";
echo "==========================================================\n\n";

if (!$DRY_RUN) {
    echo "You are about to WRITE to the database above. Type YES to continue: ";
    $confirm = trim(fgets(STDIN));
    if ($confirm !== 'YES') {
        echo "Aborted.\n";
        exit(1);
    }
}

// -------------------------------------------------------------------------
// STEP 1: find every transfer_in_reverse entry that shows the bug's
// underflow signature (qty_before < qty), skipping ones already fixed.
// -------------------------------------------------------------------------
$sql = "
    SELECT sl.id, sl.product_id, p.productName, sl.user_type, sl.user_id,
           sl.qty, sl.qty_before, sl.qty_after, sl.ref_id, sl.created_at,
           s.closing_qty AS current_closing_qty,
           s.input_qty   AS current_input_qty,
           (SELECT MAX(id) FROM stock_ledger x
             WHERE x.product_id = sl.product_id
               AND x.user_type  = sl.user_type
               AND x.user_id    = sl.user_id) AS latest_ledger_id,
           (SELECT COUNT(*) FROM stock_ledger fix
             WHERE fix.product_id  = sl.product_id
               AND fix.user_type   = sl.user_type
               AND fix.user_id     = sl.user_id
               AND fix.created_by  = 'system-correction'
               AND fix.created_at >= sl.created_at) AS already_corrected
    FROM stock_ledger sl
    JOIN products p ON p.id = sl.product_id
    JOIN stock s ON s.product_id = sl.product_id
                AND s.user_type  = sl.user_type
                AND s.user_id    = sl.user_id
    WHERE sl.action = 'transfer_in_reverse'
      AND sl.qty_before < sl.qty
      AND sl.created_by != 'system-correction'
    ORDER BY sl.created_at
";

$result = $db_conn->query($sql);
if ($result === false) {
    fwrite(STDERR, "Query failed: " . $db_conn->error . "\n");
    exit(1);
}

$safeToFix    = [];
$needsReview  = [];

while ($row = $result->fetch_assoc()) {
    if ((int)$row['already_corrected'] > 0) {
        continue; // already handled
    }

    $isLatest       = ((int)$row['id'] === (int)$row['latest_ledger_id']);
    $matchesCurrent = ((int)$row['qty_after'] === (int)$row['current_closing_qty']);

    if ($isLatest && $matchesCurrent) {
        $safeToFix[] = $row;
    } else {
        $needsReview[] = $row;
    }
}

// -------------------------------------------------------------------------
// STEP 2: report
// -------------------------------------------------------------------------
echo "SAFE TO AUTO-FIX (" . count($safeToFix) . " row" . (count($safeToFix) === 1 ? '' : 's') . "):\n";
echo str_repeat('-', 100) . "\n";
foreach ($safeToFix as $row) {
    printf(
        "  product #%d %-45s %-10s %-30s  %d -> %d\n",
        $row['product_id'],
        $row['productName'],
        $row['user_type'],
        $row['user_id'],
        (int)$row['current_closing_qty'],
        (int)$row['qty_before']
    );
}

echo "\nNEEDS MANUAL REVIEW (" . count($needsReview) . " row" . (count($needsReview) === 1 ? '' : 's') . " — not auto-fixed):\n";
echo str_repeat('-', 100) . "\n";
foreach ($needsReview as $row) {
    $reason = ((int)$row['id'] !== (int)$row['latest_ledger_id'])
        ? 'later ledger activity exists on this row since the bad entry — may have self-healed'
        : 'current stock does not match the bad entry\'s qty_after — investigate before touching';
    printf(
        "  ledger #%-6d product #%d %-45s %-10s %-30s  ref=%s\n    reason: %s\n",
        $row['id'],
        $row['product_id'],
        $row['productName'],
        $row['user_type'],
        $row['user_id'],
        $row['ref_id'],
        $reason
    );
}

if (empty($safeToFix)) {
    echo "\nNothing to auto-fix.\n";
    exit(0);
}

if ($DRY_RUN) {
    echo "\nDry run only — no changes written. Re-run with --apply to fix the " . count($safeToFix) . " safe row(s) above.\n";
    exit(0);
}

// -------------------------------------------------------------------------
// STEP 3: apply fixes, one row per transaction
// -------------------------------------------------------------------------
echo "\nApplying fixes...\n";
$fixedCount = 0;
$failedCount = 0;

foreach ($safeToFix as $row) {
    $productId  = (int) $row['product_id'];
    $userType   = $row['user_type'];
    $userId     = $row['user_id'];
    $qty        = (int) $row['qty'];
    $before     = (int) $row['current_closing_qty']; // corrupted value (0 in the known cases)
    $correct    = (int) $row['qty_before'];           // value it should be restored to
    $refId      = $row['ref_id'];
    $shortfall  = $qty - $correct; // units that were actually gone (sold/moved) already

    $db_conn->begin_transaction();
    try {
        $stmt = $db_conn->prepare(
            "UPDATE stock
                SET closing_qty = ?,
                    input_qty   = GREATEST(0, input_qty - ?),
                    updated_at  = NOW()
              WHERE product_id = ? AND user_type = ? AND user_id = ?"
        );
        // Note: input_qty is reduced by $shortfall, not the full $qty,
        // mirroring the manual repair pattern: only the units that
        // could not be reversed (already consumed) are removed from
        // input_qty; the rest of the original transfer_in's input_qty
        // contribution stays, since closing_qty itself is being
        // restored to $correct (not further reduced).
        $stmt->bind_param('iiiss', $correct, $shortfall, $productId, $userType, $userId);
        $stmt->execute();
        $stmt->close();

        $note = sprintf(
            "Correction: reverseTransferIn bug floored closing_qty to 0 instead of refusing "
            . "when only %d of the %d transferred units remained (%d already sold/moved). "
            . "Restored to %d via fix-reverse-transfer-in-floor-bug.php.",
            $correct, $qty, $shortfall, $correct
        );

        $stmt2 = $db_conn->prepare(
            "INSERT INTO stock_ledger
                (product_id, user_type, user_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
             VALUES (?, ?, ?, 'transfer_in_reverse', ?, 0, ?, 'adjustment', ?, ?, 'system-correction')"
        );
        $stmt2->bind_param('issiiss', $productId, $userType, $userId, $shortfall, $correct, $refId, $note);
        $stmt2->execute();
        $stmt2->close();

        $db_conn->commit();
        $fixedCount++;
        printf(
            "  FIXED: product #%d %s / %s / %s : %d -> %d\n",
            $productId, $row['productName'], $userType, $userId, $before, $correct
        );
    } catch (\Throwable $e) {
        $db_conn->rollback();
        $failedCount++;
        fwrite(STDERR, sprintf(
            "  FAILED: product #%d %s / %s : %s\n",
            $productId, $userType, $userId, $e->getMessage()
        ));
    }
}

echo "\nDone. Fixed: $fixedCount, Failed: $failedCount, Needs manual review: " . count($needsReview) . "\n";
exit($failedCount > 0 ? 1 : 0);
