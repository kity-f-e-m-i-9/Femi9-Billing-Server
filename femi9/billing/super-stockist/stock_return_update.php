<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

error_reporting(0);

$returnid        = $_REQUEST['returnid'] ?? '';
$returnid_decode = base64_decode($returnid);
$urlname         = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_REQUEST['urlname'] ?? '');
$updatestatus    = $_REQUEST['updatestatus'] ?? '';
$createdBy       = $_SESSION['LOGIN_USER'] ?? 'system';

// Fetch return header (prepared — no injection)
$stmtHdr = $db_conn->prepare(
    "SELECT from_usertype, from_userid, to_usertype, to_userid, total, status
       FROM user_return_stock WHERE returnid = ?"
);
$stmtHdr->bind_param('s', $returnid_decode);
$stmtHdr->execute();
$header = $stmtHdr->get_result()->fetch_assoc();
$stmtHdr->close();

if (!$header) {
    echo "<script>window.location='{$urlname}.php?updatedsuccess';</script>";
    exit;
}

$fromusertype  = (string) $header['from_usertype'];
$fromuserid    = (string) $header['from_userid'];
$to_usertype   = (string) $header['to_usertype'];
$to_userid     = (string) $header['to_userid'];
$total_amount  = (float)  $header['total'];
$currentStatus = (string) $header['status'];

// Update damaged qty if accept (prepared — no injection)
if ($updatestatus === 'accept' && isset($_REQUEST['rtnid']) && is_array($_REQUEST['rtnid'])) {
    $rtnids       = $_REQUEST['rtnid'];
    $damaged_qtys = is_array($_REQUEST['damaged_qty'] ?? null) ? $_REQUEST['damaged_qty'] : [];
    $stmtDmg = $db_conn->prepare(
        "UPDATE user_return_stock_items SET damaged_qty = ? WHERE id = ?"
    );
    foreach ($rtnids as $i => $rtnid_value) {
        $rtnid_int   = (int) $rtnid_value;
        $damaged_int = (int) ($damaged_qtys[$i] ?? 0);
        if ($rtnid_int > 0) {
            $stmtDmg->bind_param('ii', $damaged_int, $rtnid_int);
            $stmtDmg->execute();
        }
    }
    $stmtDmg->close();
}

// Fetch all items for this return (prepared — no injection)
$stmtItems = $db_conn->prepare(
    "SELECT prid, qty, status FROM user_return_stock_items WHERE returnid = ?"
);
$stmtItems->bind_param('s', $returnid_decode);
$stmtItems->execute();
$items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtItems->close();

$stockService = new StockService($db_conn);

$db_conn->begin_transaction();
try {

    if ($updatestatus === 'reject') {

        foreach ($items as $item) {
            if ($item['status'] !== 'pending') continue;
            $prid = (int) $item['prid'];
            $qty  = (int) $item['qty'];

            // Restore sender: returnqty ↓ (floor 0), closing_qty ↑ — FOR UPDATE + ledger
            $stockService->rejectReturn(
                $prid, $fromusertype, $fromuserid, $qty,
                $returnid_decode, $createdBy, true
            );
        }

        // Decrement credit amount if header was still pending
        if ($currentStatus === 'pending' && $total_amount > 0) {
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

        // Mark items + header rejected
        $stmtMI = $db_conn->prepare(
            "UPDATE user_return_stock_items SET status = 'reject' WHERE returnid = ?"
        );
        $stmtMI->bind_param('s', $returnid_decode);
        $stmtMI->execute();
        $stmtMI->close();

        $stmtMH = $db_conn->prepare(
            "UPDATE user_return_stock SET status = 'reject' WHERE returnid = ?"
        );
        $stmtMH->bind_param('s', $returnid_decode);
        $stmtMH->execute();
        $stmtMH->close();

    } else {
        // Accept path
        foreach ($items as $item) {
            if ($item['status'] !== 'pending') continue;
            $prid = (int) $item['prid'];
            $qty  = (int) $item['qty'];

            // 1. Credit receiver (SS): input_qty ↑, closing_qty ↑ — FOR UPDATE + ledger
            $stockService->acceptReturn(
                $prid, $to_usertype, $to_userid, $qty,
                $returnid_decode, $createdBy, true
            );

            // 2. Decrement sender's returnqty (was missing before — M-1 fix)
            //    rejectReturn: returnqty ↓ (floor 0), closing_qty ↑ — FOR UPDATE + ledger
            $stockService->rejectReturn(
                $prid, $fromusertype, $fromuserid, $qty,
                $returnid_decode, $createdBy, true
            );
        }

        // Mark items + header accepted
        $stmtMI = $db_conn->prepare(
            "UPDATE user_return_stock_items SET status = 'accept' WHERE returnid = ?"
        );
        $stmtMI->bind_param('s', $returnid_decode);
        $stmtMI->execute();
        $stmtMI->close();

        $stmtMH = $db_conn->prepare(
            "UPDATE user_return_stock SET status = 'accept' WHERE returnid = ?"
        );
        $stmtMH->bind_param('s', $returnid_decode);
        $stmtMH->execute();
        $stmtMH->close();
    }

    $db_conn->commit();

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("stock_return_update.php error: " . $e->getMessage());
}

echo "<script>window.location='{$urlname}.php?updatedsuccess';</script>";
?>
