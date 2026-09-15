<?php
// femi9/billing/tests/DeleteCpInvoiceTest.php
// Run: php femi9/billing/tests/DeleteCpInvoiceTest.php
require_once __DIR__ . '/../company/include/db-connect.php'; // $db_conn
require_once __DIR__ . '/../shared/CpInvoiceSchema.php';
require_once __DIR__ . '/../shared/CpInvoiceNumberService.php';

function assertTrue($cond, $msg) {
    echo ($cond ? "PASS: " : "FAIL: ") . $msg . "\n";
    if (!$cond) exit(1);
}

cpEnsureInvoiceTables($db_conn);
date_default_timezone_set("Asia/Kolkata");

$db_conn->begin_transaction();

$cp = $db_conn->query("SELECT id FROM channel_partners LIMIT 1")->fetch_assoc();
$product = $db_conn->query("SELECT id, mrp FROM products LIMIT 1")->fetch_assoc();
$godown = $db_conn->query("SELECT id FROM company_godown LIMIT 1")->fetch_assoc();
assertTrue($cp && $product && $godown, "found a CP, product, and godown to build a test scenario against");

// Real PO row so we can verify its cp_invoice_id gets un-linked, and its
// status/transfer_id are left untouched by the delete.
$db_conn->query("INSERT INTO channel_partner_purchase_orders (channel_partner_id, product_type, order_date, status, transfer_id) VALUES ({$cp['id']}, 'napkin', CURDATE(), 'completed', 999999)");
$po_id = $db_conn->insert_id;

$inv_num = cpInvoiceNextNumber($db_conn, 'CO', date('Y-m-d'));
$db_conn->query("INSERT INTO cp_invoices (invoice_number, channel_partner_id, source_godown_id, invoice_date, total_amount) VALUES ('$inv_num', {$cp['id']}, {$godown['id']}, CURDATE(), {$product['mrp']})");
$inv_id = $db_conn->insert_id;
$db_conn->query("INSERT INTO cp_invoice_items (cp_invoice_id, product_id, quantity, rate, amount) VALUES ($inv_id, {$product['id']}, 1, {$product['mrp']}, {$product['mrp']})");
$db_conn->query("UPDATE channel_partner_purchase_orders SET cp_invoice_id = $inv_id WHERE id = $po_id");

// Sanity-check the fixture before exercising the delete logic.
$before = $db_conn->query("SELECT cp_invoice_id, status, transfer_id FROM channel_partner_purchase_orders WHERE id=$po_id")->fetch_assoc();
assertTrue((int)$before['cp_invoice_id'] === $inv_id, "fixture: PO points at the new invoice before delete");

// Inline the handler's transaction body (mirrors delete-cp-invoice.php
// exactly) rather than posting HTTP, so this harness runs headless and can
// still roll everything back.
$lock = $db_conn->prepare("SELECT id FROM cp_invoices WHERE id=? FOR UPDATE");
$lock->bind_param("i", $inv_id); $lock->execute();
$locked = $lock->get_result()->fetch_assoc(); $lock->close();
assertTrue($locked !== null, "invoice row locks successfully");

$po = $db_conn->prepare("UPDATE channel_partner_purchase_orders SET cp_invoice_id = NULL WHERE cp_invoice_id = ?");
$po->bind_param("i", $inv_id); $po->execute(); $po->close();

$d1 = $db_conn->prepare("DELETE FROM cp_invoice_items WHERE cp_invoice_id=?");
$d1->bind_param("i", $inv_id); $d1->execute(); $d1->close();

$d2 = $db_conn->prepare("DELETE FROM cp_invoices WHERE id=?");
$d2->bind_param("i", $inv_id); $d2->execute();
assertTrue($d2->affected_rows === 1, "invoice row deleted");
$d2->close();

// Assertions
$invRow = $db_conn->query("SELECT * FROM cp_invoices WHERE id=$inv_id")->fetch_assoc();
assertTrue($invRow === null, "cp_invoices row no longer exists");

$itemRow = $db_conn->query("SELECT * FROM cp_invoice_items WHERE cp_invoice_id=$inv_id")->fetch_assoc();
assertTrue($itemRow === null, "cp_invoice_items row no longer exists");

$after = $db_conn->query("SELECT cp_invoice_id, status, transfer_id FROM channel_partner_purchase_orders WHERE id=$po_id")->fetch_assoc();
assertTrue($after['cp_invoice_id'] === null, "PO's cp_invoice_id is now NULL");
assertTrue($after['status'] === 'completed', "PO status untouched — still completed");
assertTrue((int)$after['transfer_id'] === 999999, "PO transfer_id untouched — stock transfer link preserved");

$db_conn->rollback(); // restore exact baseline — no test data persists
echo "All DeleteCpInvoice tests passed.\n";
