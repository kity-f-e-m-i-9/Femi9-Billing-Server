# CP Purchase Order Invoicing & Delivery Note Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When Company approves a Channel Partner (CP) purchase order, generate a real invoice (`cp_invoices`/`cp_invoice_items`) alongside the existing stock transfer, with one auto-generated number that serves as both the invoice number and the delivery note number, printable on one page.

**Architecture:** Mirror the existing TP invoicing pattern (`company/tp-invoice-action.php` + `shared/TpInvoiceNumberService.php`) for CP: a new self-migrating `cp_invoices`/`cp_invoice_items`/`cp_inv_sequence` schema, a new `CpInvoiceNumberService.php` copying `TpInvoiceNumberService.php`'s transaction-safe `FOR UPDATE`-locked sequence logic, invoice creation spliced into the existing `company/cp-po-action.php` approval transaction (after the existing stock-transfer logic, before commit), and a new simple (non-GST) print view.

**Tech Stack:** PHP (mysqli, prepared statements), MySQL/MariaDB (InnoDB), existing house HTML/Bootstrap print-page scaffold.

**Spec:** `docs/superpowers/specs/2026-09-15-cp-invoice-delivery-note-design.md`

## Global Constraints

- One invoice per approved PO; the invoice **is** the delivery note — single number, single row, no separate DN table (spec: "New DB objects", "Explicitly out of scope").
- Invoice number format `CPDN/{fy}/{seq}` (e.g. `CPDN/26-27/00001`), single source `'CO'` — Company is CP's only approver (spec: "New numbering service").
- Number generation and invoice insertion must happen inside the same DB transaction as the existing stock-transfer logic in `cp-po-action.php` — no transfer without an invoice, no invoice without a transfer (spec: "Changes to `company/cp-po-action.php`").
- The existing `pl_godown_transfers` row and all its stock-locking logic are kept unchanged, not replaced (spec: "What TP has that CP will now get").
- No GST tax-invoice framing on the print page — plain invoice-cum-delivery-note headed "Tax Invoice cum Delivery Note" with pricing and a signature block (spec: "New print view").
- `cp_invoices`/`cp_invoice_items` are pure additive records — nothing outside the new approval code path and new print view may read or write them (spec: "Guardrails").

---

## Task 1: `CpInvoiceNumberService.php` — sequence schema + number generation

**Files:**
- Create: `femi9/billing/shared/CpInvoiceNumberService.php`
- Test: `femi9/billing/tests/CpInvoiceNumberServiceTest.php` (new test harness file — this codebase has no formal PHPUnit setup; follow the existing pattern used for other one-off test harnesses, e.g. the "Partial Return Feature Test Harness" mentioned in project history: a plain PHP script run via CLI that asserts and prints PASS/FAIL, connecting to the real dev DB via `config.php`)

**Interfaces:**
- Produces: `function cpInvoiceEnsureSequenceSchema(mysqli $db): void` — self-migrating, creates `cp_inv_sequence` table if missing (columns: `source VARCHAR(10) NOT NULL PRIMARY KEY`, `last_val INT UNSIGNED NOT NULL DEFAULT 0`, `fy VARCHAR(5) NOT NULL DEFAULT ''`).
- Produces: `function cpInvoiceNextNumber(mysqli $db, string $source, string $invoiceDate, int $padDigits = 3): string` — must be called inside an active transaction; returns `"CPDN/$fy/$seq"` for `$source === 'CO'`.

- [ ] **Step 1: Write `CpInvoiceNumberService.php`**

```php
<?php
/**
 * CpInvoiceNumberService — auto-generated CP invoice number series.
 *
 * Company is CP's only approver, so there is only one source, 'CO', and the
 * number carries no source tag: CPDN/{fiscal-year}/{seq}. Mirrors
 * TpInvoiceNumberService.php's locking/self-healing pattern exactly, with
 * its own independent sequence table (cp_inv_sequence) so CP and TP
 * invoice numbering never contend with or influence each other.
 *
 * Must be called inside an active transaction (caller locks the sequence
 * row via FOR UPDATE for the duration of the invoice insert).
 */

function cpInvoiceEnsureSequenceSchema(mysqli $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $table = $db->query("SHOW TABLES LIKE 'cp_inv_sequence'");
    if ($table && $table->num_rows === 0) {
        $db->query("
            CREATE TABLE cp_inv_sequence (
                source VARCHAR(10) NOT NULL,
                last_val INT UNSIGNED NOT NULL DEFAULT 0,
                fy VARCHAR(5) NOT NULL DEFAULT '',
                PRIMARY KEY (source)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

function cpInvoiceNextNumber(mysqli $db, string $source, string $invoiceDate, int $padDigits = 3): string {
    cpInvoiceEnsureSequenceSchema($db);

    $inv_month  = (int)date('n', strtotime($invoiceDate));
    $inv_year   = (int)date('Y', strtotime($invoiceDate));
    $fy_start   = $inv_month >= 4 ? $inv_year : $inv_year - 1;
    $current_fy = substr((string)$fy_start, 2) . '-' . substr((string)($fy_start + 1), 2); // e.g. "26-27"

    $source_esc = $db->real_escape_string($source);
    $db->query("INSERT IGNORE INTO cp_inv_sequence (source, last_val, fy) VALUES ('$source_esc', 0, '')");

    $db->query("SELECT last_val, fy FROM cp_inv_sequence WHERE source='$source_esc' FOR UPDATE");
    $seq_row = $db->query("SELECT last_val, fy FROM cp_inv_sequence WHERE source='$source_esc'")->fetch_assoc();

    $like_pattern = "CPDN/$current_fy/%";
    $max_res = $db->query("SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '/', -1) AS UNSIGNED)) AS max_val FROM cp_invoices WHERE invoice_number LIKE '$like_pattern'");
    $actual_max = (int)(($max_res->fetch_assoc())['max_val'] ?? 0);

    $seq_val  = ($seq_row && $seq_row['fy'] === $current_fy) ? (int)$seq_row['last_val'] : 0;
    $next_val = max($seq_val, $actual_max) + 1;

    $db->query("UPDATE cp_inv_sequence SET last_val=$next_val, fy='$current_fy' WHERE source='$source_esc'");

    $seq_str = str_pad((string)$next_val, $padDigits, '0', STR_PAD_LEFT);
    return "CPDN/$current_fy/$seq_str";
}
```

- [ ] **Step 2: Write a CLI test harness that exercises it against the real dev DB**

