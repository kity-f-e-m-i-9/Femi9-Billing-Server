# One-Click Auto Internal Transfer for Orders Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a one-click "Auto Transfer for Orders" feature that computes
today's demand (TP purchase orders + drafted OT channel orders for LLP),
shows an editable, stock-capped popup, and on confirm executes both
transfer legs (Neksomo → Healthcare → LLP) atomically, reusing the
existing `StockService` and `internal_transfer` tables.

**Architecture:** Two new PHP pages mirror the existing manual internal
transfer pair (`internal_transfer.php` / `internal_transfer_action.php`):
`internal_transfer_auto.php` (GET: aggregate demand, render popup) and
`internal_transfer_auto_action.php` (POST: execute the two-leg transfer
chain inside one DB transaction, reusing `StockService::transferOut`/
`transferIn`). A shared helper file holds the demand-aggregation and
godown-resolution logic so it can be unit-tested standalone.

**Tech Stack:** PHP 8 + mysqli (procedural/prepared statements), existing
`StockService` (`femi9/billing/company/include/StockService.php`),
Bootstrap/jQuery/SweetAlert2 front end matching existing pages. Tests
follow this repo's convention: standalone PHP scripts under
`femi9/billing/includes/tests/` that spin up a disposable MySQL schema and
run with `php <file>.php` (no PHPUnit in this repo).

**Spec:** `docs/superpowers/specs/2026-09-18-auto-internal-transfer-design.md`

## Global Constraints

- Godown entities are resolved via `company_godown.gname`: Neksomo uses
  the existing `get_neksomo_godown_id($db_conn)` helper
  (`include/NeksomoStockBridge.php`); Healthcare = `'FEMI HEALTH CARE'`;
  LLP = `'FEMI NAYAN LLP'`. No new `companies` table.
- Demand date filter for TP orders is `tp_purchase_orders.order_date`;
  for OT orders it is `ot_sales.date` (NOT `ot_sales_invoice`, which has
  no date column).
- All stock mutations go through `StockService`; never write directly to
  `stock` / `stock_ledger`.
- `user_type` passed to `StockService` is always the literal string
  `'company'` (matches `internal_transfer_action.php`'s use of
  `$Login_user_TYPEvl`, which defaults to `'company'` for this user
  class).
- GST snapshot on `internal_transfer` rows is taken from
  `products.gst` / `products.gst_type` at transfer time, using the same
  inclusive/exclusive branching as `internal_transfer_action.php:154-162`
  (see `[[inclusive-gst-detax-convention]]`).
- Every row's committed transfer qty is capped to actually-available
  stock at commit time — never hard-fail a row; cap and report instead.

---

## File Structure

- **Create:** `femi9/billing/company/include/AutoTransferDemand.php` —
  pure functions: resolve godown ids by `gname`, aggregate required qty
  per product from TP orders + OT drafts, compute stock-capped transfer
  qty. No session/HTML — fully unit-testable against a raw `$db_conn`.
- **Create:** `femi9/billing/company/internal_transfer_auto.php` — page
  that includes `AutoTransferDemand.php`, runs the aggregation, renders
  the popup/table and a form posting to the action page.
- **Create:** `femi9/billing/company/internal_transfer_auto_action.php`
  — POST handler: re-validates/caps quantities, writes both transfer legs
  via `StockService`, commits atomically, redirects.
- **Modify:** `femi9/billing/company/femi_menu.php` — add "Auto Transfer
  for Orders" link in all 4 duplicated Internal Stock Transfer submenu
  blocks.
- **Test:** `femi9/billing/includes/tests/AutoTransferDemandTest.php` —
  standalone script testing the aggregation/capping logic in
  `AutoTransferDemand.php` against a disposable schema.

---

### Task 1: Demand aggregation and godown resolution helper

**Files:**
- Create: `femi9/billing/company/include/AutoTransferDemand.php`
- Test: `femi9/billing/includes/tests/AutoTransferDemandTest.php`

