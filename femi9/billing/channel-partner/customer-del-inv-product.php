<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$inv_id_encode = $_REQUEST['inv_id'] ?? '';
$rowid         = (int)base64_decode($_REQUEST['rowid'] ?? '');

if ($rowid > 0) {
    $item = mysqli_fetch_array(mysqli_query($db_conn, "SELECT pr_id, qty FROM invoice_items WHERE id='$rowid' LIMIT 1"));
    if ($item) {
        mysqli_query($db_conn, "DELETE FROM invoice_items WHERE id='$rowid'");
    }
}

echo "<script>window.location='customer-invoice-add.php?InvoiceID=$inv_id_encode&&ActionRemove';</script>";
