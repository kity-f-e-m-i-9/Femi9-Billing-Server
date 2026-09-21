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

ensure_neksomo_manufacturer_purchases_warehouse_column($db_conn);

function redirectWithMessage(string $location, string $message = ''): void {
    $url = $location . ($message ? '?' . $message : '');
    header("Location: $url");
    exit();
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    redirectWithMessage('neksomo-manufacturer-purchase-manage.php', 'error');
}

$purchase_id = (int) ($_POST['purchase_id'] ?? 0);
if (!$purchase_id) {
    redirectWithMessage('neksomo-manufacturer-purchase-manage.php', 'error');
}
$editUrl = 'edit-neksomo-manufacturer-purchase.php?id=' . urlencode(base64_encode((string) $purchase_id));

$vendor_id     = filter_var($_POST['vendor_id'] ?? 0, FILTER_VALIDATE_INT);
$inv_number    = trim(str_replace("'", "", $_POST['inv_number'] ?? ''));
$purchase_date = $_POST['purchase_date'] ?? '';
$new_warehouse_id = filter_var($_POST['warehouse_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$created_by    = $_SESSION['LOGIN_USER'] ?? 'system';

$raw_pids  = $_POST['product_id'] ?? [];
$raw_qtys  = $_POST['quantity_pieces'] ?? [];
$raw_costs = $_POST['cost_per_piece'] ?? [];

if (
    !$vendor_id || $inv_number === '' ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchase_date) ||
    $new_warehouse_id === null ||
    empty($raw_pids)
) {
    redirectWithMessage($editUrl, 'error=missing');
}

$purchase = null;
$oldItemsByProduct = [];
$stmt = $db_conn->prepare("SELECT * FROM neksomo_manufacturer_purchases WHERE id = ?");
$stmt->bind_param('i', $purchase_id);
$stmt->execute();
$purchase = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$purchase) {
    redirectWithMessage('neksomo-manufacturer-purchase-manage.php', 'error');
}
$old_warehouse_id = $purchase['warehouse_id'] !== null ? (int) $purchase['warehouse_id'] : null;

$oldItemStmt = $db_conn->prepare(
    "SELECT npi.*, p.pieces_per_pack, p.unit_type
     FROM neksomo_purchase_items npi
     JOIN products p ON p.id = npi.product_id
     WHERE npi.purchase_id = ?"
);
$oldItemStmt->bind_param('i', $purchase_id);
$oldItemStmt->execute();
foreach ($oldItemStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $oldItemsByProduct[(int) $row['product_id']] = $row;
}
$oldItemStmt->close();

// Vendor must exist and be active
$vstmt = $db_conn->prepare("SELECT id, vendor_name FROM neksomo_vendors WHERE id = ? AND is_active = 1");
$vstmt->bind_param('i', $vendor_id);
$vstmt->execute();
$vendorRow = $vstmt->get_result()->fetch_assoc();
$vstmt->close();
if (!$vendorRow) {
    redirectWithMessage($editUrl, 'error=missing');
}
$vendor_name = (string) $vendorRow['vendor_name'];

// Duplicate invoice number check — only matters if it actually changed.
if ($inv_number !== $purchase['invoice_number']) {
    $dupStmt = $db_conn->prepare("SELECT id FROM neksomo_manufacturer_purchases WHERE invoice_number = ? AND id != ?");
    $dupStmt->bind_param('si', $inv_number, $purchase_id);
    $dupStmt->execute();
    if ($dupStmt->get_result()->num_rows > 0) {
        $dupStmt->close();
        redirectWithMessage($editUrl, 'error=duplicate&inv=' . urlencode($inv_number));
    }
    $dupStmt->close();
}

// Build and validate the new line items (same shape/validation as Add).
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
    redirectWithMessage($editUrl, 'error=noproducts');
}

