<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

error_reporting(0);

$rowid = (int) base64_decode($_REQUEST['Roowid'] ?? '');

if ($rowid <= 0) {
    echo "<script>window.location='manage-return?deletedDone';</script>";
    exit;
}

// Fetch the company_return_stock record before deletion (prepared — no injection)
$stmt = $db_conn->prepare(
    "SELECT prid, returnqty, godownid, warehouse_id FROM company_return_stock WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($record && (int)$record['prid'] > 0) {
    $product_id  = (int)    $record['prid'];
    $returnqty   = (int)    $record['returnqty'];
    $godownid    = (string) $record['godownid'];
    $warehouseId = $record['warehouse_id'] !== null ? (int)$record['warehouse_id'] : null;
    $createdBy   = $_SESSION['LOGIN_USER'] ?? 'system';

    $stockService = new StockService($db_conn);

    $db_conn->begin_transaction();
    try {
        // Delete the record (rolls back on failure)
        $stmtDel = $db_conn->prepare("DELETE FROM company_return_stock WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();

        // Reverse: returnqty ↓ and closing_qty ↑ (floored at 0) — scoped to
        // the specific warehouse this return was recorded against.
        $refId = (string) $rowid;

        // Lock the stock row for this exact warehouse
        $s = $warehouseId === null
            ? $db_conn->prepare(
                "SELECT closing_qty, returnqty FROM stock
                  WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL
                  FOR UPDATE"
              )
            : $db_conn->prepare(
                "SELECT closing_qty, returnqty FROM stock
                  WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id = ?
                  FOR UPDATE"
              );
        if ($warehouseId === null) {
            $s->bind_param('iss', $product_id, $Login_user_TYPEvl, $godownid);
        } else {
            $s->bind_param('issi', $product_id, $Login_user_TYPEvl, $godownid, $warehouseId);
        }
        $s->execute();
        $stockRow = $s->get_result()->fetch_assoc();
        $s->close();

        if ($stockRow) {
            $before       = (int) $stockRow['closing_qty'];
            $after        = $before + $returnqty;
            $newReturnQty = max(0, (int) $stockRow['returnqty'] - $returnqty);

            $u = $warehouseId === null
                ? $db_conn->prepare(
                    "UPDATE stock SET returnqty = ?, closing_qty = ?, updated_at = NOW()
                      WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id IS NULL"
                  )
                : $db_conn->prepare(
                    "UPDATE stock SET returnqty = ?, closing_qty = ?, updated_at = NOW()
                      WHERE product_id = ? AND user_type = ? AND user_id = ? AND warehouse_id = ?"
                  );
            if ($warehouseId === null) {
                $u->bind_param('iiiss', $newReturnQty, $after, $product_id, $Login_user_TYPEvl, $godownid);
            } else {
                $u->bind_param('iiissi', $newReturnQty, $after, $product_id, $Login_user_TYPEvl, $godownid, $warehouseId);
            }
            $u->execute();
            $u->close();

            // Audit trail
            $stmtLed = $db_conn->prepare(
                "INSERT INTO stock_ledger
                     (product_id, user_type, user_id, warehouse_id, action, qty,
                      qty_before, qty_after, ref_type, ref_id, note, created_by)
                 VALUES (?, ?, ?, ?, 'return_stock_delete', ?, ?, ?, 'company_return', ?, 'return deleted', ?)"
            );
            $stmtLed->bind_param(
                'issiiiiss',
                $product_id, $Login_user_TYPEvl, $godownid, $warehouseId,
                $returnqty, $before, $after,
                $refId, $createdBy
            );
            $stmtLed->execute();
            $stmtLed->close();
        } else {
            // No stock row exists yet for this exact (product, warehouse) —
            // same gap seen with old demo/free/damage records after their
            // warehouse_id was backfilled: the label says a warehouse, but no
            // stock row was ever created there. Fall back to credit(), which
            // creates the row via INSERT ... ON DUPLICATE KEY, so the
            // returned qty is never silently lost.
            $stockService->credit(
                $product_id, $Login_user_TYPEvl, $godownid, $returnqty,
                'company_return', $refId, $createdBy,
                true, // externalTransaction
                $warehouseId
            );
        }

        $db_conn->commit();

    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("delete-return.php error: " . $e->getMessage());
    }
} else {
    // Record not found — delete defensively
    $stmtDel = $db_conn->prepare("DELETE FROM company_return_stock WHERE id = ?");
    $stmtDel->bind_param('i', $rowid);
    $stmtDel->execute();
    $stmtDel->close();
}

echo "<script>window.location='manage-return?deletedDone';</script>";
