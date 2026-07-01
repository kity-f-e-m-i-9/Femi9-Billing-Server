<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
error_reporting(0);
date_default_timezone_set("Asia/Kolkata");

if (!isset($_REQUEST['add-return'])) {
    echo "<script>window.location='stock-return-add.php';</script>";
    exit;
}

$from_usertype = htmlspecialchars(strip_tags(trim($_REQUEST['from_usertype'] ?? '')), ENT_QUOTES, 'UTF-8');
$from_userid   = htmlspecialchars(strip_tags(trim($_REQUEST['from_userid']   ?? '')), ENT_QUOTES, 'UTF-8');
$to_usertype   = htmlspecialchars(strip_tags(trim($_REQUEST['to_usertype']   ?? '')), ENT_QUOTES, 'UTF-8');
$to_userid     = htmlspecialchars(strip_tags(trim($_REQUEST['to_userid']     ?? '')), ENT_QUOTES, 'UTF-8');
$returnid      = preg_replace('/[^A-Z0-9\/\-]/', '', strtoupper($_REQUEST['returnid']  ?? ''));
$invnumber     = preg_replace('/[^A-Z0-9\/\-]/', '', strtoupper($_REQUEST['invnumber'] ?? ''));
$invid         = (int) ($_REQUEST['invid'] ?? 0);
$prid          = (int) ($_REQUEST['prid']  ?? 0);
$returnqty     = (int) ($_REQUEST['returnqty'] ?? 0);

if ($prid <= 0 || $returnqty <= 0 || empty($from_userid)) {
    echo "<script>window.location='stock-return-add.php?invalid';</script>";
    exit;
}

// Validate qty against invoice
$stmtInv = $db_conn->prepare(
    "SELECT qty, amount FROM user_invoice_items WHERE inv_id = ? AND pr_id = ?"
);
$stmtInv->bind_param('ii', $invid, $prid);
$stmtInv->execute();
$invRow = $stmtInv->get_result()->fetch_assoc();
$stmtInv->close();

if (!$invRow || $returnqty > (int)$invRow['qty']) {
    $rtnid_encode  = base64_encode($returnid);
    $invnum_encode = base64_encode($invnumber);
    echo "<script>window.location='stock_return_add2.php?returnid={$rtnid_encode}&&invnumber={$invnum_encode}&&invalidqty';</script>";
    exit;
}

$pr_mrp    = (float) $invRow['amount'];
$subtotal  = $pr_mrp * $returnqty;

// Fetch product GST/HSN
$stmtProd = $db_conn->prepare("SELECT gst, hsn FROM products WHERE id = ?");
$stmtProd->bind_param('i', $prid);
$stmtProd->execute();
$prodRow = $stmtProd->get_result()->fetch_assoc();
$stmtProd->close();

$gst_percentage    = (float) ($prodRow['gst']  ?? 0);
$hsn               = (string)($prodRow['hsn']  ?? '');
$gstamount_total   = $subtotal * $gst_percentage / 100;
$total             = $subtotal + $gstamount_total;
$return_date       = date("Y-m-d");

$stockService = new StockService($db_conn);
$createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

