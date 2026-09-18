# Pieces ↔ Pack Converter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a manual, auditable, warehouse-aware "Convert Pieces ↔
Packs" action to the Neksomo login, built on the existing
`stock.extra_pieces` column.

**Architecture:** Two new `StockService` methods
(`convertPiecesToPack()`/`convertPackToPieces()`), built entirely from
existing private helpers (`lockStockRow()`, `updateStockSnapshot()`,
`writeLedger()`) — no new StockService internals. One new page
(`neksomo-piece-pack-convert.php`) with an AJAX endpoint
(`get-piece-pack-stock.php`) for live current-stock display, one action
handler, one menu link.

**Tech Stack:** PHP 8 + mysqli, existing `StockService`
(`femi9/billing/company/include/StockService.php`). Tests follow this
repo's convention: standalone PHP scripts under
`femi9/billing/includes/tests/` with a disposable MySQL schema, run via
`php <file>.php` (no PHPUnit).

**Spec:** `docs/superpowers/specs/2026-09-18-pieces-pack-converter-design.md`

## Global Constraints

- Each `StockService` call represents one user action and writes
  exactly one `stock_ledger` row — the method takes a `$packCount`
  parameter directly and multiplies internally by `piecesPerPack`,
  matching the convention already used by every other `StockService`
  method (e.g. `acceptReturn($qty, ...)` — see
  `StockService.php:508-538` — one call, one ledger row, caller passes
  the full requested quantity, never called in an internal loop per
  unit).
- Product list for this feature is `products WHERE pieces_per_pack > 1
  AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL)` — the same
  "mapped normal company product" filter used everywhere else in this
  project's reports (`neksomo-company-stock.php`,
  `overall-stock.php`). Never the raw `temp_id LIKE 'NKS-%'` placeholder
  products used by the *purchase* flow — those are a different concept
  (internal purchase-tracking rows, not real sellable stock).
- Warehouse selection is required (not optional/unassigned-by-default)
  on the converter form — this feature exists specifically to make
  warehouse-scoped piece stock visible and actionable, so defaulting to
  "unassigned" would undercut the point.
- No `StockLots`/FIFO interaction in either new method — a conversion
  reshapes existing stock's unit granularity at the same cost basis, it
  doesn't create value or move it between locations.
- `neksomo_credit_pieces()` (inside
  `neksomo-manufacturer-purchase-action.php`) is completely untouched —
  this plan is purely additive.

---

## File Structure

- **Modify:** `femi9/billing/company/include/StockService.php` — add
  `convertPiecesToPack()` and `convertPackToPieces()`.
- **Create:** `femi9/billing/company/get-piece-pack-stock.php` — AJAX
  JSON endpoint returning current `closing_qty`/`extra_pieces` for a
  given product+warehouse.
- **Create:** `femi9/billing/company/neksomo-piece-pack-convert.php` —
  the form page.
- **Create:** `femi9/billing/company/neksomo-piece-pack-convert-action.php`
  — the action handler.
- **Modify:** `femi9/billing/company/femi_menu.php` — add the menu
  link under Neksomo's existing "Stock" submenu.
- **Test:** `femi9/billing/includes/tests/StockServicePiecesPackConvertTest.php`
  (new).

---

### Task 1: `StockService` conversion methods

**Files:**
- Modify: `femi9/billing/company/include/StockService.php`
- Test: `femi9/billing/includes/tests/StockServicePiecesPackConvertTest.php`

**Interfaces:**
- Produces:
  - `StockService::convertPiecesToPack(int $productId, string $userType, string $userId, int $piecesPerPack, int $packCount, string $refId, string $createdBy, bool $externalTransaction = false, ?int $warehouseId = null): array`
  - `StockService::convertPackToPieces(int $productId, string $userType, string $userId, int $piecesPerPack, int $packCount, string $refId, string $createdBy, bool $externalTransaction = false, ?int $warehouseId = null): array`
  - Both return `['success' => true, 'ledger_id' => int, 'closing_qty_after' => int, 'extra_pieces_after' => int]` on success; throw `StockException` on insufficient stock or missing row (matching `deduct()`/`transferOut()`'s throwing convention, since a conversion the user explicitly requested failing silently would be confusing — unlike `acceptReturn()`'s softer `['success' => false]` return, which fits a different caller context).

- [ ] **Step 1: Write the failing test**

