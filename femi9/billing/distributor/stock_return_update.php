<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
error_reporting(0);

$returnid_encode = $_REQUEST['returnid'] ?? '';
$returnid_decode = base64_decode($returnid_encode);
$urlname         = preg_replace('/[^A-Za-z0-9_\-]/', '', $_REQUEST['urlname'] ?? '');
$updatestatus    = $_REQUEST['updatestatus'] ?? '';

if (empty($returnid_decode)) {
    echo "<script>window.location='{$urlname}.php';</script>";
    exit;
}

$stmt = $db_conn->prepare(
    "SELECT from_usertype, from_userid, to_usertype, to_userid, total, status
       FROM user_return_stock WHERE returnid = ?"
);
$stmt->bind_param('s', $returnid_decode);
$stmt->execute();
$header = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$header) {
    echo "<script>window.location='{$urlname}.php?notfound';</script>";
    exit;
}

$fromusertype = $header['from_usertype'];
$fromuserid   = $header['from_userid'];
$to_usertype  = $header['to_usertype'];
$to_userid    = $header['to_userid'];
$total_amount = (float) $header['total'];
$cur_status   = $header['status'];

$stmtItems = $db_conn->prepare(
    "SELECT prid, qty, status FROM user_return_stock_items WHERE returnid = ?"
);
$stmtItems->bind_param('s', $returnid_decode);
$stmtItems->execute();
$itemsResult = $stmtItems->get_result();
$items = [];
while ($row = $itemsResult->fetch_assoc()) {
    $items[] = $row;
}
$stmtItems->close();

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

$db_conn->begin_transaction();
try {

    if ($updatestatus === 'reject') {
        // ── REJECT ────────────────────────────────────────────────────────────
        foreach ($items as $it) {
            if ($it['status'] !== 'pending') continue;
            $prid = (int) $it['prid'];
            $qty  = (int) $it['qty'];

            $stockService->rejectReturn(
                $prid, $fromusertype, $fromuserid, $qty,
                'd_return', $returnid_decode, $createdBy, true
            );

            $stmtUpd = $db_conn->prepare(
                "UPDATE user_return_stock_items SET status = 'reject'
                  WHERE returnid = ? AND prid = ?"
            );
            $stmtUpd->bind_param('si', $returnid_decode, $prid);
            $stmtUpd->execute();
            $stmtUpd->close();
        }

        if ($cur_status === 'pending' && $total_amount > 0) {
            $stmtCr = $db_conn->prepare(
                "UPDATE return_credit
                    SET credit_amount = GREATEST(0, credit_amount - ?)
                  WHERE usertype = ? AND userid = ?"
            );
            $stmtCr->bind_param('dss', $total_amount, $fromusertype, $fromuserid);
            $stmtCr->execute();
            $stmtCr->close();
        }

        $stmtHdr = $db_conn->prepare(
            "UPDATE user_return_stock SET status = 'reject' WHERE returnid = ?"
        );
        $stmtHdr->bind_param('s', $returnid_decode);
        $stmtHdr->execute();
        $stmtHdr->close();

    } else {
        // ── ACCEPT ────────────────────────────────────────────────────────────
        foreach ($items as $it) {
            if ($it['status'] !== 'pending') continue;
            $prid = (int) $it['prid'];
            $qty  = (int) $it['qty'];

            // Credit receiver (the distributor accepting stock back)
            $stockService->acceptReturn(
                $prid, $to_usertype, $to_userid, $qty,
                'd_return', $returnid_decode, $createdBy, true
            );

            // M-1 fix: decrement sender's returnqty & restore closing_qty
            $stockService->rejectReturn(
                $prid, $fromusertype, $fromuserid, $qty,
                'd_return', $returnid_decode, $createdBy, true
            );

            $stmtUpd = $db_conn->prepare(
                "UPDATE user_return_stock_items SET status = 'accept'
                  WHERE returnid = ? AND prid = ?"
            );
            $stmtUpd->bind_param('si', $returnid_decode, $prid);
            $stmtUpd->execute();
            $stmtUpd->close();
        }

        $stmtHdr = $db_conn->prepare(
            "UPDATE user_return_stock SET status = 'accept' WHERE returnid = ?"
        );
        $stmtHdr->bind_param('s', $returnid_decode);
        $stmtHdr->execute();
        $stmtHdr->close();
    }

    $db_conn->commit();

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("stock_return_update.php error: " . $e->getMessage());
}

echo "<script>window.location='{$urlname}.php?updatedsuccess';</script>";
?>
