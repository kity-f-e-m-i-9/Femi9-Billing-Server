# Per-Godown Split Stock — Phase 2 (Raw-SQL Bypass Workflows) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the two workflows that mutate the `stock` table directly with raw SQL instead of going through `StockService` — `input-action.php` (Add Input Stock) and `stock_return_update.php` (Stock Return) — so their queries correctly scope by `warehouse_id` now that `stock`'s unique key includes it (Phase 1, shipped in commits `b425612`/`3623334`/`ca045e4`). Both keep defaulting to the "unassigned" (`warehouse_id IS NULL`) row, so behavior for real users is unchanged — this closes a latent correctness gap, not a visible feature.

**Architecture:** Both files currently run 3–4 raw prepared statements against `stock` keyed only on `(product_id, user_type, user_id)`. Since Phase 1 made `warehouse_id` part of `stock`'s identity, any of those statements that omit it will now silently match/create the wrong row (or, if multiple rows exist for the same product/entity across different warehouses, match ambiguously) once a later phase starts writing non-`NULL` warehouse rows. Fixing this now — while every real row's `warehouse_id` is still `NULL` — is safe and has no visible effect, but closes the gap before Phase 3 adds any real godown selection. Every changed statement gets `AND warehouse_id IS NULL` (or `= ?` if a future caller ever passes one, though neither file gains new UI in this phase — see Global Constraints).

**Tech Stack:** PHP 8 (mysqli, prepared statements), MySQL 8 (MAMP, socket `/Applications/MAMP/tmp/mysql/mysql.sock`), manual PHP test scripts (no PHPUnit in this repo) run via `php <file>`.

**Spec:** `docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md` — this plan implements the first two bullets of that spec's "Phase 2 — Raw-SQL bypass workflows" section (`input-action.php`, `stock_return_update.php`). The spec's third bullet (Neksomo `extra_pieces` in `neksomo-manufacturer-purchase-action.php`) is explicitly deferred to Phase 3, per the spec's own wording ("needs `warehouse_id` added to its WHERE once whole-pack `credit()` calls start passing a real godown") — nothing calls that code path with a non-null warehouse yet, so there is no bug to fix there today.

## Global Constraints

- No UI changes in this phase. Neither `add-input.php` nor `add-return.php`/`stock_return_update.php` gains a godown picker here — that's Phase 3 ("wire real godown selection into workflows"). This phase only makes the existing SQL correct for the schema Phase 1 introduced.
- Every fixed statement must default to matching/creating the `warehouse_id IS NULL` row, exactly reproducing today's behavior for the 100% of real traffic that has no warehouse concept yet.
- Follow the existing manual-test convention: a disposable test database, dropped and recreated per run, using real (not simplified) table shapes for every table the code under test touches.
- All new/changed PHP files must pass `php -l` before being considered done.
- Do not touch `StockService.php`, `StockLots.php`, or `neksomo-manufacturer-purchase-action.php` in this phase.

---

## Task 1: Fix `input-action.php`'s four raw `stock` statements

**Files:**
- Modify: `femi9/billing/company/input-action.php:152-207`
- Test: `femi9/billing/includes/tests/InputActionWarehouseKeyTest.php` (new)

**Interfaces:**
- Consumes: `stock.uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id)` from Phase 1.
- Produces: nothing consumed by a later task in this plan — this task is self-contained. (Phase 3 will later add a real warehouse picker to `add-input.php` and pass a non-null value into the fixed statements here.)

- [ ] **Step 1: Write the failing test**

Create `femi9/billing/includes/tests/InputActionWarehouseKeyTest.php`. This test does not `require` `input-action.php` directly (that file is a direct-execution script gated on `$_SERVER['REQUEST_METHOD'] === 'POST'` and session state, not a reusable function) — instead it replicates the file's exact SQL statements against a disposable schema, proving the fix's correctness at the SQL level, the same logic that will run when the real script executes those statements.

