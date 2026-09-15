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
$product = $db_conn->query("SELECT id, productName, mrp, gst, gst_type, hsn FROM products WHERE gst > 0 AND mrp > 0 LIMIT 1")->fetch_assoc();
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
assertTrue(strpos($html, 'SGST (') !== false, "single-rate transfer's SGST row DOES include a bracketed percentage");
assertTrue(strpos($html, 'CGST (') !== false, "single-rate transfer's CGST row DOES include a bracketed percentage");
assertTrue(strpos($html, 'IGST') === false, "never renders an IGST row (matches TP's template exactly)");
assertTrue(strpos($html, 'Discount') === false && strpos($html, 'Courier') === false, "never renders Discount or Courier rows (neither concept exists for a transfer)");
assertTrue(strpos($html, htmlspecialchars($data['result_Godown']['gname'])) !== false, "renders the godown (seller) name");
assertTrue(strpos($html, 'Seal and Signature') !== false, "renders a signature block");

$db_conn->rollback();

// --- Scenario: gst=0 product -> "Bill of Supply" heading instead of "Tax Invoice" ---
$db_conn->begin_transaction();
$zero_gst_product = $db_conn->query("SELECT id, productName, mrp, gst, gst_type, hsn FROM products WHERE gst = 0 AND mrp > 0 LIMIT 1")->fetch_assoc();
if ($zero_gst_product && $godown && $cp) {
    $db_conn->query("INSERT INTO pl_godown_transfers (transfer_type, godown_id, cp_id, transfer_date, ref_number, note, created_by) VALUES ('godown_to_location', {$godown['id']}, {$cp['id']}, CURDATE(), 'TEST-HTML-BOS', 'test', 'harness')");
    $bos_transfer_id = $db_conn->insert_id;
    $db_conn->query("INSERT INTO pl_godown_transfer_items (transfer_id, product_id, quantity) VALUES ($bos_transfer_id, {$zero_gst_product['id']}, 2)");

    $bos_data = load_transfer_invoice_data($db_conn, $bos_transfer_id);
    $bos_html = render_transfer_invoice_html($bos_data, $bos_data['has_carton_data']);

    assertTrue(strpos($bos_html, 'Bill of Supply') !== false, "renders 'Bill of Supply' heading for a gst=0 (non-GST-liable) transfer");
    assertTrue(strpos($bos_html, 'Tax Invoice') === false, "does NOT render 'Tax Invoice' heading for a gst=0 transfer");
} else {
    echo "SKIP: no product with gst=0 and mrp>0 found in dev DB — skipping Bill of Supply heading assertion\n";
}
$db_conn->rollback();

// --- Scenario: location_id destination (no cp_id) -> shows location name, no GSTIN label for buyer ---
$db_conn->begin_transaction();
$location = $db_conn->query("SELECT id, name FROM partner_location_nodes LIMIT 1")->fetch_assoc();
if ($location && $godown && $product) {
    $db_conn->query("INSERT INTO pl_godown_transfers (transfer_type, godown_id, location_id, transfer_date, ref_number, note, created_by) VALUES ('godown_to_location', {$godown['id']}, {$location['id']}, CURDATE(), 'TEST-HTML-LOC', 'test', 'harness')");
    $loc_transfer_id = $db_conn->insert_id;
    $db_conn->query("INSERT INTO pl_godown_transfer_items (transfer_id, product_id, quantity) VALUES ($loc_transfer_id, {$product['id']}, 2)");

    $loc_data = load_transfer_invoice_data($db_conn, $loc_transfer_id);
    $loc_html = render_transfer_invoice_html($loc_data, $loc_data['has_carton_data']);

    assertTrue($loc_data['result_Invoice_Details']['is_cp_buyer'] === false, "location-destination transfer is NOT flagged as a CP buyer");
    assertTrue(strpos($loc_html, htmlspecialchars($location['name'])) !== false, "renders the partner_location_nodes location's name as the buyer");
    // The seller (godown) section always prints "GSTIN/UIN :" regardless of
    // buyer type, so a blanket absence-of-"GSTIN" check would be wrong. The
    // buyer-side GSTIN line only prints "GSTIN: ..." (no "/UIN") and only
    // when buyer_gstin is non-empty (TransferInvoiceHtml.php line ~126) — so
    // for a location buyer (no GSTIN column) exactly one "GSTIN" occurrence
    // (the seller's) should appear, not two.
    assertTrue(substr_count($loc_html, 'GSTIN') === 1, "does NOT render a buyer GSTIN label for a location buyer (only the seller's GSTIN/UIN appears, not a second buyer-side GSTIN line)");
} else {
    echo "SKIP: no partner_location_nodes row found in dev DB — skipping location-buyer (no GSTIN) assertion\n";
}
$db_conn->rollback();

