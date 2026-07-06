<?php
/**
 * add-receipt.php
 *
 * Business Rules:
 * ──────────────────────────────────────────────────────────────────────────
 * Super Stockist & Stockist ($is_advance_mandatory = true):
 *   - Invoice amount  → paid via Advance Payment deduction → payment_type = 'advance_product'
 *   - Courier charges → paid manually (Cash/UPI/etc.)    → payment_type = 'courier_charge'
 *   - Two separate forms / two separate receipts
 *
 * Distributor & Super Distributor ($is_advance_mandatory = false):
 *   - Pay invoice amount + courier charges together OR separately (manual)
 *   - Single combined form → one receipt                  → payment_type = 'regular'
 *   - OR two receipts when paying courier separately       → payment_type = 'courier_charge'
 *
 * Pending calculation uses payment_type column (not receipt_method) to
 * correctly segregate what has already been paid.
 * ──────────────────────────────────────────────────────────────────────────
 *
 * Security fixes applied:
 *  - All DB reads use prepared statements
 *  - All output escaped with htmlspecialchars()
 *  - receipt_method / receipt_remarks escaped before insert
 *  - CSRF token added to every form POST
 *  - Removed direct $_REQUEST interpolation into SQL
 */

declare(strict_types=1);

include("checksession.php");
error_reporting(1);
ini_set('display_erros','1');

$title = "Receipt";
date_default_timezone_set("Asia/Kolkata");
$current_date = date("Y-m-d");
require_once("advance-payment-functions.php");
require_once("receipt-stock-reversal.php");

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function verifyCsrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submitted)) {
        die('Invalid CSRF token.');
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function GeraHash(int $qtd): string
{
    $chars = '123456789ABDEFGHJKMNPQRS';
    $max   = strlen($chars) - 1;
    $hash  = '';
    for ($x = 0; $x < $qtd; $x++) {
        $hash .= $chars[random_int(0, $max)];
    }
    return $hash;
}

// ── Resolve user type meta ────────────────────────────────────────────────────
$invid       = $_REQUEST['invid']    ?? '';
$getinvuser  = $_REQUEST['invuser']  ?? '';

$userTypeMeta = match($getinvuser) {
    'candf'            => ['label' => 'C&F Name',              'table' => 'c_and_f',           'back' => "user-manage-invoice?invuser=$getinvuser", 'inv_table' => 'user_invoice'],
    'super_stockiest'  => ['label' => 'Super Stockist Name',   'table' => 'super_stockiest',   'back' => "user-manage-invoice?invuser=$getinvuser", 'inv_table' => 'user_invoice'],
    'stockiest'        => ['label' => 'Stockist Name',         'table' => 'stockiest',         'back' => "user-manage-invoice?invuser=$getinvuser", 'inv_table' => 'user_invoice'],
    'super_distributor'=> ['label' => 'Super Distributor Name','table' => 'super_distributor', 'back' => "user-manage-invoice?invuser=$getinvuser", 'inv_table' => 'user_invoice'],
    'distributor'      => ['label' => 'Distributor Name',      'table' => 'distributor',       'back' => "user-manage-invoice?invuser=$getinvuser", 'inv_table' => 'user_invoice'],
    'outlet'           => ['label' => 'Outlet Name',           'table' => 'outlet',            'back' => "user-manage-invoice?invuser=$getinvuser", 'inv_table' => 'user_invoice'],
    'shop'             => ['label' => 'Shop Name',             'table' => 'shop',              'back' => 'shop-user-manage-invoice',               'inv_table' => 'user_invoice'],
    default            => ['label' => 'Customer Name',         'table' => 'customers',         'back' => 'customer-user-manage-invoice',            'inv_table' => 'invoice'],
};

$lablenamedisplay = $userTypeMeta['label'];
$tablename        = $userTypeMeta['table'];
$backlink         = $userTypeMeta['back'];
$invtable_name    = $userTypeMeta['inv_table'];

// Advance payment mandatory for SS / Stockist (and C&F / outlet / shop per your existing helper)
$is_advance_mandatory = isAdvancePaymentMandatory($getinvuser);

// ── Fetch Invoice (prepared) ──────────────────────────────────────────────────
$stmt = $db_conn->prepare("SELECT * FROM `$invtable_name` WHERE inv_id = ?");
$stmt->bind_param('s', $invid);
$stmt->execute();
$result_product_list = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result_product_list) {
    die('Invoice not found.');
}

$courier_charges       = floatval($result_product_list['courier_charges'] ?? 0);
$invoice_total         = floatval($result_product_list['total'] ?? 0);
$invoice_amount_only   = $invoice_total - $courier_charges;   // product-only amount

