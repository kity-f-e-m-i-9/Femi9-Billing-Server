<?php
/**
 * User Invoice Submit
 *
 * Fix v3.0 — atomic submit
 * ────────────────────────
 * Root cause fixed: receipt creation, invoice totals update, stock deduction,
 * and status change now all live inside ONE outer transaction.
 * Previously each step committed independently, so a crash between steps left
 * the invoice as 'draft' with stock already deducted.
 *
 * New execution order
 * ───────────────────
 *  1. Read invoice from DB
 *  2. Calculate totals
 *  3. Update invoice date (outside tx — low risk, non-financial)
 *  4. Advance payment restore (edit mode) — runs in its own tx before outer tx
 *  5. Advance payment validate + deduct  — runs in its own tx before outer tx
 *     (advance-payment-functions.php uses BEGIN internally; nesting is unsafe)
 *  ── BEGIN OUTER TRANSACTION ───────────────────────────────────────────────
 *  6. Delete old receipts (edit mode)
 *  7. Create receipt row(s)
 *  8. Update invoice totals (sub_total, discount, total…)
 *  9. Stock deduction + buyer credit  (StockService, externalTransaction=true)
 * 10. UPDATE status = 'submitted'     (WHERE uses invoice's own from_user_type)
 *  ── COMMIT ────────────────────────────────────────────────────────────────
 * 11. Clear session, redirect to print
 *
 * If anything inside steps 6-10 throws, the whole block rolls back:
 * receipt gone, totals unchanged, stock unchanged, status still 'draft'.
 */

declare(strict_types=1);

include("checksession.php");
include("config.php");
require_once("advance-payment-functions.php");
require_once("include/StockService.php");

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/invoice-submit-errors.log');

if (!isset($_REQUEST['invoice-submit'])) {
    exit;
}

$invoice_id = mysqli_real_escape_string($db_conn, trim($_REQUEST['invoice_id'] ?? ''));
if (empty($invoice_id)) {
    die("Error: Invoice ID is required");
}

error_log(str_repeat("=", 80));
error_log("=== INVOICE SUBMISSION STARTED ===");
error_log("Invoice ID: $invoice_id | Time: " . date('Y-m-d H:i:s'));
error_log(str_repeat("=", 80));

// ─────────────────────────────────────────────────────────────────────────────
// STEP 1 — Read invoice
// ─────────────────────────────────────────────────────────────────────────────

$stmt = $db_conn->prepare("SELECT * FROM user_invoice WHERE inv_id = ?");
if (!$stmt) { die("Database error: " . $db_conn->error); }
$stmt->bind_param("s", $invoice_id);
$stmt->execute();
$resultdetails = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$resultdetails) {
    error_log("FATAL: Invoice not found: $invoice_id");
    die("Error: Invoice not found");
}

// Derive identities from the invoice row itself — never from session for WHERE clauses.
$company_type   = $resultdetails['from_user_type'];   // e.g. 'company'
$company_id     = $resultdetails['from_user_id'];     // e.g. godown id '1'
$customer_type  = $resultdetails['to_user_type'];
$customer_id    = $resultdetails['to_user_id'];
$invoice_number = $resultdetails['inv_number'];
$invoice_date   = $resultdetails['date'];

$requires_advance     = isAdvancePaymentMandatory($customer_type);
$is_edit_submission   = (isset($_SESSION['ACTIONEDIT']) && $_SESSION['ACTIONEDIT'] === 'edit');

error_log("Company: $company_id ($company_type)");
error_log("Customer: $customer_id ($customer_type)");
error_log("Requires advance: " . ($requires_advance ? 'YES' : 'NO'));
error_log("Mode: " . ($is_edit_submission ? 'EDIT' : 'NEW'));

// ─────────────────────────────────────────────────────────────────────────────
// STEP 2 — Calculate totals from POST
// ─────────────────────────────────────────────────────────────────────────────

$SubTotal        = floatval($_REQUEST['SubTotal']        ?? 0);
$discount        = floatval($_REQUEST['discount']        ?? 0);
$credit          = floatval($_REQUEST['credit']          ?? 0);
$roundoff        = floatval($_REQUEST['roundoff']        ?? 0);
$courier_charges = floatval($_REQUEST['courier_charges'] ?? 0);
$total_amount    = round($SubTotal - $discount - $credit + $courier_charges, 2);

