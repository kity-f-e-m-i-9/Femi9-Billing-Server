<?php
ob_start();
include("checksession.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
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
$warehouseId   = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$transfer_date = trim($_POST['transfer_date'] ?? date('Y-m-d'));
$note          = trim($_POST['note'] ?? '');
$ref_input     = trim($_POST['ref_number'] ?? '');
$created_by    = $_SESSION['LOGIN_USER'] ?? '';

$raw_pids = $_POST['product_id'] ?? [];
$raw_qtys = $_POST['qty']        ?? [];

$redirect_form = ($transfer_type === 'godown_to_location') ? 'add-godown-to-location' : 'add-location-to-godown';

if (!$godown_id || !$cp_id || empty($raw_pids)) {
    header("Location: {$redirect_form}?error=missing"); exit;
}

if (!$warehouseId) {
    header("Location: {$redirect_form}?error=missing_warehouse"); exit;
}

if (!is_godown_allowed($db_conn, $godown_id)) {
    header("Location: {$redirect_form}?error=unauthorized"); exit;
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
// Godown-side stock now goes through StockService (warehouse-aware) instead of
// raw SQL — see docs/superpowers/specs/2026-09-17-per-godown-split-stock-design.md.

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

$stockService = new StockService($db_conn);

foreach ($items as $item) {
    if ($transfer_type === 'godown_to_location') {
        $avail = $stockService->getClosingQty($item['pid'], 'company', (string) $godown_id, $warehouseId) ?? 0;
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
            // Godown → CP. StockService::transferOut() locks the row, checks
            // sufficiency, and throws StockException on insufficient stock.
            $stockService->transferOut(
                $item['pid'], 'company', (string) $godown_id, $item['qty'],
                'transfer', $ref_id, $created_by,
                true, // outer transaction owns commit
                $warehouseId
            );

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

            $stockService->transferIn(
                $item['pid'], 'company', (string) $godown_id, $item['qty'],
                'transfer', $ref_id, $created_by,
                true, // outer transaction owns commit
                null, // lotRate — no source-leg weighted rate available here
                $warehouseId
            );
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