// ── DELETE RECEIPT ────────────────────────────────────────────────────────────
if (isset($_REQUEST['delreceiptact'])) {
    verifyCsrf();

    $rcptid = (int) base64_decode($_REQUEST['rcptid'] ?? '');

    $stmt = $db_conn->prepare(
        "SELECT id, payment_type, received, inv_id FROM receipt WHERE id = ?"
    );
    $stmt->bind_param('i', $rcptid);
    $stmt->execute();
    $receipt_to_delete = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($receipt_to_delete) {
        $del_payment_type = $receipt_to_delete['payment_type'];

        // ── Restore advance balance (SS / Stockist advance_product receipts) ─
        if ($del_payment_type === 'advance_product' && $is_advance_mandatory) {
            $restoreResult = restoreAdvancePaymentOnInvoiceEdit(
                $db_conn,
                $invid,
                $result_product_list['inv_number'],
                date('Y-m-d'),
                'Receipt deleted - restoring advance payment balance',
                $result_product_list['from_user_id'],
                $result_product_list['from_user_type']
            );
            if (!$restoreResult['success']) {
                error_log("RECEIPT DELETE: Failed to restore advance balance — " . $restoreResult['message']);
            }
        }

        // ── Reverse stock for advance_product AND regular receipts ────────────
        // courier_charge receipts never touch stock, so skip them.
        // is_customer_invoice: true when invtable_name = 'invoice' (customer flow)
        if (in_array($del_payment_type, ['advance_product', 'regular'], true)) {
            $is_customer_inv = ($invtable_name === 'invoice');
            $reversalResult  = reverseStockForInvoice($db_conn, $invid, $is_customer_inv);

            if (!$reversalResult['success']) {
                // Log the failure but still proceed with receipt deletion —
                // blocking the delete would prevent the edit workflow entirely.
                error_log(
                    "RECEIPT DELETE: Stock reversal failed for inv=$invid — " .
                    $reversalResult['message']
                );
            } else {
                error_log(
                    "RECEIPT DELETE: Stock reversed for inv=$invid — " .
                    $reversalResult['items_reversed'] . " item(s)"
                );
            }
        }

        // ── Delete the receipt ────────────────────────────────────────────────
        $del = $db_conn->prepare("DELETE FROM receipt WHERE id = ?");
        $del->bind_param('i', $rcptid);
        $del->execute();
        $del->close();
    }

    $safeInvid   = urlencode($invid);
    $safeInvuser = urlencode($getinvuser);
    echo "<script>window.location='add-receipt.php?invid=$safeInvid&invuser=$safeInvuser&DeletedSuccess';</script>";
    exit;
}

// ── Fetch existing receipts & compute pending amounts ─────────────────────────
/*
 * payment_type column drives the split:
 *   'advance_product' → invoice amount bucket  (SS / Stockist)
 *   'courier_charge'  → courier charges bucket (all user types)
 *   'regular'         → invoice amount bucket  (legacy / other)
 *
 * Distributor / Super Distributor:
 *   - Product amount is NOT collected via this page (stock moves at invoice submit)
 *   - Only courier_charge receipts are added here
 *   - So only $courier_amount_pending is relevant for their flow
 */
$stmt = $db_conn->prepare(
    "SELECT payment_type, received FROM receipt WHERE inv_id = ? AND received > 0 ORDER BY id ASC"
);
$stmt->bind_param('s', $invid);
$stmt->execute();
$all_receipts_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Also fetch full receipt rows for the history table (needs all columns)
$stmt = $db_conn->prepare(
    "SELECT * FROM receipt WHERE inv_id = ? AND received > 0 ORDER BY id ASC"
);
$stmt->bind_param('s', $invid);
$stmt->execute();
$all_receipts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$invoice_amount_received = 0.0;
$courier_amount_received = 0.0;
$total_received          = 0.0;

foreach ($all_receipts_raw as $r) {
    $amt = floatval($r['received']);
    $total_received += $amt;

    if ($r['payment_type'] === 'courier_charge') {
        $courier_amount_received += $amt;
    } else {
        // advance_product and regular both count as invoice amount received
        $invoice_amount_received += $amt;
    }
}

// Pending amounts
$invoice_amount_pending = max(0.0, $invoice_amount_only - $invoice_amount_received);
$courier_amount_pending = max(0.0, $courier_charges     - $courier_amount_received);

$needs_invoice_payment = ($invoice_amount_pending > 0.01);
$needs_courier_payment = ($courier_amount_pending > 0.01);

