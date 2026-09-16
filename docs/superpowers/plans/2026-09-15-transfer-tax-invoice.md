# Internal Transfer Receipt to Tax Invoice Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `company/pl-godown-transfer-print.php` (the internal stock-transfer print page) render a full GST tax invoice — CGST/SGST split, HSN-wise summary, bank details, amount-in-words, signature block — matching `company/tp-invoice-print.php`'s template exactly, computing tax on transfer line items priced from live `products.mrp` (since `pl_godown_transfer_items` has no stored rate).

**Architecture:** Mirror TP's three-file split exactly: a new `shared/TransferInvoiceData.php` (data loading + GST computation, modeled directly on `shared/TpInvoiceData.php`) feeding a new `shared/TransferInvoiceHtml.php` (pure rendering, modeled directly on `shared/TpInvoiceHtml.php`), with `company/pl-godown-transfer-print.php` becoming a thin controller like `company/tp-invoice-print.php`.

**Tech Stack:** PHP (mysqli, prepared statements), existing house HTML/CSS invoice template markup (reused verbatim from TP's template), no new DB schema.

**Spec:** `docs/superpowers/specs/2026-09-15-transfer-tax-invoice-design.md`

## Global Constraints

- Rate for every line = `products.mrp` looked up fresh at print time — `pl_godown_transfer_items` has no rate/amount column and none is added (spec: "Why this needs new logic, not just a template swap").
- GST split is CGST + SGST only (GST% ÷ 2 each) — never IGST. This matches TP's existing template exactly; TP has no IGST code path at all (spec: "GST computation").
- Heading is literally "Tax Invoice" (or "Bill of Supply" when total GST is zero) — exact text match to TP, not a distinguishing label (spec: "GST computation", confirmed by user).
- No discount, no courier-charge rows — neither concept exists for a transfer; those template sections (present, conditional in TP's markup) simply never render here (spec: "GST computation").
- Buyer is `COALESCE(channel_partners row via cp_id, partner_location_nodes row via location_id)` — the same mutually-exclusive-destination pattern already used in `manage-pl-godown-transfers.php` and `get-transfer-items.php` (spec: "Buyer/seller data"). `partner_location_nodes` has no GSTIN/address columns — a location-only buyer renders without that detail.
- `render_transfer_invoice_html()`'s CSS/table structure/HTML classes must match `render_tp_invoice_html()` byte-for-byte except for label text differences (spec: "New files").
- No change to `manage-pl-godown-transfers.php`'s existing CP-invoice-link routing, no change to `pl_godown_transfer_items` schema, no IGST support, no change to `cp-invoice-print.php` (spec: "Explicitly out of scope").

---

## Task 1: `TransferInvoiceData.php` — data loading and GST computation

**Files:**
- Create: `femi9/billing/shared/TransferInvoiceData.php`
- Test: `femi9/billing/tests/TransferInvoiceDataTest.php`

**Interfaces:**
- Consumes: `femi9/billing/shared/number-format-helpers.php`'s `inr_format($number, $decimals=2)` and `number_to_words_inr($amount)` (already exist, used exactly as `TpInvoiceData.php` uses them); `godown_finance_filter_sql($db_conn, $alias)` from `include/GodownAccess.php`.
- Produces: `function load_transfer_invoice_data(mysqli $db_conn, int $transfer_id): ?array` — returns `null` for a nonexistent transfer id, otherwise an array with these keys (mirroring `TpInvoiceData.php`'s return shape): `result_Invoice_Details` (header row: `transfer_id`, `ref_number`, `transfer_date`, `created_by`, `created_at`, buyer fields `buyer_name`/`buyer_gstin`/`buyer_mobile`/`buyer_address_parts` — an array of already-`array_filter`ed address lines, `is_cp_buyer` bool), `result_Godown` (seller row: `gname`, `address_line1`, `address_line2`, `gstin`, `state`, `state_code`, `contact`, `email`, `logo`, `acname`, `acnumber`, `bankname`, `branchname`, `ifsc`, `upinumber`), `invoice_items` (list with `productName`, `hsn`, `gst_percentage`, `gst_type`, `mrp`, `quantity`, `packs_per_carton`, plus computed `taxable_value`, `gst_amount`, `taxable_rate`, `taxable_rate_incl`, `carton_display`), `TotalAMount123`, `Totalquantity123`, `totalgstamount`, `hsn_totals`, `hsn_gst_totals`, `hsn_gst_pct`, `__inv_gst_pct`, `TotalCartons123`, `has_carton_data`, `grand_total`, `has_gst_product`, `invoice_heading`, `result` (amount-in-words), `TAXresult` (tax-amount-in-words), `Currency_symbol`, `Currency_Name`.

- [ ] **Step 1: Write `TransferInvoiceData.php`**

```php
<?php
/**
 * Loads and computes everything render_transfer_invoice_html()
 * (TransferInvoiceHtml.php) needs for one internal stock transfer, rendered
 * as a full GST tax invoice — DB queries, GST computation, HSN totals,
 * carton breakdown, amount-in-words. Mirrors TpInvoiceData.php's shape and
 * computation exactly (see docs/superpowers/specs/2026-09-15-transfer-tax-invoice-design.md),
 * with one structural difference: pl_godown_transfer_items has no stored
 * rate/amount/discount, so every line's rate is looked up fresh from
 * products.mrp at print time instead of being read from a stored invoice
 * line. There is also no discount or courier-charge concept for a transfer,
 * so those sections of TP's template are simply never populated here.
 *
 * Returns null if the transfer id doesn't resolve to a real transfer.
 */

require_once __DIR__ . '/number-format-helpers.php';
require_once __DIR__ . '/../company/include/GodownAccess.php';

if (!function_exists('fmt_gst_pct')) {
    // Trims a rate like 1.50 down to "1.5" or 9.00 down to "9" — CGST/SGST
    // is always exactly half the item's GST% which is often a non-whole
    // number (e.g. 3% GST -> 1.5% + 1.5%). Same helper TpInvoiceData.php
    // defines, guarded the same way so both can be required in the same
    // request without a redeclaration fatal.
    function fmt_gst_pct($v) {
        return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    }
}

function load_transfer_invoice_data(mysqli $db_conn, int $transfer_id): ?array {
    // Transfer header + seller (godown) + buyer (CP or location — mutually
    // exclusive per row, same pattern already fixed in
    // pl-godown-transfer-print.php's own header query).
    $stmt = $db_conn->prepare("
        SELECT t.id AS transfer_id, t.ref_number, t.transfer_date, t.created_by, t.created_at,
               g.gname, g.address_line1, g.address_line2, g.gstin, g.state, g.state_code,
               g.contact, g.email, g.logo, g.acname, g.acnumber, g.bankname, g.branchname,
               g.ifsc, g.upinumber,
               cp.id AS cp_id, cp.name AS cp_name, cp.gstin AS cp_gstin, cp.mobile AS cp_mobile,
               cp.branch_line1 AS cp_branch_line1, cp.branch_line2 AS cp_branch_line2,
               cp.branch_city AS cp_branch_city, cp.branch_district AS cp_branch_district,
               cp.branch_state AS cp_branch_state, cp.branch_country AS cp_branch_country,
               cp.branch_pincode AS cp_branch_pincode,
               pln.name AS location_name
        FROM pl_godown_transfers t
        JOIN company_godown g ON g.id = t.godown_id AND (" . godown_finance_filter_sql($db_conn, 'g') . ")
        LEFT JOIN channel_partners cp ON cp.id = t.cp_id
        LEFT JOIN partner_location_nodes pln ON pln.id = t.location_id
        WHERE t.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $transfer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    $is_cp_buyer = !empty($row['cp_id']);
    if ($is_cp_buyer) {
        $buyer_name  = $row['cp_name'];
        $buyer_gstin = $row['cp_gstin'];
        $buyer_mobile = $row['cp_mobile'];
        $buyer_address_parts = array_filter([
            $row['cp_branch_line1'],
            $row['cp_branch_line2'],
            implode(', ', array_filter([$row['cp_branch_city'], $row['cp_branch_district']])),
            implode(', ', array_filter([$row['cp_branch_state'], $row['cp_branch_country']])),
            !empty($row['cp_branch_pincode']) ? 'Pincode: ' . $row['cp_branch_pincode'] : '',
        ]);
    } else {
        $buyer_name  = $row['location_name'];
        $buyer_gstin = null;
        $buyer_mobile = null;
        $buyer_address_parts = [];
    }

    $result_Invoice_Details = [
        'transfer_id'         => $row['transfer_id'],
        'ref_number'          => $row['ref_number'],
        'transfer_date'       => $row['transfer_date'],
        'created_by'          => $row['created_by'],
        'created_at'          => $row['created_at'],
        'buyer_name'          => $buyer_name,
        'buyer_gstin'         => $buyer_gstin,
        'buyer_mobile'        => $buyer_mobile,
        'buyer_address_parts' => $buyer_address_parts,
        'is_cp_buyer'         => $is_cp_buyer,
    ];

    $result_Godown = [
        'gname'         => $row['gname'],
        'address_line1' => $row['address_line1'],
        'address_line2' => $row['address_line2'],
        'gstin'         => $row['gstin'],
        'state'         => $row['state'],
        'state_code'    => $row['state_code'],
        'contact'       => $row['contact'],
        'email'         => $row['email'],
        'logo'          => $row['logo'],
        'acname'        => $row['acname'],
        'acnumber'      => $row['acnumber'],
        'bankname'      => $row['bankname'],
        'branchname'    => $row['branchname'],
        'ifsc'          => $row['ifsc'],
        'upinumber'     => $row['upinumber'],
    ];

    // Line items — rate is always the product's CURRENT mrp, since
    // pl_godown_transfer_items stores no rate of its own.
    $stmt2 = $db_conn->prepare("
        SELECT ti.quantity,
               p.productName, p.hsn, p.gst AS gst_percentage, p.gst_type, p.mrp, p.packs_per_carton
        FROM pl_godown_transfer_items ti
        JOIN products p ON p.id = ti.product_id
        WHERE ti.transfer_id = ?
        ORDER BY ti.id
    ");
    $stmt2->bind_param("i", $transfer_id);
    $stmt2->execute();
    $invoice_items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();

    // Totals — identical computation to TpInvoiceData.php's loop, with
    // "amount" (which TP reads from its stored invoice line) replaced by
    // mrp * quantity, since a transfer has no stored line amount.
    $TotalAMount123   = 0;
    $Totalquantity123 = 0;
    $totalgstamount   = 0;
    $hsn_totals       = [];
    $hsn_gst_totals   = [];
    $hsn_gst_pct      = [];
    $__inv_gst_pct    = 0;
    foreach ($invoice_items as &$item) {
        $qty           = (int)$item['quantity'];
        $mrp           = (float)$item['mrp'];
        $gross_amount  = $mrp * $qty;
        $gst_pct       = (int)$item['gst_percentage'];
        $gst_type      = $item['gst_type'] ?? 'exclusive';

        if ($gst_type === 'inclusive' && $gst_pct > 0) {
            $gross_taxable_value = $gross_amount * 100 / (100 + $gst_pct);
            $taxable_value       = $gross_taxable_value; // no discount to subtract for a transfer
            $gst_amount          = $gross_amount - $gross_taxable_value;
        } else {
            $gross_taxable_value = $gross_amount;
            $taxable_value       = $gross_amount;
            $gst_amount          = $gross_amount * $gst_pct / 100;
        }
        $item['taxable_value'] = $taxable_value;
        $item['gst_amount']    = $gst_amount;
        $item['taxable_rate']  = $qty > 0 ? $gross_taxable_value / $qty : 0;
        $item['taxable_rate_incl'] = $item['taxable_rate'] + ($gst_pct > 0 ? $item['taxable_rate'] * $gst_pct / 100 : 0);

        $TotalAMount123   += $taxable_value;
        $Totalquantity123 += $qty;
        $totalgstamount   += $gst_amount;
        $hsn = $item['hsn'] ?: '-';
        $hsn_totals[$hsn]     = ($hsn_totals[$hsn] ?? 0) + $taxable_value;
        $hsn_gst_totals[$hsn] = ($hsn_gst_totals[$hsn] ?? 0) + $gst_amount;
        $hsn_gst_pct[$hsn]    = $gst_pct;
        $__inv_gst_pct        = max($__inv_gst_pct, $gst_pct);
    }
    unset($item);

    // Carton breakdown — identical logic to TpInvoiceData.php.
    $TotalCartons123 = 0;
    $has_carton_data = false;
    foreach ($invoice_items as &$item) {
        $ppc = $item['packs_per_carton'];
        $item['carton_display'] = '—';
        if ($ppc !== null && $ppc !== '' && (int)$ppc > 0) {
            $has_carton_data = true;
            $ppc_int  = (int)$ppc;
            $qty      = (int)$item['quantity'];
            $cartons  = intdiv($qty, $ppc_int);
            $leftover = $qty % $ppc_int;
            $TotalCartons123 += $cartons;
            $item['carton_display'] = $cartons . ' ctn' . ($leftover > 0 ? ' + ' . $leftover . ' pack' . ($leftover > 1 ? 's' : '') : '');
        }
    }
    unset($item);

    $grand_total     = $TotalAMount123 + $totalgstamount;
    $has_gst_product = $totalgstamount > 0;
    $invoice_heading = $has_gst_product ? 'Tax Invoice' : 'Bill of Supply';

    $result    = number_to_words_inr($grand_total);
    $TAXresult = number_to_words_inr($totalgstamount);
    $TAXpaise = (int) round(($totalgstamount - floor($totalgstamount)) * 100);
    $TAXpaise_words = '';
    if ($TAXpaise > 0) {
        $words = ['0'=>'','1'=>'one','2'=>'two','3'=>'three','4'=>'four','5'=>'five','6'=>'six','7'=>'seven','8'=>'eight','9'=>'nine','10'=>'ten','11'=>'eleven','12'=>'twelve','13'=>'thirteen','14'=>'fourteen','15'=>'fifteen','16'=>'sixteen','17'=>'seventeen','18'=>'eighteen','19'=>'nineteen','20'=>'twenty','30'=>'thirty','40'=>'forty','50'=>'fifty','60'=>'sixty','70'=>'seventy','80'=>'eighty','90'=>'ninety'];
        $TAXpaise_words = ($TAXpaise < 21)
            ? $words[$TAXpaise]
            : trim($words[floor($TAXpaise / 10) * 10] . " " . $words[$TAXpaise % 10]);
    }
    if (trim($TAXresult) !== '' && $TAXpaise_words !== '') {
        $TAXresult = trim($TAXresult) . ' Rupees and ' . $TAXpaise_words . ' Paise';
    } elseif ($TAXpaise_words !== '') {
        $TAXresult = $TAXpaise_words . ' Paise';
    } elseif (trim($TAXresult) === '') {
        $TAXresult = 'Zero';
    }

    $Currency_symbol = "&#8377;";
    $Currency_Name   = "INR";

    return compact(
        'result_Invoice_Details', 'result_Godown', 'invoice_items',
        'TotalAMount123', 'Totalquantity123', 'totalgstamount',
        'hsn_totals', 'hsn_gst_totals', 'hsn_gst_pct', '__inv_gst_pct',
        'TotalCartons123', 'has_carton_data',
        'grand_total', 'has_gst_product', 'invoice_heading',
        'result', 'TAXresult', 'Currency_symbol', 'Currency_Name'
    );
}
```

- [ ] **Step 2: Write a CLI test harness against real transfer data in the dev DB**

```php
<?php
// femi9/billing/tests/TransferInvoiceDataTest.php
// Run: php femi9/billing/tests/TransferInvoiceDataTest.php
require_once __DIR__ . '/../company/include/db-connect.php'; // $db_conn
require_once __DIR__ . '/../shared/TransferInvoiceData.php';

function assertTrue($cond, $msg) {
    echo ($cond ? "PASS: " : "FAIL: ") . $msg . "\n";
    if (!$cond) exit(1);
}

$db_conn->begin_transaction();

$godown = $db_conn->query("SELECT id FROM company_godown LIMIT 1")->fetch_assoc();
$cp = $db_conn->query("SELECT id, name FROM channel_partners LIMIT 1")->fetch_assoc();
$product = $db_conn->query("SELECT id, mrp, gst, gst_type, hsn FROM products WHERE gst > 0 LIMIT 1")->fetch_assoc();
assertTrue($godown && $cp && $product, "found a godown, CP, and a GST-liable product to build a test transfer against");

// ── Scenario 1: CP-destination transfer ─────────────────────────────────
$db_conn->query("INSERT INTO pl_godown_transfers (transfer_type, godown_id, cp_id, transfer_date, ref_number, note, created_by) VALUES ('godown_to_location', {$godown['id']}, {$cp['id']}, CURDATE(), 'TEST-INV-REF', 'test', 'harness')");
$transfer_id = $db_conn->insert_id;
$qty = 5;
$db_conn->query("INSERT INTO pl_godown_transfer_items (transfer_id, product_id, quantity) VALUES ($transfer_id, {$product['id']}, $qty)");

$data = load_transfer_invoice_data($db_conn, $transfer_id);
assertTrue($data !== null, "load_transfer_invoice_data returns non-null for a real transfer");
assertTrue($data['result_Invoice_Details']['ref_number'] === 'TEST-INV-REF', "header has the correct ref_number");
assertTrue($data['result_Invoice_Details']['is_cp_buyer'] === true, "CP-destination transfer is flagged as a CP buyer");
assertTrue($data['result_Invoice_Details']['buyer_name'] === $cp['name'], "buyer_name matches the channel partner's name");
assertTrue(count($data['invoice_items']) === 1, "exactly one line item");
assertTrue((int)$data['invoice_items'][0]['quantity'] === $qty, "line item quantity matches");

$mrp = (float)$product['mrp'];
$gst_pct = (int)$product['gst'];
$expected_taxable = $product['gst_type'] === 'inclusive'
    ? ($mrp * $qty) * 100 / (100 + $gst_pct)
    : $mrp * $qty;
assertTrue(abs($data['TotalAMount123'] - $expected_taxable) < 0.01, "taxable total matches the expected MRP-based computation, got {$data['TotalAMount123']} expected $expected_taxable");
assertTrue($data['totalgstamount'] > 0, "GST amount is nonzero for a GST-liable product");
assertTrue(abs($data['grand_total'] - ($data['TotalAMount123'] + $data['totalgstamount'])) < 0.01, "grand_total = taxable total + GST amount");
assertTrue($data['invoice_heading'] === 'Tax Invoice', "heading is exactly 'Tax Invoice' for a GST-liable transfer");

// ── Scenario 2: location-destination transfer (no CP) ──────────────────
$loc = $db_conn->query("SELECT id, name FROM partner_location_nodes LIMIT 1")->fetch_assoc();
if ($loc) {
    $db_conn->query("INSERT INTO pl_godown_transfers (transfer_type, godown_id, location_id, transfer_date, ref_number, note, created_by) VALUES ('godown_to_location', {$godown['id']}, {$loc['id']}, CURDATE(), 'TEST-LOC-REF', 'test', 'harness')");
    $loc_transfer_id = $db_conn->insert_id;
    $db_conn->query("INSERT INTO pl_godown_transfer_items (transfer_id, product_id, quantity) VALUES ($loc_transfer_id, {$product['id']}, 2)");

    $loc_data = load_transfer_invoice_data($db_conn, $loc_transfer_id);
    assertTrue($loc_data['result_Invoice_Details']['is_cp_buyer'] === false, "location-destination transfer is NOT flagged as a CP buyer");
    assertTrue($loc_data['result_Invoice_Details']['buyer_name'] === $loc['name'], "buyer_name matches the location's name");
    assertTrue($loc_data['result_Invoice_Details']['buyer_gstin'] === null, "location buyer has no GSTIN (partner_location_nodes has no such column)");
} else {
    echo "SKIP: no partner_location_nodes row available to test the location-buyer branch\n";
}

// ── Scenario 3: nonexistent transfer id ─────────────────────────────────
$missing = load_transfer_invoice_data($db_conn, 999999999);
assertTrue($missing === null, "load_transfer_invoice_data returns null for a nonexistent id");

$db_conn->rollback(); // never persist test data
echo "All TransferInvoiceData tests passed.\n";
```

- [ ] **Step 3: Run the test harness and verify it passes**

Run: `php "femi9/billing/tests/TransferInvoiceDataTest.php"`
Expected: all PASS lines (or one SKIP if no `partner_location_nodes` row exists), then "All TransferInvoiceData tests passed."

- [ ] **Step 4: Verify PHP syntax**

Run: `php -l "femi9/billing/shared/TransferInvoiceData.php"`
Expected: `No syntax errors detected...`

- [ ] **Step 5: Commit**

```bash
git add femi9/billing/shared/TransferInvoiceData.php femi9/billing/tests/TransferInvoiceDataTest.php
git commit -m "Add TransferInvoiceData: GST computation for internal transfers, priced from live MRP"
```

---

## Task 2: `TransferInvoiceHtml.php` — rendering, mirroring TP's template exactly

**Files:**
- Create: `femi9/billing/shared/TransferInvoiceHtml.php`
- Test: `femi9/billing/tests/TransferInvoiceHtmlTest.php`

**Interfaces:**
- Consumes: the array shape returned by `load_transfer_invoice_data()` (Task 1): `result_Invoice_Details` (with `transfer_id`, `ref_number`, `transfer_date`, `created_by`, `created_at`, `buyer_name`, `buyer_gstin`, `buyer_mobile`, `buyer_address_parts`, `is_cp_buyer`), `result_Godown`, `invoice_items`, `TotalAMount123`, `Totalquantity123`, `totalgstamount`, `hsn_totals`, `hsn_gst_totals`, `hsn_gst_pct`, `__inv_gst_pct`, `TotalCartons123`, `has_carton_data`, `grand_total`, `has_gst_product`, `invoice_heading`, `result`, `TAXresult`, `Currency_symbol`, `Currency_Name`.
- Produces: `function render_transfer_invoice_html(array $ctx, bool $show_carton_cols): string` — returns a complete HTML fragment (not a full page), same contract as `render_tp_invoice_html()`.

- [ ] **Step 1: Write `TransferInvoiceHtml.php`**

This file is `shared/TpInvoiceHtml.php` (479 lines, already read in full during design) with these specific substitutions — copy TP's file structure and CSS verbatim, then apply:

1. Function signature: `render_transfer_invoice_html(array $ctx, bool $show_carton_cols): string` (drop the `$forPdf` parameter and every `<?php if ($forPdf): ?>` / `<?php if (!$forPdf): ?>` conditional block entirely — always emit the plain `@media print` block, since there is no PDF/WhatsApp-share variant of this page in scope).
2. The seller block (originally lines 148-175 of `TpInvoiceHtml.php`, the `cp_gst_enabled`-branching godown-vs-CP-seller-letterhead logic): **remove entirely** — a transfer's seller is always the godown, never a CP. Replace with the plain godown-letterhead branch only (TP's `else` branch, lines 168-174), unconditionally:

```php
<span id="cmpname"><?= htmlspecialchars($result_Godown['gname']); ?></span><br/>
<?= htmlspecialchars($result_Godown['address_line1']); ?><br/>
<?= htmlspecialchars($result_Godown['address_line2']); ?><br/>
<b>GSTIN/UIN :</b> <?= htmlspecialchars($result_Godown['gstin']); ?><br/>
<b>State Name</b> : <?= htmlspecialchars($result_Godown['state']); ?> <b>Code</b> : <?= htmlspecialchars($result_Godown['state_code']); ?><br/>
<b>Contact</b> : <?= htmlspecialchars($result_Godown['contact']); ?><br/>
<b>Email</b> : <?= htmlspecialchars($result_Godown['email']); ?>
```

Also keep the logo `<img>` block (TP's lines 148-150) but drop its `cp_gst_enabled` condition — show the logo whenever `$result_Godown['logo']` is set:

```php
<?php if (!empty($result_Godown['logo'])): ?>
<img src="<?= $result_Godown['logo']; ?>" style="width:150px;margin-right:5px;"/>
<?php endif; ?>
```

3. The "Consignee (Ship to)" / "Buyer (Bill to)" block (TP's lines 181-225, which builds `$delivery_parts`/`$branch_parts` from TP-specific delivery-address and branch-address columns): **replace** with a single buyer block using `result_Invoice_Details`'s `buyer_name`/`buyer_gstin`/`buyer_mobile`/`buyer_address_parts` (a transfer has one destination, not a separate ship-to vs bill-to):

```php
<p class="cusdetaiis">
Consignee &amp; Buyer:<br/>
<?= htmlspecialchars($result_Invoice_Details['buyer_name']); ?><br/>
<?php if (!empty($result_Invoice_Details['buyer_gstin'])): ?>GSTIN: <?= htmlspecialchars($result_Invoice_Details['buyer_gstin']); ?><br/><?php endif; ?>
<?php if (!empty($result_Invoice_Details['buyer_mobile'])): ?>Mobile:&nbsp;<?= htmlspecialchars($result_Invoice_Details['buyer_mobile']); ?><br/><?php endif; ?>
<?= implode('<br/>', array_map('htmlspecialchars', $result_Invoice_Details['buyer_address_parts'])); ?>
</p>
```

(Remove TP's second, near-duplicate "Buyer (Bill to)" `<p>` block entirely — a transfer has only one destination party, so there is no separate ship-to/bill-to distinction to show twice.)

4. The right-hand meta table (TP's lines 228-258, `#second_topvl`): keep the table structure and every row from TP's third row onward unchanged verbatim (`Buyer's Order No.`/`Dated`, `Dispatch Doc No.`/`Delivery Note Date`, `Dispatched through`/`Destination` — all stay as empty/blank cells exactly as TP already renders them, since none of these concepts apply to a transfer either and TP already leaves them blank). Only TP's first two rows change: the first row's labels stay the same shape (`Invoice #` / `Invoice Date:`) but read from transfer fields instead of invoice fields, and the second row (originally `Delivery Note` / `Mode/Terms of Payment`) is replaced with `Mode/Terms of Payment` / `Reference No. & Date` — dropping the `Delivery Note` label specifically, since a transfer's document IS the delivery note in spirit (it's an internal stock movement record), making a blank "Delivery Note" field inside it redundant:

```php
<tr id="border_nbottom">
<td>Invoice #<br/><b><?= htmlspecialchars($result_Invoice_Details['ref_number']); ?></b></td>
<td>Invoice Date:<br/><b><?= date("d M Y", strtotime($result_Invoice_Details['transfer_date'])); ?></b></td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Mode/Terms of Payment<br/>&nbsp;</td>
<td>Reference No. &amp; Date<br/>&nbsp;</td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Buyer's Order No.<br/>&nbsp;</td>
<td>Dated<br/>&nbsp;</td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Dispatch Doc No.<br/>&nbsp;</td>
<td>Delivery Note Date<br/>&nbsp;</td>
</tr>
<tr id="border_nbottom" valign="top">
<td height="50">Dispatched through<br/>&nbsp;</td>
<td>Destination<br/>&nbsp;</td>
</tr>
```

Also keep TP's `<p id="shiippingaddress">Terms of Delivery<br/>&nbsp;</p>` line right after this table, unchanged.

Drop the `tpResolveProductType`/`tpProductTypeLabel` badge span from the first row entirely — transfers have no product-type concept, so that `<span>` (and its preceding `<?php $_invType = ...; ?>` line) is simply omitted.

5. Item table (TP's lines 262-375): same columns as TP EXCEPT no `Disc` column (a transfer has no discount concept) — that is, in order: Sl No., Description of Goods, HSN/SAC, Quantity, [Packs/Carton, Cartons — only when `$show_carton_cols`], MRP, Rate (Excl. Tax), Rate (Incl. Tax), per, GST(%), Amount. That's 11 columns without carton columns, 13 with them (TP has 12/14 — one fewer throughout, from dropping Disc). Use this exact markup for the header row, per-item row, and totals row (carton `<td>`s included only when `$show_carton_cols` is true, exactly as TP already conditions them):

```php
<table class="item_list">
<tr id="bordervl">
<td>Sl No.</td>
<td>Description of Goods</td>
<td id="rightlaign">HSN/SAC</td>
<td id="rightlaign">Quantity</td>
<?php if ($show_carton_cols): ?>
<td id="rightlaign">Packs/Carton</td>
<td id="rightlaign">Cartons</td>
<?php endif; ?>
<td id="rightlaign">MRP</td>
<td id="rightlaign">Rate (Excl. Tax)</td>
<td id="rightlaign">Rate (Incl. Tax)</td>
<td id="rightlaign">per</td>
<td id="rightlaign">GST(%)</td>
<td id="rightlaign">Amount</td>
</tr>

<?php $invno = 0; foreach ($invoice_items as $item):
    $invno++;
    $qty           = (int)$item['quantity'];
    $gst_pct       = (int)$item['gst_percentage'];
    $mrp           = (float)$item['mrp'];
    $taxable_value = $item['taxable_value'];
    $rate          = (float)$item['taxable_rate'];
    $rate_incl     = (float)$item['taxable_rate_incl'];
?>
<tr>
<td><?= $invno; ?></td>
<td><b><?= htmlspecialchars($item['productName']); ?></b></td>
<td id="rightlaign"><?= htmlspecialchars($item['hsn']); ?></td>
<td id="rightlaign"><?= inr_format($qty, 0); ?> Packs</td>
<?php if ($show_carton_cols): ?>
<td id="rightlaign"><?= ($item['packs_per_carton'] !== null && $item['packs_per_carton'] !== '') ? inr_format((int)$item['packs_per_carton'], 0) : '—'; ?></td>
<td id="rightlaign"><?= $item['carton_display']; ?></td>
<?php endif; ?>
<td id="rightlaign"><?= inr_format($mrp, 2); ?></td>
<td id="rightlaign"><?= inr_format($rate, 2); ?></td>
<td id="rightlaign"><?= inr_format($rate_incl, 2); ?></td>
<td id="rightlaign">Packs</td>
<td id="rightlaign"><?= $gst_pct; ?>%</td>
<td id="rightlaign"><?= inr_format($taxable_value, 2); ?></td>
</tr>
<?php endforeach; ?>

<tr>
<td></td><td></td><td></td>
<td id="rightlaign"><b><?= inr_format($Totalquantity123, 0); ?> Packs</b></td>
<?php if ($show_carton_cols): ?>
<td></td>
<td id="rightlaign"><b><?= inr_format($TotalCartons123, 0); ?> ctn</b></td>
<?php endif; ?>
<td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= inr_format($TotalAMount123, 2); ?></b></td>
</tr>
```

TP's `<?php if ($discount_amount > 0): ?>` discount-total row block (its lines 328-336) is dropped entirely — this plan's data never sets a `$discount_amount` key, so there is no dead-variable reference to worry about; the block simply doesn't exist in this file.

6. SGST/CGST total rows: keep TP's computation and conditional exactly (`if ($totalgstamount > 0)`, `$SGST = inr_format($totalgstamount / 2, 2)`, `$CGST` the same, `$__half_pct = fmt_gst_pct($__inv_gst_pct / 2)`), but each row now has one fewer empty `<td>` (10 columns before the Amount `<td>` instead of TP's 11, from dropping Disc):

```php
<?php if ($totalgstamount > 0):
    $SGST = inr_format($totalgstamount / 2, 2);
    $CGST = inr_format($totalgstamount / 2, 2);
    $__half_pct = fmt_gst_pct($__inv_gst_pct / 2);
?>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>SGST (<?= $__half_pct; ?>%)</i></b></td>
<td></td><td></td>
<?php if ($show_carton_cols): ?><td></td><td></td><?php endif; ?>
<td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= $SGST; ?></b></td>
</tr>
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>CGST (<?= $__half_pct; ?>%)</i></b></td>
<td></td><td></td>
<?php if ($show_carton_cols): ?><td></td><td></td><?php endif; ?>
<td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= $CGST; ?></b></td>
</tr>
<?php endif; ?>
```

7. Courier-charges row (TP's lines 358-366): dropped entirely — no courier concept for a transfer.

8. Grand-total row, same one-fewer-column adjustment as points 5-6:

```php
<tr id="bottombordervl">
<td></td><td id="rightlaign"><b><i>Total</i></b></td>
<td></td><td></td>
<?php if ($show_carton_cols): ?><td></td><td></td><?php endif; ?>
<td></td><td></td><td></td><td></td><td></td>
<td id="rightlaign"><b><?= $Currency_symbol; ?>&nbsp;<?= inr_format($grand_total, 2); ?></b></td>
</tr>
</table>
```

9. "Amount Chargeable (in words)" block, HSN-wise summary table, "Tax Amount (in words)" + Declaration block, bank-details table, signature block, footer (TP's lines 378-475): keep every one of these verbatim, with one text change — TP's signature block says `"Territory Partner's Seal and Signature"`; change this to `"Received By: Seal and Signature"` (a transfer doesn't have a "Territory Partner," and this label is generic enough to fit either a CP or a location destination). Also drop the final `<div align="center">` block that shows `$result_Invoice_Details['cp_district']` (TP-specific field this data shape doesn't have) and the `"SUBJECT TO ERODE JURISDICTION"` line stays as-is (it's the company's own jurisdiction, applies here identically) — keep just:

```php
<div align="center">SUBJECT TO ERODE JURISDICTION</div>
<div align="center">This is a Computer Generated Invoice</div>
```

Assemble the complete file now, following TP's file exactly for every section not called out above (same `<style>` block minus the `$forPdf`-conditional CSS, same `.maincontainar` wrapper, same `#toptl` heading table using `$invoice_heading`, same item-table CSS classes `item_list`/`bordervl`/`rightlaign`/`bottombordervl`, same `#hsnsac`/`#sealsign` tables). The file's top docblock should explain what it mirrors and why (same rationale style as `TpInvoiceHtml.php`'s own docblock), for example:

```php
<?php
/**
 * Renders the #divToPrint markup for an internal stock transfer, rendered
 * as a full GST tax invoice. This is TpInvoiceHtml.php's render_tp_invoice_html()
 * template reused byte-for-byte for CSS/table structure, with TP-specific
 * concepts (Territory Partner ship-to/bill-to split, discount, courier
 * charges, product-type badge, PDF/WhatsApp variant) removed since none of
 * them apply to an internal transfer. See
 * docs/superpowers/specs/2026-09-15-transfer-tax-invoice-design.md for the
 * full rationale.
 *
 * All business logic (DB queries, GST computation, totals) stays in
 * TransferInvoiceData.php — this function only takes the already-computed
 * values and returns markup.
 */
function render_transfer_invoice_html(array $ctx, bool $show_carton_cols): string {
    extract($ctx, EXTR_SKIP);
    ob_start();
    ?>
    <!-- ... full markup per the substitutions above ... -->
    <?php
    return ob_get_clean();
}
```

- [ ] **Step 2: Write a CLI test harness that renders against real transfer data**

```php
<?php
// femi9/billing/tests/TransferInvoiceHtmlTest.php
// Run: php femi9/billing/tests/TransferInvoiceHtmlTest.php
require_once __DIR__ . '/../company/include/db-connect.php'; // $db_conn
require_once __DIR__ . '/../shared/TransferInvoiceData.php';
require_once __DIR__ . '/../shared/TransferInvoiceHtml.php';

function assertTrue($cond, $msg) {
    echo ($cond ? "PASS: " : "FAIL: ") . $msg . "\n";
    if (!$cond) exit(1);
}

$db_conn->begin_transaction();

$godown = $db_conn->query("SELECT id FROM company_godown LIMIT 1")->fetch_assoc();
$cp = $db_conn->query("SELECT id, name FROM channel_partners LIMIT 1")->fetch_assoc();
$product = $db_conn->query("SELECT id, productName, mrp, gst, gst_type, hsn FROM products WHERE gst > 0 LIMIT 1")->fetch_assoc();
assertTrue($godown && $cp && $product, "found a godown, CP, and a GST-liable product to build a test transfer against");

$db_conn->query("INSERT INTO pl_godown_transfers (transfer_type, godown_id, cp_id, transfer_date, ref_number, note, created_by) VALUES ('godown_to_location', {$godown['id']}, {$cp['id']}, CURDATE(), 'TEST-HTML-REF', 'test', 'harness')");
$transfer_id = $db_conn->insert_id;
$db_conn->query("INSERT INTO pl_godown_transfer_items (transfer_id, product_id, quantity) VALUES ($transfer_id, {$product['id']}, 3)");

$data = load_transfer_invoice_data($db_conn, $transfer_id);
$html = render_transfer_invoice_html($data, $data['has_carton_data']);

assertTrue(strpos($html, 'Tax Invoice') !== false, "renders the 'Tax Invoice' heading for a GST-liable transfer");
assertTrue(strpos($html, 'TEST-HTML-REF') !== false, "renders the transfer's ref_number as the Invoice #");
assertTrue(strpos($html, htmlspecialchars($cp['name'])) !== false, "renders the CP buyer's name");
assertTrue(strpos($html, htmlspecialchars($product['productName'])) !== false, "renders the line-item product name");
assertTrue(strpos($html, htmlspecialchars($product['hsn'])) !== false, "renders the line-item HSN code");
assertTrue(strpos($html, 'SGST') !== false && strpos($html, 'CGST') !== false, "renders both SGST and CGST rows for a GST-liable transfer");
assertTrue(strpos($html, 'IGST') === false, "never renders an IGST row (matches TP's template exactly)");
assertTrue(strpos($html, 'Discount') === false && strpos($html, 'Courier') === false, "never renders Discount or Courier rows (neither concept exists for a transfer)");
assertTrue(strpos($html, htmlspecialchars($data['result_Godown']['gname'])) !== false, "renders the godown (seller) name");
assertTrue(strpos($html, 'Seal and Signature') !== false, "renders a signature block");

$db_conn->rollback();
echo "All TransferInvoiceHtml tests passed.\n";
```

- [ ] **Step 3: Run the test harness and verify it passes**

Run: `php "femi9/billing/tests/TransferInvoiceHtmlTest.php"`
Expected: all PASS lines, then "All TransferInvoiceHtml tests passed."

- [ ] **Step 4: Verify PHP syntax**

Run: `php -l "femi9/billing/shared/TransferInvoiceHtml.php"`
Expected: `No syntax errors detected...`

- [ ] **Step 5: Commit**

```bash
git add femi9/billing/shared/TransferInvoiceHtml.php femi9/billing/tests/TransferInvoiceHtmlTest.php
git commit -m "Add TransferInvoiceHtml: renders internal transfers as a tax invoice matching TP's template"
```

---

## Task 3: Rewire `pl-godown-transfer-print.php` as a thin controller

**Files:**
- Modify: `femi9/billing/company/pl-godown-transfer-print.php` (currently 248 lines — the plain receipt built directly into this file, plus the header-query fix from an earlier commit)

**Interfaces:**
- Consumes: `load_transfer_invoice_data()` (Task 1), `render_transfer_invoice_html()` (Task 2).
- Produces: the same GET-accessible page at `company/pl-godown-transfer-print.php?id=<transfer_id>` (not base64-encoded — this page has always taken a plain integer id, unlike `cp-invoice-print.php`; do not change that contract, since `manage-pl-godown-transfers.php`'s JS already builds this URL with the raw transfer id).

- [ ] **Step 1: Replace the file's header-loading and rendering logic**

Replace the entire current contents of `femi9/billing/company/pl-godown-transfer-print.php` with a thin controller modeled on `company/tp-invoice-print.php`'s shape:

```php
<?php
include("checksession.php");
require_once("include/GodownAccess.php");
error_reporting(0);
include("config.php");
date_default_timezone_set("Asia/Kolkata");

require_once __DIR__ . '/../shared/TransferInvoiceData.php';
require_once __DIR__ . '/../shared/TransferInvoiceHtml.php';

$transfer_id = (int)($_GET['id'] ?? 0);
if ($transfer_id <= 0) { header("Location: manage-pl-godown-transfers"); exit; }

$invData = load_transfer_invoice_data($db_conn, $transfer_id);
if (!$invData) { header("Location: manage-pl-godown-transfers"); exit; }

$show_carton_cols = $invData['has_carton_data'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transfer Invoice <?php echo htmlspecialchars($invData['result_Invoice_Details']['ref_number']); ?> : <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Two+Tone|Material+Icons+Round|Material+Icons+Sharp" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png">
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

            <script>
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

            <table align="right" style="margin:10px 20px;">
                <tr>
                    <td><button type="button" onclick="PrintDiv();" class="btn btn-dark m-b-xs m-r-xs">
                        <i class="material-icons" style="font-size:16px;vertical-align:middle;">print</i> Print
                    </button></td>
                    <td><button type="button" onclick="window.location='manage-pl-godown-transfers';" class="btn btn-primary m-b-xs m-r-xs">
                        ← All Transfers
                    </button></td>
                </tr>
            </table>
            <div style="clear:both;"></div>

            <div id="divToPrint">
            <div id="divToPrintScroll">
<?php echo render_transfer_invoice_html($invData, $show_carton_cols); ?>
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

Run: `php -l "femi9/billing/company/pl-godown-transfer-print.php"`
Expected: `No syntax errors detected...`

- [ ] **Step 3: Verify against real data via a direct query trace**

Since this is a page-chrome-heavy file relying on session/login state, do the closest available headless check: confirm the controller's own logic (id parsing, `load_transfer_invoice_data()` call, redirect-on-null) is correct by tracing it with a small PHP CLI script against a real transfer id already known to exist in the dev DB — reuse a transfer id from Task 1's or Task 2's test fixtures pattern (insert one inside a transaction, call `load_transfer_invoice_data()` directly, confirm it returns non-null, confirm `render_transfer_invoice_html()` produces non-empty HTML containing the transfer's `ref_number`, then roll back). This is the same headless verification pattern used for `cp-invoice-print.php` earlier in this project.

- [ ] **Step 4: Manual verification in the browser**

Log in as a `company` user, navigate to `manage-pl-godown-transfers.php`, open a transfer that has no linked CP invoice (any existing transfer today, since none have one yet), click "View" then "Print Receipt" (still labeled that, since `manage-pl-godown-transfers.php`'s own button-label logic is unchanged by this plan) — confirm it now renders a full tax invoice (heading, GST breakdown, HSN table, bank details, signature block) instead of the old plain receipt, and confirm the Print button opens a print-preview popup with the same content.

- [ ] **Step 5: Commit**

```bash
git add femi9/billing/company/pl-godown-transfer-print.php
git commit -m "Rewire pl-godown-transfer-print.php as a thin controller rendering a tax invoice"
```

---

## Self-Review Notes (already applied above)

- **Spec coverage:** rate from live `products.mrp` (Task 1) — covered. CGST+SGST-only split, no IGST (Task 1, explicitly tested in Task 2's harness) — covered. "Tax Invoice"/"Bill of Supply" heading exact match (Task 1, tested) — covered. No discount/courier rows (Task 1 never sets those keys; Task 2 explicitly removes those template sections and tests their absence) — covered. Buyer via `COALESCE(channel_partners, partner_location_nodes)` with location-buyer having no GSTIN (Task 1, tested both branches) — covered. Byte-for-byte CSS/structure match to TP except labeled substitutions (Task 2, itemized substitution list) — covered. Thin-controller rewrite preserving the existing plain-integer `?id=` contract (Task 3) — covered. Out-of-scope items (no change to `manage-pl-godown-transfers.php`'s CP-invoice routing, no schema change, no IGST, no change to `cp-invoice-print.php`) — nothing in this plan touches those areas.
- **Placeholder scan:** no TBD/TODO; Task 2's step-by-step substitution instructions reference exact TP line numbers and full replacement code blocks rather than "similar to TP" hand-waving, since the implementer needs the literal text to assemble the file — this is deliberate specificity, not a shortcut.
- **Type consistency:** `load_transfer_invoice_data(mysqli $db_conn, int $transfer_id): ?array` (Task 1) matches its call in Task 3. `render_transfer_invoice_html(array $ctx, bool $show_carton_cols): string` (Task 2) matches its call in Task 3. The context-array keys Task 1 produces (`result_Invoice_Details`, `result_Godown`, `invoice_items`, totals, etc.) match exactly what Task 2's substitution instructions reference by name.
