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
if (($_POST['action'] ?? '') !== 'update-tp-invoice') {
    header("Location: manage-tp-invoices"); exit;
}

$enc    = $_POST['invoice_enc'] ?? '';
$inv_id = (int)base64_decode($enc);
if (!$inv_id) { header("Location: manage-tp-invoices?error=invalid"); exit; }

$ss_id = $Login_user_IDvl;

// ── Fetch existing invoice — ownership via TP.onboard_ss_id ───────────────────
$s = $db_conn->prepare("
    SELECT tpi.*
    FROM tp_invoices tpi
    JOIN territory_partners tp ON tp.id = tpi.territory_partner_id
    WHERE tpi.id = ? AND tp.onboard_ss_id = ?
    LIMIT 1
");
$s->bind_param("is", $inv_id, $ss_id); $s->execute();
$inv = $s->get_result()->fetch_assoc(); $s->close();
if (!$inv) { header("Location: manage-tp-invoices?error=notfound"); exit; }

$tp_id        = (int)$inv['territory_partner_id'];
$inv_num      = $inv['invoice_number'];
$old_subtotal = round((float)$inv['total_amount'] - (float)($inv['courier_charges'] ?? 0), 2);
$created_by   = $_SESSION['LOGIN_USER'] ?? '';

// ── Fetch old items ────────────────────────────────────────────────────────────
$s2 = $db_conn->prepare("SELECT * FROM tp_invoice_items WHERE tp_invoice_id=?");
$s2->bind_param("i", $inv_id); $s2->execute();
$old_items = $s2->get_result()->fetch_all(MYSQLI_ASSOC); $s2->close();

// ── Parse new inputs ──────────────────────────────────────────────────────────
$invoice_date    = trim($_POST['invoice_date'] ?? date('Y-m-d'));
$courier_charges = round((float)($_POST['courier_charges'] ?? 0), 2);
if ($courier_charges < 0) $courier_charges = 0;
$discount_amount = array_key_exists('discount_amount', $_POST)
    ? max(0, round((float)$_POST['discount_amount'], 2))
    : round((float)($inv['discount_amount'] ?? 0), 2);

$raw_pids  = $_POST['product_id'] ?? [];
$raw_qtys  = $_POST['qty']        ?? [];
$raw_rates = $_POST['rate']       ?? [];

$new_items = []; $seen = []; $item_errors = [];
foreach ($raw_pids as $i => $rpid) {
    $pid  = (int)$rpid;
    $qty  = (int)($raw_qtys[$i]  ?? 0);
    $rate = round((float)($raw_rates[$i] ?? 0), 2);
    if ($pid < 1) continue;
    if ($qty < 1)  { $item_errors[] = "product_$pid:qty"; continue; }
    if ($rate < 0) { $item_errors[] = "product_$pid:rate"; continue; }
    if (isset($seen[$pid])) { $item_errors[] = "product_$pid:duplicate"; continue; }
    $seen[$pid] = true;
    $new_items[] = ['pid' => $pid, 'qty' => $qty, 'rate' => $rate, 'amount' => round($qty * $rate, 2)];
}
if (empty($new_items)) {
    header("Location: edit-tp-invoice?id=$enc&error=noproducts"); exit;
}
if (!empty($item_errors)) {
    header("Location: edit-tp-invoice?id=$enc&error=invalid_items&details=" . urlencode(implode(',', $item_errors))); exit;
}

$new_subtotal = round(array_sum(array_column($new_items, 'amount')), 2);
$new_net      = max(0, round($new_subtotal - $discount_amount, 2));
$new_total    = round($new_net + $courier_charges, 2);

// ── Helpers: SS's own stock (mirrors tp-invoice-action.php / delete-tp-invoice.php) ──
function getSsQtyE(mysqli $db, string $ss_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM stock WHERE user_type='super_stockiest' AND user_id=? AND product_id=?");
    $s->bind_param("si", $ss_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}
function lockSsQtyE(mysqli $db, string $ss_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM stock WHERE user_type='super_stockiest' AND user_id=? AND product_id=? FOR UPDATE");
    $s->bind_param("si", $ss_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}
function creditSsE(mysqli $db, string $ss_id, int $pid, int $qty): void {
    $s = $db->prepare("UPDATE stock SET sent_qty=GREATEST(0,sent_qty-?), closing_qty=closing_qty+? WHERE user_type='super_stockiest' AND user_id=? AND product_id=?");
    $s->bind_param("iisi", $qty, $qty, $ss_id, $pid); $s->execute(); $s->close();
}
function debitSsE(mysqli $db, string $ss_id, int $pid, int $qty): void {
    $s = $db->prepare("UPDATE stock SET sent_qty=sent_qty+?, closing_qty=closing_qty-? WHERE user_type='super_stockiest' AND user_id=? AND product_id=?");
    $s->bind_param("iisi", $qty, $qty, $ss_id, $pid); $s->execute(); $s->close();
}
function insertSsLedgerE(mysqli $db, string $ss_id, int $pid, string $action, int $qty, int $before, int $after, string $inv_num, string $note, string $by): void {
    $utype = 'super_stockiest'; $ref_type = 'tp_invoice';
    $s = $db->prepare("INSERT INTO stock_ledger (product_id,user_type,user_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param("isssiiissss", $pid, $utype, $ss_id, $action, $qty, $before, $after, $ref_type, $inv_num, $note, $by);
    $s->execute(); $s->close();
}
function getTpQtyE(mysqli $db, int $tp_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
    $s->bind_param("ii", $tp_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}

// ── Pre-validate new stock (accounting for old qty being restored first) ──────
$old_qty_map = [];
foreach ($old_items as $oi) {
    $old_qty_map[(int)$oi['product_id']] = (int)$oi['quantity'];
}
foreach ($new_items as $item) {
    $current = getSsQtyE($db_conn, $ss_id, $item['pid']);
    $avail = $current + ($old_qty_map[$item['pid']] ?? 0);
    if ($item['qty'] > $avail) {
        header("Location: edit-tp-invoice?id=$enc&error=insufficient&pid={$item['pid']}"); exit;
    }
}

// ── Transaction ────────────────────────────────────────────────────────────────
$db_conn->begin_transaction();
try {

    // Lock invoice row — prevents concurrent edit/delete from both running reversals
    $lock = $db_conn->prepare("SELECT id FROM tp_invoices WHERE id=? FOR UPDATE");
    $lock->bind_param("i", $inv_id); $lock->execute();
    $locked = $lock->get_result()->fetch_assoc(); $lock->close();
    if (!$locked) {
        throw new \Exception("Invoice no longer exists — deleted by another request.");
    }

    // 1. Reverse old stock movements
    foreach ($old_items as $oi) {
        $pid = (int)$oi['product_id'];
        $qty = (int)$oi['quantity'];

        $before = getSsQtyE($db_conn, $ss_id, $pid);
        $after  = $before + $qty;
        creditSsE($db_conn, $ss_id, $pid, $qty);
        insertSsLedgerE($db_conn, $ss_id, $pid, 'transfer_in', $qty, $before, $after, $inv_num, 'Edit reversal: ' . $inv_num, $created_by);

        // Undo territory_partner_stock credit
        $u2 = $db_conn->prepare("UPDATE territory_partner_stock SET input_qty=GREATEST(0,input_qty-?), closing_qty=GREATEST(0,closing_qty-?) WHERE territory_partner_id=? AND product_id=?");
        $u2->bind_param("iiii", $qty, $qty, $tp_id, $pid); $u2->execute(); $u2->close();

        $tp_after  = getTpQtyE($db_conn, $tp_id, $pid);
        $tp_before = $tp_after + $qty;
        $ins2 = $db_conn->prepare("INSERT INTO territory_partner_stock_ledger (territory_partner_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $act_tp = 'deduct'; $ref_tp = 'tp_invoice'; $note_tp = 'Edit reversal: ' . $inv_num;
        $ins2->bind_param("iisiiissss", $tp_id, $pid, $act_tp, $qty, $tp_before, $tp_after, $ref_tp, $inv_num, $note_tp, $created_by);
        $ins2->execute(); $ins2->close();
    }

    // 2. Restore old advance payment — reverse exactly what was deducted for this invoice
    tpAdvanceRestore($db_conn, $inv_id);

    // 3. Validate new advance balance (net amount after discount; courier collected separately)
    $bs = $db_conn->prepare("SELECT COALESCE(SUM(balance_amount),0) AS bal FROM tp_advance_payments WHERE territory_partner_id=? AND balance_amount>0 AND status!='fully_adjusted'");
    $bs->bind_param("i", $tp_id); $bs->execute();
    $avail_balance = round((float)$bs->get_result()->fetch_assoc()['bal'], 2); $bs->close();
    if ($avail_balance < $new_net) {
        throw new \Exception("Insufficient advance balance. Available: " . inr_format($avail_balance, 2) . ", Required: " . inr_format($new_net, 2));
    }

    // 4. Apply new stock movements
    foreach ($new_items as $item) {
        $pid = $item['pid'];
        $qty = $item['qty'];

        $before = lockSsQtyE($db_conn, $ss_id, $pid);
        if ($qty > $before) throw new \Exception("Insufficient SS stock for product $pid inside transaction.");
        $after = $before - $qty;
        debitSsE($db_conn, $ss_id, $pid, $qty);
        insertSsLedgerE($db_conn, $ss_id, $pid, 'transfer_out', $qty, $before, $after, $inv_num, 'Edit: ' . $inv_num, $created_by);

        // Credit territory_partner_stock
        $tp_before = getTpQtyE($db_conn, $tp_id, $pid);
        $tp_after  = $tp_before + $qty;
        $u2 = $db_conn->prepare("INSERT INTO territory_partner_stock (territory_partner_id,product_id,input_qty,closing_qty) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE input_qty=input_qty+VALUES(input_qty), closing_qty=closing_qty+VALUES(input_qty)");
        $u2->bind_param("iiii", $tp_id, $pid, $qty, $qty); $u2->execute(); $u2->close();

        $act_tp = 'credit'; $ref_tp = 'tp_invoice'; $note_tp = 'Edit: ' . $inv_num;
        $ins2 = $db_conn->prepare("INSERT INTO territory_partner_stock_ledger (territory_partner_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $ins2->bind_param("iisiiissss", $tp_id, $pid, $act_tp, $qty, $tp_before, $tp_after, $ref_tp, $inv_num, $note_tp, $created_by);
        $ins2->execute(); $ins2->close();
    }

    // 5. Deduct new advance (FIFO, net amount after discount) and log for future restore
    tpAdvanceDeduct($db_conn, $inv_id, $inv_num, $tp_id, $new_net);

    // 6. Update invoice header (discount_amount preserved/updated so total stays consistent)
    $upd = $db_conn->prepare("UPDATE tp_invoices SET invoice_date=?, courier_charges=?, discount_amount=?, total_amount=? WHERE id=?");
    $upd->bind_param("sdddi", $invoice_date, $courier_charges, $discount_amount, $new_total, $inv_id); $upd->execute();
    if ($upd->affected_rows === 0) {
        throw new \Exception("Invoice was deleted by another request during edit.");
    }
    $upd->close();

    // 7. Replace line items
    $del = $db_conn->prepare("DELETE FROM tp_invoice_items WHERE tp_invoice_id=?");
    $del->bind_param("i", $inv_id); $del->execute(); $del->close();

    $si = $db_conn->prepare("INSERT INTO tp_invoice_items (tp_invoice_id,product_id,quantity,rate,amount) VALUES (?,?,?,?,?)");
    foreach ($new_items as $item) {
        $si->bind_param("iiidd", $inv_id, $item['pid'], $item['qty'], $item['rate'], $item['amount']);
        $si->execute();
    }
    $si->close();

    $db_conn->commit();
    header("Location: manage-tp-invoices?updated=1&inv=" . urlencode($inv_num)); exit;

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("[SS edit-tp-invoice] Failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    header("Location: edit-tp-invoice?id=$enc&error=db&msg=" . urlencode(substr($e->getMessage(), 0, 100))); exit;
}
