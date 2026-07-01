<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid        = $_REQUEST['returnid'] ?? '';
$returnid_decode = base64_decode($returnid);
$rowid           = (int) base64_decode($_REQUEST['rowid'] ?? '');
$createdBy       = $_SESSION['LOGIN_USER'] ?? 'system';

// Fetch return header to get sender identity (prepared — no injection)
$stmtHdr = $db_conn->prepare(
    "SELECT from_usertype, from_userid, invnumber FROM user_return_stock WHERE returnid = ?"
);
$stmtHdr->bind_param('s', $returnid_decode);
$stmtHdr->execute();
$header = $stmtHdr->get_result()->fetch_assoc();
$stmtHdr->close();

$fromusertype    = $header ? (string) $header['from_usertype']  : '';
$fromuserid      = $header ? (string) $header['from_userid']    : '';
$invnumber       = $header ? (string) $header['invnumber']      : '';
$invnumber_encode = base64_encode($invnumber);

if ($rowid > 0) {
    // Fetch the return item (prepared — no injection)
    $stmtItem = $db_conn->prepare(
        "SELECT prid, qty FROM user_return_stock_items WHERE id = ?"
    );
    $stmtItem->bind_param('i', $rowid);
    $stmtItem->execute();
    $item = $stmtItem->get_result()->fetch_assoc();
    $stmtItem->close();

    if ($item && (int)$item['prid'] > 0) {
        $prid = (int) $item['prid'];
        $qty  = (int) $item['qty'];

        $stockService = new StockService($db_conn);

        $db_conn->begin_transaction();
        try {
            // Delete the item record (rolls back on stock failure)
            $stmtDel = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE id = ?");
            $stmtDel->bind_param('i', $rowid);
            $stmtDel->execute();
            $stmtDel->close();

            // Restore sender: returnqty ↓ (floor 0), closing_qty ↑ — FOR UPDATE + ledger
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
        // Item not found — delete defensively
        $stmtDel = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();
    }
}

echo "<script>window.location='stock_return_add2.php?returnid={$returnid}&&invnumber={$invnumber_encode}&&DeleteSuccess';</script>";
?>