```php
<?php
// femi9/billing/tests/CpInvoiceNumberServiceTest.php
// Run: php femi9/billing/tests/CpInvoiceNumberServiceTest.php
chdir(__DIR__ . '/../company');
require_once __DIR__ . '/../../config.php'; // provides $db_conn, matching other harnesses in this codebase
require_once __DIR__ . '/../shared/CpInvoiceNumberService.php';

function assertTrue($cond, $msg) {
    echo ($cond ? "PASS: " : "FAIL: ") . $msg . "\n";
    if (!$cond) exit(1);
}

$db_conn->begin_transaction();

// cp_invoices must exist for the MAX() cross-check query not to error —
// Task 2 creates it; this harness assumes Task 2 has already run, or
// creates a minimal stand-in table if missing.
$t = $db_conn->query("SHOW TABLES LIKE 'cp_invoices'");
if ($t && $t->num_rows === 0) {
    $db_conn->query("CREATE TABLE cp_invoices (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, invoice_number VARCHAR(30) UNIQUE) ENGINE=InnoDB");
}

$date = date('Y-m-d');
$num1 = cpInvoiceNextNumber($db_conn, 'CO', $date);
assertTrue((bool)preg_match('#^CPDN/\d{2}-\d{2}/\d{3}$#', $num1), "first number matches CPDN/{fy}/{seq} format, got $num1");

// Simulate the number actually being used (insert it), then request the next one.
$db_conn->query("INSERT INTO cp_invoices (invoice_number) VALUES ('$num1')");
$num2 = cpInvoiceNextNumber($db_conn, 'CO', $date);
assertTrue($num1 !== $num2, "second call returns a different number than the first ($num1 vs $num2)");

preg_match('#/(\d+)$#', $num1, $m1);
preg_match('#/(\d+)$#', $num2, $m2);
assertTrue((int)$m2[1] === (int)$m1[1] + 1, "sequence increments by exactly 1 ($m1[1] -> $m2[1])");

$db_conn->rollback(); // never persist test data
echo "All CpInvoiceNumberService tests passed.\n";
```

- [ ] **Step 3: Run the test harness and verify it passes**

Run: `php "femi9/billing/tests/CpInvoiceNumberServiceTest.php"`
Expected: three PASS lines, then "All CpInvoiceNumberService tests passed."

- [ ] **Step 4: Commit**

```bash
git add femi9/billing/shared/CpInvoiceNumberService.php femi9/billing/tests/CpInvoiceNumberServiceTest.php
git commit -m "Add CpInvoiceNumberService for CP invoice numbering"
```

---

## Task 2: `cp_invoices` / `cp_invoice_items` schema guard

**Files:**
- Create: `femi9/billing/shared/CpInvoiceSchema.php`
- Modify: `femi9/billing/tests/CpInvoiceNumberServiceTest.php` (replace the Task 1 stand-in table creation with a real call to the new schema guard, now that it exists)

**Interfaces:**
- Consumes: nothing new (same self-migrating pattern as `cpEnsurePurchaseOrderTables()` in `femi9/billing/shared/CpPurchaseOrderBalance.php:24-74`)
- Produces: `function cpEnsureInvoiceTables(mysqli $db): void`

- [ ] **Step 1: Write `CpInvoiceSchema.php`**

```php
<?php
/**
 * CpInvoiceSchema — self-migrating creation of cp_invoices / cp_invoice_items
 * and the cp_invoice_id link column on channel_partner_purchase_orders.
 * Same pattern as cpEnsurePurchaseOrderTables() in CpPurchaseOrderBalance.php.
 */

function cpEnsureInvoiceTables(mysqli $db): void
{
    $invTable = $db->query("SHOW TABLES LIKE 'cp_invoices'");
    if ($invTable && $invTable->num_rows === 0) {
        $db->query("
            CREATE TABLE cp_invoices (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                invoice_number VARCHAR(30) NOT NULL,
                channel_partner_id INT UNSIGNED NOT NULL,
                source_godown_id INT UNSIGNED NULL,
                product_type ENUM('napkin','diaper') NOT NULL DEFAULT 'napkin',
                invoice_date DATE NOT NULL,
                total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                use_default_delivery_address TINYINT(1) NOT NULL DEFAULT 1,
                custom_delivery_line1 VARCHAR(255) NULL,
                custom_delivery_line2 VARCHAR(255) NULL,
                custom_delivery_city VARCHAR(100) NULL,
                custom_delivery_district VARCHAR(100) NULL,
                custom_delivery_state VARCHAR(100) NULL,
                custom_delivery_country VARCHAR(100) NULL,
                custom_delivery_pincode VARCHAR(20) NULL,
                transfer_id INT UNSIGNED NULL,
                created_by VARCHAR(100) NULL,
                created_by_user_type VARCHAR(20) NOT NULL DEFAULT 'company',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cpinv_number (invoice_number),
                KEY idx_cpinv_cp (channel_partner_id),
                KEY idx_cpinv_transfer (transfer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    $itemsTable = $db->query("SHOW TABLES LIKE 'cp_invoice_items'");
    if ($itemsTable && $itemsTable->num_rows === 0) {
        $db->query("
            CREATE TABLE cp_invoice_items (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                cp_invoice_id INT UNSIGNED NOT NULL,
                product_id INT UNSIGNED NOT NULL,
                quantity INT UNSIGNED NOT NULL,
                rate DECIMAL(10,2) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                KEY idx_cpinvi_invoice (cp_invoice_id),
                CONSTRAINT fk_cpinvi_invoice FOREIGN KEY (cp_invoice_id) REFERENCES cp_invoices(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    $col = $db->query("SHOW COLUMNS FROM channel_partner_purchase_orders LIKE 'cp_invoice_id'");
    if ($col && $col->num_rows === 0) {
        $db->query("ALTER TABLE channel_partner_purchase_orders ADD COLUMN cp_invoice_id INT UNSIGNED NULL AFTER transfer_id");
        $db->query("ALTER TABLE channel_partner_purchase_orders ADD KEY idx_cppo_invoice (cp_invoice_id)");
    }
}
```

- [ ] **Step 2: Update the Task 1 test harness to use the real schema guard**

In `femi9/billing/tests/CpInvoiceNumberServiceTest.php`, replace:

```php
$t = $db_conn->query("SHOW TABLES LIKE 'cp_invoices'");
if ($t && $t->num_rows === 0) {
    $db_conn->query("CREATE TABLE cp_invoices (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, invoice_number VARCHAR(30) UNIQUE) ENGINE=InnoDB");
}
```

