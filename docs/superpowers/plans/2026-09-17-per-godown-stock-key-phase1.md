# Per-Godown Split Stock — Phase 1 (Schema + StockService Core) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend `stock`'s identity key to include `warehouse_id` and thread an optional `$warehouseId` parameter through every `StockService.php` method, with zero behavior change for any existing caller (all of whom keep passing no warehouse / `null`).

**Architecture:** `stock`'s unique key becomes `(product_id, user_type, user_id, warehouse_id)` with `warehouse_id` nullable (MySQL treats multiple `NULL`s in a unique key as distinct, so "unassigned" stays a stable single bucket per entity, matching today's behavior exactly). Every public/private method in `StockService.php` that locks, reads, or writes a `stock` row gains a trailing optional `?int $warehouseId = null` parameter, included in the relevant `WHERE`/`INSERT` clause. `stock_ledger` gains a matching nullable `warehouse_id` column so the audit trail records which godown (if any) a movement affected. No caller in the ~90 files that use `StockService` is touched in this phase — they all keep calling with the old argument lists, which resolve to `$warehouseId = null` and reproduce current behavior bit-for-bit.

**Tech Stack:** PHP 8 (mysqli, prepared statements), MySQL 8 (MAMP, socket `/Applications/MAMP/tmp/mysql/mysql.sock`), manual PHP test scripts (no PHPUnit in this repo) run via `php <file>`.

**Spec:** `docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md` — this plan implements that spec's "Phase 1 — Schema + StockService core" section only. Phases 2–5 (raw-SQL bypass workflows, wiring real godown selection into workflows, FIFO lot warehouse-awareness, reports) are explicitly out of scope for this plan and will get their own plans once Phase 1 ships.

## Global Constraints

- Every existing call site of `StockService`'s public methods must keep compiling and behaving identically — this phase adds an optional trailing parameter to each method, never a required one, never a reordering of existing parameters.
- `warehouse_id` is nullable everywhere it's added; `NULL` means "unassigned," matching all pre-existing stock.
- Follow the existing manual-test convention: a disposable `stock_service_test` database, dropped and recreated per run, using real (not simplified) `stock`/`stock_ledger` table shapes — see `femi9/billing/includes/tests/StockServiceReverseTransferInTest.php` for the pattern to match.
- Do not touch `StockLots.php`, `NeksomoStockBridge.php`, or any file outside `femi9/billing/company/include/StockService.php`, `femi9/billing/company/include/db-connect.php` (test only, read-only reuse), and the new migration file — this phase's blast radius is intentionally the smallest slice that's independently shippable.
- All new/changed PHP files must pass `php -l` before being considered done.

---

## Task 1: Migration — extend `stock`'s unique key, add `stock_ledger.warehouse_id`

**Files:**
- Create: `femi9/billing/db_migrations/2026_09_17_stock_warehouse_key.sql`

**Interfaces:**
- Consumes: existing `stock` table (has `warehouse_id` nullable column and `uq_stock_entity (product_id, user_type, user_id)` unique key, both already live per the 2026-09-17 warehouses migration).
- Produces: `stock.uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id)` unique key (replacing `uq_stock_entity`), and `stock_ledger.warehouse_id` nullable column — both consumed by Task 2's `StockService.php` changes.

- [ ] **Step 1: Write the migration file**

```sql
-- Extends stock's identity key to include warehouse_id, so the same
-- product/entity can have independent stock rows in different godowns.
-- NULL warehouse_id remains its own distinct identity in a MySQL unique
-- key (multiple NULLs don't collide), so every pre-existing stock row
-- keeps behaving exactly as it does today — this is purely additive.
--
-- Phase 1 of docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md
-- Applied: 2026-09-17

ALTER TABLE stock
  DROP INDEX uq_stock_entity,
  ADD UNIQUE KEY uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id);

-- Records which godown (if any) a stock_ledger movement affected, so the
-- audit trail stays warehouse-aware once StockService starts passing a
-- real warehouse_id.
ALTER TABLE stock_ledger
  ADD COLUMN warehouse_id INT NULL AFTER user_id,
  ADD INDEX idx_stock_ledger_warehouse (warehouse_id);
```

