<?php
/**
 * Expense Tracker — Delete a single expense_import_items row.
 *
 * expense-tracker-delete-action.php deletes an entire batch; this deletes
 * one dated line item (added via manual entry or a date-detecting upload)
 * without touching the rest of that month's batch — recomputes the parent
 * expense_imports batch's totals afterward, or removes the batch entirely
 * if that was its last remaining item.
 */

declare(strict_types=1);

header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Content-Type: application/json; charset=utf-8");

session_start();

require_once("checksession.php");
require_once("config.php");
require_once("include/GodownAccess.php");

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (empty($_SESSION['LOGIN_USER_ID'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$item_id = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT) ?: (int)($_POST['item_id'] ?? 0);
if (!$item_id || $item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item']);
    exit;
}

$stmt = $db_conn->prepare("
    SELECT eii.id, eii.import_id, ei.company_id
    FROM expense_import_items eii
    JOIN expense_imports ei ON ei.id = eii.import_id
    WHERE eii.id = ?
");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
    exit;
}

if (!is_godown_allowed($db_conn, (int)$item['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$import_id = (int)$item['import_id'];

mysqli_begin_transaction($db_conn);
try {
    $del = $db_conn->prepare("DELETE FROM expense_import_items WHERE id = ?");
    $del->bind_param("i", $item_id);
    $del->execute();
    $del->close();

    $remaining = $db_conn->prepare("
        SELECT COUNT(*) c, COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) cr,
               COALESCE(SUM(net_amount),0) n, MIN(date) mn, MAX(date) mx
        FROM expense_import_items WHERE import_id = ?
    ");
    $remaining->bind_param("i", $import_id);
    $remaining->execute();
    $r = $remaining->get_result()->fetch_assoc();
    $remaining->close();

    if ((int)$r['c'] === 0) {
        // That was the last item in this batch — drop the now-empty batch too.
        $delb = $db_conn->prepare("DELETE FROM expense_imports WHERE id = ?");
        $delb->bind_param("i", $import_id);
        $delb->execute();
        $delb->close();
    } else {
        $upd = $db_conn->prepare("
            UPDATE expense_imports
            SET total_debit = ?, total_credit = ?, net_amount = ?,
                period_from = COALESCE(?, period_from), period_to = COALESCE(?, period_to)
            WHERE id = ?
        ");
        $upd->bind_param("dddssi", $r['d'], $r['cr'], $r['n'], $r['mn'], $r['mx'], $import_id);
        $upd->execute();
        $upd->close();
    }

    mysqli_commit($db_conn);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    mysqli_rollback($db_conn);
    error_log("Expense tracker item delete error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to delete']);
}