// ── ADD RECEIPT (POST) ────────────────────────────────────────────────────────
if (isset($_POST['addreceipt'])) {
    verifyCsrf();

    // Distributor / Super Distributor: receipts are generated at invoice
    // submission only. Adding receipts manually is not permitted.
    if (!$is_advance_mandatory) {
        error_log("SECURITY: addreceipt POST blocked for user type ($getinvuser)");
        $safeInvid   = urlencode($invid);
        $safeInvuser = urlencode($getinvuser);
        echo "<script>window.location='add-receipt.php?invid=$safeInvid&invuser=$safeInvuser';</script>";
        exit;
    }

    $post_receiptid    = $_POST['receiptid']       ?? '';
    $post_invid        = $_POST['invid']            ?? '';
    $post_invuser      = $_POST['invuser']          ?? '';
    $post_payment_type = $_POST['payment_type']     ?? '';   // 'invoice' | 'courier'
    $receivable_amount = floatval($_POST['receivableamount'] ?? 0);
    $received_amount   = floatval($_POST['receivedamount']   ?? 0);
    $balance_amount    = $receivable_amount - $received_amount;
    $receipt_date      = date('Y-m-d');

    $safeInvid   = urlencode($post_invid);
    $safeInvuser = urlencode($post_invuser);
    $redirectBase = "add-receipt.php?invid=$safeInvid&invuser=$safeInvuser";

    if ($received_amount > 0 && $received_amount <= $receivable_amount) {

        // Duplicate check
        $dup = $db_conn->prepare("SELECT COUNT(*) FROM receipt WHERE receiptid = ?");
        $dup->bind_param('s', $post_receiptid);
        $dup->execute();
        [$dup_count] = $dup->get_result()->fetch_row();
        $dup->close();

        if ((int)$dup_count === 0) {

            $db_payment_type = 'regular';
            $receipt_method  = '';
            $receipt_remarks = '';

            if ($is_advance_mandatory) {
                // ── SS / Stockist ─────────────────────────────────────────────
                if ($post_payment_type === 'invoice') {
                    // Invoice amount via advance balance deduction
                    $db_payment_type = 'advance_product';
                    $receipt_method  = 'Advance Payment';
                    $receipt_remarks = 'Invoice amount paid via advance payment adjustment';

                    $adjustResult = processInvoiceAdvancePaymentDeduction(
                        $db_conn,
                        $post_invid,
                        $result_product_list['inv_number'],
                        $received_amount,
                        $receipt_date,
                        $result_product_list['to_user_id'],
                        $result_product_list['to_user_type'],
                        $result_product_list['from_user_id'],
                        $result_product_list['from_user_type'],
                        $result_product_list['from_user_id'],
                        $result_product_list['from_user_type']
                    );

                    if (!$adjustResult['success']) {
                        echo "<script>window.location='$redirectBase&InsufficientBalance';</script>";
                        exit;
                    }

                } elseif ($post_payment_type === 'courier') {
                    // Courier charges — manual payment
                    $db_payment_type = 'courier_charge';
                    $receipt_method  = mysqli_real_escape_string($db_conn, $_POST['receipt_method']  ?? '');
                    $receipt_remarks = mysqli_real_escape_string($db_conn, htmlspecialchars($_POST['receipt_remarks'] ?? '', ENT_QUOTES));
                }

            } else {
                // ── Distributor / Super Distributor ───────────────────────────
                // Only courier_charge receipts are added via this page.
                // Product amount stock already moved at invoice submission.
                if ($post_payment_type === 'courier') {
                    $db_payment_type = 'courier_charge';
                    $receipt_method  = mysqli_real_escape_string($db_conn, $_POST['receipt_method']  ?? '');
                    $receipt_remarks = mysqli_real_escape_string($db_conn, htmlspecialchars($_POST['receipt_remarks'] ?? '', ENT_QUOTES));
                } else {
                    // Guard: reject any unexpected payment_type for distributors
                    echo "<script>window.location='$redirectBase&InvalidAmount';</script>";
                    exit;
                }
            }

            // Insert receipt
            $ins = $db_conn->prepare(
                "INSERT INTO receipt
                    (receiptid, inv_id, invoice_amount, received, receivable, date,
                     from_user_type, from_user_id, to_user_type, to_user_id,
                     receipt_method, receipt_remarks, payment_type)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->bind_param(
                'ssdddssssssss',
                $post_receiptid,
                $post_invid,
                $receivable_amount,
                $received_amount,
                $balance_amount,
                $receipt_date,
                $result_product_list['from_user_type'],
                $result_product_list['from_user_id'],
                $result_product_list['to_user_type'],
                $result_product_list['to_user_id'],
                $receipt_method,
                $receipt_remarks,
                $db_payment_type
            );
            $ins->execute();
            $ins->close();

            // ── Apply stock for SS / Stockist advance_product receipts ────────
            // Stock for distributors is applied at invoice submission time.
            // For SS/Stockist the invoice is created first (without stock
            // movement) and payment is confirmed here when the advance receipt
            // is added — that is when we apply stock.
            //
            // applyStockForInvoice() contains an internal guard: it checks
            // whether a prior advance_product/regular receipt already exists
            // for this invoice. If one does, stock was already applied and the
            // function exits early (returns message='already_applied').
            // This prevents double-application if the receipt form is submitted
            // twice or a partial advance receipt is being added later.
            if ($db_payment_type === 'advance_product') {
                $is_customer_inv = ($invtable_name === 'invoice');
                $stockResult = applyStockForInvoice(
                    $db_conn,
                    $post_invid,
                    $is_customer_inv,
                    $post_receiptid  // exclude this new receipt from the guard check
                );

                if (!$stockResult['success']) {
                    // Log the failure but do not block — the receipt was already
                    // inserted and the advance balance already deducted.
                    // Admin can manually adjust stock if needed.
                    error_log(
                        "RECEIPT ADD: Stock apply failed for inv=$post_invid — " .
                        $stockResult['message']
                    );
                } elseif ($stockResult['message'] === 'already_applied') {
                    error_log("RECEIPT ADD: Stock already applied for inv=$post_invid — skipped");
                } else {
                    error_log(
                        "RECEIPT ADD: Stock applied for inv=$post_invid — " .
                        $stockResult['items_applied'] . " item(s)"
                    );
                }
            }
        }

        echo "<script>window.location='$redirectBase&ReceiptAddedSuc';</script>";
    } else {
        echo "<script>window.location='$redirectBase&InvalidAmount';</script>";
    }
    exit;
}

// ── Fetch customer name / mobile ──────────────────────────────────────────────
if ($getinvuser === 'customer') {
    $cust_stmt = $db_conn->prepare("SELECT name, mobile FROM $tablename WHERE id = ?");
    $cust_stmt->bind_param('s', $result_product_list['customer_id']);
} else {
    $cust_stmt = $db_conn->prepare("SELECT name, mobile_number AS mobile FROM $tablename WHERE temp_id = ?");
    $cust_stmt->bind_param('s', $result_product_list['to_user_id']);
}
$cust_stmt->execute();
$cust_row  = $cust_stmt->get_result()->fetch_assoc();
$cust_stmt->close();

$Cust_Name  = $cust_row['name']   ?? '—';
$Cust_Mbile = $cust_row['mobile'] ?? '—';

// ── Shared receipt ID parts ───────────────────────────────────────────────────
$temp_date = date('dmy');
$temp_time = date('gis');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> : <?= e($business_name ?? '') ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Round" rel="stylesheet">
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/plugins/perfectscroll/perfect-scrollbar.css" rel="stylesheet">
    <link href="../../assets/plugins/pace/pace.css" rel="stylesheet">
    <link href="../../assets/css/main.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/neptune.png" />

    <style>
    /* ── Base ── */
    *, *::before, *::after { box-sizing: border-box; }
    body { font-family: 'Poppins', sans-serif; }

    /* ── Alerts ── */
    .alert {
        border-radius: 10px;
        border: none;
        padding: 14px 18px;
        margin-bottom: 18px;
        box-shadow: 0 2px 8px rgba(0,0,0,.07);
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .alert .material-icons { font-size: 20px; flex-shrink: 0; margin-top: 1px; }
    .alert-success  { background:#d1fae5; color:#065f46; border-left:4px solid #10b981; }
    .alert-danger   { background:#fee2e2; color:#991b1b; border-left:4px solid #ef4444; }
    .alert-info     { background:#dbeafe; color:#1e40af; border-left:4px solid #3b82f6; }
    .alert-warning  { background:#fef3c7; color:#92400e; border-left:4px solid #f59e0b; }

    /* ── Cards ── */
    .card { border:2px solid #e5e7eb; border-radius:12px; box-shadow:0 1px 4px rgba(0,0,0,.05); margin-bottom:20px; }
    .card-body { padding:24px; }
    .section-title {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        margin: 24px 0 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .section-title .material-icons { font-size: 20px; color: #2563eb; }

    /* ── Tables ── */
    table.femi-table { width:100%; border-collapse:collapse; margin-bottom:4px; }
    table.femi-table th {
        background:#f8fafc; color:#475569; font-weight:600;
        font-size:12px; text-transform:uppercase; letter-spacing:.4px;
        padding:11px 12px; border-bottom:2px solid #e5e7eb;
    }
    table.femi-table td { padding:11px 12px; border-bottom:1px solid #f1f5f9; color:#1e293b; font-size:14px; }
    table.femi-table tbody tr:hover td { background:#f8fafc; }
    table.femi-table tfoot td { font-weight:700; background:#f1f5f9; }

    /* ── Summary grid ── */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin: 16px 0;
    }
    .summary-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px 16px;
        text-align: center;
    }
    .summary-box .s-label { font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }
    .summary-box .s-value { font-size: 20px; font-weight: 700; color: #1e293b; margin-top: 4px; }
    .summary-box.green { border-color: #10b981; background: #ecfdf5; }
    .summary-box.green .s-value { color: #065f46; }
    .summary-box.orange { border-color: #f59e0b; background: #fffbeb; }
    .summary-box.orange .s-value { color: #92400e; }
    .summary-box.red { border-color: #ef4444; background: #fef2f2; }
    .summary-box.red .s-value { color: #991b1b; }
    .summary-box.blue { border-color: #3b82f6; background: #eff6ff; }
    .summary-box.blue .s-value { color: #1e40af; }

    /* ── Payment form card ── */
    .payment-card {
        border-radius: 12px;
        border: 2px solid;
        padding: 20px 22px;
        margin-bottom: 20px;
    }
    .payment-card.advance { border-color: #a78bfa; background: #faf5ff; }
    .payment-card.courier { border-color: #93c5fd; background: #eff6ff; }
    .payment-card.combined { border-color: #6ee7b7; background: #ecfdf5; }
    .payment-card h4 {
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .payment-card.advance  h4 { color: #6d28d9; }
    .payment-card.courier  h4 { color: #1d4ed8; }
    .payment-card.combined h4 { color: #065f46; }

    /* ── Form controls ── */
    .form-label { font-weight:500; color:#374151; margin-bottom:6px; font-size:13px; display:block; }
    .form-control {
        border:2px solid #e5e7eb; border-radius:8px;
        padding:9px 13px; font-size:14px; width:100%;
        margin-bottom:14px; transition: border-color .2s;
    }
    .form-control:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); outline:none; }
    .form-control:disabled, .form-control[readonly] { background:#f8fafc; color:#94a3b8; cursor:not-allowed; }

    .btn-submit {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff; border: none; border-radius: 8px;
        padding: 10px 22px; font-size: 14px; font-weight: 600;
        cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
        transition: filter .2s, transform .1s;
    }
    .btn-submit:hover  { filter: brightness(1.08); }
    .btn-submit:active { transform: scale(.97); }
    .btn-submit.purple { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
    .btn-submit.teal   { background: linear-gradient(135deg, #059669, #047857); }

    /* ── Badges ── */
    .badge-pt {
        display:inline-flex; align-items:center; gap:4px;
        padding: 4px 10px; border-radius:999px;
        font-size:11px; font-weight:700;
    }
    .badge-advance  { background:#ede9fe; color:#5b21b6; }
    .badge-courier  { background:#dbeafe; color:#1e40af; }
    .badge-regular  { background:#dcfce7; color:#166534; }

    /* ── Delete link ── */
    .delete-link {
        color: #dc2626; font-size:13px; font-weight:600;
        text-decoration:none; display:inline-flex; align-items:center; gap:4px;
        padding: 4px 10px; border-radius:6px; background: #fee2e2;
        transition: background .15s;
    }
    .delete-link:hover { background:#fecaca; color:#991b1b; }

    /* ── Payment tabs (Distributor flow) ── */
    .payment-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 0;
        flex-wrap: wrap;
    }
    .ptab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 18px;
        border: 2px solid #e2e8f0;
        border-bottom: none;
        border-radius: 10px 10px 0 0;
        background: #f8fafc;
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s, color .15s, border-color .15s;
        font-family: inherit;
    }
    .ptab:hover { background: #f1f5f9; color: #1e293b; }
    .ptab.ptab-active {
        background: #fff;
        color: #1e293b;
        border-color: #cbd5e1;
        border-bottom-color: #fff;
        z-index: 1;
        position: relative;
    }
    .ptab .material-icons { font-size: 16px; }
    .ptab-badge {
        background: #dcfce7; color: #166534;
        font-size: 11px; font-weight: 700;
        padding: 2px 7px; border-radius: 999px;
    }
    .ptab-badge-blue { background: #dbeafe; color: #1e40af; }
    .tab-panel { display: none; border-radius: 0 10px 10px 10px !important; }
    .tab-panel-active { display: block; }

    /* ── Two-column form row ── */
    .form-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 0;
    }
    @media (max-width: 600px) { .form-row-2 { grid-template-columns: 1fr; } }

    /* ── Paid pill ── */
    .paid-pill {
        display:inline-block; background:#d1fae5; color:#065f46;
        font-size:12px; font-weight:700; padding:6px 14px; border-radius:999px;
        border:1px solid #6ee7b7;
    }
    </style>
</head>
<body>
<div class="app align-content-stretch d-flex flex-wrap">
    <div class="app-sidebar">
        <?php include("logo.php"); ?>
        <?php include("femi_menu.php"); ?>
    </div>
    <div class="app-container">
        <?php include("app-header.php"); ?>

        <div class="app-content">
            <div class="content-wrapper">
                <div class="container-fluid">

                    <!-- Page title -->
                    <div class="row">
                        <div class="col">
                            <div class="page-description">
                                <h1>
                                    <table class="headertble"><tr>
                                        <td><?= e($title) ?></td>
                                        <td><a href="<?= e($backlink) ?>" title="Go Back">&#9776;</a></td>
                                    </tr></table>
                                </h1>
                            </div>
                        </div>
                    </div>

                    <!-- Flash messages -->
                    <?php if (isset($_REQUEST['ReceiptAddedSuc'])): ?>
                    <div class="alert alert-success">
                        <i class="material-icons">check_circle</i>
                        <div><strong>Receipt added successfully.</strong></div>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($_REQUEST['DeletedSuccess'])): ?>
                    <div class="alert alert-success">
                        <i class="material-icons">delete</i>
                        <div><strong>Receipt deleted.</strong> Advance balance has been restored if applicable.</div>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($_REQUEST['InvalidAmount'])): ?>
                    <div class="alert alert-danger">
                        <i class="material-icons">error</i>
                        <div><strong>Invalid amount.</strong> Received amount must be greater than 0 and ≤ balance.</div>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($_REQUEST['InsufficientBalance'])): ?>
                    <div class="alert alert-danger">
                        <i class="material-icons">account_balance_wallet</i>
                        <div><strong>Insufficient advance balance.</strong> Please top up advance payment first.</div>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">

                                    <!-- ── Invoice Details ──────────────────── -->
                                    <div class="section-title">
                                        <i class="material-icons">description</i> Invoice Details
                                    </div>
                                    <table class="femi-table">
                                        <thead><tr>
                                            <th>Invoice No.</th>
                                            <th><?= e($lablenamedisplay) ?></th>
                                            <th>Date</th>
                                            <th>Product Amount</th>
                                            <th>Courier Charges</th>
                                            <th>Total</th>
                                        </tr></thead>
                                        <tbody><tr>
                                            <td><?= e($result_product_list['inv_number']) ?></td>
                                            <td><?= e($Cust_Name) ?><br><small style="color:#64748b">M: <?= e($Cust_Mbile) ?></small></td>
                                            <td><?= e(date('d/M/Y', strtotime($result_product_list['date']))) ?></td>
                                            <td>₹<?= inr_format($invoice_amount_only, 2) ?></td>
                                            <td>₹<?= inr_format($courier_charges, 2) ?></td>
                                            <td><strong>₹<?= inr_format($invoice_total, 2) ?></strong></td>
                                        </tr></tbody>
                                    </table>

                                    <!-- ── Payment Summary ──────────────────── -->
                                    <div class="section-title" style="margin-top:20px;">
                                        <i class="material-icons">bar_chart</i> Payment Summary
                                    </div>

                                    <?php if ($is_advance_mandatory): ?>
                                    <!-- SS / Stockist: two separate pools -->
                                    <div class="summary-grid">
                                        <div class="summary-box blue">
                                            <div class="s-label">Invoice Amount</div>
                                            <div class="s-value">₹<?= inr_format($invoice_amount_only, 2) ?></div>
                                        </div>
                                        <div class="summary-box green">
                                            <div class="s-label">Invoice Received</div>
                                            <div class="s-value">₹<?= inr_format($invoice_amount_received, 2) ?></div>
                                        </div>
                                        <div class="summary-box <?= $invoice_amount_pending > 0.01 ? 'red' : 'green' ?>">
                                            <div class="s-label">Invoice Pending</div>
                                            <div class="s-value">₹<?= inr_format($invoice_amount_pending, 2) ?></div>
                                        </div>
                                        <div class="summary-box blue">
                                            <div class="s-label">Courier Charges</div>
                                            <div class="s-value">₹<?= inr_format($courier_charges, 2) ?></div>
                                        </div>
                                        <div class="summary-box green">
                                            <div class="s-label">Courier Received</div>
                                            <div class="s-value">₹<?= inr_format($courier_amount_received, 2) ?></div>
                                        </div>
                                        <div class="summary-box <?= $courier_amount_pending > 0.01 ? 'orange' : 'green' ?>">
                                            <div class="s-label">Courier Pending</div>
                                            <div class="s-value">₹<?= inr_format($courier_amount_pending, 2) ?></div>
                                        </div>
                                    </div>

                                    <?php else: ?>
                                    <!-- Distributor / Super Distributor: courier charges only -->
                                    <?php if ($courier_charges > 0.01): ?>
                                    <div class="summary-grid">
                                        <div class="summary-box blue">
                                            <div class="s-label">Courier Charges</div>
                                            <div class="s-value">₹<?= inr_format($courier_charges, 2) ?></div>
                                        </div>
                                        <div class="summary-box green">
                                            <div class="s-label">Courier Received</div>
                                            <div class="s-value">₹<?= inr_format($courier_amount_received, 2) ?></div>
                                        </div>
                                        <div class="summary-box <?= $courier_amount_pending > 0.01 ? 'orange' : 'green' ?>">
                                            <div class="s-label">Courier Pending</div>
                                            <div class="s-value">₹<?= inr_format($courier_amount_pending, 2) ?></div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info" style="margin-top:0">
                                        <i class="material-icons">info</i>
                                        <div>No courier charges on this invoice. Product amount is handled at invoice level.</div>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>

                                    <!-- ── Receipt History ──────────────────── -->
                                    <?php if (!empty($all_receipts)): ?>
                                    <div class="section-title">
                                        <i class="material-icons">receipt_long</i> Receipt History
                                    </div>
                                    <table class="femi-table">
                                        <thead><tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Payment Type</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Remarks</th>
                                            <th>Action</th>
                                        </tr></thead>
                                        <tbody>
                                        <?php foreach ($all_receipts as $i => $r):
                                            if (floatval($r['received']) <= 0) continue;

                                            $badgeClass = match($r['payment_type']) {
                                                'advance_product' => 'badge-advance',
                                                'courier_charge'  => 'badge-courier',
                                                default           => 'badge-regular',
                                            };
                                            $badgeLabel = match($r['payment_type']) {
                                                'advance_product' => 'Advance / Invoice',
                                                'courier_charge'  => 'Courier Charges',
                                                default           => 'Regular Payment',
                                            };
                                            $icon = match($r['payment_type']) {
                                                'advance_product' => 'account_balance_wallet',
                                                'courier_charge'  => 'local_shipping',
                                                default           => 'payments',
                                            };

                                            // Delete confirm message
                                            $deleteMsg = 'Delete this receipt?';
                                            if ($r['payment_type'] === 'advance_product') {
                                                $deleteMsg = 'Delete this receipt? The advance balance will be restored.';
                                            }
                                        ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><?= e(date('d/m/Y', strtotime($r['date']))) ?></td>
                                            <td>
                                                <span class="badge-pt <?= $badgeClass ?>">
                                                    <i class="material-icons" style="font-size:13px"><?= $icon ?></i>
                                                    <?= e($badgeLabel) ?>
                                                </span>
                                            </td>
                                            <td><strong>₹<?= inr_format((float)$r['received'], 2) ?></strong></td>
                                            <td><?= e($r['receipt_method']) ?></td>
                                            <td><?= e($r['receipt_remarks']) ?></td>
                                            <td>
                                                <!-- Delete uses GET with CSRF in URL is insecure; use a tiny form -->
                                                <form method="POST" style="display:inline"
                                                      onsubmit="return confirm('<?= e($deleteMsg) ?>')">
                                                    <input type="hidden" name="csrf_token"  value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="delreceiptact" value="1">
                                                    <input type="hidden" name="rcptid"   value="<?= e(base64_encode((string)$r['id'])) ?>">
                                                    <input type="hidden" name="invid"    value="<?= e($invid) ?>">
                                                    <input type="hidden" name="invuser"  value="<?= e($getinvuser) ?>">
                                                    <button type="submit" class="delete-link">
                                                        <i class="material-icons" style="font-size:14px">delete</i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="3" style="text-align:right">Total Received</td>
                                                <td>₹<?= inr_format($total_received, 2) ?></td>
                                                <td colspan="3"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <?php endif; ?>

                                    <!-- ═══════════════════════════════════════════════════
                                         PAYMENT FORMS
                                         ─────────────────────────────────────────────────
                                         FLOW A: Super Stockist / Stockist (advance mandatory)
                                           Form 1 – Invoice Amount via Advance Payment
                                           Form 2 – Courier Charges manually
                                         ─────────────────────────────────────────────────
                                         FLOW B: Distributor / Super Distributor
                                           Form 1 – Combined (invoice + courier) payment
                                           Form 2 – Courier-only (if courier still pending
                                                     after a partial combined payment)
                                         ═══════════════════════════════════════════════════ -->

                                    <?php
                                    // SS/Stockist: fully paid = invoice + courier both cleared
                                    // Distributor: fully paid = courier cleared (or no courier on invoice)
                                    // SS/Stockist: fully paid = both pools cleared
                                    // Distributor: never show "fully paid" banner — the info
                                    // panel in Flow B already explains the receipt model.
                                    $fully_paid = $is_advance_mandatory
                                        && (!$needs_invoice_payment && !$needs_courier_payment);
                                    ?>

                                    <?php if ($fully_paid): ?>
                                    <div class="alert alert-success" style="margin-top:20px">
                                        <i class="material-icons">check_circle</i>
                                        <div><strong>Invoice fully paid.</strong> All amounts including courier charges have been received.</div>
                                    </div>

                                    <?php elseif ($is_advance_mandatory): ?>
                                    <!-- ══════════ FLOW A: SS / Stockist ══════════ -->

                                    <div class="section-title">
                                        <i class="material-icons">add_card</i> Add Payment
                                    </div>

                                    <?php if ($needs_invoice_payment): ?>
                                    <!-- Form A1: Invoice Amount via Advance Payment -->
                                    <div class="payment-card advance">
                                        <h4>
                                            <i class="material-icons">account_balance_wallet</i>
                                            Pay Invoice Amount via Advance Payment
                                        </h4>
                                        <p style="font-size:13px;color:#5b21b6;margin-bottom:14px">
                                            Amount pending: <strong>₹<?= inr_format($invoice_amount_pending, 2) ?></strong>
                                            &nbsp;·&nbsp; Will be auto-deducted from advance balance.
                                        </p>

                                        <form method="POST" onsubmit="return confirm('Deduct ₹<?= inr_format($invoice_amount_pending, 2) ?> from advance balance?')">
                                            <input type="hidden" name="csrf_token"      value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="invid"           value="<?= e($invid) ?>">
                                            <input type="hidden" name="invuser"         value="<?= e($getinvuser) ?>">
                                            <input type="hidden" name="payment_type"    value="invoice">
                                            <input type="hidden" name="receiptid"       value="<?= e(GeraHash(10) . '/RCPT/' . $temp_date . '/' . $temp_time) ?>">
                                            <input type="hidden" name="receivableamount" value="<?= $invoice_amount_pending ?>">
                                            <input type="hidden" name="receivedamount"  value="<?= $invoice_amount_pending ?>">
                                            <button type="submit" name="addreceipt" class="btn-submit purple">
                                                <i class="material-icons">account_balance_wallet</i>
                                                Pay ₹<?= inr_format($invoice_amount_pending, 2) ?> from Advance
                                            </button>
                                        </form>
                                    </div>
                                    <?php else: ?>
                                    <div class="paid-pill" style="margin-bottom:14px">✅ Invoice amount fully received via advance payment</div>
                                    <?php endif; ?>

                                    <?php if ($needs_courier_payment): ?>
                                    <!-- Form A2: Courier Charges manually -->
                                    <div class="payment-card courier">
                                        <h4>
                                            <i class="material-icons">local_shipping</i>
                                            Pay Courier Charges (Manual)
                                        </h4>
                                        <p style="font-size:13px;color:#1d4ed8;margin-bottom:14px">
                                            Amount pending: <strong>₹<?= inr_format($courier_amount_pending, 2) ?></strong>
                                            &nbsp;·&nbsp; Paid separately via Cash / UPI / Bank Transfer.
                                        </p>

                                        <form method="POST" onsubmit="return confirm('Submit courier charge receipt?')">
                                            <input type="hidden" name="csrf_token"      value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="invid"           value="<?= e($invid) ?>">
                                            <input type="hidden" name="invuser"         value="<?= e($getinvuser) ?>">
                                            <input type="hidden" name="payment_type"    value="courier">
                                            <input type="hidden" name="receiptid"       value="<?= e(GeraHash(10) . '/RCPT/' . $temp_date . '/' . $temp_time . 'C') ?>">
                                            <input type="hidden" name="receivableamount" value="<?= $courier_amount_pending ?>">

                                            <label class="form-label">Date</label>
                                            <input type="date" value="<?= date('Y-m-d') ?>" class="form-control" disabled>

                                            <label class="form-label">Courier Charge Balance</label>
                                            <input type="number" class="form-control" value="<?= $courier_amount_pending ?>" disabled>

                                            <label class="form-label">Received Amount</label>
                                            <input type="number" id="cc_received" name="receivedamount" required
                                                   min="0.01" max="<?= $courier_amount_pending ?>" step="0.01"
                                                   class="form-control" placeholder="Enter amount received"
                                                   oninput="calcBalance('cc_received','<?= $courier_amount_pending ?>','cc_balance')">

                                            <label class="form-label">Payment Method</label>
                                            <select name="receipt_method" required class="form-control">
                                                <option value="" hidden>Select method</option>
                                                <option>Cash</option>
                                                <option>UPI</option>
                                                <option>Bank Transfer</option>
                                                <option>Deposit</option>
                                            </select>

                                            <label class="form-label">Remarks</label>
                                            <textarea name="receipt_remarks" required class="form-control"
                                                      placeholder="Courier charge payment remarks"></textarea>

                                            <label class="form-label">Remaining After This</label>
                                            <input type="number" id="cc_balance" class="form-control" readonly>

                                            <button type="submit" name="addreceipt" class="btn-submit" style="margin-top:6px">
                                                <i class="material-icons">add</i> Submit Courier Receipt
                                            </button>
                                        </form>
                                    </div>
                                    <?php else: ?>
                                    <div class="paid-pill" style="margin-bottom:14px">✅ Courier charges fully received</div>
                                    <?php endif; ?>

                                    <?php else: ?>
                                    <!-- ══════════ FLOW B: Distributor / Super Distributor ══════════
                                         Receipts are generated automatically at invoice submission.
                                         Adding receipts manually is disabled for this user type.
                                         Receipts can still be DELETED to enable invoice editing.
                                    ═══════════════════════════════════════════════════════════════ -->
                                    <div class="alert alert-info" style="margin-top:20px">
                                        <i class="material-icons">info</i>
                                        <div>
                                            <strong>Receipts are managed automatically for this account.</strong><br>
                                            Payments are recorded at invoice submission. To edit the invoice,
                                            delete the relevant receipt below and resubmit.
                                        </div>
                                    </div>

                                    <?php endif; // end FLOW B ?>

                                </div><!-- /card-body -->
                            </div><!-- /card -->
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="../../assets/plugins/jquery/jquery-3.5.1.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/perfectscroll/perfect-scrollbar.min.js"></script>
<script src="../../assets/plugins/pace/pace.min.js"></script>
<script src="../../assets/js/main.min.js"></script>
<script src="../../assets/js/custom.js"></script>

<script>
/**
 * Live balance calculator: balance = max(0, receivable - received)
 * @param {string} receivedId   – input element id
 * @param {number|string} max   – max receivable amount
 * @param {string} balanceId    – output element id
 */
function calcBalance(receivedId, max, balanceId) {
    const received = parseFloat(document.getElementById(receivedId).value) || 0;
    const balance  = Math.max(0, parseFloat(max) - received);
    document.getElementById(balanceId).value = balance.toFixed(2);
}

/**
 * Tab switcher for Distributor payment forms
 * @param {string} targetId  – id of the tab-panel to show
 * @param {Element} btn      – the clicked .ptab button
 */
function switchTab(targetId, btn) {
    // Deactivate all panels & tabs within the same section-title scope
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('tab-panel-active'));
    document.querySelectorAll('.ptab').forEach(b => b.classList.remove('ptab-active'));
    // Activate selected
    document.getElementById(targetId).classList.add('tab-panel-active');
    btn.classList.add('ptab-active');
}
</script>
</body>
</html>