with:

```php
require_once __DIR__ . '/../shared/CpInvoiceSchema.php';
cpEnsureInvoiceTables($db_conn);
```

- [ ] **Step 3: Re-run the Task 1 test harness to confirm it still passes against the real schema**

Run: `php "femi9/billing/tests/CpInvoiceNumberServiceTest.php"`
Expected: same three PASS lines as Task 1.

- [ ] **Step 4: Manually verify the schema guard is idempotent**

Run: `php -r '
require "femi9/billing/config.php";
require "femi9/billing/shared/CpInvoiceSchema.php";
cpEnsureInvoiceTables($db_conn);
cpEnsureInvoiceTables($db_conn);
echo "ran twice with no error\n";
'`
Expected: `ran twice with no error` (proves `SHOW TABLES`/`SHOW COLUMNS` guards prevent duplicate-table/duplicate-column errors on a second call within the same or a later request).

- [ ] **Step 5: Commit**

```bash
git add femi9/billing/shared/CpInvoiceSchema.php femi9/billing/tests/CpInvoiceNumberServiceTest.php
git commit -m "Add self-migrating cp_invoices/cp_invoice_items schema"
```

---

## Task 3: Splice invoice creation into `cp-po-action.php`'s approval transaction

**Files:**
- Modify: `femi9/billing/company/cp-po-action.php:1-165`
- Test: `femi9/billing/tests/CpPoInvoiceActionTest.php` (new)

**Interfaces:**
- Consumes: `cpInvoiceNextNumber($db, $source, $invoiceDate, $padDigits=3): string` (Task 1), `cpEnsureInvoiceTables($db): void` (Task 2)
- Produces: after a successful approval, `cp_invoices.id` is retrievable via `channel_partner_purchase_orders.cp_invoice_id`; `cp_invoices.invoice_number` is the combined invoice/delivery-note number surfaced in the redirect as `&inv=` (new query param, alongside the existing `&ref=`).

- [ ] **Step 1: Add the two new `require_once` calls near the top of `cp-po-action.php`**

In `femi9/billing/company/cp-po-action.php`, after line 6 (`require_once __DIR__ . '/../shared/CpPurchaseOrderBalance.php';`):

```php
require_once __DIR__ . '/../shared/CpInvoiceNumberService.php';
require_once __DIR__ . '/../shared/CpInvoiceSchema.php';
```

- [ ] **Step 2: Call the new schema guard alongside the existing one**

In `femi9/billing/company/cp-po-action.php`, change line 23 from:

```php
cpEnsurePurchaseOrderTables($db_conn);
```

to:

```php
cpEnsurePurchaseOrderTables($db_conn);
cpEnsureInvoiceTables($db_conn);
```

- [ ] **Step 3: Fetch the PO's delivery-address fields and product_type alongside its existing header fields**

In `femi9/billing/company/cp-po-action.php`, change the PO SELECT at lines 25-29 from:

```php
$poStmt = $db_conn->prepare("SELECT id, channel_partner_id, status FROM channel_partner_purchase_orders WHERE id = ? LIMIT 1");
$poStmt->bind_param("i", $po_id);
$poStmt->execute();
$po = $poStmt->get_result()->fetch_assoc();
$poStmt->close();
```

to:

```php
$poStmt = $db_conn->prepare("SELECT id, channel_partner_id, status, product_type,
    use_default_delivery_address, custom_delivery_line1, custom_delivery_line2,
    custom_delivery_city, custom_delivery_district, custom_delivery_state,
    custom_delivery_country, custom_delivery_pincode
    FROM channel_partner_purchase_orders WHERE id = ? LIMIT 1");
$poStmt->bind_param("i", $po_id);
$poStmt->execute();
$po = $poStmt->get_result()->fetch_assoc();
$poStmt->close();
```

- [ ] **Step 4: Insert the invoice + items inside the existing transaction, after the transfer items loop and before the PO-completion UPDATE**

In `femi9/billing/company/cp-po-action.php`, the existing transaction body (lines 117-156) ends its per-item loop at line 150 (`$ti->close();`) and then runs the PO-completion UPDATE at lines 152-156. Insert the following between those two blocks — i.e. immediately after line 150 (`$ti->close();`) and before line 152 (`$s = $db_conn->prepare("UPDATE channel_partner_purchase_orders SET status='completed'...`):

```php
    $invoice_date = date('Y-m-d');
    $inv_num = cpInvoiceNextNumber($db_conn, 'CO', $invoice_date);
    $grand_total = array_sum(array_column($poItems, 'amount'));

    $ih = $db_conn->prepare("INSERT INTO cp_invoices
        (invoice_number, channel_partner_id, source_godown_id, product_type, invoice_date,
         total_amount, use_default_delivery_address, custom_delivery_line1, custom_delivery_line2,
         custom_delivery_city, custom_delivery_district, custom_delivery_state,
         custom_delivery_country, custom_delivery_pincode, transfer_id, created_by, created_by_user_type)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'company')");
    $use_default = (int)($po['use_default_delivery_address'] ?? 1);
    $product_type = $po['product_type'] ?? 'napkin';
    // Type string built column-by-column against the VALUES list above:
    // invoice_number(s) channel_partner_id(i) source_godown_id(i) product_type(s)
    // invoice_date(s) total_amount(d) use_default_delivery_address(i)
    // custom_delivery_line1..pincode(s x7) transfer_id(i) created_by(s)
    // = "siissdisssssssis" (16 chars for 16 placeholders; 'company' is a literal, not bound)
    $ih->bind_param(
        "siissdisssssssis",
        $inv_num, $cp_id, $godown_id, $product_type, $invoice_date,
        $grand_total, $use_default,
        $po['custom_delivery_line1'], $po['custom_delivery_line2'], $po['custom_delivery_city'],
        $po['custom_delivery_district'], $po['custom_delivery_state'], $po['custom_delivery_country'],
        $po['custom_delivery_pincode'], $transfer_id, $created_by
    );
    $ih->execute();
    $cp_invoice_id = $db_conn->insert_id;
    $ih->close();

    $ii = $db_conn->prepare("INSERT INTO cp_invoice_items (cp_invoice_id, product_id, quantity, rate, amount) VALUES (?,?,?,?,?)");
    foreach ($poItems as $item) {
        $pid = (int)$item['product_id'];
        $qty = (int)$item['qty'];
        $rate = (float)$item['price'];
        $amount = (float)$item['amount'];
        $ii->bind_param("iiidd", $cp_invoice_id, $pid, $qty, $rate, $amount);
        $ii->execute();
    }
    $ii->close();

```