$db_conn->begin_transaction();
try {
    // Upsert return header
    $stmtHdrChk = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM user_return_stock WHERE returnid = ?"
    );
    $stmtHdrChk->bind_param('s', $returnid);
    $stmtHdrChk->execute();
    $hdrExists = (int)$stmtHdrChk->get_result()->fetch_assoc()['n'];
    $stmtHdrChk->close();

    if ($hdrExists === 0) {
        $stmtHdrIns = $db_conn->prepare(
            "INSERT INTO user_return_stock
                 (returnid, invnumber, date, subtotal, discount, total,
                  from_usertype, from_userid, to_usertype, to_userid, status)
             VALUES (?, ?, ?, 0, 0, 0, ?, ?, ?, ?, 'pending')"
        );
        $stmtHdrIns->bind_param(
            'sssssss',
            $returnid, $invnumber, $return_date,
            $from_usertype, $from_userid, $to_usertype, $to_userid
        );
        $stmtHdrIns->execute();
        $stmtHdrIns->close();
    }

    // Check duplicate item
    $stmtItmChk = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM user_return_stock_items WHERE returnid = ? AND prid = ?"
    );
    $stmtItmChk->bind_param('si', $returnid, $prid);
    $stmtItmChk->execute();
    $itmExists = (int)$stmtItmChk->get_result()->fetch_assoc()['n'];
    $stmtItmChk->close();

    if ($itmExists > 0) {
        $db_conn->rollback();
        $rtnid_encode  = base64_encode($returnid);
        $invnum_encode = base64_encode($invnumber);
        echo "<script>window.location='stock_return_add2.php?returnid={$rtnid_encode}&&invnumber={$invnum_encode}&&productalreadyexists';</script>";
        exit;
    }

    // Lock sender stock row FOR UPDATE
    $stmtLock = $db_conn->prepare(
        "SELECT closing_qty, returnqty FROM stock
          WHERE product_id = ? AND user_type = ? AND user_id = ? FOR UPDATE"
    );
    $stmtLock->bind_param('iss', $prid, $from_usertype, $from_userid);
    $stmtLock->execute();
    $stockRow = $stmtLock->get_result()->fetch_assoc();
    $stmtLock->close();

    $new_returnqty  = (int)($stockRow['returnqty']  ?? 0) + $returnqty;
    $new_closing    = max(0, (int)($stockRow['closing_qty'] ?? 0) - $returnqty);

    $stmtStk = $db_conn->prepare(
        "UPDATE stock SET returnqty = ?, closing_qty = ?
          WHERE product_id = ? AND user_type = ? AND user_id = ?"
    );
    $stmtStk->bind_param('iiiss', $new_returnqty, $new_closing, $prid, $from_usertype, $from_userid);
    $stmtStk->execute();
    $stmtStk->close();

    // Insert return item
    $stmtItmIns = $db_conn->prepare(
        "INSERT INTO user_return_stock_items
             (returnid, invnumber, prid, amount, qty, subtotal,
              gst_percentage, gstamount_total, total,
              from_usertype, from_userid, to_usertype, to_userid,
              date, status, hsn, damaged_qty)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 0)"
    );
    $stmtItmIns->bind_param(
        'ssididddsssssss',
        $returnid, $invnumber, $prid, $pr_mrp, $returnqty,
        $subtotal, $gst_percentage, $gstamount_total, $total,
        $from_usertype, $from_userid, $to_usertype, $to_userid,
        $return_date, $hsn
    );
    $stmtItmIns->execute();
    $stmtItmIns->close();

    // Ledger entry
    $stmtLed = $db_conn->prepare(
        "INSERT INTO stock_ledger
             (product_id, user_type, user_id, action, qty,
              qty_before, qty_after, ref_type, ref_id, note, created_by)
         VALUES (?, ?, ?, 'return_create', ?, ?,
                 ?, 'd_return', ?, 'return created', ?)"
    );
    $qty_before = (int)($stockRow['closing_qty'] ?? 0);
    $stmtLed->bind_param(
        'issiiisss',
        $prid, $from_usertype, $from_userid,
        $returnqty, $qty_before, $new_closing,
        $returnid, $createdBy
    );
    $stmtLed->execute();
    $stmtLed->close();

    $db_conn->commit();

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("stock_return_action.php error: " . $e->getMessage());
    $rtnid_encode  = base64_encode($returnid);
    $invnum_encode = base64_encode($invnumber);
    echo "<script>window.location='stock_return_add2.php?returnid={$rtnid_encode}&&invnumber={$invnum_encode}&&saveerror';</script>";
    exit;
}

$rtnid_encode  = base64_encode($returnid);
$invnum_encode = base64_encode($invnumber);
echo "<script>window.location='stock_return_add2.php?returnid={$rtnid_encode}&&invnumber={$invnum_encode}&&addedsuccess';</script>";
?>
