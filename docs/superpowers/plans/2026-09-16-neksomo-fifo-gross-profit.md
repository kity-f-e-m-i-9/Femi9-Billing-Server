# Neksomo FIFO Gross Profit (Phase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Neksomo Gross Profit cost each sold unit against the actual purchase-lot rate it came from (FIFO), instead of one blended "rate as of period-end" applied to the whole period's quantity.

**Architecture:** Add a `stock_lots` table (one row per stock-increasing event: rate, qty, remaining) and a `stock_ledger_lot_consumption` table (which lot(s) a `deduct` actually drew from, at what rate). Wire FIFO consumption into company's `StockService::deduct()`/`transferOut()` only; wire lot creation into the two LLP rate-entry forms, the Neksomo manufacturer purchase action, and `transferIn()`. Rewrite the Neksomo Gross Profit cost lookup in `mis-report.php` to sum real consumed-lot cost instead of the period-end rate subquery. Every new code path falls back to today's existing behavior when lot data is missing (pre-migration stock, TP invoices — which don't go through StockService at all) — nothing is allowed to block a sale or blow up a report for lack of lot data.

**Tech Stack:** PHP (mysqli, prepared statements), MySQL, existing `StockService` class pattern.

**Spec:** `docs/superpowers/specs/2026-09-16-neksomo-fifo-gross-profit-design.md`

## Global Constraints

- Company-side only. Do not touch the four duplicated `StockService.php` copies (distributor/stockist/super-stockist/super_distributor) or channel-partner/territory-partner.
- Never block a stock operation for missing lot data — always fall back to the existing "latest effective_date ≤ report date" rate lookup when `stock_lots` has nothing to consume.
- `stock_ledger_lot_consumption.rate` is copied at consumption time, never re-derived later — a later edit to a lot's rate must not silently rewrite historical COGS.
- FIFO order is `purchase_date ASC, id ASC`.
- Every new file follows the exact patterns already in the two files it's modeled on (`llp-purchase-rate.php`/`llp-purchase-rate-action.php`) — same CSRF check, same batch-array POST shape, same GST-snapshot-at-write convention.
- TP invoices (`tp_invoice_action.php`) do not call `StockService` at all — TP-sourced sales in the Gross Profit query will always use the fallback rate. This is a known, accepted gap for Phase 1, not a bug to chase down.

---

## File Structure

- **Create** `femi9/billing/db_migrations/2026_09_16_stock_lots.sql` — new tables.
- **Create** `femi9/billing/company/include/StockLots.php` — small static helper class: `recordLot()`, `consumeFifo()`, `restoreConsumption()`. Kept separate from `StockService.php` so `StockService` stays a thin orchestrator and the FIFO math is unit-testable in isolation.
- **Modify** `femi9/billing/company/include/StockService.php` — call `StockLots::consumeFifo()`/`recordLot()`/`restoreConsumption()` from `deduct()`, `transferOut()`, `transferIn()`, `reverseDeduct()`, `reverseTransferOut()`.
- **Modify** `femi9/billing/company/llp-purchase-rate.php` + `femi9/billing/company/llp-purchase-rate-action.php` — add Quantity Purchased field.
- **Modify** `femi9/billing/company/neksomo-llp-piece-sale.php` + `femi9/billing/company/neksomo-llp-piece-sale-action.php` — same, identical pattern.
- **Modify** `femi9/billing/company/neksomo-manufacturer-purchase-action.php` — record a lot per line after the existing credit loop.
- **Modify** `femi9/billing/company/mis-report.php` — replace `$gp_cost_rate_subq` usage in the main Gross Profit query (lines ~437–531) with a lot-consumption aggregation.

---

## Task 1: `stock_lots` and `stock_ledger_lot_consumption` tables

**Files:**
- Create: `femi9/billing/db_migrations/2026_09_16_stock_lots.sql`

**Interfaces:**
- Produces: `stock_lots(id, product_id, user_type, user_id, rate, qty_purchased, qty_remaining, purchase_date, ref_type, ref_id, created_by, created_at)`, `stock_ledger_lot_consumption(id, stock_ledger_id, stock_lot_id NULL, qty_taken, rate)`.

- [ ] **Step 1: Write the migration SQL**

```sql
-- FIFO lot tracking for company-side stock, so Gross Profit can cost each
-- sold unit against the purchase rate it actually came from instead of one
-- blended "rate as of period-end" applied to the whole period.
-- Applied: 2026-09-16

CREATE TABLE stock_lots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_type VARCHAR(32) NOT NULL,
  user_id VARCHAR(32) NOT NULL,
  rate DECIMAL(12,6) NOT NULL,
  qty_purchased INT NOT NULL,
  qty_remaining INT NOT NULL,
  purchase_date DATE NOT NULL,
  ref_type ENUM('llp_rate_entry','neksomo_purchase','transfer_in','opening_balance') NOT NULL,
  ref_id VARCHAR(64) NULL,
  created_by VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fifo_lookup (product_id, user_type, user_id, purchase_date, id),
  INDEX idx_qty_remaining (product_id, user_type, user_id, qty_remaining)
);

-- Which lot(s) a single stock_ledger deduct row actually drew from. A
-- stock_lot_id of NULL means the fallback rate-lookup was used because no
-- lot covered that portion of the sale (e.g. pre-migration stock, or a sale
-- source like TP invoices that never calls StockService at all).
CREATE TABLE stock_ledger_lot_consumption (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stock_ledger_id INT NOT NULL,
  stock_lot_id INT NULL,
  qty_taken INT NOT NULL,
  rate DECIMAL(12,6) NOT NULL,
  INDEX idx_ledger (stock_ledger_id),
  INDEX idx_lot (stock_lot_id)
);
```

- [ ] **Step 2: Apply the migration to the local dev database**

Run: `mysql -u <user> -p <database> < "femi9/billing/db_migrations/2026_09_16_stock_lots.sql"`
Expected: both `CREATE TABLE` statements succeed with no error.

- [ ] **Step 3: Verify the tables exist with the right columns**

Run: `mysql -u <user> -p <database> -e "DESCRIBE stock_lots; DESCRIBE stock_ledger_lot_consumption;"`
Expected: column lists match the `CREATE TABLE` statements above.

- [ ] **Step 4: Commit**

```bash
git add "femi9/billing/db_migrations/2026_09_16_stock_lots.sql"
git commit -m "Add stock_lots and stock_ledger_lot_consumption tables for FIFO costing"
```

---

## Task 2: `StockLots` helper class — FIFO consumption and lot recording

**Files:**
- Create: `femi9/billing/company/include/StockLots.php`
- Test: manual smoke test via a throwaway PHP script (this codebase has no PHPUnit harness in `company/`; follow the existing convention of `*_test.php`-free manual verification used elsewhere in this app — see Step 2/4 below for the exact commands)

**Interfaces:**
- Produces:
  - `StockLots::recordLot(mysqli $db, int $productId, string $userType, string $userId, float $rate, int $qty, string $purchaseDate, string $refType, ?string $refId, ?string $createdBy): int` — inserts a lot, returns its id.
  - `StockLots::consumeFifo(mysqli $db, int $productId, string $userType, string $userId, int $qtyNeeded, callable $fallbackRateFn): array` — returns a list of `['stock_lot_id' => ?int, 'qty_taken' => int, 'rate' => float]`. Locks matching `stock_lots` rows `FOR UPDATE`, decrements `qty_remaining` in place, calls `$fallbackRateFn()` (no args, returns float) only if lots run out before `qtyNeeded` is satisfied.
  - `StockLots::writeConsumption(mysqli $db, int $stockLedgerId, array $consumed): void` — inserts one `stock_ledger_lot_consumption` row per entry in `$consumed`.
  - `StockLots::restoreConsumption(mysqli $db, int $stockLedgerId): void` — looks up `stock_ledger_lot_consumption` rows for `$stockLedgerId`, adds `qty_taken` back onto each non-null `stock_lot_id`'s `qty_remaining` (skips null-lot rows — nothing to restock), then deletes those consumption rows.
- Consumes: nothing from other tasks — this is a leaf class, only depends on `stock_lots`/`stock_ledger_lot_consumption` (Task 1) and a caller-supplied fallback rate function.

- [ ] **Step 1: Write `StockLots.php`**

```php
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
```

- [ ] **Step 2: Write a manual smoke-test script and run it**

Create `/tmp/test_stock_lots.php` (throwaway, not committed):

```php
<?php
declare(strict_types=1);
chdir('/Applications/MAMP/htdocs/Femi9 Billing Server/femi9/billing/company');
require_once 'config.php';
require_once 'include/StockLots.php';

$productId = 999001; $userType = 'company'; $userId = 'test-godown';

$db_conn->query("DELETE FROM stock_lots WHERE product_id = $productId");
$db_conn->query("DELETE FROM stock_ledger_lot_consumption WHERE stock_ledger_id IN (SELECT id FROM stock_ledger WHERE product_id = $productId)");
$db_conn->query("DELETE FROM stock_ledger WHERE product_id = $productId");

$db_conn->begin_transaction();

// Worked example: 100 @ ₹20 on day 10, 400 @ ₹17 on day 15.
StockLots::recordLot($db_conn, $productId, $userType, $userId, 20.00, 100, '2026-09-10', 'opening_balance', null, 'test');
StockLots::recordLot($db_conn, $productId, $userType, $userId, 17.00, 400, '2026-09-15', 'opening_balance', null, 'test');

// Sell 90 first (all from the ₹20 lot; 10 left in it).
$consumed1 = StockLots::consumeFifo($db_conn, $productId, $userType, $userId, 90, fn() => 99.99);
assert(count($consumed1) === 1 && $consumed1[0]['rate'] === 20.00 && $consumed1[0]['qty_taken'] === 90);

// Sell 15 more: 10 from the ₹20 lot (its remainder), 5 from the ₹17 lot.
$consumed2 = StockLots::consumeFifo($db_conn, $productId, $userType, $userId, 15, fn() => 99.99);
assert(count($consumed2) === 2);
assert($consumed2[0]['rate'] === 20.00 && $consumed2[0]['qty_taken'] === 10);
assert($consumed2[1]['rate'] === 17.00 && $consumed2[1]['qty_taken'] === 5);

// Sell more than remains anywhere (395 left in ₹17 lot; ask for 500) -> fallback for the gap.
$consumed3 = StockLots::consumeFifo($db_conn, $productId, $userType, $userId, 500, fn() => 99.99);
assert(count($consumed3) === 2);
assert($consumed3[0]['rate'] === 17.00 && $consumed3[0]['qty_taken'] === 395);
assert($consumed3[1]['stock_lot_id'] === null && $consumed3[1]['rate'] === 99.99 && $consumed3[1]['qty_taken'] === 105);

$db_conn->rollback();
echo "ALL ASSERTIONS PASSED\n";
```

Run: `php /tmp/test_stock_lots.php`
Expected: `ALL ASSERTIONS PASSED` with no PHP errors or warnings.

- [ ] **Step 3: Fix any failures, re-run until it passes**

- [ ] **Step 4: Delete the throwaway test script**

Run: `rm /tmp/test_stock_lots.php`

- [ ] **Step 5: Commit**

```bash
git add "femi9/billing/company/include/StockLots.php"
git commit -m "Add StockLots FIFO consumption helper"
```

---

## Task 3: Wire `StockLots` into `StockService::deduct()` and `reverseDeduct()`

**Files:**
- Modify: `femi9/billing/company/include/StockService.php:1-13` (require), `:40-97` (`deduct`), `:167-216` (`reverseDeduct`)

**Interfaces:**
- Consumes: `StockLots::consumeFifo()`, `StockLots::writeConsumption()`, `StockLots::restoreConsumption()` (Task 2).
- Produces: `deduct()`'s return array gains no new keys (stays `['success','ledger_id','qty_after']`) — lot consumption is an internal side effect, not part of the public contract, so no caller needs to change.

- [ ] **Step 1: Add the require at the top of StockService.php**

```php
require_once __DIR__ . '/NeksomoStockBridge.php';
require_once __DIR__ . '/StockLots.php';
```

- [ ] **Step 2: Add the fallback-rate lookup as a private method**

Add this method to `StockService` (near `writeLedger`, e.g. after line 980's closing brace of `writeLedger`, before the class's final `}`):

```php
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
```

- [ ] **Step 3: Call FIFO consumption inside `deduct()`, right after `writeLedger`**

In `deduct()` (around line 79-83), change:

```php
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'deduct', $qty, $before, $after,
                $refType, $refId, '', $createdBy
            );
```

to:

```php
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'deduct', $qty, $before, $after,
                $refType, $refId, '', $createdBy
            );

            $consumed = StockLots::consumeFifo(
                $this->db, $productId, $userType, $userId, $qty,
                fn() => $this->fallbackRate($productId)
            );
            StockLots::writeConsumption($this->db, $ledgerId, $consumed);
```

- [ ] **Step 4: Call restoration inside `reverseDeduct()`, right after `writeLedger`**

In `reverseDeduct()` (around line 198-202), change:

```php
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'reverse_deduct', $qty, $before, $after,
                $refType, $refId, '', $createdBy
            );
```

to:

```php
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'reverse_deduct', $qty, $before, $after,
                $refType, $refId, '', $createdBy
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
```

- [ ] **Step 5: Manually verify with a smoke-test script**

Create `/tmp/test_stockservice_fifo.php` (throwaway):

```php
<?php
declare(strict_types=1);
chdir('/Applications/MAMP/htdocs/Femi9 Billing Server/femi9/billing/company');
require_once 'config.php';
require_once 'include/StockService.php';
require_once 'include/StockLots.php';

$productId = 999002; $userType = 'company'; $userId = 'test-godown-2';

$db_conn->query("DELETE FROM stock_ledger_lot_consumption WHERE stock_ledger_id IN (SELECT id FROM stock_ledger WHERE product_id = $productId)");
$db_conn->query("DELETE FROM stock_lots WHERE product_id = $productId");
$db_conn->query("DELETE FROM stock_ledger WHERE product_id = $productId");
$db_conn->query("DELETE FROM stock WHERE product_id = $productId AND user_type='$userType' AND user_id='$userId'");
$db_conn->query("INSERT INTO stock (product_id, opening_qty, opening_date, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, updated_at) VALUES ($productId, 500, CURDATE(), 500, 0, 0, 0, 500, '$userType', '$userId', NOW())");

StockLots::recordLot($db_conn, $productId, $userType, $userId, 20.00, 100, '2026-09-10', 'opening_balance', null, 'test');
StockLots::recordLot($db_conn, $productId, $userType, $userId, 17.00, 400, '2026-09-15', 'opening_balance', null, 'test');

$svc = new StockService($db_conn);
$result = $svc->deduct($productId, $userType, $userId, 105, 'invoice', 'test-inv-1', 'test');
$ledgerId = $result['ledger_id'];

$rows = $db_conn->query("SELECT stock_lot_id, qty_taken, rate FROM stock_ledger_lot_consumption WHERE stock_ledger_id = $ledgerId ORDER BY id")->fetch_all(MYSQLI_ASSOC);
assert(count($rows) === 2);
assert((float)$rows[0]['rate'] === 20.00 && (int)$rows[0]['qty_taken'] === 100);
assert((float)$rows[1]['rate'] === 17.00 && (int)$rows[1]['qty_taken'] === 5);
echo "DEDUCT FIFO OK\n";

$svc->reverseDeduct($productId, $userType, $userId, 105, 'invoice', 'test-inv-1', 'test');
$remaining = $db_conn->query("SELECT id, qty_remaining FROM stock_lots WHERE product_id = $productId ORDER BY purchase_date")->fetch_all(MYSQLI_ASSOC);
assert((int)$remaining[0]['qty_remaining'] === 100);
assert((int)$remaining[1]['qty_remaining'] === 400);
echo "REVERSE RESTOCK OK\n";

// Cleanup
$db_conn->query("DELETE FROM stock_ledger_lot_consumption WHERE stock_ledger_id IN (SELECT id FROM stock_ledger WHERE product_id = $productId)");
$db_conn->query("DELETE FROM stock_lots WHERE product_id = $productId");
$db_conn->query("DELETE FROM stock_ledger WHERE product_id = $productId");
$db_conn->query("DELETE FROM stock WHERE product_id = $productId AND user_type='$userType' AND user_id='$userId'");
echo "ALL PASSED\n";
```

Run: `php /tmp/test_stockservice_fifo.php`
Expected: `ALL PASSED` with no errors.

- [ ] **Step 6: Delete the throwaway script**

Run: `rm /tmp/test_stockservice_fifo.php`

- [ ] **Step 7: Commit**

```bash
git add "femi9/billing/company/include/StockService.php"
git commit -m "Wire FIFO lot consumption into StockService deduct/reverseDeduct"
```

---

## Task 4: Wire `StockLots` into `transferOut()`/`transferIn()`/`reverseTransferOut()`

**Files:**
- Modify: `femi9/billing/company/include/StockService.php:641-` (`transferOut`), `:690-` (`transferIn`), `:741-` (`reverseTransferOut`)

**Interfaces:**
- Consumes: `StockLots::consumeFifo()`, `StockLots::recordLot()`, `StockLots::writeConsumption()`, `StockLots::restoreConsumption()` (Task 2).

- [ ] **Step 1: In `transferOut()`, consume FIFO after its `writeLedger` call and return the weighted-average consumed rate**

The current body (lines 641-683):

```php
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
            $this->ensureNeksomoTopUp($productId, $userType, $userId, $qty, $createdBy);

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
```

becomes (only the block between `writeLedger` and the `return` changes):

```php
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'transfer_out', $qty, $before, $after,
                $refType, $refId, '', $createdBy
            );

            $consumed = StockLots::consumeFifo(
                $this->db, $productId, $userType, $userId, $qty,
                fn() => $this->fallbackRate($productId)
            );
            StockLots::writeConsumption($this->db, $ledgerId, $consumed);

            $totalTaken = array_sum(array_column($consumed, 'qty_taken'));
            $weightedRate = $totalTaken > 0
                ? array_sum(array_map(fn($c) => $c['qty_taken'] * $c['rate'], $consumed)) / $totalTaken
                : 0.0;

            if (!$externalTransaction) $this->db->commit();
            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after, 'consumed_rate' => $weightedRate];
```

- [ ] **Step 2: In `transferIn()`, add an optional `$lotRate` parameter and record a new lot when it's given**

`transferIn` doesn't know the consumed cost by itself — the caller (`internal_transfer_action.php`) does, because it calls `transferOut` first (Step 1, above) and gets its `consumed_rate` back. Add an optional trailing parameter so every other existing caller of `transferIn` (which won't pass it) keeps working unchanged.