- [ ] **Step 5: Update the PO-completion UPDATE to also set `cp_invoice_id`**

Update the PO-completion UPDATE at (originally) lines 152-156 from:

```php
    $s = $db_conn->prepare("UPDATE channel_partner_purchase_orders SET status='completed', transfer_id=? WHERE id=? AND status='waiting'");
    $s->bind_param("ii", $transfer_id, $po_id);
    $s->execute();
    if ($s->affected_rows < 1) throw new Exception("Purchase order was no longer waiting");
    $s->close();
```

to:

```php
    $s = $db_conn->prepare("UPDATE channel_partner_purchase_orders SET status='completed', transfer_id=?, cp_invoice_id=? WHERE id=? AND status='waiting'");
    $s->bind_param("iii", $transfer_id, $cp_invoice_id, $po_id);
    $s->execute();
    if ($s->affected_rows < 1) throw new Exception("Purchase order was no longer waiting");
    $s->close();
```

- [ ] **Step 6: Surface the invoice number in the success redirect**

Change the commit/redirect line from:

```php
    $db_conn->commit();
    header("Location: cp-today-orders.php?approved=1&ref=" . urlencode($ref_id)); exit;
```

to:

```php
    $db_conn->commit();
    header("Location: cp-today-orders.php?approved=1&ref=" . urlencode($ref_id) . "&inv=" . urlencode($inv_num)); exit;
```

- [ ] **Step 7: Write a CLI test harness against a real waiting PO in the dev DB**

This mirrors the "Happy-path approval test" harness pattern already used for Task 7 in the original CP PO plan (per project history: create a real waiting PO, run the approval, assert stock + now also invoice rows, then roll the whole thing back to restore baseline).

```php
<?php
// femi9/billing/tests/CpPoInvoiceActionTest.php
// Run: php femi9/billing/tests/CpPoInvoiceActionTest.php
require_once __DIR__ . '/../config.php'; // $db_conn
require_once __DIR__ . '/../shared/CpPurchaseOrderBalance.php';
require_once __DIR__ . '/../shared/CpInvoiceSchema.php';
require_once __DIR__ . '/../shared/CpInvoiceNumberService.php';

function assertTrue($cond, $msg) {
    echo ($cond ? "PASS: " : "FAIL: ") . $msg . "\n";
    if (!$cond) exit(1);
}

cpEnsurePurchaseOrderTables($db_conn);
cpEnsureInvoiceTables($db_conn);

$db_conn->begin_transaction();

// Pick a real CP and product with existing godown stock to build a minimal
// one-line waiting PO against, exactly as the original Task 7 test harness did.
$cp = $db_conn->query("SELECT id FROM channel_partners LIMIT 1")->fetch_assoc();
$godown = $db_conn->query("SELECT id FROM company_godown LIMIT 1")->fetch_assoc();
$stockRow = $db_conn->query("SELECT product_id, closing_qty FROM stock WHERE user_type='company' AND user_id='{$godown['id']}' AND closing_qty >= 2 LIMIT 1")->fetch_assoc();
assertTrue($cp && $godown && $stockRow, "found a CP, godown, and a product with >=2 units in stock to test against");

$cp_id = (int)$cp['id'];
$godown_id = (int)$godown['id'];
$pid = (int)$stockRow['product_id'];
$product = $db_conn->query("SELECT mrp FROM products WHERE id=$pid")->fetch_assoc();
$rate = (float)$product['mrp'];
$qty = 1;
$amount = $rate * $qty;

$db_conn->query("INSERT INTO channel_partner_purchase_orders (channel_partner_id, product_type, order_date, status) VALUES ($cp_id, 'napkin', CURDATE(), 'waiting')");
$po_id = $db_conn->insert_id;
$db_conn->query("INSERT INTO channel_partner_purchase_order_items (po_id, product_id, qty, price, amount) VALUES ($po_id, $pid, $qty, $rate, $amount)");

// Inline the approval transaction body rather than posting HTTP, so this
// harness can run headless and still roll everything back cleanly.
$poItems = [['product_id' => $pid, 'qty' => $qty, 'price' => $rate, 'amount' => $amount]];
$ref_id = 'CPPO-' . str_pad($po_id, 5, '0', STR_PAD_LEFT);
$created_by = 'test-harness';

$th = $db_conn->prepare("INSERT INTO pl_godown_transfers (transfer_type,godown_id,cp_id,transfer_date,ref_number,note,created_by,source_po_id) VALUES ('godown_to_location',?,?,?,?,?,?,?)");
$transfer_date = date('Y-m-d');
$note = 'test';
$th->bind_param("iissssi", $godown_id, $cp_id, $transfer_date, $ref_id, $note, $created_by, $po_id);
$th->execute();
$transfer_id = $db_conn->insert_id;
$th->close();

$db_conn->query("UPDATE stock SET sent_qty=sent_qty+$qty, closing_qty=closing_qty-$qty WHERE user_type='company' AND user_id='$godown_id' AND product_id=$pid");
$db_conn->query("INSERT INTO channel_partner_stock (channel_partner_id,product_id,input_qty,closing_qty) VALUES ($cp_id,$pid,$qty,$qty) ON DUPLICATE KEY UPDATE input_qty=input_qty+$qty, closing_qty=closing_qty+$qty");
$db_conn->query("INSERT INTO pl_godown_transfer_items (transfer_id,product_id,quantity) VALUES ($transfer_id,$pid,$qty)");

$invoice_date = date('Y-m-d');
$inv_num = cpInvoiceNextNumber($db_conn, 'CO', $invoice_date);
$grand_total = $amount;
$use_default = 1;
$product_type = 'napkin';

$ih = $db_conn->prepare("INSERT INTO cp_invoices
    (invoice_number, channel_partner_id, source_godown_id, product_type, invoice_date,
     total_amount, use_default_delivery_address, custom_delivery_line1, custom_delivery_line2,
     custom_delivery_city, custom_delivery_district, custom_delivery_state,
     custom_delivery_country, custom_delivery_pincode, transfer_id, created_by, created_by_user_type)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'company')");
$null = null;
$ih->bind_param(
    "siissdisssssssis",
    $inv_num, $cp_id, $godown_id, $product_type, $invoice_date,
    $grand_total, $use_default,
    $null, $null, $null, $null, $null, $null, $null,
    $transfer_id, $created_by
);
$ih->execute();
$cp_invoice_id = $db_conn->insert_id;
$ih->close();

$ii = $db_conn->prepare("INSERT INTO cp_invoice_items (cp_invoice_id, product_id, quantity, rate, amount) VALUES (?,?,?,?,?)");
$ii->bind_param("iiidd", $cp_invoice_id, $pid, $qty, $rate, $amount);
$ii->execute();
$ii->close();

$db_conn->query("UPDATE channel_partner_purchase_orders SET status='completed', transfer_id=$transfer_id, cp_invoice_id=$cp_invoice_id WHERE id=$po_id");

// Assertions
$invRow = $db_conn->query("SELECT * FROM cp_invoices WHERE id=$cp_invoice_id")->fetch_assoc();
assertTrue($invRow !== null, "cp_invoices row was created");
assertTrue((bool)preg_match('#^CPDN/\d{2}-\d{2}/\d{3}$#', $invRow['invoice_number']), "invoice_number matches CPDN/{fy}/{seq}, got {$invRow['invoice_number']}");
assertTrue(abs((float)$invRow['total_amount'] - $grand_total) < 0.001, "total_amount matches the PO's grand total");
assertTrue((int)$invRow['transfer_id'] === $transfer_id, "invoice links to the same transfer_id the stock movement created");

$itemRow = $db_conn->query("SELECT * FROM cp_invoice_items WHERE cp_invoice_id=$cp_invoice_id")->fetch_assoc();
assertTrue($itemRow !== null, "cp_invoice_items row was created");
assertTrue((int)$itemRow['quantity'] === $qty, "item quantity matches the PO line");

$poRow = $db_conn->query("SELECT cp_invoice_id, transfer_id, status FROM channel_partner_purchase_orders WHERE id=$po_id")->fetch_assoc();
assertTrue((int)$poRow['cp_invoice_id'] === $cp_invoice_id, "PO's cp_invoice_id links back to the new invoice");
assertTrue($poRow['status'] === 'completed', "PO status is completed");

$db_conn->rollback(); // restore exact baseline — no test data persists
echo "All CpPoInvoiceAction tests passed.\n";
```

