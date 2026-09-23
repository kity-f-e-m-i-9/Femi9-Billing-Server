<?php
require_once __DIR__ . '/NeksomoStockBridge.php';
require_once __DIR__ . '/StockLots.php';

/**
 * StockService — centralized, transactional stock management.
 *
 * Every write to the `stock` table goes through this class.
 * Every change is recorded in `stock_ledger` (immutable audit trail).
 *
 * All public methods expect $db_conn to already be OUTSIDE a transaction
 * unless you pass $externalTransaction = true, in which case the caller
 * owns BEGIN / COMMIT / ROLLBACK.
 */
class StockService
{
    /** User types that carry their own stock ledger */
    const STOCK_MAINTAINING_TYPES = [
        'company', 'super_stockiest', 'stockiest',
        'super_distributor', 'distributor', 'candf',
    ];

    /** @var mysqli */
    private $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    // Self-migrating — see db_migrations/2026_09_19_stock_ledger_conversion_ref_type.sql,
    // written for the same reason: convertPiecesToPack()/convertPackToPieces()
    // (and neksomo-piece-pack-convert-action.php's mapped-product deduct/
    // credit path) pass ref_type='conversion', but any environment where
    // that migration hasn't been manually run is still on the older ENUM
    // and fails with "Data truncated for column 'ref_type'" on every
    // Convert Pieces <-> Packs submission. Called before every write that
    // uses 'conversion' so it can't assume the .sql file ever ran.
    private function ensureConversionRefType(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $col = $this->db->query("SHOW COLUMNS FROM stock_ledger LIKE 'ref_type'");
        $row = $col ? $col->fetch_assoc() : null;
        if ($row && strpos((string) $row['Type'], "'conversion'") === false) {
            $this->db->query(
                "ALTER TABLE stock_ledger MODIFY COLUMN ref_type ENUM(
                    'invoice','user_invoice','return','transfer','ot_sale',
                    'adjustment','demofree','tp_invoice','conversion'
                ) NOT NULL"
            );
        }
    }

    // Self-migrating — same reasoning as ensureConversionRefType(): adds
    // stock_ledger.machine_code_id (nullable, optional per-conversion
    // packing-machine tag) if it hasn't been added yet on this environment.
    private function ensureMachineCodeColumn(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $col = $this->db->query("SHOW COLUMNS FROM stock_ledger LIKE 'machine_code_id'");
        if ($col && $col->num_rows === 0) {
            $this->db->query(
                "ALTER TABLE stock_ledger ADD COLUMN machine_code_id INT NULL DEFAULT NULL AFTER ref_id"
            );
        }
    }

    // -------------------------------------------------------------------------
    // PUBLIC API
    // -------------------------------------------------------------------------

    /**
     * Deduct qty from a seller's stock.
     * Throws StockException if closing_qty would go negative.
     *
     * @param bool $externalTransaction  Set true when caller owns the transaction.
     */
    public function deduct(
        int    $productId,
        string $userType,
        string $userId,
        int    $qty,
        string $refType,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?int   $warehouseId = null
    ): array {
        if ($refType === 'conversion') $this->ensureConversionRefType();
        if (!$externalTransaction) {
            $this->db->begin_transaction();
        }

        try {
            $this->ensureNeksomoTopUp($productId, $userType, $userId, $qty, $createdBy);

            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);

            if ($row === null) {
                throw new StockException(
                    "No stock record found for product=$productId user_type=$userType user_id=$userId"
                );
            }

            $before = (int) $row['closing_qty'];
            $after  = $before - $qty;

            if ($after < 0) {
                throw new StockException(
                    "Insufficient stock for product=$productId. Available=$before, Requested=$qty"
                );
            }

            $this->updateStockSnapshot($productId, $userType, $userId, [
                'sales_qty'   => (int)$row['sales_qty'] + $qty,
                'closing_qty' => $after,
            ], $warehouseId);

            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'deduct', $qty, $before, $after,
                $refType, $refId, '', $createdBy, $warehouseId
            );

            $consumed = StockLots::consumeFifo(
                $this->db, $productId, $userType, $userId, $qty,
                fn() => $this->fallbackRate($productId),
                $warehouseId
            );
            StockLots::writeConsumption($this->db, $ledgerId, $consumed);

            if (!$externalTransaction) {
                $this->db->commit();
            }

            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];

        } catch (\Throwable $e) {
            if (!$externalTransaction) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    /**
     * Credit qty to a buyer's stock (INSERT if no row exists, else UPDATE).
     */
    public function credit(
        int    $productId,
        string $userType,
        string $userId,
        int    $qty,
        string $refType,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?int   $warehouseId = null,
        ?int   $machineCodeId = null,
        ?string $conversionDate = null
    ): array {
        if ($refType === 'conversion') $this->ensureConversionRefType();
        if (!$externalTransaction) {
            $this->db->begin_transaction();
        }

        try {
            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);

            if ($row === null) {
                // Create a new stock row for this buyer
                $stmt = $this->db->prepare(
                    "INSERT INTO stock
                        (product_id, opening_qty, opening_date, input_qty, sales_qty,
                         sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id, updated_at)
                     VALUES (?, 0, CURDATE(), ?, 0, 0, 0, ?, ?, ?, ?, NOW())"
                );
                $stmt->bind_param('iiissi', $productId, $qty, $qty, $userType, $userId, $warehouseId);
                $stmt->execute();
                $stmt->close();

                $before = 0;
                $after  = $qty;
            } else {
                $before = (int) $row['closing_qty'];
                $after  = $before + $qty;

                $this->updateStockSnapshot($productId, $userType, $userId, [
                    'input_qty'   => (int)$row['input_qty'] + $qty,
                    'closing_qty' => $after,
                ], $warehouseId);
            }

            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'credit', $qty, $before, $after,
                $refType, $refId, '', $createdBy, $warehouseId, $machineCodeId, $conversionDate
            );

            if (!$externalTransaction) {
                $this->db->commit();
            }

            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];

        } catch (\Throwable $e) {
            if (!$externalTransaction) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    /**
     * Reverse a previous deduction (restore stock to seller).
     * Floors sales_qty at 0 — never goes negative.
     */
    public function reverseDeduct(
        int    $productId,
        string $userType,
        string $userId,
        int    $qty,
        string $refType,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?int   $warehouseId = null
    ): array {
        if (!$externalTransaction) {
            $this->db->begin_transaction();
        }

        try {
            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);

            if ($row === null) {
                if (!$externalTransaction) $this->db->rollback();
                return ['success' => false, 'reason' => 'no_stock_row'];
            }

            $before      = (int) $row['closing_qty'];
            $after        = $before + $qty;
            $newSalesQty  = max(0, (int)$row['sales_qty'] - $qty);

            $this->updateStockSnapshot($productId, $userType, $userId, [
                'sales_qty'   => $newSalesQty,
                'closing_qty' => $after,
            ], $warehouseId);

            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'reverse_deduct', $qty, $before, $after,
                $refType, $refId, '', $createdBy, $warehouseId
            );

            // Restock the exact lot(s) the original deduct drew from, found
            // via the original ledger row for this same (refType, refId, product).
            $origStmt = $this->db->prepare(
                "SELECT id FROM stock_ledger
                 WHERE ref_type = ? AND ref_id = ? AND product_id = ? AND action = 'deduct'
                 ORDER BY id DESC LIMIT 1"
            );
            $origStmt->bind_param('ssi', $refType, $refId, $productId);
            $origStmt->execute();
            $origLedgerId = $origStmt->get_result()->fetch_assoc()['id'] ?? null;
            $origStmt->close();
            if ($origLedgerId !== null) {
                StockLots::restoreConsumption($this->db, (int) $origLedgerId);
            }

            if (!$externalTransaction) {
                $this->db->commit();
            }

            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];

        } catch (\Throwable $e) {
            if (!$externalTransaction) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    /**
     * Reverse a previous credit (remove stock from buyer).
     * Floors input_qty and closing_qty at 0.
     */
    public function reverseCredit(
        int    $productId,
        string $userType,
        string $userId,
        int    $qty,
        string $refType,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?int   $warehouseId = null
    ): array {
        if (!$externalTransaction) {
            $this->db->begin_transaction();
        }

        try {
            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);

            if ($row === null) {
                if (!$externalTransaction) $this->db->rollback();
                return ['success' => false, 'reason' => 'no_stock_row'];
            }

            $before         = (int) $row['closing_qty'];
            $after           = max(0, $before - $qty);
            $newInputQty     = max(0, (int)$row['input_qty'] - $qty);

            $this->updateStockSnapshot($productId, $userType, $userId, [
                'input_qty'   => $newInputQty,
                'closing_qty' => $after,
            ], $warehouseId);

            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'reverse_credit', $qty, $before, $after,
                $refType, $refId, '', $createdBy, $warehouseId
            );

            if (!$externalTransaction) {
                $this->db->commit();
            }

            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];

        } catch (\Throwable $e) {
            if (!$externalTransaction) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    /**
     * Atomic seller-deduct + buyer-credit in a single transaction.
     * Used by B2B invoice submit.
     */
    public function deductAndCredit(
        int    $productId,
        string $sellerType,
        string $sellerId,
        string $buyerType,
        string $buyerId,
        int    $qty,
        string $refType,
        string $refId,
        string $createdBy,
        ?int   $sellerWarehouseId = null,
        ?int   $buyerWarehouseId = null
    ): array {
        $this->db->begin_transaction();

        try {
            $deductResult = $this->deduct(
                $productId, $sellerType, $sellerId, $qty,
                $refType, $refId, $createdBy, true, $sellerWarehouseId
            );

            $creditResult = ['success' => true, 'ledger_id' => null];

            if (in_array($buyerType, self::STOCK_MAINTAINING_TYPES, true)) {
                $creditResult = $this->credit(
                    $productId, $buyerType, $buyerId, $qty,
                    $refType, $refId, $createdBy, true, $buyerWarehouseId
                );
            }

            $this->db->commit();

            return [
                'success' => true,
                'deduct'  => $deductResult,
                'credit'  => $creditResult,
            ];

        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Reverse ALL ledger entries for a given reference (invoice delete / edit reset).
     *
     * Idempotent and quantity-aware: aggregates applied vs already-reversed qty
     * per (product_id, user_type, user_id) and only reverses the net remainder.
     *
     * This correctly handles the case where the same product appears in multiple
     * line items and some of those items were individually deleted before the
     * invoice itself was deleted — a presence-only check would skip the remaining
     * applied qty for that product, leaving stock permanently over-counted.
     *
     * @return int Number of reversal calls actually made.
     */
    public function reverseAll(
        string $refType,
        string $refId,
        string $createdBy
    ): int {
        // One query: sum qty per (product, user, warehouse, action) for all four action types.
        $stmt = $this->db->prepare(
            "SELECT product_id, user_type, user_id, warehouse_id, action, SUM(qty) AS qty
               FROM stock_ledger
              WHERE ref_type = ? AND ref_id = ?
                AND action IN ('deduct','credit','reverse_deduct','reverse_credit')
              GROUP BY product_id, user_type, user_id, warehouse_id, action"
        );
        $stmt->bind_param('ss', $refType, $refId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($rows)) {
            return 0;
        }

        // Index totals by party+warehouse key → action totals.
        $totals = [];
        foreach ($rows as $row) {
            $whKey = $row['warehouse_id'] === null ? 'null' : $row['warehouse_id'];
            $key = $row['product_id'] . '|' . $row['user_type'] . '|' . $row['user_id'] . '|' . $whKey;
            $totals[$key]['product_id']   = (int)    $row['product_id'];
            $totals[$key]['user_type']    = (string)  $row['user_type'];
            $totals[$key]['user_id']      = (string)  $row['user_id'];
            $totals[$key]['warehouse_id'] = $row['warehouse_id'] === null ? null : (int) $row['warehouse_id'];
            $totals[$key][$row['action']] = (int)     $row['qty'];
        }

        $this->db->begin_transaction();

        try {
            $count = 0;
            foreach ($totals as $data) {
                $productId   = $data['product_id'];
                $userType    = $data['user_type'];
                $userId      = $data['user_id'];
                $warehouseId = $data['warehouse_id'];

                // Net seller deductions still applied
                $netDeduct = ($data['deduct'] ?? 0) - ($data['reverse_deduct'] ?? 0);
                if ($netDeduct > 0) {
                    $this->reverseDeduct(
                        $productId, $userType, $userId, $netDeduct,
                        $refType, $refId, $createdBy, true, $warehouseId
                    );
                    $count++;
                }

                // Net buyer credits still applied
                $netCredit = ($data['credit'] ?? 0) - ($data['reverse_credit'] ?? 0);
                if ($netCredit > 0) {
                    $this->reverseCredit(
                        $productId, $userType, $userId, $netCredit,
                        $refType, $refId, $createdBy, true, $warehouseId
                    );
                    $count++;
                }
            }

            $this->db->commit();
            return $count;

        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Check whether stock is CURRENTLY applied (net) for a reference.
     *
     * Returns true only when applied entries outnumber reversals — meaning
     * stock has been moved and has NOT been fully reversed yet.
     *
     * Why net, not just existence:
     *   When a receipt is deleted before an invoice edit, reverseAll() writes
     *   reverse_deduct/reverse_credit entries. If we only checked for the
     *   existence of 'deduct'/'credit' rows (old approach), the guard would
     *   fire even after the stock was fully reversed, preventing re-application
     *   on the subsequent edit re-submission.
     *
     * Net logic:
     *   applied  = count of 'deduct' + 'credit' entries
     *   reversed = count of 'reverse_deduct' + 'reverse_credit' entries
     *   currently applied = applied > reversed
     */
    public function hasLedgerEntry(string $refType, string $refId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT
                SUM(action IN ('deduct','credit'))                        AS applied,
                SUM(action IN ('reverse_deduct','reverse_credit'))        AS reversed
             FROM stock_ledger
             WHERE ref_type = ? AND ref_id = ?"
        );
        $stmt->bind_param('ss', $refType, $refId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $applied  = (int)($row['applied']  ?? 0);
        $reversed = (int)($row['reversed'] ?? 0);

        return $applied > $reversed;
    }

    /**
     * Return the current closing_qty for a stock entity (no lock).
     *
     * Always the godown's own real stock.closing_qty — pack-based, same as
     * every other product. Neksomo purchases now proactively convert their
     * pool into real stock right after purchase (see
     * neksomo-manufacturer-purchase-action.php), so this no longer needs to
     * add in unconverted pool availability; ensureNeksomoTopUp() remains as
     * the deduction-time safety net for any pool stock a purchase-time
     * conversion missed.
     */
    public function getClosingQty(int $productId, string $userType, string $userId, ?int $warehouseId = null): ?int
    {
        $sql = "SELECT closing_qty FROM stock
                  WHERE product_id = ? AND user_type = ? AND user_id = ?
                    AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?');
        $stmt = $this->db->prepare($sql);
        if ($warehouseId === null) {
            $stmt->bind_param('iss', $productId, $userType, $userId);
        } else {
            $stmt->bind_param('issi', $productId, $userType, $userId, $warehouseId);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['closing_qty'] : null;
    }

    /**
     * Accept a return: credit stock back to the receiver (seller/company).
     * Increments input_qty + closing_qty, writes 'return_accept' ledger entry.
     */
    public function acceptReturn(
        int    $productId,
        string $userType,
        string $userId,
        int    $qty,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?int   $warehouseId = null
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);
            if ($row === null) {
                if (!$externalTransaction) $this->db->rollback();
                return ['success' => false, 'reason' => 'no_stock_row'];
            }
            $before = (int)$row['closing_qty'];
            $after  = $before + $qty;
            $this->updateStockSnapshot($productId, $userType, $userId, [
                'input_qty'   => (int)$row['input_qty'] + $qty,
                'closing_qty' => $after,
            ], $warehouseId);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'return_accept', $qty, $before, $after,
                'return', $refId, '', $createdBy, $warehouseId
            );
            if (!$externalTransaction) $this->db->commit();
            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];
        } catch (\Throwable $e) {
            if (!$externalTransaction) $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Reject a return: restore stock to the sender (buyer) by decrementing
     * returnqty and incrementing closing_qty. Writes 'return_reject' ledger entry.
     */
    public function rejectReturn(
        int    $productId,
        string $userType,
        string $userId,
        int    $qty,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?int   $warehouseId = null
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);
            if ($row === null) {
                if (!$externalTransaction) $this->db->rollback();
                return ['success' => false, 'reason' => 'no_stock_row'];
            }
            $before         = (int)$row['closing_qty'];
            $after           = $before + $qty;
            $newReturnQty    = max(0, (int)$row['returnqty'] - $qty);
            $this->updateStockSnapshot($productId, $userType, $userId, [
                'returnqty'   => $newReturnQty,
                'closing_qty' => $after,
            ], $warehouseId);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'return_reject', $qty, $before, $after,
                'return', $refId, '', $createdBy, $warehouseId
            );
            if (!$externalTransaction) $this->db->commit();
            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];
        } catch (\Throwable $e) {
            if (!$externalTransaction) $this->db->rollback();
            throw $e;
        }
    }

    /**
     * OT (Other Territory) sale deduction.
     * Decrements closing_qty + increments sales_qty, writes 'ot_deduct' ledger entry.
     */
    public function otDeduct(
        int    $productId,
        string $userType,
        string $userId,
        int    $qty,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?int   $warehouseId = null
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $this->ensureNeksomoTopUp($productId, $userType, $userId, $qty, $createdBy);

            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);
            if ($row === null) {
                if (!$externalTransaction) $this->db->rollback();
                throw new StockException("No stock row for product=$productId type=$userType id=$userId");
            }
            $before = (int)$row['closing_qty'];
            $after  = $before - $qty;
            if ($after < 0) {
                throw new StockException(
                    "Insufficient OT stock for product=$productId. Available=$before, Requested=$qty"
                );
            }
            $this->updateStockSnapshot($productId, $userType, $userId, [
                'sales_qty'   => (int)$row['sales_qty'] + $qty,
                'closing_qty' => $after,
            ], $warehouseId);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'ot_deduct', $qty, $before, $after,
                'ot_sale', $refId, '', $createdBy, $warehouseId
            );
            if (!$externalTransaction) $this->db->commit();
            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];
        } catch (\Throwable $e) {
            if (!$externalTransaction) $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Reverse an OT sale deduction (item delete or return).
     * Restores closing_qty, decrements sales_qty, writes 'ot_reverse' ledger entry.
     */
    public function otReverse(
        int    $productId,
        string $userType,
        string $userId,
        int    $qty,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?int   $warehouseId = null
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);
            if ($row === null) {
                if (!$externalTransaction) $this->db->rollback();
                return ['success' => false, 'reason' => 'no_stock_row'];
            }
            $before = (int)$row['closing_qty'];
            $after  = $before + $qty;
            $this->updateStockSnapshot($productId, $userType, $userId, [
                'sales_qty'   => max(0, (int)$row['sales_qty'] - $qty),
                'closing_qty' => $after,
            ], $warehouseId);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'ot_reverse', $qty, $before, $after,
                'ot_sale', $refId, '', $createdBy, $warehouseId
            );
            if (!$externalTransaction) $this->db->commit();
            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];
        } catch (\Throwable $e) {
            if (!$externalTransaction) $this->db->rollback();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // INTERNAL TRANSFER  (godown-to-godown, company-internal)
    // -------------------------------------------------------------------------

    /**
     * Deduct stock for a godown-to-godown transfer (outbound leg).
     * Increments sent_qty, decrements closing_qty.
     * Writes 'transfer_out' ledger entry.
     */
    public function transferOut(
        int    $productId,
        string $userType,
        string $userId,
        int    $qty,
        string $refType,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?int   $warehouseId = null
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $this->ensureNeksomoTopUp($productId, $userType, $userId, $qty, $createdBy);

            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);
            if ($row === null) {
                throw new StockException(
                    "No stock record for product=$productId user_type=$userType user_id=$userId"
                );
            }
            $before = (int) $row['closing_qty'];
            $after  = $before - $qty;
            if ($after < 0) {
                throw new StockException(
                    "Insufficient stock for transfer: product=$productId. Available=$before, Requested=$qty"
                );
            }
            $this->updateStockSnapshot($productId, $userType, $userId, [
                'sent_qty'    => (int) $row['sent_qty'] + $qty,
                'closing_qty' => $after,
            ], $warehouseId);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'transfer_out', $qty, $before, $after,
                $refType, $refId, '', $createdBy, $warehouseId
            );

            $consumed = StockLots::consumeFifo(
                $this->db, $productId, $userType, $userId, $qty,
                fn() => $this->fallbackRate($productId),
                $warehouseId
            );
            StockLots::writeConsumption($this->db, $ledgerId, $consumed);

            $totalTaken = array_sum(array_column($consumed, 'qty_taken'));
            $weightedRate = $totalTaken > 0
                ? array_sum(array_map(fn($c) => $c['qty_taken'] * $c['rate'], $consumed)) / $totalTaken
                : 0.0;

            if (!$externalTransaction) $this->db->commit();
            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after, 'consumed_rate' => $weightedRate];
        } catch (\Throwable $e) {
            if (!$externalTransaction) $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Credit stock for a godown-to-godown transfer (inbound leg).
     * Increments input_qty and closing_qty. Creates stock row if absent.
     * Writes 'transfer_in' ledger entry.
     */
    public function transferIn(
        int    $productId,
        string $userType,
        string $userId,
        int    $qty,
        string $refType,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?float $lotRate = null,   // weighted-avg cost carried from the source transferOut; null = skip lot creation
        ?int   $warehouseId = null
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);
            if ($row === null) {
                $stmt = $this->db->prepare(
                    "INSERT INTO stock
                        (product_id, opening_qty, opening_date, input_qty, sales_qty,
                         sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id, updated_at)
                     VALUES (?, 0, CURDATE(), ?, 0, 0, 0, ?, ?, ?, ?, NOW())"
                );
                $stmt->bind_param('iiissi', $productId, $qty, $qty, $userType, $userId, $warehouseId);
                $stmt->execute();
                $stmt->close();
                $before = 0;
                $after  = $qty;
            } else {
                $before = (int) $row['closing_qty'];
                $after  = $before + $qty;
                $this->updateStockSnapshot($productId, $userType, $userId, [
                    'input_qty'   => (int) $row['input_qty'] + $qty,
                    'closing_qty' => $after,
                ], $warehouseId);
            }
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'transfer_in', $qty, $before, $after,
                $refType, $refId, '', $createdBy, $warehouseId
            );

            if ($lotRate !== null) {
                StockLots::recordLot(
                    $this->db, $productId, $userType, $userId, $lotRate, $qty,
                    date('Y-m-d'), 'transfer_in', $refId, $createdBy,
                    $warehouseId
                );
            }

            if (!$externalTransaction) $this->db->commit();
            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];
        } catch (\Throwable $e) {
            if (!$externalTransaction) $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Reverse a transferOut (godown item delete or transfer cancellation).
     * Decrements sent_qty, restores closing_qty.
     * Writes 'transfer_out_reverse' ledger entry.
     */
    public function reverseTransferOut(
        int    $productId,
        string $userType,
        string $userId,
        int    $qty,
        string $refType,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?int   $warehouseId = null
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);
            if ($row === null) {
                if (!$externalTransaction) $this->db->rollback();
                return ['success' => false, 'reason' => 'no_stock_row'];
            }
            $before     = (int) $row['closing_qty'];
            $after      = $before + $qty;
            $newSentQty = max(0, (int) $row['sent_qty'] - $qty);
            $this->updateStockSnapshot($productId, $userType, $userId, [
                'sent_qty'    => $newSentQty,
                'closing_qty' => $after,
            ], $warehouseId);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'transfer_out_reverse', $qty, $before, $after,
                $refType, $refId, '', $createdBy, $warehouseId
            );

            // Restock the exact lot(s) the original transferOut drew from.
            $origStmt = $this->db->prepare(
                "SELECT id FROM stock_ledger
                 WHERE ref_type = ? AND ref_id = ? AND product_id = ? AND action = 'transfer_out'
                 ORDER BY id DESC LIMIT 1"
            );
            $origStmt->bind_param('ssi', $refType, $refId, $productId);
            $origStmt->execute();
            $origLedgerId = $origStmt->get_result()->fetch_assoc()['id'] ?? null;
            $origStmt->close();
            if ($origLedgerId !== null) {
                StockLots::restoreConsumption($this->db, (int) $origLedgerId);
            }

            if (!$externalTransaction) $this->db->commit();
            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];
        } catch (\Throwable $e) {
            if (!$externalTransaction) $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Reverse a transferIn (godown item delete or transfer cancellation).
     * Decrements input_qty and closing_qty (floors at 0).
     * Writes 'transfer_in_reverse' ledger entry.
     */
    public function reverseTransferIn(
        int    $productId,
        string $userType,
        string $userId,
        int    $qty,
        string $refType,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?int   $warehouseId = null
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);
            if ($row === null) {
                if (!$externalTransaction) $this->db->rollback();
                return ['success' => false, 'reason' => 'no_stock_row'];
            }
            $before = (int) $row['closing_qty'];

            // The transferred-in qty may have already been partly sold/moved
            // out since the transfer happened. Reversing blindly would
            // silently floor closing_qty at 0 and destroy that legitimate
            // stock movement (see STOCK_AUDIT_2026.md). Refuse instead —
            // the caller (internal_transfer_delete.php) must surface this
            // so the transfer can be reconciled manually before deleting.
            if ($before < $qty) {
                if (!$externalTransaction) $this->db->rollback();
                return [
                    'success'   => false,
                    'reason'    => 'insufficient_stock_to_reverse',
                    'available' => $before,
                    'requested' => $qty,
                ];
            }

            $after       = $before - $qty;
            $newInputQty = max(0, (int) $row['input_qty'] - $qty);
            $this->updateStockSnapshot($productId, $userType, $userId, [
                'input_qty'   => $newInputQty,
                'closing_qty' => $after,
            ], $warehouseId);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'transfer_in_reverse', $qty, $before, $after,
                $refType, $refId, '', $createdBy, $warehouseId
            );
            if (!$externalTransaction) $this->db->commit();
            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];
        } catch (\Throwable $e) {
            if (!$externalTransaction) $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Assemble packCount whole packs out of loose pieces (Pieces -> Pack).
     * Decrements extra_pieces by (piecesPerPack * packCount), increments
     * closing_qty by packCount. Throws StockException if extra_pieces is
     * insufficient, or if no stock row exists for this key.
     * Writes a single 'pieces_to_pack' ledger entry per call — qty/
     * qty_before/qty_after track closing_qty (the pack-based figure),
     * matching every other ledger entry's convention.
     */
    public function convertPiecesToPack(
        int    $productId,
        string $userType,
        string $userId,
        int    $piecesPerPack,
        int    $packCount,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?int   $warehouseId = null,
        ?int   $machineCodeId = null,
        ?string $conversionDate = null
    ): array {
        $this->ensureConversionRefType();
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);
            if ($row === null) {
                throw new StockException(
                    "No stock record for product=$productId user_type=$userType user_id=$userId"
                );
            }
            $piecesNeeded = $piecesPerPack * $packCount;
            $currentPieces = (int) $row['extra_pieces'];
            if ($currentPieces < $piecesNeeded) {
                throw new StockException(
                    "Insufficient pieces to assemble $packCount pack(s) of product=$productId. Available=$currentPieces, Needed=$piecesNeeded"
                );
            }

            $before = (int) $row['closing_qty'];
            $after  = $before + $packCount;
            $this->updateStockSnapshot($productId, $userType, $userId, [
                'closing_qty'  => $after,
                'extra_pieces' => $currentPieces - $piecesNeeded,
            ], $warehouseId);

            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'pieces_to_pack', $packCount, $before, $after,
                'conversion', $refId, '', $createdBy, $warehouseId, $machineCodeId, $conversionDate
            );

            if (!$externalTransaction) $this->db->commit();
            return [
                'success' => true,
                'ledger_id' => $ledgerId,
                'closing_qty_after' => $after,
                'extra_pieces_after' => $currentPieces - $piecesNeeded,
            ];
        } catch (\Throwable $e) {
            if (!$externalTransaction) $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Break packCount whole packs into loose pieces (Pack -> Pieces).
     * Decrements closing_qty by packCount, increments extra_pieces by
     * (piecesPerPack * packCount). Throws StockException if closing_qty
     * is insufficient, or if no stock row exists for this key.
     * Writes a single 'pack_to_pieces' ledger entry per call.
     */
    public function convertPackToPieces(
        int    $productId,
        string $userType,
        string $userId,
        int    $piecesPerPack,
        int    $packCount,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?int   $warehouseId = null,
        ?int   $machineCodeId = null,
        ?string $conversionDate = null
    ): array {
        $this->ensureConversionRefType();
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId, $warehouseId);
            if ($row === null) {
                throw new StockException(
                    "No stock record for product=$productId user_type=$userType user_id=$userId"
                );
            }
            $before = (int) $row['closing_qty'];
            if ($before < $packCount) {
                throw new StockException(
                    "Insufficient packs to break open for product=$productId. Available=$before, Requested=$packCount"
                );
            }
            $after = $before - $packCount;
            $piecesGained = $piecesPerPack * $packCount;
            $newPieces = (int) $row['extra_pieces'] + $piecesGained;

            $this->updateStockSnapshot($productId, $userType, $userId, [
                'closing_qty'  => $after,
                'extra_pieces' => $newPieces,
            ], $warehouseId);

            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'pack_to_pieces', $packCount, $before, $after,
                'conversion', $refId, '', $createdBy, $warehouseId, $machineCodeId, $conversionDate
            );

            if (!$externalTransaction) $this->db->commit();
            return [
                'success' => true,
                'ledger_id' => $ledgerId,
                'closing_qty_after' => $after,
                'extra_pieces_after' => $newPieces,
            ];
        } catch (\Throwable $e) {
            if (!$externalTransaction) $this->db->rollback();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    /**
     * Draws exactly the shortfall (never more) out of a Neksomo product's
     * shared pool to top up $productId's real stock row, right before a
     * deduction needs it — never as a side effect of a mere read. No-op for
     * anything not mapped / not at the Neksomo godown / already sufficient.
     * Must be called from inside an already-open transaction.
     */
    private function ensureNeksomoTopUp(
        int    $productId,
        string $userType,
        string $userId,
        int    $qtyNeeded,
        string $createdBy
    ): void {
        if ($userType !== 'company') return;
        if ((int)$userId !== get_neksomo_godown_id($this->db)) return;

        $row  = $this->lockStockRow($productId, $userType, $userId);
        $real = $row ? (int)$row['closing_qty'] : 0;
        if ($real >= $qtyNeeded) return;

        $shortfall = $qtyNeeded - $real;
        $poolAvailable = get_neksomo_pool_purchased_minus_sold_packs($this->db, $productId);
        $toConvert = min($shortfall, $poolAvailable);
        if ($toConvert <= 0) return;

        // stock_ledger.ref_type is a closed ENUM without a Neksomo-specific
        // member — 'adjustment' is the established convention for this kind
        // of system-generated credit (same as neksomo-manufacturer-purchase-
        // action.php's own purchase credits); the distinguishing detail goes
        // in ref_id instead.
        $this->credit($productId, $userType, $userId, $toConvert, 'adjustment', 'neksomo_conversion_' . uniqid(), $createdBy, true);
        record_neksomo_stock_conversion($this->db, $productId, $toConvert, $createdBy);
    }

    /**
     * Lock the stock row for this entity using SELECT … FOR UPDATE.
     * Must be called inside an active transaction.
     *
     * $warehouseId null means "unassigned" — matches every pre-Phase-1 row,
     * since NULL is its own distinct identity in uq_stock_entity_warehouse.
     */
    private function lockStockRow(int $productId, string $userType, string $userId, ?int $warehouseId = null): ?array
    {
        $sql = "SELECT * FROM stock
                  WHERE product_id = ? AND user_type = ? AND user_id = ?
                    AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?') . "
                  FOR UPDATE";
        $stmt = $this->db->prepare($sql);
        if ($warehouseId === null) {
            $stmt->bind_param('iss', $productId, $userType, $userId);
        } else {
            $stmt->bind_param('issi', $productId, $userType, $userId, $warehouseId);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Write changed columns back to the stock snapshot row.
     * Only updates the columns provided in $fields.
     */
    private function updateStockSnapshot(
        int    $productId,
        string $userType,
        string $userId,
        array  $fields,
        ?int   $warehouseId = null
    ): void {
        $setParts = [];
        $types    = '';
        $values   = [];

        foreach ($fields as $col => $val) {
            $setParts[] = "`$col` = ?";
            $types      .= 'i';
            $values[]   = $val;
        }

        $setParts[] = '`updated_at` = NOW()';
        $sql  = 'UPDATE stock SET ' . implode(', ', $setParts)
              . ' WHERE product_id = ? AND user_type = ? AND user_id = ?'
              . ' AND warehouse_id ' . ($warehouseId === null ? 'IS NULL' : '= ?');
        $types .= 'iss';
        $values[] = $productId;
        $values[] = $userType;
        $values[] = $userId;
        if ($warehouseId !== null) {
            $types .= 'i';
            $values[] = $warehouseId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Insert one immutable row into stock_ledger.
     * Returns the new ledger id.
     */
    private function writeLedger(
        int    $productId,
        string $userType,
        string $userId,
        string $action,
        int    $qty,
        int    $qtyBefore,
        int    $qtyAfter,
        string $refType,
        string $refId,
        string $note,
        string $createdBy,
        ?int   $warehouseId = null,
        ?int   $machineCodeId = null,
        ?string $conversionDate = null
    ): int {
        $this->ensureMachineCodeColumn();

        // conversionDate (Y-m-d) lets a conversion be backdated instead of
        // always landing at NOW() — only Convert Pieces<->Packs passes this;
        // every other caller leaves it null and created_at keeps its normal
        // DEFAULT CURRENT_TIMESTAMP behavior.
        if ($conversionDate !== null) {
            $stmt = $this->db->prepare(
                "INSERT INTO stock_ledger
                    (product_id, user_type, user_id, warehouse_id, action, qty,
                     qty_before, qty_after, ref_type, ref_id, machine_code_id, note, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'issisiiississs',
                $productId, $userType, $userId, $warehouseId, $action, $qty,
                $qtyBefore, $qtyAfter, $refType, $refId, $machineCodeId, $note, $createdBy, $conversionDate
            );
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO stock_ledger
                    (product_id, user_type, user_id, warehouse_id, action, qty,
                     qty_before, qty_after, ref_type, ref_id, machine_code_id, note, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'issisiiississ',
                $productId, $userType, $userId, $warehouseId, $action, $qty,
                $qtyBefore, $qtyAfter, $refType, $refId, $machineCodeId, $note, $createdBy
            );
        }
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Today's pre-FIFO cost lookup ("latest effective_date <= now"), used
     * only when stock_lots has no remaining qty to cover a deduct — keeps
     * every sale costed even for products/periods with no lot data yet
     * (pre-migration stock, or sale sources that never call StockService,
     * e.g. TP invoices).
     */
    private function fallbackRate(int $productId): float
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(
                (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece END
                     * COALESCE(NULLIF(p.pieces_per_pack,0),1)
                 FROM neksomo_llp_piece_rates r
                 JOIN products p ON p.id = ?
                 WHERE r.product_id = ? AND r.effective_date <= CURDATE()
                 ORDER BY r.effective_date DESC LIMIT 1),
                (SELECT CASE WHEN fr.gst_type = 'inclusive' THEN fr.rate_per_piece / (1 + fr.gst_rate/100) ELSE fr.rate_per_piece END
                     * COALESCE(NULLIF(p.pieces_per_pack,0),1)
                 FROM femi9_llp_sale_rates fr
                 JOIN products p ON p.id = ?
                 WHERE fr.product_id = ? AND fr.effective_date <= CURDATE()
                 ORDER BY fr.effective_date DESC LIMIT 1),
                0
            ) AS rate"
        );
        $stmt->bind_param('iiii', $productId, $productId, $productId, $productId);
        $stmt->execute();
        $rate = (float) ($stmt->get_result()->fetch_assoc()['rate'] ?? 0.0);
        $stmt->close();
        return $rate;
    }
}

/**
 * Thrown when a stock operation cannot be completed (e.g. insufficient stock).
 */
class StockException extends \RuntimeException {}