The current body (lines 690-734):

```php
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
```

Change the signature line:

```php
    public function transferIn(
        int    $productId,
        string $userType,
        string $userId,
        int    $qty,
        string $refType,
        string $refId,
        string $createdBy,
        bool   $externalTransaction = false,
        ?float $lotRate = null   // weighted-avg cost carried from the source transferOut; null = skip lot creation
    ): array {
```

And change the block between `writeLedger` and `return`:

```php
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'transfer_in', $qty, $before, $after,
                $refType, $refId, '', $createdBy
            );

            if ($lotRate !== null) {
                StockLots::recordLot(
                    $this->db, $productId, $userType, $userId, $lotRate, $qty,
                    date('Y-m-d'), 'transfer_in', $refId, $createdBy
                );
            }

            if (!$externalTransaction) $this->db->commit();
            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];
```

- [ ] **Step 3: In `reverseTransferOut()`, restore consumption after its `writeLedger` call**

The current body (lines 741-776):

```php
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
```

Change the block between `writeLedger` and `return`:

```php
            $ledgerId = $this->writeLedger(
                $productId, $userType, $userId,
                'transfer_out_reverse', $qty, $before, $after,
                $refType, $refId, '', $createdBy
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
```

- [ ] **Step 4: Update `internal_transfer_action.php` to pass the consumed rate through**