- [ ] **Step 8: Run the test harness and verify it passes**

Run: `php "femi9/billing/tests/CpPoInvoiceActionTest.php"`
Expected: all PASS lines, then "All CpPoInvoiceAction tests passed."

- [ ] **Step 9: Manually verify the real edited file's PHP syntax is valid**

Run: `php -l "femi9/billing/company/cp-po-action.php"`
Expected: `No syntax errors detected in ...cp-po-action.php`

- [ ] **Step 10: Commit**

```bash
git add femi9/billing/company/cp-po-action.php femi9/billing/tests/CpPoInvoiceActionTest.php
git commit -m "Generate a cp_invoices row (invoice = delivery note) on CP PO approval"
```

---

## Task 4: CP invoice data loader

**Files:**
- Create: `femi9/billing/shared/CpInvoiceData.php`

**Interfaces:**
- Consumes: `cp_invoices`, `cp_invoice_items`, `channel_partners`, `products`, `company_godown` tables (all pre-existing or created in Task 2)
- Produces: `function load_cp_invoice_data(mysqli $db_conn, int $inv_id): ?array` — returns an associative array with keys `result_Invoice_Details` (header row joined to CP + godown info), `result_Items` (array of line items joined to product names), `business_name`, or `null` if the id doesn't resolve.

- [ ] **Step 1: Write `CpInvoiceData.php`**

```php
<?php
/**
 * Loads everything render_cp_invoice_html() (CpInvoiceHtml.php) needs for
 * one CP invoice (which doubles as its delivery note). Mirrors the shape of
 * shared/TpInvoiceData.php but deliberately simpler — no GST computation,
 * since the CP invoice-cum-delivery-note is not a GST tax invoice (see
 * spec: "New print view").
 *
 * Returns null if the invoice id doesn't resolve to a real invoice.
 */

function load_cp_invoice_data(mysqli $db_conn, int $inv_id): ?array {
    $stmt = $db_conn->prepare("
        SELECT cpi.*,
               cp.name AS cp_name, cp.cp_id AS cp_code, cp.mobile AS cp_mobile, cp.gstin AS cp_gstin,
               cp.branch_line1, cp.branch_line2, cp.branch_city, cp.branch_district, cp.branch_state, cp.branch_country, cp.branch_pincode,
               gd.gname AS godown_name
        FROM cp_invoices cpi
        JOIN channel_partners cp ON cp.id = cpi.channel_partner_id
        LEFT JOIN company_godown gd ON gd.id = cpi.source_godown_id
        WHERE cpi.id = ?
    ");
    $stmt->bind_param("i", $inv_id);
    $stmt->execute();
    $result_Invoice_Details = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$result_Invoice_Details) {
        return null;
    }

    $stmt2 = $db_conn->prepare("
        SELECT cpii.quantity, cpii.rate, cpii.amount, p.productName
        FROM cp_invoice_items cpii
        JOIN products p ON p.id = cpii.product_id
        WHERE cpii.cp_invoice_id = ?
        ORDER BY cpii.id ASC
    ");
    $stmt2->bind_param("i", $inv_id);
    $stmt2->execute();
    $result_Items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();

    global $business_name;

    return [
        'result_Invoice_Details' => $result_Invoice_Details,
        'result_Items'           => $result_Items,
        'business_name'          => $business_name ?? '',
    ];
}
```

- [ ] **Step 2: Write a CLI test harness reusing the invoice created by Task 3's harness pattern**

