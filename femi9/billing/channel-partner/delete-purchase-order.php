<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$po_id = (int)($_POST['po_id'] ?? 0);
if ($po_id < 1) {
    header("Location: manage-purchase-orders.php");
    exit;
}

// Only a still-waiting order can be deleted — nothing has moved yet, so
// there's no stock to reverse. A completed order has a real
// pl_godown_transfers row behind it and must never be deleted here.
$s = $db_conn->prepare("DELETE FROM channel_partner_purchase_orders WHERE id = ? AND channel_partner_id = ? AND status = 'waiting'");
$s->bind_param("ii", $po_id, $Login_user_IDvl);
$s->execute();
$deleted = $s->affected_rows > 0;
$s->close();

if ($deleted) {
    // Items cascade with the order — no FK, so clean up explicitly.
    $si = $db_conn->prepare("DELETE FROM channel_partner_purchase_order_items WHERE po_id = ?");
    $si->bind_param("i", $po_id);
    $si->execute();
    $si->close();
    $_SESSION['successMessage'] = 'Purchase order deleted.';
} else {
    $_SESSION['errorMessage'] = 'That purchase order could not be deleted (it may already be completed or cancelled).';
}

header("Location: manage-purchase-orders.php");
exit;
