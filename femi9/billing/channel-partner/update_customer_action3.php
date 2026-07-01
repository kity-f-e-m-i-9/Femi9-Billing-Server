<?php
include("checksession.php");
include("config.php");
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>window.location='customer-manage-invoice.php';</script>"; exit;
}

$invid           = $_POST['invid']           ?? '';
$invuser         = $_POST['invuser']         ?? 'customer';
$old_customer_id = $_POST['old_customer_id'] ?? '';
$new_customer_id = $_POST['new_customer_id'] ?? '';

if ($old_customer_id == $new_customer_id) {
    echo "<script>window.location='update_customer3.php?invuser=$invuser&&InvoiceID=$invid&&alreadyexists';</script>";
    exit;
}

mysqli_query($db_conn, "UPDATE invoice SET customer_id='$new_customer_id' WHERE inv_id='$invid'");
mysqli_query($db_conn, "UPDATE invoice_items SET customer_id='$new_customer_id' WHERE inv_id='$invid'");
mysqli_query($db_conn, "UPDATE receipt SET to_user_id='$new_customer_id' WHERE inv_id='$invid'");

echo "<script>window.location='update_customer3.php?invuser=$invuser&&InvoiceID=$invid&&updatedsuccess';</script>";