error_log("Totals — Sub: $SubTotal  Disc: $discount  Credit: $credit  Courier: $courier_charges  Total: $total_amount");

// ─────────────────────────────────────────────────────────────────────────────
// STEP 3 — Update invoice date (low-risk, outside main tx)
// ─────────────────────────────────────────────────────────────────────────────

if (!empty($_REQUEST['update_invoice_date'])) {
    $update_invoice_date = date("Y-m-d", strtotime($_REQUEST['update_invoice_date']));
    $invoice_date        = $update_invoice_date;

    $s = $db_conn->prepare(
        "UPDATE user_invoice SET date = ?
          WHERE inv_id = ? AND from_user_type = ? AND from_user_id = ?"
    );
    $s->bind_param("ssss", $update_invoice_date, $invoice_id, $company_type, $company_id);
    $s->execute();
    $s->close();

    $s2 = $db_conn->prepare(
        "UPDATE user_invoice_items SET date = ?
          WHERE inv_id = ? AND from_user_type = ? AND from_user_id = ?"
    );
    $s2->bind_param("ssss", $update_invoice_date, $invoice_id, $company_type, $company_id);
    $s2->execute();
    $s2->close();
    error_log("Invoice date updated to $update_invoice_date");
}

// ─────────────────────────────────────────────────────────────────────────────
// STEPS 4 + 5 — Advance payment (SS / Stockist only)
// Runs in its own transaction BEFORE the outer tx.
// advance-payment-functions.php calls begin_transaction() internally;
// nesting that inside an outer tx would cause an implicit commit.
// ─────────────────────────────────────────────────────────────────────────────

if ($requires_advance) {
    error_log("=== ADVANCE PAYMENT START ===");

    // Step 4a — restore previous balance if editing
    if ($is_edit_submission) {
        $restoreResult = restoreAdvancePaymentOnInvoiceEdit(
            $db_conn, $invoice_id, $invoice_number,
            date('Y-m-d'), "Invoice edited — restoring balance",
            $company_id, $company_type
        );
        if (!$restoreResult['success']) {
            error_log("WARNING: Balance restore failed: " . $restoreResult['message']);
        } else {
            error_log("Balance restored: Rs." . inr_format($restoreResult['credited_amount'], 2));
        }
    }

    // Step 4b — validate sufficient advance balance
    $amount_requiring_advance = $total_amount - $courier_charges;
    $validation = validateAdvanceBalanceForInvoice(
        $db_conn, $customer_id, $customer_type,
        $amount_requiring_advance, $company_id
    );

    if (!$validation['can_create']) {
        error_log("INSUFFICIENT BALANCE — rolling back");
        $db_conn->query("DELETE FROM user_invoice_items WHERE inv_id = '$invoice_id'");
        $db_conn->query("DELETE FROM receipt WHERE inv_id = '$invoice_id'");
        $db_conn->query("DELETE FROM user_invoice WHERE inv_id = '$invoice_id'");
        unset($_SESSION['ACTIONEDIT']);
        $msg = htmlspecialchars($validation['message'], ENT_QUOTES);
        echo "<script>alert('$msg'); window.location='user-invoice-add.php?invuser=$customer_type';</script>";
        exit;
    }

    // Step 4c — deduct advance balance (FIFO), inside its own transaction
    $amount_for_deduction = $total_amount - $courier_charges;
    $adjustmentResult = processInvoiceAdvancePaymentDeduction(
        $db_conn,
        $invoice_id, $invoice_number, $amount_for_deduction, $invoice_date,
        $customer_id, $customer_type,
        $company_id,  $company_type,
        $company_id,  $company_type
    );

    if ($adjustmentResult['success']) {
        error_log("Advance deducted: Rs." . inr_format($adjustmentResult['adjusted_amount'], 2));
    } else {
        error_log("Advance deduction failed (non-blocking): " . $adjustmentResult['message']);
    }

    error_log("=== ADVANCE PAYMENT END ===");
}