```php
<?php
// femi9/billing/includes/tests/InputActionWarehouseKeyTest.php
// Manual run: php InputActionWarehouseKeyTest.php
//
// Regression/behavior test for Phase 2 of the per-godown split-stock spec
// (docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md):
// confirms input-action.php's four raw `stock` statements (stmtChkProd,
// stmtInsertStock, stmtGetStock, stmtUpdateStock) correctly scope by
// warehouse_id now that it's part of stock's unique key, instead of
// silently matching/reusing whichever row happens to exist for
// (product_id, user_type, user_id) regardless of warehouse.
//
// Mirrors input-action.php's statements verbatim (not a require of the
// script itself, which is a direct-execution POST handler gated on
// session state) — this proves the SQL shape is correct against a
// disposable `input_action_test` schema with real table shapes.

require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const TEST_SCHEMA = 'input_action_test';

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

// ========== SETUP: real `stock` table shape, post-Phase-1 ==========
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

// ---- Fixture: a product/entity that ALREADY has a warehouse-tagged row
// (e.g. created directly via the Manage Godowns flow or a future Phase 3
// caller), plus separately the "unassigned" row Add Input Stock must use. ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (30, 0, 500, 0, 0, 0, 500, 'company', '1', 999)");

// ---- Replicate input-action.php's exact statement shapes, POST-FIX ----
// (This block is what Step 3 makes real in input-action.php itself.)
function runInputActionFlow($conn, int $pid, string $userType, string $userId, int $qty, string $inputDate) {
    $stmtChkProd = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL
         FOR UPDATE"
    );
    $stmtChkProd->bind_param('iss', $pid, $userType, $userId);
    $stmtChkProd->execute();
    $cntProd = (int) $stmtChkProd->get_result()->fetch_assoc()['cnt'];
    $stmtChkProd->close();

    if ($cntProd === 0) {
        $stmtInsertStock = $conn->prepare(
            "INSERT INTO stock
                 (product_id, opening_qty, opening_date, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
             VALUES (?, 0, ?, 0, 0, 0, 0, 0, ?, ?, NULL)"
        );
        $stmtInsertStock->bind_param('issi', $pid, $inputDate, $userType, $userId);
        $stmtInsertStock->execute();
        $stmtInsertStock->close();
    }

    $stmtGetStock = $conn->prepare(
        "SELECT input_qty, closing_qty FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL
         FOR UPDATE"
    );
    $stmtGetStock->bind_param('iss', $pid, $userType, $userId);
    $stmtGetStock->execute();
    $stockRow = $stmtGetStock->get_result()->fetch_assoc();
    $stmtGetStock->close();

    $newInputQty   = (int) $stockRow['input_qty']   + $qty;
    $newClosingQty = (int) $stockRow['closing_qty'] + $qty;

    $stmtUpdateStock = $conn->prepare(
        "UPDATE stock SET input_qty = ?, closing_qty = ?
         WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL"
    );
    $stmtUpdateStock->bind_param('iiiss', $newInputQty, $newClosingQty, $pid, $userType, $userId);
    $stmtUpdateStock->execute();
    $stmtUpdateStock->close();
}

// ---- Test: Add Input Stock for a product that has a warehouse-tagged row
// (999) but NOT yet an unassigned row must create the unassigned row,
// leaving warehouse 999's row completely untouched. ----
runInputActionFlow($conn, 30, 'company', '1', 50, '2026-09-17');

$wh999 = $conn->query("SELECT closing_qty FROM stock WHERE product_id=30 AND user_type='company' AND user_id='1' AND warehouse_id=999")->fetch_assoc();
$unassigned = $conn->query("SELECT closing_qty FROM stock WHERE product_id=30 AND user_type='company' AND user_id='1' AND warehouse_id IS NULL")->fetch_assoc();

assertEqual((int)$wh999['closing_qty'], 500, 'Add Input Stock leaves an existing warehouse-999 row completely untouched');
assertEqual($unassigned !== null, true, 'Add Input Stock creates a separate unassigned row rather than reusing warehouse 999\'s row');
assertEqual((int)$unassigned['closing_qty'], 50, 'Add Input Stock unassigned row has the correct closing_qty (50)');

// ---- Test: a SECOND Add Input Stock for the same product correctly
// accumulates onto the unassigned row (not warehouse 999's), fixing the
// exact bug caught during Phase-0 prototyping (see spec Phase 2 notes). ----
runInputActionFlow($conn, 30, 'company', '1', 25, '2026-09-17');

$wh999After2nd = $conn->query("SELECT closing_qty FROM stock WHERE product_id=30 AND user_type='company' AND user_id='1' AND warehouse_id=999")->fetch_assoc();
$unassignedAfter2nd = $conn->query("SELECT closing_qty FROM stock WHERE product_id=30 AND user_type='company' AND user_id='1' AND warehouse_id IS NULL")->fetch_assoc();

assertEqual((int)$wh999After2nd['closing_qty'], 500, 'Second Add Input Stock still leaves warehouse-999 row untouched');
assertEqual((int)$unassignedAfter2nd['closing_qty'], 75, 'Second Add Input Stock correctly accumulates onto the unassigned row (50 + 25 = 75)');

// ---- Test: brand-new product with no existing rows at all creates
// exactly one unassigned row. ----
runInputActionFlow($conn, 31, 'company', '1', 10, '2026-09-17');
$newProductRows = $conn->query("SELECT COUNT(*) AS c FROM stock WHERE product_id=31 AND user_type='company' AND user_id='1'")->fetch_assoc();
$newProductRow = $conn->query("SELECT closing_qty, warehouse_id FROM stock WHERE product_id=31 AND user_type='company' AND user_id='1'")->fetch_assoc();
assertEqual((int)$newProductRows['c'], 1, 'Brand-new product creates exactly one stock row');
assertEqual($newProductRow['warehouse_id'], null, 'Brand-new product\'s row has warehouse_id NULL (unassigned)');
assertEqual((int)$newProductRow['closing_qty'], 10, 'Brand-new product\'s row has the correct closing_qty');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php "femi9/billing/includes/tests/InputActionWarehouseKeyTest.php"`
Expected: The test's own `runInputActionFlow()` helper already contains the FIXED SQL shape (with `AND warehouse_id IS NULL`), so this test currently exercises correct logic, not the actual (still-buggy) `input-action.php`. Run it now anyway to confirm the helper itself is internally consistent — expected `10 passed, 0 failed`. This is intentionally a "test the fix logic first" step, since `input-action.php` is a direct-execution script that can't be required and driven by test fixtures the way `StockService`'s class methods could in Phase 1. The real regression check that the ACTUAL file matches this logic happens in Step 4 below (manual code diff review) and Step 5 (`php -l` + reading the diff).