**Interfaces:**
- Produces:
  - `resolve_godown_id_by_gname(mysqli $db_conn, string $gname): ?int`
  - `get_auto_transfer_requirements(mysqli $db_conn, int $llpGodownId): array`
    — returns `[product_id => int requiredQty]` summed from today's
    waiting TP purchase orders + today's draft OT orders for
    `$llpGodownId`.
  - `cap_auto_transfer_qty(int $required, int $neksomoAvail, int $healthcareAvail): int`
    — returns `min($required, $neksomoAvail + $healthcareAvail)`, floored
    at 0.

- [ ] **Step 1: Write the failing test for `resolve_godown_id_by_gname`**

Create `femi9/billing/includes/tests/AutoTransferDemandTest.php`:

```php
<?php
// femi9/billing/includes/tests/AutoTransferDemandTest.php
// Manual run: php AutoTransferDemandTest.php
//
// Tests the pure demand-aggregation/capping logic in
// company/include/AutoTransferDemand.php against a disposable schema,
// per docs/superpowers/specs/2026-09-18-auto-internal-transfer-design.md.

require_once __DIR__ . '/../../company/include/db-connect.php';
require_once __DIR__ . '/../../company/include/AutoTransferDemand.php';

$conn = $db_conn;

const TEST_SCHEMA = 'auto_transfer_demand_test';

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

// ========== SETUP: minimal real table shapes ==========
$conn->query("CREATE TABLE company_godown (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gname VARCHAR(255) NOT NULL,
    finance_only TINYINT NOT NULL DEFAULT 0,
    contact VARCHAR(255) NOT NULL DEFAULT ''
)");

$conn->query("CREATE TABLE tp_purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    territory_partner_id INT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    status ENUM('waiting','completed') NOT NULL DEFAULT 'waiting',
    tp_invoice_id INT UNSIGNED NULL,
    notes VARCHAR(500) NOT NULL DEFAULT ''
)");

$conn->query("CREATE TABLE tp_purchase_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id INT UNSIGNED NOT NULL,
    product_id INT NOT NULL,
    qty INT NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0
)");

$conn->query("CREATE TABLE ot_sales_invoice (
    tempid VARCHAR(255) NOT NULL,
    status ENUM('confirmed','draft') NOT NULL DEFAULT 'confirmed'
)");

$conn->query("CREATE TABLE ot_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    godownid INT NOT NULL,
    prid INT NOT NULL,
    qty INT NOT NULL,
    date DATE NOT NULL,
    tempid VARCHAR(255) NOT NULL
)");

// ========== FIXTURES ==========
$conn->query("INSERT INTO company_godown (id, gname) VALUES
    (1, 'NEKSOMO HYGIENE INDUSTRIES'),
    (2, 'FEMI HEALTH CARE'),
    (3, 'FEMI NAYAN LLP')");

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// TP PO: product 101 qty 40, waiting, today
$conn->query("INSERT INTO tp_purchase_orders (id, territory_partner_id, order_date, status) VALUES (1, 9, '$today', 'waiting')");
$conn->query("INSERT INTO tp_purchase_order_items (po_id, product_id, qty) VALUES (1, 101, 40)");

// TP PO: product 101 qty 999, but completed (must be excluded)
$conn->query("INSERT INTO tp_purchase_orders (id, territory_partner_id, order_date, status) VALUES (2, 9, '$today', 'completed')");
$conn->query("INSERT INTO tp_purchase_order_items (po_id, product_id, qty) VALUES (2, 101, 999)");

// TP PO: product 101 qty 999, waiting but yesterday (must be excluded)
$conn->query("INSERT INTO tp_purchase_orders (id, territory_partner_id, order_date, status) VALUES (3, 9, '$yesterday', 'waiting')");
$conn->query("INSERT INTO tp_purchase_order_items (po_id, product_id, qty) VALUES (3, 101, 999)");

// OT draft for LLP (godownid=3), product 101 qty 15, today
$conn->query("INSERT INTO ot_sales_invoice (tempid, status) VALUES ('OTD1', 'draft')");
$conn->query("INSERT INTO ot_sales (godownid, prid, qty, date, tempid) VALUES (3, 101, 15, '$today', 'OTD1')");

// OT confirmed (not draft) for LLP, product 101 qty 999 (must be excluded)
$conn->query("INSERT INTO ot_sales_invoice (tempid, status) VALUES ('OTC1', 'confirmed')");
$conn->query("INSERT INTO ot_sales (godownid, prid, qty, date, tempid) VALUES (3, 101, 999, '$today', 'OTC1')");

// OT draft for Healthcare (godownid=2), product 101 qty 999 (wrong godown, must be excluded)
$conn->query("INSERT INTO ot_sales_invoice (tempid, status) VALUES ('OTD2', 'draft')");
$conn->query("INSERT INTO ot_sales (godownid, prid, qty, date, tempid) VALUES (2, 101, 999, '$today', 'OTD2')");

// Product 202: only an OT draft, qty 7, today, LLP
$conn->query("INSERT INTO ot_sales_invoice (tempid, status) VALUES ('OTD3', 'draft')");
$conn->query("INSERT INTO ot_sales (godownid, prid, qty, date, tempid) VALUES (3, 202, 7, '$today', 'OTD3')");

// ========== TESTS: resolve_godown_id_by_gname ==========
assertEqual(resolve_godown_id_by_gname($conn, 'FEMI HEALTH CARE'), 2, 'resolves Healthcare godown id');
assertEqual(resolve_godown_id_by_gname($conn, 'FEMI NAYAN LLP'), 3, 'resolves LLP godown id');
assertEqual(resolve_godown_id_by_gname($conn, 'DOES NOT EXIST'), null, 'returns null for unknown gname');

// ========== TESTS: get_auto_transfer_requirements ==========
$requirements = get_auto_transfer_requirements($conn, 3);
assertEqual($requirements[101] ?? null, 55, 'product 101: 40 (waiting TP today) + 15 (LLP draft OT today) = 55, excludes completed/yesterday/confirmed/wrong-godown');
assertEqual($requirements[202] ?? null, 7, 'product 202: only the LLP draft OT counts');
assertEqual(count($requirements), 2, 'no extraneous product keys');

// ========== TESTS: cap_auto_transfer_qty ==========
assertEqual(cap_auto_transfer_qty(55, 30, 10), 40, 'caps to neksomo+healthcare available when short');
assertEqual(cap_auto_transfer_qty(55, 100, 100), 55, 'uses required qty when stock is sufficient');
assertEqual(cap_auto_transfer_qty(55, 0, 0), 0, 'floors at 0 when no stock anywhere');

// ========== SUMMARY ==========
echo "\n$passCount passed, $failCount failed\n";
$conn->query("DROP DATABASE IF EXISTS `" . TEST_SCHEMA . "`");
exit($failCount > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php "femi9/billing/includes/tests/AutoTransferDemandTest.php"`
Expected: FATAL error — `AutoTransferDemand.php` doesn't exist yet (or
`resolve_godown_id_by_gname()` undefined).

