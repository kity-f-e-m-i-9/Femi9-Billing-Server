<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid        = $_REQUEST['returnid'] ?? '';
$returnid_decode = (string) base64_decode($returnid);

$stmtHdr = $db_conn->prepare(
    "SELECT total, from_usertype, from_userid, invnumber FROM user_return_stock WHERE returnid = ?"
);
$stmtHdr->bind_param('s', $returnid_decode);
$stmtHdr->execute();
$hdr = $stmtHdr->get_result()->fetch_assoc();
$stmtHdr->close();

if (!$hdr) {
    echo "<script>window.location='stock-return-manage.php?deletedone';</script>";
    exit;
}

$total_amount = $hdr['total'];
$fromusertype = (string) $hdr['from_usertype'];
$fromuserid   = (string) $hdr['from_userid'];
$invnumber    = (string) $hdr['invnumber'];

$stmtItems = $db_conn->prepare(
    "SELECT prid, qty FROM user_return_stock_items WHERE returnid = ?"
);
$stmtItems->bind_param('s', $returnid_decode);
$stmtItems->execute();
$items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtItems->close();

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

$db_conn->begin_transaction();
try {
    foreach ($items as $row) {
        $prid = (int) $row['prid'];
        $qty  = (int) $row['qty'];
        if ($prid > 0 && $qty > 0) {
            $stockService->rejectReturn(
                $prid, $fromusertype, $fromuserid, $qty,
                $returnid_decode, $createdBy, true
            );
        }
    }

    $stmtDelItems = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE returnid = ?");
    $stmtDelItems->bind_param('s', $returnid_decode);
    $stmtDelItems->execute();
    $stmtDelItems->close();

    if (!empty($invnumber)) {
        $stmtCred = $db_conn->prepare(
            "UPDATE return_credit
                SET credit_amount = GREATEST(0, credit_amount - ?)
              WHERE usertype = ? AND userid = ?"
        );
        $stmtCred->bind_param('dss', $total_amount, $fromusertype, $fromuserid);
        $stmtCred->execute();
        $stmtCred->close();
    }

    $stmtDelHdr = $db_conn->prepare("DELETE FROM user_return_stock WHERE returnid = ?");
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
