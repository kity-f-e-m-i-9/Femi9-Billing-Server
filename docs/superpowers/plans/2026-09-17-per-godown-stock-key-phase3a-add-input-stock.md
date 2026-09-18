# Per-Godown Split Stock — Phase 3a (Add Input Stock Godown Picker) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a real, correctly-behaving godown (warehouse) picker to `add-input.php`, so stock entered via Add Input Stock can be tagged to a physical location (H1, G1, G2...) and show up correctly in the Godown login's per-warehouse dashboard. Leaving the dropdown blank keeps today's exact behavior (tagged "unassigned").

**Architecture:** Phase 2 made `input-action.php`'s four `stock` statements always filter/insert with `warehouse_id IS NULL` — a hardcoded literal, since nothing could pass a real warehouse yet. This phase makes that literal conditional again: a new nullable `$warehouseId` variable, read from `$_POST['warehouse_id']`, replaces the hardcoded `IS NULL`/`NULL` with the same `($warehouseId === null ? 'IS NULL' : '= ?')` pattern `StockService.php` already uses (Phase 1). `add-input.php` gains a `<select name="warehouse_id">` populated from the `warehouses` table (same query shape `manage-warehouses.php` and the Godown login's `dashboard.php` already use), defaulting to a blank "— Not assigned —" option so existing muscle-memory workflows are unaffected.

**Tech Stack:** PHP 8 (mysqli, prepared statements), MySQL 8 (MAMP, socket `/Applications/MAMP/tmp/mysql/mysql.sock`), manual PHP test scripts (no PHPUnit in this repo) run via `php <file>`.

**Spec:** `docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md` — this plan implements the first (and, per the confirmed scope decision, only) workflow of that spec's "Phase 3 — Wire real godown selection into workflows" section: Add Input Stock. Internal transfers, Neksomo manufacturer purchase, and sales/invoice deduction remain deferred to their own future plans, each flagged in the spec as needing its own design pass (internal transfers has a naming collision with an existing "godown" concept there; sales deduction has no established godown-selection strategy yet).

## Global Constraints

- Leaving the new dropdown at its default ("— Not assigned —", value `""`) must reproduce today's exact behavior: `warehouse_id IS NULL`, identical `stock` row, identical ledger entry. This is the single most important behavior to verify — Phase 2's fix must not regress.
- Only `femi9/billing/company/add-input.php` and `femi9/billing/company/input-action.php` change in this phase. No other workflow (Stock Return, internal transfers, Neksomo purchase, sales) is touched.
- Follow the existing manual-test convention: a disposable test database, dropped and recreated per run, using real (not simplified) table shapes for every table the code under test touches.
- All new/changed PHP files must pass `php -l` before being considered done.
- The warehouse dropdown must only list `warehouses` rows where `is_active = 1`, matching the convention already used in `femi9/billing/warehouse/dashboard.php` and `femi9/billing/company/manage-warehouses.php`.

---

## Task 1: Make `input-action.php`'s four statements accept a real `warehouse_id`

**Files:**
- Modify: `femi9/billing/company/input-action.php:50-52` (read `$warehouseId` from POST)
- Modify: `femi9/billing/company/input-action.php:152-173` (four statement declarations)
- Modify: `femi9/billing/company/input-action.php:188-206` (four `bind_param` calls, now conditional)
- Test: `femi9/billing/includes/tests/InputActionWarehouseKeyTest.php` (extended)

**Interfaces:**
- Consumes: `stock.uq_stock_entity_warehouse` (Phase 1), the Phase 2 fix's hardcoded-`IS NULL` shape as the starting point.
- Produces: `input-action.php` now honors a real `$_POST['warehouse_id']` when present, still defaulting to `null` (unassigned) when absent/blank — consumed by Task 2's UI addition.

- [ ] **Step 1: Extend the test to cover a real (non-null) warehouse_id**

Append to `femi9/billing/includes/tests/InputActionWarehouseKeyTest.php`, just before the `// ========== TEARDOWN ==========` line, a new variant of the flow helper that accepts a warehouse id, plus assertions:

```php
// ---- Replicate input-action.php's POST-PHASE-3a statement shapes: same
// four statements, but warehouse_id is now a real conditional parameter
// instead of a hardcoded NULL literal. ----
function runInputActionFlowWithWarehouse($conn, int $pid, string $userType, string $userId, int $qty, string $inputDate, ?int $warehouseId) {
    $chkSql = "SELECT COUNT(*) AS cnt FROM stock
               WHERE product_id = ? AND user_type = ? AND user_id = ?
                 AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?') . "
               FOR UPDATE";
    $stmtChkProd = $conn->prepare($chkSql);
    if ($warehouseId === null) {
        $stmtChkProd->bind_param('iss', $pid, $userType, $userId);
    } else {
        $stmtChkProd->bind_param('issi', $pid, $userType, $userId, $warehouseId);
    }
    $stmtChkProd->execute();
    $cntProd = (int) $stmtChkProd->get_result()->fetch_assoc()['cnt'];
    $stmtChkProd->close();

    if ($cntProd === 0) {
        $stmtInsertStock = $conn->prepare(
            "INSERT INTO stock
                 (product_id, opening_qty, opening_date, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
             VALUES (?, 0, ?, 0, 0, 0, 0, 0, ?, ?, ?)"
        );
        $stmtInsertStock->bind_param('isssi', $pid, $inputDate, $userType, $userId, $warehouseId);
        $stmtInsertStock->execute();
        $stmtInsertStock->close();
    }

    $getSql = "SELECT input_qty, closing_qty FROM stock
               WHERE product_id = ? AND user_type = ? AND user_id = ?
                 AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?') . "
               FOR UPDATE";
    $stmtGetStock = $conn->prepare($getSql);
    if ($warehouseId === null) {
        $stmtGetStock->bind_param('iss', $pid, $userType, $userId);
    } else {
        $stmtGetStock->bind_param('issi', $pid, $userType, $userId, $warehouseId);
    }
    $stmtGetStock->execute();
    $stockRow = $stmtGetStock->get_result()->fetch_assoc();
    $stmtGetStock->close();

    $newInputQty   = (int) $stockRow['input_qty']   + $qty;
    $newClosingQty = (int) $stockRow['closing_qty'] + $qty;

    $updSql = "UPDATE stock SET input_qty = ?, closing_qty = ?
               WHERE product_id = ? AND user_type = ? AND user_id = ?
                 AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?');
    $stmtUpdateStock = $conn->prepare($updSql);
    if ($warehouseId === null) {
        $stmtUpdateStock->bind_param('iiiss', $newInputQty, $newClosingQty, $pid, $userType, $userId);
    } else {
        $stmtUpdateStock->bind_param('iiissi', $newInputQty, $newClosingQty, $pid, $userType, $userId, $warehouseId);
    }
    $stmtUpdateStock->execute();
    $stmtUpdateStock->close();
}

// ---- Test: tagging to a real warehouse (555) creates/updates that
// warehouse's row specifically, leaving any unassigned row for the same
// product untouched. ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (32, 0, 20, 0, 0, 0, 20, 'company', '1', NULL)");

runInputActionFlowWithWarehouse($conn, 32, 'company', '1', 15, '2026-09-17', 555);

$wh555 = $conn->query("SELECT closing_qty FROM stock WHERE product_id=32 AND user_type='company' AND user_id='1' AND warehouse_id=555")->fetch_assoc();
$unassignedFor32 = $conn->query("SELECT closing_qty FROM stock WHERE product_id=32 AND user_type='company' AND user_id='1' AND warehouse_id IS NULL")->fetch_assoc();

assertEqual($wh555 !== null, true, 'Tagging to warehouse 555 creates a new warehouse-555 row');
assertEqual((int)$wh555['closing_qty'], 15, 'Warehouse-555 row has the correct closing_qty');
assertEqual((int)$unassignedFor32['closing_qty'], 20, 'Unassigned row for the same product is completely untouched');

// ---- Test: a second submission tagged to the SAME warehouse (555)
// correctly accumulates onto that warehouse's row, not the unassigned one. ----
runInputActionFlowWithWarehouse($conn, 32, 'company', '1', 5, '2026-09-17', 555);
$wh555After2nd = $conn->query("SELECT closing_qty FROM stock WHERE product_id=32 AND user_type='company' AND user_id='1' AND warehouse_id=555")->fetch_assoc();
assertEqual((int)$wh555After2nd['closing_qty'], 20, 'Second submission to warehouse 555 accumulates correctly (15 + 5 = 20)');

// ---- Test: omitting warehouse_id (null) still hits the unassigned row —
// the exact Global Constraint this phase must not regress. ----
runInputActionFlowWithWarehouse($conn, 32, 'company', '1', 3, '2026-09-17', null);
$unassignedFor32After = $conn->query("SELECT closing_qty FROM stock WHERE product_id=32 AND user_type='company' AND user_id='1' AND warehouse_id IS NULL")->fetch_assoc();
$wh555Unchanged = $conn->query("SELECT closing_qty FROM stock WHERE product_id=32 AND user_type='company' AND user_id='1' AND warehouse_id=555")->fetch_assoc();
assertEqual((int)$unassignedFor32After['closing_qty'], 23, 'null warehouse_id still correctly accumulates onto the unassigned row (20 + 3 = 23)');
assertEqual((int)$wh555Unchanged['closing_qty'], 20, 'Warehouse-555 row remains untouched by the null-warehouse submission');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php "femi9/billing/includes/tests/InputActionWarehouseKeyTest.php"`
Expected: the pre-existing 8 assertions from Phase 2 still pass (they don't depend on this task's changes); the new assertions in this step also pass immediately, since — same as Phase 1/2's pattern — this new helper (`runInputActionFlowWithWarehouse`) already contains the target logic in the test file itself. This step confirms the helper's own logic is internally consistent; run it and expect all assertions to PASS (`14 passed, 0 failed` or similar). The real regression check is Step 4 below (diff review) and Step 5 (`php -l`).