Create `femi9/billing/includes/tests/StockServicePiecesPackConvertTest.php`:

```php
<?php
// femi9/billing/includes/tests/StockServicePiecesPackConvertTest.php
// Manual run: php StockServicePiecesPackConvertTest.php
//
// Regression/behavior test for the Pieces <-> Pack converter
// (docs/superpowers/specs/2026-09-18-pieces-pack-converter-design.md):
// confirms StockService::convertPiecesToPack()/convertPackToPieces()
// correctly move quantity between closing_qty and extra_pieces on the
// same stock row, scope by warehouse_id, write a ledger entry per
// call, and refuse when there isn't enough of the source unit.
//
// Uses a disposable `stock_service_pieces_pack_test` schema — never
// the app's production database — with real `stock` / `stock_ledger`
// table shapes.

require_once __DIR__ . '/../../company/include/StockService.php';
require_once __DIR__ . '/../../company/include/db-connect.php'; // reuses $db_conn's credentials/host only

$conn = $db_conn;

const TEST_SCHEMA = 'stock_service_pieces_pack_test';

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

$stockService = new StockService($conn);

// ---- Fixture: product 50, godown '3', warehouse 401 — 5 packs, 8 loose pieces. pieces_per_pack=12 ----
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, extra_pieces, user_type, user_id, warehouse_id)
    VALUES (50, 0, 5, 0, 0, 0, 5, 8, 'company', '3', 401)");
// A second, unrelated row for the same product but a DIFFERENT warehouse — proves scoping.
$conn->query("INSERT INTO stock
    (product_id, opening_qty, input_qty, sales_qty, sent_qty, returnqty, closing_qty, extra_pieces, user_type, user_id, warehouse_id)
    VALUES (50, 0, 100, 0, 0, 0, 100, 999, 'company', '3', 402)");

// ---- Test: insufficient pieces (need 12, have 8) throws and changes nothing ----
$threw = false;
try {
    $stockService->convertPiecesToPack(50, 'company', '3', 12, 1, 'CONV-1', 'tester', false, 401);
} catch (StockException $e) {
    $threw = true;
}
assertEqual($threw, true, 'convertPiecesToPack throws StockException when extra_pieces < piecesPerPack');

$unchanged = $conn->query("SELECT closing_qty, extra_pieces FROM stock WHERE product_id=50 AND warehouse_id=401")->fetch_assoc();
assertEqual((int)$unchanged['closing_qty'], 5, 'Failed conversion leaves closing_qty untouched');
assertEqual((int)$unchanged['extra_pieces'], 8, 'Failed conversion leaves extra_pieces untouched');

// ---- Test: pack -> pieces (break open 1 pack) succeeds: 5 packs -> 4, 8 pieces -> 20 ----
$result = $stockService->convertPackToPieces(50, 'company', '3', 12, 1, 'CONV-2', 'tester', false, 401);
assertEqual($result['success'], true, 'convertPackToPieces succeeds when closing_qty >= packCount');
assertEqual($result['closing_qty_after'], 4, 'convertPackToPieces return value reports closing_qty_after=4');
assertEqual($result['extra_pieces_after'], 20, 'convertPackToPieces return value reports extra_pieces_after=20 (8+12)');

$afterBreak = $conn->query("SELECT closing_qty, extra_pieces FROM stock WHERE product_id=50 AND warehouse_id=401")->fetch_assoc();
assertEqual((int)$afterBreak['closing_qty'], 4, 'DB: closing_qty decremented to 4');
assertEqual((int)$afterBreak['extra_pieces'], 20, 'DB: extra_pieces incremented to 20');

// ---- Test: now pieces -> pack succeeds (20 >= 12): closing_qty 4->5, extra_pieces 20->8 ----
$result2 = $stockService->convertPiecesToPack(50, 'company', '3', 12, 1, 'CONV-3', 'tester', false, 401);
assertEqual($result2['closing_qty_after'], 5, 'convertPiecesToPack return value reports closing_qty_after=5');
assertEqual($result2['extra_pieces_after'], 8, 'convertPiecesToPack return value reports extra_pieces_after=8 (20-12)');

// ---- Test: multi-pack conversion in one call (packCount=2): needs 24 pieces ----
$conn->query("UPDATE stock SET extra_pieces=30 WHERE product_id=50 AND warehouse_id=401");
$result3 = $stockService->convertPiecesToPack(50, 'company', '3', 12, 2, 'CONV-4', 'tester', false, 401);
assertEqual($result3['closing_qty_after'], 7, 'Multi-pack convertPiecesToPack(packCount=2): closing_qty 5->7');
assertEqual($result3['extra_pieces_after'], 6, 'Multi-pack convertPiecesToPack(packCount=2): extra_pieces 30-24=6');

// ---- Test: warehouse 402's row is completely untouched by all the above ----
$other = $conn->query("SELECT closing_qty, extra_pieces FROM stock WHERE product_id=50 AND warehouse_id=402")->fetch_assoc();
assertEqual((int)$other['closing_qty'], 100, 'Warehouse-402 row closing_qty untouched by warehouse-401 conversions');
assertEqual((int)$other['extra_pieces'], 999, 'Warehouse-402 row extra_pieces untouched by warehouse-401 conversions');

// ---- Test: ledger entries record the correct action/qty/before/after ----
$ledgerRows = $conn->query("SELECT action, qty, qty_before, qty_after, warehouse_id FROM stock_ledger WHERE product_id=50 ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
assertEqual(count($ledgerRows), 3, 'Exactly 3 ledger rows written (1 failed attempt writes none, 3 successful conversions each write 1)');
assertEqual($ledgerRows[0]['action'], 'pack_to_pieces', 'Ledger row 1 action=pack_to_pieces');
assertEqual((int)$ledgerRows[0]['qty'], 1, 'Ledger row 1 qty=1 (pack count)');
assertEqual((int)$ledgerRows[0]['qty_before'], 5, 'Ledger row 1 qty_before=5 (closing_qty before)');
assertEqual((int)$ledgerRows[0]['qty_after'], 4, 'Ledger row 1 qty_after=4 (closing_qty after)');
assertEqual($ledgerRows[1]['action'], 'pieces_to_pack', 'Ledger row 2 action=pieces_to_pack');
assertEqual($ledgerRows[2]['action'], 'pieces_to_pack', 'Ledger row 3 action=pieces_to_pack');
assertEqual((int)$ledgerRows[2]['qty'], 2, 'Ledger row 3 (multi-pack) qty=2');
assertEqual((int)$ledgerRows[0]['warehouse_id'], 401, 'Ledger rows record the correct warehouse_id');

// ---- Test: no stock row at all for the given warehouse throws ----
$threwNoRow = false;
try {
    $stockService->convertPiecesToPack(50, 'company', '3', 12, 1, 'CONV-5', 'tester', false, 403);
} catch (StockException $e) {
    $threwNoRow = true;
}
assertEqual($threwNoRow, true, 'convertPiecesToPack throws StockException when no stock row exists for that warehouse');

// ========== TEARDOWN ==========
$conn->select_db('information_schema');
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");

echo "\n$passCount passed, $failCount failed\n";
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php "femi9/billing/includes/tests/StockServicePiecesPackConvertTest.php"`
Expected: FATAL error — `convertPiecesToPack()`/`convertPackToPieces()`
don't exist yet on `StockService`.

