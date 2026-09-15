<?php
// femi9/billing/tests/CpInvoiceHtmlTest.php
// Run: php femi9/billing/tests/CpInvoiceHtmlTest.php
require_once __DIR__ . '/../company/include/db-connect.php'; // $db_conn
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

$cp = $db_conn->query("SELECT id, name, cp_id, mobile, gstin, branch_line1, branch_line2, branch_city, branch_state, branch_pincode FROM channel_partners LIMIT 1")->fetch_assoc();
$product = $db_conn->query("SELECT id, mrp, productName FROM products LIMIT 1")->fetch_assoc();
assertTrue($cp && $product, "found a channel partner and a product to build a test invoice against");

// Create a test godown
$godown_result = $db_conn->query("SELECT id, gname FROM company_godown LIMIT 1");
$godown = $godown_result ? $godown_result->fetch_assoc() : null;
$godown_id = $godown ? $godown['id'] : null;
$expected_godown_name = $godown ? $godown['gname'] : null;

$inv_num = cpInvoiceNextNumber($db_conn, 'CO', date('Y-m-d'));
$query = "INSERT INTO cp_invoices (invoice_number, channel_partner_id, invoice_date, total_amount, source_godown_id, use_default_delivery_address)
          VALUES ('$inv_num', {$cp['id']}, CURDATE(), {$product['mrp']}, " . ($godown_id ? $godown_id : "NULL") . ", 1)";
$db_conn->query($query);
$inv_id = $db_conn->insert_id;
$db_conn->query("INSERT INTO cp_invoice_items (cp_invoice_id, product_id, quantity, rate, amount) VALUES ($inv_id, {$product['id']}, 1, {$product['mrp']}, {$product['mrp']})");

$data = load_cp_invoice_data($db_conn, $inv_id);
assertTrue($data !== null, "load_cp_invoice_data returned valid data");

$html = render_cp_invoice_html($data);

// Original assertions from the brief
assertTrue(strpos($html, 'TAX INVOICE CUM DELIVERY NOTE') !== false, "renders the combined invoice+DN heading");
assertTrue(strpos($html, htmlspecialchars($inv_num)) !== false, "renders the invoice number");
assertTrue(strpos($html, htmlspecialchars($product['productName'])) !== false, "renders the line-item product name");
assertTrue(strpos($html, "Receiver's Signature") !== false, "renders a goods-receipt signature block");

// Enhanced assertions to verify joined fields render correctly
assertTrue(strpos($html, htmlspecialchars($cp['name'])) !== false, "renders the CP name (joined field from channel_partners)");
assertTrue(strpos($html, htmlspecialchars($cp['cp_id'])) !== false, "renders the CP code (joined field from channel_partners)");
assertTrue(strpos($html, htmlspecialchars($cp['branch_line1'])) !== false, "renders the branch address line 1 (joined field)");
assertTrue(strpos($html, htmlspecialchars($cp['branch_city'])) !== false, "renders the branch city (joined field)");
assertTrue(strpos($html, htmlspecialchars($cp['branch_state'])) !== false, "renders the branch state (joined field)");

if ($expected_godown_name) {
    assertTrue(strpos($html, htmlspecialchars($expected_godown_name)) !== false, "renders the godown name (joined field from company_godown)");
} else {
    assertTrue(strpos($html, htmlspecialchars('-')) !== false, "renders '-' when godown is not set (null handling)");
}

$db_conn->rollback();
echo "All CpInvoiceHtml tests passed.\n";
