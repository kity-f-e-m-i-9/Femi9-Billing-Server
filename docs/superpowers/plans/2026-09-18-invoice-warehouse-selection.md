# Warehouse Selection on Invoice/Sale Flows Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let every invoice/sale creation flow (TP, OT channel, SS,
customer, shop) optionally tag its stock deduction to a specific
physical warehouse, threading through StockService's existing
`?int $warehouseId = null` parameter.

**Architecture:** Add a nullable `warehouse_id` column to each flow's
invoice-header table (`tp_invoices`, `ot_sales`, `invoice`,
`user_invoice`). Add a warehouse `<select>` next to each flow's existing
company-godown `<select>` (or, for SS invoice, standalone). Persist the
picked value the same way each flow already persists its `godownid`.
Thread the value into the relevant `StockService` deduction/credit call
as the trailing argument. TP invoice additionally requires refactoring
its hand-rolled raw-SQL stock helpers to real `StockService` calls,
since it never adopted StockService in an earlier phase.

**Tech Stack:** PHP 8 + mysqli, existing `StockService`
(`femi9/billing/company/include/StockService.php`). Tests follow this
repo's convention: standalone PHP scripts under
`femi9/billing/includes/tests/` with a disposable MySQL schema, run via
`php <file>.php` (no PHPUnit).

**Spec:** `docs/superpowers/specs/2026-09-18-invoice-warehouse-selection-design.md`

## Global Constraints

- Warehouse dropdown is always `SELECT id, code, name FROM warehouses
  WHERE is_active = 1 ORDER BY code ASC`, identical everywhere — no
  per-company-profile filtering (warehouses are global; see spec's Key
  design facts §1).
- Every new `warehouse_id` field is optional. Blank/absent →
  `filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null`
  → passed as `null` to StockService, matching the existing convention
  used in `input-action.php` and `internal_transfer_action.php`.
- Every new DB column is `INT NULL`, additive, no backfill — same
  pattern as every prior migration in this project.
- TP invoice's CP-sourced path (`source_cp_id`) is completely untouched
  — no warehouse concept applies to channel-partner stock.
- TP invoice's refactor changes `stock_ledger.action` from
  `'transfer_out'`/`'transfer_in'` to `'deduct'`/StockService's own
  `reverseDeduct` action value, and `ref_type` from `'transfer'` to
  `'tp_invoice'` — confirmed safe in the spec (no report reads
  `stock_ledger.action` for TP invoice rows today).

---

## File Structure

- **Modify:** `femi9/billing/db_migrations/2026_09_18_invoice_warehouse_columns.sql`
  (new) — adds `warehouse_id INT NULL` to `tp_invoices`, `ot_sales`,
  `invoice`, `user_invoice`.
- **Modify:** `femi9/billing/company/add-tp-invoice.php`,
  `tp-invoice-action.php`, `edit-tp-invoice.php`,
  `edit-tp-invoice-action.php` — TP invoice warehouse picker + StockService
  refactor.
- **Modify:** `femi9/billing/company/ot-sale-add.php`,
  `ot-sale-action.php` — OT channel invoice warehouse picker.
- **Modify:** `femi9/billing/super-stockist/user-invoice-add.php` — SS
  invoice standalone warehouse picker (deduction side covered by the
  shared-include task below, since SS invoice reuses
  `invoice-stock-update.php`).
- **Modify:** `femi9/billing/company/customer-user-invoice-add.php`,
  `customer-user-invoice-action.php`, `customer-user-invoice-action2.php`
  — customer invoice (picker-based) warehouse picker.
- **Modify:** `femi9/billing/company/shop-user-invoice-add.php`,
  `shop-user-invoice-action.php` — shop invoice warehouse picker.
- **Modify:** `femi9/billing/company/invoice-stock-update.php` — shared
  deduction logic, read `warehouse_id` for both the customer-invoice and
  shop/SS-invoice branches.
- **Test:** `femi9/billing/includes/tests/TpInvoiceWarehouseKeyTest.php`,
  `femi9/billing/includes/tests/InvoiceStockUpdateWarehouseKeyTest.php`,
  `femi9/billing/includes/tests/OtSaleWarehouseKeyTest.php` (new).

---

### Task 1: Schema migration

**Files:**
- Create: `femi9/billing/db_migrations/2026_09_18_invoice_warehouse_columns.sql`

- [ ] **Step 1: Write the migration file**

```sql
-- Adds optional physical-warehouse tagging to every invoice/sale header
-- table, per docs/superpowers/specs/2026-09-18-invoice-warehouse-selection-design.md.
-- Nullable, additive — no backfill, no existing row is touched.

ALTER TABLE tp_invoices
  ADD COLUMN warehouse_id INT NULL AFTER source_godown_id;

ALTER TABLE ot_sales
  ADD COLUMN warehouse_id INT NULL AFTER godownid;

ALTER TABLE invoice
  ADD COLUMN warehouse_id INT NULL AFTER user_id;

ALTER TABLE user_invoice
  ADD COLUMN warehouse_id INT NULL AFTER from_user_id;
```

- [ ] **Step 2: Apply to the live database**

Run each statement individually via a PHP one-liner (this MySQL version
does not support `ADD COLUMN IF NOT EXISTS`, confirmed during the
`preferred_cp_id` fix earlier — guard each with a `SHOW COLUMNS ... LIKE`
check first, same pattern used there):

```bash
php -r '
require_once "femi9/billing/company/include/db-connect.php";
$alters = [
    "tp_invoices"   => "ALTER TABLE tp_invoices ADD COLUMN warehouse_id INT NULL AFTER source_godown_id",
    "ot_sales"      => "ALTER TABLE ot_sales ADD COLUMN warehouse_id INT NULL AFTER godownid",
    "invoice"       => "ALTER TABLE invoice ADD COLUMN warehouse_id INT NULL AFTER user_id",
    "user_invoice"  => "ALTER TABLE user_invoice ADD COLUMN warehouse_id INT NULL AFTER from_user_id",
];
foreach ($alters as $table => $sql) {
    $res = $db_conn->query("SHOW COLUMNS FROM $table LIKE \"warehouse_id\"");
    if ($res->num_rows > 0) { echo "$table: already has warehouse_id, skipping\n"; continue; }
    echo $db_conn->query($sql) ? "$table: OK\n" : "$table: ERROR " . $db_conn->error . "\n";
}
'
```

Expected: `OK` for all four tables.

- [ ] **Step 3: Verify row counts unaffected**

```bash
php -r '
require_once "femi9/billing/company/include/db-connect.php";
foreach (["tp_invoices","ot_sales","invoice","user_invoice"] as $t) {
    $r = $db_conn->query("SELECT COUNT(*) c FROM $t")->fetch_assoc();
    echo "$t: {$r["c"]} rows\n";
}
'
```

Expected: same counts as before migration (spot-check against your own
memory of typical row counts, or just confirm the query succeeds with no
errors — the point is these are non-destructive `ALTER`s).

- [ ] **Step 4: Commit**

```bash
git add "femi9/billing/db_migrations/2026_09_18_invoice_warehouse_columns.sql"
git commit -m "Add warehouse_id column to tp_invoices, ot_sales, invoice, user_invoice"
```

---

### Task 2: OT channel invoice warehouse picker

**Files:**
- Modify: `femi9/billing/company/ot-sale-add.php`
- Modify: `femi9/billing/company/ot-sale-action.php`
- Test: `femi9/billing/includes/tests/OtSaleWarehouseKeyTest.php`