- [ ] **Step 3: Add the two methods to `StockService.php`**

Read the file first to find a sensible insertion point — immediately
after `transferIn()`/`reverseTransferIn()` (the last pair of
"same-shape mutation" methods) is a reasonable location, before the
`private function` section begins. Add:

```php
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
        ?int   $warehouseId = null
    ): array {
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
                'conversion', $refId, '', $createdBy, $warehouseId
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
        ?int   $warehouseId = null
    ): array {
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
                'conversion', $refId, '', $createdBy, $warehouseId
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php "femi9/billing/includes/tests/StockServicePiecesPackConvertTest.php"`
Expected: `24 passed, 0 failed` (verified by actually running this
exact test against a scratch copy of the two methods below, during
plan authoring — all 24 assertions passed on the first run).

- [ ] **Step 5: Syntax check**

```bash
php -l "femi9/billing/company/include/StockService.php"
```
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add "femi9/billing/company/include/StockService.php" "femi9/billing/includes/tests/StockServicePiecesPackConvertTest.php"
git commit -m "Add StockService::convertPiecesToPack/convertPackToPieces"
```

---

### Task 2: AJAX current-stock endpoint

**Files:**
- Create: `femi9/billing/company/get-piece-pack-stock.php`

**Interfaces:**
- Produces: JSON `{"closing_qty": int, "extra_pieces": int, "pieces_per_pack": int}` for a given `product_id` + `warehouse_id` (or `null` for unassigned), or `{"closing_qty": 0, "extra_pieces": 0, "pieces_per_pack": <value>}` if no stock row exists yet — never a hard error, matching `get-godown-products.php`'s convention of returning an empty-but-valid JSON shape rather than a 404/500 for a "nothing here yet" case.

- [ ] **Step 1: Write the endpoint**

Create `femi9/billing/company/get-piece-pack-stock.php`:

```php
<?php
include("checksession.php");
require_once("include/GodownAccess.php");
header('Content-Type: application/json');
error_reporting(0);