$pids = array_column($rawItems, 'pid');
$placeholders = implode(',', array_fill(0, count($pids), '?'));
$ppStmt = $db_conn->prepare("SELECT id, pieces_per_pack, gst, gst_type, unit_type FROM products WHERE id IN ($placeholders)");
$ppStmt->bind_param(str_repeat('i', count($pids)), ...$pids);
$ppStmt->execute();
$productMeta = [];
foreach ($ppStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $productMeta[(int) $row['id']] = $row;
}
$ppStmt->close();

$newItems = [];
foreach ($rawItems as $it) {
    $meta = $productMeta[$it['pid']] ?? null;
    $pph = max((int) ($meta['pieces_per_pack'] ?? 1), 1);
    $gst_rate = (float) ($meta['gst'] ?? 0);
    $gst_type = ($meta['gst_type'] ?? 'exclusive') === 'inclusive' ? 'inclusive' : 'exclusive';
    $unit_type = $meta['unit_type'] ?? null;

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

    $newItems[$it['pid']] = [
        'pid'             => $it['pid'],
        'qty_pieces'      => $it['qty_pieces'],
        'pieces_per_pack' => $pph,
        'unit_type'       => $unit_type,
        'cost'            => $it['cost'],
        'gst_rate'        => $gst_rate,
        'gst_type'        => $gst_type,
        'taxable_value'   => $taxable_value,
        'gst_amount'      => $gst_amount,
        'total_cost'      => $total_cost,
    ];
}

$grand_total   = round(array_sum(array_column($newItems, 'total_cost')), 6);
$grand_taxable = round(array_sum(array_column($newItems, 'taxable_value')), 6);
$grand_gst     = round(array_sum(array_column($newItems, 'gst_amount')), 6);

$neksomoGodownId = (int) ($db_conn->query(
    "SELECT id FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1"
)->fetch_row()[0] ?? 0);
if (!$neksomoGodownId) {
    redirectWithMessage($editUrl, 'error');
}

// Every product touched by either the old or new line items, so removed
// and newly-added products are both accounted for.
$allProductIds = array_unique(array_merge(array_keys($oldItemsByProduct), array_keys($newItems)));

$warehouseChanged = ($old_warehouse_id !== $new_warehouse_id);

// A product's lot is "untouched" only if nothing has ever been drawn from
// it — checked per product, since one purchase can mix products that have
// and haven't moved on yet.
function neksomo_purchase_lot_untouched(mysqli $db, int $purchaseId, int $productId): bool {
    $stmt = $db->prepare(
        "SELECT qty_purchased, qty_remaining FROM stock_lots WHERE ref_type = 'neksomo_purchase' AND ref_id = ? AND product_id = ?"
    );
    $refId = (string) $purchaseId;
    $stmt->bind_param('si', $refId, $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row === null) return true; // no lot ever recorded — nothing to have consumed
    return (int) $row['qty_remaining'] === (int) $row['qty_purchased'];
}

$anyProductTouched = false;
foreach ($allProductIds as $pid) {
    if (!neksomo_purchase_lot_untouched($db_conn, $purchase_id, $pid)) {
        $anyProductTouched = true;
        break;
    }
}

// A warehouse change requires moving the ENTIRE remaining balance from the
// old warehouse to the new one — only safe when nothing has moved on yet
// for ANY product in this purchase (a partial move would need to reason
// about which specific units are "the ones that moved", which FIFO
// consumption already made ambiguous).
if ($warehouseChanged && $anyProductTouched) {
    redirectWithMessage($editUrl, 'error=warehouse_change_blocked');
}