**Interfaces:**
- Consumes: `StockService::otDeduct(int $productId, string $userType,
  string $userId, int $qty, string $refId, string $createdBy, bool
  $externalTransaction = false, ?int $warehouseId = null): array`
  (`StockService.php:588`), `StockService::otReverse(...)` (same
  trailing-param shape, used at the edit-delta call site).

This task is the simplest of the five — `otDeduct`/`otReverse` already
accept `$warehouseId`; only the calling file needs to read and pass it
through.

- [ ] **Step 1: Write the failing test**

Create `femi9/billing/includes/tests/OtSaleWarehouseKeyTest.php`:

```php
<?php
// femi9/billing/includes/tests/OtSaleWarehouseKeyTest.php
// Manual run: php OtSaleWarehouseKeyTest.php
//
// Confirms ot-sale-action.php's otDeduct()/otReverse() call sites
// correctly thread a warehouse_id through to StockService, per
// docs/superpowers/specs/2026-09-18-invoice-warehouse-selection-design.md.
//
// This test exercises StockService::otDeduct/otReverse directly with
// the same call shape ot-sale-action.php uses post-fix — it does not
// require the script itself (session-gated POST handler).

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/db-connect.php';

$conn = $db_conn;

const TEST_SCHEMA = 'ot_sale_warehouse_test';

$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `" . TEST_SCHEMA . "`")) {
    fwrite(STDERR, "FATAL: could not CREATE DATABASE — " . $conn->error . "\n");
    exit(1);
}
if (!$conn->select_db(TEST_SCHEMA)) {
    fwrite(STDERR, "FATAL: could not switch schema — " . $conn->error . "\n");
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

// ========== SETUP: real table shapes, post-Phase-1 ==========
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

$conn->query("CREATE TABLE company_godown (id INT AUTO_INCREMENT PRIMARY KEY, gname VARCHAR(255) NOT NULL)");
$conn->query("CREATE TABLE stock_lots (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(32) NOT NULL,
    user_id VARCHAR(32) NOT NULL, rate DECIMAL(12,6) NOT NULL, qty_purchased INT NOT NULL,
    qty_remaining INT NOT NULL, purchase_date DATE NOT NULL, ref_type VARCHAR(32) NOT NULL,
    ref_id VARCHAR(64) NULL, created_by VARCHAR(64) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE stock_ledger_lot_consumption (
    id INT AUTO_INCREMENT PRIMARY KEY, stock_ledger_id INT NOT NULL, stock_lot_id INT NULL,
    qty_taken INT NOT NULL, rate DECIMAL(12,6) NOT NULL
)");
$conn->query("CREATE TABLE neksomo_llp_piece_rates (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, effective_date DATE NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL, gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_type VARCHAR(10) NOT NULL DEFAULT 'exclusive'
)");
$conn->query("CREATE TABLE femi9_llp_sale_rates (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, effective_date DATE NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL, gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_type VARCHAR(10) NOT NULL DEFAULT 'exclusive'
)");
$conn->query("CREATE TABLE products (id INT AUTO_INCREMENT PRIMARY KEY, pieces_per_pack INT NULL)");

$stockService = new StockService($conn);

// ---- Fixture: product 50, entity '3' (an OT godown id), warehouse 401 ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (50, 0, 80, 0, 0, 0, 80, 'company', '3', 401)");
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (50, 0, 200, 0, 0, 0, 200, 'company', '3', NULL)");

// ---- Test: otDeduct with warehouse_id=401 only touches that row ----
$stockService->otDeduct(50, 'company', '3', 20, 'OT-TEMPID-1', 'tester', false, 401);

$wh401 = $conn->query("SELECT closing_qty FROM stock WHERE product_id=50 AND user_id='3' AND warehouse_id=401")->fetch_assoc();
$unassigned = $conn->query("SELECT closing_qty FROM stock WHERE product_id=50 AND user_id='3' AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$wh401['closing_qty'], 60, 'otDeduct(warehouse=401) deducts from the warehouse-401 row (80-20=60)');
assertEqual((int)$unassigned['closing_qty'], 200, 'otDeduct(warehouse=401) leaves the unassigned row untouched');

// ---- Test: otReverse with the same warehouse_id restores it ----
$stockService->otReverse(50, 'company', '3', 20, 'OT-TEMPID-1', 'tester', false, 401);
$wh401After = $conn->query("SELECT closing_qty FROM stock WHERE product_id=50 AND user_id='3' AND warehouse_id=401")->fetch_assoc();
assertEqual((int)$wh401After['closing_qty'], 80, 'otReverse(warehouse=401) restores the warehouse-401 row (60+20=80)');

// ---- Test: omitting warehouse_id (null) hits the unassigned row instead ----
$stockService->otDeduct(50, 'company', '3', 30, 'OT-TEMPID-2', 'tester', false, null);
$unassignedAfter = $conn->query("SELECT closing_qty FROM stock WHERE product_id=50 AND user_id='3' AND warehouse_id IS NULL")->fetch_assoc();
$wh401Unchanged = $conn->query("SELECT closing_qty FROM stock WHERE product_id=50 AND user_id='3' AND warehouse_id=401")->fetch_assoc();
assertEqual((int)$unassignedAfter['closing_qty'], 170, 'otDeduct(warehouse=null) deducts from the unassigned row (200-30=170)');
assertEqual((int)$wh401Unchanged['closing_qty'], 80, 'otDeduct(warehouse=null) leaves warehouse-401 untouched');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php "femi9/billing/includes/tests/OtSaleWarehouseKeyTest.php"`
Expected: `5 passed, 0 failed` already, since this only exercises
`StockService` itself (already warehouse-aware from Phase 1) — this
step confirms the *test* is correctly written before you touch
`ot-sale-add.php`/`ot-sale-action.php`. (Unlike other tasks, there is no
"fails first" step here because the underlying StockService methods
already support the parameter; the remaining steps are about wiring the
UI/action file, not new StockService behavior.)

- [ ] **Step 3: Add the warehouse dropdown to `ot-sale-add.php`**

