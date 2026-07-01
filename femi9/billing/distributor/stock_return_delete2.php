<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid_encode = $_REQUEST['returnid'] ?? '';
$returnid_decode = base64_decode($returnid_encode);

if (empty($returnid_decode)) {
    echo "<script>window.location='stock-return-manage.php?deletedone';</script>";
    exit;
}

$stmtHdr = $db_conn->prepare(
    "SELECT from_usertype, from_userid, total, invnumber, status
       FROM user_return_stock WHERE returnid = ?"
);
$stmtHdr->bind_param('s', $returnid_decode);
$stmtHdr->execute();
$header = $stmtHdr->get_result()->fetch_assoc();
$stmtHdr->close();

$stmtItems = $db_conn->prepare(
    "SELECT prid, qty FROM user_return_stock_items WHERE returnid = ?"
);
$stmtItems->bind_param('s', $returnid_decode);
$stmtItems->execute();
$itemsResult = $stmtItems->get_result();
$items = [];
while ($row = $itemsResult->fetch_assoc()) {
    $items[] = $row;
}
$stmtItems->close();

if (!$header) {
    echo "<script>window.location='stock-return-manage.php?deletedone';</script>";
    exit;
}

$fromusertype = (string) $header['from_usertype'];
$fromuserid   = (string) $header['from_userid'];
$total_amount = (float)  $header['total'];
$invnumber    = (string) $header['invnumber'];
$cur_status   = (string) $header['status'];

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

$db_conn->begin_transaction();
try {
    foreach ($items as $it) {
        $prid = (int) $it['prid'];
        $qty  = (int) $it['qty'];
        if ($prid <= 0) continue;

        $stockService->rejectReturn(
            $prid, $fromusertype, $fromuserid, $qty,
            'd_return', $returnid_decode, $createdBy, true
        );
    }

    $stmtDelItems = $db_conn->prepare(
        "DELETE FROM user_return_stock_items WHERE returnid = ?"
    );
    $stmtDelItems->bind_param('s', $returnid_decode);
    $stmtDelItems->execute();
    $stmtDelItems->close();

    if ($invnumber !== '' && $cur_status === 'pending' && $total_amount > 0) {
        $stmtCr = $db_conn->prepare(
            "UPDATE return_credit
                SET credit_amount = GREATEST(0, credit_amount - ?)
              WHERE usertype = ? AND userid = ?"
        );
        $stmtCr->bind_param('dss', $total_amount, $fromusertype, $fromuserid);
        $stmtCr->execute();
        $stmtCr->close();
    }

    $stmtDelHdr = $db_conn->prepare(
        "DELETE FROM user_return_stock WHERE returnid = ?"
    );
    $stmtDelHdr->bind_param('s', $returnid_decode);
    $stmtDelHdr->execute();
    $stmtDelHdr->close();

    $db_conn->commit();

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("stock_return_delete2.php error: " . $e->getMessage());
}

echo "<script>window.location='stock-return-manage.php?deletedone';</script>";
?>
