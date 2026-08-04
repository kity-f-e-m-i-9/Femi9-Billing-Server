<?php
ob_start();
include("checksession.php");
error_reporting(0);
require_once __DIR__ . '/../shared/TpAdvanceService.php';
require_once __DIR__ . '/../shared/TpInvoiceNumberService.php';
require_once __DIR__ . '/include/GodownAccess.php';

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: manage-tp-invoices?error=unauthorized"); exit;
}
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: manage-tp-invoices"); exit;
}
if (($_POST['action'] ?? '') !== 'insert-tp-invoice') {
    header("Location: manage-tp-invoices"); exit;
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function getCpQty(mysqli $db, int $cp_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM channel_partner_stock WHERE channel_partner_id=? AND product_id=?");
    $s->bind_param("ii", $cp_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}

function lockAndGetCpQty(mysqli $db, int $cp_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM channel_partner_stock WHERE channel_partner_id=? AND product_id=? FOR UPDATE");
    $s->bind_param("ii", $cp_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}

function debitCp(mysqli $db, int $cp_id, int $pid, int $qty): void {
    $s = $db->prepare("UPDATE channel_partner_stock SET closing_qty=closing_qty-? WHERE channel_partner_id=? AND product_id=?");
    $s->bind_param("iii", $qty, $cp_id, $pid); $s->execute(); $s->close();
}

function insertCpLedger(mysqli $db, int $cp_id, int $pid, int $qty, int $before, int $after, string $inv_num, string $by): void {
    $action = 'transfer_out'; $ref_type = 'tp_invoice'; $note = '';
    $s = $db->prepare("INSERT INTO channel_partner_stock_ledger (channel_partner_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param("iisiiissss", $cp_id, $pid, $action, $qty, $before, $after, $ref_type, $inv_num, $note, $by);
    $s->execute(); $s->close();
}

function getGodownQtyForTp(mysqli $db, int $godown_id, int $pid): int {
    $uid = (string)$godown_id;
    $s = $db->prepare("SELECT closing_qty FROM stock WHERE user_type='company' AND user_id=? AND product_id=?");
    $s->bind_param("si", $uid, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}

function lockAndGetGodownQtyForTp(mysqli $db, int $godown_id, int $pid): int {
    $uid = (string)$godown_id;
    $s = $db->prepare("SELECT closing_qty FROM stock WHERE user_type='company' AND user_id=? AND product_id=? FOR UPDATE");
    $s->bind_param("si", $uid, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}

function debitGodownForTp(mysqli $db, int $godown_id, int $pid, int $qty): void {
    $uid = (string)$godown_id;
    // Counted as a sale (not an internal transfer) — an "Add TP Invoice" is a
    // billed transaction to the TP, so it belongs in sales_qty alongside the
    // company's other channel-partner invoices, matching overstock_datewise.php.
    $s = $db->prepare("UPDATE stock SET sales_qty=sales_qty+?, closing_qty=closing_qty-? WHERE user_type='company' AND user_id=? AND product_id=?");
    $s->bind_param("iisi", $qty, $qty, $uid, $pid); $s->execute(); $s->close();
}

function insertGodownLedgerForTp(mysqli $db, int $godown_id, int $pid, int $qty, int $before, int $after, string $inv_num, string $by): void {
    $uid = (string)$godown_id; $utype = 'company'; $action = 'transfer_out'; $ref_type = 'transfer'; $note = '';
    $s = $db->prepare("INSERT INTO stock_ledger (product_id,user_type,user_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param("isssiiissss", $pid, $utype, $uid, $action, $qty, $before, $after, $ref_type, $inv_num, $note, $by);
    $s->execute(); $s->close();
}

function getTpQty(mysqli $db, int $tp_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
    $s->bind_param("ii", $tp_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}

function creditTp(mysqli $db, int $tp_id, int $pid, int $qty): void {
    $s = $db->prepare("INSERT INTO territory_partner_stock (territory_partner_id,product_id,input_qty,closing_qty) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE input_qty=input_qty+VALUES(input_qty), closing_qty=closing_qty+VALUES(input_qty)");
    $s->bind_param("iiii", $tp_id, $pid, $qty, $qty); $s->execute(); $s->close();
}

function insertTpLedger(mysqli $db, int $tp_id, int $pid, int $qty, int $before, int $after, string $inv_num, string $by): void {
    $action = 'credit'; $ref_type = 'tp_invoice'; $note = '';
    $s = $db->prepare("INSERT INTO territory_partner_stock_ledger (territory_partner_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param("iisiiissss", $tp_id, $pid, $action, $qty, $before, $after, $ref_type, $inv_num, $note, $by);
    $s->execute(); $s->close();
}

// ── Helpers: advance payment ───────────────────────────────────────────────────

function getTpAdvanceBalance(mysqli $db, int $tp_id, int $godown_id = 0): float {
    if ($godown_id > 0) {
        $s = $db->prepare("SELECT COALESCE(SUM(balance_amount),0) AS bal FROM tp_advance_payments WHERE territory_partner_id=? AND company_id=? AND balance_amount>0 AND status!='fully_adjusted'");
        $s->bind_param("ii", $tp_id, $godown_id);
    } else {
        $s = $db->prepare("SELECT COALESCE(SUM(balance_amount),0) AS bal FROM tp_advance_payments WHERE territory_partner_id=? AND balance_amount>0 AND status!='fully_adjusted'");
        $s->bind_param("i", $tp_id);
    }
    $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return round((float)$r['bal'], 2);
}

function deductTpAdvance(mysqli $db, int $tp_id, float $required, string $inv_num, int $godown_id = 0, int $tp_invoice_id = 0): void {
    tpAdvanceDeduct($db, $tp_invoice_id, $inv_num, $tp_id, $required, $godown_id);
}

// ── Schema migration (runs once, safe to repeat) ──────────────────────────────
$col = $db_conn->query("SHOW COLUMNS FROM tp_invoices LIKE 'courier_charges'");
if ($col && $col->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN courier_charges DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER invoice_date");
}

// Per-product Disc(%)/Disc(₹) on the invoice line items — see
// db_migrations/2026_07_20_tp_invoice_items_discount.sql
$colD = $db_conn->query("SHOW COLUMNS FROM tp_invoice_items LIKE 'discount_amount'");
if ($colD && $colD->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_invoice_items ADD COLUMN discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER amount");
    $db_conn->query("ALTER TABLE tp_invoice_items ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_percentage");
}

// Per-order delivery address override, carried from tp_purchase_orders — see
// db_migrations/2026_08_04_tp_po_delivery_address.sql
$colDel = $db_conn->query("SHOW COLUMNS FROM tp_invoices LIKE 'use_default_delivery_address'");
if ($colDel && $colDel->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN use_default_delivery_address TINYINT(1) NOT NULL DEFAULT 1 AFTER total_amount");
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN custom_delivery_line1 VARCHAR(255) NULL AFTER use_default_delivery_address");
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN custom_delivery_line2 VARCHAR(255) NULL AFTER custom_delivery_line1");
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN custom_delivery_city VARCHAR(100) NULL AFTER custom_delivery_line2");
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN custom_delivery_district VARCHAR(100) NULL AFTER custom_delivery_city");
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN custom_delivery_state VARCHAR(100) NULL AFTER custom_delivery_district");
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN custom_delivery_country VARCHAR(100) NULL AFTER custom_delivery_state");
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN custom_delivery_pincode VARCHAR(20) NULL AFTER custom_delivery_country");
}

// ── Input & validation ─────────────────────────────────────────────────────────

$po_id            = (int)($_POST['po_id'] ?? 0);
$tp_id            = (int)($_POST['tp_id'] ?? 0);
$source_loc_id    = (int)($_POST['source_location_id'] ?? 0) ?: null;
$source_cp_id     = (int)($_POST['source_cp_id'] ?? 0);
$source_godown_id = (int)($_POST['source_godown_id'] ?? 0);
$invoice_date     = trim($_POST['invoice_date'] ?? date('Y-m-d'));
$courier_charges  = round((float)($_POST['courier_charges'] ?? 0), 2);
if ($courier_charges < 0) $courier_charges = 0;
// No invoice-level discount — discounting happens per product line only (disc_pct/disc_amt below).
$discount_amount  = 0.00;
$created_by       = $_SESSION['LOGIN_USER'] ?? '';

$raw_pids      = $_POST['product_id']              ?? [];
$raw_qtys      = $_POST['qty']                     ?? [];
$raw_rates     = $_POST['rate']                    ?? [];
$raw_disc_pcts = $_POST['item_discount_percentage'] ?? [];
$raw_disc_amts = $_POST['item_discount_amount']     ?? [];

$use_godown = ($source_godown_id > 0 && !$source_cp_id);

if (!$tp_id || (!$source_cp_id && !$source_godown_id) || empty($raw_pids)) {
    header("Location: add-tp-invoice?error=missing"); exit;
}

if ($use_godown && !is_godown_allowed($db_conn, $source_godown_id)) {
    header("Location: add-tp-invoice?error=unauthorized"); exit;
}

// If a source_location_id was submitted, verify it still exists — clear to NULL if deleted
if ($source_loc_id) {
    $lv = $db_conn->prepare("SELECT id FROM partner_location_nodes WHERE id=? LIMIT 1");
    $lv->bind_param("i", $source_loc_id); $lv->execute();
    if ($lv->get_result()->num_rows === 0) $source_loc_id = null;
    $lv->close();
}

// Build and deduplicate line items
$items = []; $seen = []; $item_errors = [];
foreach ($raw_pids as $i => $rpid) {
    $pid  = (int)$rpid;
    $qty  = (int)($raw_qtys[$i]  ?? 0);
    $rate = round((float)($raw_rates[$i] ?? 0), 2);
    if ($pid < 1) continue;
    if ($qty < 1)  { $item_errors[] = "product_$pid:qty"; continue; }
    if ($rate < 0) { $item_errors[] = "product_$pid:rate"; continue; }
    if (isset($seen[$pid])) { $item_errors[] = "product_$pid:duplicate"; continue; }
    $seen[$pid] = true;
    $amount   = round($qty * $rate, 2);
    $disc_pct = round((float)($raw_disc_pcts[$i] ?? 0), 2);
    $disc_amt = round((float)($raw_disc_amts[$i] ?? 0), 2);
    if ($disc_amt < 0) $disc_amt = 0;
    if ($disc_amt > $amount) $disc_amt = $amount;
    $items[] = ['pid' => $pid, 'qty' => $qty, 'rate' => $rate, 'amount' => $amount, 'disc_pct' => $disc_pct, 'disc_amt' => $disc_amt];
}

if (!empty($item_errors)) {
    header("Location: add-tp-invoice?error=invalid_items&details=" . urlencode(implode(',', $item_errors))); exit;
}
if (empty($items)) {
    header("Location: add-tp-invoice?error=noproducts"); exit;
}

// Gross subtotal minus each line's own discount — no invoice-level discount.
$gross_subtotal       = round(array_sum(array_column($items, 'amount')), 2);
$item_discount_total  = round(array_sum(array_column($items, 'disc_amt')), 2);
$net_amount    = round($gross_subtotal - $item_discount_total, 2);
if ($net_amount < 0) $net_amount = 0;
$invoice_total = round($net_amount + $courier_charges, 2);

// Gate: TP must have been through the input stock setup at least once
$stk_init = $db_conn->prepare("SELECT stock_initialized FROM territory_partners WHERE id=? AND is_active=1 LIMIT 1");
$stk_init->bind_param("i", $tp_id); $stk_init->execute();
$stk_row = $stk_init->get_result()->fetch_assoc(); $stk_init->close();
if (!$stk_row || !(int)$stk_row['stock_initialized']) {
    header("Location: add-tp-invoice?error=no_input_stock"); exit;
}

// Pre-validate advance balance (courier charges collected separately, not from advance)
$avail_balance = getTpAdvanceBalance($db_conn, $tp_id, $use_godown ? $source_godown_id : 0);
if ($avail_balance < $net_amount) {
    header("Location: add-tp-invoice?error=nobalance&need=" . urlencode(inr_format($net_amount, 2)) . "&have=" . urlencode(inr_format($avail_balance, 2))); exit;
}

// Pre-validate stock (fast fail before transaction)
foreach ($items as $item) {
    $avail = $use_godown
        ? getGodownQtyForTp($db_conn, $source_godown_id, $item['pid'])
        : getCpQty($db_conn, $source_cp_id, $item['pid']);
    if ($item['qty'] > $avail) {
        header("Location: add-tp-invoice?error=insufficient&pid={$item['pid']}"); exit;
    }
}

// Delivery address for this invoice — inherited from the originating
// purchase order's own selection (default TP address vs. a typed one-off
// address), if this invoice was raised from tp-today-orders.php. Invoices
// created without a PO keep the default (falls back to the TP's registered
// delivery address at print time).
$useDefaultDelivery = 1;
$customDeliveryLine1 = $customDeliveryLine2 = $customDeliveryCity = $customDeliveryDistrict = $customDeliveryState = $customDeliveryCountry = $customDeliveryPincode = null;
if ($po_id > 0) {
    $poDelStmt = $db_conn->prepare(
        "SELECT use_default_delivery_address, custom_delivery_line1, custom_delivery_line2, custom_delivery_city,
                custom_delivery_district, custom_delivery_state, custom_delivery_country, custom_delivery_pincode
         FROM tp_purchase_orders WHERE id=? AND territory_partner_id=?"
    );
    $poDelStmt->bind_param("ii", $po_id, $tp_id);
    $poDelStmt->execute();
    $poDelRow = $poDelStmt->get_result()->fetch_assoc();
    $poDelStmt->close();
    if ($poDelRow) {
        $useDefaultDelivery    = (int)$poDelRow['use_default_delivery_address'];
        $customDeliveryLine1    = $poDelRow['custom_delivery_line1'];
        $customDeliveryLine2    = $poDelRow['custom_delivery_line2'];
        $customDeliveryCity     = $poDelRow['custom_delivery_city'];
        $customDeliveryDistrict = $poDelRow['custom_delivery_district'];
        $customDeliveryState    = $poDelRow['custom_delivery_state'];
        $customDeliveryCountry  = $poDelRow['custom_delivery_country'];
        $customDeliveryPincode  = $poDelRow['custom_delivery_pincode'];
    }
}

// ── Transaction ────────────────────────────────────────────────────────────────

$db_conn->begin_transaction();
try {
    // Independent per-login invoice number series (not a connected/shared counter)
    $inv_num = tpInvoiceNextNumber($db_conn, 'CO', $invoice_date);

    // Invoice header
    // created_by_user_type is set explicitly here — leaving it out left every
    // company-created invoice with a blank value (its column default),
    // silently excluded by any report/query that filters on
    // created_by_user_type='company' (e.g. overview-report1.php's Sales
    // Report), even though it was already relied on elsewhere in this file
    // ($tpinv_source_sql-style callers) and by super-stockist's own insert.
    $created_by_user_type = 'company';
    $s = $db_conn->prepare(
        "INSERT INTO tp_invoices
            (invoice_number,territory_partner_id,source_location_id,source_cp_id,source_godown_id,invoice_date,
             courier_charges,discount_amount,total_amount,created_by,created_by_user_type,
             use_default_delivery_address,custom_delivery_line1,custom_delivery_line2,custom_delivery_city,
             custom_delivery_district,custom_delivery_state,custom_delivery_country,custom_delivery_pincode)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $s->bind_param(
        "siiiisdddssisssssss", $inv_num, $tp_id, $source_loc_id, $source_cp_id, $source_godown_id, $invoice_date,
        $courier_charges, $discount_amount, $invoice_total, $created_by, $created_by_user_type,
        $useDefaultDelivery, $customDeliveryLine1, $customDeliveryLine2, $customDeliveryCity,
        $customDeliveryDistrict, $customDeliveryState, $customDeliveryCountry, $customDeliveryPincode
    );
    $s->execute();
    $invoice_id = $db_conn->insert_id;
    $s->close();

    // Line items + stock movements
    $s_item = $db_conn->prepare("INSERT INTO tp_invoice_items (tp_invoice_id,product_id,quantity,rate,amount,discount_percentage,discount_amount) VALUES (?,?,?,?,?,?,?)");
    foreach ($items as $item) {
        // Re-check inside transaction with row lock to prevent race condition
        if ($use_godown) {
            $src_before = lockAndGetGodownQtyForTp($db_conn, $source_godown_id, $item['pid']);
            if ($item['qty'] > $src_before) throw new Exception("Insufficient godown stock for product {$item['pid']}");
            $src_after = $src_before - $item['qty'];
            debitGodownForTp($db_conn, $source_godown_id, $item['pid'], $item['qty']);
            insertGodownLedgerForTp($db_conn, $source_godown_id, $item['pid'], $item['qty'], $src_before, $src_after, $inv_num, $created_by);
        } else {
            $src_before = lockAndGetCpQty($db_conn, $source_cp_id, $item['pid']);
            if ($item['qty'] > $src_before) throw new Exception("Insufficient CP stock for product {$item['pid']}");
            $src_after = $src_before - $item['qty'];
            debitCp($db_conn, $source_cp_id, $item['pid'], $item['qty']);
            insertCpLedger($db_conn, $source_cp_id, $item['pid'], $item['qty'], $src_before, $src_after, $inv_num, $created_by);
        }

        // Credit TP
        $tp_before = getTpQty($db_conn, $tp_id, $item['pid']);
        $tp_after  = $tp_before + $item['qty'];
        creditTp($db_conn, $tp_id, $item['pid'], $item['qty']);
        insertTpLedger($db_conn, $tp_id, $item['pid'], $item['qty'], $tp_before, $tp_after, $inv_num, $created_by);

        // Invoice line
        $s_item->bind_param("iiidddd", $invoice_id, $item['pid'], $item['qty'], $item['rate'], $item['amount'], $item['disc_pct'], $item['disc_amt']);
        $s_item->execute();
    }
    $s_item->close();

    // Deduct net amount (after discount) from advance; courier is collected separately via receipt
    deductTpAdvance($db_conn, $tp_id, $net_amount, $inv_num, $use_godown ? $source_godown_id : 0, $invoice_id);

    // Mark the originating purchase order (if this invoice was raised from
    // tp-today-orders.php's "Invoice" button) as completed and link it.
    if ($po_id > 0) {
        $s_po = $db_conn->prepare("UPDATE tp_purchase_orders SET status='completed', tp_invoice_id=? WHERE id=? AND territory_partner_id=? AND status='waiting'");
        $s_po->bind_param("iii", $invoice_id, $po_id, $tp_id);
        $s_po->execute();
        $s_po->close();
    }

    $db_conn->commit();
    header("Location: manage-tp-invoices?success=1&inv=" . urlencode($inv_num)); exit;

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("[TP Invoice] Transaction failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    header("Location: add-tp-invoice?error=db&msg=" . urlencode(substr($e->getMessage(), 0, 100))); exit;
}
