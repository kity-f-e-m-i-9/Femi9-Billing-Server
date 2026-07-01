<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

$returnid        = $_REQUEST['returnid'] ?? '';
$returnid_decode = base64_decode($returnid);
$createdBy       = $_SESSION['LOGIN_USER'] ?? 'system';

// Fetch return header (prepared — no injection)
$stmtHdr = $db_conn->prepare(
    "SELECT from_usertype, from_userid, total FROM user_return_stock WHERE returnid = ?"
);
$stmtHdr->bind_param('s', $returnid_decode);
$stmtHdr->execute();
$header = $stmtHdr->get_result()->fetch_assoc();
$stmtHdr->close();

if (!$header) {
    echo "<script>window.location='stock-return-manage.php?deletedone';</script>";
    exit;
}

$fromusertype = (string) $header['from_usertype'];
$fromuserid   = (string) $header['from_userid'];
$total_amount = (float)  $header['total'];

// Fetch all items (prepared — no injection)
$stmtItems = $db_conn->prepare(
    "SELECT prid, qty FROM user_return_stock_items WHERE returnid = ?"
);
$stmtItems->bind_param('s', $returnid_decode);
$stmtItems->execute();
$items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtItems->close();

$stockService = new StockService($db_conn);

$db_conn->begin_transaction();
try {
    // Restore stock for each item: returnqty ↓ (floor 0), closing_qty ↑ — FOR UPDATE + ledger
    foreach ($items as $item) {
        $prid = (int) $item['prid'];
        $qty  = (int) $item['qty'];
        if ($prid <= 0 || $qty <= 0) continue;

        $stockService->rejectReturn(
            $prid, $fromusertype, $fromuserid, $qty,
            $returnid_decode, $createdBy, true
        );
    }

    // Delete all items
    $stmtDelItems = $db_conn->prepare(
        "DELETE FROM user_return_stock_items WHERE returnid = ?"
    );
    $stmtDelItems->bind_param('s', $returnid_decode);
    $stmtDelItems->execute();
    $stmtDelItems->close();

    // Decrement return_credit if a total was set
    if ($total_amount > 0) {
        $stmtCr = $db_conn->prepare(
            "SELECT credit_amount FROM return_credit WHERE usertype = ? AND userid = ?"
        );
        $stmtCr->bind_param('ss', $fromusertype, $fromuserid);
        $stmtCr->execute();
        $crRow = $stmtCr->get_result()->fetch_assoc();
        $stmtCr->close();

        if ($crRow) {
            $newCredit = max(0.0, (float)$crRow['credit_amount'] - $total_amount);
            $stmtCrUpd = $db_conn->prepare(
                "UPDATE return_credit SET credit_amount = ? WHERE usertype = ? AND userid = ?"
            );
            $stmtCrUpd->bind_param('dss', $newCredit, $fromusertype, $fromuserid);
            $stmtCrUpd->execute();
            $stmtCrUpd->close();
        }
    }

    // Delete the return header
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
