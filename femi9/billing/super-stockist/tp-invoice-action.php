<?php
ob_start();
include("checksession.php");
error_reporting(0);
require_once __DIR__ . '/../shared/TpAdvanceService.php';

if (($Login_user_TYPEvl ?? '') !== 'super_stockiest') {
    header("Location: manage-tp-invoices?error=unauthorized"); exit;
}
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: manage-tp-invoices"); exit;
}
if (($_POST['action'] ?? '') !== 'insert-tp-invoice') {
    header("Location: manage-tp-invoices"); exit;
}

// ── Helpers ─────────────────────────────────────────────────────────────────

function lockAndGetSsQty(mysqli $db, string $ss_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM stock WHERE user_type='super_stockiest' AND user_id=? AND product_id=? FOR UPDATE");
    $s->bind_param("si", $ss_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}

function debitSs(mysqli $db, string $ss_id, int $pid, int $qty): void {
    $s = $db->prepare("UPDATE stock SET sent_qty=sent_qty+?, closing_qty=closing_qty-?, updated_at=NOW() WHERE user_type='super_stockiest' AND user_id=? AND product_id=?");
    $s->bind_param("iisi", $qty, $qty, $ss_id, $pid); $s->execute(); $s->close();
}

function getSsQty(mysqli $db, string $ss_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM stock WHERE user_type='super_stockiest' AND user_id=? AND product_id=?");
    $s->bind_param("si", $ss_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}

function creditTp(mysqli $db, int $tp_id, int $pid, int $qty): void {
    $s = $db->prepare("INSERT INTO territory_partner_stock (territory_partner_id,product_id,input_qty,closing_qty) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE input_qty=input_qty+?, closing_qty=closing_qty+?");
    $s->bind_param("iiiiii", $tp_id, $pid, $qty, $qty, $qty, $qty); $s->execute(); $s->close();
}

function insertTpLedger(mysqli $db, int $tp_id, int $pid, int $qty, int $before, int $after, string $inv_num, string $by): void {
    $action = 'credit'; $ref_type = 'tp_invoice'; $note = '';
    $s = $db->prepare("INSERT INTO territory_partner_stock_ledger (territory_partner_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param("iisiiissss", $tp_id, $pid, $action, $qty, $before, $after, $ref_type, $inv_num, $note, $by);
    $s->execute(); $s->close();
}

function getTpAdvanceBalance(mysqli $db, int $tp_id): float {
    $s = $db->prepare("SELECT COALESCE(SUM(balance_amount),0) AS bal FROM tp_advance_payments WHERE territory_partner_id=? AND balance_amount>0 AND status!='fully_adjusted'");
    $s->bind_param("i", $tp_id); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return round((float)$r['bal'], 2);
}

function deductTpAdvance(mysqli $db, int $tp_id, float $required, string $inv_num, int $tp_invoice_id = 0): void {
    tpAdvanceDeduct($db, $tp_invoice_id, $inv_num, $tp_id, $required);
}

// ── Schema migration ──────────────────────────────────────────────────────────
$col = $db_conn->query("SHOW COLUMNS FROM tp_invoices LIKE 'courier_charges'");
if ($col && $col->num_rows === 0) {
    $db_conn->query("ALTER TABLE tp_invoices ADD COLUMN courier_charges DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER invoice_date");
}

// ── Input & validation ────────────────────────────────────────────────────────
$tp_id            = (int)($_POST['tp_id'] ?? 0);
$invoice_number   = trim($_POST['invoice_number'] ?? '');
$invoice_date     = trim($_POST['invoice_date'] ?? date('Y-m-d'));
$courier_charges  = round((float)($_POST['courier_charges'] ?? 0), 2);
if ($courier_charges < 0) $courier_charges = 0;
$discount_amount  = round((float)($_POST['discount_amount'] ?? 0), 2);
if ($discount_amount < 0) $discount_amount = 0;
$created_by       = $_SESSION['LOGIN_USER'] ?? '';
$ss_id            = $Login_user_IDvl;

$raw_pids  = $_POST['product_id'] ?? [];
$raw_qtys  = $_POST['qty']        ?? [];
$raw_rates = $_POST['rate']       ?? [];

if (!$tp_id || empty($raw_pids) || $invoice_number === '') {
    header("Location: add-tp-invoice?error=missing"); exit;
}

// Manual invoice number — must be unique across all TP invoices
$dupe_chk = $db_conn->prepare("SELECT id FROM tp_invoices WHERE invoice_number = ? LIMIT 1");
$dupe_chk->bind_param("s", $invoice_number);
$dupe_chk->execute();
if ($dupe_chk->get_result()->num_rows > 0) {
    $dupe_chk->close();
    header("Location: add-tp-invoice?error=duplicate&inv=" . urlencode($invoice_number)); exit;
}
$dupe_chk->close();

// Verify TP belongs to this SS
$tp_own = $db_conn->prepare("SELECT id FROM territory_partners WHERE id=? AND onboard_ss_id=? AND is_active=1");
$tp_own->bind_param("is", $tp_id, $ss_id);
$tp_own->execute();
if ($tp_own->get_result()->num_rows === 0) {
    $tp_own->close();
    header("Location: add-tp-invoice?error=unauthorized"); exit;
}
$tp_own->close();

// Build line items
$items = []; $seen = [];
foreach ($raw_pids as $i => $rpid) {
    $pid  = (int)$rpid;
    $qty  = (int)($raw_qtys[$i] ?? 0);
    $rate = round((float)($raw_rates[$i] ?? 0), 2);
    if ($pid < 1 || $qty < 1) continue;
    if (isset($seen[$pid])) continue;
    $seen[$pid] = true;
    $items[] = ['pid' => $pid, 'qty' => $qty, 'rate' => $rate, 'amount' => round($qty * $rate, 2)];
}

if (empty($items)) {
    header("Location: add-tp-invoice?error=noproducts"); exit;
}

$subtotal      = round(array_sum(array_column($items, 'amount')), 2);
$net_amount    = round($subtotal - $discount_amount, 2);
if ($net_amount < 0) $net_amount = 0;
$invoice_total = round($net_amount + $courier_charges, 2);

// Pre-validate advance balance
$avail_balance = getTpAdvanceBalance($db_conn, $tp_id);
if ($avail_balance < $net_amount) {
    header("Location: add-tp-invoice?error=nobalance&need=" . urlencode(inr_format($net_amount, 2)) . "&have=" . urlencode(inr_format($avail_balance, 2))); exit;
}

// Pre-validate SS stock
foreach ($items as $item) {
    $avail = getSsQty($db_conn, $ss_id, $item['pid']);
    if ($item['qty'] > $avail) {
        header("Location: add-tp-invoice?error=insufficient&pid={$item['pid']}"); exit;
    }
}

// ── Transaction ───────────────────────────────────────────────────────────────
$db_conn->begin_transaction();
try {
    // Manually entered by SS staff (validated + uniqueness-checked above)
    $inv_num = $invoice_number;

    // Invoice header — source fields NULL (stock comes from SS directly)
    $null_src = null;
    $zero_src = 0;
    $s = $db_conn->prepare("INSERT INTO tp_invoices (invoice_number,territory_partner_id,source_location_id,source_cp_id,source_godown_id,invoice_date,courier_charges,discount_amount,total_amount,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param("siiiisddds", $inv_num, $tp_id, $null_src, $zero_src, $zero_src, $invoice_date, $courier_charges, $discount_amount, $invoice_total, $created_by);
    $s->execute();
    $invoice_id = $db_conn->insert_id;
    $s->close();

    // Line items + stock movements
    $s_item = $db_conn->prepare("INSERT INTO tp_invoice_items (tp_invoice_id,product_id,quantity,rate,amount) VALUES (?,?,?,?,?)");
    foreach ($items as $item) {
        // Deduct from SS stock with row lock
        $ss_before = lockAndGetSsQty($db_conn, $ss_id, $item['pid']);
        if ($item['qty'] > $ss_before) throw new Exception("Insufficient SS stock for product {$item['pid']}");
        debitSs($db_conn, $ss_id, $item['pid'], $item['qty']);

        // Credit to TP stock
        $tp_before = (function() use ($db_conn, $tp_id, $item) {
            $s = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
            $s->bind_param("ii", $tp_id, $item['pid']); $s->execute();
            $r = $s->get_result()->fetch_assoc(); $s->close();
            return $r ? (int)$r['closing_qty'] : 0;
        })();
        creditTp($db_conn, $tp_id, $item['pid'], $item['qty']);
        $tp_after = $tp_before + $item['qty'];
        insertTpLedger($db_conn, $tp_id, $item['pid'], $item['qty'], $tp_before, $tp_after, $inv_num, $created_by);

        $s_item->bind_param("iiidd", $invoice_id, $item['pid'], $item['qty'], $item['rate'], $item['amount']);
        $s_item->execute();
    }
    $s_item->close();

    // Deduct net amount from advance
    deductTpAdvance($db_conn, $tp_id, $net_amount, $inv_num, $invoice_id);

    $db_conn->commit();
    header("Location: manage-tp-invoices?success=1&inv=" . urlencode($inv_num)); exit;

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("[SS TP Invoice] Transaction failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    header("Location: add-tp-invoice?error=db&msg=" . urlencode(substr($e->getMessage(), 0, 100))); exit;
}