- [ ] **Step 2: Apply the migration to the local database**

Run:
```bash
cd "/Applications/MAMP/htdocs/Femi9 Billing Server"
DBPASS=$(grep '^DB_PASSWORD' femi9/billing/shared/.env | cut -d= -f2-)
/Applications/MAMP/Library/bin/mysql80/bin/mysql --socket=/Applications/MAMP/tmp/mysql/mysql.sock -uroot -p"$DBPASS" billing0femi9_billingapp < femi9/billing/db_migrations/2026_09_17_stock_warehouse_key.sql
```
Expected: no output (success). If you see `ERROR 1091 (42000): Can't DROP 'uq_stock_entity'; check that column/key exists`, the earlier 2026-09-17 warehouses migration wasn't applied to this database — stop and apply that one first.

- [ ] **Step 3: Verify the new key and column exist**

Run:
```bash
DBPASS=$(grep '^DB_PASSWORD' femi9/billing/shared/.env | cut -d= -f2-)
/Applications/MAMP/Library/bin/mysql80/bin/mysql --socket=/Applications/MAMP/tmp/mysql/mysql.sock -uroot -p"$DBPASS" billing0femi9_billingapp -e "SHOW INDEX FROM stock WHERE Key_name='uq_stock_entity_warehouse'; DESCRIBE stock_ledger;" 2>&1 | grep -v Warning
```
Expected: 4 rows for `uq_stock_entity_warehouse` (one per column: `product_id`, `user_type`, `user_id`, `warehouse_id`), and `stock_ledger`'s column list includes `warehouse_id` right after `user_id`.

- [ ] **Step 4: Commit**

```bash
git add femi9/billing/db_migrations/2026_09_17_stock_warehouse_key.sql
git commit -m "$(cat <<'EOF'
Extend stock's identity key to include warehouse_id (Phase 1)

Replaces uq_stock_entity (product_id, user_type, user_id) with
uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id)
so the same product/entity can eventually have independent stock rows
per godown. NULL warehouse_id is its own distinct identity in a MySQL
unique key, so every pre-existing row is unaffected.

Also adds stock_ledger.warehouse_id for audit-trail parity.

Phase 1 of docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: `StockService.php` — thread `?int $warehouseId` through the three key helper methods

**Files:**
- Modify: `femi9/billing/company/include/StockService.php:958-970` (`lockStockRow`)
- Modify: `femi9/billing/company/include/StockService.php:976-1004` (`updateStockSnapshot`)
- Modify: `femi9/billing/company/include/StockService.php:1010-1038` (`writeLedger`)
- Test: `femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php` (created in this task, extended in Task 3)

**Interfaces:**
- Consumes: `stock.uq_stock_entity_warehouse` and `stock_ledger.warehouse_id` from Task 1.
- Produces: `lockStockRow(int $productId, string $userType, string $userId, ?int $warehouseId = null): ?array`, `updateStockSnapshot(int $productId, string $userType, string $userId, array $fields, ?int $warehouseId = null): void`, `writeLedger(int $productId, string $userType, string $userId, string $action, int $qty, int $qtyBefore, int $qtyAfter, string $refType, string $refId, string $note, string $createdBy, ?int $warehouseId = null): int` — consumed by Task 3's public-method changes.

- [ ] **Step 1: Write the failing test**

Create `femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php`:

```php
<?php
// femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php
// Manual run: php StockServiceWarehouseKeyTest.php
//
// Regression/behavior test for Phase 1 of the per-godown split-stock spec
// (docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md):
// confirms StockService's private key helpers (lockStockRow,
// updateStockSnapshot, writeLedger) correctly scope by warehouse_id when
// given one, and reproduce today's exact behavior when given none (null).
//
// Uses a disposable `stock_service_test` schema — never the app's
// production database — with real `stock` / `stock_ledger` table shapes.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const STOCK_TEST_SCHEMA = 'stock_service_test';