// Dedicated to the neksomo login (admin retained for oversight/support),
// matching every other Neksomo-only page's gate.
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$productId = (int)($_GET['product_id'] ?? 0);
$warehouseId = filter_var($_GET['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;

if (!$productId) {
    echo json_encode(['error' => 'invalid_product']);
    exit;
}

$productStmt = $db_conn->prepare("SELECT pieces_per_pack FROM products WHERE id = ?");
$productStmt->bind_param('i', $productId);
$productStmt->execute();
$productRow = $productStmt->get_result()->fetch_assoc();
$productStmt->close();

if (!$productRow) {
    echo json_encode(['error' => 'product_not_found']);
    exit;
}

$piecesPerPack = max((int)($productRow['pieces_per_pack'] ?? 1), 1);

$sql = "SELECT closing_qty, extra_pieces FROM stock
        WHERE product_id = ? AND user_type = 'company' AND user_id = ?
          AND warehouse_id " . ($warehouseId === null ? 'IS NULL' : '= ?');
$stmt = $db_conn->prepare($sql);
$godownId = (string)((int)($_GET['godown_id'] ?? 0));
if ($warehouseId === null) {
    $stmt->bind_param('is', $productId, $godownId);
} else {
    $stmt->bind_param('isi', $productId, $godownId, $warehouseId);
}
$stmt->execute();
$stockRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'closing_qty'     => (int)($stockRow['closing_qty'] ?? 0),
    'extra_pieces'    => (int)($stockRow['extra_pieces'] ?? 0),
    'pieces_per_pack' => $piecesPerPack,
]);
```

Note: this endpoint requires a `godown_id` query param too (which
company entity — LLP/Healthcare/Neksomo — not just which warehouse),
since `stock` is keyed by `(product_id, user_type, user_id,
warehouse_id)` and `user_id` here is the company godown id, not the
warehouse. Task 3's page passes both.

- [ ] **Step 2: Syntax check**

```bash
php -l "femi9/billing/company/get-piece-pack-stock.php"
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add "femi9/billing/company/get-piece-pack-stock.php"
git commit -m "Add AJAX endpoint for current pieces/pack stock lookup"
```

---

### Task 3: Form page — `neksomo-piece-pack-convert.php`

**Files:**
- Create: `femi9/billing/company/neksomo-piece-pack-convert.php`

**Interfaces:**
- Consumes: `get_login_usertype()`, `get-piece-pack-stock.php` (Task 2)
  via `fetch()`.
- Produces: a form posting to `neksomo-piece-pack-convert-action.php`
  (Task 4) with fields `godownid`, `warehouse_id`, `product_id`,
  `direction` (`pieces_to_pack` / `pack_to_pieces`), `pack_count`,
  `csrf_token`.

- [ ] **Step 1: Write the page**

Create `femi9/billing/company/neksomo-piece-pack-convert.php`:

```php
<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support).
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Mapped normal company products only — never the raw NKS- placeholder
// products used by the purchase flow (see Global Constraints).
$products = $db_conn->query(
    "SELECT id, productName, pieces_per_pack FROM products
     WHERE pieces_per_pack > 1 AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL)
     ORDER BY productName ASC"
)->fetch_all(MYSQLI_ASSOC);

$godowns = $db_conn->query(
    "SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY id ASC"
)->fetch_all(MYSQLI_ASSOC);

