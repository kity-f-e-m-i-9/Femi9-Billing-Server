<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
require_once("include/GodownAccess.php");

error_reporting(0);

// Internal Stock Transfer is a finance-only area.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header("Location: dashboard.php");
    exit;
}

$rowid  = (int) base64_decode($_REQUEST['Roowid'] ?? '');
$tempid = $_REQUEST['tempid'] ?? '';

if ($rowid <= 0) {
    $_SESSION['sucMessage'] = "Invalid record.";
    echo "<script>window.location='internal_transfer_details?deletedDone&&tempid=$tempid';</script>";
    exit;
}

// Fetch the transfer row before deletion (prepared statement — no injection)
$stmt = $db_conn->prepare(
    "SELECT product_id, qty, returned_qty, send_from, send_to FROM internal_transfer WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row) {
    $product_id = (int)    $row['product_id'];
    // Some of this line's qty may have already been reversed via a partial
    // "Return Stock" action — only the remainder is still actually applied
    // to stock, so only that much should be reversed on delete.
    $qty        = (int) $row['qty'] - (int) $row['returned_qty'];
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

        // If the line was already fully returned via "Return Stock", there's
        // nothing left applied to stock to reverse — just drop the record.
        $reverseInResult = ['success' => true];
        if ($qty > 0) {
            // Restore source godown stock (sent_qty ↓, closing_qty ↑) — FOR UPDATE + ledger
            $stockService->reverseTransferOut(
                $product_id, $Login_user_TYPEvl, $send_from, $qty,
                'transfer', $tempid, $createdBy,
                true
            );

            // Remove destination godown stock (input_qty ↓, closing_qty ↓) — FOR UPDATE + ledger
            $reverseInResult = $stockService->reverseTransferIn(
                $product_id, $Login_user_TYPEvl, $send_to, $qty,
                'transfer', $tempid, $createdBy,
                true
            );
        }

        if (($reverseInResult['success'] ?? false) === false
            && ($reverseInResult['reason'] ?? '') === 'insufficient_stock_to_reverse') {
            $db_conn->rollback();
            $available = $reverseInResult['available'];
            $_SESSION['errorMessage'] = "Cannot delete this transfer — only {$available} of the "
                . "original {$qty} units are still in stock at the destination (the rest has "
                . "already been sold or moved on). Please reconcile manually before deleting.";
            echo "<script>window.location='internal_transfer_details?deletedDone&&tempid=$tempid';</script>";
            exit;
        }

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
