<?php
include("checksession.php");
include("config.php");
require_once("include/StockService.php");

if (!isset($_REQUEST['add-record'])) {
    exit;
}

$godownid = mysqli_real_escape_string($db_conn, trim($_REQUEST['godownid'] ?? ''));

// Verify opening stock exists for this godown
$stmt = $db_conn->prepare(
    "SELECT COUNT(*) AS n FROM stock WHERE user_type = ? AND user_id = ?"
);
$stmt->bind_param('ss', $Login_user_TYPEvl, $godownid);
$stmt->execute();
$hasStock = (int)$stmt->get_result()->fetch_assoc()['n'];
$stmt->close();

if ($hasStock === 0) {
    echo "<script>window.location='add-return?stocknotupdated&&gid=$godownid';</script>";
    exit;
}

$tempid    = mysqli_real_escape_string($db_conn, $_REQUEST['tempid']    ?? '');
$prid      = (int) ($_REQUEST['prid']      ?? 0);
$returnqty = (int) ($_REQUEST['returnqty'] ?? 0);
$date      = date("Y-m-d", strtotime($_REQUEST['date'] ?? 'now'));
$remarks   = mysqli_real_escape_string($db_conn, $_REQUEST['remarks'] ?? '');

if (!$prid || $returnqty <= 0) {
    echo "<script>window.location='add-return?invalidinput';</script>";
    exit;
}

// Idempotency guard — one return record per tempid
$stmt = $db_conn->prepare(
    "SELECT COUNT(*) AS n FROM company_return_stock WHERE tempid = ?"
);
$stmt->bind_param('s', $tempid);
$stmt->execute();
$alreadyExists = (int)$stmt->get_result()->fetch_assoc()['n'];
$stmt->close();

if ($alreadyExists > 0) {
    echo "<script>window.location='add-return?alreadyexists';</script>";
    exit;
}

$db_conn->begin_transaction();
try {
    // Insert return record
    $stmt = $db_conn->prepare(
        "INSERT INTO company_return_stock (tempid, prid, returnqty, date, remarks, godownid)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('siisss', $tempid, $prid, $returnqty, $date, $remarks, $godownid);
    $stmt->execute();
    $stmt->close();

    // Update stock: returnqty ↑, closing_qty ↓
    // Company returns reduce closing_qty (goods leaving) and increment returnqty column
    $stockService = new StockService($db_conn);
    $row = null;

    // Lock the stock row
    $s = $db_conn->prepare(
        "SELECT returnqty, closing_qty FROM stock
          WHERE product_id = ? AND user_type = ? AND user_id = ?
          FOR UPDATE"
    );
    $s->bind_param('iss', $prid, $Login_user_TYPEvl, $godownid);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$row) {
        throw new \RuntimeException("Stock row not found for product=$prid godown=$godownid");
    }

    $newReturnQty   = (int)$row['returnqty']   + $returnqty;
    $newClosingQty  = (int)$row['closing_qty'] - $returnqty;

    if ($newClosingQty < 0) {
        throw new \RuntimeException(
            "Insufficient stock for company return. Available={$row['closing_qty']}, Return=$returnqty"
        );
    }

    $s = $db_conn->prepare(
        "UPDATE stock SET returnqty = ?, closing_qty = ?, updated_at = NOW()
          WHERE product_id = ? AND user_type = ? AND user_id = ?"
    );
    $s->bind_param('iiiss', $newReturnQty, $newClosingQty, $prid, $Login_user_TYPEvl, $godownid);
    $s->execute();
    $s->close();

    // Write ledger entry
    $stmt = $db_conn->prepare(
        "INSERT INTO stock_ledger
            (product_id, user_type, user_id, action, qty, qty_before, qty_after,
             ref_type, ref_id, note, created_by)
         VALUES (?, ?, ?, 'return_reject', ?, ?, ?, 'return', ?, 'company return stock', ?)"
    );
    $stmt->bind_param(
        'issiiiiss',
        $prid, $Login_user_TYPEvl, $godownid,
        $returnqty, $row['closing_qty'], $newClosingQty,
        $tempid, $_SESSION['LOGIN_USER'] ?? 'system'
    );
    $stmt->execute();
    $stmt->close();

    $db_conn->commit();
    echo "<script>window.location='manage-return?addesuccess';</script>";

} catch (\Throwable $e) {
    $db_conn->rollback();
    error_log("stock_return_update error: " . $e->getMessage());
    $_SESSION['errorMessage'] = "Return failed: " . $e->getMessage();
    echo "<script>window.location='add-return?error';</script>";
}
?>
