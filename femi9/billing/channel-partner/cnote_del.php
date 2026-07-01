<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$returnid = base64_decode($_REQUEST['returnid'] ?? '');
$returnid = mysqli_real_escape_string($db_conn, $returnid);

if (empty($returnid)) {
    header("Location: manage-return.php"); exit;
}

$stmt = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE returnid=?");
$stmt->bind_param('s', $returnid);
$stmt->execute();
$stmt->close();

$stmt = $db_conn->prepare("DELETE FROM user_return_stock WHERE returnid=?");
$stmt->bind_param('s', $returnid);
$stmt->execute();
$stmt->close();

$_SESSION['successMessage'] = "Incomplete Return (Credit Note) Deleted Successfully!";
echo "<script>window.location='manage-return.php?DeleteSuccess';</script>";
exit;
?>