- [ ] **Step 3: Write `AutoTransferDemand.php`**

Create `femi9/billing/company/include/AutoTransferDemand.php`:

```php
<?php
// femi9/billing/company/include/AutoTransferDemand.php
//
// Pure demand-aggregation and stock-capping logic for the one-click
// auto internal transfer feature. See
// docs/superpowers/specs/2026-09-18-auto-internal-transfer-design.md.
//
// No session/HTML dependencies — takes a mysqli connection and returns
// plain arrays/scalars, so it can be exercised directly by
// includes/tests/AutoTransferDemandTest.php.

/**
 * Resolves a company_godown.id by exact gname match. Returns null if
 * no matching row exists.
 */
function resolve_godown_id_by_gname(mysqli $db_conn, string $gname): ?int
{
    $stmt = $db_conn->prepare("SELECT id FROM company_godown WHERE gname = ? LIMIT 1");
    $stmt->bind_param('s', $gname);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int) $row['id'] : null;
}

/**
 * Aggregates today's required quantity per product from:
 *  - tp_purchase_order_items joined to tp_purchase_orders
 *    WHERE status = 'waiting' AND order_date = CURDATE()
 *  - ot_sales joined to ot_sales_invoice (on tempid)
 *    WHERE ot_sales_invoice.status = 'draft'
 *      AND ot_sales.godownid = $llpGodownId
 *      AND ot_sales.date = CURDATE()
 *
 * Returns [product_id => requiredQty], omitting products with a
 * combined qty of 0 or less.
 */
function get_auto_transfer_requirements(mysqli $db_conn, int $llpGodownId): array
{
    $requirements = [];

    $tpStmt = $db_conn->prepare(
        "SELECT poi.product_id AS product_id, SUM(poi.qty) AS total_qty
         FROM tp_purchase_order_items poi
         INNER JOIN tp_purchase_orders po ON po.id = poi.po_id
         WHERE po.status = 'waiting' AND po.order_date = CURDATE()
         GROUP BY poi.product_id"
    );
    $tpStmt->execute();
    $tpResult = $tpStmt->get_result();
    while ($row = $tpResult->fetch_assoc()) {
        $pid = (int) $row['product_id'];
        $requirements[$pid] = ($requirements[$pid] ?? 0) + (int) $row['total_qty'];
    }
    $tpStmt->close();

    $otStmt = $db_conn->prepare(
        "SELECT os.prid AS product_id, SUM(os.qty) AS total_qty
         FROM ot_sales os
         INNER JOIN ot_sales_invoice osi ON osi.tempid = os.tempid
         WHERE osi.status = 'draft' AND os.godownid = ? AND os.date = CURDATE()
         GROUP BY os.prid"
    );
    $otStmt->bind_param('i', $llpGodownId);
    $otStmt->execute();
    $otResult = $otStmt->get_result();
    while ($row = $otResult->fetch_assoc()) {
        $pid = (int) $row['product_id'];
        $requirements[$pid] = ($requirements[$pid] ?? 0) + (int) $row['total_qty'];
    }
    $otStmt->close();

    return array_filter($requirements, fn($qty) => $qty > 0);
}

/**
 * Caps a required qty to what's actually movable through the
 * Neksomo -> Healthcare -> LLP pass-through: never more than Neksomo's
 * and Healthcare's combined available stock. Never negative.
 */
function cap_auto_transfer_qty(int $required, int $neksomoAvail, int $healthcareAvail): int
{
    $capped = min($required, $neksomoAvail + $healthcareAvail);
    return max(0, $capped);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php "femi9/billing/includes/tests/AutoTransferDemandTest.php"`