In `internal_transfer_action.php` (around lines 192-203), change:

```php
        $stockService->transferOut(
            $pid, $Login_user_TYPEvl, $send_from, $qty,
            'transfer', $tempid, $createdBy,
            true // outer transaction owns commit
        );

        // Credit to destination godown (input_qty ↑, closing_qty ↑) — FOR UPDATE + ledger
        $stockService->transferIn(
            $pid, $Login_user_TYPEvl, $send_to, $qty,
            'transfer', $tempid, $createdBy,
            true
        );
```

to:

```php
        $outResult = $stockService->transferOut(
            $pid, $Login_user_TYPEvl, $send_from, $qty,
            'transfer', $tempid, $createdBy,
            true // outer transaction owns commit
        );

        // Credit to destination godown (input_qty ↑, closing_qty ↑) — FOR UPDATE + ledger.
        // The new lot at the destination carries the SOURCE lot's real cost
        // forward (consumed_rate), not this transfer's manually-entered
        // $rate, which may be a different inter-godown billing rate.
        $stockService->transferIn(
            $pid, $Login_user_TYPEvl, $send_to, $qty,
            'transfer', $tempid, $createdBy,
            true,
            $outResult['consumed_rate'] ?? null
        );
```

- [ ] **Step 5: Manually verify with a smoke-test script**