```php
<?php
// femi9/billing/tests/CpInvoiceDataTest.php
// Run: php femi9/billing/tests/CpInvoiceDataTest.php
require_once __DIR__ . '/../config.php'; // $db_conn
require_once __DIR__ . '/../shared/CpInvoiceSchema.php';
require_once __DIR__ . '/../shared/CpInvoiceNumberService.php';
require_once __DIR__ . '/../shared/CpInvoiceData.php';

function assertTrue($cond, $msg) {
    echo ($cond ? "PASS: " : "FAIL: ") . $msg . "\n";
    if (!$cond) exit(1);
}

cpEnsureInvoiceTables($db_conn);
$db_conn->begin_transaction();

$cp = $db_conn->query("SELECT id FROM channel_partners LIMIT 1")->fetch_assoc();
$product = $db_conn->query("SELECT id, mrp FROM products LIMIT 1")->fetch_assoc();
assertTrue($cp && $product, "found a channel partner and a product to build a test invoice against");

$inv_num = cpInvoiceNextNumber($db_conn, 'CO', date('Y-m-d'));
$db_conn->query("INSERT INTO cp_invoices (invoice_number, channel_partner_id, invoice_date, total_amount) VALUES ('$inv_num', {$cp['id']}, CURDATE(), {$product['mrp']})");
$inv_id = $db_conn->insert_id;
$db_conn->query("INSERT INTO cp_invoice_items (cp_invoice_id, product_id, quantity, rate, amount) VALUES ($inv_id, {$product['id']}, 1, {$product['mrp']}, {$product['mrp']})");

$data = load_cp_invoice_data($db_conn, $inv_id);
assertTrue($data !== null, "load_cp_invoice_data returns non-null for a real invoice id");
assertTrue($data['result_Invoice_Details']['invoice_number'] === $inv_num, "returned header has the correct invoice_number");
assertTrue(count($data['result_Items']) === 1, "returned exactly one line item");
assertTrue($data['result_Items'][0]['productName'] !== null, "line item joined to a real product name");

$missing = load_cp_invoice_data($db_conn, 999999999);
assertTrue($missing === null, "load_cp_invoice_data returns null for a nonexistent id");

$db_conn->rollback();
echo "All CpInvoiceData tests passed.\n";
```

- [ ] **Step 3: Run the test harness and verify it passes**

Run: `php "femi9/billing/tests/CpInvoiceDataTest.php"`
Expected: all PASS lines, then "All CpInvoiceData tests passed."

- [ ] **Step 4: Commit**

```bash
git add femi9/billing/shared/CpInvoiceData.php femi9/billing/tests/CpInvoiceDataTest.php
git commit -m "Add CP invoice data loader"
```

---

## Task 5: CP invoice-cum-delivery-note HTML renderer

**Files:**
- Create: `femi9/billing/shared/CpInvoiceHtml.php`

**Interfaces:**
- Consumes: the array shape returned by `load_cp_invoice_data()` (Task 4): `result_Invoice_Details` (assoc array with `invoice_number`, `invoice_date`, `total_amount`, `cp_name`, `cp_code`, `cp_mobile`, `cp_gstin`, `branch_line1/2/city/district/state/country/pincode`, `use_default_delivery_address`, `custom_delivery_*`, `godown_name`), `result_Items` (list of assoc arrays with `productName`, `quantity`, `rate`, `amount`), `business_name`
- Produces: `function render_cp_invoice_html(array $invData): string` — returns a complete HTML fragment (not a full page) suitable for embedding inside `<div id="divToPrint">`, same contract as `render_tp_invoice_html()`.

- [ ] **Step 1: Write `CpInvoiceHtml.php`**

```php
<?php
/**
 * Renders one CP invoice-cum-delivery-note as an HTML fragment. This single
 * document serves as both the tax invoice and the delivery note (spec:
 * "the invoice IS the delivery note — one document, one auto-generated
 * number"), so it carries pricing plus a goods-receipt signature block,
 * rather than being split into two separate printouts.
 */

function render_cp_invoice_html(array $invData): string {
    $inv = $invData['result_Invoice_Details'];
    $items = $invData['result_Items'];
    $business_name = $invData['business_name'];

    $delivery_line1 = $inv['use_default_delivery_address'] ? $inv['branch_line1'] : $inv['custom_delivery_line1'];
    $delivery_line2 = $inv['use_default_delivery_address'] ? $inv['branch_line2'] : $inv['custom_delivery_line2'];
    $delivery_city  = $inv['use_default_delivery_address'] ? $inv['branch_city']  : $inv['custom_delivery_city'];
    $delivery_state = $inv['use_default_delivery_address'] ? $inv['branch_state'] : $inv['custom_delivery_state'];
    $delivery_pin   = $inv['use_default_delivery_address'] ? $inv['branch_pincode'] : $inv['custom_delivery_pincode'];

    ob_start();
    ?>
    <div class="maincontainar" style="padding:20px;font-family:Arial,sans-serif;">
        <div style="text-align:center;margin-bottom:10px;">
            <h3 style="margin:0;"><?= htmlspecialchars($business_name) ?></h3>
            <h5 style="margin:4px 0;letter-spacing:1px;">TAX INVOICE CUM DELIVERY NOTE</h5>
        </div>
        <table width="100%" style="border-collapse:collapse;margin-bottom:12px;">
            <tr>
                <td style="width:50%;vertical-align:top;border:1px solid #ccc;padding:8px;">
                    <strong>Bill To / Deliver To:</strong><br>
                    <?= htmlspecialchars($inv['cp_name']) ?> (<?= htmlspecialchars($inv['cp_code']) ?>)<br>
                    <?= htmlspecialchars(trim($delivery_line1 . ' ' . $delivery_line2)) ?><br>
                    <?= htmlspecialchars(trim($delivery_city . ' ' . $delivery_state . ' ' . $delivery_pin)) ?><br>
                    <?php if (!empty($inv['cp_gstin'])): ?>GSTIN: <?= htmlspecialchars($inv['cp_gstin']) ?><br><?php endif; ?>
                    <?php if (!empty($inv['cp_mobile'])): ?>Mobile: <?= htmlspecialchars($inv['cp_mobile']) ?><?php endif; ?>
                </td>
                <td style="width:50%;vertical-align:top;border:1px solid #ccc;padding:8px;">
                    <strong>Invoice / Delivery Note No:</strong> <?= htmlspecialchars($inv['invoice_number']) ?><br>
                    <strong>Date:</strong> <?= htmlspecialchars(date('d-M-Y', strtotime($inv['invoice_date']))) ?><br>
                    <strong>Dispatched From:</strong> <?= htmlspecialchars($inv['godown_name'] ?? '-') ?>
                </td>
            </tr>
        </table>
        <table width="100%" style="border-collapse:collapse;">
            <thead>
                <tr style="background:#f0f0f0;">
                    <th style="border:1px solid #ccc;padding:6px;">#</th>
                    <th style="border:1px solid #ccc;padding:6px;">Product</th>
                    <th style="border:1px solid #ccc;padding:6px;">Qty</th>
                    <th style="border:1px solid #ccc;padding:6px;">Rate</th>
                    <th style="border:1px solid #ccc;padding:6px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td style="border:1px solid #ccc;padding:6px;text-align:center;"><?= $i + 1 ?></td>
                    <td style="border:1px solid #ccc;padding:6px;"><?= htmlspecialchars($item['productName']) ?></td>
                    <td style="border:1px solid #ccc;padding:6px;text-align:center;"><?= (int)$item['quantity'] ?></td>
                    <td style="border:1px solid #ccc;padding:6px;text-align:right;"><?= number_format((float)$item['rate'], 2) ?></td>
                    <td style="border:1px solid #ccc;padding:6px;text-align:right;"><?= number_format((float)$item['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="border:1px solid #ccc;padding:6px;text-align:right;"><strong>Total</strong></td>
                    <td style="border:1px solid #ccc;padding:6px;text-align:right;"><strong><?= number_format((float)$inv['total_amount'], 2) ?></strong></td>
                </tr>
            </tfoot>
        </table>
        <table width="100%" style="margin-top:40px;">
            <tr>
                <td style="width:50%;">Received the above goods in good condition.</td>
                <td style="width:50%;text-align:right;">For <?= htmlspecialchars($business_name) ?></td>
            </tr>
            <tr>
                <td style="padding-top:40px;">Receiver's Signature: ___________________</td>
                <td style="padding-top:40px;text-align:right;">Authorized Signatory</td>
            </tr>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
```