- [ ] **Step 3: Fix `input-action.php`'s four statements**

In `femi9/billing/company/input-action.php`, replace the four prepared-statement declarations:

```php
    $stmtChkProd = $db_conn->prepare(
        "SELECT COUNT(*) AS cnt FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL
         FOR UPDATE"                                     // lock row during transaction
    );

    $stmtInsertStock = $db_conn->prepare(
        "INSERT INTO stock
             (product_id, opening_qty, opening_date, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
         VALUES (?, 0, ?, 0, 0, 0, 0, 0, ?, ?, NULL)"
    );

    $stmtGetStock = $db_conn->prepare(
        "SELECT input_qty, closing_qty FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL
         FOR UPDATE"
    );

    $stmtUpdateStock = $db_conn->prepare(
        "UPDATE stock SET input_qty = ?, closing_qty = ?
         WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL"
    );
```

None of the four statements' `bind_param` calls change — they still bind exactly the same placeholders in the same order (`WHERE ... = ? AND ... = ? AND ... = ?` for the three lookups, `VALUES (?, 0, ?, ..., ?, ?)` for the insert); only the literal SQL text changes to add `AND warehouse_id IS NULL` (a literal, not a placeholder, since this phase never passes a warehouse — see Global Constraints).

- [ ] **Step 4: Review the diff against the test's `runInputActionFlow()` helper**

