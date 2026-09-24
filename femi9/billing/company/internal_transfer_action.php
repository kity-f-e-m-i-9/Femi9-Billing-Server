<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
require_once("include/GodownAccess.php");
include("RemoveSpecialChar.php");

// Internal Stock Transfer is a finance-only area.
$__usertype = get_login_usertype($db_conn);
if ($__usertype !== 'finance') {
    header("Location: dashboard.php");
    exit;
}

error_reporting(0);

if (!isset($_REQUEST['add-record'])) {
    exit;
}

// ── Invoice-number duplicate guard ────────────────────────────────────────────
if ((int)($_REQUEST['invoice_number_accept'] ?? 1) === 0) {
    $_SESSION['errorMessage'] = "Invoice Number already exists!";
    echo "<script>window.location='internal_transfer?invoicealready';</script>";
    exit;
}

// ── Scalar inputs ─────────────────────────────────────────────────────────────
$inv_number      = RemoveSpecialChar(str_replace("'", "", $_REQUEST['inv_number'] ?? ''));
$tempid          = preg_replace('/[^A-Z0-9\/]/', '', strtoupper($_REQUEST['tempid'] ?? ''));
$send_from       = (string)(int)($_REQUEST['send_from'] ?? 0);
$send_to         = (string)(int)($_REQUEST['send_to']   ?? 0);
$date            = date("Y-m-d", strtotime($_REQUEST['date'] ?? 'now'));
$courier_charges = RemoveSpecialChar($_REQUEST['courier_charges'] ?? '0');
$username        = htmlspecialchars(strip_tags(trim($_REQUEST['username'] ?? '')), ENT_QUOTES, 'UTF-8');
$usertype        = htmlspecialchars(strip_tags(trim($_REQUEST['usertype'] ?? '')), ENT_QUOTES, 'UTF-8');

