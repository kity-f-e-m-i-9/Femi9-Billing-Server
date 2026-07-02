<?php
ob_start();
include("checksession.php");
require_once("include/GodownAccess.php");
error_reporting(0);

if (empty($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: manage-pl-godown-transfers"); exit;
}

$transfer_type = $_POST['transfer_type'] ?? '';
if (!in_array($transfer_type, ['godown_to_location', 'location_to_godown'])) {
    header("Location: manage-pl-godown-transfers"); exit;
}

$godown_id     = (int)($_POST['godown_id']   ?? 0);
$cp_id         = (int)($_POST['cp_id']       ?? 0);
$transfer_date = trim($_POST['transfer_date'] ?? date('Y-m-d'));
$note          = trim($_POST['note'] ?? '');
$ref_input     = trim($_POST['ref_number'] ?? '');
$created_by    = $_SESSION['LOGIN_USER'] ?? '';

$raw_pids = $_POST['product_id'] ?? [];
$raw_qtys = $_POST['qty']        ?? [];

if (!$godown_id || !$cp_id || empty($raw_pids)) {
    header("Location: add-godown-to-location?error=missing"); exit;
}

if (!is_godown_allowed($db_conn, $godown_id)) {
    header("Location: add-godown-to-location?error=unauthorized"); exit;
}

// Build validated line items
$items = []; $seen = [];
foreach ($raw_pids as $i => $rpid) {
    $pid = (int)$rpid;
    $qty = (int)($raw_qtys[$i] ?? 0);
    if ($pid < 1 || $qty < 1 || isset($seen[$pid])) continue;
    $seen[$pid] = true;
    $items[] = ['pid' => $pid, 'qty' => $qty];
}
if (empty($items)) {
    header("Location: add-godown-to-location?error=noproducts"); exit;
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function getGodownQty(mysqli $db, int $godown_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM stock WHERE user_type='company' AND user_id=? AND product_id=?");
    $s->bind_param("si", $godown_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}

function debitGodown(mysqli $db, int $godown_id, int $pid, int $qty): void {
    $s = $db->prepare("UPDATE stock SET sent_qty=sent_qty+?, closing_qty=closing_qty-? WHERE user_type='company' AND user_id=? AND product_id=?");
    $s->bind_param("iisi", $qty, $qty, $godown_id, $pid); $s->execute(); $s->close();
}

function creditGodown(mysqli $db, int $godown_id, int $pid, int $qty): void {
    $s = $db->prepare("UPDATE stock SET returnqty=returnqty+?, closing_qty=closing_qty+? WHERE user_type='company' AND user_id=? AND product_id=?");
    $s->bind_param("iisi", $qty, $qty, $godown_id, $pid); $s->execute(); $s->close();
}

function insertStockLedger(mysqli $db, int $godown_id, int $pid, string $action, int $qty, int $before, int $after, string $ref_id, string $by): void {
    $user_type = 'company'; $ref_type = 'transfer'; $note = '';
    $s = $db->prepare("INSERT INTO stock_ledger (product_id,user_type,user_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $uid = (string)$godown_id;
    $s->bind_param("isssiiissss", $pid, $user_type, $uid, $action, $qty, $before, $after, $ref_type, $ref_id, $note, $by);
    $s->execute(); $s->close();
}

function getCpQty(mysqli $db, int $cp_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM channel_partner_stock WHERE channel_partner_id=? AND product_id=?");
    $s->bind_param("ii", $cp_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}

function creditCp(mysqli $db, int $cp_id, int $pid, int $qty): void {
    $s = $db->prepare("INSERT INTO channel_partner_stock (channel_partner_id,product_id,input_qty,closing_qty) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE input_qty=input_qty+VALUES(input_qty), closing_qty=closing_qty+VALUES(input_qty)");
    $s->bind_param("iiii", $cp_id, $pid, $qty, $qty); $s->execute(); $s->close();
}

function debitCp(mysqli $db, int $cp_id, int $pid, int $qty): void {
    $s = $db->prepare("UPDATE channel_partner_stock SET closing_qty=closing_qty-? WHERE channel_partner_id=? AND product_id=?");
    $s->bind_param("iii", $qty, $cp_id, $pid); $s->execute(); $s->close();
}

function lockAndGetQty(mysqli $db, string $table, string $col1, string $val1, string $col2, string $val2, int $pid): int {
    $v1 = mysqli_real_escape_string($db, $val1);
    $v2 = mysqli_real_escape_string($db, $val2);
    $r  = mysqli_fetch_assoc(mysqli_query($db,
        "SELECT closing_qty FROM `$table` WHERE `$col1`='$v1' AND `$col2`='$v2' AND product_id=$pid FOR UPDATE"));
    return $r ? (int)$r['closing_qty'] : 0;
}

function lockAndGetCpQty(mysqli $db, int $cp_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM channel_partner_stock WHERE channel_partner_id=? AND product_id=? FOR UPDATE");
    $s->bind_param("ii", $cp_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}

function insertCpLedger(mysqli $db, int $cp_id, int $pid, string $action, int $qty, int $before, int $after, string $ref_id, string $by): void {
    $ref_type = 'transfer'; $note = '';
    $s = $db->prepare("INSERT INTO channel_partner_stock_ledger (channel_partner_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param("iisiiissss", $cp_id, $pid, $action, $qty, $before, $after, $ref_type, $ref_id, $note, $by);
    $s->execute(); $s->close();
}

// ── Pre-validate stock ─────────────────────────────────────────────────────────
$redirect_base = ($transfer_type === 'godown_to_location') ? 'add-godown-to-location' : 'add-location-to-godown';

foreach ($items as $item) {
    if ($transfer_type === 'godown_to_location') {
        $avail = getGodownQty($db_conn, $godown_id, $item['pid']);
    } else {
        $avail = getCpQty($db_conn, $cp_id, $item['pid']);
    }
    if ($item['qty'] > $avail) {
        header("Location: {$redirect_base}?error=insufficient&pid={$item['pid']}"); exit;
    }
}

// ── Transaction ────────────────────────────────────────────────────────────────
$db_conn->begin_transaction();
try {
    // Insert transfer header
    $s = $db_conn->prepare("INSERT INTO pl_godown_transfers (transfer_type,godown_id,cp_id,transfer_date,ref_number,note,created_by) VALUES (?,?,?,?,?,?,?)");
    $s->bind_param("siissss", $transfer_type, $godown_id, $cp_id, $transfer_date, $ref_input, $note, $created_by);
    $s->execute();
    $transfer_id = $db_conn->insert_id;
    $s->close();

    // Auto-generate ref_number if not provided
    $ref_id = $ref_input ?: ('PLT-' . str_pad($transfer_id, 5, '0', STR_PAD_LEFT));
    if (!$ref_input) {
        $db_conn->query("UPDATE pl_godown_transfers SET ref_number='$ref_id' WHERE id=$transfer_id");
    }

    // Process each product
    $s_item = $db_conn->prepare("INSERT INTO pl_godown_transfer_items (transfer_id,product_id,quantity) VALUES (?,?,?)");
    foreach ($items as $item) {
        if ($transfer_type === 'godown_to_location') {
            // Godown → CP (lock godown row before debit to prevent concurrent oversell)
            $gd_before = lockAndGetQty($db_conn, 'stock', 'user_type', 'company', 'user_id', (string)$godown_id, $item['pid']);
            if ($item['qty'] > $gd_before) throw new Exception("Insufficient godown stock for product {$item['pid']}");
            $gd_after  = $gd_before - $item['qty'];
            debitGodown($db_conn, $godown_id, $item['pid'], $item['qty']);
            insertStockLedger($db_conn, $godown_id, $item['pid'], 'transfer_out', $item['qty'], $gd_before, $gd_after, $ref_id, $created_by);

            $cp_before = getCpQty($db_conn, $cp_id, $item['pid']);
            $cp_after  = $cp_before + $item['qty'];
            creditCp($db_conn, $cp_id, $item['pid'], $item['qty']);
            insertCpLedger($db_conn, $cp_id, $item['pid'], 'transfer_in', $item['qty'], $cp_before, $cp_after, $ref_id, $created_by);

        } else {
            // CP → Godown (lock CP row before debit to prevent concurrent oversell)
            $cp_before = lockAndGetCpQty($db_conn, $cp_id, $item['pid']);
            if ($item['qty'] > $cp_before) throw new Exception("Insufficient CP stock for product {$item['pid']}");
            $cp_after  = $cp_before - $item['qty'];
            debitCp($db_conn, $cp_id, $item['pid'], $item['qty']);
            insertCpLedger($db_conn, $cp_id, $item['pid'], 'transfer_out', $item['qty'], $cp_before, $cp_after, $ref_id, $created_by);

            $gd_before = getGodownQty($db_conn, $godown_id, $item['pid']);
            $gd_after  = $gd_before + $item['qty'];
            creditGodown($db_conn, $godown_id, $item['pid'], $item['qty']);
            insertStockLedger($db_conn, $godown_id, $item['pid'], 'transfer_in', $item['qty'], $gd_before, $gd_after, $ref_id, $created_by);
        }

        $s_item->bind_param("iii", $transfer_id, $item['pid'], $item['qty']);
        $s_item->execute();
    }
    $s_item->close();

    $db_conn->commit();
    header("Location: manage-pl-godown-transfers?success=1&ref=" . urlencode($ref_id)); exit;

} catch (\Throwable $e) {
    $db_conn->rollback();
    header("Location: {$redirect_base}?error=db"); exit;
}
