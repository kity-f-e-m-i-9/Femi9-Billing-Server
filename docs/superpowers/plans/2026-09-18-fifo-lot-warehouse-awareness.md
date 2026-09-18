# FIFO Lot Warehouse-Awareness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make FIFO cost-lot tracking (`stock_lots`, `stock_ledger_lot_consumption`)
warehouse-aware, so a sale drawn from a specific physical godown only
consumes that godown's own cost lots — closing Phase 4 of the per-godown
split-stock project.

**Architecture:** Add a nullable `warehouse_id` column to `stock_lots`,
mirroring the exact pattern already used for `stock`/`stock_ledger` in
Phase 1. Thread an optional `?int $warehouseId = null` trailing parameter
through `StockLots::recordLot()`/`consumeFifo()`, then update the two
`StockService.php` call sites (already warehouse-aware) to pass the
`$warehouseId` they already have in scope. Two other call sites
(`llp-purchase-rate-action.php`, `neksomo-llp-piece-sale-action.php`)
intentionally stay `null` — they write to a synthetic, non-physical cost
pool, not a real stock location. A third (`neksomo-manufacturer-purchase-action.php`)
is deliberately left untouched — that flow has no warehouse picker of its
own yet (deferred, separate scope).

**Tech Stack:** PHP 8 + mysqli, existing `StockService`/`StockLots`
classes. Tests follow this repo's convention: standalone PHP scripts
under `femi9/billing/includes/tests/` with a disposable MySQL schema,
run via `php <file>.php` (no PHPUnit).

**Spec:** `docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md`
(Phase 4 section)

## Global Constraints

- `warehouse_id` on `stock_lots` is nullable, additive — no backfill.
  Existing lots (currently 61 rows) get `warehouse_id = NULL`
  ("unassigned"), same convention as `stock`/`stock_ledger`.
- `StockLots::consumeFifo()`'s lot-selection query must only draw from
  lots matching the requested `warehouse_id` (or the unassigned bucket,
  when `null`) — never mix lots across warehouses in one FIFO run.
- The two synthetic-holder call sites (`user_id='llp'` in
  `llp-purchase-rate-action.php` and `neksomo-llp-piece-sale-action.php`)
  are explicitly left at `warehouseId = null` — do not attempt to give
  them a real warehouse, since they represent a company-wide cost pool
  with no physical location, not a stock holder that could plausibly
  live in H1/G1/G2.
- `neksomo-manufacturer-purchase-action.php`'s `recordLot()` call
  (line 295) is out of scope for this plan — that flow has no warehouse
  selection UI yet (separately deferred); do not add one here.

---

## File Structure

- **Create:** `femi9/billing/db_migrations/2026_09_18_stock_lots_warehouse_key.sql`
  — adds `warehouse_id INT NULL` to `stock_lots`.
- **Modify:** `femi9/billing/company/include/StockLots.php` — thread
  `?int $warehouseId = null` through `recordLot()` and `consumeFifo()`.
