<?php
include("checksession.php");
require_once("include/StockService.php");

$invtype  = $_REQUEST['invtype']  ?? '';
$invuser  = $_REQUEST['invuser']  ?? '';
$invid    = base64_decode($_REQUEST['invid'] ?? '');
$stockService = new StockService($db_conn);

// Helper: reverse stock + delete invoice + receipts atomically
function deleteInvoiceWithStockReversal(
    mysqli       $db,
    StockService $stockService,
    string       $invid,
    string       $refType,    // 'invoice' or 'user_invoice'
    string       $itemsTable, // 'invoice_items' or 'user_invoice_items'
    string       $headerTable // 'invoice' or 'user_invoice'
): bool {
    $db->begin_transaction();
    try {
        // 1. Reverse all stock ledger entries for this invoice
        $stockService->reverseAll($refType, $invid, $_SESSION['LOGIN_USER'] ?? 'system');

        // 2. Delete header + receipts
        $s1 = $db->prepare("DELETE FROM `{$headerTable}` WHERE inv_id = ?");
        $s1->bind_param('s', $invid);
        $s1->execute();
        $s1->close();

        $s2 = $db->prepare("DELETE FROM receipt WHERE inv_id = ?");
        $s2->bind_param('s', $invid);
        $s2->execute();
        $s2->close();

        $db->commit();
        return true;
    } catch (\Throwable $e) {
        $db->rollback();
        error_log("delinvoice error for $invid: " . $e->getMessage());
        return false;
    }
}

// B2B: Super Stockist / Stockist / Distributor invoices
if ($invtype === "noncustomer") {
    $stmt = $db_conn->prepare("SELECT COUNT(*) AS n FROM user_invoice_items WHERE inv_id = ?");
    $stmt->bind_param('s', $invid);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    if ($count === 0) {
        $ok = deleteInvoiceWithStockReversal(
            $db_conn, $stockService, $invid,
            'user_invoice', 'user_invoice_items', 'user_invoice'
        );
        if ($ok) {
            $_SESSION['successMessage'] = "Invoice deleted successfully.";
        } else {
            $_SESSION['errorMessage'] = "Could not delete invoice. Please try again.";
        }
        echo "<script>window.location='user-manage-invoice?deletedsuccess&&invuser={$invuser}';</script>";
    } else {
        $_SESSION['errorMessage'] = "This invoice has items. Remove all items before deleting.";
        echo "<script>window.location='user-manage-invoice?productnonempty&&invuser={$invuser}';</script>";
    }
}

// Shop invoices
if ($invtype === "shop") {
    $stmt = $db_conn->prepare("SELECT COUNT(*) AS n FROM user_invoice_items WHERE inv_id = ?");
    $stmt->bind_param('s', $invid);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    if ($count === 0) {
        $ok = deleteInvoiceWithStockReversal(
            $db_conn, $stockService, $invid,
            'user_invoice', 'user_invoice_items', 'user_invoice'
        );
        if ($ok) {
            $_SESSION['successMessage'] = "Invoice deleted successfully.";
        } else {
            $_SESSION['errorMessage'] = "Could not delete invoice. Please try again.";
        }
        echo "<script>window.location='shop-user-manage-invoice?deletedsuccess';</script>";
    } else {
        $_SESSION['errorMessage'] = "This invoice has items. Remove all items before deleting.";
        echo "<script>window.location='shop-user-manage-invoice?productnonempty';</script>";
    }
}

// B2C: Customer invoices
if ($invtype === "customer") {
    $stmt = $db_conn->prepare("SELECT COUNT(*) AS n FROM invoice_items WHERE inv_id = ?");
    $stmt->bind_param('s', $invid);
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    if ($count === 0) {
        $ok = deleteInvoiceWithStockReversal(
            $db_conn, $stockService, $invid,
            'invoice', 'invoice_items', 'invoice'
        );
        if ($ok) {
            $_SESSION['successMessage'] = "Invoice deleted successfully.";
        } else {
            $_SESSION['errorMessage'] = "Could not delete invoice. Please try again.";
        }
        echo "<script>window.location='customer-user-manage-invoice?deletedsuccess';</script>";
    } else {
        $_SESSION['errorMessage'] = "This invoice has items. Remove all items before deleting.";
        echo "<script>window.location='customer-user-manage-invoice?productnonempty';</script>";
    }
}
?>
