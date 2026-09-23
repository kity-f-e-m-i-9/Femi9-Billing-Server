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
    "SELECT product_id, input_qty, godownid, warehouse_id, tempid FROM input_stock WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Rows at or below this id were relabeled to warehouse_id=2 (G1) by the
// 2026-09-23 backfill (see docs on the input_stock warehouse_id migration),
// but their actual stock credit is still sitting in the unassigned bucket —
// the backfill only relabeled input_stock, it never moved the real stock/
// stock_ledger balance. Treat these as unassigned on delete regardless of
// their stored warehouse_id, so the reversal targets where the credit
// genuinely landed instead of wrongly debiting G1's real stock. Only rows
// created after the backfill (id > cutoff) carry a warehouse_id that was
// actually selected at input time and can be trusted.
const INPUT_STOCK_BACKFILL_CUTOFF_ID = 926;

if ($record && (int)$record['product_id'] > 0) {
    $product_id  = (int)    $record['product_id'];
    $input_qty   = (int)    $record['input_qty'];
    $godownid    = (string) $record['godownid'];
    $warehouseId = ($rowid <= INPUT_STOCK_BACKFILL_CUTOFF_ID)
        ? null
        : ($record['warehouse_id'] !== null ? (int)$record['warehouse_id'] : null);
    $tempid      = (string) $record['tempid'];

    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    $db_conn->begin_transaction();
    try {
        // Delete the record first (rolls back on stock failure)
        $stmtDel = $db_conn->prepare("DELETE FROM input_stock WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();

        // Reverse the credit: input_qty ↓, closing_qty ↓ (floored at 0), writes
        // reverse_credit ledger entry — scoped to the same warehouse the
        // original input targeted (NULL/unassigned for old pre-fix records).
        // reverseCredit() silently no-ops (success=false, no exception) if no
        // stock row exists for this exact (product, warehouse); that's not
        // expected to happen in practice since insert always creates the row,
        // but the check is kept for defense rather than assuming.
        $reverseResult = $stockService->reverseCredit(
            $product_id, 'company', $godownid, $input_qty,
            'adjustment', $tempid, $createdBy,
            true, // externalTransaction
            $warehouseId
        );
        if (empty($reverseResult['success'])) {
            error_log("delete-input.php: reverseCredit no-op for product=$product_id godown=$godownid warehouse=" . ($warehouseId ?? 'NULL') . " — " . ($reverseResult['reason'] ?? 'unknown'));
        }

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
