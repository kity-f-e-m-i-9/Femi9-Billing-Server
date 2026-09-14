# CP Purchase Order System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let Channel Partners (CP) submit purchase orders for stock the same
way Territory Partners (TP) do, but capped by a live inventory-value formula
derived from their security deposit (no advance wallet, no courier payment),
with Company approval reusing the existing Godown⇄Location transfer
machinery to move real stock.

**Architecture:** Two new tables
(`channel_partner_purchase_orders`/`channel_partner_purchase_order_items`)
mirror `tp_purchase_orders`/`tp_purchase_order_items` minus the
advance/courier/SS-approver columns. A new shared helper
(`shared/CpPurchaseOrderBalance.php`) computes the live headroom formula and
is called from both the CP cart page (courtesy check) and the submit/approve
actions (authoritative check, inside the write transaction). Company
approval reuses `pl_godown_transfers` as the actual stock-movement record
(new `source_po_id` link column) and the exact lock/debit/credit helper
pattern already in `company/pl-godown-transfer-action.php` and
`company/tp-invoice-action.php`.

**Tech Stack:** PHP 8 + mysqli (procedural + light OOP, no framework),
Bootstrap 4/5 + vanilla JS/jQuery for pages, MySQL/MariaDB.

**Spec:** `docs/superpowers/specs/2026-09-12-cp-purchase-order-design.md`

## Global Constraints

- Balance formula (from spec): `available_headroom = max(0, (total_deposit + 5000) - held_stock_value - pending_po_value)`, where `total_deposit` = sum of `partner_location_nodes.deposit_amount` across the CP's assigned locations (reuse `getCpTotalDeposit()` logic from `company/cp-wallet-commission-calculator.php`), `held_stock_value` = Σ `channel_partner_stock.closing_qty × products.mrp` for that CP, `pending_po_value` = Σ (qty × price) over that CP's own `channel_partner_purchase_orders` rows with `status='waiting'`.
- Cart lines are priced at `products.mrp`, not `stockist_price`.
- No advance-payment step, no courier-payment step, no Super-Stockist alternate-approver routing for CP — Company is the only approver.
- CP purchase orders are split by product type (`napkin`/`diaper`) exactly like TP's, reusing `shared/TpProductType.php` functions as-is (`tpResolveProductType`, `tpProductTypeSqlFilter`, `tpProductTypeOfProducts`, `tpProductTypeLabel`, `tpProductTypeBadgeColors`) — do not fork or rename them.
- Every stock mutation (godown debit, CP credit) happens inside one DB transaction with `SELECT ... FOR UPDATE` row locks, re-validating quantities/headroom **inside** the lock — never trust a pre-transaction read as the final gate. Mirror the lock/debit/credit helper functions already in `company/pl-godown-transfer-action.php` (`lockAndGetQty`, `getCpQty`, `creditCp`, `debitCp`, `insertCpLedger`, `debitGodown`, `creditGodown`, `insertStockLedger`) rather than reinventing them.
- Company approval creates one real `pl_godown_transfers` row (`transfer_type='godown_to_location'`) + its `pl_godown_transfer_items`, with a new `source_po_id` column linking back to the CP PO — this is the only stock-movement record; do not also build a parallel "CP invoice" table.
- All new PHP files follow this codebase's existing self-migrating-schema pattern (`SHOW COLUMNS` / `CREATE TABLE IF NOT EXISTS` guards at the top of the entry file that first needs the column/table), matching `tpEnsureAdvanceWalletColumns()`'s style.
- Company-side pages are gated with `requirePermission('channel_partner')` (from `company/include/PermissionCheck.php`), matching how `company/add-godown-to-location.php` and CP-related pages are already gated.
- CP-side pages use CP's existing `checksession.php` + `config.php` auth (`$Login_user_IDvl` = `channel_partners.id`, `$Login_user_TYPEvl = 'channel_partner'`), same as every other `channel-partner/*.php` file.

---

## File Structure

**New shared helper:**
- `shared/CpPurchaseOrderBalance.php` — schema guards for the two new tables + the `source_po_id` column on `pl_godown_transfers`, plus the headroom-calculation functions used by every other file in this plan.

**New CP-side pages** (`channel-partner/`):
- `add-purchase-order.php` — type chooser + cart builder + submit form.
- `purchase-order-action.php` — authoritative POST handler for cart submission.
- `manage-purchase-orders.php` — CP's own order list/status.
- `delete-purchase-order.php` — cancel a still-`waiting` PO.

**New company-side pages** (`company/`):
- `cp-today-orders.php` — approval queue (list + view items + reject action inline).
- `cp-po-action.php` — POST handler: approve (creates transfer + moves stock) or reject.

**Modified existing files:**
- `channel-partner/femi_menu.php` — add "Purchase Order" / "My Purchase Orders" nav links (mirrors `territory-partner/femi_menu.php:32-33`).
- `company/femi_menu.php` — add a "CP Purchase Orders" nav link.
- `company/manage-pl-godown-transfers.php` — show a badge for transfers where `source_po_id IS NOT NULL` (small addition, existing file).

---

## Task 1: Shared schema + balance-calculation helper