Expected: `12 passed, 0 failed` (3 resolve + 3 requirements + 3 cap +
nothing else — recount against the actual assertions above; all lines
print `PASS`).

- [ ] **Step 5: Commit**

```bash
git add "femi9/billing/company/include/AutoTransferDemand.php" "femi9/billing/includes/tests/AutoTransferDemandTest.php"
git commit -m "Add demand-aggregation helper for auto internal transfer"
```

---

### Task 2: Popup page — `internal_transfer_auto.php`

**Files:**
- Create: `femi9/billing/company/internal_transfer_auto.php`

**Interfaces:**
- Consumes: `resolve_godown_id_by_gname()`, `get_auto_transfer_requirements()`,
  `cap_auto_transfer_qty()` from Task 1; `StockService::getClosingQty()`
  (`femi9/billing/company/include/StockService.php:482`); `get_neksomo_godown_id()`
  (`femi9/billing/company/include/NeksomoStockBridge.php:38`); session
  vars `$Login_user_TYPEvl`, `$db_conn`, `$business_name` set by
  `checksession.php`/`config.php`.
- Produces: renders an HTML form (`<form method="post" action="internal_transfer_auto_action.php">`)
  with per-product inputs named `product_id[]` and `qty[]`, so Task 3
  can read `$_REQUEST['product_id']` / `$_REQUEST['qty']` exactly like
  `internal_transfer_action.php:50-51`.

- [ ] **Step 1: Write the page**

Create `femi9/billing/company/internal_transfer_auto.php`:

```php
<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/NeksomoStockBridge.php");
require_once("include/AutoTransferDemand.php");
include("config.php");
date_default_timezone_set("Asia/Kolkata");

$neksomoId    = get_neksomo_godown_id($db_conn);
$healthcareId = resolve_godown_id_by_gname($db_conn, 'FEMI HEALTH CARE');
$llpId        = resolve_godown_id_by_gname($db_conn, 'FEMI NAYAN LLP');

if (!$neksomoId || !$healthcareId || !$llpId) {
    die("Auto Transfer is unavailable: one or more required company profiles "
        . "(Neksomo / FEMI HEALTH CARE / FEMI NAYAN LLP) could not be found "
        . "in company_godown.");
}

$stockService = new StockService($db_conn);
$requirements = get_auto_transfer_requirements($db_conn, $llpId);

$rows = [];
if (!empty($requirements)) {
    $productIds = array_keys($requirements);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = str_repeat('i', count($productIds));
    $stmt = $db_conn->prepare("SELECT id, product_name FROM products WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$productIds);
    $stmt->execute();
    $productResult = $stmt->get_result();
    $productNames = [];
    while ($p = $productResult->fetch_assoc()) {
        $productNames[(int) $p['id']] = $p['product_name'];
    }
    $stmt->close();

    foreach ($requirements as $pid => $required) {
        $neksomoAvail    = (int) ($stockService->getClosingQty($pid, $Login_user_TYPEvl, (string) $neksomoId) ?? 0);
        $healthcareAvail = (int) ($stockService->getClosingQty($pid, $Login_user_TYPEvl, (string) $healthcareId) ?? 0);
        $cappedQty       = cap_auto_transfer_qty($required, $neksomoAvail, $healthcareAvail);
        if ($cappedQty <= 0) continue;

        $rows[] = [
            'product_id'      => $pid,
            'product_name'    => $productNames[$pid] ?? "Product #$pid",
            'required'        => $required,
            'capped'          => $cappedQty,
            'neksomo_avail'   => $neksomoAvail,
            'healthcare_avail'=> $healthcareAvail,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auto Transfer for Orders : <?php echo $business_name; ?></title>
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
<div class="container mt-4">
    <h4>Auto Transfer for Orders</h4>
    <p class="text-muted">
        Quantities required today for waiting Territory Partner purchase
        orders and drafted OT channel orders (LLP), auto-capped to
        available Neksomo + Healthcare stock. Adjust any row before
        transferring.
    </p>

    <?php if (empty($rows)): ?>
        <div class="alert alert-info">Nothing to transfer today.</div>
    <?php else: ?>
        <form method="post" action="internal_transfer_auto_action.php">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Required Qty</th>
                        <th>Available (Neksomo)</th>
                        <th>Available (Healthcare)</th>
                        <th>Qty to Transfer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>
                            <input type="hidden" name="product_id[]" value="<?php echo (int) $row['product_id']; ?>">
                        </td>
                        <td><?php echo (int) $row['required']; ?></td>
                        <td><?php echo (int) $row['neksomo_avail']; ?></td>
                        <td><?php echo (int) $row['healthcare_avail']; ?></td>
                        <td>
                            <input type="number" min="0" name="qty[]"
                                   value="<?php echo (int) $row['capped']; ?>" class="form-control">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary">Transfer Now</button>
        </form>
    <?php endif; ?>
</div>
<script src="../../assets/plugins/jquery/jquery.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
```

- [ ] **Step 2: Syntax check**

Run: `php -l "femi9/billing/company/internal_transfer_auto.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add "femi9/billing/company/internal_transfer_auto.php"
git commit -m "Add Auto Transfer for Orders popup page"
```

---

### Task 3: Action handler — `internal_transfer_auto_action.php`

**Files:**
- Create: `femi9/billing/company/internal_transfer_auto_action.php`

**Interfaces:**
- Consumes: `$_REQUEST['product_id']` (array), `$_REQUEST['qty']` (array)
  from Task 2's form; `StockService::transferOut`/`transferIn`
  (`StockService.php:680`, `:742`); `resolve_godown_id_by_gname()`,
  `get_neksomo_godown_id()` as in Task 2.
- Produces: two sets of `internal_transfer_invoice` / `internal_transfer`
  rows per submitted product (one per leg), tempids formatted
  `AUTO<YmdHis><3-digit-random>-N1`/`-N2` to stay within the
  `internal_transfer.tempid` column and avoid collision with manual
  transfers' `<random>INTTRNS/<date>/<time>` format.

- [ ] **Step 1: Write the action handler**

Create `femi9/billing/company/internal_transfer_auto_action.php`:

```php
<?php
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/NeksomoStockBridge.php");
require_once("include/AutoTransferDemand.php");
include("config.php");
include("RemoveSpecialChar.php");

error_reporting(0);

$productIds = $_REQUEST['product_id'] ?? [];
$qtyArr     = $_REQUEST['qty'] ?? [];

if (!is_array($productIds) || count($productIds) === 0) {
    $_SESSION['errorMessage'] = "No products submitted.";
    echo "<script>window.location='internal_transfer_auto?invalid';</script>";
    exit;
}

$neksomoId    = get_neksomo_godown_id($db_conn);
$healthcareId = resolve_godown_id_by_gname($db_conn, 'FEMI HEALTH CARE');
$llpId        = resolve_godown_id_by_gname($db_conn, 'FEMI NAYAN LLP');

if (!$neksomoId || !$healthcareId || !$llpId) {
    $_SESSION['errorMessage'] = "Required company profiles not found.";
    echo "<script>window.location='internal_transfer_auto?misconfigured';</script>";
    exit;
}

$rows = [];
foreach ($productIds as $i => $rawPid) {
    $pid = (int) $rawPid;
    $qty = (int) RemoveSpecialChar($qtyArr[$i] ?? '0');
    if ($pid <= 0 || $qty <= 0) continue;
    $rows[] = ['pid' => $pid, 'qty' => $qty];
}

if (empty($rows)) {
    $_SESSION['errorMessage'] = "No valid quantities submitted.";
    echo "<script>window.location='internal_transfer_auto?invalid';</script>";
    exit;
}

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';
$username     = htmlspecialchars(strip_tags(trim($_SESSION['LOGIN_USER'] ?? '')), ENT_QUOTES, 'UTF-8');
$usertype     = htmlspecialchars(strip_tags(trim($Login_user_TYPEvl ?? '')), ENT_QUOTES, 'UTF-8');
$date         = date('Y-m-d');

$tempidBase = 'AUTO' . date('YmdHis') . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
$tempid1 = $tempidBase . '-N1'; // Neksomo -> Healthcare
$tempid2 = $tempidBase . '-N2'; // Healthcare -> LLP

$cappedRows = [];

$db_conn->begin_transaction();

try {
    $stmtInvChk = $db_conn->prepare("SELECT COUNT(*) AS n FROM internal_transfer_invoice WHERE tempid = ?");
    $stmtInvIns = $db_conn->prepare(
        "INSERT INTO internal_transfer_invoice (tempid, inv_id, inv_number, courier_charges)
         VALUES (?, '0', ?, '0')"
    );
    $stmtProdIns = $db_conn->prepare(
        "INSERT INTO internal_transfer
             (tempid, send_from, send_to, date, product_id, qty, price, discount,
              sub_total, gst, gst_type, taxable_value, gst_amount, total, hsn, username, usertype)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmtProd = $db_conn->prepare("SELECT gst, gst_type, hsn FROM products WHERE id = ?");

    /**
     * Writes one internal_transfer_invoice (once per tempid) + one
     * internal_transfer row, then performs the StockService
     * transferOut/transferIn pair. Returns the actual qty moved
     * (may be less than $qty if stock is insufficient at commit time).
     */
    $writeLeg = function (
        string $tempid, string $sendFrom, string $sendTo, int $pid, int $qty
    ) use (
        $db_conn, $stockService, $createdBy, $username, $usertype, $date,
        $stmtInvChk, $stmtInvIns, $stmtProdIns, $stmtProd, $Login_user_TYPEvl
    ): int {
        $available = $stockService->getClosingQty($pid, $Login_user_TYPEvl, $sendFrom);
        $actualQty = min($qty, (int) ($available ?? 0));
        if ($actualQty <= 0) return 0;

        $stmtProd->bind_param('i', $pid);
        $stmtProd->execute();
        $prod = $stmtProd->get_result()->fetch_assoc();
        if (!$prod) return 0;

        $gst      = (float) $prod['gst'];
        $gstType  = ($prod['gst_type'] === 'inclusive') ? 'inclusive' : 'exclusive';
        $hsn      = $prod['hsn'];
        $subTotal = 0.0; // auto transfer carries no manual rate entry; billing rate stays 0, cost flows via consumed_rate

        if ($gstType === 'inclusive') {
            $total         = $subTotal;
            $taxableValue  = number_format($total / (1 + $gst / 100), 2, '.', '');
            $gstAmount     = number_format($total - $taxableValue, 2, '.', '');
        } else {
            $taxableValue = number_format($subTotal, 2, '.', '');
            $gstAmount    = number_format($subTotal * $gst / 100, 2, '.', '');
            $total        = (float) $taxableValue + (float) $gstAmount;
        }

        $stmtInvChk->bind_param('s', $tempid);
        $stmtInvChk->execute();
        if ((int) $stmtInvChk->get_result()->fetch_assoc()['n'] === 0) {
            $invNumber = $tempid;
            $stmtInvIns->bind_param('ss', $tempid, $invNumber);
            $stmtInvIns->execute();
        }

        $rate = 0.0;
        $disc = 0.0;
        $stmtProdIns->bind_param(
            'ssssiiddddsssssss',
            $tempid, $sendFrom, $sendTo, $date, $pid, $actualQty,
            $rate, $disc, $subTotal, $gst, $gstType, $taxableValue, $gstAmount, $total, $hsn,
            $username, $usertype
        );
        $stmtProdIns->execute();

        $outResult = $stockService->transferOut(
            $pid, $Login_user_TYPEvl, $sendFrom, $actualQty,
            'transfer', $tempid, $createdBy, true
        );
        $stockService->transferIn(
            $pid, $Login_user_TYPEvl, $sendTo, $actualQty,
            'transfer', $tempid, $createdBy, true,
            $outResult['consumed_rate'] ?? null
        );

        return $actualQty;
    };

    foreach ($rows as $row) {
        $pid = $row['pid'];
        $requestedQty = $row['qty'];

        $legOneQty = $writeLeg($tempid1, (string) $neksomoId, (string) $healthcareId, $pid, $requestedQty);
        if ($legOneQty <= 0) continue;

        $legTwoQty = $writeLeg($tempid2, (string) $healthcareId, (string) $llpId, $pid, $legOneQty);

        if ($legTwoQty < $requestedQty) {
            $cappedRows[] = "Product #$pid: requested $requestedQty, transferred $legTwoQty";
        }
    }

    $stmtInvChk->close();
    $stmtInvIns->close();
    $stmtProdIns->close();
    $stmtProd->close();

    $db_conn->commit();
} catch (StockException $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = "Stock error: " . $e->getMessage();
    echo "<script>window.location='internal_transfer_auto?InvalidStock&&AlertStockError';</script>";
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("internal_transfer_auto_action error: " . $e->getMessage());
    $_SESSION['errorMessage'] = "An error occurred. Please try again.";
    echo "<script>window.location='internal_transfer_auto?saveerror';</script>";
    exit;
}

$_SESSION['sucMessage'] = "Auto transfer complete (Neksomo->Healthcare: $tempid1, Healthcare->LLP: $tempid2)."
    . (empty($cappedRows) ? "" : " Capped: " . implode('; ', $cappedRows));
echo "<script>window.location='internal_transfer_manage';</script>";
```

