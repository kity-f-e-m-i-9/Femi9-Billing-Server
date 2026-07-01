<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

if (isset($_REQUEST['inv_id'])) {

    $invoice_id_encode = $_REQUEST['inv_id'];
    $inv_id            = base64_decode($invoice_id_encode);
    $invuser           = $_REQUEST['invuser'] ?? '';
    $rowid_decode      = (int) base64_decode($_REQUEST['rowid']);

    // Fetch the item before deleting it (include buyer identity for reverseCredit)
    $stmt = $db_conn->prepare(
        "SELECT pr_id, qty, from_user_type, from_user_id, to_user_type, to_user_id
           FROM user_invoice_items WHERE id = ?"
    );
    $stmt->bind_param('i', $rowid_decode);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $gid    = $item['from_user_id'] ?? $Login_user_IDvl;
    $action = $_SESSION['ACTIONEDIT'] ?? '';

    if ($item) {
        $pr_id     = (int)    $item['pr_id'];
        $qty       = (int)    $item['qty'];
        $from_type = (string) $item['from_user_type'];
        $from_id   = (string) $item['from_user_id'];
        $to_type   = (string) $item['to_user_type'];
        $to_id     = (string) $item['to_user_id'];

        $stockService = new StockService($db_conn);
        if ($stockService->hasLedgerEntry('user_invoice', $inv_id)) {
            $db_conn->begin_transaction();
            try {
                // Restore seller's closing_qty (sales_qty ↓, closing_qty ↑)
                $stockService->reverseDeduct(
                    $pr_id, $from_type, $from_id, $qty,
                    'user_invoice', $inv_id,
                    $Login_user_IDvl,
                    true
                );

                // Remove buyer's credited stock if they maintain a ledger (input_qty ↓, closing_qty ↓)
                if (in_array($to_type, StockService::STOCK_MAINTAINING_TYPES, true)) {
                    $stockService->reverseCredit(
                        $pr_id, $to_type, $to_id, $qty,
                        'user_invoice', $inv_id,
                        $Login_user_IDvl,
                        true
                    );
                }

                $db_conn->commit();
            } catch (\Throwable $e) {
                $db_conn->rollback();
                error_log("user-del-inv-product stock reversal error: " . $e->getMessage());
            }
        }
    }

    // Delete the line item
    $stmt = $db_conn->prepare("DELETE FROM user_invoice_items WHERE id = ?");
    $stmt->bind_param('i', $rowid_decode);
    $stmt->execute();
    $stmt->close();

    echo "<script>window.location='user-invoice-add?InvoiceID={$invoice_id_encode}&&DeleteSuccess&&invuser={$invuser}&&ActionRemove&&gid={$gid}&&action={$action}';</script>";
}
?>
