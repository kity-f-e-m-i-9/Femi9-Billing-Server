<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

if (!isset($_REQUEST['add-record'])) {
    echo "<script>window.location='add-input';</script>";
    exit;
}

$usertype   = (string) $Login_user_TYPEvl;
$userid     = (string) $Login_user_IDvl;
$tempid     = preg_replace('/[^A-Z0-9\/]/', '', strtoupper($_REQUEST['tempid'] ?? ''));
$product_id = (int)    ($_REQUEST['product_id']  ?? 0);
$input_qty  = (int)    str_replace("'", '', $_REQUEST['input_qty'] ?? '0');
$remarks    = str_replace("'", "&#39;", $_REQUEST['remarks'] ?? '');
$input_date = date("Y-m-d", strtotime($_REQUEST['input_date'] ?? 'now'));
$createdBy  = $_SESSION['LOGIN_USER'] ?? 'system';

if ($product_id <= 0 || $input_qty <= 0) {
    echo "<script>window.location='add-input?invalid';</script>";
    exit;
}

$stmtChk = $db_conn->prepare(
    "SELECT COUNT(*) AS n FROM input_stock_users WHERE tempid = ?"
);
$stmtChk->bind_param('s', $tempid);
$stmtChk->execute();
$exists = (int)$stmtChk->get_result()->fetch_assoc()['n'];
$stmtChk->close();

if ($exists > 0) {
    echo "<script>window.location='add-input?alreadyexists';</script>";
    exit;
}

$stockService = new StockService($db_conn);

$db_conn->begin_transaction();
try {
    $stmtIns = $db_conn->prepare(
        "INSERT INTO input_stock_users (tempid, usertype, userid, product_id, input_qty, input_date, remarks)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmtIns->bind_param('sssiiss', $tempid, $usertype, $userid, $product_id, $input_qty, $input_date, $remarks);
    $stmtIns->execute();
    $stmtIns->close();

    $stockService->credit(
        $product_id, $usertype, $userid, $input_qty,
        'd_input', $tempid, $createdBy,
        true
    );

    $db_conn->commit();
    echo "<script>window.location='manage-input?addesuccess';</script>";

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("input-action.php error: " . $e->getMessage());
    echo "<script>window.location='add-input?saveerror';</script>";
}
?>
