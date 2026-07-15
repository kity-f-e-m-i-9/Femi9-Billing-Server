<?php
declare(strict_types=1);

include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

function redirectWithMessage(string $location, string $message = ''): void {
    $url = $location . ($message ? '?' . $message : '');
    header("Location: $url");
    exit();
}

/**
 * Credit a piece-wise purchase quantity onto a pack-based stock row.
 *
 * stock.extra_pieces holds loose pieces that haven't yet accumulated into a
 * whole pack. This adds $qtyPieces to that running remainder; whenever the
 * remainder reaches (or exceeds) one pack, the whole-pack portion is credited
 * to stock.closing_qty via StockService (so every other flow in the app keeps
 * seeing pack-based stock), and only the leftover sub-pack amount stays in
 * extra_pieces. Must run inside the caller's transaction.
 *
 * @return array{packs:int, ledger_id:?int}
 */
function neksomo_credit_pieces(
    mysqli $db, StockService $stockService,
    int $productId, string $godownId, int $piecesPerPack, int $qtyPieces,
    string $refId, string $createdBy
): array {
    $lock = $db->prepare("SELECT extra_pieces FROM stock WHERE product_id = ? AND user_type = 'company' AND user_id = ? FOR UPDATE");
    $lock->bind_param('is', $productId, $godownId);
    $lock->execute();
    $row = $lock->get_result()->fetch_assoc();
    $lock->close();

    if ($row === null) {
        $ins = $db->prepare(
            "INSERT INTO stock
                (product_id, opening_qty, opening_date, input_qty, sales_qty,
                 sent_qty, returnqty, closing_qty, extra_pieces, user_type, user_id, updated_at)
             VALUES (?, 0, CURDATE(), 0, 0, 0, 0, 0, 0, 'company', ?, NOW())"
        );
        $ins->bind_param('is', $productId, $godownId);
        $ins->execute();
        $ins->close();
        $currentExtra = 0;
    } else {
        $currentExtra = (int) $row['extra_pieces'];
    }

    $total       = $currentExtra + $qtyPieces;
    $packs       = intdiv($total, $piecesPerPack);
    $newExtra    = $total % $piecesPerPack;
    $ledgerId    = null;

    if ($packs > 0) {
        $result   = $stockService->credit($productId, 'company', $godownId, $packs, 'adjustment', $refId, $createdBy, true);
        $ledgerId = $result['ledger_id'];
    }

    $upd = $db->prepare("UPDATE stock SET extra_pieces = ?, updated_at = NOW() WHERE product_id = ? AND user_type = 'company' AND user_id = ?");
    $upd->bind_param('iis', $newExtra, $productId, $godownId);
    $upd->execute();
    $upd->close();

    return ['packs' => $packs, 'ledger_id' => $ledgerId];
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'error');
}

if (!isset($_POST['add-record'])) {
    redirectWithMessage('neksomo-manufacturer-purchase.php');
}

$vendor_id     = filter_var($_POST['vendor_id'] ?? 0, FILTER_VALIDATE_INT);
$inv_number    = trim(str_replace("'", "", $_POST['inv_number'] ?? ''));
$purchase_date = $_POST['purchase_date'] ?? '';
$created_by    = $_SESSION['LOGIN_USER'] ?? 'system';

$raw_pids  = $_POST['product_id'] ?? [];
$raw_qtys  = $_POST['quantity_pieces'] ?? [];
$raw_costs = $_POST['cost_per_piece'] ?? [];

if (
    !$vendor_id || $inv_number === '' ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchase_date) ||
    empty($raw_pids)
) {
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'error=missing');
}

// Vendor must exist and be active
$vstmt = $db_conn->prepare("SELECT id FROM neksomo_vendors WHERE id = ? AND is_active = 1");
$vstmt->bind_param('i', $vendor_id);
$vstmt->execute();
if ($vstmt->get_result()->num_rows === 0) {
    $vstmt->close();
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'error=missing');
}
$vstmt->close();

// Duplicate invoice number check (pre-check; re-verified inside the transaction below)
$dupStmt = $db_conn->prepare("SELECT id FROM neksomo_manufacturer_purchases WHERE invoice_number = ?");
$dupStmt->bind_param('s', $inv_number);
$dupStmt->execute();
if ($dupStmt->get_result()->num_rows > 0) {
    $dupStmt->close();
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'error=duplicate&inv=' . urlencode($inv_number));
}
$dupStmt->close();

// Build and validate line items — quantity is entered in pieces (the
// purchase is priced piece-wise: total_cost = pieces * cost_per_piece).
// Stock stays pack-wise everywhere else in the app, so pieces that don't
// add up to a whole pack yet are held as a running remainder (stock.extra_pieces)
// instead of being rejected — see the credit loop below.
$rawItems = []; $seen = [];
foreach ($raw_pids as $i => $rpid) {
    $pid        = filter_var($rpid, FILTER_VALIDATE_INT);
    $qty_pieces = filter_var($raw_qtys[$i] ?? 0, FILTER_VALIDATE_INT);
    $cost       = filter_var($raw_costs[$i] ?? null, FILTER_VALIDATE_FLOAT);
    if (!$pid || !$qty_pieces || $qty_pieces <= 0 || $cost === false || $cost < 0) continue;
    if (isset($seen[$pid])) continue;
    $seen[$pid] = true;
    $rawItems[] = ['pid' => $pid, 'qty_pieces' => $qty_pieces, 'cost' => $cost];
}

