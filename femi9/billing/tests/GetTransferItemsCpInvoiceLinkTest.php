<?php
// femi9/billing/tests/GetTransferItemsCpInvoiceLinkTest.php
// Run: php femi9/billing/tests/GetTransferItemsCpInvoiceLinkTest.php
//
// Verifies get-transfer-items.php's query correctly surfaces the CP invoice
// id (and its base64 encoding) for a transfer that originated from an
// invoiced CP purchase order, and correctly omits it for a plain manual
// transfer — this is what manage-pl-godown-transfers.php's Print button
// uses to decide whether to link to cp-invoice-print.php or the plain
// pl-godown-transfer-print.php receipt.
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
$godown = $db_conn->query("SELECT id FROM company_godown LIMIT 1")->fetch_assoc();
$product = $db_conn->query("SELECT id, mrp FROM products LIMIT 1")->fetch_assoc();
assertTrue($cp && $godown && $product, "found a CP, godown, and product to build test scenarios against");

// ── Scenario 1: CP-order-originated transfer with a real invoice ──────────
$db_conn->query("INSERT INTO channel_partner_purchase_orders (channel_partner_id, product_type, order_date, status) VALUES ({$cp['id']}, 'napkin', CURDATE(), 'completed')");
$po_id = $db_conn->insert_id;

$db_conn->query("INSERT INTO pl_godown_transfers (transfer_type, godown_id, cp_id, transfer_date, ref_number, note, created_by, source_po_id) VALUES ('godown_to_location', {$godown['id']}, {$cp['id']}, CURDATE(), 'TEST-REF', 'test', 'harness', $po_id)");
$transfer_id = $db_conn->insert_id;

$inv_num = cpInvoiceNextNumber($db_conn, 'CO', date('Y-m-d'));
$db_conn->query("INSERT INTO cp_invoices (invoice_number, channel_partner_id, source_godown_id, invoice_date, total_amount, transfer_id) VALUES ('$inv_num', {$cp['id']}, {$godown['id']}, CURDATE(), {$product['mrp']}, $transfer_id)");
$inv_id = $db_conn->insert_id;
$db_conn->query("UPDATE channel_partner_purchase_orders SET cp_invoice_id = $inv_id, transfer_id = $transfer_id WHERE id = $po_id");

// Same query get-transfer-items.php runs (minus the godown_finance_filter_sql
// join condition, which is a session-dependent filter fragment, not part of
// what this test is verifying).
$stmt = $db_conn->prepare("
    SELECT t.id, po.cp_invoice_id
    FROM pl_godown_transfers t
    LEFT JOIN channel_partner_purchase_orders po ON po.id = t.source_po_id
    WHERE t.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $transfer_id);
$stmt->execute();
$transfer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$enc = !empty($transfer['cp_invoice_id']) ? base64_encode((string)$transfer['cp_invoice_id']) : null;

assertTrue((int)$transfer['cp_invoice_id'] === $inv_id, "CP-order-originated transfer resolves to the correct cp_invoice_id");
assertTrue($enc !== null && (int)base64_decode($enc) === $inv_id, "base64 encoding round-trips to the same invoice id cp-invoice-print.php would decode");

// ── Scenario 2: plain manual transfer (no source_po_id) ────────────────────
$db_conn->query("INSERT INTO pl_godown_transfers (transfer_type, godown_id, cp_id, transfer_date, ref_number, note, created_by) VALUES ('godown_to_location', {$godown['id']}, {$cp['id']}, CURDATE(), 'MANUAL-REF', 'manual test', 'harness')");
$manual_transfer_id = $db_conn->insert_id;

$stmt2 = $db_conn->prepare("
    SELECT t.id, po.cp_invoice_id
    FROM pl_godown_transfers t
    LEFT JOIN channel_partner_purchase_orders po ON po.id = t.source_po_id
    WHERE t.id = ?
    LIMIT 1
");
$stmt2->bind_param("i", $manual_transfer_id);
$stmt2->execute();
$manual = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

assertTrue(empty($manual['cp_invoice_id']), "manual transfer (no source_po_id) has no cp_invoice_id — falls back to the plain receipt print");

$db_conn->rollback(); // restore exact baseline — no test data persists
echo "All GetTransferItemsCpInvoiceLink tests passed.\n";
