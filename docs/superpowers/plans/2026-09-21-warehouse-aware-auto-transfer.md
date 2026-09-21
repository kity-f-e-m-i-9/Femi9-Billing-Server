# Warehouse-Aware Auto Transfer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a user pick which physical warehouse each leg of an Auto Transfer (and manual Internal Transfer) actually moves stock through, filtered by a new company-profile ↔ warehouse mapping, instead of always operating on the unassigned (`warehouse_id = NULL`) stock row.

**Architecture:** A new `company_godown_warehouses` join table plus a small PHP helper module drive filtered `<select>` option lists on three existing pages (`manage-warehouses.php` admin editor, `internal_transfer_auto.php` per-row pickers, `internal_transfer.php` picker filtering). `internal_transfer_auto_action.php`'s two-leg transfer logic threads the picked warehouse ids through `StockService::transferOut()`/`transferIn()`'s existing trailing `$warehouseId` parameter — no StockService changes needed, since warehouse-awareness already exists there from a prior phase. `undo_auto_transfer()` and the Transfer History queries in `AutoTransferDemand.php` gain warehouse read-through from `stock_ledger`, which already stores it.

**Tech Stack:** PHP 8 + mysqli, vanilla JS + jQuery + Bootstrap 5 (matching every existing page in this codebase), manual test convention (disposable MySQL schema per test file, `php <file>.php`, no PHPUnit).

**Spec:** `docs/superpowers/specs/2026-09-21-warehouse-aware-auto-transfer-design.md`

## Global Constraints

