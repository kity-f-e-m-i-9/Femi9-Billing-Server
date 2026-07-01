<?php
include("checksession.php");
include("config.php");
include("RemoveSpecialChar.php");
error_reporting(0);

if (!isset($_REQUEST['updateInvoiceNum'])) {
    echo "<script>window.location='dashboard.php';</script>"; exit;
}

$invuser   = $_POST['invuser']   ?? '';
$InvoiceID = base64_decode($_POST['InvoiceID'] ?? '');
$action    = $_POST['action']    ?? '';
$gid       = $_POST['gid']       ?? '';
$tblenme   = $_POST['tblenme']   ?? '1';
$redirurl  = $_POST['redirurl']  ?? 'shop-invoice-add';

$invnumber = RemoveSpecialChar($_POST['invnumber'] ?? '');
$invnumber = str_replace("'", '', $invnumber);

if ($tblenme == '1') {
    $tablename = 'user_invoice';
    $chk = mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT COUNT(*) AS n FROM user_invoice WHERE inv_number='$invnumber' AND from_user_type='$Login_user_TYPEvl' AND from_user_id='$Login_user_IDvl'"));
} else {
    $tablename = 'invoice';
    $chk = mysqli_fetch_array(mysqli_query($db_conn,
        "SELECT COUNT(*) AS n FROM invoice WHERE inv_number='$invnumber' AND user_type='$Login_user_TYPEvl' AND user_id='$Login_user_IDvl'"));
}

$InvoiceID_enc = base64_encode($InvoiceID);
if ((int)$chk['n'] === 0) {
    mysqli_query($db_conn, "UPDATE $tablename SET inv_number='$invnumber' WHERE inv_id='$InvoiceID'");
    echo "<script>window.location='{$redirurl}.php?invuser=$invuser&&InvoiceID={$InvoiceID_enc}&&action=$action&&gid=$gid&&InvoiceUpdatedSuccess';</script>";
} else {
    echo "<script>window.location='{$redirurl}.php?invuser=$invuser&&InvoiceID={$InvoiceID_enc}&&action=$action&&gid=$gid&&invoicealready';</script>";
}
