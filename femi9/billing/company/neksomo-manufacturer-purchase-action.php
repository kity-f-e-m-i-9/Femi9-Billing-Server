<?php
declare(strict_types=1);

include("checksession.php");
include("config.php");
require_once("include/GodownAccess.php");
require_once("include/StockService.php");
require_once("include/StockLots.php");
require_once("include/NeksomoStockBridge.php");
require_once("include/NeksomoPurchaseSchema.php");

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

ensure_neksomo_manufacturer_purchases_warehouse_column($db_conn);

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    redirectWithMessage('neksomo-manufacturer-purchase.php', 'error');
}

if (!isset($_POST['add-record'])) {
    redirectWithMessage('neksomo-manufacturer-purchase.php');
}

$vendor_id     = filter_var($_POST['vendor_id'] ?? 0, FILTER_VALIDATE_INT);
$inv_number    = trim(str_replace("'", "", $_POST['inv_number'] ?? ''));
$purchase_date = $_POST['purchase_date'] ?? '';
$warehouse_id  = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$created_by    = $_SESSION['LOGIN_USER'] ?? 'system';

$raw_pids  = $_POST['product_id'] ?? [];
$raw_qtys  = $_POST['quantity_pieces'] ?? [];
$raw_costs = $_POST['cost_per_piece'] ?? [];

if (
    !$vendor_id || $inv_number === '' ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchase_date) ||
    $warehouse_id === null ||
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

// Look up pieces_per_pack and GST details for every product in one query.
// GST is never trusted from the client — it's always taken from the
// product's own gst/gst_type at the moment of purchase, so a line item's
// tax breakdown can't be spoofed or drift from what's actually configured.
$pids = array_column($rawItems, 'pid');
$placeholders = implode(',', array_fill(0, count($pids), '?'));
$ppStmt = $db_conn->prepare("SELECT id, pieces_per_pack, gst, gst_type, unit_type FROM products WHERE id IN ($placeholders)");
$ppStmt->bind_param(str_repeat('i', count($pids)), ...$pids);
$ppStmt->execute();
$piecesPerPackByProduct = [];
$gstRateByProduct = [];
$gstTypeByProduct = [];
$unitTypeByProduct = [];
foreach ($ppStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $piecesPerPackByProduct[(int)$row['id']] = (int)$row['pieces_per_pack'];
    $gstRateByProduct[(int)$row['id']] = (float)$row['gst'];
    $gstTypeByProduct[(int)$row['id']] = $row['gst_type'] === 'inclusive' ? 'inclusive' : 'exclusive';
    $unitTypeByProduct[(int)$row['id']] = $row['unit_type'];
}
$ppStmt->close();

// Cost/Piece is priced according to the product's own GST setting: for
// exclusive products it's the pre-tax rate (GST is added on top); for
// inclusive products it's the final rate (GST is backed out of it). Either
// way, total_cost ends up as the tax-inclusive amount actually paid.
$items = [];
foreach ($rawItems as $it) {
    $pieces_per_pack = $piecesPerPackByProduct[$it['pid']] ?? 1;
    if ($pieces_per_pack < 1) $pieces_per_pack = 1;
    $gst_rate = $gstRateByProduct[$it['pid']] ?? 0.0;
    $gst_type = $gstTypeByProduct[$it['pid']] ?? 'exclusive';

    // Rounded to 6 decimal places (matching the DB columns' precision), not 2
    // — cost/piece is user-entered and shouldn't be silently truncated to
    // whole paise.
    $entered_amount = round($it['qty_pieces'] * $it['cost'], 6);
    if ($gst_type === 'inclusive') {
        $total_cost    = $entered_amount;
        $taxable_value = round($total_cost / (1 + $gst_rate / 100), 6);
        $gst_amount    = round($total_cost - $taxable_value, 6);
    } else {
        $taxable_value = $entered_amount;
        $gst_amount    = round($taxable_value * $gst_rate / 100, 6);
        $total_cost    = round($taxable_value + $gst_amount, 6);
    }

    $items[] = [
        'pid'             => $it['pid'],
        'qty_pieces'      => $it['qty_pieces'],
        'pieces_per_pack' => $pieces_per_pack,
        'unit_type'       => $unitTypeByProduct[$it['pid']] ?? null,
        'cost'            => $it['cost'],
        'gst_rate'        => $gst_rate,
        'gst_type'        => $gst_type,
        'taxable_value'   => $taxable_value,
        'gst_amount'      => $gst_amount,
        'total_cost'      => $total_cost,
    ];
}