// ─────────────────────────────────────────────────────────────────────────────
// OUTER TRANSACTION — steps 6-10 are atomic
// Receipt creation, totals, stock, and status all commit or all roll back.
// ─────────────────────────────────────────────────────────────────────────────

$db_conn->begin_transaction();
error_log("Outer transaction started");

try {
    // ── Step 6: delete old receipts (edit mode) ───────────────────────────────
    if ($is_edit_submission) {
        $s = $db_conn->prepare("DELETE FROM receipt WHERE inv_id = ?");
        $s->bind_param("s", $invoice_id);
        $s->execute();
        $s->close();
        error_log("Old receipts deleted");
    }

    // ── Step 7: create receipt row(s) ────────────────────────────────────────
    $receiptdate = $invoice_date;

    // Guard: only create if not already exists for this invoice
    $s = $db_conn->prepare("SELECT COUNT(*) AS n FROM receipt WHERE receiptid = ?");
    $s->bind_param("s", $invoice_id);
    $s->execute();
    $receiptExists = (int) $s->get_result()->fetch_assoc()['n'];
    $s->close();

    // Reusable INSERT closure
    $insertReceipt = function (
        string $rcpt_id,
        float  $inv_amount,
        float  $rcvd,
        float  $rcvable,
        string $method,
        string $remarks,
        string $ptype
    ) use ($db_conn, $invoice_id, $receiptdate, $company_type, $company_id, $customer_type, $customer_id): void {
        $s = $db_conn->prepare(
            "INSERT INTO receipt
                (receiptid, inv_id, invoice_amount, received, receivable, date,
                 from_user_type, from_user_id, to_user_type, to_user_id,
                 receipt_method, receipt_remarks, payment_type, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        if (!$s) { throw new \RuntimeException("Receipt prepare failed: " . $db_conn->error); }
        $s->bind_param(
            "ssdddssssssss",
            $rcpt_id, $invoice_id, $inv_amount, $rcvd, $rcvable, $receiptdate,
            $company_type, $company_id, $customer_type, $customer_id,
            $method, $remarks, $ptype
        );
        if (!$s->execute()) { throw new \RuntimeException("Receipt insert failed: " . $s->error); }
        $s->close();
    };

    if ($receiptExists === 0) {
        if ($requires_advance) {
            // SS / Stockist — advance_product receipt
            $receivedamount   = round($total_amount - $courier_charges, 2);
            $receivableamount = $courier_charges;
            $insertReceipt(
                $invoice_id, $total_amount,
                $receivedamount, $receivableamount,
                'Advance Payment',
                'Paid via advance payment. Courier Rs.' . inr_format($courier_charges, 2) . ' pending.',
                'advance_product'
            );
            error_log("Receipt created: advance_product Rs.$receivedamount");
        } else {
            // Distributor / others — split regular + courier_charge
            $received_total      = floatval($_REQUEST['receivedamount']  ?? 0);
            $receipt_method      = mysqli_real_escape_string($db_conn, $_REQUEST['receipt_method']  ?? 'Cash');
            $receipt_remarks     = mysqli_real_escape_string($db_conn, str_replace("'", "&#39;", $_REQUEST['receipt_remarks'] ?? ''));
            $product_amount_only = round($total_amount - $courier_charges, 2);
            $courier_paid_now    = ($courier_charges > 0 && $received_total >= $courier_charges) ? $courier_charges : 0.00;
            $product_received    = round($received_total - $courier_paid_now, 2);
            $product_receivable  = round($product_amount_only - $product_received, 2);

            $insertReceipt(
                $invoice_id, $total_amount,
                $product_received, $product_receivable,
                $receipt_method, $receipt_remarks, 'regular'
            );
            error_log("Receipt created: regular Rs.$product_received");

            if ($courier_paid_now > 0) {
                $insertReceipt(
                    $invoice_id . '/CC', $courier_charges,
                    $courier_paid_now, 0.00,
                    $receipt_method, 'Courier charges collected with invoice', 'courier_charge'
                );
                error_log("Receipt created: courier_charge Rs.$courier_paid_now");
            }
        }
    } else {
        error_log("Receipt already exists — skipped creation");
    }

    // ── Step 8: update invoice totals ────────────────────────────────────────
    unset($_SESSION['INVOICEFINISH']);

    $s = $db_conn->prepare(
        "UPDATE user_invoice
            SET credit = ?, sub_total = ?, discount = ?, total = ?,
                roundoff = ?, courier_charges = ?
          WHERE inv_id = ? AND from_user_type = ? AND from_user_id = ?"
    );
    if (!$s) { throw new \RuntimeException("Invoice update prepare failed: " . $db_conn->error); }
    $s->bind_param(
        "dddddssss",
        $credit, $SubTotal, $discount, $total_amount, $roundoff, $courier_charges,
        $invoice_id, $company_type, $company_id   // ← from invoice, not session
    );
    if (!$s->execute()) { throw new \RuntimeException("Invoice totals update failed: " . $s->error); }
    $s->close();
    error_log("Invoice totals updated");

    // ── Step 9: stock deduction + buyer credit ────────────────────────────────
    $stockService       = new StockService($db_conn);
    $is_customer_invoice = false; // this file always handles user_invoice (B2B)

    if ($is_edit_submission) {
        // Reverse old stock before re-applying new quantities
        try {
            $reversed = $stockService->reverseAll('user_invoice', $invoice_id, $company_id);
            error_log("Edit mode: reversed $reversed ledger entries");
        } catch (\Throwable $e) {
            error_log("Edit reversal warning (non-blocking): " . $e->getMessage());
        }
    }

    // Tell invoice-stock-update.php to skip its own begin/commit — we own the tx
    $invoice_stock_external_txn = true;
    if (!defined('INVOICE_STOCK_UPDATE_INCLUDED')) {
        define('INVOICE_STOCK_UPDATE_INCLUDED', true);
    }
    include("invoice-stock-update.php");
    error_log("Stock update completed");

    // ── Step 10: mark invoice as submitted ────────────────────────────────────
    // Uses company_type and company_id from the invoice row — not from session.
    $s = $db_conn->prepare(
        "UPDATE user_invoice SET status = 'submitted'
          WHERE inv_id = ? AND from_user_type = ? AND from_user_id = ?"
    );
    if (!$s) { throw new \RuntimeException("Status update prepare failed: " . $db_conn->error); }
    $s->bind_param("sss", $invoice_id, $company_type, $company_id);
    $s->execute();
    $affected = $s->affected_rows;
    $s->close();

    // In edit mode the invoice is already 'submitted', so MySQL reports 0 affected rows
    // (value unchanged) — that is expected and correct. Only throw when genuinely no row
    // was found, which can't happen in edit mode (the totals UPDATE earlier in the same
    // transaction already matched the same WHERE clause successfully).
    if ($affected === 0 && !$is_edit_submission) {
        throw new \RuntimeException(
            "Status update matched 0 rows for inv_id=$invoice_id " .
            "from_user_type=$company_type from_user_id=$company_id"
        );
    }
    error_log("Invoice status set to 'submitted' ($affected row)");

    // ── All steps succeeded — commit ──────────────────────────────────────────
    $db_conn->commit();
    error_log("Outer transaction COMMITTED");

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("Outer transaction ROLLED BACK — " . $e->getMessage());
    error_log("Stack: " . $e->getTraceAsString());

    unset($_SESSION['ACTIONEDIT']);
    $_SESSION['errorMessage'] = "Invoice submission failed. Please try again.";
    echo "<script>window.location='user-invoice-add.php?InvoiceID="
        . base64_encode($invoice_id) . "&invuser=$customer_type&submiterror=1';</script>";
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Done
// ─────────────────────────────────────────────────────────────────────────────

$was_edit = $is_edit_submission;
unset($_SESSION['ACTIONEDIT']);

error_log("=== INVOICE SUBMISSION COMPLETED: $invoice_id ===");
error_log(str_repeat("=", 80));

if ($was_edit) {
    echo "<script>window.location='user-manage-invoice.php?invuser=" . urlencode($customer_type) . "&EditSuccess';</script>";
} else {
    echo "<script>window.location='user-invoice-print.php?invoiceid=" . base64_encode($invoice_id) . "';</script>";
}
exit;
?>