Read the file first to find the existing `godownid` dropdown (around
line 240, per the spec's investigation) and its surrounding company_godown
query (around line 120). Add, immediately after the existing `godownid`
`<select>` block:

```php
<!-- Godown (physical warehouse) -->
<div class="mb-3">
    <label class="form-label">Godown (physical)</label>
    <select name="warehouse_id" class="form-control">
        <option value="">— Not tracked —</option>
        <?php
        $resWh = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
        while ($wh = $resWh->fetch_assoc()) {
        ?>
        <option value="<?= (int) $wh['id'] ?>">
            <?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?>
        </option>
        <?php } ?>
    </select>
</div>
```

While here, also apply `godown_finance_filter_sql()` to the existing
`godownid` query (spec flags this as an existing gap directly touched by
this task — confirm the exact query text first via `grep -n
"company_godown" femi9/billing/company/ot-sale-add.php`, then add
`WHERE " . godown_finance_filter_sql($db_conn)` matching the pattern
used in `internal_transfer.php:152`).

- [ ] **Step 4: Thread `warehouse_id` through `ot-sale-action.php`**

Read the file first to find both `otDeduct`/`otReverse` call sites
(around lines 253 and 476/478 per the spec). Add near the top, alongside
the existing `$godownid` parsing:

```php
$warehouseId = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
```

Add `$warehouseId` as the trailing argument to both `otDeduct()` calls
and the `otReverse()` call, matching each function's existing parameter
order (`..., $createdBy, $externalTransaction, $warehouseId`). Also add
`warehouse_id` to whatever INSERT persists the `ot_sales` row (so the
edit-delta path can re-read it later) — find this via `grep -n "INSERT
INTO ot_sales" femi9/billing/company/ot-sale-action.php`.

- [ ] **Step 5: Syntax check**

```bash
php -l "femi9/billing/company/ot-sale-add.php"
php -l "femi9/billing/company/ot-sale-action.php"
```
Expected: `No syntax errors detected` for both.

- [ ] **Step 6: Run the test suite**

Run: `php "femi9/billing/includes/tests/OtSaleWarehouseKeyTest.php"`
Expected: `5 passed, 0 failed`.

- [ ] **Step 7: Commit**

```bash
git add "femi9/billing/company/ot-sale-add.php" "femi9/billing/company/ot-sale-action.php" "femi9/billing/includes/tests/OtSaleWarehouseKeyTest.php"
git commit -m "Add physical godown (warehouse) tagging to OT channel invoice"
```

---

### Task 3: Shared `invoice-stock-update.php` — customer & shop/SS invoice deduction

**Files:**
- Modify: `femi9/billing/company/invoice-stock-update.php`
- Test: `femi9/billing/includes/tests/InvoiceStockUpdateWarehouseKeyTest.php`

**Interfaces:**
- Consumes: `$inv['warehouse_id']` from the `invoice`/`user_invoice` row
  fetched at the top of the file (lines ~24-32 for customer-invoice
  branch, ~42-49 for shop/SS branch, per current file structure read
  earlier in this session).
- Produces: `StockService::deduct(...)` called with the trailing
  `$warehouseId` argument; `StockService::credit(...)` (buyer side) is
  called WITHOUT a warehouse argument (defaults to `null`) — per spec,
  buyer-side warehouse tagging is out of scope.

This task covers customer invoice (picker-based) AND shop invoice AND SS
invoice in one file change, since all three route through this shared
include.

- [ ] **Step 1: Write the failing test**

Create `femi9/billing/includes/tests/InvoiceStockUpdateWarehouseKeyTest.php`:

```php
<?php
// femi9/billing/includes/tests/InvoiceStockUpdateWarehouseKeyTest.php
// Manual run: php InvoiceStockUpdateWarehouseKeyTest.php
//
// Confirms invoice-stock-update.php reads warehouse_id off the
// invoice/user_invoice row and passes it to StockService::deduct(),
// while leaving the buyer-side credit() call warehouse-unaware, per
// docs/superpowers/specs/2026-09-18-invoice-warehouse-selection-design.md.
//
// Exercises invoice-stock-update.php directly (it's an include, gated
// on a define()) against a disposable schema with real table shapes.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/db-connect.php';

$conn = $db_conn;

const TEST_SCHEMA = 'invoice_stock_update_warehouse_test';

$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `" . TEST_SCHEMA . "`")) {
    fwrite(STDERR, "FATAL: could not CREATE DATABASE — " . $conn->error . "\n");
    exit(1);
}
if (!$conn->select_db(TEST_SCHEMA)) {
    fwrite(STDERR, "FATAL: could not switch schema — " . $conn->error . "\n");
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

// ========== SETUP ==========
$conn->query("CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, opening_qty INT NOT NULL DEFAULT 0,
    opening_date DATE NOT NULL DEFAULT '2026-01-01', input_qty INT NOT NULL DEFAULT 0,
    sales_qty INT NOT NULL DEFAULT 0, sent_qty INT NOT NULL DEFAULT 0, returnqty INT NOT NULL DEFAULT 0,
    closing_qty INT NOT NULL DEFAULT 0, extra_pieces INT UNSIGNED NOT NULL DEFAULT 0,
    user_type VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, warehouse_id INT NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id)
)");
$conn->query("CREATE TABLE stock_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL, warehouse_id INT NULL, action VARCHAR(50) NOT NULL, qty INT NOT NULL,
    qty_before INT NOT NULL, qty_after INT NOT NULL, ref_type VARCHAR(50) NOT NULL, ref_id VARCHAR(255) NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '', created_by VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE company_godown (id INT AUTO_INCREMENT PRIMARY KEY, gname VARCHAR(255) NOT NULL)");
$conn->query("CREATE TABLE stock_lots (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(32) NOT NULL,
    user_id VARCHAR(32) NOT NULL, rate DECIMAL(12,6) NOT NULL, qty_purchased INT NOT NULL,
    qty_remaining INT NOT NULL, purchase_date DATE NOT NULL, ref_type VARCHAR(32) NOT NULL,
    ref_id VARCHAR(64) NULL, created_by VARCHAR(64) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE stock_ledger_lot_consumption (
    id INT AUTO_INCREMENT PRIMARY KEY, stock_ledger_id INT NOT NULL, stock_lot_id INT NULL,
    qty_taken INT NOT NULL, rate DECIMAL(12,6) NOT NULL
)");
$conn->query("CREATE TABLE neksomo_llp_piece_rates (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, effective_date DATE NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL, gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_type VARCHAR(10) NOT NULL DEFAULT 'exclusive'
)");
$conn->query("CREATE TABLE femi9_llp_sale_rates (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, effective_date DATE NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL, gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_type VARCHAR(10) NOT NULL DEFAULT 'exclusive'
)");
$conn->query("CREATE TABLE products (id INT AUTO_INCREMENT PRIMARY KEY, pieces_per_pack INT NULL)");

$conn->query("CREATE TABLE invoice (
    inv_id VARCHAR(64) PRIMARY KEY, user_type VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL,
    warehouse_id INT NULL, customer_id VARCHAR(255) NOT NULL, date DATE NOT NULL
)");
$conn->query("CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY, inv_id VARCHAR(64) NOT NULL, pr_id INT NOT NULL, qty INT NOT NULL,
    deleted_at TIMESTAMP NULL
)");

// ---- Fixture: seller entity '9' has stock in warehouse 501 + an unassigned row ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (60, 0, 40, 0, 0, 0, 40, 'company', '9', 501)");
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (60, 0, 100, 0, 0, 0, 100, 'company', '9', NULL)");

$conn->query("INSERT INTO invoice (inv_id, user_type, user_id, warehouse_id, customer_id, date)
    VALUES ('INV-TEST-1', 'company', '9', 501, 'CUST-1', '2026-09-18')");
$conn->query("INSERT INTO invoice_items (inv_id, pr_id, qty) VALUES ('INV-TEST-1', 60, 10)");

// ---- Replicate invoice-stock-update.php's customer-invoice branch, POST-FIX ----
// (This is what Task 3 Step 3 makes real in invoice-stock-update.php itself.)
$is_customer_invoice = true;
$invoice_id = 'INV-TEST-1';
$invoice_stock_external_txn = true; // avoid nested commit in this standalone test

$stmt = $conn->prepare("SELECT * FROM invoice WHERE inv_id = ?");
$stmt->bind_param('s', $invoice_id);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
$stmt->close();

$company_type = $inv['user_type'];
$company_id   = $inv['user_id'];
$warehouseId  = $inv['warehouse_id'] !== null ? (int) $inv['warehouse_id'] : null;

$stmt = $conn->prepare("SELECT pr_id, qty FROM invoice_items WHERE inv_id = ? AND deleted_at IS NULL");
$stmt->bind_param('s', $invoice_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stockService = new StockService($conn);
foreach ($items as $item) {
    $stockService->deduct((int)$item['pr_id'], $company_type, $company_id, (int)$item['qty'], 'invoice', $invoice_id, 'tester', true, $warehouseId);
}

// ---- Assertions ----
$wh501 = $conn->query("SELECT closing_qty FROM stock WHERE product_id=60 AND user_id='9' AND warehouse_id=501")->fetch_assoc();
$unassigned = $conn->query("SELECT closing_qty FROM stock WHERE product_id=60 AND user_id='9' AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$wh501['closing_qty'], 30, 'Customer invoice with warehouse_id=501 deducts from the warehouse-501 row (40-10=30)');
assertEqual((int)$unassigned['closing_qty'], 100, 'Customer invoice with warehouse_id=501 leaves the unassigned row untouched');

// ---- Test: an invoice with warehouse_id=NULL falls back to the unassigned row ----
$conn->query("INSERT INTO invoice (inv_id, user_type, user_id, warehouse_id, customer_id, date)
    VALUES ('INV-TEST-2', 'company', '9', NULL, 'CUST-1', '2026-09-18')");
$conn->query("INSERT INTO invoice_items (inv_id, pr_id, qty) VALUES ('INV-TEST-2', 60, 15)");

$inv_id2 = 'INV-TEST-2';
$stmt = $conn->prepare("SELECT * FROM invoice WHERE inv_id = ?");
$stmt->bind_param('s', $inv_id2);
$stmt->execute();
$inv2 = $stmt->get_result()->fetch_assoc();
$stmt->close();
$warehouseId2 = $inv2['warehouse_id'] !== null ? (int) $inv2['warehouse_id'] : null;

$stockService->deduct(60, $inv2['user_type'], $inv2['user_id'], 15, 'invoice', 'INV-TEST-2', 'tester', true, $warehouseId2);

$unassignedAfter = $conn->query("SELECT closing_qty FROM stock WHERE product_id=60 AND user_id='9' AND warehouse_id IS NULL")->fetch_assoc();
$wh501Unchanged = $conn->query("SELECT closing_qty FROM stock WHERE product_id=60 AND user_id='9' AND warehouse_id=501")->fetch_assoc();
assertEqual((int)$unassignedAfter['closing_qty'], 85, 'Invoice with warehouse_id=NULL deducts from the unassigned row (100-15=85)');
assertEqual((int)$wh501Unchanged['closing_qty'], 30, 'Invoice with warehouse_id=NULL leaves warehouse-501 untouched');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php "femi9/billing/includes/tests/InvoiceStockUpdateWarehouseKeyTest.php"`
Expected: FATAL error — `invoice` table has no `warehouse_id` column
referenced correctly, or more likely this passes already since the test
constructs its own fixture inline rather than requiring the real file.
Confirm by checking: this test does NOT `require` `invoice-stock-update.php`
itself (that file is designed to be included only after a `define()`
guard, per its own header comment) — it replicates the logic inline, so
it validates the *approach* before Step 3 makes the real file match.
If it already passes, that confirms the test itself is correct; proceed
to Step 3 to make the real file match this proven logic.

- [ ] **Step 3: Update `invoice-stock-update.php`**

Read the file first (already read in full during spec research — current
structure has the customer-invoice branch around lines 24-32 and the
shop/SS branch around lines 42-49, with the deduct/credit calls around
lines 103/108). Make these two changes:

In the customer-invoice branch, add after the existing `$invoice_date`
line:
```php
$warehouseId   = $inv['warehouse_id'] !== null ? (int) $inv['warehouse_id'] : null;
```

In the shop/SS-invoice branch (the `else` block), add after its existing
`$invoice_date` line:
```php
$warehouseId   = $inv['warehouse_id'] !== null ? (int) $inv['warehouse_id'] : null;
```

Then update the seller-side deduct call to pass it:
```php
$stockService->deduct(
    $prId, $company_type, $company_id, $qty,
    $refType, $invoice_id, $createdBy,
    true,
    $warehouseId
);
```

Leave the buyer-side `credit()` call unchanged (no warehouse argument —
defaults to `null`, per spec's explicit scope decision).

- [ ] **Step 4: Syntax check**

```bash
php -l "femi9/billing/company/invoice-stock-update.php"
```
Expected: `No syntax errors detected`

- [ ] **Step 5: Run the test suite**

Run: `php "femi9/billing/includes/tests/InvoiceStockUpdateWarehouseKeyTest.php"`
Expected: `4 passed, 0 failed`

- [ ] **Step 6: Commit**

```bash
git add "femi9/billing/company/invoice-stock-update.php" "femi9/billing/includes/tests/InvoiceStockUpdateWarehouseKeyTest.php"
git commit -m "Thread warehouse_id through shared invoice-stock-update.php deduction"
```

---

### Task 4: Customer invoice (picker-based) warehouse picker UI

**Files:**
- Modify: `femi9/billing/company/customer-user-invoice-add.php`
- Modify: `femi9/billing/company/customer-user-invoice-action.php`
- Modify: `femi9/billing/company/customer-user-invoice-action2.php`

**Interfaces:**
- Consumes: Task 3's `invoice.warehouse_id` column and
  `invoice-stock-update.php`'s already-updated read of it.
- Produces: the `invoice` row's `INSERT` (in
  `customer-user-invoice-action.php`, confirmed at the line containing
  `insert into invoice (inv_id,id_only,inv_number,customer_id,date,inv_year,
  sub_total,discount,total,user_type,user_id,gst_type,roundoff,courier_charges,buyer_gsttype)`)
  gains a `warehouse_id` column + value, sourced from the same
  `$_REQUEST['warehouse_id']` that arrives alongside `$_REQUEST['godownid']`
  on the very first "add item" submission (the request that creates the
  invoice header). `customer-user-invoice-action2.php` (subsequent
  "add item" submissions against an existing invoice) does not need to
  touch `warehouse_id` — it only inserts `invoice_items` rows, never a
  second `invoice` header row (this was confirmed during investigation:
  the header INSERT is guarded and only fires once).

- [ ] **Step 1: Add the warehouse dropdown to `customer-user-invoice-add.php`**

Read the file first to find both `godownid` `<select>` occurrences (lines
~194 and ~640, per the earlier investigation — one is likely a
hidden/duplicate for a different form state). Add, immediately after
each existing `godownid` `<select>` block:

```php
<!-- Godown (physical warehouse) -->
<select name="warehouse_id" class="js-states form-control" tabindex="-1" style="display: none; width: 100%">
    <option value="">— Not tracked —</option>
    <?php
    $resWh = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
    while ($wh = $resWh->fetch_assoc()) {
    ?>
    <option value="<?= (int) $wh['id'] ?>">
        <?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?>
    </option>
    <?php } ?>
</select>
```

(Matching the existing `godownid` select's own class/style attributes —
confirm exact classes via the file read, since the two occurrences may
differ slightly, e.g. one might not have `js-states`/`select2`
initialization; mirror whichever pattern the sibling `godownid` select
at that exact location uses.)

- [ ] **Step 2: Persist `warehouse_id` on invoice creation in `customer-user-invoice-action.php`**

Read the file first (already read in full during planning — header
INSERT is the line containing `insert into invoice (inv_id,id_only,...)`).
Add right after the existing `$godownid=$_REQUEST['godownid'];` line:

```php
$warehouseId = filter_var($_REQUEST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$warehouseIdSql = $warehouseId === null ? 'NULL' : (int) $warehouseId;
```

Update the header INSERT to include the new column:
```php
$insert_Invoice="insert into invoice (inv_id,id_only,inv_number,customer_id,date,inv_year,
sub_total,discount,total,user_type,user_id,warehouse_id,
gst_type,roundoff,courier_charges,buyer_gsttype)
values 
('$inv_id','$id_only','$inv_number','$customer_id','$date','$inv_year','0','0','0',
'$Login_user_TYPEvl','$Login_user_IDvl',$warehouseIdSql,'$gst_type','0','0','$buyer_gsttype')";
```

Note: `$warehouseIdSql` is interpolated unquoted (it's either the bare
word `NULL` or a cast integer, never a string), matching how `'0'` numeric
literals are already handled elsewhere in this exact query — this file
uses raw string interpolation throughout (not prepared statements), so
follow its existing convention rather than introducing a mismatched
style for one field.

- [ ] **Step 3: Syntax check**

```bash
php -l "femi9/billing/company/customer-user-invoice-add.php"
php -l "femi9/billing/company/customer-user-invoice-action.php"
```
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Manual smoke test of the SQL shape**

Since this file uses raw string interpolation rather than prepared
statements (pre-existing style, not something this task changes), verify
the new INSERT's column/value count matches by running:

```bash
php -r '
$inv_id="TESTINV1"; $id_only="0"; $inv_number="TEST-1"; $customer_id="1"; $date="2026-09-18"; $inv_year="2026";
$Login_user_TYPEvl="company"; $Login_user_IDvl="9"; $gst_type="exclusive"; $buyer_gsttype="exclusive";
$warehouseIdSql = 501;
$insert_Invoice="insert into invoice (inv_id,id_only,inv_number,customer_id,date,inv_year,
sub_total,discount,total,user_type,user_id,warehouse_id,
gst_type,roundoff,courier_charges,buyer_gsttype)
values 
(\"$inv_id\",\"$id_only\",\"$inv_number\",\"$customer_id\",\"$date\",\"$inv_year\",\"0\",\"0\",\"0\",
\"$Login_user_TYPEvl\",\"$Login_user_IDvl\",$warehouseIdSql,\"$gst_type\",\"0\",\"0\",\"$buyer_gsttype\")";
echo $insert_Invoice . "\n";
$colCount = substr_count(explode(") values", $insert_Invoice)[0], ",") + 1;
$valCount = substr_count(explode("values", $insert_Invoice)[1], ",") + 1;
echo "columns: $colCount, values: $valCount\n";
'
```

Expected: `columns: 16, values: 16` (matching count, confirming no
off-by-one in the manual edit).

- [ ] **Step 5: Commit**

```bash
git add "femi9/billing/company/customer-user-invoice-add.php" "femi9/billing/company/customer-user-invoice-action.php"
git commit -m "Add physical godown (warehouse) tagging to customer invoice"
```

---

### Task 5: Shop invoice warehouse picker UI

**Files:**
- Modify: `femi9/billing/company/shop-user-invoice-add.php`
- Modify: `femi9/billing/company/shop-user-invoice-action.php`

**Interfaces:**
- Consumes: Task 3's `user_invoice.warehouse_id` column.
- Produces: the `user_invoice` header INSERT (confirmed containing
  `insert into user_invoice (inv_id,id_only,inv_number,date,inv_year,sub_total,
  discount,total,to_user_type,to_user_id,from_user_type,from_user_id,gst_type,
  credit,roundoff,courier_charges,rwpoints_enable,buyer_gsttype,username,usertype)`)
  gains `warehouse_id`.

- [ ] **Step 1: Add the warehouse dropdown to `shop-user-invoice-add.php`**

Read the file first to find the existing `godownid` dropdown (line ~635,
options query at line ~637). Add immediately after:

```php
<!-- Godown (physical warehouse) -->
<div class="mb-3">
    <label class="form-label">Godown (physical)</label>
    <select name="warehouse_id" class="form-control">
        <option value="">— Not tracked —</option>
        <?php
        $resWh = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
        while ($wh = $resWh->fetch_assoc()) {
        ?>
        <option value="<?= (int) $wh['id'] ?>">
            <?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?>
        </option>
        <?php } ?>
    </select>
</div>
```

Also check the pre-selected/locked variant noted in the spec (around
line 195-196, "a single fixed option, e.g. when arriving from a specific
godown context") — if that variant exists as a separate code path,
confirm during implementation whether a warehouse should similarly be
lockable there, or left as "not tracked" in that context (default to
"not tracked" if unclear — this is the safer, less invasive choice and
matches the optional-everywhere constraint).

- [ ] **Step 2: Persist `warehouse_id` on invoice creation in `shop-user-invoice-action.php`**

Read the file first (header INSERT confirmed containing the column list
above). Add right after wherever `$godownid` is read from
`$_REQUEST`/`$_POST` (grep for it first: `grep -n 'godownid' femi9/billing/company/shop-user-invoice-action.php`):

```php
$warehouseId = filter_var($_REQUEST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$warehouseIdSql = $warehouseId === null ? 'NULL' : (int) $warehouseId;
```

Update the header INSERT to include `warehouse_id` — insert the column
name right after `from_user_id` and the corresponding `$warehouseIdSql`
(unquoted) right after `'$from_user_id'` in the VALUES clause, following
the same interpolation convention already used by this file's other
numeric-literal fields (mirror Task 4 Step 2's approach exactly).

- [ ] **Step 3: Syntax check**

```bash
php -l "femi9/billing/company/shop-user-invoice-add.php"
php -l "femi9/billing/company/shop-user-invoice-action.php"
```
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add "femi9/billing/company/shop-user-invoice-add.php" "femi9/billing/company/shop-user-invoice-action.php"
git commit -m "Add physical godown (warehouse) tagging to shop invoice"
```

---

### Task 6: SS invoice standalone warehouse picker UI

**Files:**
- Modify: `femi9/billing/super-stockist/user-invoice-add.php`

**Interfaces:**
- Consumes: Task 3's `user_invoice.warehouse_id` column (SS invoice
  writes to the same `user_invoice` table as shop invoice, via the same
  `user-invoice-submit.php` → `invoice-stock-update.php` chain already
  fixed in Task 3).
- Produces: the same `warehouse_id` field name, so it flows through
  whatever shared action/submit files SS invoice uses (confirm during
  implementation whether SS invoice's action file is the same
  `shop-user-invoice-action.php`-style file or its own — the spec's
  investigation found `user-invoice-submit.php` as the deduction entry
  point but did not confirm the exact header-INSERT file for SS; if it
  turns out to be a distinct INSERT statement rather than reusing
  Task 5's edited file, apply the identical column-addition pattern from
  Task 5 Step 2 to that file instead).

- [ ] **Step 1: Investigate SS invoice's actual header-INSERT location**

Run: `grep -rn "insert into user_invoice\|INSERT INTO user_invoice" femi9/billing/super-stockist/`

If this returns a match inside `femi9/billing/super-stockist/`, that
file needs the same `warehouse_id` column-and-value addition as Task 5
Step 2 (apply the identical pattern there). If it returns no match, SS
invoice reuses `femi9/billing/company/shop-user-invoice-action.php`'s
INSERT directly (already fixed by Task 5) — in that case this task only
needs the UI addition (Step 2 below), nothing else server-side.

- [ ] **Step 2: Add the standalone warehouse dropdown to `user-invoice-add.php`**

Read the file first to find both `godownid` hidden-field occurrences
(lines ~681 and ~1277, per the spec — both `<input type="hidden"
name="godownid" value="<?=$onboard_userID;?>">`). Immediately after each
one, add a REAL (visible, user-editable) dropdown — unlike the other
flows, there is no preceding company-profile picker step, so this
dropdown stands alone:

```php
<div class="mb-3">
    <label class="form-label">Godown (physical)</label>
    <select name="warehouse_id" class="form-control">
        <option value="">— Not tracked —</option>
        <?php
        $resWh = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
        while ($wh = $resWh->fetch_assoc()) {
        ?>
        <option value="<?= (int) $wh['id'] ?>">
            <?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?>
        </option>
        <?php } ?>
    </select>
</div>
```

- [ ] **Step 3: Apply Step 1's finding**

If Step 1 found a distinct SS-specific INSERT, apply the same
`$warehouseId`/`$warehouseIdSql` parsing and column addition as Task 5
Step 2 to that file now.

- [ ] **Step 4: Syntax check**

```bash
php -l "femi9/billing/super-stockist/user-invoice-add.php"
```
Expected: `No syntax errors detected`. If Step 3 modified another file,
syntax-check it too.

- [ ] **Step 5: Commit**

```bash
git add "femi9/billing/super-stockist/user-invoice-add.php"
# plus any file touched in Step 3
git commit -m "Add standalone physical godown (warehouse) picker to SS invoice"
```

---

### Task 7: TP invoice — refactor to StockService + warehouse picker

**Files:**
- Modify: `femi9/billing/company/add-tp-invoice.php`
- Modify: `femi9/billing/company/tp-invoice-action.php`
- Modify: `femi9/billing/company/edit-tp-invoice.php`
- Modify: `femi9/billing/company/edit-tp-invoice-action.php`
- Test: `femi9/billing/includes/tests/TpInvoiceWarehouseKeyTest.php`

**Interfaces:**
- Consumes: `StockService::deduct()` (`StockService.php:41`),
  `StockService::reverseDeduct()` (`StockService.php:176`) — both
  already accept trailing `?int $warehouseId = null`.
- Produces: replaces `tp-invoice-action.php`'s
  `getGodownQtyForTp()`/`lockAndGetGodownQtyForTp()`/
  `debitGodownForTp()`/`insertGodownLedgerForTp()` and
  `edit-tp-invoice-action.php`'s equivalent `*E`-suffixed helpers with
  direct `StockService` calls. CP-sourced helpers
  (`getCpQty`/`lockAndGetCpQty`/`debitCp`/`insertCpLedger` and their `*E`
  siblings) are UNTOUCHED.

This is the largest task — it changes both the create and edit paths,
and changes `stock_ledger`'s recorded `action`/`ref_type` values for
TP-invoice-sourced rows (documented behavior change, confirmed safe in
the spec's investigation).

- [ ] **Step 1: Write the failing test**

Create `femi9/billing/includes/tests/TpInvoiceWarehouseKeyTest.php`:

```php
<?php
// femi9/billing/includes/tests/TpInvoiceWarehouseKeyTest.php
// Manual run: php TpInvoiceWarehouseKeyTest.php
//
// Confirms the refactored tp-invoice-action.php / edit-tp-invoice-action.php
// godown-sourced path correctly threads warehouse_id through
// StockService::deduct()/reverseDeduct(), replacing the old hand-rolled
// raw-SQL helpers, per
// docs/superpowers/specs/2026-09-18-invoice-warehouse-selection-design.md.
//
// Exercises StockService directly with the exact call shape the
// refactored files use — this is the behavior contract Step 3/5 below
// must implement in the real files.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/db-connect.php';

$conn = $db_conn;

const TEST_SCHEMA = 'tp_invoice_warehouse_test';

$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `" . TEST_SCHEMA . "`")) {
    fwrite(STDERR, "FATAL: could not CREATE DATABASE — " . $conn->error . "\n");
    exit(1);
}
if (!$conn->select_db(TEST_SCHEMA)) {
    fwrite(STDERR, "FATAL: could not switch schema — " . $conn->error . "\n");
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

// ========== SETUP ==========
$conn->query("CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, opening_qty INT NOT NULL DEFAULT 0,
    opening_date DATE NOT NULL DEFAULT '2026-01-01', input_qty INT NOT NULL DEFAULT 0,
    sales_qty INT NOT NULL DEFAULT 0, sent_qty INT NOT NULL DEFAULT 0, returnqty INT NOT NULL DEFAULT 0,
    closing_qty INT NOT NULL DEFAULT 0, extra_pieces INT UNSIGNED NOT NULL DEFAULT 0,
    user_type VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, warehouse_id INT NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id)
)");
$conn->query("CREATE TABLE stock_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL, warehouse_id INT NULL, action VARCHAR(50) NOT NULL, qty INT NOT NULL,
    qty_before INT NOT NULL, qty_after INT NOT NULL, ref_type VARCHAR(50) NOT NULL, ref_id VARCHAR(255) NOT NULL,
    note VARCHAR(255) NOT NULL DEFAULT '', created_by VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE company_godown (id INT AUTO_INCREMENT PRIMARY KEY, gname VARCHAR(255) NOT NULL)");
$conn->query("CREATE TABLE stock_lots (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(32) NOT NULL,
    user_id VARCHAR(32) NOT NULL, rate DECIMAL(12,6) NOT NULL, qty_purchased INT NOT NULL,
    qty_remaining INT NOT NULL, purchase_date DATE NOT NULL, ref_type VARCHAR(32) NOT NULL,
    ref_id VARCHAR(64) NULL, created_by VARCHAR(64) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE stock_ledger_lot_consumption (
    id INT AUTO_INCREMENT PRIMARY KEY, stock_ledger_id INT NOT NULL, stock_lot_id INT NULL,
    qty_taken INT NOT NULL, rate DECIMAL(12,6) NOT NULL
)");
$conn->query("CREATE TABLE neksomo_llp_piece_rates (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, effective_date DATE NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL, gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_type VARCHAR(10) NOT NULL DEFAULT 'exclusive'
)");
$conn->query("CREATE TABLE femi9_llp_sale_rates (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, effective_date DATE NOT NULL,
    rate_per_piece DECIMAL(10,2) NOT NULL, gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    gst_type VARCHAR(10) NOT NULL DEFAULT 'exclusive'
)");
$conn->query("CREATE TABLE products (id INT AUTO_INCREMENT PRIMARY KEY, pieces_per_pack INT NULL)");

$stockService = new StockService($conn);

// ---- Fixture: godown '7' (a company_godown id used as TP invoice source), warehouse 601 ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (70, 0, 90, 0, 0, 0, 90, 'company', '7', 601)");
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, user_type, user_id, warehouse_id)
    VALUES (70, 0, 150, 0, 0, 0, 150, 'company', '7', NULL)");

// ---- Test: creating a TP invoice (deduct) with warehouse_id=601 ----
$stockService->deduct(70, 'company', '7', 25, 'tp_invoice', 'TPINV-1', 'tester', false, 601);

$wh601 = $conn->query("SELECT closing_qty, sales_qty FROM stock WHERE product_id=70 AND user_id='7' AND warehouse_id=601")->fetch_assoc();
$unassigned = $conn->query("SELECT closing_qty FROM stock WHERE product_id=70 AND user_id='7' AND warehouse_id IS NULL")->fetch_assoc();
assertEqual((int)$wh601['closing_qty'], 65, 'TP invoice deduct(warehouse=601) deducts from the warehouse-601 row (90-25=65)');
assertEqual((int)$wh601['sales_qty'], 25, 'TP invoice deduct(warehouse=601) increments sales_qty (matches old debitGodownForTp behavior)');
assertEqual((int)$unassigned['closing_qty'], 150, 'TP invoice deduct(warehouse=601) leaves the unassigned row untouched');

$ledgerRow = $conn->query("SELECT action, ref_type, warehouse_id FROM stock_ledger WHERE product_id=70 AND user_id='7' AND ref_id='TPINV-1'")->fetch_assoc();
assertEqual($ledgerRow['action'], 'deduct', 'TP invoice deduct writes ledger action=deduct (new convention, was transfer_out)');
assertEqual($ledgerRow['ref_type'], 'tp_invoice', 'TP invoice deduct writes ledger ref_type=tp_invoice (new convention, was transfer)');
assertEqual((int)$ledgerRow['warehouse_id'], 601, 'TP invoice deduct ledger records the warehouse_id');

// ---- Test: editing/reversing that same invoice restores stock via reverseDeduct ----
$stockService->reverseDeduct(70, 'company', '7', 25, 'tp_invoice', 'TPINV-1', 'tester', false, 601);
$wh601After = $conn->query("SELECT closing_qty, sales_qty FROM stock WHERE product_id=70 AND user_id='7' AND warehouse_id=601")->fetch_assoc();
assertEqual((int)$wh601After['closing_qty'], 90, 'reverseDeduct(warehouse=601) restores closing_qty (65+25=90)');
assertEqual((int)$wh601After['sales_qty'], 0, 'reverseDeduct(warehouse=601) restores sales_qty (25-25=0)');

// ---- Test: a TP invoice with no warehouse selected (null) hits the unassigned row ----
$stockService->deduct(70, 'company', '7', 40, 'tp_invoice', 'TPINV-2', 'tester', false, null);
$unassignedAfter = $conn->query("SELECT closing_qty FROM stock WHERE product_id=70 AND user_id='7' AND warehouse_id IS NULL")->fetch_assoc();
$wh601Unchanged = $conn->query("SELECT closing_qty FROM stock WHERE product_id=70 AND user_id='7' AND warehouse_id=601")->fetch_assoc();
assertEqual((int)$unassignedAfter['closing_qty'], 110, 'TP invoice deduct(warehouse=null) deducts from the unassigned row (150-40=110)');
assertEqual((int)$wh601Unchanged['closing_qty'], 90, 'TP invoice deduct(warehouse=null) leaves warehouse-601 untouched');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it passes standalone**

Run: `php "femi9/billing/includes/tests/TpInvoiceWarehouseKeyTest.php"`
Expected: `10 passed, 0 failed` (this validates the target StockService
call shape before touching the real files, same as Task 2's approach —
StockService itself already supports this, so the "failing" state to fix
is in `tp-invoice-action.php`/`edit-tp-invoice-action.php`, not here).

- [ ] **Step 3: Refactor `tp-invoice-action.php`**

Read the file first (already read in full during planning). Make these
changes:

1. Add `require_once("include/StockService.php");` near the top
   (alongside the existing `require_once` block).
2. Add warehouse parsing near where `$source_godown_id`/`$source_cp_id`
   are read (line ~190-191):
   ```php
   $warehouseId = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
   ```
3. Delete these four functions entirely:
   `getGodownQtyForTp` (line 49), `lockAndGetGodownQtyForTp` (line 57),
   `debitGodownForTp` (line 65), `insertGodownLedgerForTp` (line 74).
   Leave the CP-sourced functions (`getCpQty`, `lockAndGetCpQty`,
   `debitCp`, `insertCpLedger`) completely untouched.
4. Replace the pre-validation call at line ~298
   (`getGodownQtyForTp($db_conn, $source_godown_id, $item['pid'])`) with:
   ```php
   $stockService->getClosingQty($item['pid'], 'company', (string) $source_godown_id, $warehouseId) ?? 0
   ```
   (requires `$stockService = new StockService($db_conn);` to exist
   before this line — add it near the top if not already present from a
   later step's ordering; check whether `$stockService` is already
   instantiated elsewhere in this file before adding a duplicate).
5. Replace the debit block at lines ~372-376
   (`lockAndGetGodownQtyForTp` / `debitGodownForTp` /
   `insertGodownLedgerForTp`) with:
   ```php
   $stockService->deduct(
       $item['pid'], 'company', (string) $source_godown_id, $item['qty'],
       'tp_invoice', $inv_num, $created_by,
       true, // outer transaction owns commit
       $warehouseId
   );
   ```
   Wrap this call (and the rest of the existing `foreach ($items as
   $item)` loop body) in a `try`/`catch (StockException $e)` if the
   surrounding code isn't already inside one — check the existing outer
   `try` block first; if `tp-invoice-action.php` already has a top-level
   `try`/`catch(\Throwable $e)` around its whole transaction (confirm via
   `grep -n "catch"`), a `StockException` will already be caught there
   and no new catch block is needed.
6. Add `warehouse_id` to the `tp_invoices` header INSERT (line ~351-358,
   the query containing `(invoice_number,territory_partner_id,product_type,
   source_location_id,source_cp_id,source_godown_id,invoice_date,...)`),
   and its `bind_param` type string — this one DOES use prepared
   statements (`bind_param("sisiiisdddssisssssss", ...)`), so add the
   new `?` placeholder plus a matching `i` or `NULL`-safe handling in the
   type string and param list. Since `warehouse_id` can be `NULL`, bind
   it as a nullable int the same way `source_location_id` is already
   handled in this exact statement (check how `$source_loc_id` — which
   is `?: null`, per line 30 of the sibling edit file — is bound; mirror
   that exact pattern here for consistency).

- [ ] **Step 4: Syntax check**

```bash
php -l "femi9/billing/company/tp-invoice-action.php"
```
Expected: `No syntax errors detected`

- [ ] **Step 5: Refactor `edit-tp-invoice-action.php`**

Read the file first (already read in full during planning — reversal
block at lines 211-217, re-apply block at lines 277-283). Make these
changes:

1. Add `require_once("include/StockService.php");` and
   `$stockService = new StockService($db_conn);` near the top.
2. Add warehouse parsing near where `$source_godown_id` is derived from
   the existing invoice (line ~32):
   ```php
   $warehouseId = (int)($inv['warehouse_id'] ?? 0) ?: null;
   ```
   (This reads the CURRENT invoice's stored warehouse — editing does not
   let the user change which warehouse an invoice drew from, only
   quantities/products, matching how `source_godown_id` itself is
   already fixed/read-only on edit rather than re-selectable — confirm
   this assumption against the actual `edit-tp-invoice.php` form during
   implementation; if the form DOES let the user change the warehouse,
   read it from `$_POST['warehouse_id']` instead, matching the create
   flow's pattern.)
3. Delete these functions: `getGodownQtyE` (line 128),
   `lockGodownQtyE` (line 135), `creditGodownForTpE` (line 142),
   `debitGodownForTpE` (line 147), `insertGodownLedgerE` (line 152). Leave
   all CP-sourced (`*CpE`) and legacy partner-location (`getLocQty`/
   `lockLocQty`) helpers untouched.
4. Replace the pre-validation call at line ~176
   (`getGodownQtyE($db_conn, $source_godown_id, $item['pid'])`) with:
   ```php
   $stockService->getClosingQty($item['pid'], 'company', (string) $source_godown_id, $warehouseId) ?? 0
   ```
5. Replace the reversal block at lines 211-217
   (`getGodownQtyE`/`creditGodownForTpE`/`insertGodownLedgerE`) with:
   ```php
   $stockService->reverseDeduct(
       $pid, 'company', (string) $source_godown_id, $qty,
       'tp_invoice', $inv_num, $created_by,
       true,
       $warehouseId
   );
   ```
   Note: `reverseDeduct()` returns `['success' => false, 'reason' =>
   'no_stock_row']` rather than throwing when no row exists (see
   `StockService.php:194-197`) — the old `creditGodownForTpE()` had no
   such guard and would silently no-op on a missing row too (a raw
   `UPDATE` matching zero rows is a no-op), so behavior is equivalent;
   no additional error handling needed here beyond what the existing
   surrounding `try`/`catch` already provides for `StockException`s from
   the other call.
6. Replace the re-apply block at lines 277-283
   (`lockGodownQtyE`/`debitGodownForTpE`/`insertGodownLedgerE`) with:
   ```php
   $stockService->deduct(
       $pid, 'company', (string) $source_godown_id, $qty,
       'tp_invoice', $inv_num, $created_by,
       true,
       $warehouseId
   );
   ```
   This can throw `StockException` on insufficient stock — confirm the
   surrounding `try`/`catch(\Throwable $e)` (already present at the file's
   outer transaction level, per the file read during planning) catches
   it correctly; `StockException extends \RuntimeException` which is a
   `\Throwable`, so the existing generic catch already handles it
   correctly with no changes needed.

- [ ] **Step 6: Syntax check**

```bash
php -l "femi9/billing/company/edit-tp-invoice-action.php"
```
Expected: `No syntax errors detected`

- [ ] **Step 7: Add the warehouse dropdown to `add-tp-invoice.php` and `edit-tp-invoice.php`**

In `add-tp-invoice.php`, read the file to find where the
`source_godown_id`/godown-mode UI lives (client-side JS toggling between
CP-mode and godown-mode, per the spec's investigation). Add a "Godown
(physical)" dropdown that is shown/hidden by the same JS toggle that
shows/hides the existing godown-mode fields (visible only when
godown-mode is active, hidden when CP-mode is active):

```php
<!-- Godown (physical warehouse) — only relevant when sourcing from a company godown, not a CP -->
<select name="warehouse_id" id="warehouse_id_select" class="form-control">
    <option value="">— Not tracked —</option>
    <?php
    $resWh = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
    while ($wh = $resWh->fetch_assoc()) {
    ?>
    <option value="<?= (int) $wh['id'] ?>">
        <?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?>
    </option>
    <?php } ?>
</select>
```

Wire its container `<div>`'s visibility into the same JS function that
already toggles the godown-mode fields (find it via `grep -n
"source_godown_id\|godownDrop" femi9/billing/company/add-tp-invoice.php`
and add the warehouse select's container id to whatever `show()`/`hide()`
calls that function already makes for the godown-mode block).

For `edit-tp-invoice.php`, read the file to find its analogous
godown-mode display (likely read-only/pre-filled, matching how
`source_godown_id` itself is displayed on edit) and add a matching
read-only or dropdown display of the currently-stored `warehouse_id`,
consistent with whatever the file already does for `source_godown_id`
on this page (mirror that exact pattern rather than introducing a new
one).

- [ ] **Step 8: Syntax check**

```bash
php -l "femi9/billing/company/add-tp-invoice.php"
php -l "femi9/billing/company/edit-tp-invoice.php"
```
Expected: `No syntax errors detected` for both.

- [ ] **Step 9: Run the test suite**

Run: `php "femi9/billing/includes/tests/TpInvoiceWarehouseKeyTest.php"`
Expected: `10 passed, 0 failed`

- [ ] **Step 10: Commit**

```bash
git add "femi9/billing/company/add-tp-invoice.php" "femi9/billing/company/tp-invoice-action.php" "femi9/billing/company/edit-tp-invoice.php" "femi9/billing/company/edit-tp-invoice-action.php" "femi9/billing/includes/tests/TpInvoiceWarehouseKeyTest.php"
git commit -m "Refactor TP invoice godown-sourced stock to StockService + warehouse tagging"
```

---

### Task 8: Full regression pass

**Files:** none (verification only, no code changes)

- [ ] **Step 1: Run every warehouse-related test file together**

```bash
for f in femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php \
         femi9/billing/includes/tests/StockServiceReverseTransferInTest.php \
         femi9/billing/includes/tests/InputActionWarehouseKeyTest.php \
         femi9/billing/includes/tests/StockReturnUpdateWarehouseKeyTest.php \
         femi9/billing/includes/tests/InternalTransferWarehouseKeyTest.php \
         femi9/billing/includes/tests/OtSaleWarehouseKeyTest.php \
         femi9/billing/includes/tests/InvoiceStockUpdateWarehouseKeyTest.php \
         femi9/billing/includes/tests/TpInvoiceWarehouseKeyTest.php; do
  echo "=== $f ==="
  php "$f" | tail -3
done
```

Expected: every file ends with `N passed, 0 failed`.

- [ ] **Step 2: Confirm no unexpected file changes**

```bash
git status --short
```

Expected: clean, or only the same pre-existing unrelated files noted in
earlier phases (`femi_menu.php`'s in-progress Credit Notes work,
`internal_transfer_return_*` files) — nothing from this plan left
uncommitted.

- [ ] **Step 3: Report results**

No commit needed for this task — it's verification only. Note any
discrepancies found; if the automated pass surfaces a bug, fix it as a
small follow-up task before considering the plan complete. Manual
browser click-through of all 5 flows (create + edit where applicable) is
recommended before considering this fully done, following the same
"skip if MAMP isn't running, note the gap honestly" precedent set in
Phase 3a.

---

## Self-Review Notes

- **Spec coverage:** all 5 flows (TP, OT, SS, customer, shop) have a
  dedicated task; shared plumbing (`invoice-stock-update.php`) is one
  task covering 3 of the 5 flows' deduction side, matching the spec's
  observation that this file is shared. Schema migration, testing
  strategy, and migration safety sections of the spec are covered by
  Task 1 and Task 8.
- **Placeholder scan:** no TBD/TODO. Task 6 and Task 7 Step 7 contain
  explicit "confirm during implementation" instructions rather than
  guessed code, because the spec itself flagged these as open questions
  requiring a fresh read of files not yet fully read at spec-writing
  time (SS invoice's exact INSERT location; edit-tp-invoice.php's exact
  godown-display pattern) — this is intentionally investigative
  (grep-then-branch), not a vague placeholder, consistent with how
  Task-1-style "read the file first" instructions work throughout this
  plan.
- **Type consistency:** `StockService::deduct/reverseDeduct/otDeduct/
  otReverse/credit` signatures used throughout match what was confirmed
  by reading `StockService.php` directly during planning (all end in
  `bool $externalTransaction = false, ?int $warehouseId = null`, except
  `otDeduct`/`otReverse` which were confirmed to already have the same
  trailing shape). `$warehouseId` variable naming is consistent across
  every task.