Run: `git diff femi9/billing/company/input-action.php`
Expected: the four statement bodies in the diff match, clause-for-clause, the four statement bodies inside `runInputActionFlow()` in the test file from Step 1. This confirms the test's already-correct logic and the real file's newly-fixed logic are the same SQL shape.

- [ ] **Step 5: Run `php -l` and the test**

Run:
```bash
php -l "femi9/billing/company/input-action.php"
php "femi9/billing/includes/tests/InputActionWarehouseKeyTest.php"
```
Expected: `No syntax errors detected`, then `10 passed, 0 failed`.

- [ ] **Step 6: Commit**

```bash
git add femi9/billing/company/input-action.php femi9/billing/includes/tests/InputActionWarehouseKeyTest.php
git commit -m "$(cat <<'EOF'
Fix Add Input Stock to scope by warehouse_id (Phase 2)

input-action.php's four raw stock statements (stmtChkProd,
stmtInsertStock, stmtGetStock, stmtUpdateStock) were keyed only on
(product_id, user_type, user_id) — now that Phase 1 made warehouse_id
part of stock's unique key, that omission meant a second Add Input Stock
submission for the same product would silently match/reuse whichever
row happened to exist, regardless of its warehouse_id. All four now add
"AND warehouse_id IS NULL" / "warehouse_id = NULL" respectively, matching
the one behavior this workflow has today (no godown picker exists yet —
that's Phase 3) and correctly leaving any warehouse-tagged row untouched.

No UI change — add-input.php gains no godown picker in this phase.

Phase 2 of docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Fix `stock_return_update.php`'s two raw `stock` statements

**Files:**
- Modify: `femi9/billing/company/stock_return_update.php:67-97`
- Test: `femi9/billing/includes/tests/StockReturnUpdateWarehouseKeyTest.php` (new)

**Interfaces:**
- Consumes: `stock.uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id)` from Phase 1.
- Produces: nothing consumed by a later task in this plan — self-contained, same shape as Task 1.

**Note on this workflow's semantics** (resolves the spec's open question about Stock Return godown inference): `$godownid` in this file is the **company entity** id (`user_id` — LLP/Healthcare/Neksomo), not a physical warehouse. Nothing in the current codebase assigns a real `warehouse_id` to any stock row, so this fix — like Task 1 — only needs to stop being ambiguous under the new key, not infer or look up any prior movement's godown. That inference question is deferred to whichever Phase 3 workflow first lets a real godown reach `stock`.

- [ ] **Step 1: Write the failing test**

Create `femi9/billing/includes/tests/StockReturnUpdateWarehouseKeyTest.php`:

```php
<?php
// femi9/billing/includes/tests/StockReturnUpdateWarehouseKeyTest.php
// Manual run: php StockReturnUpdateWarehouseKeyTest.php
//
// Regression/behavior test for Phase 2 of the per-godown split-stock spec
// (docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md):
// confirms stock_return_update.php's two raw `stock` statements (the
// SELECT ... FOR UPDATE lock and the UPDATE) correctly scope by
// warehouse_id now that it's part of stock's unique key.
//
// Mirrors stock_return_update.php's statements verbatim (not a require of
// the script itself, which is a direct-execution POST handler gated on
// $_REQUEST['add-record'] and session state) — this proves the SQL shape
// is correct against a disposable `stock_return_test` schema with real
// table shapes.

require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const TEST_SCHEMA = 'stock_return_test';

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

// ========== SETUP: real `stock` table shape, post-Phase-1 ==========
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

// ---- Fixture: a product/entity with BOTH a warehouse-tagged row and the
// unassigned row the return must actually touch. ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (40, 0, 0, 0, 0, 0, 300, 'company', '1', 777)");
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (40, 0, 0, 0, 0, 0, 100, 'company', '1', NULL)");

