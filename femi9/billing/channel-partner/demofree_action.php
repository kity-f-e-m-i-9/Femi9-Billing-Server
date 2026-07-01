<?php
include("checksession.php");
include("config.php");
error_reporting(0);

$tp_id = (int)$Login_user_IDvl;

// Inline stock helpers (no StockService dependency)
function tp_get_closing_qty($db, $tp_id, $pid) {
    $s = $db->prepare("SELECT closing_qty FROM territory_partner_stock WHERE territory_partner_id=? AND product_id=?");
    $s->bind_param('ii', $tp_id, $pid);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    return $row ? (int)$row['closing_qty'] : 0;
}

function tp_deduct_stock($db, $tp_id, $pid, $qty, $ref_type, $ref_id, $created_by) {
    $before = tp_get_closing_qty($db, $tp_id, $pid);
    $after  = max(0, $before - $qty);

    $u = $db->prepare("UPDATE territory_partner_stock SET closing_qty=? WHERE territory_partner_id=? AND product_id=?");
    $u->bind_param('iii', $after, $tp_id, $pid);
    $u->execute();
    $u->close();

    $l = $db->prepare("INSERT INTO territory_partner_stock_ledger
        (territory_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
        VALUES (?, ?, 'deduct', ?, ?, ?, ?, ?, 'demofree', ?)");
    $l->bind_param('iiiiisss', $tp_id, $pid, $qty, $before, $after, $ref_type, $ref_id, $created_by);
    $l->execute();
    $l->close();
}

function tp_credit_stock($db, $tp_id, $pid, $qty, $ref_type, $ref_id, $created_by) {
    $before = tp_get_closing_qty($db, $tp_id, $pid);
    $after  = $before + $qty;

    $u = $db->prepare("UPDATE territory_partner_stock SET closing_qty=? WHERE territory_partner_id=? AND product_id=?");
    $u->bind_param('iii', $after, $tp_id, $pid);
    $u->execute();
    $u->close();

    $l = $db->prepare("INSERT INTO territory_partner_stock_ledger
        (territory_partner_id, product_id, action, qty, qty_before, qty_after, ref_type, ref_id, note, created_by)
        VALUES (?, ?, 'credit', ?, ?, ?, ?, ?, 'demofree_reversal', ?)");
    $l->bind_param('iiiiisss', $tp_id, $pid, $qty, $before, $after, $ref_type, $ref_id, $created_by);
    $l->execute();
    $l->close();
}

// ── INSERT ────────────────────────────────────────────────────────────────────
if (isset($_REQUEST['add-record'])) {

    $tempid   = preg_replace('/[^A-Z0-9\/]/', '', strtoupper($_REQUEST['tempid'] ?? ''));
    $date     = date("Y-m-d", strtotime($_REQUEST['date'] ?? 'now'));
    $remarks  = htmlspecialchars(strip_tags(trim($_REQUEST['remarks'] ?? '')), ENT_QUOTES, 'UTF-8');
    $category = htmlspecialchars(strip_tags(trim($_REQUEST['category'] ?? '')), ENT_QUOTES, 'UTF-8');
    $usertype = 'territory_partner';
    $userid   = $tp_id;

    $product_ids = $_REQUEST['product_id'] ?? [];
    $qty_arr     = $_REQUEST['qty']        ?? [];

    if (!is_array($product_ids) || count($product_ids) === 0) {
        $_SESSION['errorMessage'] = "No products submitted.";
        header("Location: demofree-new.php?invalid");
        exit;
    }

    $rows = [];
    foreach ($product_ids as $i => $rawPid) {
        $pid = (int)$rawPid;
        $qty = (int)($qty_arr[$i] ?? 0);
        if ($pid <= 0 || $qty <= 0) continue;
        $rows[] = ['pid' => $pid, 'qty' => $qty];
    }

    if (empty($rows)) {
        $_SESSION['errorMessage'] = "No valid products.";
        header("Location: demofree-new.php?invalid");
        exit;
    }

    $created_by = $_SESSION['LOGIN_USER'] ?? 'system';

    $db_conn->begin_transaction();
    try {
        $stmtChk = $db_conn->prepare("SELECT COUNT(*) AS n FROM demofreedamage WHERE tempid=? AND product_id=?");
        $stmtIns = $db_conn->prepare(
            "INSERT INTO demofreedamage (tempid, date, remarks, product_id, qty, category, usertype, userid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($rows as $row) {
            $pid = $row['pid'];
            $qty = $row['qty'];

            $stmtChk->bind_param('si', $tempid, $pid);
            $stmtChk->execute();
            $chkRes  = $stmtChk->get_result();
            $chkRow  = $chkRes->fetch_assoc();
            $chkRes->free();
            if ((int)($chkRow['n'] ?? 0) > 0) continue;

            $stmtIns->bind_param('sssissss', $tempid, $date, $remarks, $pid, $qty, $category, $usertype, $userid);
            $stmtIns->execute();

            tp_deduct_stock($db_conn, $tp_id, $pid, $qty, 'demofree', $tempid, $created_by);
        }

        $stmtChk->close();
        $stmtIns->close();
        $db_conn->commit();

    } catch (\Throwable $e) {
        $db_conn->rollback();
        error_log('[TP demofree INSERT] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $_SESSION['errorMessage'] = "An error occurred. Please try again.";
        header("Location: demofree-new.php?saveerror");
        exit;
    }

    $_SESSION['sucMessage'] = "Demo/Free/Damage Details Added Successfully!";
    header("Location: demofree-manage.php?addsuccess");
    exit;
}

// ── UPDATE (header only — no stock impact) ───────────────────────────────────
if (isset($_REQUEST['update-record'])) {

    $tempid   = preg_replace('/[^A-Z0-9\/]/', '', strtoupper($_REQUEST['update_tempid'] ?? ''));
    $date     = date("Y-m-d", strtotime($_REQUEST['date'] ?? 'now'));
    $remarks  = htmlspecialchars(strip_tags(trim($_REQUEST['remarks'] ?? '')), ENT_QUOTES, 'UTF-8');
    $category = htmlspecialchars(strip_tags(trim($_REQUEST['category'] ?? '')), ENT_QUOTES, 'UTF-8');

    $stmt = $db_conn->prepare("UPDATE demofreedamage SET category=?, date=?, remarks=? WHERE tempid=?");
    $stmt->bind_param('ssss', $category, $date, $remarks, $tempid);
    $stmt->execute();
    $stmt->close();

    $_SESSION['sucMessage'] = "Demo/Free/Damage Details Updated Successfully!";
    header("Location: demofree-manage.php?updatedsuccess");
    exit;
}
?>
