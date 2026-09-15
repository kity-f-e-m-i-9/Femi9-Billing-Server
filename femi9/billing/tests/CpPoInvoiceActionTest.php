<?php
// femi9/billing/tests/CpPoInvoiceActionTest.php
// Run: php femi9/billing/tests/CpPoInvoiceActionTest.php
date_default_timezone_set("Asia/Kolkata");
require_once __DIR__ . '/../company/include/db-connect.php'; // provides $db_conn
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

// Fetch the PO row the way cp-po-action.php's expanded SELECT does, so this
// harness exercises the same delivery-address/product_type fields.
$poStmt = $db_conn->prepare("SELECT id, channel_partner_id, status, product_type,
    use_default_delivery_address, custom_delivery_line1, custom_delivery_line2,
    custom_delivery_city, custom_delivery_district, custom_delivery_state,
    custom_delivery_country, custom_delivery_pincode
    FROM channel_partner_purchase_orders WHERE id = ? LIMIT 1");
$poStmt->bind_param("i", $po_id);
$poStmt->execute();
$po = $poStmt->get_result()->fetch_assoc();
$poStmt->close();

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
$use_default = (int)($po['use_default_delivery_address'] ?? 1);
$product_type = $po['product_type'] ?? 'napkin';

$ih = $db_conn->prepare("INSERT INTO cp_invoices
    (invoice_number, channel_partner_id, source_godown_id, product_type, invoice_date,
     total_amount, use_default_delivery_address, custom_delivery_line1, custom_delivery_line2,
     custom_delivery_city, custom_delivery_district, custom_delivery_state,
     custom_delivery_country, custom_delivery_pincode, transfer_id, created_by, created_by_user_type)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'company')");
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
