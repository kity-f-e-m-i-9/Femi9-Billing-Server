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

$from_usertype = (string) ($_REQUEST['from_usertype'] ?? '');
$from_userid   = (string) ($_REQUEST['from_userid']   ?? '');
$to_usertype   = (string) ($_REQUEST['to_usertype']   ?? '');
$to_userid     = (string) ($_REQUEST['to_userid']     ?? '');
$returnid      = (string) ($_REQUEST['returnid']      ?? '');
$invnumber     = (string) ($_REQUEST['invnumber']     ?? '');
$invid         = (string) ($_REQUEST['invid']         ?? '');
$prid          = (int)    ($_REQUEST['prid']          ?? 0);
$returnqty     = (int)    ($_REQUEST['returnqty']     ?? 0);
$createdBy     = $_SESSION['LOGIN_USER'] ?? 'system';

if ($prid <= 0 || $returnqty <= 0) {
    $rtnid_encode  = base64_encode($returnid);
    $inv_encode    = base64_encode($invnumber);
    echo "<script>window.location='stock_return_add2.php?returnid={$rtnid_encode}&&invnumber={$inv_encode}&&invalidqty';</script>";
    exit;
}

// Validate returnqty against invoice qty (prepared — no injection)
$stmtInv = $db_conn->prepare(
    "SELECT qty, amount FROM user_invoice_items WHERE inv_id = ? AND pr_id = ?"
);
$stmtInv->bind_param('si', $invid, $prid);
$stmtInv->execute();
$invItem = $stmtInv->get_result()->fetch_assoc();
$stmtInv->close();

$invoiceqty = $invItem ? (int)$invItem['qty']     : 0;
$pr_mrp     = $invItem ? (float)$invItem['amount'] : 0.0;

$rtnid_encode = base64_encode($returnid);
$inv_encode   = base64_encode($invnumber);

if ($returnqty > $invoiceqty) {
    echo "<script>window.location='stock_return_add2.php?returnid={$rtnid_encode}&&invnumber={$inv_encode}&&invalidqty';</script>";
    exit;
}

// Get product GST details (prepared — no injection)
$stmtProd = $db_conn->prepare("SELECT gst, hsn FROM products WHERE id = ?");
$stmtProd->bind_param('i', $prid);
$stmtProd->execute();
$prod = $stmtProd->get_result()->fetch_assoc();
$stmtProd->close();

$gst_percentage   = $prod ? (float)$prod['gst'] : 0.0;
$hsn              = $prod ? (string)$prod['hsn'] : '';
$subtotal         = $pr_mrp * $returnqty;
$gstamount_total  = $subtotal * $gst_percentage / 100;
$total            = $subtotal + $gstamount_total;

$stockService = new StockService($db_conn);

$db_conn->begin_transaction();
try {
    // Ensure return header exists (idempotent)
    $stmtChkHdr = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM user_return_stock WHERE returnid = ?"
    );
    $stmtChkHdr->bind_param('s', $returnid);
    $stmtChkHdr->execute();
    $hdrCount = (int)$stmtChkHdr->get_result()->fetch_assoc()['n'];
    $stmtChkHdr->close();

    if ($hdrCount === 0) {
        $return_date = date("Y-m-d");
        $stmtInsHdr = $db_conn->prepare(
            "INSERT INTO user_return_stock
                 (returnid, invnumber, date, subtotal, discount, total,
                  from_usertype, from_userid, to_usertype, to_userid, status)
             VALUES (?, ?, ?, 0, 0, 0, ?, ?, ?, ?, 'pending')"
        );
        $stmtInsHdr->bind_param(
            'sssssss',
            $returnid, $invnumber, $return_date,
            $from_usertype, $from_userid, $to_usertype, $to_userid
        );
        $stmtInsHdr->execute();
        $stmtInsHdr->close();
    }

    // Skip if this product already in return (idempotent)
    $stmtChkItem = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM user_return_stock_items WHERE returnid = ? AND prid = ?"
    );
    $stmtChkItem->bind_param('si', $returnid, $prid);
    $stmtChkItem->execute();
    $itemCount = (int)$stmtChkItem->get_result()->fetch_assoc()['n'];
    $stmtChkItem->close();

    if ($itemCount > 0) {
        $db_conn->rollback();
        echo "<script>window.location='stock_return_add2.php?returnid={$rtnid_encode}&&invnumber={$inv_encode}&&productalreadyexists';</script>";
        exit;
    }

    $return_date = date("Y-m-d");
    $stmtInsItem = $db_conn->prepare(
        "INSERT INTO user_return_stock_items
             (returnid, invnumber, prid, amount, qty, subtotal, gst_percentage,
              gstamount_total, total, from_usertype, from_userid, to_usertype,
              to_userid, date, status, hsn, damaged_qty)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 0)"
    );
    $stmtInsItem->bind_param(
        'ssidddddsssssss',
        $returnid, $invnumber, $prid, $pr_mrp, $returnqty,
        $subtotal, $gst_percentage, $gstamount_total, $total,
        $from_usertype, $from_userid, $to_usertype, $to_userid,
        $return_date, $hsn
    );
    $stmtInsItem->execute();
    $stmtInsItem->close();

    // Deduct from sender: returnqty ↑, closing_qty ↓ — FOR UPDATE + ledger
    $stmtLock = $db_conn->prepare(
        "SELECT closing_qty, returnqty FROM stock
          WHERE product_id = ? AND user_type = ? AND user_id = ?
          FOR UPDATE"
    );
    $stmtLock->bind_param('iss', $prid, $from_usertype, $from_userid);
    $stmtLock->execute();
    $stockRow = $stmtLock->get_result()->fetch_assoc();
    $stmtLock->close();

    if ($stockRow) {
        $before       = (int)$stockRow['closing_qty'];
        $after        = max(0, $before - $returnqty);
        $newReturnQty = (int)$stockRow['returnqty'] + $returnqty;

        $stmtUpd = $db_conn->prepare(
            "UPDATE stock SET returnqty = ?, closing_qty = ?, updated_at = NOW()
              WHERE product_id = ? AND user_type = ? AND user_id = ?"
        );
        $stmtUpd->bind_param('iiiss', $newReturnQty, $after, $prid, $from_usertype, $from_userid);
        $stmtUpd->execute();
        $stmtUpd->close();

        // Ledger: ss_return_create
        $stmtLed = $db_conn->prepare(
            "INSERT INTO stock_ledger
                 (product_id, user_type, user_id, action, qty,
                  qty_before, qty_after, ref_type, ref_id, note, created_by)
             VALUES (?, ?, ?, 'return_create', ?, ?, ?, 'ss_return', ?, 'return created', ?)"
        );
        $stmtLed->bind_param(
            'issiiiss',
            $prid, $from_usertype, $from_userid,
            $returnqty, $before, $after,
            $returnid, $createdBy
        );
        $stmtLed->execute();
        $stmtLed->close();
    }

    $db_conn->commit();

    echo "<script>window.location='stock_return_add2.php?returnid={$rtnid_encode}&&invnumber={$inv_encode}&&addedsuccess';</script>";

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("stock_return_action.php error: " . $e->getMessage());
    echo "<script>window.location='stock_return_add2.php?returnid={$rtnid_encode}&&invnumber={$inv_encode}&&saveerror';</script>";
}
?>
