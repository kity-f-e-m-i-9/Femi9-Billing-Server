<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
error_reporting(0);

$returnid        = $_REQUEST['returnid'] ?? '';
$returnid_decode = (string) base64_decode($returnid);
$urlname         = $_REQUEST['urlname']      ?? 'stock_return_pending';
$updatestatus    = $_REQUEST['updatestatus'] ?? '';

// Fetch return header
$stmtHdr = $db_conn->prepare(
    "SELECT total, from_usertype, from_userid, to_usertype, to_userid, status
       FROM user_return_stock WHERE returnid = ?"
);
$stmtHdr->bind_param('s', $returnid_decode);
$stmtHdr->execute();
$hdr = $stmtHdr->get_result()->fetch_assoc();
$stmtHdr->close();

if (!$hdr) {
    echo "<script>window.location='{$urlname}.php?updatedsuccess';</script>";
    exit;
}

$total_amount  = $hdr['total'];
$fromusertype  = $hdr['from_usertype'];
$fromuserid    = $hdr['from_userid'];
$to_usertype   = $hdr['to_usertype'];
$to_userid     = $hdr['to_userid'];
$currentStatus = $hdr['status'];

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

// ── UPDATE DAMAGED QTY (accept path pre-step) ─────────────────────────────────
if ($updatestatus === 'accept' && isset($_REQUEST['rtnid'])) {
    $rtnids      = $_REQUEST['rtnid']       ?? [];
    $damaged_qtys = $_REQUEST['damaged_qty'] ?? [];
    if (is_array($rtnids)) {
        $stmtDmg = $db_conn->prepare(
            "UPDATE user_return_stock_items SET damaged_qty = ? WHERE id = ?"
        );
        foreach ($rtnids as $i => $rtnid_value) {
            $rtnid_value   = (int) $rtnid_value;
            $damaged_value = (int) ($damaged_qtys[$i] ?? 0);
            if ($rtnid_value > 0) {
                $stmtDmg->bind_param('ii', $damaged_value, $rtnid_value);
                $stmtDmg->execute();
            }
        }
        $stmtDmg->close();
    }
}

// ── REJECT ────────────────────────────────────────────────────────────────────
if ($updatestatus === 'reject') {

    $stmtItems = $db_conn->prepare(
        "SELECT prid, qty, status FROM user_return_stock_items WHERE returnid = ?"
    );
    $stmtItems->bind_param('s', $returnid_decode);
    $stmtItems->execute();
    $items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtItems->close();

    $db_conn->begin_transaction();
    try {
        $stmtSetReject = $db_conn->prepare(
            "UPDATE user_return_stock_items SET status = 'reject' WHERE returnid = ? AND prid = ?"
        );

        foreach ($items as $row) {
            $prid   = (int)    $row['prid'];
            $qty    = (int)    $row['qty'];
            $status = (string) $row['status'];

            if ($status === 'pending') {
                // Restore sender: returnqty ↓ (floor 0), closing_qty ↑ — FOR UPDATE + ledger
                $stockService->rejectReturn(
                    $prid, $fromusertype, $fromuserid, $qty,
                    $returnid_decode, $createdBy, true
                );
            }

            $stmtSetReject->bind_param('si', $returnid_decode, $prid);
            $stmtSetReject->execute();
        }
        $stmtSetReject->close();

        // Decrement return_credit if header was pending
        if ($currentStatus === 'pending') {
            $stmtCred = $db_conn->prepare(
                "UPDATE return_credit
                    SET credit_amount = GREATEST(0, credit_amount - ?)
                  WHERE usertype = ? AND userid = ?"
            );
            $stmtCred->bind_param('dss', $total_amount, $fromusertype, $fromuserid);
            $stmtCred->execute();
            $stmtCred->close();
        }

        $stmtUpdHdr = $db_conn->prepare(
            "UPDATE user_return_stock SET status = 'reject' WHERE returnid = ?"
        );
        $stmtUpdHdr->bind_param('s', $returnid_decode);
        $stmtUpdHdr->execute();
        $stmtUpdHdr->close();

        $db_conn->commit();
    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("stock_return_update.php reject error: " . $e->getMessage());
    }

} else {

    // ── ACCEPT ────────────────────────────────────────────────────────────────
    $stmtItems = $db_conn->prepare(
        "SELECT prid, qty, status FROM user_return_stock_items WHERE returnid = ?"
    );
    $stmtItems->bind_param('s', $returnid_decode);
    $stmtItems->execute();
    $items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtItems->close();

    $db_conn->begin_transaction();
    try {
        $stmtSetAccept = $db_conn->prepare(
            "UPDATE user_return_stock_items SET status = 'accept' WHERE returnid = ? AND prid = ?"
        );

        foreach ($items as $row) {
            $prid   = (int)    $row['prid'];
            $qty    = (int)    $row['qty'];
            $status = (string) $row['status'];

            if ($status === 'pending') {
                // Credit receiver: input_qty ↑, closing_qty ↑ — FOR UPDATE + ledger
                $stockService->acceptReturn(
                    $prid, $to_usertype, $to_userid, $qty,
                    $returnid_decode, $createdBy, true
                );
                // M-1 fix: decrement sender's returnqty (returnqty ↓ floor 0, closing_qty ↑)
                $stockService->rejectReturn(
                    $prid, $fromusertype, $fromuserid, $qty,
                    $returnid_decode, $createdBy, true
                );
            }

            $stmtSetAccept->bind_param('si', $returnid_decode, $prid);
            $stmtSetAccept->execute();
        }
        $stmtSetAccept->close();

        $stmtUpdHdr = $db_conn->prepare(
            "UPDATE user_return_stock SET status = 'accept' WHERE returnid = ?"
        );
        $stmtUpdHdr->bind_param('s', $returnid_decode);
        $stmtUpdHdr->execute();
        $stmtUpdHdr->close();

        $db_conn->commit();
    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("stock_return_update.php accept error: " . $e->getMessage());
    }
}

echo "<script>window.location='{$urlname}.php?updatedsuccess';</script>";
?>
