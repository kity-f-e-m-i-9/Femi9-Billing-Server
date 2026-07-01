<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

error_reporting(0);

if (!isset($_REQUEST['truncate'])) {
    $reqid_encode = $_REQUEST['reqid'] ?? '';
    echo "<script>window.location='stock_request_details?reqid=$reqid_encode';</script>";
    exit;
}

$reqid_encode = $_REQUEST['reqid'] ?? '';
$reqid        = base64_decode($reqid_encode);
$rowid        = (int) base64_decode($_REQUEST['rowid'] ?? '');

// Fetch the stock-request header (prepared — no injection)
$stmt = $db_conn->prepare(
    "SELECT fromusertype, fromuserid FROM stock_request WHERE reqid = ?"
);
$stmt->bind_param('s', $reqid);
$stmt->execute();
$reqRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reqRow) {
    echo "<script>window.location='stock_request_details?reqid=$reqid_encode';</script>";
    exit;
}

$buyer_type = (string) $reqRow['fromusertype'];
$buyer_id   = (string) $reqRow['fromuserid'];

// Fetch the line item before deleting it (prepared — no injection)
$stmt = $db_conn->prepare(
    "SELECT pr_id, qty FROM user_invoice_items WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($item && (int)$item['pr_id'] > 0) {
    $pr_id = (int) $item['pr_id'];
    $qty   = (int) $item['qty'];

    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    $db_conn->begin_transaction();
    try {
        // Delete the line item first (rolls back on stock failure)
        $stmtDel = $db_conn->prepare("DELETE FROM user_invoice_items WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();

        // Only reverse stock if it was actually applied (ledger guard)
        if ($stockService->hasLedgerEntry('stock_request', $reqid)) {
            // Restore seller's stock (company/godown that fulfilled the request)
            $stockService->reverseDeduct(
                $pr_id, $Login_user_TYPEvl, $Login_user_IDvl, $qty,
                'stock_request', $reqid, $createdBy,
                true
            );

            // Remove buyer's credited stock if they maintain a ledger
            if (in_array($buyer_type, StockService::STOCK_MAINTAINING_TYPES, true)) {
                $stockService->reverseCredit(
                    $pr_id, $buyer_type, $buyer_id, $qty,
                    'stock_request', $reqid, $createdBy,
                    true
                );
            }
        } else {
            // Legacy path: no ledger entries — fall back to direct stock correction
            // Restore seller (closing_qty ↑, sales_qty ↓)
            $s = $db_conn->prepare(
                "UPDATE stock
                    SET sales_qty    = GREATEST(0, sales_qty - ?),
                        closing_qty  = closing_qty + ?
                  WHERE product_id = ? AND user_type = ? AND user_id = ?"
            );
            $s->bind_param('iiiss', $qty, $qty, $pr_id, $Login_user_TYPEvl, $Login_user_IDvl);
            $s->execute();
            $s->close();

            // Remove buyer's stock (closing_qty ↓, input_qty ↓) — only if they maintain stock
            if (in_array($buyer_type, StockService::STOCK_MAINTAINING_TYPES, true)) {
                $s2 = $db_conn->prepare(
                    "UPDATE stock
                        SET input_qty   = GREATEST(0, input_qty - ?),
                            closing_qty = GREATEST(0, closing_qty - ?)
                      WHERE product_id = ? AND user_type = ? AND user_id = ?"
                );
                $s2->bind_param('iiiss', $qty, $qty, $pr_id, $buyer_type, $buyer_id);
                $s2->execute();
                $s2->close();
            }
        }

        $db_conn->commit();

    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("del-inv-product2 error: " . $e->getMessage());
    }
} else {
    // No item found — just delete the row defensively
    $stmtDel = $db_conn->prepare("DELETE FROM user_invoice_items WHERE id = ?");
    $stmtDel->bind_param('i', $rowid);
    $stmtDel->execute();
    $stmtDel->close();
}

echo "<script>window.location='stock_request_details?reqid=$reqid_encode&&DeleteSuccess&&ActionRemove';</script>";
