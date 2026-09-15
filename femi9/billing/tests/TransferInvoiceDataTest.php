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
$product = $db_conn->query("SELECT id, mrp, gst, gst_type, hsn FROM products WHERE gst > 0 AND mrp > 0 LIMIT 1")->fetch_assoc();
assertTrue($godown && $cp && $product, "found a godown, CP, and a GST-liable product (with a positive MRP) to build a test transfer against");

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
assertTrue($data['invoice_heading'] === 'Delivery Slip', "heading is always exactly 'Delivery Slip', regardless of GST liability");

// dn_number is generated once (from the shared CPDN counter also used by CP
// invoices) and persisted onto the transfer row — re-loading the same
// transfer must return the identical number, not a freshly generated one.
assertTrue((bool)preg_match('#^CPDN/\d{2}-\d{2}/\d{3}$#', $data['result_Invoice_Details']['dn_number']), "dn_number matches CPDN/{fy}/{seq} format (3-digit padding, same as CP invoices), got {$data['result_Invoice_Details']['dn_number']}");
$data_reload = load_transfer_invoice_data($db_conn, $transfer_id);
assertTrue($data_reload['result_Invoice_Details']['dn_number'] === $data['result_Invoice_Details']['dn_number'], "reloading the same transfer returns the SAME dn_number, not a freshly generated one");

// A second, different transfer's first print must get a different number
// than the first transfer's (both drawn from the same counter, so this also
// confirms the counter actually advances between calls).
$db_conn->query("INSERT INTO pl_godown_transfers (transfer_type, godown_id, cp_id, transfer_date, ref_number, note, created_by) VALUES ('godown_to_location', {$godown['id']}, {$cp['id']}, CURDATE(), 'TEST-INV-REF-2', 'test', 'harness')");
$transfer_id_2 = $db_conn->insert_id;
$db_conn->query("INSERT INTO pl_godown_transfer_items (transfer_id, product_id, quantity) VALUES ($transfer_id_2, {$product['id']}, 1)");
$data_2 = load_transfer_invoice_data($db_conn, $transfer_id_2);
assertTrue($data_2['result_Invoice_Details']['dn_number'] !== $data['result_Invoice_Details']['dn_number'], "a different transfer's dn_number differs from the first transfer's (counter genuinely advances)");

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

// ── Scenario 4: transfer with neither cp_id nor location_id (no resolvable
// buyer) — should be treated like a nonexistent transfer, not silently
// render a blank buyer block. ────────────────────────────────────────────
$db_conn->query("INSERT INTO pl_godown_transfers (transfer_type, godown_id, transfer_date, ref_number, note, created_by) VALUES ('godown_to_location', {$godown['id']}, CURDATE(), 'TEST-NOBUYER-REF', 'test', 'harness')");
$nobuyer_transfer_id = $db_conn->insert_id;
$db_conn->query("INSERT INTO pl_godown_transfer_items (transfer_id, product_id, quantity) VALUES ($nobuyer_transfer_id, {$product['id']}, 1)");
$nobuyer_data = load_transfer_invoice_data($db_conn, $nobuyer_transfer_id);
assertTrue($nobuyer_data === null, "load_transfer_invoice_data returns null for a transfer with neither cp_id nor location_id set (no resolvable buyer)");

