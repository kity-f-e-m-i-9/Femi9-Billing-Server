<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");
require_once("include/StockService.php");
require_once("include/NeksomoPurchaseSchema.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

ensure_neksomo_manufacturer_purchases_warehouse_column($db_conn);

$id = (int) base64_decode($_REQUEST['id'] ?? '');
$created_by = $_SESSION['LOGIN_USER'] ?? 'system';

$stmt = $db_conn->prepare("SELECT * FROM neksomo_manufacturer_purchases WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$purchase = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$purchase) {
    header("Location: neksomo-manufacturer-purchase-manage.php");
    exit;
}

$itemStmt = $db_conn->prepare(
    "SELECT npi.*, p.pieces_per_pack
     FROM neksomo_purchase_items npi
     JOIN products p ON p.id = npi.product_id
     WHERE npi.purchase_id = ?"
);
$itemStmt->bind_param('i', $id);
$itemStmt->execute();
$items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemStmt->close();

$neksomoGodownId = (int) ($db_conn->query(
    "SELECT id FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1"
)->fetch_row()[0] ?? 0);

$warehouseId = $purchase['warehouse_id'] !== null ? (int) $purchase['warehouse_id'] : null;

// Refuse outright if this purchase's own lot has already been partially
// (or fully) drawn from by a later sale — deleting it would corrupt that
// sale's FIFO cost-basis history. Checked before the transaction starts
// so nothing partially applies.
$lotCheckStmt = $db_conn->prepare(
    "SELECT qty_purchased, qty_remaining FROM stock_lots WHERE ref_type = 'neksomo_purchase' AND ref_id = ? AND product_id = ?"
);
$purchaseIdStr = (string) $id;
foreach ($items as $item) {
    $lotCheckStmt->bind_param('si', $purchaseIdStr, $item['product_id']);
    $lotCheckStmt->execute();
    $lotRow = $lotCheckStmt->get_result()->fetch_assoc();
    if ($lotRow !== null && (int) $lotRow['qty_remaining'] < (int) $lotRow['qty_purchased']) {
        $lotCheckStmt->close();
        header("Location: neksomo-manufacturer-purchase-manage.php?error=already_consumed");
        exit;
    }
}
$lotCheckStmt->close();

$db_conn->begin_transaction();
try {
    if ($neksomoGodownId) {
        $stockService = new StockService($db_conn);
        foreach ($items as $item) {
            neksomo_reverse_pieces(
                $db_conn, $stockService,
                (int) $item['product_id'], (string) $neksomoGodownId,
                max((int) $item['pieces_per_pack'], 1), (int) $item['quantity_pieces'],
                'manuf_purchase_delete_' . $id . '_' . $item['product_id'],
                $created_by, $warehouseId
            );

            // Removes the lot this purchase recorded (if any — a purchase
            // that only topped up loose pieces never created one). Safe:
            // already confirmed above that nothing has drawn from it yet.
            // Does NOT reverse any pool-conversion this purchase may have
            // triggered for a mapped company product (see neksomo-
            // manufacturer-purchase-action.php's pool-conversion block) —
            // that's a pre-existing gap, not introduced or fixed here.
            $delLot = $db_conn->prepare(
                "DELETE FROM stock_lots WHERE ref_type = 'neksomo_purchase' AND ref_id = ? AND product_id = ?"
            );
            $delLot->bind_param('si', $purchaseIdStr, $item['product_id']);
            $delLot->execute();
            $delLot->close();
        }
    }

    // neksomo_purchase_items rows cascade via FK on the header delete
    $stmt = $db_conn->prepare("DELETE FROM neksomo_manufacturer_purchases WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    $db_conn->commit();
    header("Location: neksomo-manufacturer-purchase-manage.php?deletedDone");
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log('[delete-neksomo-manufacturer-purchase] ' . $e->getMessage());
    header("Location: neksomo-manufacturer-purchase-manage.php?error");
    exit;
}