- [ ] **Step 3: Update `input-action.php` to read and thread a real `warehouse_id`**

In `femi9/billing/company/input-action.php`, first add the new input right after the existing scalar-input block:

```php
// ── Collect & validate scalar inputs ────────────────────────────────────────
$godownId   = filter_var($_POST['godownid']    ?? 0, FILTER_VALIDATE_INT);
$inputDate  = $_POST['input_date'] ?? '';
$tempId     = preg_replace('/[^A-Z0-9\/]/', '', strtoupper($_POST['tempid'] ?? ''));

// Optional: which physical godown (warehouse) this stock is being received
// into. Blank/absent means "unassigned" — the same behavior this workflow
// has always had. FILTER_VALIDATE_INT returns false for an empty string,
// so the `?: null` coalesces both "absent" and "blank selected" to null.
$warehouseId = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
```

Then replace the four statement declarations:

```php
    $stmtChkProd = $db_conn->prepare(
        "SELECT COUNT(*) AS cnt FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ?
           AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?') . "
         FOR UPDATE"                                     // lock row during transaction
    );

    $stmtInsertStock = $db_conn->prepare(
        "INSERT INTO stock
             (product_id, opening_qty, opening_date, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
         VALUES (?, 0, ?, 0, 0, 0, 0, 0, ?, ?, ?)"
    );

    $stmtGetStock = $db_conn->prepare(
        "SELECT input_qty, closing_qty FROM stock
         WHERE product_id = ? AND user_type = ? AND user_id = ?
           AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?') . "
         FOR UPDATE"
    );

    $stmtUpdateStock = $db_conn->prepare(
        "UPDATE stock SET input_qty = ?, closing_qty = ?
         WHERE product_id = ? AND user_type = ? AND user_id = ?
           AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?')
    );
```

Note `$stmtInsertStock` always has a `?` placeholder for `warehouse_id` (unlike the other three) — an `INSERT` always supplies a value, whether `NULL` or a real int, so this one doesn't need the conditional-literal treatment; only `bind_param` needs to pass `$warehouseId` (which mysqli sends as SQL `NULL` correctly through an `i` placeholder even when the PHP value is `null`).

Then replace the loop body's four `bind_param`/`execute` calls:

