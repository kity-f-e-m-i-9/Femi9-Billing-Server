<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

error_reporting(0);

$rowid  = (int) base64_decode($_REQUEST['Roowid'] ?? '');
$tempid = $_REQUEST['tempid'] ?? '';

if ($rowid <= 0) {
    echo "<script>window.location='demofree_details?deletedDone&&tempid=$tempid';</script>";
    exit;
}

// Fetch the demo/free/damage record before deletion (prepared — no injection)
$stmt = $db_conn->prepare(
    "SELECT product_id, qty, usertype, userid, warehouse_id FROM demofreedamage WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($record && (int)$record['product_id'] > 0) {
    $product_id  = (int)    $record['product_id'];
    $qty         = (int)    $record['qty'];
    $usertype    = (string) $record['usertype'];
    $userid      = (string) $record['userid'];
    $warehouseId = $record['warehouse_id'] !== null ? (int)$record['warehouse_id'] : null;

    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    $db_conn->begin_transaction();
    try {
        // Delete the record (rolls back on stock failure)
        $stmtDel = $db_conn->prepare("DELETE FROM demofreedamage WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();

        // Reverse the transferOut: sent_qty ↓, closing_qty ↑ — FOR UPDATE + ledger.
        // reverseTransferOut() silently no-ops (success=false, no exception) when
        // no stock row exists yet for this exact (product, warehouse) — this
        // happens for old records whose warehouse_id was backfilled after the
        // fact (see docs on the demofreedamage warehouse_id migration): the
        // label says a warehouse, but no stock row was ever created there.
        // Fall back to credit(), which creates the row via INSERT ... ON
        // DUPLICATE KEY, so the qty is never silently lost.
        $reverseResult = $stockService->reverseTransferOut(
            $product_id, $usertype, $userid, $qty,
            'demofree', $tempid, $createdBy,
            true, // externalTransaction
            $warehouseId
        );
        if ($reverseResult['success'] === false) {
            $stockService->credit(
                $product_id, $usertype, $userid, $qty,
                'demofree', $tempid, $createdBy,
                true, // externalTransaction
                $warehouseId
            );
        }

        $db_conn->commit();

    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("demofree_delete.php error: " . $e->getMessage());
    }
} else {
    // Record not found — delete defensively
    $stmtDel = $db_conn->prepare("DELETE FROM demofreedamage WHERE id = ?");
    $stmtDel->bind_param('i', $rowid);
    $stmtDel->execute();
    $stmtDel->close();
}

$_SESSION['sucMessage'] = "One Product Details Deleted! (Demo/Free/Damage)";
echo "<script>window.location='demofree_details?deletedDone&&tempid=$tempid';</script>";
