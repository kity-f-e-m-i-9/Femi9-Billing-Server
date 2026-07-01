<?php
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
        bool   $externalTransaction = false
    ): array {
        if (!$externalTransaction) {
            $this->db->begin_transaction();
        }

        try {
            $row = $this->lockStockRow($productId, $userType, $userId);

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
            ]);

            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'deduct', $qty, $before, $after,
                $refType, $refId, '', $createdBy
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
        bool   $externalTransaction = false
    ): array {
        if (!$externalTransaction) {
            $this->db->begin_transaction();
        }

        try {
            $row = $this->lockStockRow($productId, $userType, $userId);

            if ($row === null) {
                // Create a new stock row for this buyer
                $stmt = $this->db->prepare(
                    "INSERT INTO stock
                        (product_id, opening_qty, opening_date, input_qty, sales_qty,
                         sent_qty, returnqty, closing_qty, user_type, user_id, updated_at)
                     VALUES (?, 0, CURDATE(), ?, 0, 0, 0, ?, ?, ?, NOW())"
                );
                $stmt->bind_param('iiiss', $productId, $qty, $qty, $userType, $userId);
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
                ]);
            }

            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'credit', $qty, $before, $after,
                $refType, $refId, '', $createdBy
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
        bool   $externalTransaction = false
    ): array {
        if (!$externalTransaction) {
            $this->db->begin_transaction();
        }

        try {
            $row = $this->lockStockRow($productId, $userType, $userId);

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
            ]);

            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'reverse_deduct', $qty, $before, $after,
                $refType, $refId, '', $createdBy
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
        bool   $externalTransaction = false
    ): array {
        if (!$externalTransaction) {
            $this->db->begin_transaction();
        }

        try {
            $row = $this->lockStockRow($productId, $userType, $userId);

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
            ]);

            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'reverse_credit', $qty, $before, $after,
                $refType, $refId, '', $createdBy
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
        string $createdBy
    ): array {
        $this->db->begin_transaction();

        try {
            $deductResult = $this->deduct(
                $productId, $sellerType, $sellerId, $qty,
                $refType, $refId, $createdBy, true
            );

            $creditResult = ['success' => true, 'ledger_id' => null];

            if (in_array($buyerType, self::STOCK_MAINTAINING_TYPES, true)) {
                $creditResult = $this->credit(
                    $productId, $buyerType, $buyerId, $qty,
                    $refType, $refId, $createdBy, true
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
        // One query: sum qty per (product, user, action) for all four action types.
        $stmt = $this->db->prepare(
            "SELECT product_id, user_type, user_id, action, SUM(qty) AS qty
               FROM stock_ledger
              WHERE ref_type = ? AND ref_id = ?
                AND action IN ('deduct','credit','reverse_deduct','reverse_credit')
              GROUP BY product_id, user_type, user_id, action"
        );
        $stmt->bind_param('ss', $refType, $refId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($rows)) {
            return 0;
        }

        // Index totals by party key → action totals.
        $totals = [];
        foreach ($rows as $row) {
            $key = $row['product_id'] . '|' . $row['user_type'] . '|' . $row['user_id'];
            $totals[$key]['product_id']   = (int)    $row['product_id'];
            $totals[$key]['user_type']    = (string)  $row['user_type'];
            $totals[$key]['user_id']      = (string)  $row['user_id'];
            $totals[$key][$row['action']] = (int)     $row['qty'];
        }

        $this->db->begin_transaction();

        try {
            $count = 0;
            foreach ($totals as $data) {
                $productId = $data['product_id'];
                $userType  = $data['user_type'];
                $userId    = $data['user_id'];

                // Net seller deductions still applied
                $netDeduct = ($data['deduct'] ?? 0) - ($data['reverse_deduct'] ?? 0);
                if ($netDeduct > 0) {
                    $this->reverseDeduct(
                        $productId, $userType, $userId, $netDeduct,
                        $refType, $refId, $createdBy, true
                    );
                    $count++;
                }

                // Net buyer credits still applied
                $netCredit = ($data['credit'] ?? 0) - ($data['reverse_credit'] ?? 0);
                if ($netCredit > 0) {
                    $this->reverseCredit(
                        $productId, $userType, $userId, $netCredit,
                        $refType, $refId, $createdBy, true
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
     */
    public function getClosingQty(int $productId, string $userType, string $userId): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT closing_qty FROM stock
              WHERE product_id = ? AND user_type = ? AND user_id = ?"
        );
        $stmt->bind_param('iss', $productId, $userType, $userId);
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
        bool   $externalTransaction = false
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId);
            if ($row === null) {
                if (!$externalTransaction) $this->db->rollback();
                return ['success' => false, 'reason' => 'no_stock_row'];
            }
            $before = (int)$row['closing_qty'];
            $after  = $before + $qty;
            $this->updateStockSnapshot($productId, $userType, $userId, [
                'input_qty'   => (int)$row['input_qty'] + $qty,
                'closing_qty' => $after,
            ]);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'return_accept', $qty, $before, $after,
                'return', $refId, '', $createdBy
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
        bool   $externalTransaction = false
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId);
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
            ]);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'return_reject', $qty, $before, $after,
                'return', $refId, '', $createdBy
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
        bool   $externalTransaction = false
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId);
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
            ]);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'ot_deduct', $qty, $before, $after,
                'ot_sale', $refId, '', $createdBy
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
        bool   $externalTransaction = false
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId);
            if ($row === null) {
                if (!$externalTransaction) $this->db->rollback();
                return ['success' => false, 'reason' => 'no_stock_row'];
            }
            $before = (int)$row['closing_qty'];
            $after  = $before + $qty;
            $this->updateStockSnapshot($productId, $userType, $userId, [
                'sales_qty'   => max(0, (int)$row['sales_qty'] - $qty),
                'closing_qty' => $after,
            ]);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'ot_reverse', $qty, $before, $after,
                'ot_sale', $refId, '', $createdBy
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
        bool   $externalTransaction = false
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId);
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
            ]);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'transfer_out', $qty, $before, $after,
                $refType, $refId, '', $createdBy
            );
            if (!$externalTransaction) $this->db->commit();
            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];
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
        bool   $externalTransaction = false
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId);
            if ($row === null) {
                $stmt = $this->db->prepare(
                    "INSERT INTO stock
                        (product_id, opening_qty, opening_date, input_qty, sales_qty,
                         sent_qty, returnqty, closing_qty, user_type, user_id, updated_at)
                     VALUES (?, 0, CURDATE(), ?, 0, 0, 0, ?, ?, ?, NOW())"
                );
                $stmt->bind_param('iiiss', $productId, $qty, $qty, $userType, $userId);
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
                ]);
            }
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'transfer_in', $qty, $before, $after,
                $refType, $refId, '', $createdBy
            );
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
        bool   $externalTransaction = false
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId);
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
            ]);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'transfer_out_reverse', $qty, $before, $after,
                $refType, $refId, '', $createdBy
            );
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
        bool   $externalTransaction = false
    ): array {
        if (!$externalTransaction) $this->db->begin_transaction();
        try {
            $row = $this->lockStockRow($productId, $userType, $userId);
            if ($row === null) {
                if (!$externalTransaction) $this->db->rollback();
                return ['success' => false, 'reason' => 'no_stock_row'];
            }
            $before      = (int) $row['closing_qty'];
            $after       = max(0, $before - $qty);
            $newInputQty = max(0, (int) $row['input_qty'] - $qty);
            $this->updateStockSnapshot($productId, $userType, $userId, [
                'input_qty'   => $newInputQty,
                'closing_qty' => $after,
            ]);
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'transfer_in_reverse', $qty, $before, $after,
                $refType, $refId, '', $createdBy
            );
            if (!$externalTransaction) $this->db->commit();
            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];
        } catch (\Throwable $e) {
            if (!$externalTransaction) $this->db->rollback();
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    /**
     * Lock the stock row for this entity using SELECT … FOR UPDATE.
     * Must be called inside an active transaction.
     */
    private function lockStockRow(int $productId, string $userType, string $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM stock
              WHERE product_id = ? AND user_type = ? AND user_id = ?
              FOR UPDATE"
        );
        $stmt->bind_param('iss', $productId, $userType, $userId);
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
        array  $fields
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
              . ' WHERE product_id = ? AND user_type = ? AND user_id = ?';
        $types .= 'iss';
        $values[] = $productId;
        $values[] = $userType;
        $values[] = $userId;

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
        string $createdBy
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO stock_ledger
                (product_id, user_type, user_id, action, qty,
                 qty_before, qty_after, ref_type, ref_id, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'isssiiissss',
            $productId, $userType, $userId, $action, $qty,
            $qtyBefore, $qtyAfter, $refType, $refId, $note, $createdBy
        );
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }
}

/**
 * Thrown when a stock operation cannot be completed (e.g. insufficient stock).
 */
class StockException extends \RuntimeException {}
