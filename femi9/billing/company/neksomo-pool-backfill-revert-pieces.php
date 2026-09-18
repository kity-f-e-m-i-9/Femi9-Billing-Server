<?php
/**
 * One-time revert: undoes neksomo-pool-backfill.php's effect on
 * unit_type='pieces' Neksomo products (napkins), which was later decided
 * to be wrong — pieces-type pool stock should only become real pack stock
 * through an explicit Convert Pieces <-> Packs action, never an automatic
 * pieces/pieces_per_pack division. Pack-type products (diapers) are
 * unaffected and keep their backfilled stock.
 *
 * Reverses exactly the 3 `neksomo_stock_conversions` rows the original
 * backfill created for unit_type='pieces' Neksomo products: decrements
 * closing_qty/input_qty back by the credited amount (mirroring
 * StockService::credit()'s own accounting in reverse), writes a
 * corresponding reversal ledger entry, and deletes the conversion rows so
 * the pool's "already converted" bookkeeping no longer double-counts them.
 *
 * CLI only. Run once: php neksomo-pool-backfill-revert-pieces.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

chdir(__DIR__);
require_once("include/db-connect.php");
require_once("include/NeksomoStockBridge.php");

$neksomoGodownId    = get_neksomo_godown_id($db_conn);
$neksomoGodownIdStr = (string) $neksomoGodownId;
if (!$neksomoGodownId) {
    exit("Could not resolve NEKSOMO HYGIENE INDUSTRIES godown id.\n");
}

$rows = $db_conn->query(
    "SELECT nsc.id, nsc.neksomo_product_id, nsc.company_product_id, nsc.qty_company_packs
     FROM neksomo_stock_conversions nsc
     JOIN products np ON np.id = nsc.neksomo_product_id
     WHERE nsc.created_by = 'neksomo-pool-backfill' AND np.unit_type = 'pieces'"
)->fetch_all(MYSQLI_ASSOC);

if (empty($rows)) {
    exit("Nothing to revert — no pieces-type backfill conversions found.\n");
}

$reverted = 0;
foreach ($rows as $row) {
    $companyProductId = (int) $row['company_product_id'];
    $qty               = (int) $row['qty_company_packs'];
    $conversionId      = (int) $row['id'];

    $db_conn->begin_transaction();
    try {
        $stmt = $db_conn->prepare(
            "SELECT closing_qty, input_qty FROM stock
             WHERE product_id = ? AND user_type = 'company' AND user_id = ? AND warehouse_id IS NULL
             FOR UPDATE"
        );
        $stmt->bind_param('is', $companyProductId, $neksomoGodownIdStr);
        $stmt->execute();
        $stockRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($stockRow === null) {
            throw new \RuntimeException("No stock row for product=$companyProductId at Neksomo godown");
        }

        $before = (int) $stockRow['closing_qty'];
        $after  = $before - $qty;
        if ($after < 0) {
            throw new \RuntimeException("Reverting $qty would take product=$companyProductId closing_qty below 0 (currently $before) — stopping, needs manual review");
        }

        $stmtUpd = $db_conn->prepare(
            "UPDATE stock SET closing_qty = ?, input_qty = GREATEST(input_qty - ?, 0), updated_at = NOW()
             WHERE product_id = ? AND user_type = 'company' AND user_id = ? AND warehouse_id IS NULL"
        );
        $stmtUpd->bind_param('iiis', $after, $qty, $companyProductId, $neksomoGodownIdStr);
        $stmtUpd->execute();
        $stmtUpd->close();

        $refId = 'neksomo_backfill_revert_' . uniqid();
        $stmtLedger = $db_conn->prepare(
            "INSERT INTO stock_ledger
                (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by, created_at)
             VALUES (?, 'company', ?, NULL, 'reverse_credit', ?, ?, ?, 'adjustment', ?, 'Revert erroneous pieces-type pool backfill', 'neksomo-pool-backfill-revert', NOW())"
        );
        $stmtLedger->bind_param('isiiis', $companyProductId, $neksomoGodownIdStr, $qty, $before, $after, $refId);
        $stmtLedger->execute();
        $stmtLedger->close();

        $stmtDel = $db_conn->prepare("DELETE FROM neksomo_stock_conversions WHERE id = ?");
        $stmtDel->bind_param('i', $conversionId);
        $stmtDel->execute();
        $stmtDel->close();

        $db_conn->commit();
    } catch (\Throwable $e) {
        $db_conn->rollback();
        echo "FAILED product=$companyProductId: {$e->getMessage()}\n";
        continue;
    }

    $reverted++;
    echo "Reverted $qty pack(s) from company product $companyProductId (closing_qty $before -> $after), deleted conversion #$conversionId\n";
}

echo "Done. $reverted conversion(s) reverted.\n";
