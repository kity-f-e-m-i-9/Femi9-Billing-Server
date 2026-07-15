<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");
require_once("include/StockService.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

/**
 * Reverse a piece-wise purchase quantity from a pack-based stock row —
 * the mirror image of neksomo_credit_pieces() in
 * neksomo-manufacturer-purchase-action.php. Subtracts $qtyPieces from the
 * running loose-piece remainder (stock.extra_pieces); if that would go
 * negative, borrows back whole packs via StockService::reverseCredit() so
 * the remainder lands back in [0, piecesPerPack). Must run inside the
 * caller's transaction.
 */
function neksomo_reverse_pieces(
    mysqli $db, StockService $stockService,
    int $productId, string $godownId, int $piecesPerPack, int $qtyPieces,
    string $refId, string $createdBy
): void {
    $lock = $db->prepare("SELECT extra_pieces FROM stock WHERE product_id = ? AND user_type = 'company' AND user_id = ? FOR UPDATE");
    $lock->bind_param('is', $productId, $godownId);
    $lock->execute();
    $row = $lock->get_result()->fetch_assoc();
    $lock->close();

    if ($row === null) {
        return; // nothing to reverse — no stock row exists for this product
    }

    $net = (int) $row['extra_pieces'] - $qtyPieces;

    if ($net >= 0) {
        $newExtra = $net;
    } else {
        $packsToReverse = (int) ceil(abs($net) / $piecesPerPack);
        $stockService->reverseCredit($productId, 'company', $godownId, $packsToReverse, 'adjustment', $refId, $createdBy, true);
        $newExtra = $net + ($packsToReverse * $piecesPerPack);
    }

    $upd = $db->prepare("UPDATE stock SET extra_pieces = ?, updated_at = NOW() WHERE product_id = ? AND user_type = 'company' AND user_id = ?");
    $upd->bind_param('iis', $newExtra, $productId, $godownId);
    $upd->execute();
    $upd->close();
}

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
                $created_by
            );
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
