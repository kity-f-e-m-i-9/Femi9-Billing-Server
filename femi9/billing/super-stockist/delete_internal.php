<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

error_reporting(0);

$rowid = (int) base64_decode($_REQUEST['rowid'] ?? '');

if ($rowid <= 0) {
    echo "<script>window.location='manage_internal?deletedDone';</script>";
    exit;
}

// Fetch the transfer record before deletion (prepared — no injection)
$stmt = $db_conn->prepare(
    "SELECT prid, qty, from_usertype, from_userid, to_usertype, to_userid, tempid
       FROM internal_transfer_ss WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($record && (int)$record['prid'] > 0) {
    $prid         = (int)    $record['prid'];
    $qty          = (int)    $record['qty'];
    $from_type    = (string) $record['from_usertype'];
    $from_id      = (string) $record['from_userid'];
    $to_type      = (string) $record['to_usertype'];
    $to_id        = (string) $record['to_userid'];
    $tempid       = (string) $record['tempid'];
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    $stockService = new StockService($db_conn);

    $db_conn->begin_transaction();
    try {
        // Delete the transfer record first (rolls back on stock failure)
        $stmtDel = $db_conn->prepare("DELETE FROM internal_transfer_ss WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();

        // Reverse sender: sent_qty ↓, closing_qty ↑ — FOR UPDATE + ledger
        $stockService->reverseTransferOut(
            $prid, $from_type, $from_id, $qty,
            'transfer', $tempid, $createdBy,
            true // externalTransaction
        );

        // Reverse receiver: input_qty ↓, closing_qty ↓ — FOR UPDATE + ledger
        if ($to_type === 'territory_partner') {
            $tp_id = (int) $to_id;

            $stmt_tp_lock = $db_conn->prepare(
                "SELECT closing_qty FROM territory_partner_stock
                  WHERE territory_partner_id = ? AND product_id = ? FOR UPDATE"
            );
            $stmt_tp_lock->bind_param("ii", $tp_id, $prid);
            $stmt_tp_lock->execute();
            $tp_row = $stmt_tp_lock->get_result()->fetch_assoc();
            $stmt_tp_lock->close();

            if ($tp_row) {
                $tp_before = (int) $tp_row['closing_qty'];
                $tp_after  = max(0, $tp_before - $qty);

                $stmt_tp_rev = $db_conn->prepare(
                    "UPDATE territory_partner_stock
                        SET input_qty = GREATEST(0, input_qty - ?), closing_qty = ?
                      WHERE territory_partner_id = ? AND product_id = ?"
                );
                $stmt_tp_rev->bind_param("iiii", $qty, $tp_after, $tp_id, $prid);
                $stmt_tp_rev->execute();
                $stmt_tp_rev->close();

                $tp_action  = 'internal_transfer_in_reverse';
                $tp_reftype = 'internal_transfer';
                $tp_note    = '';
                $stmt_tp_ledger = $db_conn->prepare(
                    "INSERT INTO territory_partner_stock_ledger
                        (territory_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt_tp_ledger->bind_param("iisiiissss", $tp_id, $prid, $tp_action, $qty, $tp_before, $tp_after, $tp_reftype, $tempid, $tp_note, $createdBy);
                $stmt_tp_ledger->execute();
                $stmt_tp_ledger->close();
            }
        } else {
            $stockService->reverseTransferIn(
                $prid, $to_type, $to_id, $qty,
                'transfer', $tempid, $createdBy,
                true // externalTransaction
            );
        }

        $db_conn->commit();

        $_SESSION['sucMessage'] = "One Internal Stock Transfer Details Deleted Successfully!";

    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("delete_internal.php error: " . $e->getMessage());
        $_SESSION['errorMessage'] = "Delete failed: " . $e->getMessage();
    }
} else {
    // Record not found — delete defensively
    $stmtDel = $db_conn->prepare("DELETE FROM internal_transfer_ss WHERE id = ?");
    $stmtDel->bind_param('i', $rowid);
    $stmtDel->execute();
    $stmtDel->close();
    $_SESSION['sucMessage'] = "One Internal Stock Transfer Details Deleted Successfully!";
}

echo "<script>window.location='manage_internal.php';</script>";
?>
