<?php
include("checksession.php");
include("config.php");

error_reporting(0);

$rowid = (int) base64_decode($_REQUEST['Roowid'] ?? '');

if ($rowid <= 0) {
    echo "<script>window.location='manage-return?deletedDone';</script>";
    exit;
}

// Fetch the company_return_stock record before deletion (prepared — no injection)
$stmt = $db_conn->prepare(
    "SELECT prid, returnqty, godownid FROM company_return_stock WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($record && (int)$record['prid'] > 0) {
    $product_id = (int)    $record['prid'];
    $returnqty  = (int)    $record['returnqty'];
    $godownid   = (string) $record['godownid'];
    $createdBy  = $_SESSION['LOGIN_USER'] ?? 'system';

    $db_conn->begin_transaction();
    try {
        // Delete the record (rolls back on failure)
        $stmtDel = $db_conn->prepare("DELETE FROM company_return_stock WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();

        // Reverse: returnqty ↓ and closing_qty ↑ (floored at 0)
        // Uses FOR UPDATE to prevent race condition, writes ledger entry for audit trail
        $stmtLock = $db_conn->prepare(
            "SELECT closing_qty, returnqty FROM stock
              WHERE product_id = ? AND user_type = ? AND user_id = ?
              FOR UPDATE"
        );
        $stmtLock->bind_param('iss', $product_id, $Login_user_TYPEvl, $godownid);
        $stmtLock->execute();
        $stockRow = $stmtLock->get_result()->fetch_assoc();
        $stmtLock->close();

        if ($stockRow) {
            $before       = (int) $stockRow['closing_qty'];
            $after        = $before + $returnqty;
            $newReturnQty = max(0, (int) $stockRow['returnqty'] - $returnqty);

            $stmtUpd = $db_conn->prepare(
                "UPDATE stock
                    SET returnqty   = ?,
                        closing_qty = ?,
                        updated_at  = NOW()
                  WHERE product_id = ? AND user_type = ? AND user_id = ?"
            );
            $stmtUpd->bind_param('iiiss', $newReturnQty, $after, $product_id, $Login_user_TYPEvl, $godownid);
            $stmtUpd->execute();
            $stmtUpd->close();

            // Audit trail
            $refId   = (string) $rowid;
            $stmtLed = $db_conn->prepare(
                "INSERT INTO stock_ledger
                     (product_id, user_type, user_id, action, qty,
                      qty_before, qty_after, ref_type, ref_id, note, created_by)
                 VALUES (?, ?, ?, 'return_stock_delete', ?, ?, ?, 'company_return', ?, 'return deleted', ?)"
            );
            $stmtLed->bind_param(
                'issiiiss',
                $product_id, $Login_user_TYPEvl, $godownid,
                $returnqty, $before, $after,
                $refId, $createdBy
            );
            $stmtLed->execute();
            $stmtLed->close();
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
