<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

error_reporting(0);

$rowid = (int) base64_decode($_REQUEST['Roowid'] ?? '');

if ($rowid <= 0) {
    echo "<script>window.location='manage-input?deletedDone';</script>";
    exit;
}

// Fetch the input_stock record before deletion (prepared — no injection)
$stmt = $db_conn->prepare(
    "SELECT product_id, input_qty, godownid, tempid FROM input_stock WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($record && (int)$record['product_id'] > 0) {
    $product_id = (int)    $record['product_id'];
    $input_qty  = (int)    $record['input_qty'];
    $godownid   = (string) $record['godownid'];
    $tempid     = (string) $record['tempid'];

    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    $db_conn->begin_transaction();
    try {
        // Delete the record first (rolls back on stock failure)
        $stmtDel = $db_conn->prepare("DELETE FROM input_stock WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();

        // Reverse the credit: input_qty ↓, closing_qty ↓ (floored at 0), writes reverse_credit ledger entry
        $stockService->reverseCredit(
            $product_id, 'company', $godownid, $input_qty,
            'adjustment', $tempid, $createdBy,
            true // externalTransaction
        );

        $db_conn->commit();

    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("delete-input.php error: " . $e->getMessage());
    }
} else {
    // Row not found — delete defensively without stock reversal
    $stmtDel = $db_conn->prepare("DELETE FROM input_stock WHERE id = ?");
    $stmtDel->bind_param('i', $rowid);
    $stmtDel->execute();
    $stmtDel->close();
}

echo "<script>window.location='manage-input?deletedDone';</script>";
