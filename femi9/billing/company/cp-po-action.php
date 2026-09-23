<?php
ob_start();
include("checksession.php");
require_once("include/PermissionCheck.php"); requirePermission('channel_partner');
date_default_timezone_set("Asia/Kolkata");
require_once("include/GodownAccess.php");
require_once __DIR__ . '/../shared/CpPurchaseOrderBalance.php';
require_once __DIR__ . '/../shared/CpInvoiceNumberService.php';
require_once __DIR__ . '/../shared/CpInvoiceSchema.php';
require_once("include/StockService.php");
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: cp-today-orders.php?error=unauthorized"); exit;
}
if (empty($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: cp-today-orders.php"); exit;
}

$po_id  = (int)($_POST['po_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($po_id < 1 || !in_array($action, ['approve', 'reject'], true)) {
    header("Location: cp-today-orders.php?error=missing"); exit;
}

cpEnsurePurchaseOrderTables($db_conn);
cpEnsureInvoiceTables($db_conn);

$poStmt = $db_conn->prepare("SELECT id, channel_partner_id, status, product_type,
    use_default_delivery_address, custom_delivery_line1, custom_delivery_line2,
    custom_delivery_city, custom_delivery_district, custom_delivery_state,
    custom_delivery_country, custom_delivery_pincode
    FROM channel_partner_purchase_orders WHERE id = ? LIMIT 1");
$poStmt->bind_param("i", $po_id);
$poStmt->execute();
$po = $poStmt->get_result()->fetch_assoc();
$poStmt->close();

if (!$po || $po['status'] !== 'waiting') {
    header("Location: cp-today-orders.php?error=notwaiting"); exit;
}
$cp_id = (int)$po['channel_partner_id'];

// ── Reject ───────────────────────────────────────────────────────────────
if ($action === 'reject') {
    $reason = trim($_POST['reason'] ?? '') ?: 'Rejected by company';
    $created_by = $_SESSION['LOGIN_USER'] ?? '';
    $s = $db_conn->prepare("UPDATE channel_partner_purchase_orders SET status='cancelled', cancel_reason=?, cancelled_at=NOW(), cancelled_by=? WHERE id=? AND status='waiting'");
    $s->bind_param("ssi", $reason, $created_by, $po_id);
    $s->execute();
    $s->close();
    header("Location: cp-today-orders.php?rejected=1"); exit;
}

// ── Approve ──────────────────────────────────────────────────────────────
$godown_id   = (int)($_POST['godown_id'] ?? 0);
$warehouseId = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
if ($godown_id < 1 || !is_godown_allowed($db_conn, $godown_id)) {
    header("Location: cp-today-orders.php?error=unauthorized"); exit;
}
if (!$warehouseId) {
    header("Location: cp-today-orders.php?error=missing_warehouse"); exit;
}

$items = $db_conn->prepare("SELECT product_id, qty, price, amount FROM channel_partner_purchase_order_items WHERE po_id = ?");
$items->bind_param("i", $po_id);
$items->execute();
$poItems = $items->get_result()->fetch_all(MYSQLI_ASSOC);
$items->close();

if (empty($poItems)) {
    header("Location: cp-today-orders.php?error=noitems"); exit;
}

// Authoritative re-check of headroom right before committing stock — closes
// the race between the queue page's displayed headroom (computed at page
// load) and this click (stock/deposit may have moved since). Excludes this
// PO's own already-counted pending value from the check, since approving it
// is what "spends" that reservation for real.
$grandTotal = array_sum(array_column($poItems, 'amount'));
$headroom = cpAvailableHeadroom($db_conn, $cp_id, $po_id);
if ($grandTotal > $headroom + 0.001) {
    header("Location: cp-today-orders.php?error=overheadroom"); exit;
}

$created_by = $_SESSION['LOGIN_USER'] ?? '';

// ── Stock helpers ────────────────────────────────────────────────────────────
// Godown-side stock goes through StockService (warehouse-aware, shared with
// pl-godown-transfer-action.php). CP-side stock has no warehouse concept, so
// it stays as local raw-SQL helpers, same as the other CP-stock call sites.
function cpPoLockAndGetCpQty(mysqli $db, int $cp_id, int $pid): int {
    $s = $db->prepare("SELECT closing_qty FROM channel_partner_stock WHERE channel_partner_id=? AND product_id=? FOR UPDATE");
    $s->bind_param("ii", $cp_id, $pid); $s->execute();
    $r = $s->get_result()->fetch_assoc(); $s->close();
    return $r ? (int)$r['closing_qty'] : 0;
}
function cpPoCreditCp(mysqli $db, int $cp_id, int $pid, int $qty): void {
    $s = $db->prepare("INSERT INTO channel_partner_stock (channel_partner_id,product_id,input_qty,closing_qty) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE input_qty=input_qty+VALUES(input_qty), closing_qty=closing_qty+VALUES(input_qty)");
    $s->bind_param("iiii", $cp_id, $pid, $qty, $qty); $s->execute(); $s->close();
}
function cpPoInsertCpLedger(mysqli $db, int $cp_id, int $pid, string $action, int $qty, int $before, int $after, string $ref_id, string $by): void {
    $ref_type = 'transfer'; $note = '';
    $s = $db->prepare("INSERT INTO channel_partner_stock_ledger (channel_partner_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $s->bind_param("iisiiissss", $cp_id, $pid, $action, $qty, $before, $after, $ref_type, $ref_id, $note, $by);
    $s->execute(); $s->close();
}

$stockService = new StockService($db_conn);

$db_conn->begin_transaction();
try {
    $ref_id = 'CPPO-' . str_pad($po_id, 5, '0', STR_PAD_LEFT);

    $th = $db_conn->prepare("INSERT INTO pl_godown_transfers (transfer_type,godown_id,cp_id,transfer_date,ref_number,note,created_by,source_po_id) VALUES ('godown_to_location',?,?,?,?,?,?,?)");
    $transfer_date = date('Y-m-d');
    $note = 'Fulfilled from CP purchase order #' . $po_id;
    $th->bind_param("iissssi", $godown_id, $cp_id, $transfer_date, $ref_id, $note, $created_by, $po_id);
    $th->execute();
    $transfer_id = $db_conn->insert_id;
    $th->close();

    $ti = $db_conn->prepare("INSERT INTO pl_godown_transfer_items (transfer_id,product_id,quantity) VALUES (?,?,?)");
    foreach ($poItems as $item) {
        $pid = (int)$item['product_id'];
        $qty = (int)$item['qty'];

        // StockService::transferOut() locks the row inside the transaction —
        // the authoritative gate, not whatever the queue page showed — and
        // throws StockException on insufficient stock.
        $stockService->transferOut(
            $pid, 'company', (string) $godown_id, $qty,
            'transfer', $ref_id, $created_by,
            true, // outer transaction owns commit
            $warehouseId
        );

        $cp_before = cpPoLockAndGetCpQty($db_conn, $cp_id, $pid);
        $cp_after  = $cp_before + $qty;
        cpPoCreditCp($db_conn, $cp_id, $pid, $qty);
        cpPoInsertCpLedger($db_conn, $cp_id, $pid, 'transfer_in', $qty, $cp_before, $cp_after, $ref_id, $created_by);

        $ti->bind_param("iii", $transfer_id, $pid, $qty);
        $ti->execute();
    }
    $ti->close();

    $invoice_date = date('Y-m-d');
    $inv_num = cpInvoiceNextNumber($db_conn, 'CO', $invoice_date);
    $grand_total = array_sum(array_column($poItems, 'amount'));

    $ih = $db_conn->prepare("INSERT INTO cp_invoices
        (invoice_number, channel_partner_id, source_godown_id, product_type, invoice_date,
         total_amount, use_default_delivery_address, custom_delivery_line1, custom_delivery_line2,
         custom_delivery_city, custom_delivery_district, custom_delivery_state,
         custom_delivery_country, custom_delivery_pincode, transfer_id, created_by, created_by_user_type)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'company')");
    $use_default = (int)($po['use_default_delivery_address'] ?? 1);
    $product_type = $po['product_type'] ?? 'napkin';
    // Type string built column-by-column against the VALUES list above:
    // invoice_number(s) channel_partner_id(i) source_godown_id(i) product_type(s)
    // invoice_date(s) total_amount(d) use_default_delivery_address(i)
    // custom_delivery_line1..pincode(s x7) transfer_id(i) created_by(s)
    // = "siissdisssssssis" (16 chars for 16 placeholders; 'company' is a literal, not bound)
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
    foreach ($poItems as $item) {
        $pid = (int)$item['product_id'];
        $qty = (int)$item['qty'];
        $rate = (float)$item['price'];
        $amount = (float)$item['amount'];
        $ii->bind_param("iiidd", $cp_invoice_id, $pid, $qty, $rate, $amount);
        $ii->execute();
    }
    $ii->close();

    $s = $db_conn->prepare("UPDATE channel_partner_purchase_orders SET status='completed', transfer_id=?, cp_invoice_id=? WHERE id=? AND status='waiting'");
    $s->bind_param("iii", $transfer_id, $cp_invoice_id, $po_id);
    $s->execute();
    if ($s->affected_rows < 1) throw new Exception("Purchase order was no longer waiting");
    $s->close();

    $db_conn->commit();
    header("Location: cp-today-orders.php?approved=1&ref=" . urlencode($ref_id) . "&inv=" . urlencode($inv_num)); exit;

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("[CP PO Approve] Transaction failed: " . $e->getMessage());
    header("Location: cp-today-orders.php?error=db&msg=" . urlencode(substr($e->getMessage(), 0, 100))); exit;
}
