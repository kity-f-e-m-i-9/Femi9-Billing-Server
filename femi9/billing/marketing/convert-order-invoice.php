<?php
include("checksession.php");
include("config.php");
require_once("include/TeamSubtree.php");
require_once(__DIR__ . '/../shared/TpOrderInvoiceLookup.php');
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// Lets a DM/ASM/SM do the same "Convert" a TP can do on their own
// manage-orders.php — pick one of the assigned TP's already-raised invoices
// for this shop and link this Get Order to it. 2026-11-24, user-requested
// ("dm ku location pakkathula convert aprm not convert nu button vechuru...
// ithey tp pannitangalum saei ivarey pannitalum saei").

$order_id = $_POST['order_id'] ?? '';
$inv_id   = $_POST['inv_id'] ?? '';
$ownId    = (int)$markeingSTFID;

if ($order_id === '' || $inv_id === '') {
    $_SESSION['errorMessage'] = "Please pick an invoice to convert this order to.";
    header('Location: manage_order_product.php');
    exit;
}

// This order's ms_id must be inside the caller's own subtree — never trust
// the posted order_id alone to belong to someone this DM can act on.
$allowedSubtreeSet = array_flip(getMsSubtreeIds($db_conn, $ownId));
$stmt = $db_conn->prepare("SELECT ms_id FROM ms_orders WHERE order_id=? LIMIT 1");
$stmt->bind_param('s', $order_id);
$stmt->execute();
$orow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$orow || !isset($allowedSubtreeSet[(int)$orow['ms_id']])) {
    $_SESSION['errorMessage'] = "You don't have permission to convert this order.";
    header('Location: manage_order_product.php');
    exit;
}

$stmtTp = $db_conn->prepare("SELECT tp_id FROM tp_orders WHERE order_id=? LIMIT 1");
$stmtTp->bind_param('s', $order_id);
$stmtTp->execute();
$trow = $stmtTp->get_result()->fetch_assoc();
$stmtTp->close();
if (!$trow || empty($trow['tp_id'])) {
    $_SESSION['errorMessage'] = "This order has no assigned TP.";
    header('Location: manage_order_product.php');
    exit;
}

$error = convertTpOrderToInvoice($db_conn, $order_id, (int)$trow['tp_id'], $inv_id);
if ($error !== null) {
    $_SESSION['errorMessage'] = $error;
} else {
    $_SESSION['successMessage'] = "Order converted to the selected invoice.";
}
header('Location: manage_order_product.php');
exit;