// ── Scenario 5: mixed GST-rate transfer -> has_mixed_gst_rates is true ──
// Prefer two real products at different rates if the dev DB happens to have
// them; otherwise insert two temporary product rows (inside this same
// rolled-back transaction) so the true branch of has_mixed_gst_rates is
// actually exercised end-to-end rather than skipped — a prior run of this
// harness found only one distinct non-zero GST rate in the dev DB and had
// to SKIP this scenario, leaving the logic verified only by code review.
$gst_rates = $db_conn->query("SELECT DISTINCT gst FROM products WHERE gst > 0 AND mrp > 0")->fetch_all(MYSQLI_ASSOC);
if (count($gst_rates) >= 2) {
    $rate_a = $gst_rates[0]['gst'];
    $rate_b = $gst_rates[1]['gst'];
    $product_a = $db_conn->query("SELECT id FROM products WHERE gst = $rate_a AND mrp > 0 LIMIT 1")->fetch_assoc();
    $product_b = $db_conn->query("SELECT id FROM products WHERE gst = $rate_b AND mrp > 0 LIMIT 1")->fetch_assoc();
} else {
    // Copy a real product's full row via SELECT * so every NOT NULL column
    // this table happens to have is satisfied, then override only the
    // columns this scenario cares about (name, gst, mrp) — safer than
    // hand-listing every required column and risking a missed one.
    $template = $db_conn->query("SELECT * FROM products LIMIT 1")->fetch_assoc();
    unset($template['id']);
    $cols_a = $template; $cols_a['productName'] = 'TEST MIXED GST PRODUCT A'; $cols_a['gst'] = 5; $cols_a['mrp'] = 100;
    $cols_b = $template; $cols_b['productName'] = 'TEST MIXED GST PRODUCT B'; $cols_b['gst'] = 18; $cols_b['mrp'] = 100;
    $col_names = implode(',', array_keys($cols_a));

    $vals_a = implode(',', array_map(fn($v) => $v === null ? 'NULL' : "'" . $db_conn->real_escape_string($v) . "'", array_values($cols_a)));
    $db_conn->query("INSERT INTO products ($col_names) VALUES ($vals_a)");
    $product_a = ['id' => $db_conn->insert_id];

    $vals_b = implode(',', array_map(fn($v) => $v === null ? 'NULL' : "'" . $db_conn->real_escape_string($v) . "'", array_values($cols_b)));
    $db_conn->query("INSERT INTO products ($col_names) VALUES ($vals_b)");
    $product_b = ['id' => $db_conn->insert_id];
}

$db_conn->query("INSERT INTO pl_godown_transfers (transfer_type, godown_id, cp_id, transfer_date, ref_number, note, created_by) VALUES ('godown_to_location', {$godown['id']}, {$cp['id']}, CURDATE(), 'TEST-MIXEDGST-REF', 'test', 'harness')");
$mixed_transfer_id = $db_conn->insert_id;
$db_conn->query("INSERT INTO pl_godown_transfer_items (transfer_id, product_id, quantity) VALUES ($mixed_transfer_id, {$product_a['id']}, 1)");
$db_conn->query("INSERT INTO pl_godown_transfer_items (transfer_id, product_id, quantity) VALUES ($mixed_transfer_id, {$product_b['id']}, 1)");

$mixed_data = load_transfer_invoice_data($db_conn, $mixed_transfer_id);
assertTrue($mixed_data['has_mixed_gst_rates'] === true, "has_mixed_gst_rates is true for a transfer with two distinct non-zero GST rates among its line items");

// ── Scenario 6 (control): single-rate transfer -> has_mixed_gst_rates false
$db_conn->query("INSERT INTO pl_godown_transfers (transfer_type, godown_id, cp_id, transfer_date, ref_number, note, created_by) VALUES ('godown_to_location', {$godown['id']}, {$cp['id']}, CURDATE(), 'TEST-SINGLEGST-REF', 'test', 'harness')");
$single_transfer_id = $db_conn->insert_id;
$db_conn->query("INSERT INTO pl_godown_transfer_items (transfer_id, product_id, quantity) VALUES ($single_transfer_id, {$product_a['id']}, 1)");
$single_data = load_transfer_invoice_data($db_conn, $single_transfer_id);
assertTrue($single_data['has_mixed_gst_rates'] === false, "has_mixed_gst_rates is false for a transfer whose line items share one GST rate");

$db_conn->rollback(); // never persist test data
echo "All TransferInvoiceData tests passed.\n";
