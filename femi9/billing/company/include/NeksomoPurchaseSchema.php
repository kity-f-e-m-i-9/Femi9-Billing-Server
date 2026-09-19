<?php
declare(strict_types=1);

/**
 * Shared schema guard + piece-crediting helpers for Neksomo manufacturer
 * purchases, used by both the Add and Edit action handlers so the two
 * flows stay consistent (a duplicated copy in each file would drift).
 */

// Self-migrating: records which physical godown (warehouse) a purchase's
// stock was actually credited to. Nullable — existing purchases predate
// this column and have no known warehouse (same "unassigned" convention
// as every other warehouse_id column in this app).
function ensure_neksomo_manufacturer_purchases_warehouse_column(mysqli $db_conn): void
{
    $col = $db_conn->query("SHOW COLUMNS FROM neksomo_manufacturer_purchases LIKE 'warehouse_id'");
    if ($col && $col->num_rows === 0) {
        $db_conn->query("ALTER TABLE neksomo_manufacturer_purchases ADD COLUMN warehouse_id INT NULL AFTER purchase_date");
    }
}

/**
 * Credit a piece-wise purchase quantity onto a pack-based stock row.
 *
 * stock.extra_pieces holds loose pieces that haven't yet accumulated into a
 * whole pack. This adds $qtyPieces to that running remainder; whenever the
 * remainder reaches (or exceeds) one pack, the whole-pack portion is credited
 * to stock.closing_qty via StockService (so every other flow in the app keeps
 * seeing pack-based stock), and only the leftover sub-pack amount stays in
 * extra_pieces. Must run inside the caller's transaction.
 *
 * @return array{packs:int, ledger_id:?int}
 */
function neksomo_credit_pieces(
    mysqli $db, StockService $stockService,
    int $productId, string $godownId, int $piecesPerPack, int $qtyPieces,
    string $refId, string $createdBy, ?int $warehouseId = null
): array {
    if ($qtyPieces <= 0) {
        return ['packs' => 0, 'ledger_id' => null];
    }

    $sql = "SELECT extra_pieces FROM stock WHERE product_id = ? AND user_type = 'company' AND user_id = ?
              AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?') . " FOR UPDATE";
    $lock = $db->prepare($sql);
    if ($warehouseId === null) {
        $lock->bind_param('is', $productId, $godownId);
    } else {
        $lock->bind_param('isi', $productId, $godownId, $warehouseId);
    }
    $lock->execute();
    $row = $lock->get_result()->fetch_assoc();
    $lock->close();

    if ($row === null) {
        $ins = $db->prepare(
            "INSERT INTO stock
                (product_id, opening_qty, opening_date, input_qty, sales_qty,
                 sent_qty, returnqty, closing_qty, extra_pieces, user_type, user_id, warehouse_id, updated_at)
             VALUES (?, 0, CURDATE(), 0, 0, 0, 0, 0, 0, 'company', ?, ?, NOW())"
        );
        $ins->bind_param('isi', $productId, $godownId, $warehouseId);
        $ins->execute();
        $ins->close();
        $currentExtra = 0;
    } else {
        $currentExtra = (int) $row['extra_pieces'];
    }

    $total       = $currentExtra + $qtyPieces;
    $packs       = intdiv($total, $piecesPerPack);
    $newExtra    = $total % $piecesPerPack;
    $ledgerId    = null;

    if ($packs > 0) {
        $result   = $stockService->credit($productId, 'company', $godownId, $packs, 'adjustment', $refId, $createdBy, true, $warehouseId);
        $ledgerId = $result['ledger_id'];
    }

    $sqlUpd = "UPDATE stock SET extra_pieces = ?, updated_at = NOW()
               WHERE product_id = ? AND user_type = 'company' AND user_id = ?
                 AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?');
    $upd = $db->prepare($sqlUpd);
    if ($warehouseId === null) {
        $upd->bind_param('iis', $newExtra, $productId, $godownId);
    } else {
        $upd->bind_param('iisi', $newExtra, $productId, $godownId, $warehouseId);
    }
    $upd->execute();
    $upd->close();

    return ['packs' => $packs, 'ledger_id' => $ledgerId];
}

/**
 * Reverse a piece-wise purchase quantity from a pack-based stock row — the
 * mirror image of neksomo_credit_pieces(). Subtracts $qtyPieces from the
 * running loose-piece remainder (stock.extra_pieces); if that would go
 * negative, borrows back whole packs via StockService::reverseCredit() so
 * the remainder lands back in [0, piecesPerPack). Must run inside the
 * caller's transaction.
 *
 * Silently no-ops if no stock row exists for this (product, godown,
 * warehouse) — nothing to reverse.
 */
function neksomo_reverse_pieces(
    mysqli $db, StockService $stockService,
    int $productId, string $godownId, int $piecesPerPack, int $qtyPieces,
    string $refId, string $createdBy, ?int $warehouseId = null
): void {
    if ($qtyPieces <= 0) {
        return;
    }

    $sql = "SELECT extra_pieces FROM stock WHERE product_id = ? AND user_type = 'company' AND user_id = ?
              AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?') . " FOR UPDATE";
    $lock = $db->prepare($sql);
    if ($warehouseId === null) {
        $lock->bind_param('is', $productId, $godownId);
    } else {
        $lock->bind_param('isi', $productId, $godownId, $warehouseId);
    }
    $lock->execute();
    $row = $lock->get_result()->fetch_assoc();
    $lock->close();

    if ($row === null) {
        return; // nothing to reverse — no stock row exists for this product/warehouse
    }

    $net = (int) $row['extra_pieces'] - $qtyPieces;

    if ($net >= 0) {
        $newExtra = $net;
    } else {
        $packsToReverse = (int) ceil(abs($net) / $piecesPerPack);
        $stockService->reverseCredit($productId, 'company', $godownId, $packsToReverse, 'adjustment', $refId, $createdBy, true, $warehouseId);
        $newExtra = $net + ($packsToReverse * $piecesPerPack);
    }

    $sqlUpd = "UPDATE stock SET extra_pieces = ?, updated_at = NOW()
               WHERE product_id = ? AND user_type = 'company' AND user_id = ?
                 AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?');
    $upd = $db->prepare($sqlUpd);
    if ($warehouseId === null) {
        $upd->bind_param('iis', $newExtra, $productId, $godownId);
    } else {
        $upd->bind_param('iisi', $newExtra, $productId, $godownId, $warehouseId);
    }
    $upd->execute();
    $upd->close();
}