```php
        // 2. Ensure a stock row exists for this product / godown
        if ($warehouseId === null) {
            $stmtChkProd->bind_param('isi', $pid, $userType, $userId);
        } else {
            $stmtChkProd->bind_param('issi', $pid, $userType, $userId, $warehouseId);
        }
        $stmtChkProd->execute();
        $cntProd = (int) $stmtChkProd->get_result()->fetch_assoc()['cnt'];

        if ($cntProd === 0) {
            $stmtInsertStock->bind_param('isssi', $pid, $inputDate, $userType, $userId, $warehouseId);
            $stmtInsertStock->execute();
        }

        // 3. Read current stock quantities (locked)
        if ($warehouseId === null) {
            $stmtGetStock->bind_param('isi', $pid, $userType, $userId);
        } else {
            $stmtGetStock->bind_param('issi', $pid, $userType, $userId, $warehouseId);
        }
        $stmtGetStock->execute();
        $stockRow = $stmtGetStock->get_result()->fetch_assoc();

        $newInputQty   = (int) $stockRow['input_qty']   + $qty;
        $newClosingQty = (int) $stockRow['closing_qty'] + $qty;

        // 4. Update stock
        if ($warehouseId === null) {
            $stmtUpdateStock->bind_param('iiisi', $newInputQty, $newClosingQty, $pid, $userType, $userId);
        } else {
            $stmtUpdateStock->bind_param('iiissi', $newInputQty, $newClosingQty, $pid, $userType, $userId, $warehouseId);
        }
        $stmtUpdateStock->execute();
```

Careful check on `bind_param` type strings for the two branches — they must match the number of `?` placeholders in each SQL variant exactly:
- `stmtChkProd`: null branch has 3 placeholders (`product_id`, `user_type`, `user_id`) → `'isi'` wait — recheck order: the SQL is `product_id = ? AND user_type = ? AND user_id = ?` → types in order are `i`,`s`,`s` → **`'iss'`**, not `'isi'`. Use `'iss'` for the null branch (3 params: int, string, string) and `'issi'` for the non-null branch (4 params: int, string, string, int).
- `stmtGetStock`: same shape as `stmtChkProd` — `'iss'` null branch, `'issi'` non-null branch.
- `stmtUpdateStock`: null branch has 5 placeholders (`input_qty`, `closing_qty`, `product_id`, `user_type`, `user_id`) → `i`,`i`,`i`,`s`,`s` → `'iiiss'`; non-null branch adds `warehouse_id` (`i`) → `'iiissi'`.
- `stmtInsertStock`: always 5 placeholders — the SQL literal is `VALUES (?, 0, ?, 0, 0, 0, 0, 0, ?, ?, ?)`, so placeholders in order are `product_id`(i), `opening_date`(s), `user_type`(s), `user_id`(s), `warehouse_id`(i) → **`'isssi'`**. Note: the CURRENT live code's 4-placeholder version of this same statement (from Phase 2, before this task) is `bind_param('issi', $pid, $inputDate, $userType, $userId)`, which is already a pre-existing type-string/variable mismatch (correct would be `'isss'` — `$userType` is a string, not the `i` the third character implies). It doesn't misbehave today only because `$userId` happens to always be numeric and mysqli coerces loosely. This plan does NOT fix that pre-existing mismatch — out of scope for Phase 3a — but writes the CORRECT type string (`'isssi'`, not a copy-pasted `'issii'`) for the new 5-placeholder version this task introduces.

Use these exact, verified type strings:
```php
        if ($warehouseId === null) {
            $stmtChkProd->bind_param('iss', $pid, $userType, $userId);
        } else {
            $stmtChkProd->bind_param('issi', $pid, $userType, $userId, $warehouseId);
        }
        $stmtChkProd->execute();
        $cntProd = (int) $stmtChkProd->get_result()->fetch_assoc()['cnt'];

        if ($cntProd === 0) {
            $stmtInsertStock->bind_param('isssi', $pid, $inputDate, $userType, $userId, $warehouseId);
            $stmtInsertStock->execute();
        }

        if ($warehouseId === null) {
            $stmtGetStock->bind_param('iss', $pid, $userType, $userId);
        } else {
            $stmtGetStock->bind_param('issi', $pid, $userType, $userId, $warehouseId);
        }
        $stmtGetStock->execute();
        $stockRow = $stmtGetStock->get_result()->fetch_assoc();

        $newInputQty   = (int) $stockRow['input_qty']   + $qty;
        $newClosingQty = (int) $stockRow['closing_qty'] + $qty;

        if ($warehouseId === null) {
            $stmtUpdateStock->bind_param('iiiss', $newInputQty, $newClosingQty, $pid, $userType, $userId);
        } else {
            $stmtUpdateStock->bind_param('iiissi', $newInputQty, $newClosingQty, $pid, $userType, $userId, $warehouseId);
        }
        $stmtUpdateStock->execute();
```

