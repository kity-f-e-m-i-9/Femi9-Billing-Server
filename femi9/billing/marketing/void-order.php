<?php
include("checksession.php");
include("config.php");
require_once("include/TeamSubtree.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// DM-side "Not Convert" — mirrors territory-partner/void-order.php, with a
// subtree-based permission check instead of a TP session (the TP who owns
// the order is looked up server-side, never trusted from the request).
// 2026-11-24, user-requested.

$order_id    = $_REQUEST['order_id'] ?? '';
$void_reason = trim($_REQUEST['void_reason'] ?? '');
$ownId       = (int)$markeingSTFID;

if ($order_id === '') {
    header('Location: manage_order_product.php');
    exit;
}
if ($void_reason === '') {
    $_SESSION['errorMessage'] = "Please enter a reason for not converting the order.";
    header('Location: manage_order_product.php');
    exit;
}

$allowedSubtreeSet = array_flip(getMsSubtreeIds($db_conn, $ownId));
$stmt = $db_conn->prepare("SELECT ms_id FROM ms_orders WHERE order_id=? LIMIT 1");
$stmt->bind_param('s', $order_id);
$stmt->execute();
$orow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$orow || !isset($allowedSubtreeSet[(int)$orow['ms_id']])) {
    $_SESSION['errorMessage'] = "You don't have permission to update this order.";
    header('Location: manage_order_product.php');
    exit;
}

$stmtTp = $db_conn->prepare("SELECT tp_id, new_order, invoiced_inv_id, voided_at FROM tp_orders WHERE order_id=? LIMIT 1");
$stmtTp->bind_param('s', $order_id);
$stmtTp->execute();
$trow = $stmtTp->get_result()->fetch_assoc();
$stmtTp->close();

if (!$trow) {
    $_SESSION['errorMessage'] = "Order not found.";
    header('Location: manage_order_product.php');
    exit;
}
if ($trow['new_order'] !== 'yes') {
    $_SESSION['errorMessage'] = "This visit has no order to void.";
    header('Location: manage_order_product.php');
    exit;
}
if (!empty($trow['invoiced_inv_id'])) {
    $_SESSION['errorMessage'] = "This order has already been converted to an invoice — void the invoice instead.";
    header('Location: manage_order_product.php');
    exit;
}
if (!empty($trow['voided_at'])) {
    $_SESSION['errorMessage'] = "This order has already been marked Not Converted.";
    header('Location: manage_order_product.php');
    exit;
}

$tp_id = (int)$trow['tp_id'];
$voidedByType = 'marketing';
$voidedByStr = (string)$ownId;

$stmtVoid = $db_conn->prepare(
    "UPDATE tp_orders SET voided_at=NOW(), voided_by_user_type=?, voided_by_user_id=?, void_reason=? WHERE order_id=? AND tp_id=?"
);
$stmtVoid->bind_param('ssssi', $voidedByType, $voidedByStr, $void_reason, $order_id, $tp_id);
$stmtVoid->execute();
$stmtVoid->close();

$_SESSION['successMessage'] = "Order marked as Not Converted.";
header('Location: manage_order_product.php');
exit;
