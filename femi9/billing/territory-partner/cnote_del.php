<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$returnid = base64_decode($_REQUEST['returnid'] ?? '');
$returnid = mysqli_real_escape_string($db_conn, $returnid);

if (empty($returnid)) {
    header("Location: manage-return.php"); exit;
}

// Safety: only pending (draft) CNs may be bulk-deleted — accepted CNs must be unwound item-by-item
$stmt = $db_conn->prepare("SELECT status FROM user_return_stock WHERE returnid=? LIMIT 1");
$stmt->bind_param('s', $returnid);
$stmt->execute();
$retRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$retRow) { header("Location: manage-return.php"); exit; }

if ($retRow['status'] !== 'pending') {
    // Accepted CN — only allow header deletion if all items have already been individually removed
    $stmtChk = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM user_return_stock_items WHERE returnid=?");
    $stmtChk->bind_param('s', $returnid);
    $stmtChk->execute();
    $remaining = (int)$stmtChk->get_result()->fetch_assoc()['cnt'];
    $stmtChk->close();

    if ($remaining > 0) {
        $_SESSION['errorMessage'] = "This credit note has already been finalised. Remove items individually from the CN details page.";
        echo "<script>window.location='manage-return.php?DeleteFailed';</script>"; exit;
    }
    // All items removed — safe to delete the now-empty header only
    $stmt = $db_conn->prepare("DELETE FROM user_return_stock WHERE returnid=?");
    $stmt->bind_param('s', $returnid);
    $stmt->execute();
    $stmt->close();
    $_SESSION['successMessage'] = "Credit Note deleted successfully.";
    echo "<script>window.location='manage-return.php?DeleteSuccess';</script>"; exit;
}

$stmt = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE returnid=?");
$stmt->bind_param('s', $returnid);
$stmt->execute();
$stmt->close();

$stmt = $db_conn->prepare("DELETE FROM user_return_stock WHERE returnid=?");
$stmt->bind_param('s', $returnid);
$stmt->execute();
$stmt->close();

$_SESSION['successMessage'] = "Incomplete Return (Credit Note) Deleted Successfully!";
echo "<script>window.location='manage-return.php?DeleteSuccess';</script>";
exit;
?>
