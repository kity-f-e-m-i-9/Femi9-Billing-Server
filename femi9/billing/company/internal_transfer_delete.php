<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

error_reporting(0);

$rowid  = (int) base64_decode($_REQUEST['Roowid'] ?? '');
$tempid = $_REQUEST['tempid'] ?? '';

if ($rowid <= 0) {
    $_SESSION['sucMessage'] = "Invalid record.";
    echo "<script>window.location='internal_transfer_details?deletedDone&&tempid=$tempid';</script>";
    exit;
}

// Fetch the transfer row before deletion (prepared statement — no injection)
$stmt = $db_conn->prepare(
    "SELECT product_id, qty, send_from, send_to FROM internal_transfer WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row) {
    $product_id = (int)    $row['product_id'];
    $qty        = (int)    $row['qty'];
    $send_from  = (string) $row['send_from'];
    $send_to    = (string) $row['send_to'];

    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    $db_conn->begin_transaction();
    try {
        // Delete the line record first (inside transaction so it rolls back on stock failure)
        $stmtDel = $db_conn->prepare("DELETE FROM internal_transfer WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();

        // Restore source godown stock (sent_qty ↓, closing_qty ↑) — FOR UPDATE + ledger
        $stockService->reverseTransferOut(
            $product_id, $Login_user_TYPEvl, $send_from, $qty,
            'transfer', $tempid, $createdBy,
            true
        );

        // Remove destination godown stock (input_qty ↓, closing_qty ↓) — FOR UPDATE + ledger
        $stockService->reverseTransferIn(
            $product_id, $Login_user_TYPEvl, $send_to, $qty,
            'transfer', $tempid, $createdBy,
            true
        );

        $db_conn->commit();

    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("internal_transfer_delete error: " . $e->getMessage());
        $_SESSION['errorMessage'] = "Delete failed. Please try again.";
        echo "<script>window.location='internal_transfer_details?deletedDone&&tempid=$tempid';</script>";
        exit;
    }
} else {
    // Row not found — delete defensively
    $stmtDel = $db_conn->prepare("DELETE FROM internal_transfer WHERE id = ?");
    $stmtDel->bind_param('i', $rowid);
    $stmtDel->execute();
    $stmtDel->close();
}

$_SESSION['sucMessage'] = "One Internal Stock Transfer Details Deleted Successfully!";
echo "<script>window.location='internal_transfer_details?deletedDone&&tempid=$tempid';</script>";