- [ ] **Step 2: Syntax check**

Run: `php -l "femi9/billing/company/internal_transfer_auto_action.php"`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add "femi9/billing/company/internal_transfer_auto_action.php"
git commit -m "Add auto internal transfer action handler (two-leg StockService chain)"
```

---

### Task 4: Menu entry

**Files:**
- Modify: `femi9/billing/company/femi_menu.php`

**Interfaces:**
- Consumes: existing `internal_transfer` submenu blocks (4 occurrences,
  each containing `<li><a href="internal_transfer_return_manage">Credit Notes (Transfer Returns)</a></li>`
  as the last entry in the flyout).

- [ ] **Step 1: Locate all 4 occurrences**

Run:
```bash
grep -n 'internal_transfer_return_manage' "femi9/billing/company/femi_menu.php"
```

Expected: 4 line numbers (one per duplicated menu block).

- [ ] **Step 2: Add the new link after each occurrence**

For each of the 4 matches, use Edit to change:

```html
<li><a href="internal_transfer_return_manage">Credit Notes (Transfer Returns)</a></li>
```

to:

```html
<li><a href="internal_transfer_return_manage">Credit Notes (Transfer Returns)</a></li>
<li><a href="internal_transfer_auto">Auto Transfer for Orders</a></li>
```

Since all 4 occurrences share identical surrounding text, edit them one
at a time using enough surrounding context (the enclosing `<ul>`/menu
block's unique preceding lines, e.g. the permission-check `if` above
block 4 at ~L869, or the specific role heading above blocks 1-3) to
target each occurrence individually — do not use a blind `replace_all`,
since that correctly hits all 4 identical lines in one pass only if no
other unrelated line in the file matches the same text (confirm via the
Step 1 grep count: exactly 4 matches expected).

- [ ] **Step 3: Syntax check**

Run: `php -l "femi9/billing/company/femi_menu.php"`
Expected: `No syntax errors detected`

- [ ] **Step 4: Verify all 4 blocks were updated**

Run:
```bash
grep -c 'internal_transfer_auto"' "femi9/billing/company/femi_menu.php"
```
Expected: `4`

- [ ] **Step 5: Commit**

```bash
git add "femi9/billing/company/femi_menu.php"
git commit -m "Add Auto Transfer for Orders menu link"
```

---

### Task 5: End-to-end manual verification

**Files:** none (verification only, no code changes)

- [ ] **Step 1: Seed test demand data**

Using the app's existing TP purchase order flow (or direct INSERT
against a dev DB) create one `tp_purchase_orders` row with
`status='waiting'`, `order_date=CURDATE()`, plus a
`tp_purchase_order_items` row for a real product with a known qty.
Using the OT sale form with "Save Draft", create one draft OT order
(`ot_sales_invoice.status='draft'`) for the LLP godown, dated today, for
the same product.

- [ ] **Step 2: Confirm Neksomo and Healthcare have enough stock**

Check `stock.closing_qty` for that product at both the Neksomo and
Healthcare `company_godown` ids is comfortably above the combined
required qty from Step 1 (top up via existing Add Input Stock flow if
not).

- [ ] **Step 3: Load the popup**

Visit `internal_transfer_auto.php` while logged in as a company user
with `internal_transfer` permission. Confirm the seeded product appears
with the correct summed Required Qty (TP qty + OT qty) and a pre-filled
Qty to Transfer equal to that sum (since stock is sufficient per Step
2).

- [ ] **Step 4: Submit and verify both legs**

Click "Transfer Now". Confirm redirect to `internal_transfer_manage.php`
shows a success message naming two tempids. Query
`internal_transfer WHERE tempid IN (<tempid1>, <tempid2>)` and confirm
two rows exist for the product: one `send_from=Neksomo, send_to=Healthcare`,
one `send_from=Healthcare, send_to=LLP`, both with the expected qty.
Query `stock_ledger` for the same product/tempids and confirm
`transfer_out`/`transfer_in` entries exist for all three godowns in the
correct before/after sequence. Confirm LLP's `stock.closing_qty` for the
product increased by the transferred qty.

- [ ] **Step 5: Verify the capping path**

Repeat Steps 1-4 with a required qty deliberately larger than Neksomo +
Healthcare's combined available stock. Confirm the popup's pre-filled
qty is capped, and if submitted un-adjusted, confirm the actual
transferred qty in `internal_transfer` matches the capped amount (not
the original requirement) and the success message lists it under
"Capped:".

- [ ] **Step 6: Report results**

No commit for this task — it's verification only. Note any
discrepancies found; if the manual walkthrough surfaces a bug, fix it
as a small follow-up task before considering the plan complete.

---

## Self-Review Notes

- **Spec coverage:** demand aggregation (Task 1), godown resolution
  (Task 1), popup UI with editable capped quantities (Task 2), two-leg
  atomic transfer via StockService (Task 3), menu entry (Task 4), manual
  end-to-end + capping verification (Task 5) — all spec sections have a
  corresponding task.
- **Placeholder scan:** no TBD/TODO; all code blocks are complete,
  runnable PHP.
- **Type consistency:** `resolve_godown_id_by_gname` / 
  `get_auto_transfer_requirements` / `cap_auto_transfer_qty` signatures
  in Task 1 match their exact usage in Tasks 2 and 3. `StockService`
  method calls match the real signatures read from
  `StockService.php:482,680,742` (positional args, `$externalTransaction=true`,
  `$lotRate` from `consumed_rate`).
