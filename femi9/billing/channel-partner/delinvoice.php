<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$invtype = $_REQUEST['invtype'] ?? '';
$invuser = $_REQUEST['invuser'] ?? '';
$invid   = mysqli_real_escape_string($db_conn, base64_decode($_REQUEST['invid'] ?? ''));

if ($invtype === 'shop') {
    $cnt = mysqli_fetch_array(mysqli_query($db_conn, "SELECT COUNT(*) AS n FROM user_invoice_items WHERE inv_id='$invid'"))['n'];
    if ((int)$cnt === 0) {
        mysqli_query($db_conn, "DELETE FROM user_invoice WHERE inv_id='$invid'");
        mysqli_query($db_conn, "DELETE FROM receipt WHERE inv_id='$invid'");
        $_SESSION['successMessage'] = "Invoice Deleted!";
        echo "<script>window.location='shop-manage-invoice.php?deletedDone';</script>";
    } else {
        $_SESSION['errorMessage'] = "Cannot delete — invoice has items!";
        echo "<script>window.location='shop-manage-invoice.php';</script>";
    }
} elseif ($invtype === 'customer') {
    $cnt = mysqli_fetch_array(mysqli_query($db_conn, "SELECT COUNT(*) AS n FROM invoice_items WHERE inv_id='$invid'"))['n'];
    if ((int)$cnt === 0) {
        mysqli_query($db_conn, "DELETE FROM invoice WHERE inv_id='$invid'");
        mysqli_query($db_conn, "DELETE FROM receipt WHERE inv_id='$invid'");
        $_SESSION['successMessage'] = "Invoice Deleted!";
        echo "<script>window.location='customer-manage-invoice.php?deletedDone';</script>";
    } else {
        $_SESSION['errorMessage'] = "Cannot delete — invoice has items!";
        echo "<script>window.location='customer-manage-invoice.php';</script>";
    }
} else {
    header("Location: dashboard.php"); exit;
}