$grand_total    = round(array_sum(array_column($items, 'total_cost')), 6);
$grand_taxable  = round(array_sum(array_column($items, 'taxable_value')), 6);
$grand_gst      = round(array_sum(array_column($items, 'gst_amount')), 6);

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
            $refId, $created_by, $warehouse_id
        );
        $item['qty_packs']  = $credit['packs'];
        $item['ledger_id']  = $credit['ledger_id'];
    }
    unset($item);

    $first = $items[0];
    $headerStmt = $db_conn->prepare(
        "INSERT INTO neksomo_manufacturer_purchases
            (vendor_id, invoice_number, product_id, manufacturer_name, purchase_date, warehouse_id, total_amount, total_taxable_value, total_gst_amount, quantity_packs, cost_per_piece, total_cost, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $headerStmt->bind_param(
        'isissidddidds',
        $vendor_id, $inv_number, $first['pid'], $vendor_name, $purchase_date, $warehouse_id,
        $grand_total, $grand_taxable, $grand_gst, $first['qty_packs'], $first['cost'], $first['total_cost'], $created_by
    );
    $headerStmt->execute();
    $purchase_id = $db_conn->insert_id;
    $headerStmt->close();

    $itemStmt = $db_conn->prepare(
        "INSERT INTO neksomo_purchase_items (purchase_id, product_id, quantity_packs, quantity_pieces, cost_per_piece, gst_rate, gst_type, total_cost, taxable_value, gst_amount, stock_ledger_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($items as $item) {
        $itemStmt->bind_param(
            'iiiiddsdddi',
            $purchase_id, $item['pid'], $item['qty_packs'], $item['qty_pieces'], $item['cost'],
            $item['gst_rate'], $item['gst_type'], $item['total_cost'], $item['taxable_value'], $item['gst_amount'], $item['ledger_id']
        );
        $itemStmt->execute();

        // Only whole packs actually credited to stock become a lot — a
        // purchase that only topped up the loose-piece remainder (qty_packs
        // === 0) has nothing to record yet; it'll become a lot once enough
        // further pieces accumulate to complete a pack (a future purchase's
        // own recordLot call, once that purchase's qty_packs > 0).
        if ($item['qty_packs'] > 0) {
            // Rate is per PIECE in the purchase form; stock_lots tracks
            // pack-based qty (matching stock.closing_qty), so the lot's rate
            // must be per PACK: cost_per_piece * pieces_per_pack.
            $ratePerPack = round($item['cost'] * $item['pieces_per_pack'], 6);
            StockLots::recordLot(
                $db_conn, $item['pid'], 'company', (string) $neksomoGodownId,
                $ratePerPack, $item['qty_packs'], $purchase_date,
                'neksomo_purchase', (string) $purchase_id, $created_by, $warehouse_id
            );
        }

        // Proactively draw this purchase's pool contribution into every
        // mapped sellable company product's real stock — otherwise the
        // credited stock sits invisibly on the raw NKS-% placeholder
        // product ($item['pid']) until someone happens to attempt a sale
        // that exceeds what's already on a mapped product's own row
        // (StockService::ensureNeksomoTopUp()'s lazy, reactive path).
        //
        // Only for unit_type='pack' Neksomo products (e.g. diapers): those
        // are 1:1 with the mapped company product's pack unit, so eager
        // conversion is unambiguous. Pieces-type products (e.g. napkins)
        // are deliberately excluded — assembling loose pieces into packs
        // is a real-world action the Neksomo login performs explicitly via
        // Convert Pieces <-> Packs, not something that should happen
        // silently on every purchase; ensureNeksomoTopUp() still covers a
        // pieces-type shortfall reactively at sale time.
        if ($item['unit_type'] === 'pack') {
            // One Neksomo product can map to several sibling company SKUs
            // sharing the same pool — each sibling is processed in a stable
            // order (ascending company_product_id) and only draws what's
            // still available after earlier siblings claimed their share,
            // using the same "purchased - sold - already converted" pool
            // math ensureNeksomoTopUp() already relies on.
            $mappedCompanyProductIds = get_neksomo_product_mapping($db_conn, $item['pid']);
            sort($mappedCompanyProductIds);
            foreach ($mappedCompanyProductIds as $companyProductId) {
                $availablePacks = get_neksomo_pool_available_packs($db_conn, $companyProductId);
                if ($availablePacks <= 0) continue;
                $stockService->credit(
                    $companyProductId, 'company', (string) $neksomoGodownId, $availablePacks,
                    'adjustment', 'neksomo_conversion_' . uniqid(), $created_by, true, $warehouse_id
                );
                record_neksomo_stock_conversion($db_conn, $companyProductId, $availablePacks, $created_by);
            }
        }
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