- [ ] **Step 4: Review the diff against the test's `runInputActionFlowWithWarehouse()` helper**

Run: `git diff femi9/billing/company/input-action.php`
Expected: the four statement bodies (both null-branch and non-null-branch SQL text) and their conditional `bind_param` calls match, clause-for-clause and type-string-for-type-string, the corresponding blocks inside `runInputActionFlowWithWarehouse()` in the test file. Pay particular attention to `stmtInsertStock`'s type string (`'isssi'`) — this is the one most likely to be typo'd since it grew by a character from Phase 2's fixed 4-param shape, and because the pre-existing (out-of-scope) type-string mismatch on the old 4-param version could tempt copying the wrong pattern forward.

- [ ] **Step 5: Run `php -l` and the full test file**

Run:
```bash
php -l "femi9/billing/company/input-action.php"
php "femi9/billing/includes/tests/InputActionWarehouseKeyTest.php"
```
Expected: `No syntax errors detected`, then all assertions PASS with `0 failed` (Phase 2's original 8 plus this task's new ones).

- [ ] **Step 6: Commit**

```bash
git add femi9/billing/company/input-action.php femi9/billing/includes/tests/InputActionWarehouseKeyTest.php
git commit -m "$(cat <<'EOF'
Let Add Input Stock accept a real warehouse_id (Phase 3a)

input-action.php's four stock statements now read $_POST['warehouse_id']
(via FILTER_VALIDATE_INT, blank/absent -> null) instead of hardcoding
"warehouse_id IS NULL" as Phase 2 left them. Each statement conditionally
builds its WHERE/bind_param shape depending on whether a real warehouse
was submitted, matching the same (warehouseId === null ? 'IS NULL' : '= ?')
pattern StockService.php already uses.

No UI change yet in this commit — add-input.php's form still doesn't
submit warehouse_id, so every real request continues to resolve to null
(unassigned), reproducing today's exact behavior. The dropdown itself is
the next task.

Phase 3a of docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Add the godown dropdown to `add-input.php`

**Files:**
- Modify: `femi9/billing/company/add-input.php:65-70` (fetch active warehouses)
- Modify: `femi9/billing/company/add-input.php:195-201` (form field)
- Test: manual UI verification (this task has no automated test — see Step 3)

**Interfaces:**
- Consumes: `input-action.php`'s Task 1 change (accepts `$_POST['warehouse_id']`).
- Produces: a working end-to-end godown-tagging flow — nothing further in this plan consumes it (last task).

- [ ] **Step 1: Fetch the active warehouse list**

In `femi9/billing/company/add-input.php`, right after the existing product-list fetch:

```php
// ── Product list ─────────────────────────────────────────────────────────────
$products = [];
$resProd = $db_conn->query("SELECT id, productName FROM products WHERE (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) ORDER BY productName ASC");
while ($row = $resProd->fetch_assoc()) {
    $products[] = $row;
}

// ── Godown (warehouse) list ──────────────────────────────────────────────────
// Same shape as manage-warehouses.php and femi9/billing/warehouse/dashboard.php.
$warehouses = [];
$resWh = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
while ($row = $resWh->fetch_assoc()) {
    $warehouses[] = $row;
}
```

- [ ] **Step 2: Add the dropdown to the form**

In `femi9/billing/company/add-input.php`, right after the existing Date field:

```php
                                                <!-- Date -->
                                                <div class="mb-3">
                                                    <label class="form-label">Date *</label>
                                                    <input type="date" required name="input_date"
                                                           value="<?= date('Y-m-d') ?>"
                                                           class="form-control">
                                                </div>

                                                <!-- Godown (physical warehouse) -->
                                                <div class="mb-3">
                                                    <label class="form-label">Godown</label>
                                                    <select name="warehouse_id" class="form-control">
                                                        <option value="">— Not assigned —</option>
                                                        <?php foreach ($warehouses as $wh): ?>
                                                            <option value="<?= (int) $wh['id'] ?>">
                                                                <?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="form-text">Physical storage location this stock is being received into. Leave blank if not tracked by godown.</div>
                                                </div>
