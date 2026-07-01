<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

error_reporting(0);

$rowid = (int) base64_decode($_REQUEST['Roowid'] ?? '');

if ($rowid <= 0) {
    echo "<script>window.location='manage-input?deletedDone';</script>";
    exit;
}

$stmt = $db_conn->prepare(
    "SELECT product_id, input_qty, usertype, userid, tempid FROM input_stock_users WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($record && (int)$record['product_id'] > 0) {
    $product_id = (int)    $record['product_id'];
    $input_qty  = (int)    $record['input_qty'];
    $usertype   = (string) $record['usertype'];
    $userid     = (string) $record['userid'];
    $tempid     = (string) $record['tempid'];

    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    $db_conn->begin_transaction();
    try {
        $stmtDel = $db_conn->prepare("DELETE FROM input_stock_users WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();

        $stockService->reverseCredit(
            $product_id, $usertype, $userid, $input_qty,
            'd_input', $tempid, $createdBy,
            true
        );

        $db_conn->commit();

    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("delete-input.php error: " . $e->getMessage());
    }
} else {
    $stmtDel = $db_conn->prepare("DELETE FROM input_stock_users WHERE id = ?");
    $stmtDel->bind_param('i', $rowid);
    $stmtDel->execute();
    $stmtDel->close();
}

echo "<script>window.location='manage-input?deletedDone';</script>";
?>
