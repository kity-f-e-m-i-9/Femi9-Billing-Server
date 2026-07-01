<?php
/**
 * Delete Incomplete Return - Stockist Version
 * Deletes entire return note
 */

include("checksession.php");
include("config.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
date_default_timezone_set("Asia/Kolkata");

$returnid = isset($_REQUEST['returnid']) ? base64_decode($_REQUEST['returnid']) : '';
$returnid = mysqli_real_escape_string($db_conn, $returnid);

if (empty($returnid)) {
    error_log("DELETE RETURN ERROR: Invalid return ID");
    $_SESSION['errorMessage'] = "Invalid return ID";
    header("Location: cnote_manage.php?error=invalid_returnid");
    exit;
}

// Delete return items first
$stmt = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE returnid = ?");
$stmt->bind_param("s", $returnid);
$stmt->execute();
$items_deleted = $stmt->affected_rows;
$stmt->close();

// Delete return master record
$stmt = $db_conn->prepare("DELETE FROM user_return_stock WHERE returnid = ?");
$stmt->bind_param("s", $returnid);
$stmt->execute();
$stmt->close();

error_log("INCOMPLETE RETURN DELETED: Return $returnid, Items deleted: $items_deleted");

$_SESSION['successMessage'] = "Incomplete Return (Credit Note) Deleted Successfully!";
echo "<script>window.location='cnote_manage.php?DeleteSuccess';</script>";
exit;
?>