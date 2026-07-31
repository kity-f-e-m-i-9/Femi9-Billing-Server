<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

$id = (int) base64_decode($_REQUEST['id'] ?? '');
if (!$id) {
    header("Location: neksomo-manage-products.php?error");
    exit;
}

// Only ever touch products this login created — never the shared/admin catalog.
$own = $db_conn->prepare("SELECT id FROM products WHERE id = ? AND temp_id LIKE 'NKS-%'");
$own->bind_param('i', $id);
$own->execute();
if ($own->get_result()->num_rows === 0) {
    $own->close();
    header("Location: neksomo-manage-products.php?error");
    exit;
}
$own->close();

// Block a real delete if this product has any recorded activity — purchases,
// sale/purchase rates, a mapping to a company product, or non-zero stock
// movement — since removing the row would leave all of that pointing at a
// product that no longer exists. Deactivate (already on this page) is the
// right tool once a product has real history; delete is only for a
// mistaken or genuinely unused entry.
function neksomo_product_has_activity(mysqli $db, int $id): bool {
    $checks = [
        "SELECT 1 FROM neksomo_purchase_items WHERE product_id = ? LIMIT 1",
        "SELECT 1 FROM neksomo_llp_piece_rates WHERE product_id = ? LIMIT 1",
        "SELECT 1 FROM neksomo_llp_piece_purchase_rates WHERE product_id = ? LIMIT 1",
        "SELECT 1 FROM neksomo_product_mapping WHERE neksomo_product_id = ? LIMIT 1",
        "SELECT 1 FROM stock WHERE product_id = ? AND (input_qty<>0 OR sales_qty<>0 OR sent_qty<>0 OR returnqty<>0 OR closing_qty<>0 OR extra_pieces<>0) LIMIT 1",
    ];
    foreach ($checks as $sql) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $hasRow = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($hasRow) return true;
    }
    return false;
}

if (neksomo_product_has_activity($db_conn, $id)) {
    header("Location: neksomo-manage-products.php?deleteblocked");
    exit;
}

$db_conn->begin_transaction();
try {
    // Remove the zeroed stock row Add Product creates up front (safe — the
    // activity check above already confirmed it's still all zeros).
    $delStock = $db_conn->prepare("DELETE FROM stock WHERE product_id = ? AND user_type = 'company'");
    $delStock->bind_param('i', $id);
    $delStock->execute();
    $delStock->close();

    $delProduct = $db_conn->prepare("DELETE FROM products WHERE id = ? AND temp_id LIKE 'NKS-%'");
    $delProduct->bind_param('i', $id);
    $delProduct->execute();
    $ok = $delProduct->affected_rows > 0;
    $delProduct->close();

    if (!$ok) {
        throw new Exception('DELETE_FAILED');
    }
    $db_conn->commit();
    header("Location: neksomo-manage-products.php?deletedDone");
} catch (\Throwable $e) {
    $db_conn->rollback();
    header("Location: neksomo-manage-products.php?error");
}
exit;