// ---- Replicate stock_return_update.php's exact statement shapes, POST-FIX ----
function runStockReturnFlow($conn, int $prid, string $userType, string $userId, int $returnQty) {
    $s = $conn->prepare(
        "SELECT returnqty, closing_qty FROM stock
          WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL
          FOR UPDATE"
    );
    $s->bind_param('iss', $prid, $userType, $userId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$row) {
        throw new \RuntimeException("Stock row not found for product=$prid");
    }

    $newReturnQty  = (int)$row['returnqty']   + $returnQty;
    $newClosingQty = (int)$row['closing_qty'] - $returnQty;

    if ($newClosingQty < 0) {
        throw new \RuntimeException("Insufficient stock for company return");
    }

    $s = $conn->prepare(
        "UPDATE stock SET returnqty = ?, closing_qty = ?, updated_at = NOW()
          WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL"
    );
    $s->bind_param('iiiss', $newReturnQty, $newClosingQty, $prid, $userType, $userId);
    $s->execute();
    $s->close();

    return ['before' => (int)$row['closing_qty'], 'after' => $newClosingQty];
}

// ---- Test: return 30 units — must hit the unassigned row (100 -> 70),
// leaving the warehouse-777 row (300) completely untouched. ----
$result = runStockReturnFlow($conn, 40, 'company', '1', 30);
assertEqual($result['before'], 100, 'Stock return reads the unassigned row\'s closing_qty (100), not warehouse 777\'s (300)');
assertEqual($result['after'], 70, 'Stock return correctly computes new closing_qty (100 - 30 = 70)');

$wh777 = $conn->query("SELECT closing_qty, returnqty FROM stock WHERE product_id=40 AND user_type='company' AND user_id='1' AND warehouse_id=777")->fetch_assoc();
$unassigned = $conn->query("SELECT closing_qty, returnqty FROM stock WHERE product_id=40 AND user_type='company' AND user_id='1' AND warehouse_id IS NULL")->fetch_assoc();

assertEqual((int)$wh777['closing_qty'], 300, 'Warehouse-777 row is completely untouched after the return');
assertEqual((int)$wh777['returnqty'], 0, 'Warehouse-777 row\'s returnqty is completely untouched');
assertEqual((int)$unassigned['closing_qty'], 70, 'Unassigned row\'s closing_qty correctly reduced');
assertEqual((int)$unassigned['returnqty'], 30, 'Unassigned row\'s returnqty correctly incremented');

// ---- Test: a product with ONLY a warehouse-tagged row (no unassigned
// row at all) must fail loudly, not silently match the wrong warehouse. ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (41, 0, 0, 0, 0, 0, 50, 'company', '1', 888)");
try {
    runStockReturnFlow($conn, 41, 'company', '1', 10);
    assertEqual('no exception thrown', 'RuntimeException', 'Return against a product with only a warehouse-tagged row throws (no ambiguous match)');
} catch (\RuntimeException $e) {
    assertEqual(true, true, 'Return against a product with only a warehouse-tagged row throws (no ambiguous match)');
}
$wh888Unchanged = $conn->query("SELECT closing_qty FROM stock WHERE product_id=41 AND user_type='company' AND user_id='1' AND warehouse_id=888")->fetch_assoc();
assertEqual((int)$wh888Unchanged['closing_qty'], 50, 'Warehouse-888 row is untouched after the refused return');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify the helper logic is internally consistent**

Run: `php "femi9/billing/includes/tests/StockReturnUpdateWarehouseKeyTest.php"`
Expected: `9 passed, 0 failed` — same reasoning as Task 1 Step 2: this test's `runStockReturnFlow()` helper already contains the fixed SQL, proving the fix logic is correct. The real file gets fixed next.

- [ ] **Step 3: Fix `stock_return_update.php`'s two statements**

In `femi9/billing/company/stock_return_update.php`, replace the lock-and-read statement:

```php
    // Lock the stock row
    $s = $db_conn->prepare(
        "SELECT returnqty, closing_qty FROM stock
          WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL
          FOR UPDATE"
    );
    $s->bind_param('iss', $prid, $Login_user_TYPEvl, $godownid);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
```

