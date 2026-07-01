<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$invtype = $_REQUEST['invtype'] ?? '';
$invuser = $_REQUEST['invuser'] ?? '';
$invid   = base64_decode($_REQUEST['invid'] ?? '');
$tp_id   = (int)$Login_user_IDvl;

// Sanity: invid must be non-empty and match a real invoice owned by this TP
if (empty($invid)) {
    header("Location: dashboard.php"); exit;
}

/**
 * Reverse all tp_invoice ledger entries for $invid and restore closing_qty.
 * Must be called inside an open transaction.
 */
function reverseStockLedger($db_conn, $tp_id, $invid) {
    $stmt = $db_conn->prepare(
        "SELECT product_id, qty FROM territory_partner_stock_ledger
         WHERE territory_partner_id=? AND ref_type='tp_invoice' AND ref_id=? FOR UPDATE"
    );
    $stmt->bind_param('is', $tp_id, $invid);
    $stmt->execute();
    $entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($entries as $le) {
        $upd = $db_conn->prepare(
            "UPDATE territory_partner_stock SET closing_qty=closing_qty+?
             WHERE territory_partner_id=? AND product_id=?"
        );
        $upd->bind_param('iii', $le['qty'], $tp_id, $le['product_id']);
        $upd->execute();
        $upd->close();
    }

    if ($entries) {
        $del = $db_conn->prepare(
            "DELETE FROM territory_partner_stock_ledger
             WHERE territory_partner_id=? AND ref_type='tp_invoice' AND ref_id=?"
        );
        $del->bind_param('is', $tp_id, $invid);
        $del->execute();
        $del->close();
    }
}

if ($invtype === 'shop') {
    // Only allow deletion if invoice has no items
    $s = $db_conn->prepare("SELECT COUNT(*) AS n FROM user_invoice_items WHERE inv_id=?");
    $s->bind_param('s', $invid);
    $s->execute();
    $cnt = (int)$s->get_result()->fetch_assoc()['n'];
    $s->close();

    if ($cnt === 0) {
        $db_conn->begin_transaction();
        try {
            reverseStockLedger($db_conn, $tp_id, $invid);

            $s = $db_conn->prepare("DELETE FROM user_invoice WHERE inv_id=? AND from_user_type=? AND from_user_id=?");
            $s->bind_param('ssi', $invid, $Login_user_TYPEvl, $tp_id);
            $s->execute(); $s->close();

            $s = $db_conn->prepare("DELETE FROM receipt WHERE inv_id=?");
            $s->bind_param('s', $invid);
            $s->execute(); $s->close();

            $db_conn->commit();
        } catch (\Throwable $e) {
            $db_conn->rollback();
            error_log('[TP delinvoice shop] ' . $e->getMessage());
            $_SESSION['errorMessage'] = "Failed to delete invoice. Please try again.";
            echo "<script>window.location='shop-manage-invoice.php?deletefailed';</script>"; exit;
        }

        $_SESSION['successMessage'] = "Invoice Deleted!";
        echo "<script>window.location='shop-manage-invoice.php?deletedDone';</script>";
    } else {
        $_SESSION['errorMessage'] = "Cannot delete — invoice has items!";
        echo "<script>window.location='shop-manage-invoice.php';</script>";
    }

} elseif ($invtype === 'customer') {
    $s = $db_conn->prepare("SELECT COUNT(*) AS n FROM invoice_items WHERE inv_id=?");
    $s->bind_param('s', $invid);
    $s->execute();
    $cnt = (int)$s->get_result()->fetch_assoc()['n'];
    $s->close();

    if ($cnt === 0) {
        $db_conn->begin_transaction();
        try {
            reverseStockLedger($db_conn, $tp_id, $invid);

            $s = $db_conn->prepare("DELETE FROM invoice WHERE inv_id=? AND user_type=? AND user_id=?");
            $s->bind_param('ssi', $invid, $Login_user_TYPEvl, $tp_id);
            $s->execute(); $s->close();

            $s = $db_conn->prepare("DELETE FROM receipt WHERE inv_id=?");
            $s->bind_param('s', $invid);
            $s->execute(); $s->close();

            $db_conn->commit();
        } catch (\Throwable $e) {
            $db_conn->rollback();
            error_log('[TP delinvoice customer] ' . $e->getMessage());
            $_SESSION['errorMessage'] = "Failed to delete invoice. Please try again.";
            echo "<script>window.location='customer-manage-invoice.php?deletefailed';</script>"; exit;
        }

        $_SESSION['successMessage'] = "Invoice Deleted!";
        echo "<script>window.location='customer-manage-invoice.php?deletedDone';</script>";
    } else {
        $_SESSION['errorMessage'] = "Cannot delete — invoice has items!";
        echo "<script>window.location='customer-manage-invoice.php';</script>";
    }

} else {
    header("Location: dashboard.php"); exit;
}