$conn->query("DROP DATABASE IF EXISTS `" . STOCK_TEST_SCHEMA . "`");
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `" . STOCK_TEST_SCHEMA . "`")) {
    fwrite(STDERR, "FATAL: could not CREATE DATABASE `" . STOCK_TEST_SCHEMA . "` — " . $conn->error . "\n");
    exit(1);
}
if (!$conn->select_db(STOCK_TEST_SCHEMA)) {
    fwrite(STDERR, "FATAL: could not switch to schema `" . STOCK_TEST_SCHEMA . "` — " . $conn->error . "\n");
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

// ========== SETUP: real `stock` / `stock_ledger` table shapes, post-Phase-1 ==========
$conn->query("CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    opening_qty INT NOT NULL DEFAULT 0,
    opening_date DATE NOT NULL DEFAULT '2026-01-01',
    input_qty INT NOT NULL DEFAULT 0,
    sales_qty INT NOT NULL DEFAULT 0,
    sent_qty INT NOT NULL DEFAULT 0,
    returnqty INT NOT NULL DEFAULT 0,
    closing_qty INT NOT NULL DEFAULT 0,
    extra_pieces INT UNSIGNED NOT NULL DEFAULT 0,
    user_type VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    warehouse_id INT NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id)
)");

$conn->query("CREATE TABLE stock_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_type VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    warehouse_id INT NULL,
    action VARCHAR(50) NOT NULL,
    qty INT NOT NULL,
    qty_before INT NOT NULL,
    qty_after INT NOT NULL,
    ref_type VARCHAR(50) NOT NULL,
    ref_id VARCHAR(255) NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_by VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

// ---- Two rows for the SAME product/entity, different warehouses ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (14, 0, 300, 0, 0, 0, 300, 'company', '1', 101)");
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (14, 0, 200, 0, 0, 0, 200, 'company', '1', NULL)");

$reflection = new ReflectionClass('StockService');
$stockService = new StockService($conn);

function callPrivate($obj, string $method, array $args) {
    $ref = new ReflectionMethod($obj, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs($obj, $args);
}

// ---- lockStockRow: warehouse_id distinguishes the two rows ----
$rowWh101 = callPrivate($stockService, 'lockStockRow', [14, 'company', '1', 101]);
assertEqual((int)$rowWh101['closing_qty'], 300, 'lockStockRow(warehouse=101) reads the warehouse-101 row');

$rowNull = callPrivate($stockService, 'lockStockRow', [14, 'company', '1', null]);
assertEqual((int)$rowNull['closing_qty'], 200, 'lockStockRow(warehouse=null) reads the unassigned row, not the 101 row');

// ---- updateStockSnapshot: only touches the row matching warehouse_id ----
callPrivate($stockService, 'updateStockSnapshot', [14, 'company', '1', ['closing_qty' => 999], 101]);
$after101 = $conn->query("SELECT closing_qty FROM stock WHERE product_id=14 AND user_type='company' AND user_id='1' AND warehouse_id=101")->fetch_assoc();
$afterNull = $conn->query("SELECT closing_qty FROM stock WHERE product_id=14 AND user_type='company' AND user_id='1' AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$after101['closing_qty'], 999, 'updateStockSnapshot(warehouse=101) updates only the 101 row');
assertEqual((int)$afterNull['closing_qty'], 200, 'updateStockSnapshot(warehouse=101) leaves the unassigned row untouched');

// ---- writeLedger: records warehouse_id on the ledger row ----
$ledgerId = callPrivate($stockService, 'writeLedger', [14, 'company', '1', 'credit', 50, 200, 250, 'adjustment', 'ref-1', '', 'tester', 101]);
$ledgerRow = $conn->query("SELECT warehouse_id FROM stock_ledger WHERE id=$ledgerId")->fetch_assoc();
assertEqual((int)$ledgerRow['warehouse_id'], 101, 'writeLedger records the given warehouse_id');

$ledgerIdNull = callPrivate($stockService, 'writeLedger', [14, 'company', '1', 'credit', 50, 200, 250, 'adjustment', 'ref-2', '', 'tester', null]);
$ledgerRowNull = $conn->query("SELECT warehouse_id FROM stock_ledger WHERE id=$ledgerIdNull")->fetch_assoc();
assertEqual($ledgerRowNull['warehouse_id'], null, 'writeLedger(warehouse=null) records NULL, matching pre-Phase-1 behavior');

// ---- Backward compatibility: omitting the parameter entirely behaves like null ----
$rowOmitted = callPrivate($stockService, 'lockStockRow', [14, 'company', '1']);
assertEqual((int)$rowOmitted['closing_qty'], 200, 'lockStockRow with warehouse param omitted defaults to null (unassigned row)');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . STOCK_TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php "femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php"`
Expected: FAIL — `lockStockRow`, `updateStockSnapshot`, and `writeLedger` don't yet accept a 4th/12th argument, so PHP will throw `ArgumentCountError: Too few arguments to function StockService::lockStockRow(), 4 passed ... exactly 3 expected` (or similar for the other two methods). The important thing is it fails, not the exact message.

- [ ] **Step 3: Modify `lockStockRow`, `updateStockSnapshot`, `writeLedger`**

In `femi9/billing/company/include/StockService.php`, replace the three private helper methods:

```php
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
        ?int   $warehouseId = null
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO stock_ledger
                (product_id, user_type, user_id, warehouse_id, action, qty,
                 qty_before, qty_after, ref_type, ref_id, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'issisiiissss',
            $productId, $userType, $userId, $warehouseId, $action, $qty,
            $qtyBefore, $qtyAfter, $refType, $refId, $note, $createdBy
        );
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }
```

The `bind_param` type string `'issisiiissss'` matches the 12 parameters in order: `product_id`(i), `user_type`(s), `user_id`(s), `warehouse_id`(i), `action`(s), `qty`(i), `qty_before`(i), `qty_after`(i), `ref_type`(s), `ref_id`(s), `note`(s), `created_by`(s). `warehouse_id` binds as `i` even when the PHP value is `null` — mysqli sends SQL `NULL` through an `i`-typed placeholder correctly.

- [ ] **Step 4: Run test to verify it passes**

Run: `php "femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php"`
Expected: `8 passed, 0 failed` (all `assertEqual` lines PASS).

- [ ] **Step 5: Run `php -l` on the modified file**

Run: `php -l "femi9/billing/company/include/StockService.php"`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add femi9/billing/company/include/StockService.php femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php
git commit -m "$(cat <<'EOF'
StockService: thread optional warehouse_id through key helpers

lockStockRow, updateStockSnapshot, and writeLedger all gain a trailing
?int $warehouseId = null parameter, matching stock's new
uq_stock_entity_warehouse key. Every existing caller (still calling with
3-11 args, no warehouse) resolves to null, which the new SQL treats as
"warehouse_id IS NULL" — identical to today's single-row-per-entity
behavior, since NULL is its own distinct identity in the unique key.

Public methods (deduct, credit, transferOut, etc.) are not yet updated —
that's the next task. This task only touches the shared private helpers
so they're ready to receive a warehouse_id once callers pass one.

Phase 1 of docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: `StockService.php` — thread `?int $warehouseId` through all 14 public mutating methods + `getClosingQty`/`hasLedgerEntry`

**Files:**
- Modify: `femi9/billing/company/include/StockService.php` (all public methods: `deduct`, `credit`, `reverseDeduct`, `reverseCredit`, `deductAndCredit`, `reverseAll`, `hasLedgerEntry`, `getClosingQty`, `acceptReturn`, `rejectReturn`, `otDeduct`, `otReverse`, `transferOut`, `transferIn`, `reverseTransferOut`, `reverseTransferIn`)
- Test: `femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php` (extended)

**Interfaces:**
- Consumes: `lockStockRow(..., ?int $warehouseId = null)`, `updateStockSnapshot(..., ?int $warehouseId = null)`, `writeLedger(..., ?int $warehouseId = null)` from Task 2.
- Produces: every public method above gains a trailing `?int $warehouseId = null` parameter (added AFTER all existing parameters, including `$externalTransaction`, so existing positional call sites are unaffected). `credit()`'s and `transferIn()`'s row-creation `INSERT INTO stock` statements gain `warehouse_id` in their column list. This is what Task 4 (out of scope for this plan, future work) will pass real values into.

- [ ] **Step 1: Write the failing test (extend the same file from Task 2)**

Append to `femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php`, just before the `// ========== TEARDOWN ==========` line:

```php
// ---- Public API: deduct/credit correctly scope by warehouse_id ----
// Two independent stock pools for the same product/entity, different godowns.
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (20, 0, 100, 0, 0, 0, 100, 'company', '5', 201)");
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (20, 0, 50, 0, 0, 0, 50, 'company', '5', 202)");

// Deduct 30 from warehouse 201 only — warehouse 202's 50 units must be untouched.
$deductResult = $stockService->deduct(20, 'company', '5', 30, 'adjustment', 'wh-test-1', 'tester', false, 201);
assertEqual($deductResult['success'], true, 'deduct(warehouse=201) succeeds');
$wh201After = $conn->query("SELECT closing_qty FROM stock WHERE product_id=20 AND user_type='company' AND user_id='5' AND warehouse_id=201")->fetch_assoc();
$wh202After = $conn->query("SELECT closing_qty FROM stock WHERE product_id=20 AND user_type='company' AND user_id='5' AND warehouse_id=202")->fetch_assoc();
assertEqual((int)$wh201After['closing_qty'], 70, 'deduct(warehouse=201) reduces only the 201 row (100 -> 70)');
assertEqual((int)$wh202After['closing_qty'], 50, 'deduct(warehouse=201) leaves the 202 row untouched (still 50)');

// Deducting more than warehouse 201 has left (70) must fail even though
// warehouse 202 + 201 combined would cover it — no cross-warehouse fallback.
try {
    $stockService->deduct(20, 'company', '5', 71, 'adjustment', 'wh-test-2', 'tester', false, 201);
    assertEqual('no exception thrown', 'StockException', 'deduct(warehouse=201, qty=71) throws StockException (insufficient in that warehouse alone)');
} catch (StockException $e) {
    assertEqual(true, true, 'deduct(warehouse=201, qty=71) throws StockException (insufficient in that warehouse alone)');
}

// credit() with a warehouse_id on a brand-new product/entity/warehouse combo
// creates a row carrying that warehouse_id.
$creditResult = $stockService->credit(21, 'company', '5', 40, 'adjustment', 'wh-test-3', 'tester', false, 301);
assertEqual($creditResult['success'], true, 'credit(warehouse=301) on new row succeeds');
$newRow = $conn->query("SELECT closing_qty, warehouse_id FROM stock WHERE product_id=21 AND user_type='company' AND user_id='5' AND warehouse_id=301")->fetch_assoc();
assertEqual((int)$newRow['closing_qty'], 40, 'credit(warehouse=301) created row has correct closing_qty');
assertEqual((int)$newRow['warehouse_id'], 301, 'credit(warehouse=301) created row carries the warehouse_id');

// Backward compatibility: calling deduct/credit with NO warehouse arg at all
// (today's exact call shape, 8 positional args) must still work unchanged.
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (22, 0, 10, 0, 0, 0, 10, 'company', '5', NULL)");
$legacyDeduct = $stockService->deduct(22, 'company', '5', 5, 'adjustment', 'wh-test-4', 'tester', false);
assertEqual($legacyDeduct['success'], true, 'deduct() called with the pre-Phase-1 8-argument shape still succeeds');
$legacyRow = $conn->query("SELECT closing_qty FROM stock WHERE product_id=22 AND user_type='company' AND user_id='5' AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$legacyRow['closing_qty'], 5, 'deduct() with no warehouse arg updates the unassigned row exactly as before');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php "femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php"`
Expected: FAIL on the `deduct(warehouse=201)` assertions — `deduct()` doesn't yet accept a 9th argument, so PHP throws `ArgumentCountError`. (The last block, calling `deduct()` with the old 8-arg shape, would actually still pass even before this task's changes — that's expected and fine; it's the earlier 9-arg calls that must fail first.)

- [ ] **Step 3: Add `?int $warehouseId = null` to every public method, passed through to the private helpers**

In `femi9/billing/company/include/StockService.php`, for **each** of the 14 public mutating methods plus `getClosingQty` and `hasLedgerEntry`, add a trailing parameter and thread it through. Below is the exact diff shape for every method — apply the same pattern to each one named.

**`deduct()`** — add parameter, pass to `lockStockRow`, `updateStockSnapshot`, `writeLedger`:
```php
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
                fn() => $this->fallbackRate($productId)
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
```
(Note: `StockLots::consumeFifo`/`fallbackRate` calls are untouched — FIFO lot warehouse-awareness is Phase 4, out of scope here.)

**`credit()`** — add parameter, thread through, and add `warehouse_id` to the row-creation INSERT:
```php
    public function credit(
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
```
Note the `bind_param` type string changed from `'iiiss'` (5 params: productId, qty, qty, userType, userId) to `'iiissi'` (6 params: productId, qty, qty, userType, userId, warehouseId) — double check parameter order matches the `?`s in the SQL exactly: `product_id`(i), `input_qty`(i), `closing_qty`(i), `user_type`(s), `user_id`(s), `warehouse_id`(i) → `iiissi`.

**`reverseDeduct()`** — add parameter, pass to `lockStockRow`/`updateStockSnapshot`/`writeLedger`:
```php
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
```

**`reverseCredit()`** — same pattern:
```php
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
```

**`deductAndCredit()`** — add two independent optional warehouse params (seller's warehouse and buyer's warehouse are different entities, so they need separate values), pass to the internal `deduct`/`credit` calls:
```php
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
```

**`reverseAll()`** — this one aggregates from `stock_ledger` GROUP BY; add `warehouse_id` to the GROUP BY and thread it through to the `reverseDeduct`/`reverseCredit` calls it makes internally:
```php
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
```

**`hasLedgerEntry()`** — no `stock` row access, only reads `stock_ledger` filtered by `ref_type`/`ref_id` (no product/warehouse in its WHERE at all) — leave unchanged. No edit needed for this method.

**`getClosingQty()`** — add parameter, pass to the SELECT:
```php
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
        $real = $row ? (int)$row['closing_qty'] : null;

        $pool = $this->neksomoPoolAvailable($productId, $userType, $userId);
        if ($pool <= 0) return $real;
        return ($real ?? 0) + $pool;
    }
```
(`neksomoPoolAvailable` itself is intentionally left warehouse-blind — the Neksomo shared pool is a separate bookkeeping bridge, not a `stock` row, and is out of scope for this phase.)

**`acceptReturn()`**:
```php
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
```

**`rejectReturn()`**:
```php
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
```

**`otDeduct()`**:
```php
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
```

**`otReverse()`**:
```php
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
```

**`transferOut()`**:
```php
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
                fn() => $this->fallbackRate($productId)
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
```

**`transferIn()`** — this one already has an optional `?float $lotRate = null` as its last parameter; add `$warehouseId` AFTER it so existing positional calls (which stop at `$externalTransaction` or `$lotRate`) are unaffected:
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
                    date('Y-m-d'), 'transfer_in', $refId, $createdBy
                );
            }

            if (!$externalTransaction) $this->db->commit();
            return ['success' => true, 'ledger_id' => $ledgerId, 'qty_after' => $after];
        } catch (\Throwable $e) {
            if (!$externalTransaction) $this->db->rollback();
            throw $e;
        }
    }