// --- Scenario: mixed GST-rate transfer -> SGST/CGST labels suppress the
// percentage, but the HSN-wise per-row percentages stay correct ---
// Prefer two real distinct-rate products if the dev DB has them; otherwise
// insert two temporary product rows (same rolled-back transaction) with
// distinct HSNs AND distinct rates, so this scenario is never silently
// skipped and genuinely exercises the true branch end-to-end. (A same-HSN
// different-rate pairing would also be a valid test of has_mixed_gst_rates
// itself, but would break this test's own HSN-wise-table assertions below,
// which expect two separate HSN rows — so this fixture deliberately keeps
// the two temp products on different HSNs.)
$db_conn->begin_transaction();
$gst_rates = $db_conn->query("SELECT DISTINCT gst FROM products WHERE gst > 0 AND mrp > 0")->fetch_all(MYSQLI_ASSOC);
if (count($gst_rates) >= 2 && $godown && $cp) {
    $rate_a = $gst_rates[0]['gst'];
    $rate_b = $gst_rates[1]['gst'];
    $product_a = $db_conn->query("SELECT id, hsn, gst FROM products WHERE gst = $rate_a AND mrp > 0 LIMIT 1")->fetch_assoc();
    $product_b = $db_conn->query("SELECT id, hsn, gst FROM products WHERE gst = $rate_b AND mrp > 0 LIMIT 1")->fetch_assoc();
} elseif ($godown && $cp) {
    $template = $db_conn->query("SELECT * FROM products LIMIT 1")->fetch_assoc();
    unset($template['id']);
    $esc = fn($v) => $v === null ? 'NULL' : "'" . $db_conn->real_escape_string($v) . "'";

    $cols_a = $template; $cols_a['productName'] = 'TEST HTML MIXED GST A'; $cols_a['gst'] = 5; $cols_a['mrp'] = 100; $cols_a['hsn'] = 'TESTHSNA';
    $col_names = implode(',', array_keys($cols_a));
    $db_conn->query("INSERT INTO products ($col_names) VALUES (" . implode(',', array_map($esc, array_values($cols_a))) . ")");
    $product_a = ['id' => $db_conn->insert_id, 'hsn' => $cols_a['hsn'], 'gst' => $cols_a['gst']];

    $cols_b = $template; $cols_b['productName'] = 'TEST HTML MIXED GST B'; $cols_b['gst'] = 18; $cols_b['mrp'] = 100; $cols_b['hsn'] = 'TESTHSNB';
    $db_conn->query("INSERT INTO products ($col_names) VALUES (" . implode(',', array_map($esc, array_values($cols_b))) . ")");
    $product_b = ['id' => $db_conn->insert_id, 'hsn' => $cols_b['hsn'], 'gst' => $cols_b['gst']];
}

if (isset($product_a) && isset($product_b)) {
    $db_conn->query("INSERT INTO pl_godown_transfers (transfer_type, godown_id, cp_id, transfer_date, ref_number, note, created_by) VALUES ('godown_to_location', {$godown['id']}, {$cp['id']}, CURDATE(), 'TEST-HTML-MIXEDGST', 'test', 'harness')");
    $mixed_transfer_id = $db_conn->insert_id;
    $db_conn->query("INSERT INTO pl_godown_transfer_items (transfer_id, product_id, quantity) VALUES ($mixed_transfer_id, {$product_a['id']}, 1)");
    $db_conn->query("INSERT INTO pl_godown_transfer_items (transfer_id, product_id, quantity) VALUES ($mixed_transfer_id, {$product_b['id']}, 1)");

    $mixed_data = load_transfer_invoice_data($db_conn, $mixed_transfer_id);
    $mixed_html = render_transfer_invoice_html($mixed_data, $mixed_data['has_carton_data']);

    assertTrue($mixed_data['has_mixed_gst_rates'] === true, "data layer reports has_mixed_gst_rates true for a two-distinct-rate transfer");
    assertTrue(preg_match('/SGST\s*\(/', $mixed_html) !== 1, "mixed-rate transfer's SGST label does NOT include a bracketed percentage");
    assertTrue(preg_match('/CGST\s*\(/', $mixed_html) !== 1, "mixed-rate transfer's CGST label does NOT include a bracketed percentage");
    assertTrue(strpos($mixed_html, '<i>SGST</i>') !== false, "mixed-rate transfer still renders a plain 'SGST' label");
    assertTrue(strpos($mixed_html, '<i>CGST</i>') !== false, "mixed-rate transfer still renders a plain 'CGST' label");
    // The HSN-wise summary table's per-row percentages are computed per-HSN
    // (independent of has_mixed_gst_rates) and must remain correct.
    assertTrue(strpos($mixed_html, fmt_gst_pct($product_a['gst'] / 2) . '%') !== false, "HSN-wise table still shows product A's correct per-HSN rate");
    assertTrue(strpos($mixed_html, fmt_gst_pct($product_b['gst'] / 2) . '%') !== false, "HSN-wise table still shows product B's correct per-HSN rate");
} else {
    echo "SKIP: no godown/CP available to build the mixed-GST-rate transfer fixture\n";
}
$db_conn->rollback();

echo "All TransferInvoiceHtml tests passed.\n";