Create `/tmp/test_transfer_fifo.php` (throwaway) modeled exactly on Task 3 Step 5's script, but calling `transferOut()`/`transferIn()` instead of `deduct()`, asserting: (a) `transferOut` on a product with lots at ₹20/₹17 returns the correct `consumed_rate` weighted average for a quantity spanning both lots, (b) after `transferIn` with that rate, a new `stock_lots` row exists at the destination `user_id` with that exact rate and qty.

Run: `php /tmp/test_transfer_fifo.php`
Expected: all assertions pass.

- [ ] **Step 6: Delete the throwaway script**

Run: `rm /tmp/test_transfer_fifo.php`

- [ ] **Step 7: Commit**

```bash
git add "femi9/billing/company/include/StockService.php" "femi9/billing/company/internal_transfer_action.php"
git commit -m "Carry FIFO lot cost across internal transfers"
```

---

## Task 5: Capture purchase quantity on the LLP rate-entry forms

**Files:**
- Modify: `femi9/billing/company/llp-purchase-rate.php`
- Modify: `femi9/billing/company/llp-purchase-rate-action.php`
- Modify: `femi9/billing/company/neksomo-llp-piece-sale.php`
- Modify: `femi9/billing/company/neksomo-llp-piece-sale-action.php`

**Interfaces:**
- Consumes: `StockLots::recordLot()` (Task 2).
- Produces: nothing new for other tasks — this is a leaf UI/action change. Task 6 reads `stock_lots` directly, not through this task's code.