$db_conn->begin_transaction();
try {
    $stockService = new StockService($db_conn);

    foreach ($allProductIds as $pid) {
        $old = $oldItemsByProduct[$pid] ?? null;
        $new = $newItems[$pid] ?? null;
        $pph = max((int) ($new['pieces_per_pack'] ?? ($old['pieces_per_pack'] ?? 1)), 1);

        $oldQtyPieces = $old ? (int) $old['quantity_pieces'] : 0;
        $newQtyPieces = $new ? (int) $new['qty_pieces'] : 0;
        $delta = $newQtyPieces - $oldQtyPieces;

        if ($warehouseChanged) {
            // Nothing has moved on (checked above) — safe to fully reverse
            // the old warehouse's credit and reapply the full new quantity
            // at the new warehouse, rather than trying to net a delta
            // across two different stock rows.
            if ($oldQtyPieces > 0) {
                neksomo_reverse_pieces(
                    $db_conn, $stockService, $pid, (string) $neksomoGodownId, $pph, $oldQtyPieces,
                    'manuf_purchase_edit_' . $purchase_id . '_' . $pid, $created_by, $old_warehouse_id
                );
            }
            if ($newQtyPieces > 0) {
                neksomo_credit_pieces(
                    $db_conn, $stockService, $pid, (string) $neksomoGodownId, $pph, $newQtyPieces,
                    'manuf_purchase_edit_' . $purchase_id . '_' . $pid, $created_by, $new_warehouse_id
                );
            }
        } elseif ($delta > 0) {
            neksomo_credit_pieces(
                $db_conn, $stockService, $pid, (string) $neksomoGodownId, $pph, $delta,
                'manuf_purchase_edit_' . $purchase_id . '_' . $pid, $created_by, $new_warehouse_id
            );
        } elseif ($delta < 0) {
            // Refuse per-product if this specific reduction would eat into
            // stock that's already moved on (checked precisely here, not
            // just the coarse "anything touched" check above, since a
            // small reduction might still fit within what's provably
            // still on hand even if some OTHER product in this purchase
            // has already moved on).
            $availStmt = $db_conn->prepare(
                "SELECT closing_qty, extra_pieces FROM stock WHERE product_id = ? AND user_type = 'company' AND user_id = ?
                   AND warehouse_id " . ($new_warehouse_id === null ? 'IS NULL' : '= ?')
            );
            $godownIdStr = (string) $neksomoGodownId;
            if ($new_warehouse_id === null) {
                $availStmt->bind_param('is', $pid, $godownIdStr);
            } else {
                $availStmt->bind_param('isi', $pid, $godownIdStr, $new_warehouse_id);
            }
            $availStmt->execute();
            $availRow = $availStmt->get_result()->fetch_assoc();
            $availStmt->close();

            $availablePieces = $availRow ? ((int) $availRow['closing_qty'] * $pph + (int) $availRow['extra_pieces']) : 0;
            if ($availablePieces < abs($delta)) {
                $db_conn->rollback();
                redirectWithMessage($editUrl, 'error=already_consumed');
            }

            neksomo_reverse_pieces(
                $db_conn, $stockService, $pid, (string) $neksomoGodownId, $pph, abs($delta),
                'manuf_purchase_edit_' . $purchase_id . '_' . $pid, $created_by, $new_warehouse_id
            );
        }
        // $delta === 0 and no warehouse change: nothing to do for stock —
        // only cost/GST fields may still differ, handled below.
    }

    // Replace the line items wholesale — simpler and safer than patching
    // individual rows, now that every stock effect has already been
    // reconciled above.
    $db_conn->query("DELETE FROM neksomo_purchase_items WHERE purchase_id = " . (int) $purchase_id);

    $itemStmt = $db_conn->prepare(
        "INSERT INTO neksomo_purchase_items (purchase_id, product_id, quantity_packs, quantity_pieces, cost_per_piece, gst_rate, gst_type, total_cost, taxable_value, gst_amount, stock_ledger_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)"
    );
    foreach ($newItems as $item) {
        $qtyPacks = intdiv($item['qty_pieces'], $item['pieces_per_pack']);
        $itemStmt->bind_param(
            'iiiiddsddd',
            $purchase_id, $item['pid'], $qtyPacks, $item['qty_pieces'], $item['cost'],
            $item['gst_rate'], $item['gst_type'], $item['total_cost'], $item['taxable_value'], $item['gst_amount']
        );
        $itemStmt->execute();
    }
    $itemStmt->close();

    // Reconcile stock_lots for the NEW quantities: for a warehouse change
    // (full reverse+reapply, verified untouched above) the old lot no
    // longer reflects reality, so it's dropped and a fresh one recorded.
    // For a same-warehouse quantity change, the existing lot's own
    // qty_purchased/qty_remaining/rate are adjusted by the same delta so
    // it keeps tracking correctly against whatever's already been
    // consumed from it.
    foreach ($newItems as $item) {
        $pid = $item['pid'];
        $newQtyPacks = intdiv($item['qty_pieces'], $item['pieces_per_pack']);
        $ratePerPack = round($item['cost'] * $item['pieces_per_pack'], 6);

        if ($warehouseChanged) {
            $db_conn->query(
                "DELETE FROM stock_lots WHERE ref_type = 'neksomo_purchase' AND ref_id = '" . (int) $purchase_id . "' AND product_id = " . (int) $pid
            );
            if ($newQtyPacks > 0) {
                StockLots::recordLot(
                    $db_conn, $pid, 'company', (string) $neksomoGodownId,
                    $ratePerPack, $newQtyPacks, $purchase_date,
                    'neksomo_purchase', (string) $purchase_id, $created_by, $new_warehouse_id
                );
            }
            continue;
        }

        $lotStmt = $db_conn->prepare(
            "SELECT id, qty_purchased, qty_remaining FROM stock_lots WHERE ref_type = 'neksomo_purchase' AND ref_id = ? AND product_id = ?"
        );
        $purchaseIdStr = (string) $purchase_id;
        $lotStmt->bind_param('si', $purchaseIdStr, $pid);
        $lotStmt->execute();
        $lotRow = $lotStmt->get_result()->fetch_assoc();
        $lotStmt->close();

        if ($lotRow === null) {
            if ($newQtyPacks > 0) {
                StockLots::recordLot(
                    $db_conn, $pid, 'company', (string) $neksomoGodownId,
                    $ratePerPack, $newQtyPacks, $purchase_date,
                    'neksomo_purchase', (string) $purchase_id, $created_by, $new_warehouse_id
                );
            }
        } else {
            $packDelta = $newQtyPacks - (int) $lotRow['qty_purchased'];
            $newRemaining = (int) $lotRow['qty_remaining'] + $packDelta;
            $updLot = $db_conn->prepare(
                "UPDATE stock_lots SET qty_purchased = ?, qty_remaining = ?, rate = ? WHERE id = ?"
            );
            $updLot->bind_param('iidi', $newQtyPacks, $newRemaining, $ratePerPack, $lotRow['id']);
            $updLot->execute();
            $updLot->close();
        }
    }

    // Update the header. Legacy single-line fields (product_id, quantity_packs,
    // cost_per_piece, total_cost) mirror the FIRST item, same convention Add uses.
    $newItemsList = array_values($newItems);
    $first = $newItemsList[0];
    $firstQtyPacks = intdiv($first['qty_pieces'], $first['pieces_per_pack']);
    $headerStmt = $db_conn->prepare(
        "UPDATE neksomo_manufacturer_purchases
         SET vendor_id = ?, invoice_number = ?, product_id = ?, manufacturer_name = ?, purchase_date = ?, warehouse_id = ?,
             total_amount = ?, total_taxable_value = ?, total_gst_amount = ?, quantity_packs = ?, cost_per_piece = ?, total_cost = ?
         WHERE id = ?"
    );
    $headerStmt->bind_param(
        'isissidddiddi',
        $vendor_id, $inv_number, $first['pid'], $vendor_name, $purchase_date, $new_warehouse_id,
        $grand_total, $grand_taxable, $grand_gst, $firstQtyPacks, $first['cost'], $first['total_cost'], $purchase_id
    );
    $headerStmt->execute();
    $headerStmt->close();

    $db_conn->commit();
    redirectWithMessage('neksomo-manufacturer-purchase-manage.php', 'editDone');
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log('[edit-neksomo-manufacturer-purchase] ' . $e->getMessage());
    redirectWithMessage($editUrl, 'error');
}
