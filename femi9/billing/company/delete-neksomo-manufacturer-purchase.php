<?php include("checksession.php");
require_once("include/GodownAccess.php");
include("config.php");
require_once("include/StockService.php");

$__usertype = get_login_usertype($db_conn);
if (!in_array($__usertype, ['neksomo', 'admin'], true)) {
    header("Location: dashboard.php");
    exit;
}

$id = (int) base64_decode($_REQUEST['id'] ?? '');
$created_by = $_SESSION['LOGIN_USER'] ?? 'system';

$stmt = $db_conn->prepare("SELECT * FROM neksomo_manufacturer_purchases WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$entry = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$entry) {
    header("Location: neksomo-manufacturer-purchase-manage.php");
    exit;
}

$neksomoGodownId = (int) ($db_conn->query(
    "SELECT id FROM company_godown WHERE gname = 'NEKSOMO HYGIENE INDUSTRIES' LIMIT 1"
)->fetch_row()[0] ?? 0);

$db_conn->begin_transaction();
try {
    if ($neksomoGodownId) {
        $stockService = new StockService($db_conn);
        $stockService->reverseCredit(
            (int) $entry['product_id'],
            'company',
            (string) $neksomoGodownId,
            (int) $entry['quantity_packs'],
            'adjustment',
            'manuf_purchase_delete_' . $id,
            $created_by,
            true
        );
    }

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
