<?php
include("checksession.php");
include("config.php");
error_reporting(0);

if (!isset($_REQUEST['updateCustomer'])) { header("Location: shop-manage-invoice.php"); exit; }

$invid           = mysqli_real_escape_string($db_conn, $_POST['invid']           ?? '');
$invuser         = mysqli_real_escape_string($db_conn, $_POST['invuser']         ?? 'shop');
$old_customer_id = mysqli_real_escape_string($db_conn, $_POST['old_customer_id'] ?? '');
$new_customer_id = mysqli_real_escape_string($db_conn, $_POST['new_customer_id'] ?? '');

if (empty($new_customer_id)) { header("Location: update_customer2.php?InvoiceID=$invid&invuser=$invuser"); exit; }

// Update invoice
mysqli_query($db_conn, "UPDATE user_invoice SET to_user_id='$new_customer_id' WHERE inv_id='$invid'");
// Update items
mysqli_query($db_conn, "UPDATE user_invoice_items SET to_user_id='$new_customer_id' WHERE inv_id='$invid'");

echo "<script>window.location='shop-manage-invoice.php?updatedsuccess';</script>";
