<?php
include("checksession.php"); require_once("include/PermissionCheck.php"); requirePermission('ot_channels');
include("config.php");
require_once("include/StockService.php");

$rowid  = (int) base64_decode($_REQUEST['id']);
$tempid = $_REQUEST['tempid'] ?? '';

// Fetch the OT sale item before deleting it
$stmt = $db_conn->prepare(
    "SELECT prid, qty, godownid, warehouse_id FROM ot_sales WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($item) {
    $productId    = (int)    $item['prid'];
    $qty          = (int)    $item['qty'];
    $godownid     = (string) $item['godownid'];
    $warehouseId  = $item['warehouse_id'] !== null ? (int) $item['warehouse_id'] : null;
    // Stock is kept under the deducting session's own type (see
    // ot-sale-action.php's otDeduct call and ot-sale-return.php's otReverse
    // call, both of which use $Login_user_TYPEvl) -- ot_sales.usertype is a
    // different, unrelated column (the submitting account's role), so using
    // it here silently missed the stock row and never restored quantity.
    $usertype = $Login_user_TYPEvl;

    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    try {
        // otDeductUndo, not otReverse — this bill is being deleted outright
        // (never existed), not returned by the customer, so it should come
        // back out of Sales Qty entirely rather than show up as a same-size
        // Return next to an unchanged Sales figure. See StockService.php's
        // otDeductUndo() docblock.
        //
        // otDeductUndo() used to return success=false (reason: no_stock_row)
        // instead of throwing on a missing stock row, and this ignored that
        // return value entirely — the OT sale got deleted either way,
        // silently leaving the qty never credited back (confirmed
        // 2026-10-27, two invoices' worth of stock went missing this way).
        // It now throws StockException like otDeduct()/otReverseUndo()
        // already do, so a missing row refuses the deletion here instead of
        // deleting it with stock left wrong.
        $stockService->otDeductUndo(
            $productId, $usertype, $godownid,
            $qty, (string)$rowid, $createdBy,
            false, // externalTransaction
            $warehouseId
        );
    } catch (StockException $e) {
        error_log("ot-sale-delete otDeductUndo failed: " . $e->getMessage());
        $_SESSION['errorMessage'] = "Could not delete this item — no matching stock record was found to credit the quantity back to, so deleting it would have left stock wrong. Please contact support before deleting this item.";
        echo "<script>window.location='ot-sale-details?tempid=" . urlencode($tempid) . "';</script>";
        exit;
    } catch (\Throwable $e) {
        error_log("ot-sale-delete otDeductUndo error: " . $e->getMessage());
        $_SESSION['errorMessage'] = "Could not delete this item — an error occurred while reversing its stock. Please contact support.";
        echo "<script>window.location='ot-sale-details?tempid=" . urlencode($tempid) . "';</script>";
        exit;
    }
}

$stmt = $db_conn->prepare("DELETE FROM ot_sales WHERE id = ?");
$stmt->bind_param('i', $rowid);
$stmt->execute();
$stmt->close();

$_SESSION['sucMessage'] = "One OT Sales record deleted successfully.";
echo "<script>window.location='ot-sale-details?deletedDone&&tempid=".urlencode($tempid)."';</script>";
?>