```

**`reverseTransferOut()`**:
```php
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
```

**`reverseTransferIn()`**:
```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php "femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php"`
Expected: `17 passed, 0 failed` (8 from Task 2's assertions + 9 new ones from this task — count may vary slightly depending on exact assertion lines added, but failCount must be 0).

- [ ] **Step 5: Run the pre-existing regression test to confirm no behavior changed**

Run: `php "femi9/billing/includes/tests/StockServiceReverseTransferInTest.php"`
Expected: `5 passed, 0 failed` — this test never passes a `$warehouseId`, so it must produce byte-identical results to before this task's changes (this is the direct verification of "zero behavior change for existing callers").

- [ ] **Step 6: Run `php -l` on the modified file**

Run: `php -l "femi9/billing/company/include/StockService.php"`
Expected: `No syntax errors detected`

- [ ] **Step 7: Commit**

```bash
git add femi9/billing/company/include/StockService.php femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php
git commit -m "$(cat <<'EOF'
StockService: thread optional warehouse_id through all public methods

All 14 public mutating methods (deduct, credit, reverseDeduct,
reverseCredit, deductAndCredit, reverseAll, acceptReturn, rejectReturn,
otDeduct, otReverse, transferOut, transferIn, reverseTransferOut,
reverseTransferIn) plus getClosingQty gain a trailing optional
?int $warehouseId parameter (deductAndCredit gets two — sellerWarehouseId
and buyerWarehouseId — since seller and buyer are different entities).

