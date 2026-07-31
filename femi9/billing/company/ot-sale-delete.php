<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

$rowid  = (int) base64_decode($_REQUEST['id']);
$tempid = $_REQUEST['tempid'] ?? '';

// Fetch the OT sale item before deleting it
$stmt = $db_conn->prepare(
    "SELECT prid, qty, godownid FROM ot_sales WHERE id = ?"
);
$stmt->bind_param('i', $rowid);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($item) {
    $productId = (int)    $item['prid'];
    $qty       = (int)    $item['qty'];
    $godownid  = (string) $item['godownid'];
    // Stock is kept under the deducting session's own type (see
    // ot-sale-action.php's otDeduct call and ot-sale-return.php's otReverse
    // call, both of which use $Login_user_TYPEvl) -- ot_sales.usertype is a
    // different, unrelated column (the submitting account's role), so using
    // it here silently missed the stock row and never restored quantity.
    $usertype = $Login_user_TYPEvl;

    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    try {
        $stockService->otReverse(
            $productId, $usertype, $godownid,
            $qty, (string)$rowid, $createdBy
        );
    } catch (\Throwable $e) {
        error_log("ot-sale-delete otReverse error: " . $e->getMessage());
    }
}

$stmt = $db_conn->prepare("DELETE FROM ot_sales WHERE id = ?");
$stmt->bind_param('i', $rowid);
$stmt->execute();
$stmt->close();

$_SESSION['sucMessage'] = "One OT Sales record deleted successfully.";
echo "<script>window.location='ot-sale-details?deletedDone&&tempid=$tempid';</script>";
?>
