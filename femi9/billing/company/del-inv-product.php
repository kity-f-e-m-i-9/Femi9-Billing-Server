<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

if (isset($_REQUEST['inv_id'])) {

    $invoice_id_encode = $_REQUEST['inv_id'];
    $inv_id            = base64_decode($invoice_id_encode);
    $rowid             = (int) base64_decode($_REQUEST['rowid']);

    // Fetch the item before deleting it
    $stmt = $db_conn->prepare(
        "SELECT pr_id, qty, user_type, user_id FROM invoice_items WHERE id = ?"
    );
    $stmt->bind_param('i', $rowid);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($item) {
        $pr_id     = (int)    $item['pr_id'];
        $qty       = (int)    $item['qty'];
        $user_type = (string) $item['user_type'];
        $user_id   = (string) $item['user_id'];

        // Restore seller stock only if already applied (ledger guard)
        $stockService = new StockService($db_conn);
        if ($stockService->hasLedgerEntry('invoice', $inv_id)) {
            try {
                $stockService->reverseDeduct(
                    $pr_id, $user_type, $user_id, $qty,
                    'invoice', $inv_id,
                    $Login_user_IDvl
                );
            } catch (\Throwable $e) {
                error_log("del-inv-product reverseDeduct error: " . $e->getMessage());
            }
        }
    }

    // Delete the line item
    $stmt = $db_conn->prepare("DELETE FROM invoice_items WHERE id = ?");
    $stmt->bind_param('i', $rowid);
    $stmt->execute();
    $stmt->close();

    echo "<script>window.location='invoice?InvoiceID={$invoice_id_encode}&&DeleteSuccess&&ActionRemove';</script>";
}
?>
