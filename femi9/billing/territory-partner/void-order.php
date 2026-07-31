<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

// Lets a TP directly void a "Get Order" field visit before it's ever turned
// into an invoice — e.g. the shop later says they don't want the product.
// Voiding here is not the same as deleting: the visit stays visible with a
// Voided marker instead of disappearing, and (unlike the invoice) there's no
// stock to reverse since nothing was ever deducted at this stage.

$order_id = $_REQUEST['order_id'] ?? '';
$tp_id    = (int)$Login_user_IDvl;
$tp_id_str = (string)$tp_id;

if ($order_id === '') {
    header('Location: manage-orders.php');
    exit;
}

$stmt = $db_conn->prepare(
    "SELECT id, new_order, invoiced_inv_id, voided_at FROM tp_orders WHERE order_id=? AND tp_id=?"
);
$stmt->bind_param('si', $order_id, $tp_id);
$stmt->execute();
$lines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($lines)) {
    $_SESSION['errorMessage'] = "Order not found.";
    header('Location: manage-orders.php');
    exit;
}
if ($lines[0]['new_order'] !== 'yes') {
    $_SESSION['errorMessage'] = "This visit has no order to void.";
    header('Location: manage-orders.php');
    exit;
}
if (!empty($lines[0]['invoiced_inv_id'])) {
    $_SESSION['errorMessage'] = "This order has already been converted to an invoice — void the invoice instead.";
    header('Location: manage-orders.php');
    exit;
}
if (!empty($lines[0]['voided_at'])) {
    $_SESSION['errorMessage'] = "This order has already been voided.";
    header('Location: manage-orders.php');
    exit;
}

$stmtVoid = $db_conn->prepare(
    "UPDATE tp_orders SET voided_at=NOW(), voided_by_user_type=?, voided_by_user_id=? WHERE order_id=? AND tp_id=?"
);
$stmtVoid->bind_param('sssi', $Login_user_TYPEvl, $tp_id_str, $order_id, $tp_id);
$stmtVoid->execute();
$stmtVoid->close();

$_SESSION['successMessage'] = "Order voided.";
header('Location: manage-orders.php');
exit;
