<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid        = $_REQUEST['returnid'] ?? '';
$returnid_decode = (string) base64_decode($returnid);

$rowid        = $_REQUEST['rowid'] ?? '';
$rowid_decode = (int) base64_decode($rowid);

$stmtHdr = $db_conn->prepare(
    "SELECT from_usertype, from_userid, invnumber FROM user_return_stock WHERE returnid = ?"
);
$stmtHdr->bind_param('s', $returnid_decode);
$stmtHdr->execute();
$hdr = $stmtHdr->get_result()->fetch_assoc();
$stmtHdr->close();

$fromusertype     = (string) ($hdr['from_usertype'] ?? '');
$fromuserid       = (string) ($hdr['from_userid']   ?? '');
$invnumber        = (string) ($hdr['invnumber']      ?? '');
$invnumber_encode = base64_encode($invnumber);

if ($rowid_decode <= 0) {
    echo "<script>window.location='stock_return_add2.php?returnid={$returnid}&&invnumber={$invnumber_encode}&&DeleteSuccess';</script>";
    exit;
}

$stmtItem = $db_conn->prepare(
    "SELECT prid, qty FROM user_return_stock_items WHERE id = ?"
);
$stmtItem->bind_param('i', $rowid_decode);
$stmtItem->execute();
$item = $stmtItem->get_result()->fetch_assoc();
$stmtItem->close();

if ($item && (int)$item['prid'] > 0) {
    $prid      = (int)    $item['prid'];
    $qty       = (int)    $item['qty'];
    $createdBy = $_SESSION['LOGIN_USER'] ?? 'system';

    $stockService = new StockService($db_conn);

    $db_conn->begin_transaction();
    try {
        $stmtDel = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE id = ?");
        $stmtDel->bind_param('i', $rowid_decode);
        $stmtDel->execute();
        $stmtDel->close();

        $stockService->rejectReturn(
            $prid, $fromusertype, $fromuserid, $qty,
            $returnid_decode, $createdBy, true
        );

        $db_conn->commit();
    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("stock_return_delete.php error: " . $e->getMessage());
    }
} else {
    $stmtDel = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE id = ?");
    $stmtDel->bind_param('i', $rowid_decode);
    $stmtDel->execute();
    $stmtDel->close();
}

echo "<script>window.location='stock_return_add2.php?returnid={$returnid}&&invnumber={$invnumber_encode}&&DeleteSuccess';</script>";
?>
