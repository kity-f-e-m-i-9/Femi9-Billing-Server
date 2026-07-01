<?php
include("checksession.php");
include("config.php");
error_reporting(0);

if (($Login_user_TYPEvl ?? '') !== 'company') {
    header("Location: manage-tp-invoices"); exit;
}

$returnid = trim(base64_decode($_REQUEST['returnid'] ?? ''));
if (!$returnid) { header("Location: tp-cnote-manage"); exit; }

// Only delete if still pending
$s = $db_conn->prepare("SELECT status FROM user_return_stock WHERE returnid=? AND from_usertype='territory_partner' AND to_usertype='company' LIMIT 1");
$s->bind_param('s', $returnid);
$s->execute();
$row = $s->get_result()->fetch_assoc(); $s->close();

if (!$row) { header("Location: tp-cnote-manage"); exit; }

if ($row['status'] !== 'pending') {
    // Accepted CN — only allow header deletion if all items have been individually removed
    $s = $db_conn->prepare("SELECT COUNT(*) AS cnt FROM user_return_stock_items WHERE returnid=?");
    $s->bind_param('s', $returnid);
    $s->execute();
    $remaining = (int)$s->get_result()->fetch_assoc()['cnt']; $s->close();

    if ($remaining > 0) {
        $_SESSION['errorMessage'] = "Remove all items from the credit note first before deleting.";
        header("Location: tp-cnote-manage"); exit;
    }
}

$s = $db_conn->prepare("DELETE FROM user_return_stock_items WHERE returnid=?");
$s->bind_param('s', $returnid); $s->execute(); $s->close();

$s = $db_conn->prepare("DELETE FROM user_return_stock WHERE returnid=?");
$s->bind_param('s', $returnid); $s->execute(); $s->close();

$_SESSION['successMessage'] = "Draft Credit Note deleted.";
header("Location: tp-cnote-manage");
exit;