// Optional: which physical godown (warehouse) this transfer moves stock
// out of / into. Blank/absent means "unassigned", matching every other
// warehouse-aware workflow so far.
$warehouseFromId = filter_var($_REQUEST['warehouse_from_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
$warehouseToId   = filter_var($_REQUEST['warehouse_to_id']   ?? '', FILTER_VALIDATE_INT) ?: null;

if ($send_from === '0' || $to = $send_to === '0') {
    $_SESSION['errorMessage'] = "Invalid godown selection.";
    echo "<script>window.location='internal_transfer?invalid';</script>";
    exit;
}

if (!is_godown_allowed($db_conn, (int)$send_from) || !is_godown_allowed($db_conn, (int)$send_to)) {
    $_SESSION['errorMessage'] = "You are not authorized to use this company profile.";
    echo "<script>window.location='internal_transfer?unauthorized';</script>";
    exit;
}

if ($send_from === $send_to) {
    $_SESSION['errorMessage'] = "Same Company not accepted!";
    echo "<script>window.location='internal_transfer?samecompany';</script>";
    exit;
}

// ── Array inputs ──────────────────────────────────────────────────────────────
$product_ids  = $_REQUEST['product_id'] ?? [];
$qty_arr      = $_REQUEST['qty']        ?? [];
$rate_arr     = $_REQUEST['rate']       ?? [];
$discount_arr = $_REQUEST['discount']   ?? [];

if (!is_array($product_ids) || count($product_ids) === 0) {
    $_SESSION['errorMessage'] = "No products submitted.";
    echo "<script>window.location='internal_transfer?invalid';</script>";
    exit;
}

// Normalise per-row values
$rows = [];
$zeroRateProductIds = [];
foreach ($product_ids as $i => $rawPid) {
    $pid  = (int) $rawPid;
    $qty  = (int) RemoveSpecialChar($qty_arr[$i]      ?? '0');
    $rate = (float)($rate_arr[$i]                     ?? 0);
    $disc = (float)($discount_arr[$i]                 ?? 0);
    if ($pid <= 0 || $qty <= 0) continue;
    // A ₹0 (or blank, which casts to 0) rate silently produced a real
    // internal_transfer row with a zero-value line — the qty/stock still
    // moved correctly, but the invoice showed that product transferred for
    // free, with no amount/GST recorded at all. The client-side rate input
    // only enforces min="0" (required="" doesn't block an explicit "0"), so
    // this is the actual gatekeeper. The WHOLE submission is rejected
    // (rather than silently dropping just that row) so what gets saved
    // always matches exactly what the user reviewed on screen before
    // clicking Submit. Confirmed 2026-09-24.
    if ($rate <= 0) { $zeroRateProductIds[] = $pid; continue; }
    $rows[] = compact('pid', 'qty', 'rate', 'disc');
}

if (!empty($zeroRateProductIds)) {
    $namesStmt = $db_conn->prepare("SELECT productName FROM products WHERE id IN (" . implode(',', array_fill(0, count($zeroRateProductIds), '?')) . ")");
    $namesStmt->bind_param(str_repeat('i', count($zeroRateProductIds)), ...$zeroRateProductIds);
    $namesStmt->execute();
    $names = array_column($namesStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'productName');
    $namesStmt->close();
    $_SESSION['errorMessage'] = "This transfer was NOT saved — no rate (₹0) was entered for: " . implode(', ', $names) . ". Please enter a valid rate for every product and resubmit.";
    echo "<script>window.location='internal_transfer?invalid';</script>";
    exit;
}

if (empty($rows)) {
    $_SESSION['errorMessage'] = "No valid products.";
    echo "<script>window.location='internal_transfer?invalid';</script>";
    exit;
}

// ── Tempid duplicate guard ────────────────────────────────────────────────────
$stmtChk = $db_conn->prepare(
    "SELECT COUNT(*) AS n FROM internal_transfer WHERE tempid = ?"
);
$stmtChk->bind_param('s', $tempid);
$stmtChk->execute();
if ((int)$stmtChk->get_result()->fetch_assoc()['n'] > 0) {
    echo "<script>window.location='internal_transfer_print?tempid=$tempid';</script>";
    exit;
}
$stmtChk->close();

// ── Pre-validate stock for all rows (outside transaction, no lock) ────────────
$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

foreach ($rows as $row) {
    $available = $stockService->getClosingQty($row['pid'], $Login_user_TYPEvl, $send_from, $warehouseFromId);
    if ($available === null || $available < $row['qty']) {
        $_SESSION['errorMessage'] =
            "Insufficient stock for product #{$row['pid']}. " .
            "Available: " . ($available ?? 0) . ", Requested: {$row['qty']}";
        echo "<script>window.location='internal_transfer?InvalidStock&&AlertStockError';</script>";
        exit;
    }
}

// ── Begin atomic transaction ──────────────────────────────────────────────────
$db_conn->begin_transaction();

try {
    $stmtInvChk = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM internal_transfer_invoice WHERE tempid = ?"
    );
    $stmtInvIns = $db_conn->prepare(
        "INSERT INTO internal_transfer_invoice (tempid, inv_id, inv_number, courier_charges)
         VALUES (?, '0', ?, ?)"
    );
    $stmtProdChk = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM internal_transfer WHERE tempid = ? AND product_id = ?"
    );
    $stmtProdIns = $db_conn->prepare(
        "INSERT INTO internal_transfer
             (tempid, send_from, send_to, date, product_id, qty, price, discount,
              sub_total, gst, gst_type, taxable_value, gst_amount, total, hsn, username, usertype)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmtProd = $db_conn->prepare(
        "SELECT gst, gst_type, hsn FROM products WHERE id = ?"
    );

    foreach ($rows as $row) {
        $pid  = $row['pid'];
        $qty  = $row['qty'];
        $rate = $row['rate'];
        $disc = $row['disc'];

        // Fetch product details
        $stmtProd->bind_param('i', $pid);
        $stmtProd->execute();
        $prod = $stmtProd->get_result()->fetch_assoc();
        if (!$prod) continue;

        $sub_total_rate = $rate * $qty;
        $sub_total      = $sub_total_rate - $disc;
        $gst            = (float) $prod['gst'];
        $gst_type       = ($prod['gst_type'] === 'inclusive') ? 'inclusive' : 'exclusive';
        $hsn            = $prod['hsn'];

        // GST is never trusted from the client — always taken from the
        // product's own gst/gst_type at the moment of transfer, same
        // convention as neksomo-manufacturer-purchase-action.php. Inclusive
        // products already have GST baked into the entered rate, so the
        // taxable value is backed out of sub_total instead of adding GST on
        // top of it (previously this page always added GST on top
        // regardless of gst_type, overcharging tax on inclusive products).
        if ($gst_type === 'inclusive') {
            $total         = $sub_total;
            $taxable_value = number_format($total / (1 + $gst / 100), 2, '.', '');
            $gst_amount    = number_format($total - $taxable_value, 2, '.', '');
        } else {
            $taxable_value = number_format($sub_total, 2, '.', '');
            $gst_amount    = number_format($sub_total * $gst / 100, 2, '.', '');
            $total         = (float) $taxable_value + (float) $gst_amount;
        }

        // Create invoice header once per tempid
        $stmtInvChk->bind_param('s', $tempid);
        $stmtInvChk->execute();
        if ((int) $stmtInvChk->get_result()->fetch_assoc()['n'] === 0) {
            $stmtInvIns->bind_param('sss', $tempid, $inv_number, $courier_charges);
            $stmtInvIns->execute();
        }

        // Skip duplicate product under this tempid
        $stmtProdChk->bind_param('si', $tempid, $pid);
        $stmtProdChk->execute();
        if ((int) $stmtProdChk->get_result()->fetch_assoc()['n'] > 0) continue;

        // Insert transfer line. gst_amount/total/taxable_value are bound as
        // strings — number_format() returns a string in the inclusive
        // branch and total is a float in the exclusive branch, but the
        // destination columns (gst_amount/total varchar, taxable_value
        // decimal) accept either via mysqli's string coercion; using 's'
        // uniformly avoids a type mismatch between the two branches.
        $stmtProdIns->bind_param(
            'ssssiiddddsssssss',
            $tempid, $send_from, $send_to, $date, $pid, $qty,
            $rate, $disc, $sub_total, $gst, $gst_type, $taxable_value, $gst_amount, $total, $hsn,
            $username, $usertype
        );
        $stmtProdIns->execute();

        // Deduct from source godown (sent_qty ↑, closing_qty ↓) — FOR UPDATE + ledger
        $outResult = $stockService->transferOut(
            $pid, $Login_user_TYPEvl, $send_from, $qty,
            'transfer', $tempid, $createdBy,
            true, // outer transaction owns commit
            $warehouseFromId
        );

        // Credit to destination godown (input_qty ↑, closing_qty ↑) — FOR UPDATE + ledger.
        // The new lot at the destination carries the SOURCE lot's real cost
        // forward (consumed_rate), not this transfer's manually-entered
        // $rate, which may be a different inter-godown billing rate.
        $stockService->transferIn(
            $pid, $Login_user_TYPEvl, $send_to, $qty,
            'transfer', $tempid, $createdBy,
            true,
            $outResult['consumed_rate'] ?? null,
            $warehouseToId
        );
    }

    $stmtInvChk->close();
    $stmtInvIns->close();
    $stmtProdChk->close();
    $stmtProdIns->close();
    $stmtProd->close();

    $db_conn->commit();

} catch (StockException $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = "Stock error: " . $e->getMessage();
    echo "<script>window.location='internal_transfer?InvalidStock&&AlertStockError';</script>";
    exit;
} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("internal_transfer_action error: " . $e->getMessage());
    $_SESSION['errorMessage'] = "An error occurred. Please try again.";
    echo "<script>window.location='internal_transfer?saveerror';</script>";
    exit;
}

echo "<script>window.location='internal_transfer_print?tempid=$tempid';</script>";
