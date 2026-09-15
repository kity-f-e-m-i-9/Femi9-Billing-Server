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