- [ ] **Step 1: Add a Quantity input to `llp-purchase-rate.php`'s add-row UI**

Find the rate input in the form (near where `$('#rateInput')` is read, per the existing `addProduct()` function). Add a sibling `#qtyInput` field next to the existing rate field, then update `addProduct()`:

```javascript
        window.addProduct = function () {
            hideAddError();
            var $opt       = $('#productSelect').find('option:selected');
            var product_id = parseInt($('#productSelect').val());
            var name       = $opt.text().trim();
            var isPack     = $opt.data('unit-type') === 'pack';
            var rate       = parseFloat($('#rateInput').val());
            var qty        = parseInt($('#qtyInput').val());

            if (!product_id)             { showAddError('Please select a product.'); return; }
            if (isNaN(rate) || rate < 0) { showAddError('Please enter a valid rate.'); return; }
            if (isNaN(qty) || qty <= 0)  { showAddError('Please enter a valid quantity purchased.'); return; }
            if (rateItems.find(function (i) { return i.product_id === product_id; })) {
                showAddError('This product is already added.'); return;
            }

            rateItems.push({ product_id: product_id, name: name, rate: rate, qty: qty, unitLabel: isPack ? '/pack' : '/pc' });
            renderTable();
            resetAddForm();
        };
```

Update `renderTable()` to show the qty column, and `buildHiddenInputs()`:

```javascript
        function buildHiddenInputs() {
            var html = '';
            $.each(rateItems, function (_, item) {
                html += '<input type="hidden" name="product_id[]"     value="' + item.product_id + '">';
                html += '<input type="hidden" name="rate_per_piece[]" value="' + item.rate + '">';
                html += '<input type="hidden" name="qty_purchased[]"  value="' + item.qty + '">';
            });
            $('#hiddenRateInputs').html(html);
        }
```

Add the `#qtyInput` field's HTML markup next to the existing rate input, and add a "Qty" column header + cell in the table markup (`renderTable()`'s appended `<tr>`), following the exact same structure as the existing Rate column.

- [ ] **Step 2: Update `llp-purchase-rate-action.php`'s add-record branch to read and store qty**

Change:

```php
    $raw_pids       = $_POST['product_id'] ?? [];
    $raw_rates      = $_POST['rate_per_piece'] ?? [];
```

to:

```php
    $raw_pids       = $_POST['product_id'] ?? [];
    $raw_rates      = $_POST['rate_per_piece'] ?? [];
    $raw_qtys       = $_POST['qty_purchased'] ?? [];
```

Change the row-building loop:

```php
    $rows = []; $seen = [];
    foreach ($raw_pids as $i => $rpid) {
        $product_id     = filter_var($rpid, FILTER_VALIDATE_INT);
        $rate_per_piece = filter_var($raw_rates[$i] ?? null, FILTER_VALIDATE_FLOAT);
        $qty_purchased  = filter_var($raw_qtys[$i] ?? null, FILTER_VALIDATE_INT);
        if (!$product_id || $rate_per_piece === false || $rate_per_piece < 0) continue;
        if (!$qty_purchased || $qty_purchased <= 0) continue;
        if (isset($seen[$product_id])) continue;
        $seen[$product_id] = true;
        $rows[] = ['product_id' => $product_id, 'rate' => $rate_per_piece, 'qty' => $qty_purchased];
    }
```

After the existing insert loop's `$stmt->execute(); $added++;` (inside the `try` block), record a lot for the just-inserted rate row:

```php
require_once("include/StockLots.php");
```

