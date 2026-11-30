<?php
include("checksession.php");
include("config.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");
require_once(__DIR__ . '/../shared/TpOrderInvoiceLookup.php');

// Links a "Got Order" field visit to an invoice the TP has ALREADY raised to
// that same shop — replaces the old "Invoice" button, which auto-generated a
// brand new invoice from the order's own captured product/qty lines. Here
// the TP instead picks from their real, already-created invoices for that
// shop (manage-orders.php's Convert modal), so no new user_invoice row is
// ever created by this file — it only sets tp_orders.invoiced_inv_id, same
// column the old flow used, so the existing Pending/Incomplete/Completed
// status logic on manage-orders.php keeps working unchanged. Validation
// logic lives in shared/TpOrderInvoiceLookup.php so the DM's own Convert
// button (marketing/convert-order-invoice.php) stays identical. 2026-11-24,
// user-requested.

$order_id = $_POST['order_id'] ?? '';
$inv_id   = $_POST['inv_id'] ?? '';
$tp_id    = (int)$Login_user_IDvl;

if ($order_id === '' || $inv_id === '') {
    $_SESSION['errorMessage'] = "Please pick an invoice to convert this order to.";
    header('Location: manage-orders.php');
    exit;
}

$error = convertTpOrderToInvoice($db_conn, $order_id, $tp_id, $inv_id);
if ($error !== null) {
    $_SESSION['errorMessage'] = $error;
    header('Location: manage-orders.php');
    exit;
}

$_SESSION['successMessage'] = "Order converted to the selected invoice.";
header('Location: manage-orders.php');
exit;
