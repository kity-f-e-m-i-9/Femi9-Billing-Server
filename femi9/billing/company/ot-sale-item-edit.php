<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
error_reporting(0);

// Direct qty/rate/discount edit on an existing OT Sales line item (Manage
// Sale -> Product Details -> Edit). Unlike ot-sale-delete.php (which reverses
// the whole line), this adjusts stock by only the delta between old and new
// qty, so a partial correction doesn't touch quantity that's still valid.

$rowid   = (int) base64_decode($_POST['rowid'] ?? '');
$tempid  = $_POST['tempid'] ?? '';
$newQty  = (int) ($_POST['qty'] ?? 0);
$newRate = (float) ($_POST['price'] ?? 0);
$newDisc = (float) ($_POST['discount'] ?? 0);

if ($rowid <= 0 || $tempid === '' || $newQty <= 0) {
    header("Location: ot-sale-details.php?tempid=" . urlencode($tempid));
    exit;
}

$stmt = $db_conn->prepare("SELECT prid, qty, godownid FROM ot_sales WHERE id = ? AND tempid = ?");
$stmt->bind_param('is', $rowid, $tempid);
$stmt->execute();
$oldRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$oldRow) {
    header("Location: ot-sale-details.php?tempid=" . urlencode($tempid));
    exit;
}

$productId = (int) $oldRow['prid'];
$oldQty    = (int) $oldRow['qty'];
$godownid  = (string) $oldRow['godownid'];
$delta     = $newQty - $oldQty;

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

$db_conn->begin_transaction();
try {
    if ($delta > 0) {
        // Qty increased -- deduct the extra amount, same check as a fresh sale.
        $available = $stockService->getClosingQty($productId, $Login_user_TYPEvl, $godownid);
        if ($available === null || $available < $delta) {
            throw new StockException("Insufficient stock. Available: " . ($available ?? 0) . ", Extra needed: $delta");
        }
        $stockService->otDeduct($productId, $Login_user_TYPEvl, $godownid, $delta, $tempid, $createdBy, true);
    } elseif ($delta < 0) {
        // Qty decreased -- give back only the difference.
        $stockService->otReverse($productId, $Login_user_TYPEvl, $godownid, -$delta, $tempid, $createdBy, true);
    }

    $stmtProd = $db_conn->prepare("SELECT gst FROM products WHERE id = ?");
    $stmtProd->bind_param('i', $productId);
    $stmtProd->execute();
    $prod = $stmtProd->get_result()->fetch_assoc();
    $stmtProd->close();
    $gst = (float) ($prod['gst'] ?? 0);

    $subTotal  = ($newRate * $newQty) - $newDisc;
    $gstAmount = round($subTotal * $gst / 100, 2);
    $total     = $subTotal + $gstAmount;

    $stmtUpd = $db_conn->prepare(
        "UPDATE ot_sales SET qty=?, price=?, discount=?, sub_total=?, gst_amount=?, total=? WHERE id=?"
    );
    $stmtUpd->bind_param('idddddi', $newQty, $newRate, $newDisc, $subTotal, $gstAmount, $total, $rowid);
    $stmtUpd->execute();
    $stmtUpd->close();

    // Re-roll the invoice header total the same way ot-sale-action.php does
    // after every line change, so Courier/Wallet/Round-off stay consistent.
    $resHdr = $db_conn->prepare("SELECT courier_charges, wallet_amount FROM ot_sales_invoice WHERE tempid = ?");
    $resHdr->bind_param('s', $tempid);
    $resHdr->execute();
    $hdr = $resHdr->get_result()->fetch_assoc();
    $resHdr->close();

    $stmtSum = $db_conn->prepare("SELECT SUM(total) AS s FROM ot_sales WHERE tempid = ?");
    $stmtSum->bind_param('s', $tempid);
    $stmtSum->execute();
    $sumRow = $stmtSum->get_result()->fetch_assoc();
    $stmtSum->close();

    if ($hdr && $sumRow && $sumRow['s'] !== null) {
        $unroundvalue = (float) $sumRow['s'];
        $courier      = (float) $hdr['courier_charges'];
        $wallet       = (float) $hdr['wallet_amount'];
        $withCourier  = $unroundvalue + $courier;
        $roundvalue   = round($withCourier);
        $roundoff     = $roundvalue - $withCourier;
        $netTotal     = max(0, $roundvalue - $wallet);

        $stmtHdrUpd = $db_conn->prepare(
            "UPDATE ot_sales_invoice SET subtotal=?, round_off=?, total=? WHERE tempid=?"
        );
        $stmtHdrUpd->bind_param('ddds', $unroundvalue, $roundoff, $netTotal, $tempid);
        $stmtHdrUpd->execute();
        $stmtHdrUpd->close();
    }

    $db_conn->commit();
    $_SESSION['sucMessage'] = "Product line updated successfully.";
} catch (\Throwable $e) {
    $db_conn->rollback();
    $_SESSION['errorMessage'] = "Update failed: " . $e->getMessage();
}

header("Location: ot-sale-details.php?tempid=" . urlencode($tempid));
exit;
