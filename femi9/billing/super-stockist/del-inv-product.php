<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

$reqid_encode = $_REQUEST['reqid'] ?? '';
$reqid        = base64_decode($reqid_encode);

if (!isset($_REQUEST['truncate'])) {
    echo "<script>window.location='stock_request_details.php?reqid={$reqid_encode}';</script>";
    exit;
}

// Fetch request header to get buyer identity (prepared — no injection)
$stmtReq = $db_conn->prepare(
    "SELECT fromusertype, fromuserid FROM stock_request WHERE reqid = ?"
);
$stmtReq->bind_param('s', $reqid);
$stmtReq->execute();
$reqRow = $stmtReq->get_result()->fetch_assoc();
$stmtReq->close();

if (!$reqRow) {
    echo "<script>window.location='stock_request_details.php?reqid={$reqid_encode}';</script>";
    exit;
}

$invuser     = (string) $reqRow['fromusertype'];
$customer_id = (string) $reqRow['fromuserid'];

$rowid = (int) base64_decode($_REQUEST['rowid'] ?? '');
if ($rowid <= 0) {
    echo "<script>window.location='stock_request_details.php?reqid={$reqid_encode}&&DeleteSuccess&&ActionRemove';</script>";
    exit;
}

// Fetch the line item (prepared — no injection)
$stmtItem = $db_conn->prepare(
    "SELECT pr_id, qty FROM user_invoice_items WHERE id = ?"
);
$stmtItem->bind_param('i', $rowid);
$stmtItem->execute();
$item = $stmtItem->get_result()->fetch_assoc();
$stmtItem->close();

if ($item && (int)$item['pr_id'] > 0) {
    $pr_id     = (int) $item['pr_id'];
    $qty       = (int) $item['qty'];
    $createdBy = $_SESSION['LOGIN_USER'] ?? 'system';

    $stockService = new StockService($db_conn);

    $db_conn->begin_transaction();
    try {
        // Delete the line item
        $stmtDel = $db_conn->prepare("DELETE FROM user_invoice_items WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();

        if ($stockService->hasLedgerEntry('stock_request', $reqid)) {
            // StockService path: ledger-driven reversal
            $stockService->reverseDeduct(
                $pr_id, $Login_user_TYPEvl, $Login_user_IDvl, $qty,
                'stock_request', $reqid, $createdBy, true
            );
            if (in_array($invuser, StockService::STOCK_MAINTAINING_TYPES, true) && !empty($customer_id)) {
                $stockService->reverseCredit(
                    $pr_id, $invuser, $customer_id, $qty,
                    'stock_request', $reqid, $createdBy, true
                );
            }
        } else {
            // Legacy path: direct SQL with floor guards
            $stmtSeller = $db_conn->prepare(
                "UPDATE stock
                    SET sales_qty   = GREATEST(0, sales_qty - ?),
                        closing_qty = closing_qty + ?,
                        updated_at  = NOW()
                  WHERE product_id = ? AND user_type = ? AND user_id = ?"
            );
            $stmtSeller->bind_param('iiiss', $qty, $qty, $pr_id, $Login_user_TYPEvl, $Login_user_IDvl);
            $stmtSeller->execute();
            $stmtSeller->close();

            if (in_array($invuser, StockService::STOCK_MAINTAINING_TYPES, true) && !empty($customer_id)) {
                $stmtBuyer = $db_conn->prepare(
                    "UPDATE stock
                        SET input_qty   = GREATEST(0, input_qty - ?),
                            closing_qty = GREATEST(0, closing_qty - ?),
                            updated_at  = NOW()
                      WHERE product_id = ? AND user_type = ? AND user_id = ?"
                );
                $stmtBuyer->bind_param('iiiss', $qty, $qty, $pr_id, $invuser, $customer_id);
                $stmtBuyer->execute();
                $stmtBuyer->close();
            }
        }

        $db_conn->commit();
    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("del-inv-product.php stock reversal error: " . $e->getMessage());
    }
} else {
    // Item not found — delete defensively
    $stmtDel = $db_conn->prepare("DELETE FROM user_invoice_items WHERE id = ?");
    $stmtDel->bind_param('i', $rowid);
    $stmtDel->execute();
    $stmtDel->close();
}

echo "<script>window.location='stock_request_details.php?reqid={$reqid_encode}&&DeleteSuccess&&ActionRemove';</script>";
?>