if (empty($rawItems)) {
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'error=noproducts');
}

// Look up pieces_per_pack for every product in one query
$pids = array_column($rawItems, 'pid');
$placeholders = implode(',', array_fill(0, count($pids), '?'));
$ppStmt = $db_conn->prepare("SELECT id, pieces_per_pack FROM products WHERE id IN ($placeholders)");
$ppStmt->bind_param(str_repeat('i', count($pids)), ...$pids);
$ppStmt->execute();
$piecesPerPackByProduct = [];
foreach ($ppStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $piecesPerPackByProduct[(int)$row['id']] = (int)$row['pieces_per_pack'];
}
$ppStmt->close();

$items = [];
foreach ($rawItems as $it) {
    $pieces_per_pack = $piecesPerPackByProduct[$it['pid']] ?? 1;
    if ($pieces_per_pack < 1) $pieces_per_pack = 1;
    $items[] = [
        'pid'             => $it['pid'],
        'qty_pieces'      => $it['qty_pieces'],
        'pieces_per_pack' => $pieces_per_pack,
        'cost'            => $it['cost'],
        'total_cost' => round($it['qty_pieces'] * $it['cost'], 2),
    ];
}

$grand_total = round(array_sum(array_column($items, 'total_cost')), 2);

// Neksomo's own godown id, looked up by name rather than hardcoded — this
// page is only ever reachable by neksomo/admin, and the stock it credits is
// always Neksomo Hygiene Industries' own on-hand stock.
$neksomoGodownId = (int) ($db_conn->query(
    "SELECT id FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1"
)->fetch_row()[0] ?? 0);

if (!$neksomoGodownId) {
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'error');
}

$db_conn->begin_transaction();
try {
    // Re-check duplicate inside the transaction (row lock guards the race
    // between the pre-check above and this insert).
    $dupTx = $db_conn->prepare("SELECT id FROM neksomo_manufacturer_purchases WHERE invoice_number = ? FOR UPDATE");
    $dupTx->bind_param('s', $inv_number);
    $dupTx->execute();
    if ($dupTx->get_result()->num_rows > 0) {
        $dupTx->close();
        throw new Exception('DUPLICATE_INVOICE_NUMBER');
    }
    $dupTx->close();

    // Header row — manufacturer_name kept in sync from the vendor for the
    // legacy free-text column (still NOT NULL); product_id/quantity_packs/
    // cost_per_piece/total_cost/stock_ledger_id on the header are legacy
    // single-line fields, superseded by neksomo_purchase_items below.
    $vnameStmt = $db_conn->prepare("SELECT vendor_name FROM neksomo_vendors WHERE id = ?");
    $vnameStmt->bind_param('i', $vendor_id);
    $vnameStmt->execute();
    $vendor_name = (string)($vnameStmt->get_result()->fetch_assoc()['vendor_name'] ?? '');
    $vnameStmt->close();

    $stockService = new StockService($db_conn);

    // First pass: apply the piece-wise credit for every item (may credit 0
    // whole packs if the purchase only tops up the loose-piece remainder).
    foreach ($items as &$item) {
        // stock_ledger.ref_type is a closed ENUM (invoice/user_invoice/return/
        // transfer/ot_sale/adjustment/demofree/tp_invoice) — 'adjustment' is
        // what Add Input Stock also uses; the distinguishing detail goes in ref_id.
        $refId = 'manuf_purchase_' . $vendor_id . '_' . $item['pid'] . '_' . uniqid();
        $credit = neksomo_credit_pieces(
            $db_conn, $stockService,
            $item['pid'], (string) $neksomoGodownId, $item['pieces_per_pack'], $item['qty_pieces'],
            $refId, $created_by
        );
        $item['qty_packs']  = $credit['packs'];
        $item['ledger_id']  = $credit['ledger_id'];
    }
    unset($item);

    $first = $items[0];
    $headerStmt = $db_conn->prepare(
        "INSERT INTO neksomo_manufacturer_purchases
            (vendor_id, invoice_number, product_id, manufacturer_name, purchase_date, total_amount, quantity_packs, cost_per_piece, total_cost, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $headerStmt->bind_param(
        'isissdidds',
        $vendor_id, $inv_number, $first['pid'], $vendor_name, $purchase_date,
        $grand_total, $first['qty_packs'], $first['cost'], $first['total_cost'], $created_by
    );
    $headerStmt->execute();
    $purchase_id = $db_conn->insert_id;
    $headerStmt->close();

    $itemStmt = $db_conn->prepare(
        "INSERT INTO neksomo_purchase_items (purchase_id, product_id, quantity_packs, quantity_pieces, cost_per_piece, total_cost, stock_ledger_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($items as $item) {
        $itemStmt->bind_param('iiiiddi', $purchase_id, $item['pid'], $item['qty_packs'], $item['qty_pieces'], $item['cost'], $item['total_cost'], $item['ledger_id']);
        $itemStmt->execute();
    }
    $itemStmt->close();

    $db_conn->commit();
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'addesuccess');
} catch (\Throwable $e) {
    $db_conn->rollback();
    if ($e->getMessage() === 'DUPLICATE_INVOICE_NUMBER') {
        redirectWithMessage('neksomo-manufacturer-purchase.php', 'error=duplicate&inv=' . urlencode($inv_number));
    }
    error_log('[neksomo-manufacturer-purchase] ' . $e->getMessage());
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'error');
}
