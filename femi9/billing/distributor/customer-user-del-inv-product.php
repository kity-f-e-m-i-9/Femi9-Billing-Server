<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

if (isset($_REQUEST['inv_id'])) {
    $invoice_id_encode = $_REQUEST['inv_id'];
    $rowid = (int) base64_decode($_REQUEST['rowid'] ?? '');

    if ($rowid <= 0) {
        echo "<script>window.location='customer-user-invoice-add.php?InvoiceID={$invoice_id_encode}&&DeleteSuccess&&ActionRemove';</script>";
        exit;
    }

    $stmt = $db_conn->prepare(
        "SELECT pr_id, qty, usertype, userid FROM invoice_items WHERE id = ?"
    );
    $stmt->bind_param('i', $rowid);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    $db_conn->begin_transaction();
    try {
        $stmtDel = $db_conn->prepare("DELETE FROM invoice_items WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();

        if ($item && (int)$item['pr_id'] > 0) {
            $product_id = (int)    $item['pr_id'];
            $qty        = (int)    $item['qty'];
            $sellerType = (string) $item['usertype'];
            $sellerId   = (string) $item['userid'];

            if ($stockService->hasLedgerEntry('invoice_item', (string)$rowid)) {
                $stockService->reverseDeduct(
                    $product_id, $sellerType, $sellerId, $qty,
                    'invoice_item', (string)$rowid, $createdBy, true
                );
                // customers do not maintain stock — no buyer reversal
            } else {
                $stmtFloor = $db_conn->prepare(
                    "UPDATE stock
                        SET sales_qty   = GREATEST(0, sales_qty   - ?),
                            closing_qty = GREATEST(0, closing_qty + ?)
                      WHERE product_id = ? AND user_type = ? AND user_id = ?"
                );
                $stmtFloor->bind_param('iiiss', $qty, $qty, $product_id, $sellerType, $sellerId);
                $stmtFloor->execute();
                $stmtFloor->close();
            }
        }

        $db_conn->commit();

    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("customer-user-del-inv-product.php error: " . $e->getMessage());
    }

    echo "<script>window.location='customer-user-invoice-add.php?InvoiceID={$invoice_id_encode}&&DeleteSuccess&&ActionRemove';</script>";
}
?>