$warehouses = $db_conn->query(
    "SELECT id, code, name FROM warehouses WHERE is_active = 1 ORDER BY code ASC"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Convert Pieces &harr; Packs : <?php echo $business_name; ?></title>
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>
        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="page-description">
                        <h1>Convert Pieces &harr; Packs</h1>
                    </div>

                    <?php if (isset($_SESSION['errorMessage'])): $flashErr = htmlspecialchars($_SESSION['errorMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['errorMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"error",title:"Error",text:"<?= $flashErr ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['sucMessage'])): $flashMsg = htmlspecialchars($_SESSION['sucMessage'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['sucMessage']); ?>
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>Swal.fire({icon:"success",title:"Success",text:"<?= $flashMsg ?>",confirmButtonText:"OK"});</script>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-body">
                                    <form action="neksomo-piece-pack-convert-action.php" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                                        <div class="mb-3">
                                            <label class="form-label">Product <span class="required">*</span></label>
                                            <select required name="product_id" id="productSelect" class="form-control">
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($products as $p): ?>
                                                <option value="<?= (int)$p['id'] ?>" data-pieces-per-pack="<?= (int)$p['pieces_per_pack'] ?>">
                                                    <?= htmlspecialchars($p['productName'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$p['pieces_per_pack'] ?>/pack)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Company Profile <span class="required">*</span></label>
                                            <select required name="godownid" id="godownSelect" class="form-control">
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($godowns as $g): ?>
                                                <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['gname'], ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Godown (physical) <span class="required">*</span></label>
                                            <select required name="warehouse_id" id="warehouseSelect" class="form-control">
                                                <option value="" hidden>Select</option>
                                                <?php foreach ($warehouses as $wh): ?>
                                                <option value="<?= (int)$wh['id'] ?>"><?= htmlspecialchars($wh['code'], ENT_QUOTES, 'UTF-8') ?><?= $wh['name'] ? ' - ' . htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') : '' ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div id="currentStockPanel" class="alert alert-info" style="display:none;"></div>

                                        <div class="mb-3">
                                            <label class="form-label">Direction <span class="required">*</span></label><br>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="direction" id="dirP2P" value="pieces_to_pack" checked>
                                                <label class="form-check-label" for="dirP2P">Pieces &rarr; Pack (assemble)</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="direction" id="dirPack2P" value="pack_to_pieces">
                                                <label class="form-check-label" for="dirPack2P">Pack &rarr; Pieces (break open)</label>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Number of Packs <span class="required">*</span></label>
                                            <input type="number" min="1" required name="pack_count" class="form-control">
                                            <div class="form-text">How many whole packs to assemble or break open.</div>
                                        </div>

                                        <button type="submit" class="btn btn-primary">Convert</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script>
function refreshCurrentStock() {
    var productId = document.getElementById('productSelect').value;
    var godownId = document.getElementById('godownSelect').value;
    var warehouseId = document.getElementById('warehouseSelect').value;
    var panel = document.getElementById('currentStockPanel');
    if (!productId || !godownId || !warehouseId) {
        panel.style.display = 'none';
        return;
    }
    fetch('get-piece-pack-stock.php?product_id=' + encodeURIComponent(productId) + '&godown_id=' + encodeURIComponent(godownId) + '&warehouse_id=' + encodeURIComponent(warehouseId))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) { panel.style.display = 'none'; return; }
            panel.style.display = '';
            panel.textContent = 'Current stock: ' + data.closing_qty + ' pack(s), ' + data.extra_pieces + ' loose piece(s) — ' + data.pieces_per_pack + ' pieces per pack.';
        })
        .catch(function () { panel.style.display = 'none'; });
}
['productSelect', 'godownSelect', 'warehouseSelect'].forEach(function (id) {
    document.getElementById(id).addEventListener('change', refreshCurrentStock);
});
</script>
</body>
</html>
```

- [ ] **Step 2: Syntax check**

```bash
php -l "femi9/billing/company/neksomo-piece-pack-convert.php"
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add "femi9/billing/company/neksomo-piece-pack-convert.php"
git commit -m "Add Convert Pieces <-> Packs form page"
```

---

### Task 4: Action handler — `neksomo-piece-pack-convert-action.php`

**Files:**
- Create: `femi9/billing/company/neksomo-piece-pack-convert-action.php`

**Interfaces:**
- Consumes: `$_POST['product_id']`, `$_POST['godownid']`,
  `$_POST['warehouse_id']`, `$_POST['direction']`,
  `$_POST['pack_count']` from Task 3's form;
  `StockService::convertPiecesToPack()`/`convertPackToPieces()` from
  Task 1.
- Produces: a `stock_ledger` row per conversion; redirects back to
  `neksomo-piece-pack-convert.php` with a flash success/error message.

- [ ] **Step 1: Write the action handler**

Create `femi9/billing/company/neksomo-piece-pack-convert-action.php`:

```php
<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
include("config.php");

// Dedicated to the neksomo login (admin retained for oversight/support).
$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['errorMessage'] = "Invalid form submission. Please try again.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$productId   = (int)($_POST['product_id'] ?? 0);
$godownId    = (string)(int)($_POST['godownid'] ?? 0);
$warehouseId = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$direction   = $_POST['direction'] ?? '';
$packCount   = (int)($_POST['pack_count'] ?? 0);

if (!$productId || !$godownId || $packCount < 1 || !in_array($direction, ['pieces_to_pack', 'pack_to_pieces'], true)) {
    $_SESSION['errorMessage'] = "Invalid submission — please fill in every field.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}

if (!is_godown_allowed($db_conn, (int)$godownId)) {
    $_SESSION['errorMessage'] = "You are not authorized to use this company profile.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}

// Warehouse is required for this feature specifically (see plan's Global
// Constraints) — the picker is `required` client-side, this re-validates
// server-side since the client-side attribute alone is never trusted.
if ($warehouseId === null) {
    $_SESSION['errorMessage'] = "Please select a godown (physical).";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}

$stmt = $db_conn->prepare("SELECT pieces_per_pack FROM products WHERE id = ? AND pieces_per_pack > 1");
$stmt->bind_param('i', $productId);
$stmt->execute();
$productRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$productRow) {
    $_SESSION['errorMessage'] = "Selected product does not support pieces/pack conversion.";
    header("Location: neksomo-piece-pack-convert.php");
    exit;
}
$piecesPerPack = (int)$productRow['pieces_per_pack'];

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';
$refId        = 'CONV-' . date('YmdHis') . '-' . random_int(100, 999);

try {
    if ($direction === 'pieces_to_pack') {
        $result = $stockService->convertPiecesToPack(
            $productId, 'company', $godownId, $piecesPerPack, $packCount,
            $refId, $createdBy, false, $warehouseId
        );
        $_SESSION['sucMessage'] = "Assembled $packCount pack(s). New stock: {$result['closing_qty_after']} pack(s), {$result['extra_pieces_after']} loose piece(s).";
    } else {
        $result = $stockService->convertPackToPieces(
            $productId, 'company', $godownId, $piecesPerPack, $packCount,
            $refId, $createdBy, false, $warehouseId
        );
        $_SESSION['sucMessage'] = "Broke open $packCount pack(s). New stock: {$result['closing_qty_after']} pack(s), {$result['extra_pieces_after']} loose piece(s).";
    }
} catch (StockException $e) {
    $_SESSION['errorMessage'] = $e->getMessage();
} catch (\Throwable $e) {
    error_log("neksomo-piece-pack-convert-action error: " . $e->getMessage());
    $_SESSION['errorMessage'] = "An error occurred. Please try again.";
}

header("Location: neksomo-piece-pack-convert.php");
exit;
```

- [ ] **Step 2: Syntax check**

```bash
php -l "femi9/billing/company/neksomo-piece-pack-convert-action.php"
```
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add "femi9/billing/company/neksomo-piece-pack-convert-action.php"
git commit -m "Add Convert Pieces <-> Packs action handler"
```

---

### Task 5: Menu entry

**Files:**
- Modify: `femi9/billing/company/femi_menu.php`

**Interfaces:**
- Consumes: the existing Neksomo "Stock" submenu block (containing
  `neksomo-purchase-stock.php`).

- [ ] **Step 1: Locate the Neksomo Stock submenu**

Run:
```bash
grep -n "neksomo-purchase-stock.php" "femi9/billing/company/femi_menu.php"
```
Expected: 1 line number, inside the block starting `<a href="#">...Stock<i
class="material-icons has-sub-menu">` (confirmed during planning at
approximately line 184 — re-confirm the exact line via this grep before
editing, since other work may have shifted line numbers since).

- [ ] **Step 2: Add the new link**

Add a new `<li>` immediately after the `neksomo-purchase-stock.php`
link, inside the same `<ul class="sub-menu">` block:

```html
<li><a href="neksomo-purchase-stock.php">Purchase Stock</a></li>
<li><a href="neksomo-piece-pack-convert.php">Convert Pieces &harr; Packs</a></li>
```

- [ ] **Step 3: Syntax check**

```bash
php -l "femi9/billing/company/femi_menu.php"
```
Expected: `No syntax errors detected`

- [ ] **Step 4: Verify the link was added exactly once**

```bash
grep -c "neksomo-piece-pack-convert.php" "femi9/billing/company/femi_menu.php"
```
Expected: `1` (this link belongs in the Neksomo-specific menu block
only, unlike the per-godown project's earlier "Add Input Stock"-style
links which needed adding to multiple duplicated role blocks — Neksomo
has one menu block, not four).

- [ ] **Step 5: Commit**

```bash
git add "femi9/billing/company/femi_menu.php"
git commit -m "Add Convert Pieces <-> Packs menu link"
```

---

### Task 6: Full regression pass and manual verification

**Files:** none (verification only, no code changes)

- [ ] **Step 1: Run every warehouse/StockService-related test file together**

```bash
for f in femi9/billing/includes/tests/StockServiceWarehouseKeyTest.php \
         femi9/billing/includes/tests/StockServiceReverseTransferInTest.php \
         femi9/billing/includes/tests/InputActionWarehouseKeyTest.php \
         femi9/billing/includes/tests/StockReturnUpdateWarehouseKeyTest.php \
         femi9/billing/includes/tests/InternalTransferWarehouseKeyTest.php \
         femi9/billing/includes/tests/OtSaleWarehouseKeyTest.php \
         femi9/billing/includes/tests/InvoiceStockUpdateWarehouseKeyTest.php \
         femi9/billing/includes/tests/TpInvoiceWarehouseKeyTest.php \
         femi9/billing/includes/tests/StockLotsWarehouseKeyTest.php \
         femi9/billing/includes/tests/StockServicePiecesPackConvertTest.php; do
  echo "=== $f ==="
  php "$f" | tail -2
done
```

Expected: every file ends with `N passed, 0 failed` — confirms the two
new `StockService` methods didn't regress anything in the class they
were added to (a syntax slip in one method could break the whole file's
`require_once`, which every one of these tests would catch).

- [ ] **Step 2: Confirm no unexpected file changes**

```bash
git status --short
```
Expected: clean, or only pre-existing unrelated files already noted in
earlier phases of this project — nothing from this plan left
uncommitted.

- [ ] **Step 3: Manual browser verification**

Log in as a Neksomo user, navigate to Stock → Convert Pieces ↔ Packs.
Pick a product with `pieces_per_pack > 1`, a company profile, and a
warehouse (create a test warehouse via Manage Godowns first if none
exist). Confirm the current-stock panel updates via AJAX once all three
selections are made. Submit a Pack → Pieces conversion, confirm the
success message shows correct before/after numbers, then submit a
matching Pieces → Pack conversion and confirm the stock returns to its
original state. Query `stock_ledger WHERE ref_type='conversion'` to
confirm both ledger rows exist with the expected `action`/`qty`/
`qty_before`/`qty_after` values.

If MAMP isn't running or a live click-through isn't possible in this
session, skip this step and note the gap honestly in the completion
report — same precedent as every other UI-verification step in this
project. Do not claim this step passed without actually performing it.

- [ ] **Step 4: Report results**

No commit needed for this task — it's verification only.

---

## Self-Review Notes

- **Spec coverage:** every section of the design spec has a
  corresponding task — StockService methods (Task 1), visibility of
  current piece stock (Task 2/3), the page and both directions (Task
  3), the action handler and its ledger writes (Task 4), the menu entry
  (Task 5), and the spec's testing-strategy section (Task 1's test +
  Task 6's manual verification).
- **Placeholder scan:** no TBD/TODO; all code blocks are complete,
  runnable PHP. The spec's two "Open Questions" are both resolved in
  this plan's Global Constraints (one ledger row per call, confirmed
  against `acceptReturn()`'s existing convention; AJAX endpoint chosen
  over page-reload, matching `get-godown-products.php`'s precedent) —
  neither carried forward as an unresolved gap.
- **Type consistency:** `convertPiecesToPack()`/`convertPackToPieces()`
  signatures in Task 1 match their exact call shape in Task 4's action
  handler (positional args, `$externalTransaction=false` since this
  action handler doesn't wrap a larger outer transaction, `$warehouseId`
  last). The AJAX endpoint's response shape (Task 2) matches exactly
  what Task 3's JS `refreshCurrentStock()` reads
  (`closing_qty`/`extra_pieces`/`pieces_per_pack`).