```

This is intentionally NOT `required` — leaving it blank submits `warehouse_id=""`, which Task 1's `FILTER_VALIDATE_INT ?: null` coalesces to `null`, reproducing today's exact behavior (Global Constraint).

- [ ] **Step 3: Manual end-to-end verification**

This task has no automated test — `add-input.php` is a session-gated, form-rendering page, and Task 1 already proved the underlying SQL logic correct in isolation. Verify manually:

1. Start the app locally (however this project is normally served — MAMP on `http://localhost:8888` or equivalent) and log in to the company login.
2. Open **Add Input Stock**. Confirm a "Godown" dropdown appears between Date and Products, showing "— Not assigned —" plus every active row from `warehouses` (created earlier via Manage Godowns — e.g. H1, G1, G2 if you created them in this session's earlier work).
3. Submit one entry with the dropdown left at "— Not assigned —". Confirm it succeeds and behaves exactly as before (check **Manage Input Stock** / **Company Overall Stock** for the expected quantity).
4. Submit a second entry for a **different** product, this time selecting a real godown (e.g. H1). Confirm it succeeds.
5. Log in to the Godown (warehouse) login created earlier this session, select the same godown (H1) from its dashboard filter, and confirm the product from step 4 appears with the correct quantity — and does NOT appear under "All Godowns... unassigned" alongside step 3's product.
6. Submit a third entry for the SAME product as step 4, again tagged to H1. Confirm the Godown login's dashboard shows the accumulated total (step 4's qty + step 6's qty), not a duplicate row or a reset.

Report the outcome of steps 3–6 before proceeding to commit.

- [ ] **Step 4: Run `php -l`**

Run: `php -l "femi9/billing/company/add-input.php"`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add femi9/billing/company/add-input.php
git commit -m "$(cat <<'EOF'
Add godown picker to Add Input Stock form (Phase 3a)

add-input.php now fetches active warehouses (same query shape as
manage-warehouses.php and the Godown login's dashboard.php) and renders
a "Godown" dropdown between Date and Products, defaulting to
"— Not assigned —". Submitting with the default selected reproduces
today's exact behavior (warehouse_id null); selecting a real godown tags
that submission's stock rows to it, now that input-action.php (previous
commit) correctly scopes every statement by warehouse_id.

This is the first end-to-end godown-tagging flow: stock entered here and
tagged to a godown now shows up correctly in the read-only Godown login's
per-warehouse dashboard.

Manually verified: default submission unchanged, warehouse-tagged
submission creates/accumulates its own row without disturbing the
unassigned row, and the Godown login reflects the tagged quantity.

Phase 3a of docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Full regression pass

**Files:**
- None modified — verification only.

- [ ] **Step 1: Run every test file from Phases 1–3a together**

Run:
```bash
php "femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php" && \
php "femi9/billing/includes/tests/StockServiceReverseTransferInTest.php" && \
php "femi9/billing/includes/tests/InputActionWarehouseKeyTest.php" && \
php "femi9/billing/includes/tests/StockReturnUpdateWarehouseKeyTest.php"
```
Expected: all four exit 0, `0 failed` in each.

- [ ] **Step 2: Confirm no other file was modified**

Run: `git status --short`
Expected: only pre-existing unrelated changes (if any were already present before this plan started), nothing from this plan left uncommitted.

- [ ] **Step 3: No commit needed for this task** — verification-only.

---

## What this plan deliberately does not do

- No godown selection wired into internal transfers, Neksomo manufacturer purchase, or sales/invoice deduction — each remains its own future plan per the spec.
- No change to `StockLots.php` / FIFO cost-lot consumption — still Phase 4 in the spec.
- No change to `overall-stock.php` or other reports — still Phase 5 in the spec. (The Godown login's own `dashboard.php` already sums by `warehouse_id` correctly, from Phase 1's original commit — no change needed there.)

After this plan ships, Add Input Stock is the first fully working end-to-end godown-tagging workflow: create a godown, receive stock into it, see it reflected in the read-only Godown login.