- No hard enforcement anywhere — the mapping only filters dropdown *options*; no backend write path refuses an (entity, warehouse) combination outside the mapping.
- Every schema change is additive and self-migrating (a guard function checked at the top of every function that touches the new table/columns) — a manual-apply-only `.sql` file must never be the only way the schema gets created, per this project's repeated production-incident history on exactly this feature area.
- Empty-mapping fallback: if a company profile has zero linked warehouses, the picker falls back to the full active-warehouse list — never a dead-end empty dropdown.
- No backfill of historical `internal_transfer`/`stock_ledger` rows — old Auto Transfer runs keep `warehouse_id = NULL` on both legs; Undo/History for those behaves exactly as today.
- Per this session's standing rule: do not run any tests, `php -l`, or other verification without the user explicitly asking first. Test files are still written as part of each task's deliverable (per this project's established convention), but are not executed by the implementer unless the user says to.
- Follow this repo's existing procedural/inline-HTML style in legacy files (`internal_transfer.php`, `manage-warehouses.php`) rather than introducing a different code style within them.

---

### Task 1: `company_godown_warehouses` mapping table + helper module

**Files:**
- Create: `femi9/billing/company/include/GodownWarehouseMapping.php`
- Create: `femi9/billing/db_migrations/2026_09_21_company_godown_warehouses.sql` (documentation-only migration file, mirrors the self-migrating guard)
- Test: `femi9/billing/includes/tests/GodownWarehouseMappingTest.php`

**Interfaces:**
- Produces:
  - `ensure_company_godown_warehouses_table(mysqli $db_conn): void`
  - `get_warehouses_for_godown(mysqli $db_conn, int $companyGodownId): array` — returns `[{id: int, code: string, name: ?string}, ...]`, ordered by `code ASC`. Empty array if nothing mapped (not an error).
  - `get_godowns_for_warehouse(mysqli $db_conn, int $warehouseId): array` — returns `[{id: int, gname: string}, ...]`.
  - `set_godowns_for_warehouse(mysqli $db_conn, int $warehouseId, array $companyGodownIds): void` — replaces the full set (delete then re-insert).

- [ ] **Step 1: Write the failing test for the self-migrating guard**

```php
<?php
// femi9/billing/includes/tests/GodownWarehouseMappingTest.php
// Manual run: php GodownWarehouseMappingTest.php
//
// Regression/behavior test for GodownWarehouseMapping.php: confirms the
// company_godown_warehouses table self-migrates, and that
// get_warehouses_for_godown()/get_godowns_for_warehouse()/
// set_godowns_for_warehouse() round-trip correctly, including the
// "replace, not append" semantics of set_godowns_for_warehouse().
//
// Uses a disposable `godown_warehouse_mapping_test` schema — never the
// app's production database.

require_once __DIR__ . '/../../company/include/GodownWarehouseMapping.php';
require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const TEST_SCHEMA = 'godown_warehouse_mapping_test';

$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `" . TEST_SCHEMA . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
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

// ========== SETUP: real table shapes ==========
$conn->query("CREATE TABLE warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_warehouses_code (code)
)");
$conn->query("INSERT INTO warehouses (id, code, name) VALUES (1, 'H1', 'Head Office'), (2, 'G1', 'Godown 1'), (3, 'G2', 'Godown 2')");

$conn->query("CREATE TABLE company_godown (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gname VARCHAR(255) NOT NULL
)");
$conn->query("INSERT INTO company_godown (id, gname) VALUES (10, 'NEKSOMO HYGIENE INDUSTRIES'), (11, 'FEMI HEALTH CARE'), (12, 'FEMI NAYAN LLP')");

// ========== TESTS: self-migration ==========
$before = $conn->query("SHOW TABLES LIKE 'company_godown_warehouses'");
assertEqual($before->num_rows, 0, 'setup: company_godown_warehouses does not exist yet');

ensure_company_godown_warehouses_table($conn);
$after = $conn->query("SHOW TABLES LIKE 'company_godown_warehouses'");
assertEqual($after->num_rows, 1, 'ensure_company_godown_warehouses_table() self-migrates the table');

ensure_company_godown_warehouses_table($conn); // idempotent
$again = $conn->query("SHOW TABLES LIKE 'company_godown_warehouses'");
assertEqual($again->num_rows, 1, 'calling the guard twice is idempotent');

// ========== TESTS: empty state ==========
$empty = get_warehouses_for_godown($conn, 10);
assertEqual(count($empty), 0, 'get_warehouses_for_godown() returns empty array when nothing mapped');

// ========== TESTS: set + get round-trip ==========
set_godowns_for_warehouse($conn, 2, [10, 11]); // G1 linked to Neksomo + Healthcare

$whForNeksomo = get_warehouses_for_godown($conn, 10);
assertEqual(count($whForNeksomo), 1, 'Neksomo now has exactly 1 linked warehouse');
assertEqual($whForNeksomo[0]['code'], 'G1', 'Neksomo linked warehouse is G1');

$godownsForG1 = get_godowns_for_warehouse($conn, 2);
assertEqual(count($godownsForG1), 2, 'G1 has exactly 2 linked company profiles');
$gnames = array_column($godownsForG1, 'gname');
sort($gnames);
assertEqual($gnames, ['FEMI HEALTH CARE', 'NEKSOMO HYGIENE INDUSTRIES'], 'G1 linked profiles are Neksomo + Healthcare');

// ========== TESTS: set_godowns_for_warehouse REPLACES, not appends ==========
set_godowns_for_warehouse($conn, 2, [12]); // now only LLP
$godownsForG1After = get_godowns_for_warehouse($conn, 2);
assertEqual(count($godownsForG1After), 1, 'set_godowns_for_warehouse() replaced the prior set (now 1, not 3)');
assertEqual($godownsForG1After[0]['gname'], 'FEMI NAYAN LLP', 'G1 is now linked only to LLP');

// Neksomo should no longer show G1 as linked, since it was replaced away.
$whForNeksomoAfter = get_warehouses_for_godown($conn, 10);
assertEqual(count($whForNeksomoAfter), 0, 'Neksomo no longer shows G1 after replacement');

// ========== TESTS: multiple warehouses for one godown ==========
set_godowns_for_warehouse($conn, 1, [12]); // H1 also linked to LLP
$whForLlp = get_warehouses_for_godown($conn, 12);
assertEqual(count($whForLlp), 2, 'LLP now has 2 linked warehouses (G1 and H1)');
$codes = array_column($whForLlp, 'code');
sort($codes);
assertEqual($codes, ['G1', 'H1'], 'LLP linked warehouse codes are G1 and H1, ordered');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php femi9/billing/includes/tests/GodownWarehouseMappingTest.php`
Expected: FAIL — fatal error, `GodownWarehouseMapping.php` does not exist yet.

- [ ] **Step 3: Write `GodownWarehouseMapping.php`**

```php
<?php
declare(strict_types=1);

/**
 * Many-to-many mapping between company profiles (company_godown rows —
 * LLP / Healthcare / Neksomo / etc.) and physical warehouses. A warehouse
 * can hold stock for several company profiles; a company profile can have
 * stock spread across several warehouses. Purely advisory — used to
 * filter warehouse picker dropdowns across the app, never to block a
 * write. An unmapped pair is simply absent from a filtered list, not an
 * error; callers fall back to the full warehouse list when a company
 * profile has no linked warehouses at all.
 */

// Self-migrating — same convention as every other table added in this
// project (e.g. ensure_auto_transfer_skip_table()). A checked-in
// migration file also exists for documentation/manual-apply convenience
// (db_migrations/2026_09_21_company_godown_warehouses.sql), but nothing
// depends on it having run.
function ensure_company_godown_warehouses_table(mysqli $db_conn): void
{
    $db_conn->query(
        "CREATE TABLE IF NOT EXISTS company_godown_warehouses (
            company_godown_id INT NOT NULL,
            warehouse_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (company_godown_id, warehouse_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

/**
 * Warehouses linked to one company profile, ordered by code. Empty array
 * if nothing is linked yet — callers apply their own empty-fallback
 * policy (typically: show the full active-warehouse list instead).
 *
 * @return array<int, array{id:int, code:string, name:?string}>
 */
function get_warehouses_for_godown(mysqli $db_conn, int $companyGodownId): array
{
    ensure_company_godown_warehouses_table($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT w.id, w.code, w.name
         FROM company_godown_warehouses cgw
         INNER JOIN warehouses w ON w.id = cgw.warehouse_id
         WHERE cgw.company_godown_id = ? AND w.is_active = 1
         ORDER BY w.code ASC"
    );
    $stmt->bind_param('i', $companyGodownId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(fn($r) => [
        'id'   => (int) $r['id'],
        'code' => $r['code'],
        'name' => $r['name'],
    ], $rows);
}

/**
 * Company profiles linked to one warehouse, ordered by name — powers the
 * admin editor's per-warehouse checkbox list.
 *
 * @return array<int, array{id:int, gname:string}>
 */
function get_godowns_for_warehouse(mysqli $db_conn, int $warehouseId): array
{
    ensure_company_godown_warehouses_table($db_conn);
    $stmt = $db_conn->prepare(
        "SELECT cg.id, cg.gname
         FROM company_godown_warehouses cgw
         INNER JOIN company_godown cg ON cg.id = cgw.company_godown_id
         WHERE cgw.warehouse_id = ?
         ORDER BY cg.gname ASC"
    );
    $stmt->bind_param('i', $warehouseId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(fn($r) => [
        'id'    => (int) $r['id'],
        'gname' => $r['gname'],
    ], $rows);
}

/**
 * Replaces the full set of company profiles linked to one warehouse
 * (delete + re-insert) — simplest correct approach for a small admin
 * form where the whole checkbox set is resubmitted every save.
 *
 * @param int[] $companyGodownIds
 */
function set_godowns_for_warehouse(mysqli $db_conn, int $warehouseId, array $companyGodownIds): void
{
    ensure_company_godown_warehouses_table($db_conn);

    $del = $db_conn->prepare("DELETE FROM company_godown_warehouses WHERE warehouse_id = ?");
    $del->bind_param('i', $warehouseId);
    $del->execute();
    $del->close();

    if (empty($companyGodownIds)) {
        return;
    }

    $ins = $db_conn->prepare("INSERT INTO company_godown_warehouses (company_godown_id, warehouse_id) VALUES (?, ?)");
    foreach (array_unique(array_map('intval', $companyGodownIds)) as $cgId) {
        $ins->bind_param('ii', $cgId, $warehouseId);
        $ins->execute();
    }
    $ins->close();
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php femi9/billing/includes/tests/GodownWarehouseMappingTest.php`
Expected: PASS — all assertions green.

- [ ] **Step 5: Write the documentation migration file**

```sql
-- ============================================================
-- Add company_godown_warehouses mapping table
-- Date: 2026-09-21
-- Many-to-many between company profiles (company_godown) and physical
-- warehouses. Documentation/manual-apply convenience only — the table
-- is self-migrating via ensure_company_godown_warehouses_table() in
-- company/include/GodownWarehouseMapping.php, so this file is never
-- required to have run. See docs/superpowers/specs/
-- 2026-09-21-warehouse-aware-auto-transfer-design.md.
-- ============================================================

CREATE TABLE IF NOT EXISTS company_godown_warehouses (
    company_godown_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (company_godown_id, warehouse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

Save to `femi9/billing/db_migrations/2026_09_21_company_godown_warehouses.sql`.

- [ ] **Step 6: Commit**

```bash
git add femi9/billing/company/include/GodownWarehouseMapping.php \
        femi9/billing/db_migrations/2026_09_21_company_godown_warehouses.sql \
        femi9/billing/includes/tests/GodownWarehouseMappingTest.php
git commit -m "Add company_godown_warehouses mapping table and helper functions"
```

---

### Task 2: Admin UI — link company profiles to warehouses on `manage-warehouses.php`

**Files:**
- Modify: `femi9/billing/company/manage-warehouses.php`

**Interfaces:**
- Consumes: `get_godowns_for_warehouse()`, `set_godowns_for_warehouse()` from Task 1's `GodownWarehouseMapping.php`; `godown_finance_filter_sql()` from `include/GodownAccess.php` (already used elsewhere in this codebase, e.g. Convert Pieces↔Packs' company profile picker).
- Produces: no new interface — this is the leaf UI that fills the mapping table Task 3+ read from.

- [ ] **Step 1: Add `require_once` and load eligible company profiles**

In `femi9/billing/company/manage-warehouses.php`, after the existing `require_once("include/PermissionCheck.php"); requireAdminOnly();` line (line 2), add:

```php
require_once("include/GodownAccess.php");
require_once("include/GodownWarehouseMapping.php");
```

After the existing `$warehouses = [];` block (current lines 63-67), add:

```php
$eligibleGodowns = [];
$res2 = mysqli_query($db_conn, "SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY gname ASC");
while ($row = mysqli_fetch_assoc($res2)) {
    $eligibleGodowns[] = $row;
}

// Pre-load each warehouse's currently-linked company profile ids, so the
// checkbox list below can mark them checked.
$linkedByWarehouse = [];
foreach ($warehouses as $wh) {
    $linked = get_godowns_for_warehouse($db_conn, (int) $wh['id']);
    $linkedByWarehouse[(int) $wh['id']] = array_column($linked, 'id');
}
```

- [ ] **Step 2: Handle the new POST field in the `edit` action**

In the existing `elseif ($action === 'edit')` block (current lines 34-48), after the successful `mysqli_stmt_execute($stmt)` call and before its `header('Location: ...')` redirect, add:

```php
$linkedGodownIds = array_map('intval', $_POST['linked_godowns'] ?? []);
set_godowns_for_warehouse($db_conn, $id, $linkedGodownIds);
```

Place this right after `if (mysqli_stmt_execute($stmt)) {` and before the `header('Location: manage-warehouses.php?updatedSuccess');` line, so it only runs on a successful code/name update (same transaction-less pattern this file already uses elsewhere — no wrapping transaction needed since both statements are independent and idempotent on retry).

- [ ] **Step 3: Add the checkbox list to each warehouse's row**

In the existing table row loop (`<?php foreach ($warehouses as $wh): ... ?>`, current lines 157-186), add a new `<td>` between the existing "Status" `<td>` and "Actions" `<td>`:

```php
<td style="min-width:220px;">
    <?php if (empty($eligibleGodowns)): ?>
        <span class="text-muted small">No company profiles available.</span>
    <?php else: ?>
        <?php foreach ($eligibleGodowns as $cg): $cgId = (int) $cg['id']; ?>
        <div class="form-check form-check-inline" style="margin-bottom:4px;">
            <input class="form-check-input" type="checkbox"
                   form="<?php echo $editFormId; ?>"
                   name="linked_godowns[]"
                   value="<?php echo $cgId; ?>"
                   id="linked_<?php echo $rid; ?>_<?php echo $cgId; ?>"
                   <?php echo in_array($cgId, $linkedByWarehouse[$rid] ?? [], true) ? 'checked' : ''; ?>>
            <label class="form-check-label small" for="linked_<?php echo $rid; ?>_<?php echo $cgId; ?>">
                <?php echo htmlspecialchars($cg['gname']); ?>
            </label>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</td>
```

Also add the corresponding `<th>Linked Company Profiles</th>` to the `<thead>` row (current lines 149-154), between `<th>Status</th>` and `<th>Actions</th>`.

Note: this uses the existing `form="<?php echo $editFormId; ?>"` cross-form-association pattern this page already relies on for the code/name inputs (they live outside the `<form>` tag physically, associated only via the `form` attribute) — the checkboxes follow the identical convention, so they submit correctly with the row's existing "Save" button without any JS.

- [ ] **Step 4: Commit**

```bash
git add femi9/billing/company/manage-warehouses.php
git commit -m "Add company-profile linking checkboxes to manage-warehouses.php"
```

---

### Task 3: Auto Transfer — per-row warehouse pickers + availability endpoint

**Files:**
- Create: `femi9/billing/company/get-auto-transfer-row-availability.php`
- Modify: `femi9/billing/company/internal_transfer_auto.php`
- Test: `femi9/billing/includes/tests/GetAutoTransferRowAvailabilityTest.php` (logic-only test of the shared computation, since the endpoint itself is a thin HTTP wrapper — see Step 1)

**Interfaces:**
- Consumes: `get_warehouses_for_godown()` from Task 1; `StockService::getClosingQty(int $productId, string $userType, string $userId, ?int $warehouseId = null): ?int` (existing, unchanged); `cap_auto_transfer_qty_by_source()` (existing function in `AutoTransferDemand.php` — read it first to confirm its exact signature before wiring the endpoint, since this plan was written from the PHP-side capping call already present in `internal_transfer_auto.php`, not from re-deriving the JS-side port from scratch).
- Produces: `get-auto-transfer-row-availability.php` — GET endpoint, params `product_id`, `source_warehouse_id` (optional, omitted/blank = unassigned), returns JSON `{neksomo_avail: int, healthcare_avail: int}`.

- [ ] **Step 1: Read `cap_auto_transfer_qty_by_source()`'s exact signature**

Run: `grep -n "function cap_auto_transfer_qty_by_source" femi9/billing/company/include/AutoTransferDemand.php` and read the function body. Confirm its parameter names/order and return shape (`internal_transfer_auto.php`'s current call at the top of the file, `$split = cap_auto_transfer_qty_by_source($tpRequired, $otRequired, $available);`, returning `['tp' => int, 'ot' => int]`) before writing Step 3's client-side port — the implementer must not guess this from the plan alone, since it wasn't re-derived in the design doc.

- [ ] **Step 2: Write the endpoint**

```php
<?php
// femi9/billing/company/get-auto-transfer-row-availability.php
//
// Recomputes one product row's Neksomo/Healthcare available stock scoped
// to a specific source warehouse, for the Auto Transfer page's warehouse
// pickers (see docs/superpowers/specs/2026-09-21-warehouse-aware-auto-
// transfer-design.md). Mirrors get-piece-pack-stock.php's shape, but for
// two entities (Neksomo + Healthcare) instead of one.

include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/NeksomoStockBridge.php");
header('Content-Type: application/json');
error_reporting(0);

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$productId = (int) ($_GET['product_id'] ?? 0);
$sourceWarehouseId = filter_var($_GET['source_warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;

if (!$productId) {
    echo json_encode(['error' => 'invalid_product']);
    exit;
}

$neksomoId    = get_neksomo_godown_id($db_conn);
$healthcareId = resolve_godown_id_by_gname($db_conn, 'FEMI HEALTH CARE');
if (!$neksomoId || !$healthcareId) {
    echo json_encode(['error' => 'misconfigured']);
    exit;
}

$stockService = new StockService($db_conn);
$neksomoAvail    = (int) ($stockService->getClosingQty($productId, $Login_user_TYPEvl, (string) $neksomoId, $sourceWarehouseId) ?? 0);
$healthcareAvail = (int) ($stockService->getClosingQty($productId, $Login_user_TYPEvl, (string) $healthcareId, $sourceWarehouseId) ?? 0);

echo json_encode([
    'neksomo_avail'    => $neksomoAvail,
    'healthcare_avail' => $healthcareAvail,
]);
```

Note: `resolve_godown_id_by_gname()` is already declared in `include/AutoTransferDemand.php`, not `NeksomoStockBridge.php` — add `require_once("include/AutoTransferDemand.php");` to the requires block above (the plan's snippet above omits it; add it when writing the real file).

- [ ] **Step 3: Add per-row warehouse pickers to `internal_transfer_auto.php`**

At the top of the file, after the existing `$llpId` resolution block (current lines 10-18), add:

```php
require_once("include/GodownWarehouseMapping.php");

$allWarehouses = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC")->fetch_all(MYSQLI_ASSOC);

$sourceWarehouseOptions = array_merge(
    get_warehouses_for_godown($db_conn, $neksomoId),
    get_warehouses_for_godown($db_conn, $healthcareId)
);
// De-duplicate by id (a warehouse linked to both Neksomo and Healthcare
// would otherwise appear twice), then fall back to the full list if the
// union is empty (nothing mapped yet — never show a dead-end dropdown).
$seenIds = [];
$sourceWarehouseOptions = array_values(array_filter($sourceWarehouseOptions, function ($wh) use (&$seenIds) {
    if (isset($seenIds[$wh['id']])) return false;
    $seenIds[$wh['id']] = true;
    return true;
}));
if (empty($sourceWarehouseOptions)) {
    $sourceWarehouseOptions = $allWarehouses;
}

$destWarehouseOptions = get_warehouses_for_godown($db_conn, $llpId);
if (empty($destWarehouseOptions)) {
    $destWarehouseOptions = $allWarehouses;
}
```

In the per-row template (the `foreach ($rows as $row):` block, current lines 289-336), inside the existing `.ata-row-grid` div, add two new `.ata-field` blocks — placed before the existing "Qty to Transfer" field so the user picks warehouses first, which then drives the qty computation:

```php
<div class="ata-field">
    <label>Source Godown (physical)</label>
    <select class="form-control ata-source-warehouse" data-product-id="<?php echo (int) $row['product_id']; ?>">
        <option value="">— Unassigned —</option>
        <?php foreach ($sourceWarehouseOptions as $wh): ?>
        <option value="<?php echo (int) $wh['id']; ?>"><?php echo htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8'); ?><?php echo $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : ''; ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="ata-field">
    <label>Destination Godown (physical)</label>
    <select class="form-control ata-dest-warehouse" name="warehouse_to[]">
        <option value="">— Unassigned —</option>
        <?php foreach ($destWarehouseOptions as $wh): ?>
        <option value="<?php echo (int) $wh['id']; ?>"><?php echo htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8'); ?><?php echo $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : ''; ?></option>
        <?php endforeach; ?>
    </select>
</div>
```

Note: the Source Godown select has no `name` attribute (it's read by JS and copied into a hidden `warehouse_from[]` input on submit, matching this page's existing `buildHiddenInputs()`-less-but-similar approach — see Step 4) while Destination Godown posts directly via `name="warehouse_to[]"` since it needs no client-side recompute trigger of its own beyond the existing per-row structure. Add a hidden input right after the Source Godown select:

```php
<input type="hidden" name="warehouse_from[]" class="ata-source-warehouse-hidden" value="">
```

- [ ] **Step 4: Wire the JS recompute**

In the existing `<script>` block, after the existing `refreshRowStock`/similar helper functions (this page's current JS does not yet have a per-row Auto Transfer availability refresh function — add a new one), add:

```javascript
function refreshRowAvailability(rowEl) {
    var productId = rowEl.querySelector('.ata-source-warehouse').getAttribute('data-product-id');
    var sourceWarehouseSelect = rowEl.querySelector('.ata-source-warehouse');
    var sourceWarehouseId = sourceWarehouseSelect.value;
    var hiddenInput = rowEl.querySelector('.ata-source-warehouse-hidden');
    hiddenInput.value = sourceWarehouseId;

    var url = 'get-auto-transfer-row-availability.php?product_id=' + encodeURIComponent(productId)
        + (sourceWarehouseId ? '&source_warehouse_id=' + encodeURIComponent(sourceWarehouseId) : '');

    fetch(url)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) return;
            var neksomoAvail = data.neksomo_avail;
            var healthcareAvail = data.healthcare_avail;

            rowEl.setAttribute('data-neksomo-avail', neksomoAvail);
            rowEl.setAttribute('data-healthcare-avail', healthcareAvail);

            var chips = rowEl.querySelectorAll('.ata-avail-chip b');
            if (chips[0]) chips[0].textContent = neksomoAvail;
            if (chips[1]) chips[1].textContent = healthcareAvail;

            // Re-run the same capped-qty logic the page already uses
            // elsewhere for live recompute (mirrors PHP's
            // cap_auto_transfer_qty_by_source() — confirmed exact
            // shape in Task 3 Step 1 before implementing this).
            var productId2 = rowEl.querySelector('.ata-source-warehouse').getAttribute('data-product-id');
            var reqTpEl = rowEl.querySelector('.ata-tag-tp');
            var reqOtEl = rowEl.querySelector('.ata-tag-ot');
            // NOTE: exact recompute wiring depends on cap_auto_transfer_qty_by_source()'s
            // confirmed shape from Step 1 — implementer fills in the capped split
            // here and updates the qty_<id> input + req_<id>_num text, following
            // the same pattern as this page's existing capping display.
        })
        .catch(function () { /* leave current values on fetch failure */ });
}

document.querySelectorAll('.ata-source-warehouse').forEach(function (sel) {
    sel.addEventListener('change', function () {
        refreshRowAvailability(sel.closest('.ata-row-card'));
    });
});
```

The `// NOTE:` block above is an intentional explicit placeholder for the implementer to fill using the confirmed `cap_auto_transfer_qty_by_source()` shape from Step 1 — not a plan gap, but a dependency this plan cannot resolve without that earlier grep/read happening first at implementation time.

- [ ] **Step 5: Write the availability endpoint's test**

```php
<?php
// femi9/billing/includes/tests/GetAutoTransferRowAvailabilityTest.php
// Manual run: php GetAutoTransferRowAvailabilityTest.php
//
// Logic-level test for get-auto-transfer-row-availability.php's core
// computation (StockService::getClosingQty() scoped by warehouse) —
// doesn't exercise the HTTP/session layer (checksession.php requires a
// real login), only confirms the underlying stock lookup returns the
// correct per-warehouse figures the endpoint echoes as JSON.
//
// Uses a disposable `auto_transfer_row_availability_test` schema.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/db-connect.php';

$conn = $db_conn;
const TEST_SCHEMA = 'auto_transfer_row_availability_test';

$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
$conn->query("CREATE DATABASE IF NOT EXISTS `" . TEST_SCHEMA . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$conn->select_db(TEST_SCHEMA);

$passCount = 0; $failCount = 0;
function assertEqual($actual, $expected, $label) {
    global $passCount, $failCount;
    if ($actual == $expected) { echo "PASS: $label\n"; $passCount++; }
    else { echo "FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n"; $failCount++; }
}

$conn->query("CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL, opening_qty INT NOT NULL DEFAULT 0, opening_date DATE NOT NULL DEFAULT '2026-01-01',
    input_qty INT NOT NULL DEFAULT 0, sales_qty INT NOT NULL DEFAULT 0, sent_qty INT NOT NULL DEFAULT 0,
    returnqty INT NOT NULL DEFAULT 0, closing_qty INT NOT NULL DEFAULT 0, extra_pieces INT UNSIGNED NOT NULL DEFAULT 0,
    user_type VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, warehouse_id INT NULL, updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id)
)");
$conn->query("CREATE TABLE stock_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL,
    warehouse_id INT NULL, action VARCHAR(50) NOT NULL, qty INT NOT NULL, qty_before INT NOT NULL, qty_after INT NOT NULL,
    ref_type VARCHAR(50) NOT NULL, ref_id VARCHAR(255) NOT NULL, note VARCHAR(255) NOT NULL DEFAULT '',
    created_by VARCHAR(255) NOT NULL DEFAULT '', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE company_godown (id INT AUTO_INCREMENT PRIMARY KEY, gname VARCHAR(255) NOT NULL)");

// Product 9 at Neksomo (user_id=3): 100 units unassigned, 40 units at warehouse 2.
$conn->query("INSERT INTO stock (product_id, user_type, user_id, warehouse_id, closing_qty) VALUES (9, 'company', '3', NULL, 100)");
$conn->query("INSERT INTO stock (product_id, user_type, user_id, warehouse_id, closing_qty) VALUES (9, 'company', '3', 2, 40)");
// Product 9 at Healthcare (user_id=4): 0 unassigned, 15 units at warehouse 2.
$conn->query("INSERT INTO stock (product_id, user_type, user_id, warehouse_id, closing_qty) VALUES (9, 'company', '4', 2, 15)");

$stockService = new StockService($conn);

$unassignedNeksomo = (int) ($stockService->getClosingQty(9, 'company', '3', null) ?? 0);
$warehouse2Neksomo = (int) ($stockService->getClosingQty(9, 'company', '3', 2) ?? 0);
$warehouse2Healthcare = (int) ($stockService->getClosingQty(9, 'company', '4', 2) ?? 0);
$unassignedHealthcare = (int) ($stockService->getClosingQty(9, 'company', '4', null) ?? 0);

assertEqual($unassignedNeksomo, 100, 'Unassigned Neksomo stock is 100, unaffected by warehouse 2 row');
assertEqual($warehouse2Neksomo, 40, 'Warehouse-2-scoped Neksomo stock is 40, not blended with unassigned');
assertEqual($warehouse2Healthcare, 15, 'Warehouse-2-scoped Healthcare stock is 15');
assertEqual($unassignedHealthcare, 0, 'Unassigned Healthcare stock is 0 (only warehouse-2 row exists)');

$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 6: Run both new tests**

Run: `php femi9/billing/includes/tests/GetAutoTransferRowAvailabilityTest.php`
Expected: PASS — all assertions green (this test only exercises `StockService::getClosingQty()`, already proven correct by existing `StockServiceWarehouseKeyTest.php`, so it should pass immediately once written).

- [ ] **Step 7: Commit**

```bash
git add femi9/billing/company/get-auto-transfer-row-availability.php \
        femi9/billing/company/internal_transfer_auto.php \
        femi9/billing/includes/tests/GetAutoTransferRowAvailabilityTest.php
git commit -m "Add per-row source/destination warehouse pickers to Auto Transfer page"
```

---

### Task 4: Thread warehouse ids through `internal_transfer_auto_action.php`'s two-leg transfer

**Files:**
- Modify: `femi9/billing/company/internal_transfer_auto_action.php`
- Test: `femi9/billing/includes/tests/AutoTransferActionWarehouseTest.php`

**Interfaces:**
- Consumes: `StockService::getClosingQty()`, `StockService::transferOut()`, `StockService::transferIn()` (all existing, unchanged signatures — trailing `?int $warehouseId = null`).
- Produces: no new interface — internal behavior change to an existing action handler.

- [ ] **Step 1: Write the failing test**

This test exercises the exact two-leg logic the action handler runs, extracted as assertions against `StockService` directly (matching this project's established pattern of testing StockService behavior rather than spinning up a full HTTP request against a form-posting action file — see `UndoAutoTransferTest.php` for precedent testing `AutoTransferDemand.php` functions the same way).

```php
<?php
// femi9/billing/includes/tests/AutoTransferActionWarehouseTest.php
// Manual run: php AutoTransferActionWarehouseTest.php
//
// Regression/behavior test for the two-leg warehouse threading rule
// internal_transfer_auto_action.php's $writeLeg() closure implements
// (see docs/superpowers/specs/2026-09-21-warehouse-aware-auto-transfer-
// design.md): Leg 1's destination and Leg 2's source must land in the
// SAME warehouse (goods don't teleport mid-hop, only the owning company
// profile changes at the Healthcare stop); Leg 2's destination lands in
// the user's chosen Destination Godown, independent of the source.
//
// This test calls StockService::transferOut()/transferIn() directly
// with the same call pattern $writeLeg() uses, rather than posting to
// the action file over HTTP (checksession.php requires a real login
// session) — confirms the underlying stock movement is correct, which
// is what the action handler change actually depends on.
//
// Uses a disposable `auto_transfer_action_warehouse_test` schema.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/db-connect.php';

$conn = $db_conn;
const TEST_SCHEMA = 'auto_transfer_action_warehouse_test';

$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
$conn->query("CREATE DATABASE IF NOT EXISTS `" . TEST_SCHEMA . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$conn->select_db(TEST_SCHEMA);

$passCount = 0; $failCount = 0;
function assertEqual($actual, $expected, $label) {
    global $passCount, $failCount;
    if ($actual == $expected) { echo "PASS: $label\n"; $passCount++; }
    else { echo "FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n"; $failCount++; }
}

$conn->query("CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL, opening_qty INT NOT NULL DEFAULT 0, opening_date DATE NOT NULL DEFAULT '2026-01-01',
    input_qty INT NOT NULL DEFAULT 0, sales_qty INT NOT NULL DEFAULT 0, sent_qty INT NOT NULL DEFAULT 0,
    returnqty INT NOT NULL DEFAULT 0, closing_qty INT NOT NULL DEFAULT 0, extra_pieces INT UNSIGNED NOT NULL DEFAULT 0,
    user_type VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, warehouse_id INT NULL, updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id)
)");
$conn->query("CREATE TABLE stock_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL,
    warehouse_id INT NULL, action VARCHAR(50) NOT NULL, qty INT NOT NULL, qty_before INT NOT NULL, qty_after INT NOT NULL,
    ref_type VARCHAR(50) NOT NULL, ref_id VARCHAR(255) NOT NULL, note VARCHAR(255) NOT NULL DEFAULT '',
    created_by VARCHAR(255) NOT NULL DEFAULT '', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE stock_lots (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(32) NOT NULL, user_id VARCHAR(32) NOT NULL,
    warehouse_id INT NULL, rate DECIMAL(12,6) NOT NULL, qty_purchased INT NOT NULL, qty_remaining INT NOT NULL,
    purchase_date DATE NOT NULL, ref_type ENUM('llp_rate_entry','neksomo_purchase','transfer_in','opening_balance') NOT NULL,
    ref_id VARCHAR(64) NULL, created_by VARCHAR(64) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE stock_ledger_lot_consumption (
    id INT AUTO_INCREMENT PRIMARY KEY, stock_ledger_id INT NOT NULL, stock_lot_id INT NULL, qty_taken INT NOT NULL, rate DECIMAL(12,6) NOT NULL
)");
$conn->query("CREATE TABLE company_godown (id INT AUTO_INCREMENT PRIMARY KEY, gname VARCHAR(255) NOT NULL)");

const NEKSOMO_ID = '3'; const HEALTHCARE_ID = '4'; const LLP_ID = '5';
const SOURCE_WH = 2; // G1
const DEST_WH = 3;   // G2 — deliberately different from source

// Seed Neksomo's stock at the source warehouse.
$conn->query("INSERT INTO stock (product_id, user_type, user_id, warehouse_id, closing_qty) VALUES (9, 'company', '" . NEKSOMO_ID . "', " . SOURCE_WH . ", 100)");
$conn->query("INSERT INTO stock_lots (product_id, user_type, user_id, warehouse_id, rate, qty_purchased, qty_remaining, purchase_date, ref_type) VALUES (9, 'company', '" . NEKSOMO_ID . "', " . SOURCE_WH . ", 10.00, 100, 100, '2026-09-01', 'opening_balance')");

$stockService = new StockService($conn);

// Leg 1: Neksomo @ SOURCE_WH -> Healthcare @ SOURCE_WH (same warehouse —
// the intermediate hop stays physically where the stock started).
$outResult = $stockService->transferOut(9, 'company', NEKSOMO_ID, 30, 'transfer', 'TEST-N1', 'tester', true, SOURCE_WH);
$stockService->transferIn(9, 'company', HEALTHCARE_ID, 30, 'transfer', 'TEST-N1', 'tester', true, $outResult['consumed_rate'] ?? null, SOURCE_WH);

// Leg 2: Healthcare @ SOURCE_WH -> LLP @ DEST_WH (destination differs).
$outResult2 = $stockService->transferOut(9, 'company', HEALTHCARE_ID, 30, 'transfer', 'TEST-N2', 'tester', true, SOURCE_WH);
$stockService->transferIn(9, 'company', LLP_ID, 30, 'transfer', 'TEST-N2', 'tester', true, $outResult2['consumed_rate'] ?? null, DEST_WH);

$neksomoAtSource    = (int) ($stockService->getClosingQty(9, 'company', NEKSOMO_ID, SOURCE_WH) ?? 0);
$healthcareAtSource = (int) ($stockService->getClosingQty(9, 'company', HEALTHCARE_ID, SOURCE_WH) ?? 0);
$llpAtDest          = (int) ($stockService->getClosingQty(9, 'company', LLP_ID, DEST_WH) ?? 0);
$llpAtSource        = (int) ($stockService->getClosingQty(9, 'company', LLP_ID, SOURCE_WH) ?? 0);

assertEqual($neksomoAtSource, 70, 'Neksomo at source warehouse debited by 30 (100 - 30)');
assertEqual($healthcareAtSource, 0, 'Healthcare at source warehouse: credited 30 then immediately debited 30 (net 0) — intermediate hop leaves no residue');
assertEqual($llpAtDest, 30, 'LLP at DESTINATION warehouse credited 30 — different warehouse than source, as picked by the user');
assertEqual($llpAtSource, 0, 'LLP at SOURCE warehouse untouched — stock never lands there, confirming legs are warehouse-scoped independently');

$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php femi9/billing/includes/tests/AutoTransferActionWarehouseTest.php`
Expected: This test should actually PASS immediately, since it exercises only already-existing, already-warehouse-aware `StockService` methods directly — it does not yet touch `internal_transfer_auto_action.php` itself. This is expected and correct: it's a regression-proof of the underlying mechanism the next step's action-file change relies on, written and verified green BEFORE editing the action file, so a later action-file bug is caught by comparing against this known-good baseline. Proceed to Step 3 regardless.

- [ ] **Step 3: Thread warehouse ids through `internal_transfer_auto_action.php`**

Read the current file in full first (it may have shifted slightly since this plan's grounding read) — `$writeLeg` closure is around lines 127-184, called at lines 199 and further below for Leg 2.

Add two new POST array reads near the existing `$rate1Arr`/`$rate2Arr` (current lines 41-42):

```php
$warehouseFromArr = $_REQUEST['warehouse_from'] ?? [];
$warehouseToArr   = $_REQUEST['warehouse_to'] ?? [];
```

In the `$rows` build loop (current lines 71-78), capture the per-row warehouse values alongside `rate1`/`rate2`:

```php
foreach ($productIds as $i => $rawPid) {
    $pid   = (int) $rawPid;
    $qty   = (int) RemoveSpecialChar($qtyArr[$i] ?? '0');
    $rate1 = (float) ($rate1Arr[$i] ?? 0);
    $rate2 = (float) ($rate2Arr[$i] ?? 0);
    $sourceWarehouseId = filter_var($warehouseFromArr[$i] ?? '', FILTER_VALIDATE_INT) ?: null;
    $destWarehouseId   = filter_var($warehouseToArr[$i] ?? '', FILTER_VALIDATE_INT) ?: null;
    if ($pid <= 0 || $qty <= 0) continue;
    $rows[] = ['pid' => $pid, 'qty' => $qty, 'rate1' => $rate1, 'rate2' => $rate2, 'source_warehouse_id' => $sourceWarehouseId, 'dest_warehouse_id' => $destWarehouseId];
}
```

Update `$writeLeg`'s signature (current lines 127-132) to accept two trailing warehouse parameters:

```php
$writeLeg = function (
    string $tempid, string $invNumber, string $sendFrom, string $sendTo, int $pid, int $qty, float $rate,
    ?int $sourceWarehouseId, ?int $destWarehouseId
) use (
    $db_conn, $stockService, $createdBy, $username, $usertype, $date,
    $stmtInvChk, $stmtInvIns, $stmtProdIns, $stmtProd, $Login_user_TYPEvl
): int {
```

Inside the closure, update the pre-validation `getClosingQty()` call (current line 133):

```php
$available = $stockService->getClosingQty($pid, $Login_user_TYPEvl, $sendFrom, $sourceWarehouseId);
```

And the `transferOut()`/`transferIn()` calls (current lines 173-181):

```php
$outResult = $stockService->transferOut(
    $pid, $Login_user_TYPEvl, $sendFrom, $actualQty,
    'transfer', $tempid, $createdBy, true, $sourceWarehouseId
);
$stockService->transferIn(
    $pid, $Login_user_TYPEvl, $sendTo, $actualQty,
    'transfer', $tempid, $createdBy, true,
    $outResult['consumed_rate'] ?? null, $destWarehouseId
);
```

Finally, update the two call sites of `$writeLeg` (current lines ~199 for Leg 1, and the Leg 2 call following it — read the file to find the exact current line, since it references `$legOneQty` from Leg 1's return value) to pass the per-row warehouse ids, applying the "Leg 1's destination = Leg 2's source = the row's source warehouse; only Leg 2's destination is the row's destination warehouse" rule from the design doc:

```php
$legOneQty = $writeLeg($tempid1, $invNumber1, (string) $neksomoId, (string) $healthcareId, $pid, $requestedQty, $row['rate1'], $row['source_warehouse_id'], $row['source_warehouse_id']);
if ($legOneQty <= 0) continue;

$legTwoQty = $writeLeg($tempid2, $invNumber2, (string) $healthcareId, (string) $llpId, $pid, $legOneQty, $row['rate2'], $row['source_warehouse_id'], $row['dest_warehouse_id']);
```

- [ ] **Step 4: Commit**

```bash
git add femi9/billing/company/internal_transfer_auto_action.php \
        femi9/billing/includes/tests/AutoTransferActionWarehouseTest.php
git commit -m "Thread per-leg warehouse ids through Auto Transfer's two-leg action handler"
```

---

### Task 5: Manual Internal Transfer — filter existing warehouse pickers by company profile

**Files:**
- Create: `femi9/billing/company/get-godown-warehouses.php`
- Modify: `femi9/billing/company/internal_transfer.php`

**Interfaces:**
- Consumes: `get_warehouses_for_godown()` from Task 1.
- Produces: `get-godown-warehouses.php` — GET endpoint, param `company_godown_id`, returns JSON `{warehouses: [{id, code, name}, ...]}` (already falls back to the full active list if the mapping is empty, matching Task 3's endpoint policy).

- [ ] **Step 1: Write the filtering endpoint**

```php
<?php
// femi9/billing/company/get-godown-warehouses.php
//
// Returns the warehouse options for one company profile, filtered by
// company_godown_warehouses — falls back to the full active-warehouse
// list if nothing is mapped yet. Used by internal_transfer.php's
// Send From/Send To pickers to re-filter the two existing warehouse
// dropdowns on change (see docs/superpowers/specs/2026-09-21-warehouse-
// aware-auto-transfer-design.md).

include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/GodownWarehouseMapping.php");
header('Content-Type: application/json');
error_reporting(0);

$companyGodownId = (int) ($_GET['company_godown_id'] ?? 0);
if (!$companyGodownId) {
    echo json_encode(['error' => 'invalid_godown']);
    exit;
}

$warehouses = get_warehouses_for_godown($db_conn, $companyGodownId);
if (empty($warehouses)) {
    $res = $db_conn->query("SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC");
    $warehouses = $res->fetch_all(MYSQLI_ASSOC);
    $warehouses = array_map(fn($w) => ['id' => (int) $w['id'], 'code' => $w['code'], 'name' => $w['name']], $warehouses);
}

echo json_encode(['warehouses' => $warehouses]);
```

- [ ] **Step 2: Add JS re-filtering to `internal_transfer.php`**

Read the file's existing `<script>` block first (the form already has an `onchange="checkopeningstock(this.value);"` handler on `send_from`, current line 150) to find where to add new listeners without disrupting that.

Add IDs to the existing `send_from`/`send_to`/`warehouse_from_id`/`warehouse_to_id` selects if they don't already have them (current lines 150, 163, 181, 190 use `name` only, no `id`) — add `id="sendFromSelect"`, `id="sendToSelect"`, `id="warehouseFromSelect"`, `id="warehouseToSelect"` respectively.

Add this script near the end of the file, alongside the existing `<script>` blocks:

```html
<script>
function renderWarehouseOptions(selectEl, warehouses) {
    var currentVal = selectEl.value;
    var html = '<option value="">— Not tracked —</option>';
    warehouses.forEach(function (wh) {
        var label = wh.code + (wh.name ? ' - ' + wh.name : '');
        html += '<option value="' + wh.id + '">' + label.replace(/</g, '&lt;') + '</option>';
    });
    selectEl.innerHTML = html;
    // Keep the previous selection if it's still a valid option after refiltering.
    if (currentVal && Array.from(selectEl.options).some(function (o) { return o.value === currentVal; })) {
        selectEl.value = currentVal;
    }
}

function refilterWarehouseSelect(companyGodownSelectId, warehouseSelectId) {
    var companySelect = document.getElementById(companyGodownSelectId);
    var warehouseSelect = document.getElementById(warehouseSelectId);
    if (!companySelect || !warehouseSelect) return;
    var godownId = companySelect.value;
    if (!godownId) return;

    fetch('get-godown-warehouses.php?company_godown_id=' + encodeURIComponent(godownId))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) return;
            renderWarehouseOptions(warehouseSelect, data.warehouses);
        })
        .catch(function () { /* leave current options on fetch failure */ });
}

var sendFromSelect = document.getElementById('sendFromSelect');
var sendToSelect = document.getElementById('sendToSelect');
if (sendFromSelect) {
    sendFromSelect.addEventListener('change', function () {
        refilterWarehouseSelect('sendFromSelect', 'warehouseFromSelect');
    });
}
if (sendToSelect) {
    sendToSelect.addEventListener('change', function () {
        refilterWarehouseSelect('sendToSelect', 'warehouseToSelect');
    });
}
</script>
```

Note: this listener is additive alongside the existing `onchange="checkopeningstock(this.value);"` inline handler on `send_from` — both fire independently, no conflict, since `addEventListener` doesn't replace the inline `onchange` attribute.

- [ ] **Step 3: Commit**

```bash
git add femi9/billing/company/get-godown-warehouses.php \
        femi9/billing/company/internal_transfer.php
git commit -m "Filter manual Internal Transfer's warehouse pickers by linked company profile"
```

---

### Task 6: Transfer History — surface per-leg warehouse

**Files:**
- Modify: `femi9/billing/company/include/AutoTransferDemand.php`
- Modify: `femi9/billing/company/internal_transfer_auto.php` (History modal rendering)
- Test: `femi9/billing/includes/tests/AutoTransferHistoryWarehouseTest.php`

**Interfaces:**
- Consumes: existing `stock_ledger.warehouse_id` column (already populated once Task 4 ships).
- Produces: `get_auto_transfer_history_for_date()` gains two new keys per row: `neksomo_warehouse_id` (?int) and `llp_warehouse_id` (?int).

- [ ] **Step 1: Write the failing test**

```php
<?php
// femi9/billing/includes/tests/AutoTransferHistoryWarehouseTest.php
// Manual run: php AutoTransferHistoryWarehouseTest.php
//
// Regression/behavior test confirming get_auto_transfer_history_for_date()
// surfaces the warehouse each leg actually used (read from stock_ledger,
// already populated once Task 4's warehouse threading ships) — and that
// a pre-existing run with NULL warehouse_id on both legs (the "before
// this feature shipped" case) renders as null, not a crash or a wrong
// default.
//
// Uses a disposable `auto_transfer_history_warehouse_test` schema.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/AutoTransferDemand.php';
require_once __DIR__ . '/../../company/include/db-connect.php';

$conn = $db_conn;
const TEST_SCHEMA = 'auto_transfer_history_warehouse_test';

$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
$conn->query("CREATE DATABASE IF NOT EXISTS `" . TEST_SCHEMA . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$conn->select_db(TEST_SCHEMA);

$passCount = 0; $failCount = 0;
function assertEqual($actual, $expected, $label) {
    global $passCount, $failCount;
    if ($actual == $expected) { echo "PASS: $label\n"; $passCount++; }
    else { echo "FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n"; $failCount++; }
}

$conn->query("CREATE TABLE products (id INT AUTO_INCREMENT PRIMARY KEY, productName VARCHAR(255) NOT NULL)");
$conn->query("INSERT INTO products (id, productName) VALUES (9, 'Test Product')");

$conn->query("CREATE TABLE internal_transfer (
    id INT AUTO_INCREMENT PRIMARY KEY, tempid VARCHAR(64) NOT NULL, send_from VARCHAR(64) NOT NULL, send_to VARCHAR(64) NOT NULL,
    date DATE NOT NULL, product_id INT NOT NULL, qty INT NOT NULL, returned_qty INT NOT NULL DEFAULT 0
)");
$conn->query("CREATE TABLE internal_transfer_invoice (id INT AUTO_INCREMENT PRIMARY KEY, tempid VARCHAR(64) NOT NULL, inv_id VARCHAR(64) NOT NULL, inv_number VARCHAR(64) NOT NULL, courier_charges VARCHAR(64) NOT NULL DEFAULT '0')");
$conn->query("CREATE TABLE stock_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL,
    warehouse_id INT NULL, action VARCHAR(50) NOT NULL, qty INT NOT NULL, qty_before INT NOT NULL, qty_after INT NOT NULL,
    ref_type VARCHAR(50) NOT NULL, ref_id VARCHAR(255) NOT NULL, note VARCHAR(255) NOT NULL DEFAULT '',
    created_by VARCHAR(255) NOT NULL DEFAULT '', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

const NEKSOMO_ID = '3'; const HEALTHCARE_ID = '4'; const LLP_ID = '5';

// Run A: new-style, warehouse-tagged (source=2, dest=3).
$conn->query("INSERT INTO internal_transfer (tempid, send_from, send_to, date, product_id, qty) VALUES ('AUTO001-N1', '" . NEKSOMO_ID . "', '" . HEALTHCARE_ID . "', '2026-09-21', 9, 20)");
$conn->query("INSERT INTO internal_transfer (tempid, send_from, send_to, date, product_id, qty) VALUES ('AUTO001-N2', '" . HEALTHCARE_ID . "', '" . LLP_ID . "', '2026-09-21', 9, 20)");
$conn->query("INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, created_by) VALUES (9, 'company', '" . NEKSOMO_ID . "', 2, 'transfer_out', 20, 100, 80, 'transfer', 'AUTO001-N1', 'tester')");
$conn->query("INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, created_by) VALUES (9, 'company', '" . HEALTHCARE_ID . "', 2, 'transfer_in', 20, 0, 20, 'transfer', 'AUTO001-N1', 'tester')");
$conn->query("INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, created_by) VALUES (9, 'company', '" . LLP_ID . "', 3, 'transfer_in', 20, 0, 20, 'transfer', 'AUTO001-N2', 'tester')");

// Run B: old-style, pre-feature (NULL warehouse on both legs).
$conn->query("INSERT INTO internal_transfer (tempid, send_from, send_to, date, product_id, qty) VALUES ('AUTO002-N1', '" . NEKSOMO_ID . "', '" . HEALTHCARE_ID . "', '2026-09-21', 9, 10)");
$conn->query("INSERT INTO internal_transfer (tempid, send_from, send_to, date, product_id, qty) VALUES ('AUTO002-N2', '" . HEALTHCARE_ID . "', '" . LLP_ID . "', '2026-09-21', 9, 10)");
$conn->query("INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, created_by) VALUES (9, 'company', '" . NEKSOMO_ID . "', NULL, 'transfer_out', 10, 80, 70, 'transfer', 'AUTO002-N1', 'tester')");
$conn->query("INSERT INTO stock_ledger (product_id, user_type, user_id, warehouse_id, action, qty, qty_before, qty_after, ref_type, ref_id, created_by) VALUES (9, 'company', '" . LLP_ID . "', NULL, 'transfer_in', 10, 0, 10, 'transfer', 'AUTO002-N2', 'tester')");

$rows = get_auto_transfer_history_for_date($conn, '2026-09-21', (int) NEKSOMO_ID, (int) HEALTHCARE_ID, (int) LLP_ID);

$runA = null; $runB = null;
foreach ($rows as $r) {
    if ($r['tempid'] === 'AUTO001-N2') $runA = $r;
    if ($r['tempid'] === 'AUTO002-N2') $runB = $r;
}

assertEqual($runA !== null, true, 'Run A (warehouse-tagged) found in history');
assertEqual($runA['neksomo_warehouse_id'], 2, 'Run A neksomo_warehouse_id is 2 (source warehouse)');
assertEqual($runA['llp_warehouse_id'], 3, 'Run A llp_warehouse_id is 3 (destination warehouse, different from source)');

assertEqual($runB !== null, true, 'Run B (pre-feature, NULL warehouse) found in history');
assertEqual($runB['neksomo_warehouse_id'], null, 'Run B neksomo_warehouse_id is null — old run unaffected, no wrong default');
assertEqual($runB['llp_warehouse_id'], null, 'Run B llp_warehouse_id is null — old run unaffected');

$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php femi9/billing/includes/tests/AutoTransferHistoryWarehouseTest.php`
Expected: FAIL — `$runA['neksomo_warehouse_id']` and `$runA['llp_warehouse_id']` keys don't exist yet in the returned array shape.

- [ ] **Step 3: Add warehouse columns to the query and return shape**

In `get_auto_transfer_history_for_date()` (current lines 625-674 of `AutoTransferDemand.php`), add two columns to the SELECT list:

```php
$stmt = $db_conn->prepare(
    "SELECT it2.tempid, it2.product_id, p.productName, it2.qty AS qty_transferred,
            sl_out.qty_before AS neksomo_before,
            sl_in1.qty_before AS healthcare_before,
            sl_in2.qty_before AS llp_before,
            sl_out.qty_after AS neksomo_after,
            sl_in1.qty_after AS healthcare_after,
            sl_in2.qty_after AS llp_after,
            sl_out.warehouse_id AS neksomo_warehouse_id,
            sl_in2.warehouse_id AS llp_warehouse_id,
            COALESCE(sl_out.created_at, sl_in2.created_at) AS transferred_at
     FROM internal_transfer it2
     ...
```

(keep the rest of the SQL unchanged — only the SELECT list gains the two new columns, using the already-joined `sl_out`/`sl_in2` aliases).

Update the return `array_map()` to include the two new keys:

```php
return array_map(function ($row) {
    return [
        'tempid'              => $row['tempid'],
        'product_id'          => (int) $row['product_id'],
        'product_name'        => $row['productName'],
        'qty_transferred'     => (int) $row['qty_transferred'],
        'neksomo_before'      => $row['neksomo_before'] !== null ? (int) $row['neksomo_before'] : null,
        'healthcare_before'   => $row['healthcare_before'] !== null ? (int) $row['healthcare_before'] : null,
        'llp_before'          => $row['llp_before'] !== null ? (int) $row['llp_before'] : null,
        'neksomo_after'       => $row['neksomo_after'] !== null ? (int) $row['neksomo_after'] : null,
        'healthcare_after'    => $row['healthcare_after'] !== null ? (int) $row['healthcare_after'] : null,
        'llp_after'           => $row['llp_after'] !== null ? (int) $row['llp_after'] : null,
        'neksomo_warehouse_id'=> $row['neksomo_warehouse_id'] !== null ? (int) $row['neksomo_warehouse_id'] : null,
        'llp_warehouse_id'    => $row['llp_warehouse_id'] !== null ? (int) $row['llp_warehouse_id'] : null,
        'transferred_at'      => $row['transferred_at'],
    ];
}, $rows);
```

Note: `get_auto_transfer_history_grouped_for_date()` (the function immediately after this one, current lines ~676+) calls `get_auto_transfer_history_for_date()` internally and copies each row's fields into its own `'products'` array via `$productRow = $row; unset($productRow['tempid'], $productRow['transferred_at']);` — the two new keys flow through automatically with no change needed there.

- [ ] **Step 4: Run test to verify it passes**

Run: `php femi9/billing/includes/tests/AutoTransferHistoryWarehouseTest.php`
Expected: PASS — all assertions green.

- [ ] **Step 5: Surface the warehouse in the Transfer History modal UI**

In `internal_transfer_auto.php`'s Transfer History rendering JS (the `loadTransferHistory()` function's table-building logic — read the current file to find the exact `<th>`/`<td>` construction for the per-product columns), add a "Godown" column showing `neksomo_warehouse_id`/`llp_warehouse_id` as their warehouse code — this requires the warehouse code lookup to be available client-side. Simplest approach: have the PHP-side warehouse list (`$allWarehouses`, already built in Task 3 Step 3) also be emitted as a small JS lookup object near the top of the `<script>` block:

```javascript
var warehouseCodeById = <?php echo json_encode(array_column($allWarehouses, 'code', 'id')); ?>;
function warehouseLabel(id) {
    if (!id) return '<span class="text-muted">—</span>';
    return warehouseCodeById[id] || ('#' + id);
}
```

Use `warehouseLabel(run.products[i].neksomo_warehouse_id)` / `warehouseLabel(run.products[i].llp_warehouse_id)` wherever the History modal renders each run's per-product row, following the same "—" convention this modal already uses for other null figures (`thFmtStock()`).

- [ ] **Step 6: Commit**

```bash
git add femi9/billing/company/include/AutoTransferDemand.php \
        femi9/billing/company/internal_transfer_auto.php \
        femi9/billing/includes/tests/AutoTransferHistoryWarehouseTest.php
git commit -m "Surface per-leg warehouse in Auto Transfer history"
```

---

### Task 7: Undo Auto Transfer — reverse against the exact warehouse each leg used

**Files:**
- Modify: `femi9/billing/company/include/AutoTransferDemand.php`
- Test: `femi9/billing/includes/tests/UndoAutoTransferWarehouseTest.php`

**Interfaces:**
- Consumes: `StockService::reverseTransferIn()`, `StockService::reverseTransferOut()` (existing, trailing `?int $warehouseId = null`).
- Produces: `undo_auto_transfer()`'s behavior changes internally — no signature change (it already takes no warehouse argument from its caller; it now derives the correct warehouse itself by reading it back from `stock_ledger`, per the design doc's "never re-derived from the UI" rule).

- [ ] **Step 1: Write the failing test**

```php
<?php
// femi9/billing/includes/tests/UndoAutoTransferWarehouseTest.php
// Manual run: php UndoAutoTransferWarehouseTest.php
//
// Regression/behavior test confirming undo_auto_transfer() reverses each
// leg against the EXACT warehouse that leg's original stock_ledger entry
// used — not the unassigned (NULL) row, and not whatever a UI picker
// might currently show — even when source and destination warehouses
// differ across the two legs. See docs/superpowers/specs/2026-09-21-
// warehouse-aware-auto-transfer-design.md.
//
// Uses a disposable `undo_auto_transfer_warehouse_test` schema — same
// table shapes as the existing UndoAutoTransferTest.php, since this test
// exercises the same function.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/AutoTransferDemand.php';
require_once __DIR__ . '/../../company/include/db-connect.php';

$conn = $db_conn;
const TEST_SCHEMA = 'undo_auto_transfer_warehouse_test';

$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
$conn->query("CREATE DATABASE IF NOT EXISTS `" . TEST_SCHEMA . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$conn->select_db(TEST_SCHEMA);

$passCount = 0; $failCount = 0;
function assertEqual($actual, $expected, $label) {
    global $passCount, $failCount;
    if ($actual == $expected) { echo "PASS: $label\n"; $passCount++; }
    else { echo "FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n"; $failCount++; }
}

// Same table shapes as UndoAutoTransferTest.php.
$conn->query("CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL, opening_qty INT NOT NULL DEFAULT 0, opening_date DATE NOT NULL DEFAULT '2026-01-01',
    input_qty INT NOT NULL DEFAULT 0, sales_qty INT NOT NULL DEFAULT 0, sent_qty INT NOT NULL DEFAULT 0,
    returnqty INT NOT NULL DEFAULT 0, closing_qty INT NOT NULL DEFAULT 0, extra_pieces INT UNSIGNED NOT NULL DEFAULT 0,
    user_type VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL, warehouse_id INT NULL, updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_stock_entity_warehouse (product_id, user_type, user_id, warehouse_id)
)");
$conn->query("CREATE TABLE stock_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(255) NOT NULL, user_id VARCHAR(255) NOT NULL,
    warehouse_id INT NULL, action VARCHAR(50) NOT NULL, qty INT NOT NULL, qty_before INT NOT NULL, qty_after INT NOT NULL,
    ref_type VARCHAR(50) NOT NULL, ref_id VARCHAR(255) NOT NULL, note VARCHAR(255) NOT NULL DEFAULT '',
    created_by VARCHAR(255) NOT NULL DEFAULT '', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE stock_lots (
    id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_type VARCHAR(32) NOT NULL, user_id VARCHAR(32) NOT NULL,
    warehouse_id INT NULL, rate DECIMAL(12,6) NOT NULL, qty_purchased INT NOT NULL, qty_remaining INT NOT NULL,
    purchase_date DATE NOT NULL, ref_type ENUM('llp_rate_entry','neksomo_purchase','transfer_in','opening_balance') NOT NULL,
    ref_id VARCHAR(64) NULL, created_by VARCHAR(64) NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE stock_ledger_lot_consumption (
    id INT AUTO_INCREMENT PRIMARY KEY, stock_ledger_id INT NOT NULL, stock_lot_id INT NULL, qty_taken INT NOT NULL, rate DECIMAL(12,6) NOT NULL
)");
$conn->query("CREATE TABLE internal_transfer (
    id INT AUTO_INCREMENT PRIMARY KEY, tempid VARCHAR(64) NOT NULL, send_from VARCHAR(64) NOT NULL, send_to VARCHAR(64) NOT NULL,
    date DATE NOT NULL, product_id INT NOT NULL, qty INT NOT NULL, returned_qty INT NOT NULL DEFAULT 0
)");
$conn->query("CREATE TABLE internal_transfer_invoice (id INT AUTO_INCREMENT PRIMARY KEY, tempid VARCHAR(64) NOT NULL, inv_id VARCHAR(64) NOT NULL, inv_number VARCHAR(64) NOT NULL, courier_charges VARCHAR(64) NOT NULL DEFAULT '0')");

const NEKSOMO_ID = 3; const HEALTHCARE_ID = 4; const LLP_ID = 5;
const SOURCE_WH = 2; const DEST_WH = 3;

$stockService = new StockService($conn);

// Seed and perform a real two-leg transfer using StockService directly
// (same mechanism internal_transfer_auto_action.php's $writeLeg() uses),
// so stock/stock_ledger/stock_lots end up in the exact state undo needs
// to reverse against.
$conn->query("INSERT INTO stock (product_id, user_type, user_id, warehouse_id, closing_qty) VALUES (9, 'company', '" . NEKSOMO_ID . "', " . SOURCE_WH . ", 100)");
$conn->query("INSERT INTO stock_lots (product_id, user_type, user_id, warehouse_id, rate, qty_purchased, qty_remaining, purchase_date, ref_type) VALUES (9, 'company', '" . NEKSOMO_ID . "', " . SOURCE_WH . ", 10.00, 100, 100, '2026-09-01', 'opening_balance')");

$out1 = $stockService->transferOut(9, 'company', (string) NEKSOMO_ID, 20, 'transfer', 'AUTO999-N1', 'tester', true, SOURCE_WH);
$stockService->transferIn(9, 'company', (string) HEALTHCARE_ID, 20, 'transfer', 'AUTO999-N1', 'tester', true, $out1['consumed_rate'] ?? null, SOURCE_WH);
$out2 = $stockService->transferOut(9, 'company', (string) HEALTHCARE_ID, 20, 'transfer', 'AUTO999-N2', 'tester', true, SOURCE_WH);
$stockService->transferIn(9, 'company', (string) LLP_ID, 20, 'transfer', 'AUTO999-N2', 'tester', true, $out2['consumed_rate'] ?? null, DEST_WH);

$conn->query("INSERT INTO internal_transfer (tempid, send_from, send_to, date, product_id, qty) VALUES ('AUTO999-N1', '" . NEKSOMO_ID . "', '" . HEALTHCARE_ID . "', '2026-09-21', 9, 20)");
$conn->query("INSERT INTO internal_transfer (tempid, send_from, send_to, date, product_id, qty) VALUES ('AUTO999-N2', '" . HEALTHCARE_ID . "', '" . LLP_ID . "', '2026-09-21', 9, 20)");
$conn->query("INSERT INTO internal_transfer_invoice (tempid, inv_id, inv_number) VALUES ('AUTO999-N1', '0', 'G/26-27/1')");
$conn->query("INSERT INTO internal_transfer_invoice (tempid, inv_id, inv_number) VALUES ('AUTO999-N2', '0', 'S/26-27/1')");

// Pre-undo sanity: confirm the transfer landed where expected.
assertEqual((int) ($stockService->getClosingQty(9, 'company', (string) NEKSOMO_ID, SOURCE_WH) ?? 0), 80, 'pre-undo: Neksomo at source warehouse debited to 80');
assertEqual((int) ($stockService->getClosingQty(9, 'company', (string) LLP_ID, DEST_WH) ?? 0), 20, 'pre-undo: LLP at destination warehouse credited to 20');

$result = undo_auto_transfer($conn, 'AUTO999-N2', 9, 'company', NEKSOMO_ID, HEALTHCARE_ID, LLP_ID, 'tester');
assertEqual($result['success'] ?? false, true, 'undo_auto_transfer() succeeds');

$neksomoAfterUndo = (int) ($stockService->getClosingQty(9, 'company', (string) NEKSOMO_ID, SOURCE_WH) ?? 0);
$llpAfterUndo     = (int) ($stockService->getClosingQty(9, 'company', (string) LLP_ID, DEST_WH) ?? 0);
$neksomoUnassignedAfterUndo = (int) ($stockService->getClosingQty(9, 'company', (string) NEKSOMO_ID, null) ?? 0);

assertEqual($neksomoAfterUndo, 100, 'Neksomo at SOURCE warehouse fully restored to 100 — undo reversed against the correct warehouse, not the unassigned row');
assertEqual($llpAfterUndo, 0, 'LLP at DESTINATION warehouse correctly debited back to 0');
assertEqual($neksomoUnassignedAfterUndo, 0, 'Neksomo UNASSIGNED row untouched (stays 0) — undo never blends into the wrong warehouse bucket');

$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php femi9/billing/includes/tests/UndoAutoTransferWarehouseTest.php`
Expected: FAIL — `undo_auto_transfer()` currently calls `reverseTransferIn()`/`reverseTransferOut()` with no warehouse argument, so it reverses against the unassigned (`NULL`) row instead of `SOURCE_WH`/`DEST_WH`, leaving those warehouse-scoped rows at their post-transfer values instead of restored.

- [ ] **Step 3: Update `undo_auto_transfer()` to read back and use the real warehouse per leg**

In `AutoTransferDemand.php`'s `undo_auto_transfer()` (current lines 756-833), after the existing `$legs` lookup (current lines 763-776) and before computing `$qtyLeg1`/`$qtyLeg2`, add a lookup of each leg's actual warehouse from `stock_ledger`:

```php
// Read back the exact warehouse each leg's stock_ledger entry actually
// used — never trust a UI-supplied value, since the picker on screen
// when Undo is clicked may not reflect what the original transfer used
// (see docs/superpowers/specs/2026-09-21-warehouse-aware-auto-transfer-
// design.md). NULL for any pre-feature run (both legs always NULL back
// then), which reverseTransferIn()/reverseTransferOut() already treat
// identically to "no warehouse" — no special-casing needed here.
function auto_transfer_leg_warehouse(mysqli $db, string $tempid, int $productId, string $action): ?int
{
    $stmt = $db->prepare(
        "SELECT warehouse_id FROM stock_ledger
         WHERE ref_type = 'transfer' AND ref_id = ? AND product_id = ? AND action = ?
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('sis', $tempid, $productId, $action);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($row && $row['warehouse_id'] !== null) ? (int) $row['warehouse_id'] : null;
}
```

Then in `undo_auto_transfer()`, right after the existing `$qtyLeg1`/`$qtyLeg2` computation (current lines 778-779), add:

```php
// Leg 2: Healthcare -> LLP. sourceWarehouse = where it left Healthcare
// (the transfer_out row); destWarehouse = where it landed at LLP (the
// transfer_in row) — these can legitimately differ (see Task 4).
$leg2SourceWarehouse = auto_transfer_leg_warehouse($db_conn, $tempidN2, $productId, 'transfer_out');
$leg2DestWarehouse   = auto_transfer_leg_warehouse($db_conn, $tempidN2, $productId, 'transfer_in');
// Leg 1: Neksomo -> Healthcare.
$leg1SourceWarehouse = auto_transfer_leg_warehouse($db_conn, $tempidN1, $productId, 'transfer_out');
$leg1DestWarehouse   = auto_transfer_leg_warehouse($db_conn, $tempidN1, $productId, 'transfer_in');
```

Update the reversal calls (current lines 788-804) to pass the looked-up warehouse ids as each method's trailing argument:

```php
if ($qtyLeg2 > 0) {
    $reverseIn2 = $stockService->reverseTransferIn($productId, $userType, $llpStr, $qtyLeg2, 'transfer', $tempidN2, $createdBy, true, $leg2DestWarehouse);
    if (($reverseIn2['success'] ?? false) === false) {
        $db_conn->rollback();
        return $reverseIn2;
    }
    $stockService->reverseTransferOut($productId, $userType, $healthcareStr, $qtyLeg2, 'transfer', $tempidN2, $createdBy, true, $leg2SourceWarehouse);
}

if ($qtyLeg1 > 0) {
    $reverseIn1 = $stockService->reverseTransferIn($productId, $userType, $healthcareStr, $qtyLeg1, 'transfer', $tempidN1, $createdBy, true, $leg1DestWarehouse);
    if (($reverseIn1['success'] ?? false) === false) {
        $db_conn->rollback();
        return $reverseIn1;
    }
    $stockService->reverseTransferOut($productId, $userType, $neksomoStr, $qtyLeg1, 'transfer', $tempidN1, $createdBy, true, $leg1SourceWarehouse);
}
```

Note the ordering rule preserved from the existing code: reverse Leg 2 fully before touching Leg 1 (opposite of how stock moved), unchanged by this warehouse threading — only the trailing argument on each call is new.

- [ ] **Step 4: Run test to verify it passes**

Run: `php femi9/billing/includes/tests/UndoAutoTransferWarehouseTest.php`
Expected: PASS — all assertions green.

- [ ] **Step 5: Run the full existing regression suite**

Run each of these in turn and confirm all still pass (this task touches a function several other tests already exercise):
```
php femi9/billing/includes/tests/UndoAutoTransferTest.php
php femi9/billing/includes/tests/AutoTransferDemandTest.php
php femi9/billing/includes/tests/InternalTransferWarehouseKeyTest.php
php femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php
```
Expected: all PASS, zero failures — confirms the new warehouse-lookup logic in `undo_auto_transfer()` doesn't change behavior for any case those tests already cover (all of which use `warehouse_id = NULL` throughout, so `auto_transfer_leg_warehouse()` should resolve to `null` for every one of them, reproducing prior behavior bit-for-bit).

- [ ] **Step 6: Commit**

```bash
git add femi9/billing/company/include/AutoTransferDemand.php \
        femi9/billing/includes/tests/UndoAutoTransferWarehouseTest.php
git commit -m "Undo Auto Transfer reverses against the exact warehouse each leg used"
```

---

## Self-Review Notes

- **Spec coverage:** Task 1 covers the mapping table + helpers; Task 2 covers the admin UI; Task 3 covers Auto Transfer's per-row pickers + live availability; Task 4 covers the actual transfer-execution warehouse threading; Task 5 covers manual Internal Transfer's picker filtering; Task 6 covers Transfer History; Task 7 covers Undo. Every section of the design doc has a corresponding task.
- **Placeholder scan:** the one explicit `// NOTE:` placeholder in Task 3 Step 4 is intentional and justified — it depends on a runtime `grep`/read (`cap_auto_transfer_qty_by_source()`'s exact current shape) that Task 3 Step 1 requires happen first, and the design doc itself flagged this exact function as unconfirmed at spec time. This is not a "TBD" in the prohibited sense (a step with no actionable content); it's a data dependency the plan correctly defers to implementation-time discovery, called out explicitly rather than guessed at.
- **Type/signature consistency:** every `StockService`/`AutoTransferDemand.php`/`GodownWarehouseMapping.php` function name and parameter used across tasks was re-verified against the actual current file contents during plan-writing (not assumed from the design doc alone) — `getClosingQty`, `transferOut`, `transferIn`, `reverseTransferOut`, `reverseTransferIn` signatures all confirmed via direct file reads in this session before being written into the plan.

## Estimated implementation time

Rough sizing, assuming one focused implementer (human or agent) working through the tasks sequentially, including writing+running each test and manual verification by the user afterward (not included in the estimate, since that's the user's own step per the project's standing no-unrequested-testing rule):

| Task | Description | Estimate |
|---|---|---|
| 1 | Mapping table + helper module + test | 30–45 min |
| 2 | Admin UI checkboxes on manage-warehouses.php | 20–30 min |
| 3 | Auto Transfer pickers + availability endpoint + JS wiring | 60–90 min (the JS capping-recompute port is the fiddliest part) |
| 4 | Thread warehouse ids through the two-leg action handler | 30–45 min |
| 5 | Filter manual Internal Transfer's pickers | 30–45 min |
| 6 | Transfer History surfacing | 30–40 min |
| 7 | Undo warehouse-correctness | 30–45 min |

**Total: roughly 4–6 hours of focused implementation work**, plus whatever time the user spends manually verifying each piece on their own local/production environment afterward (per the standing rule, not something the implementer does automatically). Task 3 is the most likely to run long, since it's the only task involving non-trivial new client-side JS logic (the live-recompute capping port) rather than a straightforward server-side threading change.