- **Modify:** `femi9/billing/company/include/StockService.php` — pass
  the already-in-scope `$warehouseId` at its 2 call sites (line 87 inside
  `deduct()`, line 718 inside `transferOut()`, line 784 inside
  `transferIn()`'s `recordLot()` call).
- **Test:** `femi9/billing/includes/tests/StockLotsWarehouseKeyTest.php`
  (new).

---

### Task 1: `StockLots` warehouse-aware core + schema migration

**Files:**
- Create: `femi9/billing/db_migrations/2026_09_18_stock_lots_warehouse_key.sql`
- Modify: `femi9/billing/company/include/StockLots.php`
- Test: `femi9/billing/includes/tests/StockLotsWarehouseKeyTest.php`

**Interfaces:**
- Produces:
  - `StockLots::recordLot(mysqli $db, int $productId, string $userType, string $userId, float $rate, int $qty, string $purchaseDate, string $refType, ?string $refId, ?string $createdBy, ?int $warehouseId = null): int`
  - `StockLots::consumeFifo(mysqli $db, int $productId, string $userType, string $userId, int $qtyNeeded, callable $fallbackRateFn, ?int $warehouseId = null): array`
    (`writeConsumption()`/`restoreConsumption()` are unchanged — they key
    off `stock_ledger_lot_consumption.stock_lot_id`, which already
    uniquely identifies a specific lot regardless of warehouse, so no
    warehouse parameter is needed on those two methods.)

- [ ] **Step 1: Write the failing test**

Create `femi9/billing/includes/tests/StockLotsWarehouseKeyTest.php`:

```php
<?php
// femi9/billing/includes/tests/StockLotsWarehouseKeyTest.php
// Manual run: php StockLotsWarehouseKeyTest.php
//
// Regression/behavior test for Phase 4 of the per-godown split-stock spec
// (docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md):
// confirms StockLots::recordLot()/consumeFifo() correctly scope by
// warehouse_id when given one, and reproduce today's exact behavior
// (drawing from the unassigned/NULL bucket) when given none.
//
// Uses a disposable `stock_lots_warehouse_test` schema — never the app's
// production database — with real `stock_lots` /
// `stock_ledger_lot_consumption` table shapes.

require_once __DIR__ . '/../../company/include/StockLots.php';
require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const TEST_SCHEMA = 'stock_lots_warehouse_test';

$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `" . TEST_SCHEMA . "`")) {
    fwrite(STDERR, "FATAL: could not CREATE DATABASE `" . TEST_SCHEMA . "` — " . $conn->error . "\n");
    exit(1);
}
if (!$conn->select_db(TEST_SCHEMA)) {
    fwrite(STDERR, "FATAL: could not switch to schema `" . TEST_SCHEMA . "` — " . $conn->error . "\n");
    exit(1);
}

$passCount = 0;
$failCount = 0;

function assertEqual($actual, $expected, $label) {
    global $passCount, $failCount;
    if ($actual == $expected) {
        echo "PASS: $label\n";
        $passCount++;
    } else {
        echo "FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
        $failCount++;
    }
}

// ========== SETUP: real table shapes, post-Phase-4 ==========
$conn->query("CREATE TABLE stock_lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_type VARCHAR(32) NOT NULL,
    user_id VARCHAR(32) NOT NULL,
    warehouse_id INT NULL,
    rate DECIMAL(12,6) NOT NULL,
    qty_purchased INT NOT NULL,
    qty_remaining INT NOT NULL,
    purchase_date DATE NOT NULL,
    ref_type VARCHAR(32) NOT NULL,
    ref_id VARCHAR(64) NULL,
    created_by VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE stock_ledger_lot_consumption (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stock_ledger_id INT NOT NULL,
    stock_lot_id INT NULL,
    qty_taken INT NOT NULL,
    rate DECIMAL(12,6) NOT NULL
)");

function fallbackRate(): float { return 99.0; } // distinctive sentinel — should never be hit while real lots cover the qty

// ========== TESTS: recordLot tags the warehouse_id ==========
$lotId = StockLots::recordLot($conn, 10, 'company', '1', 5.00, 100, '2026-09-01', 'test', 'ref-1', 'tester', 201);
$row = $conn->query("SELECT warehouse_id FROM stock_lots WHERE id=$lotId")->fetch_assoc();
assertEqual((int)$row['warehouse_id'], 201, 'recordLot(warehouse=201) tags the lot with warehouse_id');

$lotIdNull = StockLots::recordLot($conn, 10, 'company', '1', 6.00, 50, '2026-09-02', 'test', 'ref-2', 'tester', null);
$rowNull = $conn->query("SELECT warehouse_id FROM stock_lots WHERE id=$lotIdNull")->fetch_assoc();
assertEqual($rowNull['warehouse_id'], null, 'recordLot(warehouse=null) leaves warehouse_id NULL (unassigned)');

// ========== TESTS: consumeFifo only draws from the matching warehouse's lots ==========
// Product 20: two lots in warehouse 301 (oldest first), one lot in warehouse 302, one unassigned lot.
StockLots::recordLot($conn, 20, 'company', '2', 10.00, 30, '2026-09-01', 'test', 'w301-a', 'tester', 301);
StockLots::recordLot($conn, 20, 'company', '2', 12.00, 40, '2026-09-05', 'test', 'w301-b', 'tester', 301);
StockLots::recordLot($conn, 20, 'company', '2', 20.00, 100, '2026-09-01', 'test', 'w302-a', 'tester', 302);
StockLots::recordLot($conn, 20, 'company', '2', 30.00, 100, '2026-09-01', 'test', 'unassigned-a', 'tester', null);

// Consume 50 units from warehouse 301: should take all 30 from the oldest
// lot (rate 10.00) then 20 from the second lot (rate 12.00) — never touch
// warehouse 302's or the unassigned lot.
$consumed301 = StockLots::consumeFifo($conn, 20, 'company', '2', 50, 'fallbackRate', 301);
assertEqual(count($consumed301), 2, 'consumeFifo(warehouse=301) draws from exactly 2 lots (FIFO order within that warehouse)');
assertEqual($consumed301[0]['qty_taken'], 30, 'consumeFifo(warehouse=301) takes the full oldest lot first (30 units @ rate 10.00)');
assertEqual($consumed301[0]['rate'], 10.00, 'consumeFifo(warehouse=301) oldest lot rate is correct');
assertEqual($consumed301[1]['qty_taken'], 20, 'consumeFifo(warehouse=301) takes the remainder from the second lot (20 units @ rate 12.00)');
assertEqual($consumed301[1]['rate'], 12.00, 'consumeFifo(warehouse=301) second lot rate is correct');

$wh302Row = $conn->query("SELECT qty_remaining FROM stock_lots WHERE product_id=20 AND warehouse_id=302")->fetch_assoc();
assertEqual((int)$wh302Row['qty_remaining'], 100, 'consumeFifo(warehouse=301) leaves warehouse-302 lot completely untouched');

$unassignedRow = $conn->query("SELECT qty_remaining FROM stock_lots WHERE product_id=20 AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$unassignedRow['qty_remaining'], 100, 'consumeFifo(warehouse=301) leaves the unassigned lot completely untouched');

// Consume 40 units with warehouse=null: should draw only from the
// unassigned lot, not warehouse 301 or 302.
$consumedNull = StockLots::consumeFifo($conn, 20, 'company', '2', 40, 'fallbackRate', null);
assertEqual(count($consumedNull), 1, 'consumeFifo(warehouse=null) draws from exactly 1 lot (the unassigned one)');
assertEqual($consumedNull[0]['qty_taken'], 40, 'consumeFifo(warehouse=null) takes the correct qty from the unassigned lot');
assertEqual($consumedNull[0]['rate'], 30.00, 'consumeFifo(warehouse=null) unassigned lot rate is correct');

$unassignedAfter = $conn->query("SELECT qty_remaining FROM stock_lots WHERE product_id=20 AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$unassignedAfter['qty_remaining'], 60, 'consumeFifo(warehouse=null) correctly decremented the unassigned lot (100-40=60)');

// ========== TEST: fallback rate still triggers when a warehouse's own lots are insufficient ==========
// Warehouse 301 had 70 total (30+40); the earlier 50-unit consumption left
// exactly 20 remaining in the second lot. Drain that remaining 20 first so
// warehouse 301 has genuinely 0 left, then confirm a further request falls
// back rather than bleeding into warehouse 302 or the unassigned lot.
$drain301 = StockLots::consumeFifo($conn, 20, 'company', '2', 20, 'fallbackRate', 301);
assertEqual($drain301[0]['qty_taken'], 20, 'Draining warehouse 301\'s remaining 20 units succeeds from its own lot (no fallback yet)');

$consumedShort = StockLots::consumeFifo($conn, 20, 'company', '2', 10, 'fallbackRate', 301);
assertEqual(count($consumedShort), 1, 'consumeFifo(warehouse=301) with no remaining lots falls back to fallbackRateFn');
assertEqual($consumedShort[0]['stock_lot_id'], null, 'Fallback consumption has no stock_lot_id');
assertEqual($consumedShort[0]['rate'], 99.0, 'Fallback consumption uses the sentinel fallback rate (99.0), never bleeds into warehouse 302 or unassigned lots');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php "femi9/billing/includes/tests/StockLotsWarehouseKeyTest.php"`
Expected: FATAL error or early assertion failures — `recordLot()`/
`consumeFifo()` don't yet accept a `$warehouseId` argument, and
`stock_lots` (as created by this test) has a `warehouse_id` column the
current `StockLots.php` code never writes to.

- [ ] **Step 3: Write the migration**

Create `femi9/billing/db_migrations/2026_09_18_stock_lots_warehouse_key.sql`:

```sql
-- Adds physical-warehouse tagging to FIFO cost lots, so a sale drawn from
-- a specific godown only consumes that godown's own lots. Nullable,
-- additive — existing lots become "unassigned" (NULL), same convention
-- as stock.warehouse_id / stock_ledger.warehouse_id from Phase 1. See
-- docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md,
-- Phase 4.
ALTER TABLE stock_lots
  ADD COLUMN warehouse_id INT NULL AFTER user_id;
```

- [ ] **Step 4: Apply the migration to the live database**

```bash
php -r '
require_once "femi9/billing/company/include/db-connect.php";
$res = $db_conn->query("SHOW COLUMNS FROM stock_lots LIKE \"warehouse_id\"");
if ($res->num_rows > 0) { echo "stock_lots: already has warehouse_id, skipping\n"; exit; }
echo $db_conn->query("ALTER TABLE stock_lots ADD COLUMN warehouse_id INT NULL AFTER user_id") ? "OK\n" : "ERROR: " . $db_conn->error . "\n";
'
```

Expected: `OK`.

- [ ] **Step 5: Verify row count unaffected**

```bash
php -r '
require_once "femi9/billing/company/include/db-connect.php";
$r = $db_conn->query("SELECT COUNT(*) c FROM stock_lots")->fetch_assoc();
echo "stock_lots: {$r["c"]} rows\n";
'
```

Expected: same count as before migration (61, per this session's earlier
check) — confirms the `ALTER` was non-destructive.

- [ ] **Step 6: Update `StockLots.php`**

Modify `femi9/billing/company/include/StockLots.php`:

```php
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
        ?string $createdBy,
        ?int $warehouseId = null
    ): int {
        if ($qty <= 0) {
            return 0;
        }
        $stmt = $db->prepare(
            "INSERT INTO stock_lots
                (product_id, user_type, user_id, warehouse_id, rate, qty_purchased,
                 qty_remaining, purchase_date, ref_type, ref_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'issidiissss',
            $productId, $userType, $userId, $warehouseId, $rate, $qty,
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
        callable $fallbackRateFn,
        ?int $warehouseId = null
    ): array {
        $consumed = [];
        $remaining = $qtyNeeded;

        $sql = "SELECT id, qty_remaining, rate FROM stock_lots
                WHERE product_id = ? AND user_type = ? AND user_id = ?
                  AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?') . "
                  AND qty_remaining > 0
                ORDER BY purchase_date ASC, id ASC
                FOR UPDATE";
        $stmt = $db->prepare($sql);
        if ($warehouseId === null) {
            $stmt->bind_param('iss', $productId, $userType, $userId);
        } else {
            $stmt->bind_param('issi', $productId, $userType, $userId, $warehouseId);
        }
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
```

`writeConsumption()` and `restoreConsumption()` are unchanged — leave
them exactly as they are (see Interfaces note above for why).

- [ ] **Step 7: Run test to verify it passes**

Run: `php "femi9/billing/includes/tests/StockLotsWarehouseKeyTest.php"`
Expected: `17 passed, 0 failed` (verified by actually running this test
against the fixed StockLots.php during plan authoring — all lines print
`PASS`).

- [ ] **Step 8: Syntax check**

```bash
php -l "femi9/billing/company/include/StockLots.php"
```
Expected: `No syntax errors detected`

- [ ] **Step 9: Commit**

```bash
git add "femi9/billing/db_migrations/2026_09_18_stock_lots_warehouse_key.sql" "femi9/billing/company/include/StockLots.php" "femi9/billing/includes/tests/StockLotsWarehouseKeyTest.php"
git commit -m "Add warehouse_id to stock_lots; thread through StockLots recordLot/consumeFifo"
```

---

### Task 2: Wire `$warehouseId` through StockService's 3 call sites

**Files:**
- Modify: `femi9/billing/company/include/StockService.php`

**Interfaces:**
- Consumes: `StockLots::recordLot()`/`consumeFifo()`'s new trailing
  `?int $warehouseId = null` parameter from Task 1.

This task has no new test file — Task 1's `StockLotsWarehouseKeyTest.php`
already proves `StockLots` itself is correct; this task is pure
call-site wiring, verified by re-running the full existing StockService
warehouse-key regression suite (Step 3 below), which already exercises
`deduct()`/`transferOut()`/`transferIn()` end-to-end.

- [ ] **Step 1: Update `deduct()`'s `consumeFifo` call (line 87)**

In `femi9/billing/company/include/StockService.php`, locate (inside
`deduct()`):

```php
            $consumed = StockLots::consumeFifo(
                $this->db, $productId, $userType, $userId, $qty,
                fn() => $this->fallbackRate($productId)
            );
```

Change to:

```php
            $consumed = StockLots::consumeFifo(
                $this->db, $productId, $userType, $userId, $qty,
                fn() => $this->fallbackRate($productId),
                $warehouseId
            );
```

- [ ] **Step 2: Update `transferOut()`'s `consumeFifo` call (line 718) and `transferIn()`'s `recordLot` call (line 784)**

Locate (inside `transferOut()`):

```php
            $consumed = StockLots::consumeFifo(
                $this->db, $productId, $userType, $userId, $qty,
                fn() => $this->fallbackRate($productId)
            );
```

Change to:

```php
            $consumed = StockLots::consumeFifo(
                $this->db, $productId, $userType, $userId, $qty,
                fn() => $this->fallbackRate($productId),
                $warehouseId
            );
```

Locate (inside `transferIn()`):

```php
            if ($lotRate !== null) {
                StockLots::recordLot(
                    $this->db, $productId, $userType, $userId, $lotRate, $qty,
                    date('Y-m-d'), 'transfer_in', $refId, $createdBy
                );
            }
```

Change to:

```php
            if ($lotRate !== null) {
                StockLots::recordLot(
                    $this->db, $productId, $userType, $userId, $lotRate, $qty,
                    date('Y-m-d'), 'transfer_in', $refId, $createdBy,
                    $warehouseId
                );
            }
```

All three call sites already have `$warehouseId` as a named parameter in
scope at that point in each method — no new variable derivation needed.

- [ ] **Step 3: Syntax check**

```bash
php -l "femi9/billing/company/include/StockService.php"
```
Expected: `No syntax errors detected`

- [ ] **Step 4: Run the full warehouse-key regression suite**

```bash
for f in femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php \
         femi9/billing/includes/tests/StockServiceReverseTransferInTest.php \
         femi9/billing/includes/tests/InputActionWarehouseKeyTest.php \
         femi9/billing/includes/tests/StockReturnUpdateWarehouseKeyTest.php \
         femi9/billing/includes/tests/InternalTransferWarehouseKeyTest.php \
         femi9/billing/includes/tests/OtSaleWarehouseKeyTest.php \
         femi9/billing/includes/tests/InvoiceStockUpdateWarehouseKeyTest.php \
         femi9/billing/includes/tests/TpInvoiceWarehouseKeyTest.php \
         femi9/billing/includes/tests/StockLotsWarehouseKeyTest.php; do
  echo "=== $f ==="
  php "$f" | tail -2
done
```

Expected: every file ends with `N passed, 0 failed` — this specifically
confirms Task 1/2's changes didn't regress any of the flows that call
`deduct()`/`transferOut()`/`transferIn()` indirectly (every prior phase's
tests exercise these methods, so a `consumeFifo`/`recordLot` signature
mismatch would surface here even without new lot-specific assertions in
those files).

- [ ] **Step 5: Commit**

```bash
git add "femi9/billing/company/include/StockService.php"
git commit -m "Thread warehouse_id through StockService's 3 StockLots call sites"
```

---

### Task 3: Full regression pass and verification

**Files:** none (verification only, no code changes)

- [ ] **Step 1: Confirm no unexpected file changes**

```bash
git status --short
```

Expected: clean, or only pre-existing unrelated files noted in earlier
phases — nothing from this plan left uncommitted.

- [ ] **Step 2: Confirm the two synthetic-holder call sites are genuinely untouched**

```bash
git diff HEAD~2 -- femi9/billing/company/llp-purchase-rate-action.php femi9/billing/company/neksomo-llp-piece-sale-action.php femi9/billing/company/neksomo-manufacturer-purchase-action.php
```

Expected: empty diff — confirms this plan did not modify any of the 3
call sites explicitly out of scope (Global Constraints).

- [ ] **Step 3: Report results**

No commit needed for this task — it's verification only. This closes
Phase 4 of the per-godown split-stock spec. Note in the final report to
the user: Phase 4 is now the last StockLots-adjacent piece; Phase 5
(reports, e.g. `overall-stock.php`'s `GROUP BY`-unaware query) remains
the only fully open phase from the original spec, plus the two
deliberately-deferred items (Neksomo manufacturer purchase's own
warehouse picker, and the separate composite-item/kit-assembly feature
idea raised during this session).

---

## Self-Review Notes

- **Spec coverage:** Phase 4's every bullet point (nullable `warehouse_id`
  on `stock_lots`, `recordLot()` gains the param, `consumeFifo()`'s
  lot-selection query scopes by it, existing lots default to NULL) has a
  corresponding step in Task 1. The spec's dependency note ("this phase
  depends on Phase 3 establishing which workflows actually pass a real
  godown") is satisfied — Add Input Stock, Internal Transfers, and all 5
  invoice flows already pass real `warehouseId` values into
  `deduct()`/`transferOut()`/`transferIn()`, which is exactly what Task 2
  wires through to `StockLots`.
- **Placeholder scan:** no TBD/TODO; all code blocks are complete,
  runnable PHP/SQL.
- **Type consistency:** `StockLots::recordLot()`/`consumeFifo()`
  signatures in Task 1 match their exact call shape in Task 2's
  `StockService.php` edits (trailing `?int $warehouseId = null` on both,
  positional — matches the convention already used for every other
  `?int $warehouseId = null` parameter added in Phases 1–3).
- **Out-of-scope call sites verified**: `llp-purchase-rate-action.php`
  and `neksomo-llp-piece-sale-action.php` both call `recordLot()` with
  8 positional args today (no warehouse); since the new 11th parameter is
  optional with a `null` default, these calls continue to compile and
  behave identically without any edit — correctly left alone per the
  Global Constraints, verified via Task 3 Step 2's diff check.