Every parameter is added after all existing parameters, so every one of
the ~90 files that call StockService today keeps compiling and behaving
identically — they all resolve to warehouseId=null, which the underlying
helpers (from the previous commit) treat as "warehouse_id IS NULL",
matching every pre-existing stock row exactly.

hasLedgerEntry() is unchanged — it never touches a stock row, only
stock_ledger filtered by ref_type/ref_id.

Verified via femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php
(new warehouse-scoping assertions) and the pre-existing
StockServiceReverseTransferInTest.php (unchanged pass, confirming no
behavior regression for callers that don't pass a warehouse).

Phase 1 of docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Full regression pass — confirm every existing StockService call site still behaves correctly

**Files:**
- None modified — this task is verification only.

**Interfaces:**
- Consumes: the completed Task 3 `StockService.php`.
- Produces: confidence that Phase 1 is safe to consider done; nothing for later tasks to consume (this is the last task in this plan).

- [ ] **Step 1: Re-run both test files together**

Run:
```bash
php "femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php" && \
php "femi9/billing/includes/tests/StockServiceReverseTransferInTest.php"
```
Expected: both exit 0, all assertions PASS, `0 failed` in both.

- [ ] **Step 2: Confirm no other file in the repo was modified**

Run: `git status --short`
Expected: clean working tree (everything from Tasks 1–3 already committed) — confirms this phase touched exactly the migration file, `StockService.php`, and the new test file, nothing in the ~90 caller files.

- [ ] **Step 3: Verify the live database matches the expected post-migration shape**

Run:
```bash
DBPASS=$(grep '^DB_PASSWORD' femi9/billing/shared/.env | cut -d= -f2-)
/Applications/MAMP/Library/bin/mysql80/bin/mysql --socket=/Applications/MAMP/tmp/mysql/mysql.sock -uroot -p"$DBPASS" billing0femi9_billingapp -e "SELECT COUNT(*) AS total_stock_rows FROM stock; SHOW INDEX FROM stock WHERE Key_name IN ('uq_stock_entity','uq_stock_entity_warehouse');" 2>&1 | grep -v Warning
```
Expected: `uq_stock_entity` no longer appears (it was dropped in Task 1); `uq_stock_entity_warehouse` appears with 4 rows; `total_stock_rows` matches whatever the row count was before this plan started (Phase 1 doesn't insert/delete any real stock rows, only changes the key and adds a nullable column already present from the earlier warehouses migration).

- [ ] **Step 4: No commit needed for this task** — it's verification-only. If any check in Steps 1–3 fails, stop and fix the root cause in the relevant earlier task rather than patching around it here.

---

## What this plan deliberately does not do

Per the spec's phase boundaries, the following are explicitly out of scope and will be their own future plans:

- Passing a real (non-null) `$warehouseId` from any actual caller (Add Input Stock, internal transfers, invoice submission, etc.) — Phase 3 in the spec.
- Fixing the two raw-SQL bypass workflows (`input-action.php`, `stock_return_update.php`) that mutate `stock` directly without going through `StockService` — Phase 2 in the spec.
- Any change to `StockLots.php` / FIFO cost-lot consumption — Phase 4 in the spec.
- Any change to `overall-stock.php` or other reports — Phase 5 in the spec.

After this plan ships, `stock.warehouse_id` and `StockService`'s new parameter exist and are proven correct, but nothing in the live application passes a non-null warehouse yet — behavior is unchanged for real users until Phase 2/3 land.
