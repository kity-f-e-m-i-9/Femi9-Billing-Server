<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
error_reporting(0);

$rowid = (int) base64_decode($_REQUEST['rowid'] ?? '');

if ($rowid <= 0) {
    $_SESSION['sucMessage'] = "One Internal Stock Transfer Details Deleted Successfully!";
    echo "<script>window.location='manage_internal?deletedDone';</script>";
    exit;
}

// Fetch transfer record before deletion (prepared — no injection)
$stmt = $db_conn->prepare(
    "SELECT prid, qty, from_usertype, from_userid, to_usertype, to_userid, tempid
       FROM internal_transfer_ss WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($record && (int)$record['prid'] > 0) {
    $prid      = (int)    $record['prid'];
    $qty       = (int)    $record['qty'];
    $from_type = (string) $record['from_usertype'];
    $from_id   = (string) $record['from_userid'];
    $to_type   = (string) $record['to_usertype'];
    $to_id     = (string) $record['to_userid'];
    $tempid    = (string) $record['tempid'];
    $createdBy = $_SESSION['LOGIN_USER'] ?? 'system';

    $stockService = new StockService($db_conn);

    $db_conn->begin_transaction();
    try {
        $stmtDel = $db_conn->prepare("DELETE FROM internal_transfer_ss WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();

        // Restore sender: sent_qty ↓ (floor 0), closing_qty ↑ — FOR UPDATE + ledger
        $stockService->reverseTransferOut(
            $prid, $from_type, $from_id, $qty,
            'internal_transfer', $tempid, $createdBy, true
        );

        // Restore receiver: input_qty ↓ (floor 0), closing_qty ↓ (floor 0) — FOR UPDATE + ledger
        $stockService->reverseTransferIn(
            $prid, $to_type, $to_id, $qty,
            'internal_transfer', $tempid, $createdBy, true
        );

        $db_conn->commit();
    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("delete_internal.php error: " . $e->getMessage());
    }
} else {
    $stmtDel = $db_conn->prepare("DELETE FROM internal_transfer_ss WHERE id = ?");
    $stmtDel->bind_param('i', $rowid);
    $stmtDel->execute();
    $stmtDel->close();
}

$_SESSION['sucMessage'] = "One Internal Stock Transfer Details Deleted Successfully!";
echo "<script>window.location='manage_internal?deletedDone';</script>";
?>
