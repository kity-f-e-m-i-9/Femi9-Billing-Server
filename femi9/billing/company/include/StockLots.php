<?php
declare(strict_types=1);

/**
 * FIFO lot tracking on top of StockService's existing stock/stock_ledger
 * tables. A "lot" is one stock-increasing event at a known rate; consuming
 * FIFO means always taking from the oldest lot with qty_remaining > 0
 * first, so a unit sold today costs whatever it actually cost to bring in,
 * not today's blended average rate.
 */
class StockLots
{
    public static function recordLot(
        mysqli $db,
        int $productId,
        string $userType,
        string $userId,
        float $rate,
        int $qty,
        string $purchaseDate,
        string $refType,
        ?string $refId,
        ?string $createdBy
    ): int {
        if ($qty <= 0) {
            return 0;
        }
        $stmt = $db->prepare(
            "INSERT INTO stock_lots
                (product_id, user_type, user_id, rate, qty_purchased,
                 qty_remaining, purchase_date, ref_type, ref_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'issdiissss',
            $productId, $userType, $userId, $rate, $qty,
            $qty, $purchaseDate, $refType, $refId, $createdBy
        );
        $stmt->execute();
        $id = (int) $db->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * @return array<int, array{stock_lot_id: ?int, qty_taken: int, rate: float}>
     */
    public static function consumeFifo(
        mysqli $db,
        int $productId,
        string $userType,
        string $userId,
        int $qtyNeeded,
        callable $fallbackRateFn
    ): array {
        $consumed = [];
        $remaining = $qtyNeeded;

        $stmt = $db->prepare(
            "SELECT id, qty_remaining, rate FROM stock_lots
             WHERE product_id = ? AND user_type = ? AND user_id = ? AND qty_remaining > 0
             ORDER BY purchase_date ASC, id ASC
             FOR UPDATE"
        );
        $stmt->bind_param('iss', $productId, $userType, $userId);
        $stmt->execute();
        $lots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $updateStmt = $db->prepare("UPDATE stock_lots SET qty_remaining = ? WHERE id = ?");

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (int) $lot['qty_remaining']);
            $newRemaining = (int) $lot['qty_remaining'] - $take;

            $updateStmt->bind_param('ii', $newRemaining, $lot['id']);
            $updateStmt->execute();

            $consumed[] = [
                'stock_lot_id' => (int) $lot['id'],
                'qty_taken'    => $take,
                'rate'         => (float) $lot['rate'],
            ];
            $remaining -= $take;
        }
        $updateStmt->close();

        if ($remaining > 0) {
            $consumed[] = [
                'stock_lot_id' => null,
                'qty_taken'    => $remaining,
                'rate'         => (float) $fallbackRateFn(),
            ];
        }

        return $consumed;
    }

    public static function writeConsumption(mysqli $db, int $stockLedgerId, array $consumed): void
    {
        if (empty($consumed)) {
            return;
        }
        $stmt = $db->prepare(
            "INSERT INTO stock_ledger_lot_consumption (stock_ledger_id, stock_lot_id, qty_taken, rate)
             VALUES (?, ?, ?, ?)"
        );
        foreach ($consumed as $c) {
            $stmt->bind_param('iiid', $stockLedgerId, $c['stock_lot_id'], $c['qty_taken'], $c['rate']);
            $stmt->execute();
        }
        $stmt->close();
    }

    public static function restoreConsumption(mysqli $db, int $stockLedgerId): void
    {
        $stmt = $db->prepare(
            "SELECT stock_lot_id, qty_taken FROM stock_ledger_lot_consumption WHERE stock_ledger_id = ?"
        );
        $stmt->bind_param('i', $stockLedgerId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($rows)) {
            return;
        }

        $restoreStmt = $db->prepare(
            "UPDATE stock_lots SET qty_remaining = qty_remaining + ? WHERE id = ?"
        );
        foreach ($rows as $r) {
            if ($r['stock_lot_id'] === null) {
                continue;
            }
            $restoreStmt->bind_param('ii', $r['qty_taken'], $r['stock_lot_id']);
            $restoreStmt->execute();
        }
        $restoreStmt->close();

        $delStmt = $db->prepare("DELETE FROM stock_ledger_lot_consumption WHERE stock_ledger_id = ?");
        $delStmt->bind_param('i', $stockLedgerId);
        $delStmt->execute();
        $delStmt->close();
    }
}
