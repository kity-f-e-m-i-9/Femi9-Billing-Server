<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

if (isset($_REQUEST['invoice-submit'])) {

    $invoice_id   = mysqli_real_escape_string($db_conn, trim($_REQUEST['invoice_id'] ?? ''));
    $SubTotal     = floatval($_REQUEST['SubTotal'] ?? 0);
    $discount     = floatval($_REQUEST['discount'] ?? 0);
    $total_amount = round($SubTotal - $discount);

    if (empty($invoice_id)) {
        die("Error: Invoice ID is required");
    }

    // Update invoice totals
    $stmt = $db_conn->prepare(
        "UPDATE invoice SET sub_total = ?, discount = ?, total = ?
          WHERE inv_id = ? AND user_type = ? AND user_id = ?"
    );
    $stmt->bind_param('dddss s', $SubTotal, $discount, $total_amount,
                      $invoice_id, $Login_user_TYPEvl, $Login_user_IDvl);
    $stmt->execute();
    $stmt->close();

    // Edit mode: reverse previous stock so hasLedgerEntry returns false and re-apply proceeds
    $stockService = new StockService($db_conn);
    $is_edit_submission = (isset($_SESSION['ACTIONEDIT']) && $_SESSION['ACTIONEDIT'] === 'edit');
    if ($is_edit_submission) {
        try {
            $reversed = $stockService->reverseAll('invoice', $invoice_id, $Login_user_IDvl);
            error_log("invoice-submit EDIT: reversed $reversed ledger entries for $invoice_id");
        } catch (\Throwable $e) {
            error_log("invoice-submit EDIT: reversal warning (non-blocking): " . $e->getMessage());
        }
    }

    // Idempotency guard: skip if stock is currently applied (prevents double-deduction on page refresh)
    if (!$stockService->hasLedgerEntry('invoice', $invoice_id)) {

        // Fetch all line items
        $stmt = $db_conn->prepare(
            "SELECT pr_id, qty FROM invoice_items
              WHERE inv_id = ? AND user_type = ? AND user_id = ?
                AND deleted_at IS NULL"
        );
        $stmt->bind_param('sss', $invoice_id, $Login_user_TYPEvl, $Login_user_IDvl);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!empty($items)) {
            $db_conn->begin_transaction();
            try {
                foreach ($items as $item) {
                    $stockService->deduct(
                        (int)$item['pr_id'],
                        $Login_user_TYPEvl,
                        $Login_user_IDvl,
                        (int)$item['qty'],
                        'invoice',
                        $invoice_id,
                        $Login_user_IDvl,
                        true
                    );
                }
                $db_conn->commit();
            } catch (StockException $e) {
                $db_conn->rollback();
                $_SESSION['errorMessage'] = "Stock error: " . $e->getMessage();
                echo "<script>window.location='invoice?InvoiceID=" . base64_encode($invoice_id) . "&&StockError';</script>";
                exit;
            } catch (\Throwable $e) {
                $db_conn->rollback();
                error_log("invoice-submit stock error: " . $e->getMessage());
                die("An error occurred while updating stock. Please try again.");
            }
        }
    }

    unset($_SESSION['ACTIONEDIT']);
    echo "<script>window.location='invoice-print?invoiceid=" . $invoice_id . "';</script>";
}
?>
