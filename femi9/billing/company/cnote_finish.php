<?php
/**
 * Finish Credit Note (Return Submission)
 * Finalizes a return note and adds advance payment credit if applicable
 * 
 * SECURITY: Prepared statements, input validation, transaction handling
 */

include("checksession.php"); require_once("include/PermissionCheck.php"); requireAnyPermission(['ss','st','dt','sdt','shop','cus','stock_request','ot_channels','debit_note','manage_return','demo_free','report','payment_entry','manage_payment_entry','territory_partner']);
include("config.php");
include("advance-payment-functions.php");
require_once("include/StockService.php");
require_once(__DIR__ . '/../shared/CpShopWalletCommission.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
date_default_timezone_set("Asia/Kolkata");

// Validate and sanitize inputs
$returnid = isset($_REQUEST['returnid']) ? base64_decode($_REQUEST['returnid']) : '';
$returnid = mysqli_real_escape_string($db_conn, $returnid);

if (empty($returnid)) {
    error_log("FINISH RETURN ERROR: Invalid return ID");
    $_SESSION['errorMessage'] = "Invalid return ID";
    header("Location: cnote_manage.php?error=invalid_returnid");
    exit;
}

// SubTotal is computed server-side from the actual line items, never trusted
// from the client — the form's Subtotal field is a disabled display mirror of
// this same figure, not something the user can edit (Discount is the only
// genuine user-editable amount here). Trusting the posted value let the header
// total drift out of sync with the real items whenever anything changed
// between page render and submit (e.g. an item removed via cnote_delete.php
// in another tab).
$stmt = $db_conn->prepare("SELECT COALESCE(SUM(total),0) AS computed_subtotal FROM user_return_stock_items WHERE returnid = ? AND deleted_at IS NULL");
$stmt->bind_param("s", $returnid);
$stmt->execute();
$SubTotal = (float)($stmt->get_result()->fetch_assoc()['computed_subtotal'] ?? 0);
$stmt->close();

$discount = (float)($_REQUEST['discount'] ?? 0);
$total_amount = (float)($SubTotal - $discount);

if ($SubTotal < 0 || $discount < 0 || $total_amount < 0) {
    error_log("FINISH RETURN ERROR: Invalid amounts - SubTotal: $SubTotal, Discount: $discount");
    $_SESSION['errorMessage'] = "Invalid amount values";
    header("Location: cnote_new.php?returnid=" . base64_encode($returnid) . "&error=invalid_amounts");
    exit;
}

/*
|--------------------------------------------------------------------------
| FETCH RETURN MASTER DETAILS
|--------------------------------------------------------------------------
*/
$stmt = $db_conn->prepare("
    SELECT invnumber, from_usertype, from_userid, to_usertype, to_userid, status
    FROM user_return_stock
    WHERE returnid = ? AND deleted_at IS NULL
    LIMIT 1
");
$stmt->bind_param("s", $returnid);
$stmt->execute();
$return = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$return) {
    error_log("FINISH RETURN ERROR: Return $returnid not found");
    $_SESSION['errorMessage'] = "Return not found";
    header("Location: cnote_manage.php?error=return_not_found");
    exit;
}

$invid = $return['invnumber'];
$from_usertype = $return['from_usertype'];
$from_userid = $return['from_userid'];
$to_usertype = $return['to_usertype'];
$to_userid = $return['to_userid'];
$current_status = $return['status'];

// Which physical warehouse the original sale drew from, if recorded —
// invoice/user_invoice both have this column (see docs/superpowers/specs/
// 2026-09-18-invoice-warehouse-selection-design.md) but neither invoice
// creation path is wired to populate it yet, so this is NULL for every row
// today. Falls back to G1 (warehouse id 2) rather than the unassigned
// bucket — same default applied to the TP-invoice and demo/free/damage
// backfills, since that's where this company's stock actually lives in
// practice. Once invoice creation is wired to record the real warehouse,
// this fallback simply stops being hit.
$warehouseId = 2; // Default: G1
if ($from_usertype === 'customer') {
    $stmt = $db_conn->prepare("SELECT warehouse_id FROM invoice WHERE inv_id = ? LIMIT 1");
} else {
    $stmt = $db_conn->prepare("SELECT warehouse_id FROM user_invoice WHERE inv_id = ? LIMIT 1");
}
$stmt->bind_param("s", $invid);
$stmt->execute();
$whRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($whRow && $whRow['warehouse_id'] !== null) {
    $warehouseId = (int) $whRow['warehouse_id'];
} elseif ($to_usertype === 'company' && $to_userid !== 'company' && ctype_digit((string) $to_userid)) {
    // DM-order Shop Invoice godown path (company/ms-order-invoice-add.php,
    // 2026-11-13) never recorded a warehouse_id either, but unlike the
    // TP-invoice/demo/damage backfills this G1 default was written for,
    // THIS invoice type's stock was debited with no warehouse filter at all
    // (see that file's stock-check query) — i.e. the unassigned/NULL-
    // warehouse bucket, not G1. Returning it into G1 would silently split
    // the stock into a second, wrong bucket instead of restoring the one it
    // actually came from. Detected by source_ms_order_id rather than
    // trusting the G1 default for any invoice this narrow condition misses.
    $stmtSrc = $db_conn->prepare("SELECT source_ms_order_id FROM user_invoice WHERE inv_id = ? LIMIT 1");
    $stmtSrc->bind_param("s", $invid);
    $stmtSrc->execute();
    $srcRow = $stmtSrc->get_result()->fetch_assoc();
    $stmtSrc->close();
    if ($srcRow && $srcRow['source_ms_order_id'] !== null) {
        $warehouseId = null;
    }
}

// CP-fulfilled Shop Invoice detection (company/ms-order-invoice-add.php's
// CP-stock path, 2026-11-13) — from_user_id on that invoice is the literal
// string 'company' (not a real company_godown id), because the stock it
// actually drew from was a specific Channel Partner's own
// channel_partner_stock, not any company godown. Returning against it must
// credit that CP's own stock back, not the generic `stock` table under a
// nonexistent user_id='company' row (which would silently lose the stock).
$returnToCpId = null;
if ($to_usertype === 'company' && $to_userid === 'company') {
    $stmtFcp = $db_conn->prepare("SELECT fulfilled_by_cp_id FROM user_invoice WHERE inv_id = ? LIMIT 1");
    $stmtFcp->bind_param('s', $invid);
    $stmtFcp->execute();
    $fcpRow = $stmtFcp->get_result()->fetch_assoc();
    $stmtFcp->close();
    $returnToCpId = ($fcpRow && $fcpRow['fulfilled_by_cp_id'] !== null) ? (int) $fcpRow['fulfilled_by_cp_id'] : null;
}

/*
|--------------------------------------------------------------------------
| ONLY PROCESS IF STILL PENDING
|--------------------------------------------------------------------------
*/
if ($current_status === 'pending') {

    /*
    |----------------------------------------------------------------------
    | ADVANCE PAYMENT CREDIT (Super Stockist/Stockist Only)
    |----------------------------------------------------------------------
    */
    if (in_array($from_usertype, ['super_stockiest', 'stockiest'])) {

        $reference_number = "CN-" . $returnid;

        // Check if credit already exists (prevent duplicate credits)
        if (!hasReturnAdvanceCreditByReference($db_conn, $reference_number)) {

            if ($total_amount > 0) {

                // Fetch invoice details
                $stmt = $db_conn->prepare("
                    SELECT inv_number, date 
                    FROM user_invoice 
                    WHERE inv_id = ?
                    LIMIT 1
                ");
                $stmt->bind_param("s", $invid);
                $stmt->execute();
                $inv = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($inv) {
                    // Add advance payment credit
                    $credit_result = addAdvancePaymentCreditForReturn(
                        $db_conn,
                        $returnid,
                        $invid,
                        $inv['inv_number'],
                        (float)$total_amount,
                        date('Y-m-d'),
                        $inv['date'],
                        $from_userid,
                        $from_usertype,
                        $to_userid,
                        $to_usertype,
                        $Login_user_TYPEvl ?? 'system',
                        $Login_user_TYPEvl ?? 'system'
                    );

                    if ($credit_result['success']) {
                        error_log(
                            "ADVANCE PAYMENT CREDIT ADDED: " .
                            "Return $returnid, Invoice {$inv['inv_number']}, Amount: $total_amount"
                        );
                    } else {
                        error_log(
                            "ADVANCE PAYMENT CREDIT FAILED: " .
                            "Return $returnid, Error: {$credit_result['message']}"
                        );
                        $_SESSION['warningMessage'] = "Return created but advance payment credit failed: " . $credit_result['message'];
                    }
                } else {
                    error_log("FINISH RETURN ERROR: Invoice $invid not found for return $returnid");
                }
            }
        } else {
            error_log("ADVANCE PAYMENT CREDIT SKIPPED: Credit already exists for Return $returnid");
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH RETURN ITEMS (needed before transaction)
|--------------------------------------------------------------------------
*/
$stmt = $db_conn->prepare(
    "SELECT prid, qty FROM user_return_stock_items WHERE returnid = ? AND deleted_at IS NULL"
);
$stmt->bind_param("s", $returnid);
$stmt->execute();
$return_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/*
|--------------------------------------------------------------------------
| ATOMIC TRANSACTION: status updates + stock adjustments
| All-or-nothing: if stock fails, totals and status are rolled back too.
|--------------------------------------------------------------------------
*/
$db_conn->begin_transaction();

try {
    // Update return header totals and status
    $stmt = $db_conn->prepare("
        UPDATE user_return_stock
        SET subtotal = ?, discount = ?, total = ?, status = 'accept'
        WHERE returnid = ?
    ");
    $stmt->bind_param("ddds", $SubTotal, $discount, $total_amount, $returnid);
    $stmt->execute();
    $stmt->close();

    // Update all return items to 'accept'
    $stmt = $db_conn->prepare(
        "UPDATE user_return_stock_items SET status = 'accept' WHERE returnid = ? AND deleted_at IS NULL"
    );
    $stmt->bind_param("s", $returnid);
    $stmt->execute();
    $stmt->close();

    // Stock adjustments via StockService (FOR UPDATE lock + ledger entries)
    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    foreach ($return_items as $item) {
        $prid      = (int) $item['prid'];
        $returnqty = (int) $item['qty'];

        if ($returnToCpId !== null) {
            // Receiver is actually a Channel Partner (see detection above) —
            // credit channel_partner_stock directly, same convention as
            // delete-tp-invoice.php's own CP-stock reversal.
            $uCp = $db_conn->prepare("UPDATE channel_partner_stock SET closing_qty=closing_qty+? WHERE channel_partner_id=? AND product_id=?");
            $uCp->bind_param("iii", $returnqty, $returnToCpId, $prid);
            $uCp->execute();
            $uCpAffected = $uCp->affected_rows;
            $uCp->close();

            // CP had no stock row yet for this product (plausible — a CP can
            // fulfill an order for a product it isn't otherwise stocked in) —
            // the UPDATE above matched zero rows, so create one instead of
            // silently crediting nothing. Without this, $afterCp below reads
            // back 0 (no row), making $beforeCp negative and recording a
            // nonsensical ledger entry while the stock itself is never
            // actually credited. 2026-11-21, code-review finding.
            if ($uCpAffected === 0) {
                $insNewCp = $db_conn->prepare("INSERT INTO channel_partner_stock (channel_partner_id, product_id, closing_qty) VALUES (?, ?, ?)");
                $insNewCp->bind_param("iii", $returnToCpId, $prid, $returnqty);
                $insNewCp->execute();
                $insNewCp->close();
            }

            $rCp = $db_conn->prepare("SELECT closing_qty FROM channel_partner_stock WHERE channel_partner_id=? AND product_id=?");
            $rCp->bind_param("ii", $returnToCpId, $prid);
            $rCp->execute();
            $cpRow = $rCp->get_result()->fetch_assoc();
            $rCp->close();
            $afterCp  = $cpRow ? (int) $cpRow['closing_qty'] : 0;
            $beforeCp = $afterCp - $returnqty;

            // ref_type is a narrow ENUM (input/transfer/invoice/adjustment/
            // opening/tp_invoice) with no 'return' value — 'transfer' is the
            // same convention super-stockist/tp-cnote-finish.php already
            // uses for this exact case.
            $actionCp = 'transfer_in'; $refTypeCp = 'transfer'; $noteCp = 'Return: ' . $invid;
            $insCp = $db_conn->prepare(
                "INSERT INTO channel_partner_stock_ledger (channel_partner_id,product_id,action,qty,qty_before,qty_after,ref_type,ref_id,note,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)"
            );
            $insCp->bind_param("iisiiissss", $returnToCpId, $prid, $actionCp, $returnqty, $beforeCp, $afterCp, $refTypeCp, $returnid, $noteCp, $createdBy);
            $insCp->execute();
            $insCp->close();
        } else {
            // Receiver (to_user, typically company): reverse the original sale
            // closing_qty ↑, sales_qty ↓  →  stock comes back. $warehouseId
            // defaults to G1 (see note above) when the invoice never recorded
            // one — only applied for a company receiver, since other usertypes
            // have no warehouse concept.
            $effectiveWarehouseId = $to_usertype === 'company' ? $warehouseId : null;
            $deductResult = $stockService->reverseDeduct(
                $prid, $to_usertype, $to_userid, $returnqty,
                'return', $returnid, $createdBy,
                true, // externalTransaction
                $effectiveWarehouseId
            );
            if (empty($deductResult['success']) && ($deductResult['reason'] ?? null) === 'no_stock_row') {
                // No stock row exists yet for this exact (product, warehouse) —
                // e.g. G1 never had this product before. Fall back to credit(),
                // which creates the row via INSERT ... ON DUPLICATE KEY, so the
                // returned qty is never silently lost or a legitimate return
                // blocked just because the warehouse row hasn't been created yet.
                $deductResult = $stockService->credit(
                    $prid, $to_usertype, $to_userid, $returnqty,
                    'return', $returnid, $createdBy,
                    true, // externalTransaction
                    $effectiveWarehouseId
                );
            }
            if (empty($deductResult['success'])) {
                throw new \RuntimeException(
                    "Stock credit failed for product {$prid} (to {$to_usertype}/{$to_userid}): "
                    . ($deductResult['reason'] ?? 'unknown reason')
                );
            }
        }

        // Sender (from_user, buyer): remove the returned goods from their ledger
        // closing_qty ↓, input_qty ↓  →  goods physically left buyer
        if (in_array($from_usertype, StockService::STOCK_MAINTAINING_TYPES, true)) {
            $stockService->reverseCredit(
                $prid, $from_usertype, $from_userid, $returnqty,
                'return', $returnid, $createdBy,
                true // externalTransaction
            );
        }

        error_log("CNOTE STOCK ADJUSTED: Product $prid, Qty: $returnqty, Return: $returnid");
    }

    // Claw back 3% of THIS return's own amount from the fulfilling CP's
    // wallet, if that invoice's commission was ever actually credited
    // (no-op otherwise — see function doc).
    if ($returnToCpId !== null) {
        reverseShopWalletCommissionForReturn($db_conn, $invid, $returnid, $total_amount);
    }

    $db_conn->commit();

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("CNOTE FINISH FAILED: Return $returnid — " . $e->getMessage());
    $_SESSION['errorMessage'] = "Credit note finalization failed. Please try again.";
    header("Location: cnote_manage.php?error=stock_update_failed");
    exit;
}

/*
|--------------------------------------------------------------------------
| FETCH INVOICE NUMBER FOR SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/
$stmt = $db_conn->prepare("
    SELECT inv_number 
    FROM user_invoice 
    WHERE inv_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $invid);
$stmt->execute();
$invdata = $stmt->get_result()->fetch_assoc();
$stmt->close();

$inv_number_display = $invdata['inv_number'] ?? 'Unknown';

/*
|--------------------------------------------------------------------------
| SUCCESS LOG AND REDIRECT
|--------------------------------------------------------------------------
*/
error_log(
    "RETURN FINALIZED SUCCESS: " .
    "Return $returnid, Invoice $inv_number_display, " .
    "SubTotal: $SubTotal, Discount: $discount, Total: $total_amount"
);

$_SESSION['successMessage'] = 
    "Credit Note Added Successfully against Invoice Number: " . 
    htmlspecialchars($inv_number_display);

echo "<script>window.location='cnote_manage.php?returnaddedsuccess';</script>";
exit;
?>