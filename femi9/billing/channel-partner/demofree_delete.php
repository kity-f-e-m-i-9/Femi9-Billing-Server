<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$rowid  = (int)base64_decode($_REQUEST['Roowid'] ?? '');
$tempid = $_REQUEST['tempid'] ?? '';

if ($rowid <= 0) {
    header("Location: demofree_details.php?tempid=" . urlencode($tempid));
    exit;
}

// Fetch the record before deletion
$stmt = $db_conn->prepare("SELECT product_id, qty, userid FROM demofreedamage WHERE id=? LIMIT 1");
$stmt->bind_param('i', $rowid);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($record) {
    $pid        = (int)$record['product_id'];
    $qty        = (int)$record['qty'];
    $tp_id      = (int)$record['userid'];
    $created_by = $_SESSION['LOGIN_USER'] ?? 'system';

    $db_conn->begin_transaction();
    try {
        $stmtDel = $db_conn->prepare("DELETE FROM demofreedamage WHERE id=?");
        $stmtDel->bind_param('i', $rowid);
        $stmtDel->execute();
        $stmtDel->close();

        if ($pid > 0 && $qty > 0 && $tp_id > 0) {
            // Reverse: return stock to territory_partner_stock
            $stmtBefore = $db_conn->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
            $stmtBefore->bind_param('ii', $tp_id, $pid);
            $stmtBefore->execute();
            $rowBefore = $stmtBefore->get_result()->fetch_assoc();
            $stmtBefore->close();

            $qty_before = $rowBefore ? (int)$rowBefore['closing_qty'] : 0;
            $qty_after  = $qty_before + $qty;

            $stmtUp = $db_conn->prepare("UPDATE territory_partner_stock SET closing_qty=? WHERE territory_partner_id=? AND product_id=?");
            $stmtUp->bind_param('iii', $qty_after, $tp_id, $pid);
            $stmtUp->execute();
            $stmtUp->close();

            $stmtLedger = $db_conn->prepare(
                "INSERT INTO territory_partner_stock_ledger
                 (territory_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
                 VALUES (?, ?, 'credit', ?, ?, ?, 'demofree', ?, 'demofree_reversal', ?)"
            );
            $stmtLedger->bind_param('iiiiiss', $tp_id, $pid, $qty, $qty_before, $qty_after, $tempid, $created_by);
            $stmtLedger->execute();
            $stmtLedger->close();
        }

        $db_conn->commit();

    } catch (\Throwable $e) {
        $db_conn->rollback();
    }
}

$_SESSION['sucMessage'] = "One Product Entry Deleted! (Demo/Free/Damage)";
header("Location: demofree_details.php?tempid=" . urlencode($tempid));
exit;
?>