And the update statement:

```php
    $s = $db_conn->prepare(
        "UPDATE stock SET returnqty = ?, closing_qty = ?, updated_at = NOW()
          WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL"
    );
    $s->bind_param('iiiss', $newReturnQty, $newClosingQty, $prid, $Login_user_TYPEvl, $godownid);
    $s->execute();
    $s->close();
```

(Only the literal SQL text changes — `bind_param` calls and every surrounding line stay identical.)

- [ ] **Step 4: Review the diff against the test's `runStockReturnFlow()` helper**

Run: `git diff femi9/billing/company/stock_return_update.php`
Expected: the two statement bodies in the diff match, clause-for-clause, the two statement bodies inside `runStockReturnFlow()` in the test file from Step 1.

- [ ] **Step 5: Run `php -l` and the test**

Run:
```bash
php -l "femi9/billing/company/stock_return_update.php"
php "femi9/billing/includes/tests/StockReturnUpdateWarehouseKeyTest.php"
```
Expected: `No syntax errors detected`, then `9 passed, 0 failed`.

- [ ] **Step 6: Commit**

```bash
git add femi9/billing/company/stock_return_update.php femi9/billing/includes/tests/StockReturnUpdateWarehouseKeyTest.php
git commit -m "$(cat <<'EOF'
Fix Stock Return to scope by warehouse_id (Phase 2)

stock_return_update.php's SELECT...FOR UPDATE lock and UPDATE statement
were keyed only on (product_id, user_type, user_id) — now that Phase 1
made warehouse_id part of stock's unique key, a product with more than
one stock row for the same entity (e.g. one warehouse-tagged, one
unassigned) could match the wrong row. Both statements now add
"AND warehouse_id IS NULL", matching the one behavior this workflow has
today (godownid here is the company entity, not a physical warehouse —
nothing assigns a real warehouse_id yet) and failing loudly instead of
silently matching an unrelated warehouse's row if one ever exists.

No UI change — add-return.php gains no godown picker in this phase.

Phase 2 of docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Full regression pass — confirm every Phase 1 + Phase 2 test still passes together

**Files:**
- None modified — this task is verification only.

**Interfaces:**
- Consumes: all tests from Phase 1 (`StockServiceWarehouseKeyTest.php`, `StockServiceReverseTransferInTest.php`) and this phase's two new tests.
- Produces: confidence that Phase 2 is safe to consider done.

- [ ] **Step 1: Run all four test files**

Run:
```bash
php "femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php" && \
php "femi9/billing/includes/tests/StockServiceReverseTransferInTest.php" && \
php "femi9/billing/includes/tests/InputActionWarehouseKeyTest.php" && \
php "femi9/billing/includes/tests/StockReturnUpdateWarehouseKeyTest.php"
```
Expected: all four exit 0, `0 failed` in each.

- [ ] **Step 2: Confirm no other file in the repo was modified**

Run: `git status --short`
Expected: clean working tree (everything from Tasks 1–2 already committed) — confirms this phase touched exactly `input-action.php`, `stock_return_update.php`, and the two new test files.

- [ ] **Step 3: No commit needed for this task** — verification-only. If any check fails, stop and fix the root cause in the relevant earlier task rather than patching around it here.

---

## What this plan deliberately does not do

Per the spec's phase boundaries and the scope decisions confirmed before writing this plan:

- No godown picker UI on `add-input.php` or `add-return.php` — that's Phase 3 ("wire real godown selection into workflows").
- No change to `neksomo-manufacturer-purchase-action.php`'s `extra_pieces` statement — the spec itself defers this until whole-pack `credit()` calls in that file start passing a real (non-null) warehouse, which is Phase 3 work; there is no bug to fix there today.
- No change to `StockService.php`, `StockLots.php`, or any report — untouched since Phase 1.

After this plan ships, both raw-SQL bypass workflows are correctness-safe against the new key, but still only ever operate on the "unassigned" bucket — behavior for real users remains unchanged until Phase 3 lands actual godown selection.
