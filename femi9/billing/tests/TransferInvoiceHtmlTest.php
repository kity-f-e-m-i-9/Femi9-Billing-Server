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
assertTrue(strpos($html, 'IGST') === false, "never renders an IGST row (matches TP's template exactly)");
assertTrue(strpos($html, 'Discount') === false && strpos($html, 'Courier') === false, "never renders Discount or Courier rows (neither concept exists for a transfer)");
assertTrue(strpos($html, htmlspecialchars($data['result_Godown']['gname'])) !== false, "renders the godown (seller) name");
assertTrue(strpos($html, 'Seal and Signature') !== false, "renders a signature block");

$db_conn->rollback();
echo "All TransferInvoiceHtml tests passed.\n";
