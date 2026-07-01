<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");
include("RemoveSpecialChar.php");

error_reporting(0);

// ── INSERT ────────────────────────────────────────────────────────────────────
if (isset($_REQUEST['add-record'])) {

    $tempid   = preg_replace('/[^A-Z0-9\/]/', '', strtoupper($_REQUEST['tempid'] ?? ''));
    $usertype = htmlspecialchars(strip_tags(trim($_REQUEST['usertype'] ?? '')), ENT_QUOTES, 'UTF-8');
    $userid   = htmlspecialchars(strip_tags(trim($_REQUEST['userid']   ?? '')), ENT_QUOTES, 'UTF-8');
    $date     = date("Y-m-d", strtotime($_REQUEST['date'] ?? 'now'));
    $remarks  = RemoveSpecialChar($_REQUEST['remarks']  ?? '');
    $category = htmlspecialchars(strip_tags(trim($_REQUEST['category'] ?? '')), ENT_QUOTES, 'UTF-8');

    $product_ids = $_REQUEST['product_id'] ?? [];
    $qty_arr     = $_REQUEST['qty']        ?? [];

    if (!is_array($product_ids) || count($product_ids) === 0) {
        $_SESSION['errorMessage'] = "No products submitted.";
        echo "<script>window.location='demofree_new?invalid';</script>";
        exit;
    }

    // Build validated row list
    $rows = [];
    foreach ($product_ids as $i => $rawPid) {
        $pid = (int) $rawPid;
        $qty = (int) RemoveSpecialChar($qty_arr[$i] ?? '0');
        if ($pid <= 0 || $qty <= 0) continue;
        $rows[] = ['pid' => $pid, 'qty' => $qty];
    }

    if (empty($rows)) {
        $_SESSION['errorMessage'] = "No valid products.";
        echo "<script>window.location='demofree_new?invalid';</script>";
        exit;
    }

    // Pre-validate stock availability (outside transaction, no lock)
    $stockService = new StockService($db_conn);
    $createdBy    = $_SESSION['LOGIN_USER'] ?? 'system';

    foreach ($rows as $row) {
        $available = $stockService->getClosingQty($row['pid'], $usertype, $userid);
        if ($available === null || $available < $row['qty']) {
            $_SESSION['errorMessage'] =
                "Insufficient stock for product #{$row['pid']}. " .
                "Available: " . ($available ?? 0) . ", Requested: {$row['qty']}";
            echo "<script>window.location='demofree_new?InvalidStock&&AlertStockError';</script>";
            exit;
        }
    }

    // Begin atomic transaction
    $db_conn->begin_transaction();

    try {
        $stmtChk = $db_conn->prepare(
            "SELECT COUNT(*) AS n FROM demofreedamage WHERE tempid = ? AND product_id = ?"
        );
        $stmtIns = $db_conn->prepare(
            "INSERT INTO demofreedamage
                 (tempid, date, remarks, product_id, qty, category, usertype, userid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($rows as $row) {
            $pid = $row['pid'];
            $qty = $row['qty'];

            // Skip duplicate product under this tempid
            $stmtChk->bind_param('si', $tempid, $pid);
            $stmtChk->execute();
            if ((int) $stmtChk->get_result()->fetch_assoc()['n'] > 0) continue;

            // Insert demo/free/damage record
            $stmtIns->bind_param('sssissss', $tempid, $date, $remarks, $pid, $qty, $category, $usertype, $userid);
            $stmtIns->execute();

            // Deduct stock: sent_qty ↑, closing_qty ↓ — FOR UPDATE + ledger entry
            $stockService->transferOut(
                $pid, $usertype, $userid, $qty,
                'demofree', $tempid, $createdBy,
                true // externalTransaction
            );
        }

        $stmtChk->close();
        $stmtIns->close();

        $db_conn->commit();

    } catch (StockException $e) {
        $db_conn->rollback();
        $_SESSION['errorMessage'] = "Stock error: " . $e->getMessage();
        echo "<script>window.location='demofree_new?InvalidStock&&AlertStockError';</script>";
        exit;
    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log("demofree_action INSERT error: " . $e->getMessage());
        $_SESSION['errorMessage'] = "An error occurred. Please try again.";
        echo "<script>window.location='demofree_new?saveerror';</script>";
        exit;
    }

    $_SESSION['sucMessage'] = "Demo/Free/Damage Details Added Successfully!";
    echo "<script>window.location='demofree_manage?addesuccess';</script>";
    exit;
}

// ── UPDATE (header details only — no stock impact) ────────────────────────────
if (isset($_REQUEST['update-record'])) {

    $tempid   = preg_replace('/[^A-Z0-9\/]/', '', strtoupper($_REQUEST['update_tempid'] ?? ''));
    $date     = date("Y-m-d", strtotime($_REQUEST['date'] ?? 'now'));
    $remarks  = RemoveSpecialChar($_REQUEST['remarks']  ?? '');
    $category = htmlspecialchars(strip_tags(trim($_REQUEST['category'] ?? '')), ENT_QUOTES, 'UTF-8');

    $stmt = $db_conn->prepare(
        "UPDATE demofreedamage SET category = ?, date = ?, remarks = ? WHERE tempid = ?"
    );
    $stmt->bind_param('ssss', $category, $date, $remarks, $tempid);
    $stmt->execute();
    $stmt->close();

    $_SESSION['sucMessage'] = "Demo/Free/Damage Details Updated Successfully!";
    echo "<script>window.location='demofree_manage?updatedsuccess';</script>";
    exit;
}
