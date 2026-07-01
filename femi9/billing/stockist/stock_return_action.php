<?php
include("checksession.php");
include("config.php");
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

if ($prid <= 0 || $returnqty <= 0 || empty($returnid)) {
    echo "<script>window.location='stock-return-add.php';</script>";
    exit;
}

// Get product GST/HSN (prepared — no injection)
$stmtProd = $db_conn->prepare("SELECT gst, hsn FROM products WHERE id = ?");
$stmtProd->bind_param('i', $prid);
$stmtProd->execute();
$prod = $stmtProd->get_result()->fetch_assoc();
$stmtProd->close();

$gst_percentage = (float) ($prod['gst'] ?? 0);
$hsn            = (string) ($prod['hsn'] ?? '');

// Validate return qty against invoice qty (prepared)
$stmtInv = $db_conn->prepare(
    "SELECT qty, amount FROM user_invoice_items WHERE inv_id = ? AND pr_id = ?"
);
$stmtInv->bind_param('si', $invid, $prid);
$stmtInv->execute();
$invItem = $stmtInv->get_result()->fetch_assoc();
$stmtInv->close();

$invoiceqty = (int)   ($invItem['qty']    ?? 0);
$pr_mrp     = (float) ($invItem['amount'] ?? 0);

if ($returnqty > $invoiceqty) {
    $rtnid_encode    = base64_encode($returnid);
    $invnumber_encode = base64_encode($invnumber);
    echo "<script>window.location='stock_return_add2.php?returnid={$rtnid_encode}&&invnumber={$invnumber_encode}&&invalidqty';</script>";
    exit;
}

$subtotal       = $pr_mrp * $returnqty;
$gstamount_total = $subtotal * $gst_percentage / 100;
$total          = $subtotal + $gstamount_total;
$return_date    = date("Y-m-d");
$createdBy      = $_SESSION['LOGIN_USER'] ?? 'system';

$db_conn->begin_transaction();
try {
    // Insert header if not exists
    $stmtChkHdr = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM user_return_stock WHERE returnid = ?"
    );
    $stmtChkHdr->bind_param('s', $returnid);
    $stmtChkHdr->execute();
    if ((int)$stmtChkHdr->get_result()->fetch_assoc()['n'] === 0) {
        $stmtInsHdr = $db_conn->prepare(
            "INSERT INTO user_return_stock
                 (returnid, invnumber, date, subtotal, discount, total,
                  from_usertype, from_userid, to_usertype, to_userid, status)
             VALUES (?, ?, ?, 0, 0, 0, ?, ?, ?, ?, 'pending')"
        );
        $stmtInsHdr->bind_param('sssssss',
            $returnid, $invnumber, $return_date,
            $from_usertype, $from_userid, $to_usertype, $to_userid
        );
        $stmtInsHdr->execute();
        $stmtInsHdr->close();
    }
    $stmtChkHdr->close();

    // Insert item if not exists
    $stmtChkItem = $db_conn->prepare(
        "SELECT COUNT(*) AS n FROM user_return_stock_items WHERE returnid = ? AND prid = ?"
    );
    $stmtChkItem->bind_param('si', $returnid, $prid);
    $stmtChkItem->execute();
    $itemExists = (int)$stmtChkItem->get_result()->fetch_assoc()['n'];
    $stmtChkItem->close();

    if ($itemExists > 0) {
        $db_conn->commit();
        $rtnid_encode    = base64_encode($returnid);
        $invnumber_encode = base64_encode($invnumber);
        echo "<script>window.location='stock_return_add2.php?returnid={$rtnid_encode}&&invnumber={$invnumber_encode}&&productalreadyexists';</script>";
        exit;
    }

    $stmtInsItem = $db_conn->prepare(
        "INSERT INTO user_return_stock_items
             (returnid, invnumber, prid, amount, qty, subtotal,
              gst_percentage, gstamount_total, total,
              from_usertype, from_userid, to_usertype, to_userid,
              date, status, hsn, damaged_qty)
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

    // Lock sender's stock row and update returnqty ↑, closing_qty ↓ (floor 0)
    $stmtLock = $db_conn->prepare(
        "SELECT closing_qty, returnqty FROM stock
          WHERE product_id = ? AND user_type = ? AND user_id = ? FOR UPDATE"
    );
    $stmtLock->bind_param('iss', $prid, $from_usertype, $from_userid);
    $stmtLock->execute();
    $stockRow = $stmtLock->get_result()->fetch_assoc();
    $stmtLock->close();

    if ($stockRow) {
        $new_returnqty  = $stockRow['returnqty'] + $returnqty;
        $new_closing    = max(0, $stockRow['closing_qty'] - $returnqty);

        $stmtUpd = $db_conn->prepare(
            "UPDATE stock SET returnqty = ?, closing_qty = ?, updated_at = NOW()
              WHERE product_id = ? AND user_type = ? AND user_id = ?"
        );
        $stmtUpd->bind_param('iiiss', $new_returnqty, $new_closing, $prid, $from_usertype, $from_userid);
        $stmtUpd->execute();
        $stmtUpd->close();

        // Audit trail
        $stmtLed = $db_conn->prepare(
            "INSERT INTO stock_ledger
                 (product_id, user_type, user_id, action, qty,
                  qty_before, qty_after, ref_type, ref_id, note, created_by)
             VALUES (?, ?, ?, 'return_create', ?, ?, ?, 'st_return', ?, 'return created', ?)"
        );
        $stmtLed->bind_param(
            'issiiiss',
            $prid, $from_usertype, $from_userid,
            $returnqty,
            $stockRow['closing_qty'], $new_closing,
            $returnid, $createdBy
        );
        $stmtLed->execute();
        $stmtLed->close();
    }

    $db_conn->commit();

    $rtnid_encode    = base64_encode($returnid);
    $invnumber_encode = base64_encode($invnumber);
    echo "<script>window.location='stock_return_add2.php?returnid={$rtnid_encode}&&invnumber={$invnumber_encode}&&addedsuccess';</script>";

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("stock_return_action.php error: " . $e->getMessage());
    $rtnid_encode    = base64_encode($returnid);
    $invnumber_encode = base64_encode($invnumber);
    echo "<script>window.location='stock_return_add2.php?returnid={$rtnid_encode}&&invnumber={$invnumber_encode}&&saveerror';</script>";
}
?>
