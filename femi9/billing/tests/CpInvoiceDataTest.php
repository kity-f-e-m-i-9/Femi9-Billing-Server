<?php
// femi9/billing/tests/CpInvoiceDataTest.php
// Run: php femi9/billing/tests/CpInvoiceDataTest.php
require_once __DIR__ . '/../company/include/db-connect.php'; // $db_conn
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