**Files:**
- Create: `shared/CpPurchaseOrderBalance.php`
- Test: `scratch/test_cp_balance.php` (throwaway manual test script, not part of the app — verifies the helper against a real DB connection; delete after Task 1's manual verification, per Step 5 below)

**Interfaces:**
- Consumes: nothing from other tasks (this is the foundation).
- Produces (used by Tasks 2, 3, 4, 5):
  - `cpEnsurePurchaseOrderTables(mysqli $db): void` — creates `channel_partner_purchase_orders`, `channel_partner_purchase_order_items` if missing, and adds `pl_godown_transfers.source_po_id` if missing.
  - `cpTotalDeposit(mysqli $db, int $cpId): float` — sum of `partner_location_nodes.deposit_amount` across this CP's assigned locations.
  - `cpHeldStockValue(mysqli $db, int $cpId): float` — Σ `channel_partner_stock.closing_qty × products.mrp` for this CP.
  - `cpPendingPoValue(mysqli $db, int $cpId, ?int $excludePoId = null): float` — Σ item amounts over this CP's own `waiting` POs, optionally excluding one PO id (used when re-validating a PO that already exists, e.g. at approval time).
  - `cpAvailableHeadroom(mysqli $db, int $cpId, ?int $excludePoId = null): float` — `max(0, (cpTotalDeposit + 5000) - cpHeldStockValue - cpPendingPoValue)`.

- [ ] **Step 1: Write the schema-guard and calculation functions**

```php
<?php
/**
 * CpPurchaseOrderBalance — schema guards and live headroom calculation for
 * the Channel Partner purchase-order flow.
 *
 * Unlike Territory Partners (which fund orders from a pre-paid advance
 * wallet, see shared/TpProductType.php + TpAdvanceService), a Channel
 * Partner has no wallet at all. Their ordering capacity is a live
 * inventory-value cap: at any moment they may hold stock (valued at MRP)
 * worth up to (their total security deposit + Rs.5000) across their
 * assigned locations. As held stock sells out — channel_partner_stock is
 * debited elsewhere whenever Company invoices a TP from this CP's stock,
 * see company/tp-invoice-action.php — headroom reopens automatically.
 * There is no stored balance to decrement/increment; every caller
 * recomputes this fresh from live tables, same spirit as
 * tp_advance_payments' "balance minus reserved-by-waiting-orders" pattern
 * but with deposit+held-stock standing in for the wallet.
 */

/** Self-migrating: create the two CP purchase-order tables and the
 * pl_godown_transfers.source_po_id link column if they don't exist yet.
 * Called from every entry file that touches any of them, same pattern as
 * tpEnsureAdvanceWalletColumns() in shared/TpProductType.php. */
function cpEnsurePurchaseOrderTables(mysqli $db): void
{
    $poTable = $db->query("SHOW TABLES LIKE 'channel_partner_purchase_orders'");
    if ($poTable && $poTable->num_rows === 0) {
        $db->query("
            CREATE TABLE channel_partner_purchase_orders (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                channel_partner_id INT UNSIGNED NOT NULL,
                product_type ENUM('napkin','diaper') NOT NULL DEFAULT 'napkin',
                order_date DATE NOT NULL,
                status ENUM('waiting','completed','cancelled') NOT NULL DEFAULT 'waiting',
                transfer_id INT UNSIGNED NULL,
                cancel_reason VARCHAR(255) NULL,
                cancelled_at DATETIME NULL,
                cancelled_by VARCHAR(100) NULL,
                use_default_delivery_address TINYINT(1) NOT NULL DEFAULT 1,
                custom_delivery_line1 VARCHAR(255) NULL,
                custom_delivery_line2 VARCHAR(255) NULL,
                custom_delivery_city VARCHAR(100) NULL,
                custom_delivery_district VARCHAR(100) NULL,
                custom_delivery_state VARCHAR(100) NULL,
                custom_delivery_country VARCHAR(100) NULL,
                custom_delivery_pincode VARCHAR(20) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cppo_cp_status (channel_partner_id, status),
                KEY idx_cppo_transfer (transfer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    $itemsTable = $db->query("SHOW TABLES LIKE 'channel_partner_purchase_order_items'");
    if ($itemsTable && $itemsTable->num_rows === 0) {
        $db->query("
            CREATE TABLE channel_partner_purchase_order_items (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                po_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                qty INT UNSIGNED NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                KEY idx_cppoi_po (po_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    $col = $db->query("SHOW COLUMNS FROM pl_godown_transfers LIKE 'source_po_id'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE pl_godown_transfers ADD COLUMN source_po_id INT UNSIGNED NULL AFTER cp_id");
        $db->query("ALTER TABLE pl_godown_transfers ADD KEY idx_plgt_source_po (source_po_id)");
    }
}

/** Sum of security deposit across every location assigned to this CP.
 * Mirrors company/cp-wallet-commission-calculator.php::getCpTotalDeposit()
 * exactly (same query) — that function takes a string cp_id (the
 * 'CP-0007'-style code); this one takes the numeric channel_partners.id,
 * since that's what every CP-session page already has as $Login_user_IDvl. */
function cpTotalDeposit(mysqli $db, int $cpId): float
{
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(n.deposit_amount), 0) AS total
         FROM channel_partner_locations cpl
         JOIN partner_location_nodes n ON n.id = cpl.location_id
         WHERE cpl.channel_partner_id = ?"
    );
    $stmt->bind_param("i", $cpId);
    $stmt->execute();
    $total = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

/** Value, at MRP, of everything this CP currently holds in stock. */
function cpHeldStockValue(mysqli $db, int $cpId): float
{
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(cps.closing_qty * p.mrp), 0) AS total
         FROM channel_partner_stock cps
         JOIN products p ON p.id = cps.product_id
         WHERE cps.channel_partner_id = ?"
    );
    $stmt->bind_param("i", $cpId);
    $stmt->execute();
    $total = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

/** Value of this CP's own still-waiting purchase-order carts — these
 * haven't landed in channel_partner_stock yet, but they've earmarked
 * headroom, same reasoning tpApoBalanceFor()'s "reserved" subtraction
 * uses for TP's advance wallet (see territory-partner/add-purchase-order.php).
 * $excludePoId lets a caller re-validate an existing PO's own headroom
 * without double-counting that PO's own value against itself. */
function cpPendingPoValue(mysqli $db, int $cpId, ?int $excludePoId = null): float
{
    $sql = "SELECT COALESCE(SUM(i.amount), 0) AS total
            FROM channel_partner_purchase_orders o
            JOIN channel_partner_purchase_order_items i ON i.po_id = o.id
            WHERE o.channel_partner_id = ? AND o.status = 'waiting'";
    if ($excludePoId !== null) $sql .= " AND o.id != ?";

    $stmt = $db->prepare($sql);
    if ($excludePoId !== null) {
        $stmt->bind_param("ii", $cpId, $excludePoId);
    } else {
        $stmt->bind_param("i", $cpId);
    }
    $stmt->execute();
    $total = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

/** The live headroom a new (or re-validated) cart must fit inside. Always
 * recomputed from current tables — never stored — so it self-corrects the
 * moment held stock sells out or the deposit changes. */
function cpAvailableHeadroom(mysqli $db, int $cpId, ?int $excludePoId = null): float
{
    $cap = cpTotalDeposit($db, $cpId) + 5000;
    $used = cpHeldStockValue($db, $cpId) + cpPendingPoValue($db, $cpId, $excludePoId);
    return max(0.0, round($cap - $used, 2));
}
```

- [ ] **Step 2: Manual verification against a real DB connection**

Create a throwaway script (not committed) to sanity-check the functions
against your local database, since this codebase has no PHPUnit harness —
every existing test in this repo is this same manual-script pattern (see
`company/_verify_fix.php`-style files referenced in project history).

```php
<?php
// scratch/test_cp_balance.php — throwaway, delete after verifying
require_once __DIR__ . '/../femi9/billing/channel-partner/include/db-connect.php';
require_once __DIR__ . '/../femi9/billing/shared/CpPurchaseOrderBalance.php';

cpEnsurePurchaseOrderTables($db_conn);

$check = $db_conn->query("SHOW TABLES LIKE 'channel_partner_purchase_orders'");
echo $check->num_rows === 1 ? "PASS: table exists\n" : "FAIL: table missing\n";

$col = $db_conn->query("SHOW COLUMNS FROM pl_godown_transfers LIKE 'source_po_id'");
echo $col->num_rows === 1 ? "PASS: source_po_id column exists\n" : "FAIL: column missing\n";

// Pick any real CP id from your DB to sanity check the numbers are non-negative and finite.
$cpRow = $db_conn->query("SELECT id FROM channel_partners LIMIT 1")->fetch_assoc();
if ($cpRow) {
    $cpId = (int)$cpRow['id'];
    printf("deposit=%.2f held=%.2f pending=%.2f headroom=%.2f\n",
        cpTotalDeposit($db_conn, $cpId),
        cpHeldStockValue($db_conn, $cpId),
        cpPendingPoValue($db_conn, $cpId),
        cpAvailableHeadroom($db_conn, $cpId)
    );
}
```

Run: `php scratch/test_cp_balance.php`
Expected: two `PASS` lines and one printed line of four non-negative
numbers with `headroom = max(0, deposit + 5000 - held - pending)` holding
arithmetically.

- [ ] **Step 3: Delete the throwaway script**

```bash
rm scratch/test_cp_balance.php
```

- [ ] **Step 4: Commit**

```bash
git add shared/CpPurchaseOrderBalance.php
git commit -m "Add CP purchase-order schema guards and headroom calculation helper

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: CP cart-builder page (`add-purchase-order.php`)

**Files:**
- Create: `femi9/billing/channel-partner/add-purchase-order.php`

**Interfaces:**
- Consumes: `cpEnsurePurchaseOrderTables`, `cpAvailableHeadroom` (Task 1); `tpResolveProductType`, `tpProductTypeSqlFilter`, `tpProductTypeLabel`, `tpProductTypeBadgeColors` (existing `shared/TpProductType.php`, `require_once`d directly — do not copy).
- Produces: a form posting to `purchase-order-action.php` (Task 3) with fields: `product_type` (hidden), `pr_id[]`, `qty[]`, `price[]` (MRP, read-only), `use_default_delivery_address`, `custom_delivery_line1/2/city/district/state/country/pincode`.

- [ ] **Step 1: Write the page**

Base this directly on `femi9/billing/territory-partner/add-purchase-order.php`,
with these concrete changes:

1. Same `?type=napkin|diaper` chooser screen at the top (copy verbatim,
   just change the two `<a href>` targets to stay on this file — they
   already are relative, no change needed — and drop the
   `tpEnsureAdvanceWalletColumns` / `tpEnsureCourierPaymentTables` calls,
   replacing them with `cpEnsurePurchaseOrderTables($db_conn);`).
2. Remove entirely: the Super-Stockist approver radio block, the advance
   balance card driven by `tp_advance_payments`, the courier-payment card,
   the pickup-method modal/checkbox, the excess/advance-payment warning
   card, `stash-po-draft.php`/`add-advance-payment.php`/`pay-courier-payment.php`
   redirects.
3. Replace the balance card with:
   ```php
   $headroom = cpAvailableHeadroom($db_conn, (int)$Login_user_IDvl);
   ```
   rendered the same visual way TP's `.apo-balance-card` is, but labeled
   "Available Order Headroom" with a one-line explainer: "Based on your
   security deposit and current stock on hand."
4. Product picker query selects from the full active catalog scoped by
   type, priced at MRP instead of `stockist_price`:
   ```php
   $productList = [];
   $resProd = mysqli_query($db_conn, "SELECT id, productName, mrp FROM products WHERE deleted_at IS NULL AND (temp_id NOT LIKE 'NKS-%' OR temp_id IS NULL) AND " . tpProductTypeSqlFilter($productType, 'products') . " ORDER BY productName ASC");
   if ($resProd) while ($p = mysqli_fetch_assoc($resProd)) $productList[] = $p;
   ```
   and the `<option data-price="...">` attribute reads `$p['mrp']` instead
   of `$p['stockist_price']`.
5. Delivery-address block: same "use existing / custom" toggle, but sourced
   from `channel_partners` instead of `territory_partners`:
   ```php
   $cpDeliveryStmt = mysqli_prepare($db_conn,
       "SELECT delivery_line1, delivery_line2, delivery_city, delivery_district, delivery_state, delivery_country, delivery_pincode
        FROM channel_partners WHERE id = ?"
   );
   mysqli_stmt_bind_param($cpDeliveryStmt, "i", $Login_user_IDvl);
   mysqli_stmt_execute($cpDeliveryStmt);
   $cpDeliveryAddress = mysqli_stmt_get_result($cpDeliveryStmt)->fetch_assoc() ?: [];
   mysqli_stmt_close($cpDeliveryStmt);
   ```
   with the same `array_filter`/`implode` assembly TP's page uses for
   `$tpDeliveryAddressParts`, renamed `$cpDeliveryAddressParts`.
6. JS: strip `advBalanceByApprover`/`eligibleSubmissionByApprover`/
   `onApproverChange`/pickup-related functions entirely. Keep
   `addPoLine`/`removePoLine`/`renderPoLines`/`poGrandTotal` as-is (price
   comes from the MRP now, logic unchanged). Replace `updatePoSummary()`'s
   advance-balance math with headroom math:
   ```js
   var headroom = <?=json_encode($headroom)?>;
   function updatePoSummary() {
       var total = poGrandTotal();
       document.getElementById('poGrandTotal').textContent = '₹' + total.toFixed(2);
       var warning = document.getElementById('poExcessWarning');
       var excess = total - headroom;
       if (excess > 0.001) {
           document.getElementById('poExcessAmount').textContent = excess.toFixed(2);
           warning.style.display = '';
       } else {
           warning.style.display = 'none';
       }
   }
   ```
   The excess card (`.apo-excess-card`) keeps only its title/description
   text, changed to: "Your order total exceeds your available headroom by
   ₹<span id="poExcessAmount">0.00</span>. Remove items or wait for your
   held stock to sell before ordering more." — with the
   "Submit Advance Payment" button and its `goToAdvancePayment()` handler
   removed entirely (there's nothing to redirect to).
7. `validatePoLines()`: drop the courier-payment and advance-submission
   checks; keep the "add at least one product" and delivery-address checks;
   replace the excess check with:
   ```js
   var total = poGrandTotal();
   if (total - headroom > 0.001) {
       alert('Your order total exceeds your available headroom by ₹' + (total - headroom).toFixed(2) + '. Remove items or wait for stock to sell before ordering more.');
       return false;
   }
   ```
8. Form submit button text: "Submit Purchase Order" (unchanged), posting
   to `purchase-order-action.php`.
9. Keep the "My Purchase Orders" back-link, pointed at
   `manage-purchase-orders.php` (Task 4).

- [ ] **Step 2: Manual smoke test**

Log in as a channel_partner test account, visit
`channel-partner/add-purchase-order.php?type=napkin`. Confirm: the type
chooser shows when no `type` param is given; the headroom banner shows a
number; adding a product populates the table at MRP price; the excess
warning appears once cart total exceeds the shown headroom and blocks
submit with the alert; delivery-address toggle works.

- [ ] **Step 3: Commit**

```bash
git add "femi9/billing/channel-partner/add-purchase-order.php"
git commit -m "Add CP purchase order cart-builder page

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: CP cart submission handler (`purchase-order-action.php`)

**Files:**
- Create: `femi9/billing/channel-partner/purchase-order-action.php`

**Interfaces:**
- Consumes: `cpEnsurePurchaseOrderTables`, `cpAvailableHeadroom` (Task 1); `tpResolveProductType`, `tpProductTypeOfProducts`, `tpProductTypeLabel` (existing).
- Produces: inserts a `channel_partner_purchase_orders` row (`status='waiting'`) + its items; redirects to `manage-purchase-orders.php` (Task 4) on success, back to `add-purchase-order.php` with `$_SESSION['errorMessage']` on failure — same redirect-with-session-message convention as `territory-partner/purchase-order-action.php`.

- [ ] **Step 1: Write the handler**

```php
<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/CpPurchaseOrderBalance.php';
error_reporting(0);

if (!isset($_POST['submit_po'])) {
    header("Location: add-purchase-order.php");
    exit;
}

$cp_id = (int)$Login_user_IDvl;
$order_date = date("Y-m-d");
$productType = tpResolveProductType($_POST['product_type'] ?? null);

cpEnsurePurchaseOrderTables($db_conn);

$pr_ids = $_POST['pr_id'] ?? [];
$qtys   = $_POST['qty']   ?? [];

// Price is never trusted from the client — re-read each product's current
// MRP here, same principle as territory-partner/purchase-order-action.php
// re-validating everything server-side rather than trusting the cart total
// the browser computed.
$items = [];
foreach ($pr_ids as $i => $rpid) {
    $pid = (int)$rpid;
    $qty = (int)($qtys[$i] ?? 0);
    if ($pid < 1 || $qty < 1) continue;

    $pStmt = $db_conn->prepare("SELECT mrp FROM products WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $pStmt->bind_param("i", $pid);
    $pStmt->execute();
    $pRow = $pStmt->get_result()->fetch_assoc();
    $pStmt->close();
    if (!$pRow) continue;

    $price = round((float)$pRow['mrp'], 2);
    $amount = round($qty * $price, 2);
    $items[] = ['pid' => $pid, 'qty' => $qty, 'price' => $price, 'amount' => $amount];
}

if (empty($items)) {
    $_SESSION['errorMessage'] = 'Please add at least one product before submitting.';
    header("Location: add-purchase-order.php");
    exit;
}

// Product-type re-classification — the picker on add-purchase-order.php
// only offers the declared type's products, but that's UX only; a raw POST
// could bypass it.
$cartClassification = tpProductTypeOfProducts($db_conn, array_column($items, 'pid'));
if ($cartClassification['mixed'] || ($cartClassification['type'] !== null && $cartClassification['type'] !== $productType)) {
    $_SESSION['errorMessage'] = 'This order contains a product that doesn\'t match its declared type (' . tpProductTypeLabel($productType) . '). Please start the order again.';
    header("Location: add-purchase-order.php");
    exit;
}

// Delivery address
$useDefaultDelivery = isset($_POST['use_default_delivery_address']) ? 1 : 0;
$customDeliveryLine1    = trim($_POST['custom_delivery_line1']    ?? '');
$customDeliveryLine2    = trim($_POST['custom_delivery_line2']    ?? '');
$customDeliveryCity     = trim($_POST['custom_delivery_city']     ?? '');
$customDeliveryDistrict = trim($_POST['custom_delivery_district'] ?? '');
$customDeliveryState    = trim($_POST['custom_delivery_state']    ?? '');
$customDeliveryCountry  = trim($_POST['custom_delivery_country']  ?? '');
$customDeliveryPincode  = trim($_POST['custom_delivery_pincode']  ?? '');

if (!$useDefaultDelivery && $customDeliveryLine1 === '') {
    $_SESSION['errorMessage'] = 'Please enter a delivery address, or use the existing delivery address.';
    header("Location: add-purchase-order.php");
    exit;
}
if ($useDefaultDelivery) {
    $customDeliveryLine1 = $customDeliveryLine2 = $customDeliveryCity = $customDeliveryDistrict = $customDeliveryState = $customDeliveryCountry = $customDeliveryPincode = null;
}

$grandTotal = array_sum(array_column($items, 'amount'));

// Authoritative headroom check — never trust the client's displayed total.
$headroom = cpAvailableHeadroom($db_conn, $cp_id);
if ($grandTotal > $headroom + 0.001) {
    $_SESSION['errorMessage'] = 'Your order total (₹' . number_format($grandTotal, 2) . ') exceeds your available headroom (₹' . number_format($headroom, 2) . '). Remove items or wait for your held stock to sell before ordering more.';
    header("Location: add-purchase-order.php");
    exit;
}

$db_conn->begin_transaction();
try {
    $s = $db_conn->prepare(
        "INSERT INTO channel_partner_purchase_orders
            (channel_partner_id, product_type, order_date, status, use_default_delivery_address,
             custom_delivery_line1, custom_delivery_line2, custom_delivery_city, custom_delivery_district,
             custom_delivery_state, custom_delivery_country, custom_delivery_pincode)
         VALUES (?, ?, ?, 'waiting', ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $s->bind_param(
        "ississsssss", $cp_id, $productType, $order_date, $useDefaultDelivery,
        $customDeliveryLine1, $customDeliveryLine2, $customDeliveryCity, $customDeliveryDistrict,
        $customDeliveryState, $customDeliveryCountry, $customDeliveryPincode
    );
    $s->execute();
    $po_id = $db_conn->insert_id;
    $s->close();

    $si = $db_conn->prepare("INSERT INTO channel_partner_purchase_order_items (po_id, product_id, qty, price, amount) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $si->bind_param("iiidd", $po_id, $item['pid'], $item['qty'], $item['price'], $item['amount']);
        $si->execute();
    }
    $si->close();

    $db_conn->commit();

    $_SESSION['successMessage'] = 'Purchase order submitted successfully.';
    header("Location: manage-purchase-orders.php");
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = 'Failed to submit purchase order. Please try again.';
    header("Location: add-purchase-order.php");
    exit;
}
```

- [ ] **Step 2: Manual smoke test — happy path**

As a CP test account with known headroom > 0, submit a cart from
`add-purchase-order.php` whose total is under headroom. Confirm redirect to
`manage-purchase-orders.php` with the success message, and a new row exists:

```bash
php -r '
require "femi9/billing/channel-partner/include/db-connect.php";
require "femi9/billing/shared/CpPurchaseOrderBalance.php";
$r = $db_conn->query("SELECT * FROM channel_partner_purchase_orders ORDER BY id DESC LIMIT 1")->fetch_assoc();
var_dump($r);
'
```
Expected: the row exists with `status='waiting'` and the correct
`channel_partner_id`/`product_type`.

- [ ] **Step 3: Manual smoke test — over-headroom rejection**

Repeat with a cart total intentionally larger than current headroom (or
temporarily submit a very large qty). Confirm redirect back to
`add-purchase-order.php` with the over-headroom `$_SESSION['errorMessage']`,
and **no** new row was inserted.

- [ ] **Step 4: Commit**

```bash
git add "femi9/billing/channel-partner/purchase-order-action.php"
git commit -m "Add CP purchase order submission handler with authoritative headroom check

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: CP order list + cancel (`manage-purchase-orders.php`, `delete-purchase-order.php`)

**Files:**
- Create: `femi9/billing/channel-partner/manage-purchase-orders.php`
- Create: `femi9/billing/channel-partner/delete-purchase-order.php`

**Interfaces:**
- Consumes: `cpEnsurePurchaseOrderTables` (Task 1); `tpProductTypeLabel`, `tpProductTypeBadgeColors` (existing).
- Produces: nothing consumed by later tasks — this is a leaf UI.

- [ ] **Step 1: Write `manage-purchase-orders.php`**

Base on `territory-partner/manage-purchase-orders.php`, simplified: drop the
"direct invoices with no PO" section (part 3 in the TP version) and the
courier-retry section entirely — a CP PO's own items and status are the
only display fields needed (no separate "bill copy" concept since Company's
approval doesn't produce a printable CP invoice, only a stock transfer).

```php
<?php
include("checksession.php");
include("config.php");
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/CpPurchaseOrderBalance.php';
error_reporting(0);

date_default_timezone_set("Asia/Kolkata");
$today     = date("Y-m-d");
$from_date = $_REQUEST['frdate'] ?? date("Y-m-d", strtotime("-6 days"));
$to_date   = $_REQUEST['todate'] ?? $today;

$statusFilter = $_REQUEST['status_filter'] ?? 'all';
$allowedStatusFilters = ['all', 'waiting', 'completed', 'cancelled'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) $statusFilter = 'all';

cpEnsurePurchaseOrderTables($db_conn);

$stmt = mysqli_prepare($db_conn,
    "SELECT o.id, o.order_date, o.status, o.transfer_id, o.cancel_reason, o.product_type,
            i.product_id, i.qty, i.price, i.amount, p.productName
     FROM channel_partner_purchase_orders o
     LEFT JOIN channel_partner_purchase_order_items i ON i.po_id = o.id
     LEFT JOIN products p ON p.id = i.product_id
     WHERE o.channel_partner_id=? AND o.order_date BETWEEN ? AND ?"
    . ($statusFilter !== 'all' ? " AND o.status = ?" : '')
    . "\n     ORDER BY o.order_date DESC, o.id DESC, i.id ASC"
);
if ($statusFilter !== 'all') {
    mysqli_stmt_bind_param($stmt, "isss", $Login_user_IDvl, $from_date, $to_date, $statusFilter);
} else {
    mysqli_stmt_bind_param($stmt, "iss", $Login_user_IDvl, $from_date, $to_date);
}
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$orders = [];
foreach ($rows as $r) {
    $key = 'po_' . $r['id'];
    if (!isset($orders[$key])) {
        $orders[$key] = [
            'po_id'         => (int)$r['id'],
            'display_date'  => $r['order_date'],
            'status'        => $r['status'],
            'transfer_id'   => $r['transfer_id'],
            'cancel_reason' => $r['cancel_reason'],
            'product_type'  => $r['product_type'] ?? 'napkin',
            'lines'         => [],
            'total'         => 0,
        ];
    }
    if ($r['product_id']) {
        $orders[$key]['lines'][] = ['product' => $r['productName'], 'qty' => (int)$r['qty'], 'price' => (float)$r['price'], 'amount' => (float)$r['amount']];
        $orders[$key]['total'] += (float)$r['amount'];
    }
}
uasort($orders, fn($a, $b) => strtotime($b['display_date']) <=> strtotime($a['display_date']) ?: $b['po_id'] <=> $a['po_id']);

$totalOrders    = count($orders);
$totalAmount    = array_sum(array_column($orders, 'total'));
$waitingCount   = count(array_filter($orders, fn($o) => $o['status'] === 'waiting'));
$completedCount = count(array_filter($orders, fn($o) => $o['status'] === 'completed'));
$cancelledCount = count(array_filter($orders, fn($o) => $o['status'] === 'cancelled'));
$headroom       = cpAvailableHeadroom($db_conn, (int)$Login_user_IDvl);
?>
```

Follow this with the same page chrome (head/sidebar/header includes), stat
cards (Total Orders / Total Amount / Waiting / Completed — same 4-card row
as TP's), an added 5th "Available Headroom" chip showing `$headroom`, the
same date+status filter form (status options: All/Waiting/Completed/
Cancelled — no "Courier Payment Rejected" option), and a table with columns
Date / Approver ("Company" — CP always) / Type / Products / Total / Status
/ Actions. Actions column: a "View" button reusing the same
`items-view-trigger` modal JS pattern as TP's page (items only, no bill
info since there's no CP invoice concept — pass `data-bill=""`/omit it and
adjust the modal JS to skip the bill block when absent, mirroring the
`if (bill) { ... }` branch already in TP's page), plus a Cancel form (only
when `status === 'waiting'`) posting to `delete-purchase-order.php`:

```php
<?php if ($o['status'] === 'waiting'): ?>
<form method="post" action="delete-purchase-order.php" onsubmit="return confirm('Delete this purchase order? This cannot be undone.');">
    <input type="hidden" name="po_id" value="<?=(int)$o['po_id']?>">
    <button type="submit" class="po-delete-btn"><i class="material-icons" style="font-size:14px;">delete</i> Delete</button>
</form>
<?php endif; ?>
```

Reuse the exact CSS classes (`.po-stat-card`, `.po-table`, `.po-status-pill`,
etc.) copied verbatim from `territory-partner/manage-purchase-orders.php`'s
`<style>` block so the page looks consistent with the rest of the app.

- [ ] **Step 2: Write `delete-purchase-order.php`**

```php
<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$po_id = (int)($_POST['po_id'] ?? 0);
if ($po_id < 1) {
    header("Location: manage-purchase-orders.php");
    exit;
}

// Only a still-waiting order can be deleted — nothing has moved yet, so
// there's no stock to reverse. A completed order has a real
// pl_godown_transfers row behind it and must never be deleted here.
$s = $db_conn->prepare("DELETE FROM channel_partner_purchase_orders WHERE id = ? AND channel_partner_id = ? AND status = 'waiting'");
$s->bind_param("ii", $po_id, $Login_user_IDvl);
$s->execute();
$deleted = $s->affected_rows > 0;
$s->close();

if ($deleted) {
    // Items cascade with the order — no FK, so clean up explicitly.
    $si = $db_conn->prepare("DELETE FROM channel_partner_purchase_order_items WHERE po_id = ?");
    $si->bind_param("i", $po_id);
    $si->execute();
    $si->close();
    $_SESSION['successMessage'] = 'Purchase order deleted.';
} else {
    $_SESSION['errorMessage'] = 'That purchase order could not be deleted (it may already be completed or cancelled).';
}

header("Location: manage-purchase-orders.php");
exit;
```

- [ ] **Step 3: Manual smoke test**

Visit `manage-purchase-orders.php` as the CP account used in Task 3;
confirm the waiting PO from Task 3's happy-path test appears with correct
totals, the headroom chip shows a number, and clicking Delete on it removes
it from the list (confirm via the same `SELECT * FROM
channel_partner_purchase_orders` query as Task 3 Step 2 — the row should
now be gone).

- [ ] **Step 4: Commit**

```bash
git add "femi9/billing/channel-partner/manage-purchase-orders.php" "femi9/billing/channel-partner/delete-purchase-order.php"
git commit -m "Add CP purchase order list and cancel pages

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 5: CP navigation links

**Files:**
- Modify: `femi9/billing/channel-partner/femi_menu.php`

**Interfaces:**
- Consumes: nothing (pure markup).
- Produces: nothing consumed elsewhere.

- [ ] **Step 1: Add the nav entry**

Insert a new `<li>` right after the existing "Sales / Invoices" entry
(matches where TP's own equivalent nav sits relative to its other items):

```php
        <li>
            <a href="#"><i class="material-icons-two-tone">shopping_cart</i>Purchase Order<i class="material-icons has-sub-menu">keyboard_arrow_right</i></a>
            <ul class="sub-menu">
                <li><a href="add-purchase-order.php">New Purchase Order</a></li>
                <li><a href="manage-purchase-orders.php">My Purchase Orders</a></li>
            </ul>
        </li>
```

placed between the existing `manage-tp-invoices.php` `<li>` block and the
`my-territory-partners.php` `<li>` block.

- [ ] **Step 2: Manual smoke test**

Load any `channel-partner/*.php` page as a logged-in CP; confirm the new
"Purchase Order" menu entry appears with its two sub-links, and both
navigate correctly.

- [ ] **Step 3: Commit**

```bash
git add "femi9/billing/channel-partner/femi_menu.php"
git commit -m "Add CP purchase order nav links

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 6: Company approval queue (`cp-today-orders.php`)

**Files:**
- Create: `femi9/billing/company/cp-today-orders.php`

**Interfaces:**
- Consumes: `cpEnsurePurchaseOrderTables`, `cpAvailableHeadroom` (Task 1); `tpProductTypeLabel`, `tpProductTypeBadgeColors` (existing); `godown_finance_filter_sql` (existing, `company/include/GodownAccess.php`).
- Produces: links to `cp-po-action.php` (Task 7) for approve/reject, carrying `po_id` and (for approve) a chosen `godown_id`.

- [ ] **Step 1: Write the page**

Base structure on `company/tp-today-orders.php`'s queue pattern, scoped to
the new tables and simplified (no courier/screenshot sections, no SS
routing):

```php
<?php
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
require_once("include/GodownAccess.php");
require_once __DIR__ . '/../shared/TpProductType.php';
require_once __DIR__ . '/../shared/CpPurchaseOrderBalance.php';
error_reporting(0);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

date_default_timezone_set("Asia/Kolkata");
$today = date("Y-m-d");

$filterSubmitted = isset($_GET['from_date']) || isset($_GET['to_date']) || isset($_GET['status_filter']);
if ($filterSubmitted) {
    $from_date = $_GET['from_date'] ?? $today;
    $to_date   = $_GET['to_date']   ?? $today;
    if (strtotime($from_date) > strtotime($to_date)) { [$from_date, $to_date] = [$to_date, $from_date]; }
    $statusFilter = $_GET['status_filter'] ?? 'active';
    $allowedStatusFilters = ['active', 'waiting', 'completed', 'cancelled', 'all'];
    if (!in_array($statusFilter, $allowedStatusFilters, true)) $statusFilter = 'active';
} else {
    $from_date = ''; $to_date = ''; $statusFilter = 'waiting';
}

$typeFilter = $_GET['type_filter'] ?? '';
if (!in_array($typeFilter, ['napkin', 'diaper'], true)) $typeFilter = '';

cpEnsurePurchaseOrderTables($db_conn);

$whereSql = "WHERE 1=1";
$bindTypes = ''; $bindValues = [];
if ($filterSubmitted) {
    $whereSql .= ' AND o.order_date BETWEEN ? AND ?';
    $bindTypes .= 'ss'; $bindValues[] = $from_date; $bindValues[] = $to_date;
}
if ($statusFilter === 'active') {
    $whereSql .= " AND o.status != 'cancelled'";
} elseif (in_array($statusFilter, ['waiting', 'completed', 'cancelled'], true)) {
    $whereSql .= ' AND o.status = ?'; $bindTypes .= 's'; $bindValues[] = $statusFilter;
}
if ($typeFilter !== '') {
    $whereSql .= ' AND o.product_type = ?'; $bindTypes .= 's'; $bindValues[] = $typeFilter;
}

$stmt = $db_conn->prepare(
    "SELECT o.id, o.order_date, o.status, o.transfer_id, o.cancel_reason, o.product_type, o.channel_partner_id,
            cp.name AS cp_name, cp.cp_id AS cp_code,
            i.product_id, i.qty, i.price, i.amount, p.productName
     FROM channel_partner_purchase_orders o
     JOIN channel_partners cp ON cp.id = o.channel_partner_id
     LEFT JOIN channel_partner_purchase_order_items i ON i.po_id = o.id
     LEFT JOIN products p ON p.id = i.product_id
     $whereSql
     ORDER BY o.order_date DESC, o.id DESC, i.id ASC"
);
if ($bindTypes !== '') $stmt->bind_param($bindTypes, ...$bindValues);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$orders = [];
foreach ($rows as $r) {
    $key = (int)$r['id'];
    if (!isset($orders[$key])) {
        $orders[$key] = [
            'po_id'              => (int)$r['id'],
            'display_date'       => $r['order_date'],
            'status'             => $r['status'],
            'transfer_id'        => $r['transfer_id'],
            'cancel_reason'      => $r['cancel_reason'],
            'product_type'       => $r['product_type'] ?? 'napkin',
            'cp_id'              => (int)$r['channel_partner_id'],
            'cp_name'            => $r['cp_name'],
            'cp_code'            => $r['cp_code'],
            'lines'              => [],
            'total'              => 0,
            'headroom'           => null, // computed lazily below, only for waiting orders
        ];
    }
    if ($r['product_id']) {
        $orders[$key]['lines'][] = ['product' => $r['productName'], 'qty' => (int)$r['qty'], 'price' => (float)$r['price'], 'amount' => (float)$r['amount']];
        $orders[$key]['total'] += (float)$r['amount'];
    }
}

// Current headroom per CP — only needed for orders still awaiting a
// decision, and computed once per distinct CP rather than once per order.
$headroomByCp = [];
foreach ($orders as $key => $o) {
    if ($o['status'] !== 'waiting') continue;
    if (!isset($headroomByCp[$o['cp_id']])) {
        $headroomByCp[$o['cp_id']] = cpAvailableHeadroom($db_conn, $o['cp_id'], $o['po_id']);
    }
    $orders[$key]['headroom'] = $headroomByCp[$o['cp_id']];
}

$godowns = $db_conn->query("SELECT id, gname FROM company_godown WHERE " . godown_finance_filter_sql($db_conn) . " ORDER BY gname")->fetch_all(MYSQLI_ASSOC);

uasort($orders, fn($a, $b) => strtotime($b['display_date']) <=> strtotime($a['display_date']) ?: $b['po_id'] <=> $a['po_id']);
$waitingCount = count(array_filter($orders, fn($o) => $o['status'] === 'waiting'));
?>
```

Then render: page chrome, a stat row (Waiting / Completed / Cancelled
counts), the date/status/type filter form (same shape as
`tp-today-orders.php`'s), and a table with columns Date / CP / Type /
Products (view-items modal trigger) / Total / Headroom (only shown for
waiting rows — display `₹<?=number_format($o['headroom'],2)?>`,
in red if `< $o['total']`, else green) / Status / Actions.

Actions column, only for `status === 'waiting'` rows: a small inline form
with a godown `<select>` (populated from `$godowns`) plus an "Approve"
button posting to `cp-po-action.php` with `action=approve`, `po_id`,
`godown_id`, and the CSRF token; and a "Reject" button opening a small
prompt for a reason (same `prompt()`-based pattern
`cancel-tp-purchase-order.php`'s caller uses) that posts
`action=reject&po_id=...&reason=...`.

```html
<form method="post" action="cp-po-action.php" style="display:inline-flex;gap:6px;align-items:center;">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
    <input type="hidden" name="po_id" value="<?=(int)$o['po_id']?>">
    <input type="hidden" name="action" value="approve">
    <select name="godown_id" class="form-control form-control-sm" required style="width:140px;">
        <option value="">Godown…</option>
        <?php foreach ($godowns as $g): ?>
        <option value="<?=$g['id']?>"><?=htmlspecialchars($g['gname'])?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this order and transfer stock now?');">Approve</button>
</form>
<button type="button" class="btn btn-sm btn-danger" onclick="rejectCpPo(<?=(int)$o['po_id']?>)">Reject</button>
```

with a small script block:

```html
<script>
function rejectCpPo(poId) {
    var reason = prompt('Reason for rejecting this order:');
    if (reason === null) return;
    var f = document.createElement('form');
    f.method = 'post'; f.action = 'cp-po-action.php';
    f.innerHTML = '<input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">' +
        '<input type="hidden" name="po_id" value="' + poId + '">' +
        '<input type="hidden" name="action" value="reject">' +
        '<input type="hidden" name="reason" value="' + reason.replace(/"/g, '&quot;') + '">';
    document.body.appendChild(f);
    f.submit();
}
</script>
```

Include the same items-view-trigger modal pattern as
`tp-today-orders.php`/`manage-purchase-orders.php` for the Products column.

- [ ] **Step 2: Manual smoke test**

Log in as company, visit `company/cp-today-orders.php`. Confirm the waiting
PO from Task 3 appears, its headroom column shows a number, the godown
dropdown is populated, and the type/status/date filters work.

- [ ] **Step 3: Commit**

```bash
git add "femi9/billing/company/cp-today-orders.php"
git commit -m "Add company-side CP purchase order approval queue

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 7: Company approval/rejection handler (`cp-po-action.php`)

**Files:**
- Create: `femi9/billing/company/cp-po-action.php`

**Interfaces:**
- Consumes: `cpEnsurePurchaseOrderTables`, `cpAvailableHeadroom` (Task 1); the lock/debit/credit helper *pattern* from `company/pl-godown-transfer-action.php` (reimplemented here with the same function bodies, since that file's functions aren't currently exposed for reuse — see Step 1's note).
- Produces: on approve, a `pl_godown_transfers` row (`source_po_id` set) + items, updated `channel_partner_stock`/`stock` + their ledgers, and `channel_partner_purchase_orders.status='completed'` with `transfer_id` set. On reject, `status='cancelled'` with `cancel_reason` set. Redirects to `cp-today-orders.php` either way.

- [ ] **Step 1: Write the handler**

Note: `company/pl-godown-transfer-action.php`'s helper functions
(`getGodownQty`, `debitGodown`, `creditGodown`, `insertStockLedger`,
`getCpQty`, `creditCp`, `debitCp`, `lockAndGetQty`, `lockAndGetCpQty`,
`insertCpLedger`) are declared at file scope inside that script, not in a
reusable header, so PHP would fatal on redeclaration if this file also
included that one. Duplicate the exact same function bodies here (same
approach `company/tp-invoice-action.php` and
`company/edit-tp-invoice-action.php` already take — this codebase already
has 3+ independent copies of the identical CP-stock helper functions, so
adding a 4th matches the established pattern rather than fighting it).

```php
<?php
ob_start();
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
require_once("include/GodownAccess.php");
require_once __DIR__ . '/../shared/CpPurchaseOrderBalance.php';
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: cp-today-orders.php?error=unauthorized"); exit;
}
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: cp-today-orders.php"); exit;
}

$po_id  = (int)($_POST['po_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($po_id < 1 || !in_array($action, ['approve', 'reject'], true)) {
    header("Location: cp-today-orders.php?error=missing"); exit;
}

cpEnsurePurchaseOrderTables($db_conn);

$poStmt = $db_conn->prepare("SELECT id, channel_partner_id, status FROM channel_partner_purchase_orders WHERE id = ? LIMIT 1");
$poStmt->bind_param("i", $po_id);
$poStmt->execute();
$po = $poStmt->get_result()->fetch_assoc();
$poStmt->close();

if (!$po || $po['status'] !== 'waiting') {
    header("Location: cp-today-orders.php?error=notwaiting"); exit;
}
$cp_id = (int)$po['channel_partner_id'];

// ── Reject ───────────────────────────────────────────────────────────────
if ($action === 'reject') {
    $reason = trim($_POST['reason'] ?? '') ?: 'Rejected by company';
    $created_by = $_SESSION['LOGIN_USER'] ?? '';
    $s = $db_conn->prepare("UPDATE channel_partner_purchase_orders SET status='cancelled', cancel_reason=?, cancelled_at=NOW(), cancelled_by=? WHERE id=? AND status='waiting'");
    $s->bind_param("ssi", $reason, $created_by, $po_id);
    $s->execute();
    $s->close();
    header("Location: cp-today-orders.php?rejected=1"); exit;
}

// ── Approve ──────────────────────────────────────────────────────────────
$godown_id = (int)($_POST['godown_id'] ?? 0);
if ($godown_id < 1 || !is_godown_allowed($db_conn, $godown_id)) {
    header("Location: cp-today-orders.php?error=unauthorized"); exit;
}

$items = $db_conn->prepare("SELECT product_id, qty, price, amount FROM channel_partner_purchase_order_items WHERE po_id = ?");
$items->bind_param("i", $po_id);
$items->execute();
$poItems = $items->get_result()->fetch_all(MYSQLI_ASSOC);
$items->close();

if (empty($poItems)) {
    header("Location: cp-today-orders.php?error=noitems"); exit;
}

// Authoritative re-check of headroom right before committing stock — closes
// the race between the queue page's displayed headroom (computed at page
// load) and this click (stock/deposit may have moved since). Excludes this
// PO's own already-counted pending value from the check, since approving it
// is what "spends" that reservation for real.
$grandTotal = array_sum(array_column($poItems, 'amount'));
$headroom = cpAvailableHeadroom($db_conn, $cp_id, $po_id);
if ($grandTotal > $headroom + 0.001) {
    header("Location: cp-today-orders.php?error=overheadroom"); exit;
}

$created_by = $_SESSION['LOGIN_USER'] ?? '';

// ── Stock helpers (duplicated from company/pl-godown-transfer-action.php —
// see this file's Step 1 note for why) ─────────────────────────────────────
function cpPoLockAndGetGodownQty(mysqli $db, int $godown_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM stock WHERE user_type='company' AND user_id=? AND product_id=? FOR UPDATE");
    $uid = (string)$godown_id;
    $s->bind_param("si", $uid, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}
function cpPoDebitGodown(mysqli $db, int $godown_id, int $pid, int $qty): void {
    $s = $db->prepare("UPDATE stock SET sent_qty=sent_qty+?, closing_qty=closing_qty-? WHERE user_type='company' AND user_id=? AND product_id=?");
    $uid = (string)$godown_id;
    $s->bind_param("iisi", $qty, $qty, $uid, $pid); $s->execute(); $s->close();
}
function cpPoInsertStockLedger(mysqli $db, int $godown_id, int $pid, string $action, int $qty, int $before, int $after, string $ref_id, string $by): void {
    $user_type = 'company'; $ref_type = 'transfer'; $note = '';
    $uid = (string)$godown_id;
    $s = $db->prepare("INSERT INTO stock_ledger (product_id,user_type,user_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param("isssiiissss", $pid, $user_type, $uid, $action, $qty, $before, $after, $ref_type, $ref_id, $note, $by);
    $s->execute(); $s->close();
}
function cpPoLockAndGetCpQty(mysqli $db, int $cp_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM channel_partner_stock WHERE channel_partner_id=? AND product_id=? FOR UPDATE");
    $s->bind_param("ii", $cp_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}
function cpPoCreditCp(mysqli $db, int $cp_id, int $pid, int $qty): void {
    $s = $db->prepare("INSERT INTO channel_partner_stock (channel_partner_id,product_id,input_qty,closing_qty) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE input_qty=input_qty+VALUES(input_qty), closing_qty=closing_qty+VALUES(input_qty)");
    $s->bind_param("iiii", $cp_id, $pid, $qty, $qty); $s->execute(); $s->close();
}
function cpPoInsertCpLedger(mysqli $db, int $cp_id, int $pid, string $action, int $qty, int $before, int $after, string $ref_id, string $by): void {
    $ref_type = 'transfer'; $note = '';
    $s = $db->prepare("INSERT INTO channel_partner_stock_ledger (channel_partner_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param("iisiiissss", $cp_id, $pid, $action, $qty, $before, $after, $ref_type, $ref_id, $note, $by);
    $s->execute(); $s->close();
}

$db_conn->begin_transaction();
try {
    $ref_id = 'CPPO-' . str_pad($po_id, 5, '0', STR_PAD_LEFT);

    $th = $db_conn->prepare("INSERT INTO pl_godown_transfers (transfer_type,godown_id,cp_id,transfer_date,ref_number,note,created_by,source_po_id) VALUES ('godown_to_location',?,?,?,?,?,?,?)");
    $transfer_date = date('Y-m-d');
    $note = 'Fulfilled from CP purchase order #' . $po_id;
    $th->bind_param("iisssi", $godown_id, $cp_id, $transfer_date, $ref_id, $note, $created_by, $po_id);
    $th->execute();
    $transfer_id = $db_conn->insert_id;
    $th->close();

    $ti = $db_conn->prepare("INSERT INTO pl_godown_transfer_items (transfer_id,product_id,quantity) VALUES (?,?,?)");
    foreach ($poItems as $item) {
        $pid = (int)$item['product_id'];
        $qty = (int)$item['qty'];

        // Lock + re-check godown stock inside the transaction — the
        // authoritative gate, not whatever the queue page showed.
        $gd_before = cpPoLockAndGetGodownQty($db_conn, $godown_id, $pid);
        if ($qty > $gd_before) throw new Exception("Insufficient godown stock for product {$pid}");
        $gd_after = $gd_before - $qty;
        cpPoDebitGodown($db_conn, $godown_id, $pid, $qty);
        cpPoInsertStockLedger($db_conn, $godown_id, $pid, 'transfer_out', $qty, $gd_before, $gd_after, $ref_id, $created_by);

        $cp_before = cpPoLockAndGetCpQty($db_conn, $cp_id, $pid);
        $cp_after  = $cp_before + $qty;
        cpPoCreditCp($db_conn, $cp_id, $pid, $qty);
        cpPoInsertCpLedger($db_conn, $cp_id, $pid, 'transfer_in', $qty, $cp_before, $cp_after, $ref_id, $created_by);

        $ti->bind_param("iii", $transfer_id, $pid, $qty);
        $ti->execute();
    }
    $ti->close();

    $s = $db_conn->prepare("UPDATE channel_partner_purchase_orders SET status='completed', transfer_id=? WHERE id=? AND status='waiting'");
    $s->bind_param("ii", $transfer_id, $po_id);
    $s->execute();
    if ($s->affected_rows < 1) throw new Exception("Purchase order was no longer waiting");
    $s->close();

    $db_conn->commit();
    header("Location: cp-today-orders.php?approved=1&ref=" . urlencode($ref_id)); exit;

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("[CP PO Approve] Transaction failed: " . $e->getMessage());
    header("Location: cp-today-orders.php?error=db&msg=" . urlencode(substr($e->getMessage(), 0, 100))); exit;
}
```

- [ ] **Step 2: Manual smoke test — approve happy path**

From `cp-today-orders.php`, approve the waiting PO from Task 3/4 against a
godown known to have enough stock for its items. Confirm redirect with
`approved=1`, and verify via direct query:

```bash
php -r '
require "femi9/billing/company/include/db-connect.php";
$po = $db_conn->query("SELECT status, transfer_id FROM channel_partner_purchase_orders ORDER BY id DESC LIMIT 1")->fetch_assoc();
var_dump($po);
$t = $db_conn->query("SELECT * FROM pl_godown_transfers WHERE id = " . (int)$po["transfer_id"])->fetch_assoc();
var_dump($t);
'
```
Expected: `status='completed'`, a non-null `transfer_id`, and the
`pl_godown_transfers` row has `source_po_id` matching the PO and
`transfer_type='godown_to_location'`. Also confirm
`channel_partner_stock.closing_qty` increased and the godown's `stock.closing_qty`
decreased by the ordered quantities, and both `stock_ledger` and
`channel_partner_stock_ledger` gained matching rows.

- [ ] **Step 3: Manual smoke test — insufficient godown stock rolls back cleanly**

Submit a new CP PO for a quantity larger than the chosen godown currently
holds, then try to approve it. Confirm redirect with `error=db`, and that
**no** row changed: PO still `status='waiting'`, no new
`pl_godown_transfers` row, godown and CP stock unchanged.

- [ ] **Step 4: Manual smoke test — reject path**

Submit another CP PO, reject it from the queue with a reason. Confirm
`status='cancelled'`, `cancel_reason` set, and no stock/transfer rows were
created.

- [ ] **Step 5: Commit**

```bash
git add "femi9/billing/company/cp-po-action.php"
git commit -m "Add company-side CP purchase order approve/reject handler with transactional stock transfer

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 8: Company navigation + transfer-report badge

**Files:**
- Modify: `femi9/billing/company/femi_menu.php`
- Modify: `femi9/billing/company/manage-pl-godown-transfers.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed elsewhere — final polish task.

- [ ] **Step 1: Add the company nav link**

In `company/femi_menu.php`, add a link near the existing
`purchase-order-report-pending`/`purchase-order-report` entries (these are
the ms/field-order equivalents — put the new one in the same menu group):

```php
<a href="cp-today-orders.php"><i class="material-icons-two-tone">inventory_2</i>CP Purchase Orders</a>
```

- [ ] **Step 2: Add the `source_po_id` badge to the transfer report**

In `company/manage-pl-godown-transfers.php`, find where each transfer row
is rendered (its `SELECT` should already list `pl_godown_transfers`
columns — add `source_po_id` to that SELECT if not already present via
`t.*`), and add a small badge next to the ref number when it's set:

```php
<?php if (!empty($transfer['source_po_id'])): ?>
<span class="badge badge-info" style="font-size:9.5px;" title="Created from a CP purchase order request">From CP Order #<?=(int)$transfer['source_po_id']?></span>
<?php endif; ?>
```

- [ ] **Step 3: Manual smoke test**

Confirm the new "CP Purchase Orders" link appears in Company's nav and
opens `cp-today-orders.php`. Confirm the transfer created in Task 7's Step
2 test now shows the "From CP Order #N" badge in
`manage-pl-godown-transfers.php`, and that manually-created transfers
(no `source_po_id`) show no badge.

- [ ] **Step 4: Commit**

```bash
git add "femi9/billing/company/femi_menu.php" "femi9/billing/company/manage-pl-godown-transfers.php"
git commit -m "Add CP purchase order nav link and transfer-report badge

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 9: End-to-end verification

**Files:** none created/modified — this task only exercises Tasks 1-8 together.

- [ ] **Step 1: Full lifecycle smoke test**

As a CP test account: submit a purchase order under headroom
(`add-purchase-order.php` → `purchase-order-action.php`), confirm it shows
as Waiting in `manage-purchase-orders.php` with the correct headroom chip.

As Company: see it in `cp-today-orders.php`, approve it against a real
godown. Confirm: `channel_partner_purchase_orders.status='completed'`,
`pl_godown_transfers` row created with `source_po_id` set, CP's
`channel_partner_stock` increased, godown's `stock` decreased, both ledgers
gained rows, and `manage-pl-godown-transfers.php` shows the badge.

Back as the CP: confirm `manage-purchase-orders.php` now shows the order
as Completed, and the CP's own `overall-stock.php`
(`channel-partner/overall-stock.php`) reflects the increased quantity.

- [ ] **Step 2: Headroom self-correction test**

Note the CP's headroom before and after the above approval — held stock
value went up, so headroom should have gone down by the order's total
(within the ±5000 cap logic). Then, using the existing manual
Godown⇄Location transfer screen (`company/add-location-to-godown.php`,
`transfer_type='location_to_godown'`) or any existing TP-invoice-from-CP-
stock flow, reduce the CP's held stock back down, and confirm
`cpAvailableHeadroom()` (re-run the Task 1 Step 2 style throwaway check, or
just reload `manage-purchase-orders.php`) rises again accordingly — proving
headroom is live-computed, not a stored/decremented balance.

- [ ] **Step 3: Concurrent-approval race check (manual)**

Submit two CP purchase orders whose combined total exceeds the CP's
current headroom, but each individually fits. Approve the first — confirm
success. Attempt to approve the second — confirm it's rejected with
`error=overheadroom` (Task 7's authoritative re-check inside the approval
action caught what the queue page's stale headroom display could not).

No commit for this task — it's verification only. If any step fails,
return to the relevant earlier task and fix before considering the feature
done.