- [ ] **Step 2: Write a CLI test harness that renders against a real invoice**

```php
<?php
// femi9/billing/tests/CpInvoiceHtmlTest.php
// Run: php femi9/billing/tests/CpInvoiceHtmlTest.php
require_once __DIR__ . '/../config.php'; // $db_conn
require_once __DIR__ . '/../shared/CpInvoiceSchema.php';
require_once __DIR__ . '/../shared/CpInvoiceNumberService.php';
require_once __DIR__ . '/../shared/CpInvoiceData.php';
require_once __DIR__ . '/../shared/CpInvoiceHtml.php';

function assertTrue($cond, $msg) {
    echo ($cond ? "PASS: " : "FAIL: ") . $msg . "\n";
    if (!$cond) exit(1);
}

cpEnsureInvoiceTables($db_conn);
$db_conn->begin_transaction();

$cp = $db_conn->query("SELECT id FROM channel_partners LIMIT 1")->fetch_assoc();
$product = $db_conn->query("SELECT id, mrp, productName FROM products LIMIT 1")->fetch_assoc();
assertTrue($cp && $product, "found a channel partner and a product to build a test invoice against");

$inv_num = cpInvoiceNextNumber($db_conn, 'CO', date('Y-m-d'));
$db_conn->query("INSERT INTO cp_invoices (invoice_number, channel_partner_id, invoice_date, total_amount) VALUES ('$inv_num', {$cp['id']}, CURDATE(), {$product['mrp']})");
$inv_id = $db_conn->insert_id;
$db_conn->query("INSERT INTO cp_invoice_items (cp_invoice_id, product_id, quantity, rate, amount) VALUES ($inv_id, {$product['id']}, 1, {$product['mrp']}, {$product['mrp']})");

$data = load_cp_invoice_data($db_conn, $inv_id);
$html = render_cp_invoice_html($data);

assertTrue(strpos($html, 'TAX INVOICE CUM DELIVERY NOTE') !== false, "renders the combined invoice+DN heading");
assertTrue(strpos($html, htmlspecialchars($inv_num)) !== false, "renders the invoice number");
assertTrue(strpos($html, htmlspecialchars($product['productName'])) !== false, "renders the line-item product name");
assertTrue(strpos($html, "Receiver's Signature") !== false, "renders a goods-receipt signature block");

$db_conn->rollback();
echo "All CpInvoiceHtml tests passed.\n";
```

- [ ] **Step 3: Run the test harness and verify it passes**

Run: `php "femi9/billing/tests/CpInvoiceHtmlTest.php"`
Expected: all PASS lines, then "All CpInvoiceHtml tests passed."

- [ ] **Step 4: Commit**

```bash
git add femi9/billing/shared/CpInvoiceHtml.php femi9/billing/tests/CpInvoiceHtmlTest.php
git commit -m "Add CP invoice-cum-delivery-note HTML renderer"
```

---

## Task 6: `cp-invoice-print.php` page + link from `cp-today-orders.php`

**Files:**
- Create: `femi9/billing/company/cp-invoice-print.php`
- Modify: `femi9/billing/company/cp-today-orders.php:54-107` (fetch `cp_invoice_id`), `:340-348` (show a "View Invoice" link on completed rows)

**Interfaces:**
- Consumes: `load_cp_invoice_data()` (Task 4), `render_cp_invoice_html()` (Task 5)
- Produces: a GET-accessible print page at `company/cp-invoice-print.php?id=<base64 invoice id>`, following the exact controller shape of `company/tp-invoice-print.php:1-23,45-127` (auth via `checksession.php`, base64-decoded `id` param, popup-window print via `PrintDiv()`, no WhatsApp share button — out of scope per spec).

- [ ] **Step 1: Write `cp-invoice-print.php`**

```php
<?php include("checksession.php"); error_reporting(0); include("config.php");

require_once __DIR__ . '/../shared/CpInvoiceData.php';
require_once __DIR__ . '/../shared/CpInvoiceHtml.php';

$enc_id = $_GET['id'] ?? '';
$inv_id = (int)base64_decode($enc_id);
if (!$inv_id) { header("Location: cp-today-orders.php"); exit; }

$invData = load_cp_invoice_data($db_conn, $inv_id);
if (!$invData) { header("Location: cp-today-orders.php"); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CP Invoice : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/images/neptune.png" />
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

                <script type="text/javascript">
                function PrintDiv() {
                    var divToPrint = document.getElementById('divToPrint');
                    var popupWin = window.open('', '_blank', 'width=990,height=540,left=200,top=80');
                    popupWin.document.open();
                    popupWin.document.write(
                        '<html><head><style>' +
                        '@page { margin: 0; size: auto; }' +
                        'body { margin: 10mm; }' +
                        '</style></head>' +
                        '<body onload="window.print()">' + divToPrint.innerHTML + '</body></html>'
                    );
                    popupWin.document.close();
                }
                </script>

                <div id="cpInvoiceActionBar" class="d-flex flex-wrap justify-content-end">
                    <button type="button" onClick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">Print</button>
                    <button type="button" onClick="javascript:window.location='cp-today-orders.php';" class="btn btn-primary m-b-xs m-r-xs">Back to CP Purchase Orders</button>
                </div>

                <br/>
                <div style="clear:both;"></div>

                <div id="divToPrint">
                <div id="divToPrintScroll">
<?php echo render_cp_invoice_html($invData); ?>
                </div>
                </div>

            </div>
        </div>
    </div>

    <script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
    <script src="../../assets/plugins/pace/pace.min.js"></script>
    <script src="../../assets/js/main.min.js"></script>
    <script src="../../assets/js/custom.js"></script>
</body>
</html>
```

