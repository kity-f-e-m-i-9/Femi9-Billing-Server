<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$tp_id = (int)$Login_user_IDvl;
$po_id = (int)($_POST['po_id'] ?? 0);

if ($po_id < 1) {
    $_SESSION['errorMessage'] = 'Invalid purchase order.';
    header("Location: manage-purchase-orders.php");
    exit;
}

$stmt = $db_conn->prepare(
    "SELECT id, status FROM tp_purchase_orders WHERE id = ? AND territory_partner_id = ?"
);
$stmt->bind_param('ii', $po_id, $tp_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    $_SESSION['errorMessage'] = 'Purchase order not found.';
    header("Location: manage-purchase-orders.php");
    exit;
}
if ($order['status'] !== 'waiting') {
    $_SESSION['errorMessage'] = 'Only orders that are still waiting can be deleted.';
    header("Location: manage-purchase-orders.php");
    exit;
}

// If any linked payment screenshot has already been converted into a real
// tp_advance_payments ledger entry, block the delete — cascading it away
// would destroy the audit trail for money that's already been credited.
$chk = $db_conn->prepare(
    "SELECT COUNT(*) AS cnt FROM tp_purchase_order_screenshots WHERE po_id = ? AND advance_payment_id IS NOT NULL"
);
$chk->bind_param('i', $po_id);
$chk->execute();
$alreadyCredited = (int)($chk->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;
$chk->close();

if ($alreadyCredited) {
    $_SESSION['errorMessage'] = 'This order cannot be deleted — a payment from it has already been credited to your account. Please contact the company.';
    header("Location: manage-purchase-orders.php");
    exit;
}

// Clean up screenshot files from disk before the DB cascade removes the rows.
$files = $db_conn->prepare("SELECT file_path FROM tp_purchase_order_screenshots WHERE po_id = ?");
$files->bind_param('i', $po_id);
$files->execute();
$fileRows = $files->get_result()->fetch_all(MYSQLI_ASSOC);
$files->close();

$uploadDir = __DIR__ . '/po_screenshots/';
foreach ($fileRows as $f) {
    $path = $uploadDir . basename($f['file_path']);
    if (file_exists($path)) unlink($path);
}

$del = $db_conn->prepare("DELETE FROM tp_purchase_orders WHERE id = ? AND territory_partner_id = ? AND status = 'waiting'");
$del->bind_param('ii', $po_id, $tp_id);
$del->execute();
$ok = $del->affected_rows === 1;
$del->close();

$_SESSION[$ok ? 'successMessage' : 'errorMessage'] = $ok
    ? 'Purchase order deleted.'
    : 'Could not delete this order — it may have already been actioned.';
header("Location: manage-purchase-orders.php");
exit;