(add this require near the top of the file, with the other `require_once`s)

```php
        try {
            $stmt->execute();
            $added++;

            // stock_lots is keyed to a physical stock holder (user_type/user_id),
            // but femi9_llp_sale_rates has no holder concept — it's Femi9's own
            // cost basis, held company-wide. Use user_type='company',
            // user_id='llp' as a fixed synthetic holder for this cost pool,
            // distinct from any real godown id.
            StockLots::recordLot(
                $db_conn, $row['product_id'], 'company', 'llp',
                $row['rate'], $row['qty'], $effective_date,
                'llp_rate_entry', (string) $db_conn->insert_id, $created_by
            );
        } catch (\mysqli_sql_exception $e) {
```

- [ ] **Step 3: Repeat Steps 1-2 identically for `neksomo-llp-piece-sale.php`/`neksomo-llp-piece-sale-action.php`**

Same field additions, same `qty_purchased[]` array name, same `StockLots::recordLot()` call with `ref_type='llp_rate_entry'` — the only difference is the target rate table is `neksomo_llp_piece_rates` and the synthetic holder should be `user_type='company', user_id='llp'` (same pool — both tables feed the same Gross Profit cost basis per the spec's fallback-chain design).

- [ ] **Step 4: Manually verify by submitting both forms in a browser (or via curl with a valid session) and checking `stock_lots`**

Run: `mysql -u <user> -p <database> -e "SELECT * FROM stock_lots WHERE ref_type='llp_rate_entry' ORDER BY id DESC LIMIT 5;"`
Expected: one new row per product submitted, with the entered qty and rate.

- [ ] **Step 5: Commit**

```bash
git add "femi9/billing/company/llp-purchase-rate.php" "femi9/billing/company/llp-purchase-rate-action.php" "femi9/billing/company/neksomo-llp-piece-sale.php" "femi9/billing/company/neksomo-llp-piece-sale-action.php"
git commit -m "Capture purchase quantity on LLP rate-entry forms, create FIFO lots"
```

---

## Task 6: Record a lot from Neksomo manufacturer purchases

**Files:**
- Modify: `femi9/billing/company/neksomo-manufacturer-purchase-action.php`

**Interfaces:**
- Consumes: `StockLots::recordLot()` (Task 2).

- [ ] **Step 1: Add the require**

Near the existing `require_once("include/StockService.php");` at the top of the file, add:

```php
require_once("include/StockLots.php");
```

- [ ] **Step 2: Record a lot per item, after the existing item-insert loop**

The existing code (around lines 276-284):

```php
    foreach ($items as $item) {
        $itemStmt->bind_param(
            'iiiiddsdddi',
            $purchase_id, $item['pid'], $item['qty_packs'], $item['qty_pieces'], $item['cost'],
            $item['gst_rate'], $item['gst_type'], $item['total_cost'], $item['taxable_value'], $item['gst_amount'], $item['ledger_id']
        );
        $itemStmt->execute();
    }
    $itemStmt->close();
```

becomes:

```php
    foreach ($items as $item) {
        $itemStmt->bind_param(
            'iiiiddsdddi',
            $purchase_id, $item['pid'], $item['qty_packs'], $item['qty_pieces'], $item['cost'],
            $item['gst_rate'], $item['gst_type'], $item['total_cost'], $item['taxable_value'], $item['gst_amount'], $item['ledger_id']
        );
        $itemStmt->execute();

        // Only whole packs actually credited to stock become a lot — a
        // purchase that only topped up the loose-piece remainder (qty_packs
        // === 0) has nothing to record yet; it'll become a lot once enough
        // further pieces accumulate to complete a pack (a future purchase's
        // own recordLot call, once that purchase's qty_packs > 0).
        if ($item['qty_packs'] > 0) {
            // Rate is per PIECE in the purchase form; stock_lots tracks
            // pack-based qty (matching stock.closing_qty), so the lot's rate
            // must be per PACK: cost_per_piece * pieces_per_pack.
            $ratePerPack = round($item['cost'] * $item['pieces_per_pack'], 6);
            StockLots::recordLot(
                $db_conn, $item['pid'], 'company', (string) $neksomoGodownId,
                $ratePerPack, $item['qty_packs'], $purchase_date,
                'neksomo_purchase', (string) $purchase_id, $created_by
            );
        }
    }
    $itemStmt->close();
```

- [ ] **Step 3: Manually verify by submitting a manufacturer purchase and checking `stock_lots`**

Run: `mysql -u <user> -p <database> -e "SELECT * FROM stock_lots WHERE ref_type='neksomo_purchase' ORDER BY id DESC LIMIT 5;"`
Expected: one row per line item with `qty_packs > 0`, rate = `cost_per_piece * pieces_per_pack`.

- [ ] **Step 4: Commit**

```bash
git add "femi9/billing/company/neksomo-manufacturer-purchase-action.php"
git commit -m "Record FIFO lot from Neksomo manufacturer purchases"
```

---

## Task 7: Opening-lot backfill migration

**Files:**
- Create: `femi9/billing/db_migrations/2026_09_16_stock_lots_opening_balance.sql`

**Interfaces:**
- Consumes: `stock_lots` (Task 1), existing `stock` table, existing rate-lookup logic (same formula as `StockService::fallbackRate()` from Task 3).

- [ ] **Step 1: Write the backfill SQL**

```sql
-- Seed one opening lot per (product, holder) with existing stock, so
-- FIFO consumption has something to draw from immediately after this
-- migration instead of falling back to the blended rate for all
-- pre-cutover stock. Rate is today's "latest effective_date <= now" lookup
-- — the same fallback formula StockService::fallbackRate() uses, applied
-- once here for existing stock. Uses only company-side stock (StockLots
-- is only wired into the company StockService copy in this phase).
-- Applied: 2026-09-16

INSERT INTO stock_lots (product_id, user_type, user_id, rate, qty_purchased, qty_remaining, purchase_date, ref_type, created_by)
SELECT
    s.product_id, s.user_type, s.user_id,
    COALESCE(
        (SELECT CASE WHEN r.gst_type = 'inclusive' THEN r.rate_per_piece / (1 + r.gst_rate/100) ELSE r.rate_per_piece END
             * COALESCE(NULLIF(p.pieces_per_pack,0),1)
         FROM neksomo_llp_piece_rates r
         WHERE r.product_id = p.id AND r.effective_date <= CURDATE()
         ORDER BY r.effective_date DESC LIMIT 1),
        (SELECT CASE WHEN fr.gst_type = 'inclusive' THEN fr.rate_per_piece / (1 + fr.gst_rate/100) ELSE fr.rate_per_piece END
             * COALESCE(NULLIF(p.pieces_per_pack,0),1)
         FROM femi9_llp_sale_rates fr
         WHERE fr.product_id = p.id AND fr.effective_date <= CURDATE()
         ORDER BY fr.effective_date DESC LIMIT 1),
        0
    ) AS rate,
    s.closing_qty, s.closing_qty, CURDATE(), 'opening_balance', 'migration'
FROM stock s
JOIN products p ON p.id = s.product_id
WHERE s.closing_qty > 0
  AND s.user_type = 'company';
```

- [ ] **Step 2: Apply it and spot-check**

Run: `mysql -u <user> -p <database> < "femi9/billing/db_migrations/2026_09_16_stock_lots_opening_balance.sql"`

Run: `mysql -u <user> -p <database> -e "SELECT COUNT(*) FROM stock_lots WHERE ref_type='opening_balance';"`
Expected: one row per company-side `(product_id, user_id)` with `closing_qty > 0` at migration time — count should roughly match `SELECT COUNT(*) FROM stock WHERE user_type='company' AND closing_qty > 0`.

- [ ] **Step 3: Commit**

```bash
git add "femi9/billing/db_migrations/2026_09_16_stock_lots_opening_balance.sql"
git commit -m "Backfill opening stock lots for pre-cutover company stock"
```

---

## Task 8: Rewrite Neksomo Gross Profit cost lookup in `mis-report.php`

**Files:**
- Modify: `femi9/billing/company/mis-report.php:437-531` (main Gross Profit query)

**Interfaces:**
- Consumes: `stock_ledger_lot_consumption` (Task 1), populated by Tasks 3-4's `deduct()`/`transferOut()` wiring.

- [ ] **Step 1: Replace the cost-rate subquery approach with a lot-consumption join**

The current query (lines 502-531) computes `SUM((sold_rate - cost_rate_as_of(to)) * net_qty)` where `cost_rate_as_of` is looked up once per product for the whole period. Replace it with a query that sums real per-sale COGS from `stock_ledger_lot_consumption`, joined to `stock_ledger` for the date/ref_type filter, alongside the existing sold/return subqueries.

Add a new COGS subquery, keyed by `product_id` and the period's date range, matching against `stock_ledger.ref_type IN ('invoice','user_invoice','ot_sale')`. Note `stock_ledger` has no direct date column — join through the ledger's own timestamp (`created_at`) for the period filter, since `deduct()` writes the ledger row at the moment of the sale (not backdated):

```php
$gp_cogs_subq = "(
    SELECT sl.product_id, SUM(slc.qty_taken * slc.rate) AS cogs
    FROM stock_ledger sl
    JOIN stock_ledger_lot_consumption slc ON slc.stock_ledger_id = sl.id
    WHERE sl.action = 'deduct'
      AND sl.ref_type IN ('invoice','user_invoice','ot_sale')
      AND sl.user_type = ?
      AND DATE(sl.created_at) BETWEEN ? AND ?
    GROUP BY sl.product_id
)";
```

Replace the `$gross_profit` query's SELECT and WHERE (lines 502, 530) — change:

```php
$gross_profit = (float)cval($db_conn,
    "SELECT COALESCE(SUM((sold.sold_rate / {$gp_sold_rate_gst_divisor} - {$gp_cost_rate_subq}) * (sold.qty_sold - COALESCE(ret.qty_returned,0))), 0)
     FROM (
         ...
     ) sold
     JOIN products p ON p.id = sold.pr_id
     LEFT JOIN (...) ret ON ret.pr_id = sold.pr_id
     WHERE {$gp_cost_rate_subq} IS NOT NULL",
    str_repeat('s', count($gp_all_params)), $gp_all_params);
```

to:

```php
$gp_cogs_params = [$utype, $from, $to];
$gross_profit = (float)cval($db_conn,
    "SELECT COALESCE(SUM(
         sold.sold_rate / {$gp_sold_rate_gst_divisor} * (sold.qty_sold - COALESCE(ret.qty_returned,0))
         - COALESCE(cogs.cogs, 0) * (sold.qty_sold - COALESCE(ret.qty_returned,0)) / NULLIF(sold.qty_sold, 0)
     ), 0)
     FROM (
         SELECT s.pr_id, SUM(s.qty) qty_sold, SUM(s.line_total)/NULLIF(SUM(s.qty),0) sold_rate
         FROM (
             SELECT ii.pr_id, ii.qty, ii.total AS line_total
             FROM invoice_items ii JOIN invoice i ON i.inv_id=ii.inv_id
             WHERE i.user_type=? AND i.sub_total>0 AND i.date BETWEEN ? AND ?{$tc_ii}
             UNION ALL
             SELECT uii.pr_id, uii.qty, uii.total AS line_total
             FROM user_invoice_items uii JOIN user_invoice ui ON ui.inv_id=uii.inv_id
             WHERE ui.from_user_type=? AND ui.sub_total>0 AND ui.date BETWEEN ? AND ?{$tc_uii}
             {$gp_ot_sold_union}
             {$gp_tp_union}
         ) s
         GROUP BY s.pr_id
     ) sold
     JOIN products p ON p.id = sold.pr_id
     LEFT JOIN (
         SELECT r.pr_id, SUM(r.qty) qty_returned
         FROM (
             SELECT ri.prid pr_id, ri.qty
             FROM user_return_stock_items ri
             WHERE ri.to_usertype=?".($filter_tp > 0 ? " AND ri.to_userid={$filter_tp}" : "")." AND ri.date BETWEEN ? AND ?
             {$gp_ot_ret_union}
         ) r
         GROUP BY r.pr_id
     ) ret ON ret.pr_id = sold.pr_id
     LEFT JOIN {$gp_cogs_subq} cogs ON cogs.product_id = sold.pr_id",
    str_repeat('s', count($gp_params) + count($gp_return_params) + count($gp_cogs_params)),
    array_merge($gp_params, $gp_return_params, $gp_cogs_params));
```

Note: `$gp_cost_rate_subq` and its `[$to, $to]` double-binding are no longer used by this query — remove the `array_merge([$to, $to], $gp_params, $gp_return_params, [$to, $to])` line (the old `$gp_all_params`) since the params list is now `$gp_params + $gp_return_params + $gp_cogs_params` as shown above. Leave `$gp_cost_rate_subq` itself defined (it's still used by the separate Napkin/Diaper LLP blocks elsewhere in the file, out of this task's scope) — only this one query's usage changes.

The `COALESCE(cogs.cogs, 0) * (qty_sold - qty_returned) / NULLIF(qty_sold, 0)` term pro-rates COGS down when some of the period's sold qty was returned, matching the existing return-netting behavior (net cost only for units that stayed sold) without needing a second consumption lookup for returns in this phase — returns valued at the *same average consumed rate* as the sale, not FIFO'd separately. This is a deliberate simplification: exact FIFO-of-returns is not needed to fix the core reported bug (mid-period rate changes), and matches the plan's cost-efficiency goal by not requiring a second consumption-linkage query.

- [ ] **Step 2: Manually verify against the worked example**

Set up test data reproducing the spec's worked example (100 packs @ ₹20 lot, 400 @ ₹17 lot, sell 90 before day 15, sell 15 after) through the real invoice-creation flow (not direct DB inserts) so `StockService::deduct()` populates `stock_ledger_lot_consumption` naturally. Load `mis-report.php` for that product/date range and confirm the reported Gross Profit matches a hand-calculated FIFO value: `(sale_rate - 20) * 100 + (sale_rate - 17) * 5` for the two consumeFifo calls in this example (adjust exact figures to whatever qty/rate combination the manual test setup uses).

Expected: Gross Profit figure matches the hand calculation, not the old blended-rate calculation.

- [ ] **Step 3: Regression-check a normal period with no mid-period rate change**

Run the report for a historical period where the cost rate never changed. Expected: Gross Profit figure matches (or very closely matches — small differences from opening-lot backfill rounding are acceptable) what it reported before this change, confirming no regression for the common case.

- [ ] **Step 4: Commit**

```bash
git add "femi9/billing/company/mis-report.php"
git commit -m "Cost Neksomo Gross Profit from actual FIFO lot consumption"
```

---

## Task 9: End-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Re-run the Task 8 Step 2 worked-example scenario one more time end-to-end** — create a product's LLP rate at ₹20 with qty 100 via the UI form, sell some of it via a real invoice, add a new LLP rate at ₹17 with qty 400, sell more (spanning both lots), then load `mis-report.php` and confirm the Gross Profit figure is exactly what FIFO predicts.

- [ ] **Step 2: Verify a reversal (delete/edit an invoice) restores the correct lot** — after the above sale, delete the invoice (triggering `reverseDeduct`), then check `stock_lots.qty_remaining` for both lots via `SELECT * FROM stock_lots WHERE product_id = <pid> ORDER BY purchase_date;` — confirm quantities returned to their pre-sale state.

- [ ] **Step 3: Verify an internal transfer carries cost forward** — transfer some of the ₹17-lot stock to another godown, then check `stock_lots` at the destination `user_id` has a new `transfer_in` row at ₹17 (not the transfer's manually-entered `$rate`, if different).

- [ ] **Step 4: No commit needed** — this task is verification only; if any step fails, return to the relevant earlier task, fix, and re-commit there.
