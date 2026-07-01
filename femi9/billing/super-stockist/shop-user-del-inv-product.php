<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

if (isset($_REQUEST['inv_id'])) {

    $invoice_id_encode = $_REQUEST['inv_id'];
    $invuser           = (string) ($_REQUEST['invuser']  ?? '');
    $customer_id       = (string) ($_REQUEST['userid']   ?? '');

    $rowid = (int) base64_decode($_REQUEST['rowid'] ?? '');
    if ($rowid <= 0) {
        echo "<script>window.location='shop-user-invoice-add.php?InvoiceID={$invoice_id_encode}&&invuser={$invuser}';</script>";
        exit;
    }

    // Fetch the line item (prepared — no injection)
    $stmt = $db_conn->prepare(
        "SELECT pr_id, qty, inv_id FROM user_invoice_items WHERE id = ?"
    );
    $stmt->bind_param('i', $rowid);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($item && (int)$item['pr_id'] > 0) {
        $pr_id     = (int)    $item['pr_id'];
        $qty       = (int)    $item['qty'];
        $inv_id    = (string) $item['inv_id'];
        $createdBy = $_SESSION['LOGIN_USER'] ?? 'system';

        $stockService = new StockService($db_conn);

        $db_conn->begin_transaction();
        try {
            // Delete the line item
            $stmtDel = $db_conn->prepare("DELETE FROM user_invoice_items WHERE id = ?");
            $stmtDel->bind_param('i', $rowid);
            $stmtDel->execute();
            $stmtDel->close();

            if ($stockService->hasLedgerEntry('user_invoice', $inv_id)) {
                // StockService path: full ledger reversal
                $stockService->reverseDeduct(
                    $pr_id, $Login_user_TYPEvl, $Login_user_IDvl, $qty,
                    'user_invoice', $inv_id, $createdBy, true
                );
                if (in_array($invuser, StockService::STOCK_MAINTAINING_TYPES, true) && !empty($customer_id)) {
                    $stockService->reverseCredit(
                        $pr_id, $invuser, $customer_id, $qty,
                        'user_invoice', $inv_id, $createdBy, true
                    );
                }
            } else {
                // Legacy path: direct SQL with floor guards
                $stmtS = $db_conn->prepare(
                    "UPDATE stock
                        SET sales_qty   = GREATEST(0, sales_qty - ?),
                            closing_qty = closing_qty + ?,
                            updated_at  = NOW()
                      WHERE product_id = ? AND user_type = ? AND user_id = ?"
                );
                $stmtS->bind_param('iiiss', $qty, $qty, $pr_id, $Login_user_TYPEvl, $Login_user_IDvl);
                $stmtS->execute();
                $stmtS->close();

                if (in_array($invuser, StockService::STOCK_MAINTAINING_TYPES, true) && !empty($customer_id)) {
                    $stmtB = $db_conn->prepare(
                        "UPDATE stock
                            SET input_qty   = GREATEST(0, input_qty - ?),
                                closing_qty = GREATEST(0, closing_qty - ?),
                                updated_at  = NOW()
                          WHERE product_id = ? AND user_type = ? AND user_id = ?"
                    );
                    $stmtB->bind_param('iiiss', $qty, $qty, $pr_id, $invuser, $customer_id);
                    $stmtB->execute();
                    $stmtB->close();
                }
            }

            $db_conn->commit();
        } catch (\Throwable $e) {
            $db_conn->rollback();
            error_log("shop-user-del-inv-product.php stock reversal error: " . $e->getMessage());
        }
    } else {
        // Item not found — delete defensively
        $stmtDel = $db_conn->prepare("DELETE FROM user_invoice_items WHERE id = ?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();
    }

    echo "<script>window.location='shop-user-invoice-add.php?InvoiceID={$invoice_id_encode}&&DeleteSuccess&&invuser={$invuser}&&action={$_SESSION['ACTIONEDIT']}&&ActionRemove';</script>";
}
?>