- [ ] **Step 2: Verify PHP syntax**

Run: `php -l "femi9/billing/company/cp-invoice-print.php"`
Expected: `No syntax errors detected...`

- [ ] **Step 3: Fetch `cp_invoice_id` in `cp-today-orders.php`'s order query**

In `femi9/billing/company/cp-today-orders.php`, change the SELECT at lines 54-63 from:

```php
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
```

to:

```php
$stmt = $db_conn->prepare(
    "SELECT o.id, o.order_date, o.status, o.transfer_id, o.cp_invoice_id, o.cancel_reason, o.product_type, o.channel_partner_id,
            cp.name AS cp_name, cp.cp_id AS cp_code,
            i.product_id, i.qty, i.price, i.amount, p.productName
     FROM channel_partner_purchase_orders o
     JOIN channel_partners cp ON cp.id = o.channel_partner_id
     LEFT JOIN channel_partner_purchase_order_items i ON i.po_id = o.id
     LEFT JOIN products p ON p.id = i.product_id
     $whereSql
     ORDER BY o.order_date DESC, o.id DESC, i.id ASC"
);
```

- [ ] **Step 4: Carry `cp_invoice_id` into the `$orders[$key]` array**

In `femi9/billing/company/cp-today-orders.php`, change the array literal at lines 73-86 from:

```php
        $orders[$key] = [
            'po_id'              => (int)$r['id'],
            'display_date'       => $r['order_date'],
            'status'             => $r['status'],
            'transfer_id'        => $r['transfer_id'],
            'cancel_reason'      => $r['cancel_reason'],
```

to:

```php
        $orders[$key] = [
            'po_id'              => (int)$r['id'],
            'display_date'       => $r['order_date'],
            'status'             => $r['status'],
            'transfer_id'        => $r['transfer_id'],
            'cp_invoice_id'      => $r['cp_invoice_id'],
            'cancel_reason'      => $r['cancel_reason'],
```

(leave the rest of the array literal, lines 79-86, unchanged)

- [ ] **Step 5: Add a "View Invoice" link on completed rows**

In `femi9/billing/company/cp-today-orders.php`, change the completed-status badge block at lines 340-348 from:

```php
                                            <td>
                                                <?php if ($o['status'] === 'completed'): ?>
                                                <span class="badge-completed">Completed</span>
                                                <?php elseif ($o['status'] === 'cancelled'): ?>
                                                <span class="badge-cancelled" <?=$o['cancel_reason'] ? 'title="' . htmlspecialchars($o['cancel_reason'], ENT_QUOTES) . '"' : ''?>>Cancelled</span>
                                                <?php else: ?>
                                                <span class="badge-waiting">Waiting</span>
                                                <?php endif; ?>
                                            </td>
```

to:

```php
                                            <td>
                                                <?php if ($o['status'] === 'completed'): ?>
                                                <span class="badge-completed">Completed</span>
                                                <?php if (!empty($o['cp_invoice_id'])): ?>
                                                <br><a href="cp-invoice-print.php?id=<?=base64_encode((string)$o['cp_invoice_id'])?>" target="_blank" style="font-size:11px;">View Invoice</a>
                                                <?php endif; ?>
                                                <?php elseif ($o['status'] === 'cancelled'): ?>
                                                <span class="badge-cancelled" <?=$o['cancel_reason'] ? 'title="' . htmlspecialchars($o['cancel_reason'], ENT_QUOTES) . '"' : ''?>>Cancelled</span>
                                                <?php else: ?>
                                                <span class="badge-waiting">Waiting</span>
                                                <?php endif; ?>
                                            </td>
```

- [ ] **Step 6: Verify PHP syntax of the modified file**

Run: `php -l "femi9/billing/company/cp-today-orders.php"`
Expected: `No syntax errors detected...`

- [ ] **Step 7: Manual verification in the browser**

Log in as a `company` user, navigate to `cp-today-orders.php`, approve a real waiting CP purchase order (or use a test one), confirm the redirect succeeds, then find the newly completed row and click "View Invoice" — confirm `cp-invoice-print.php` renders the invoice/delivery-note with the correct invoice number, items, quantities, and total, and that the Print button opens a print-preview popup.

- [ ] **Step 8: Commit**

```bash
git add femi9/billing/company/cp-invoice-print.php femi9/billing/company/cp-today-orders.php
git commit -m "Add CP invoice print page and link it from the CP PO queue"
```

---

## Self-Review Notes (already applied above)

- **Spec coverage:** new `cp_invoices`/`cp_invoice_items`/`cp_inv_sequence` schema (Task 2, Task 1) — covered; `CpInvoiceNumberService` mirroring `TpInvoiceNumberService` (Task 1) — covered; invoice creation spliced into `cp-po-action.php`'s existing transaction (Task 3) — covered; single-document invoice-cum-delivery-note print with signature block, no GST framing (Task 5, Task 6) — covered; `channel_partner_purchase_orders.cp_invoice_id` link column (Task 2, used in Task 3/6) — covered; explicit non-goals (no separate DN table/number/print, no TP changes, no partial shipments, legacy `delivery_note` untouched) — nothing in this plan touches those areas, consistent with the spec.
- **Placeholder scan:** no TBD/TODO; the `bind_param` type string in Task 3 (`"siissdisssssssis"`, 16 chars for 16 placeholders) is derived once via an inline comment and used identically in the real edit (Step 4) and the test harness (Step 7).
- **Type consistency:** `cpInvoiceNextNumber()` signature (Task 1) matches its calls in Task 3 and test harnesses exactly. `load_cp_invoice_data()`'s return shape (Task 4) matches what `render_cp_invoice_html()` (Task 5) and `cp-invoice-print.php` (Task 6) consume. `cpEnsureInvoiceTables()` (Task 2) is called with the same signature everywhere it's used (Task 3, all test harnesses).
